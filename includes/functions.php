<?php

function money(float $amount): string {
    return '£' . number_format($amount, 2);
}

function stage_label(string $stage): string {
    $labels = [
        'new'        => 'New',
        'contacted'  => 'Contacted',
        'qualified'  => 'Qualified',
        'quoted'     => 'Quoted',
        'won'        => 'Won',
        'lost'       => 'Lost',
    ];
    return $labels[$stage] ?? ucfirst($stage);
}

function quote_status_label(string $status): string {
    $labels = [
        'draft'    => 'Draft',
        'sent'     => 'Sent',
        'accepted' => 'Accepted',
        'declined' => 'Declined',
        'expired'  => 'Expired',
    ];
    return $labels[$status] ?? ucfirst($status);
}

/**
 * Next quote number for the current year, e.g. HT-2026-0007.
 *
 * Derives the next sequence from the highest existing number *for that year*
 * rather than a plain row COUNT — so deleting a quote never causes a number
 * to be reused, and the sequence is stable. The UNIQUE constraint on
 * quote_number is the real guard against two simultaneous inserts colliding;
 * callers insert inside a transaction and retry once on a duplicate-key error.
 */
function next_quote_number(PDO $pdo): string {
    $year = date('Y');
    $prefix = "HT-{$year}-";
    $stmt = $pdo->prepare(
        "SELECT MAX(CAST(SUBSTRING(quote_number, ?) AS UNSIGNED)) AS mx
         FROM quotes WHERE quote_number LIKE ?"
    );
    // SUBSTRING is 1-indexed; start right after the prefix.
    $stmt->execute([strlen($prefix) + 1, $prefix . '%']);
    $next = (int)($stmt->fetch()['mx'] ?? 0) + 1;
    return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function ticket_status_label(string $status): string {
    $labels = [
        'open'        => 'Open',
        'in_progress' => 'In Progress',
        'closed'      => 'Closed',
    ];
    return $labels[$status] ?? ucfirst($status);
}

function ticket_status_badge(string $status): string {
    // Reuses existing badge colour classes (won=green, new=blue, lost=grey)
    $map = ['open' => 'new', 'in_progress' => 'quoted', 'closed' => 'won'];
    return $map[$status] ?? 'lost';
}

function ticket_priority_label(string $priority): string {
    return ucfirst($priority);
}

function next_ticket_number(PDO $pdo): string {
    $year = date('Y');
    $prefix = "TK-{$year}-";
    $stmt = $pdo->prepare(
        "SELECT MAX(CAST(SUBSTRING(ticket_number, ?) AS UNSIGNED)) AS mx
         FROM tickets WHERE ticket_number LIKE ?"
    );
    $stmt->execute([strlen($prefix) + 1, $prefix . '%']);
    $next = (int)($stmt->fetch()['mx'] ?? 0) + 1;
    return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

/**
 * Create a one-time token for either a new-staff "set your password" invite
 * or a "forgot password" self-service reset. Old unused tokens for the same
 * user+purpose are invalidated first, so only the newest link ever works.
 */
function create_reset_token(PDO $pdo, int $userId, string $purpose, int $ttlHours = 48): string {
    $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND purpose = ? AND used_at IS NULL")
        ->execute([$userId, $purpose]);
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + $ttlHours * 3600);
    $pdo->prepare('INSERT INTO password_resets (user_id, token, purpose, expires_at) VALUES (?,?,?,?)')
        ->execute([$userId, $token, $purpose, $expires]);
    return $token;
}

/**
 * Look up a still-valid (unused, unexpired) token. Returns the reset row
 * joined with the user, or null if the token is missing/expired/used.
 */
function find_valid_reset_token(PDO $pdo, string $token): ?array {
    $stmt = $pdo->prepare(
        'SELECT pr.*, u.name, u.email FROM password_resets pr
         JOIN users u ON u.id = pr.user_id
         WHERE pr.token = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()'
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function consume_reset_token(PDO $pdo, int $resetId): void {
    $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')->execute([$resetId]);
}

/**
 * Human-readable price for a service catalog entry, respecting its
 * pricing model (flat / per-unit / monthly / custom).
 */
function service_price_display(array $service): string {
    switch ($service['price_type']) {
        case 'per_unit':
            return money($service['price']) . ' per ' . e($service['unit_label'] ?: 'unit');
        case 'monthly':
            return money($service['price']) . '/month';
        case 'custom':
            return 'Custom quote';
        case 'flat':
        default:
            return money($service['price']);
    }
}

function service_price_type_label(string $type): string {
    $labels = ['flat' => 'Flat price', 'per_unit' => 'Per unit', 'monthly' => 'Monthly', 'custom' => 'Custom quote'];
    return $labels[$type] ?? ucfirst($type);
}

function quote_request_status_label(string $status): string {
    $labels = ['new' => 'New', 'contacted' => 'Contacted', 'converted' => 'Converted', 'closed' => 'Closed'];
    return $labels[$status] ?? ucfirst($status);
}

function quote_request_status_badge(string $status): string {
    $map = ['new' => 'new', 'contacted' => 'quoted', 'converted' => 'won', 'closed' => 'lost'];
    return $map[$status] ?? 'lost';
}

/**
 * Plain-language, rule-based summary of a discovery form submission,
 * meant to be readable in under 30 seconds before staff dig into the
 * full answers. No AI involved — just assembling sentences from what
 * was actually submitted, so there's no ongoing cost and nothing to
 * misinterpret.
 */
/**
 * Rule-based lead score for a discovery-form submission, so staff can
 * spot high-value or time-wasting leads on the dashboard without
 * reading every detail first. Pure rules, no AI - zero ongoing cost,
 * fully predictable, easy to adjust the thresholds later.
 */
function discovery_lead_score(array $qr): array {
    $budget = $qr['budget_range'] ?? '';
    $isHighBudget = in_array($budget, ['2500 to 5000 GBP', '5000 GBP or more'], true);
    $isQuickWinBudget = $budget === '600 to 1000 GBP';
    $isFixedDeadline = ($qr['timeline_flexibility'] ?? '') === 'Fixed deadline';
    $hasExistingWebsite = !empty($qr['website_url']);
    $isSmallPageCount = ($qr['page_count_bracket'] ?? '') === '1-5';
    $logoReady = ($qr['has_logo'] ?? '') === 'Yes';
    $budgetUnknown = $budget === '' || $budget === 'Custom';
    $serviceUnsure = strpos(strtolower($qr['project_type'] ?? ''), 'not sure') !== false;

    if ($isHighBudget && $isFixedDeadline && $hasExistingWebsite) {
        return ['label' => 'High Value', 'class' => 'badge-won'];
    }
    if ($isQuickWinBudget && $isSmallPageCount && $logoReady) {
        return ['label' => 'Quick Win', 'class' => 'badge-quoted'];
    }
    if ($budgetUnknown && $serviceUnsure) {
        return ['label' => 'Discovery Needed', 'class' => 'badge-lost'];
    }
    return ['label' => 'Standard', 'class' => 'badge-new'];
}

function discovery_staff_summary(array $qr): string {
    $parts = [];

    $services = $qr['project_type'] ? $qr['project_type'] : null;
    $company = $qr['company_name'];
    if ($services) {
        $parts[] = "This is a {$services} inquiry from {$company}.";
    } else {
        $parts[] = "This is an inquiry from {$company}.";
    }

    if ($qr['project_goals']) {
        $parts[] = "They're aiming to: " . lcfirst($qr['project_goals']) . '.';
    }

    if ($qr['solution_type']) {
        $parts[] = "Solution type: {$qr['solution_type']}.";
    }

    if ($qr['budget_range']) {
        $budget = str_replace(' GBP', '', $qr['budget_range']);
        $parts[] = "Self-selected budget: {$budget}.";
    }

    if ($qr['timeline_flexibility'] || $qr['preferred_launch_date']) {
        $timelineBits = [];
        if ($qr['preferred_launch_date']) $timelineBits[] = 'wants to launch around ' . date('d M Y', strtotime($qr['preferred_launch_date']));
        if ($qr['timeline_flexibility']) $timelineBits[] = 'timeline is ' . strtolower($qr['timeline_flexibility']);
        if ($timelineBits) $parts[] = ucfirst(implode(', ', $timelineBits)) . '.';
    }

    $contentBits = [];
    if ($qr['has_content']) $contentBits[] = 'content: ' . strtolower($qr['has_content']);
    if ($qr['has_logo']) $contentBits[] = 'logo: ' . strtolower($qr['has_logo']);
    if ($qr['has_domain']) $contentBits[] = 'domain: ' . strtolower($qr['has_domain']);
    if ($contentBits) $parts[] = ucfirst(implode(', ', $contentBits)) . '.';

    if ($qr['is_urgent'] === 'Yes') {
        $parts[] = 'Flagged as urgent.';
    }

    return implode(' ', $parts);
}

function log_activity(PDO $pdo, ?int $userId, ?int $clientId, ?int $leadId, string $note): void {
    $stmt = $pdo->prepare(
        'INSERT INTO activity_log (client_id, lead_id, user_id, note, created_at)
         VALUES (?, ?, ?, ?, NOW())'
    );
    $stmt->execute([$clientId, $leadId, $userId, $note]);
}