<?php
/**
 * Minimal email sending via PHP's built-in mail() function.
 *
 * On cPanel shared hosting, mail() is normally already configured to send
 * through the account's mail server, so this needs no extra setup. If your
 * host disables mail() or emails land in spam, swap this out for SMTP via
 * PHPMailer (composer require phpmailer/phpmailer) — the call sites in the
 * rest of the app don't need to change, only the inside of send_email().
 */

define('MAIL_FROM_ADDRESS', 'noreply@himaltech.co.uk');
define('MAIL_FROM_NAME', 'Himal Tech');

function send_email(string $toEmail, string $toName, string $subject, string $bodyHtml): bool {
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_ADDRESS . '>';
    $headers[] = 'Reply-To: ' . MAIL_FROM_ADDRESS;

    $ok = @mail($toEmail, $subject, $bodyHtml, implode("\r\n", $headers));
    if (!$ok) {
        error_log("Failed to send email to $toEmail: $subject");
    }
    return $ok;
}

/**
 * Notify a client that a quote has been sent to them, if they have a portal
 * login. Silently does nothing if they don't have one yet (no email to send to).
 */
function notify_quote_sent(PDO $pdo, array $quote, string $companyName): void {
    $stmt = $pdo->prepare("SELECT email, name FROM users WHERE client_id = ? AND role = 'client' AND status = 'active' LIMIT 1");
    $stmt->execute([$quote['client_id']]);
    $recipient = $stmt->fetch();
    if (!$recipient) {
        return; // no portal login yet — nothing to email
    }

    $link = APP_URL . '/quotes/print.php?id=' . $quote['id'];
    $subject = "New quote from Himal Tech — {$quote['quote_number']}";
    $body = "
        <p>Hi {$recipient['name']},</p>
        <p>Himal Tech has sent you a new quote, <strong>{$quote['quote_number']}</strong>, totalling <strong>£" . number_format($quote['total'], 2) . "</strong>.</p>
        <p><a href=\"$link\">View the quote</a> or log into your client portal to see it alongside your other quotes.</p>
        <p>— Himal Tech</p>
    ";
    send_email($recipient['email'], $recipient['name'], $subject, $body);
}

/**
 * Sent when a new staff/admin account is created — instead of sharing a
 * temporary password over chat, they get a secure link to set their own.
 */
function notify_staff_invite(string $toEmail, string $toName, string $token): void {
    $link = APP_URL . '/set-password.php?token=' . $token;
    $subject = 'Your Himal Tech CRM account';
    $body = "
        <p>Hi {$toName},</p>
        <p>An account has been created for you on the Himal Tech CRM.</p>
        <p><a href=\"$link\">Click here to set your password</a> and log in. This link expires in 48 hours.</p>
        <p>If you weren't expecting this, you can ignore this email.</p>
        <p>— Himal Tech</p>
    ";
    send_email($toEmail, $toName, $subject, $body);
}

/**
 * Sent when someone requests a password reset. Deliberately does not
 * reveal whether the email exists — the caller decides that at the form
 * level, not here.
 */
function notify_password_reset(string $toEmail, string $toName, string $token): void {
    $link = APP_URL . '/set-password.php?token=' . $token;
    $subject = 'Reset your Himal Tech CRM password';
    $body = "
        <p>Hi {$toName},</p>
        <p>Someone requested a password reset for your account. If this was you, click below to choose a new password. This link expires in 2 hours.</p>
        <p><a href=\"$link\">Reset my password</a></p>
        <p>If you didn't request this, you can safely ignore this email — your password won't change.</p>
        <p>— Himal Tech</p>
    ";
    send_email($toEmail, $toName, $subject, $body);
}

/**
 * Notify the staff member who created a quote when the client accepts or
 * declines it themselves from their portal.
 */
function notify_quote_response(PDO $pdo, int $staffUserId, array $quote, string $decision): void {
    $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ? AND status = 'active'");
    $stmt->execute([$staffUserId]);
    $recipient = $stmt->fetch();
    if (!$recipient) return;

    $link = APP_URL . '/quotes/view.php?id=' . $quote['id'];
    $verb = $decision === 'accepted' ? 'accepted' : 'declined';
    $subject = "Quote {$verb}: {$quote['quote_number']}";
    $body = "
        <p>Hi {$recipient['name']},</p>
        <p>The client has <strong>$verb</strong> quote <strong>{$quote['quote_number']}</strong> (" . money($quote['total']) . ").</p>
        <p><a href=\"$link\">View the quote</a></p>
        <p>— Himal Tech CRM</p>
    ";
    send_email($recipient['email'], $recipient['name'], $subject, $body);
}

/**
 * Notify a staff member when a lead is assigned to them.
 */
function notify_lead_assigned(PDO $pdo, int $userId, array $lead, string $companyName): void {
    $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ? AND status = 'active'");
    $stmt->execute([$userId]);
    $recipient = $stmt->fetch();
    if (!$recipient) return;

    $link = APP_URL . '/leads/view.php?id=' . $lead['id'];
    $subject = "Lead assigned to you: {$lead['title']}";
    $body = "
        <p>Hi {$recipient['name']},</p>
        <p>You've been assigned a lead: <strong>" . htmlspecialchars($lead['title']) . "</strong> ($companyName).</p>
        <p><a href=\"$link\">View the lead</a></p>
        <p>— Himal Tech CRM</p>
    ";
    send_email($recipient['email'], $recipient['name'], $subject, $body);
}

/**
 * Notify a staff member when a ticket is assigned to them.
 */
function notify_ticket_assigned(PDO $pdo, int $userId, array $ticket): void {
    $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ? AND status = 'active'");
    $stmt->execute([$userId]);
    $recipient = $stmt->fetch();
    if (!$recipient) return;

    $link = APP_URL . '/tickets/view.php?id=' . $ticket['id'];
    $subject = "Ticket assigned to you: {$ticket['ticket_number']}";
    $body = "
        <p>Hi {$recipient['name']},</p>
        <p>You've been assigned ticket <strong>{$ticket['ticket_number']}: " . htmlspecialchars($ticket['subject']) . "</strong>.</p>
        <p><a href=\"$link\">View the ticket</a></p>
        <p>— Himal Tech CRM</p>
    ";
    send_email($recipient['email'], $recipient['name'], $subject, $body);
}

/**
 * Notify a client's portal user when staff reply to their ticket.
 */
function notify_ticket_reply(PDO $pdo, array $ticket): void {
    $stmt = $pdo->prepare("SELECT email, name FROM users WHERE client_id = ? AND role = 'client' AND status = 'active' LIMIT 1");
    $stmt->execute([$ticket['client_id']]);
    $recipient = $stmt->fetch();
    if (!$recipient) {
        return;
    }

    $link = APP_URL . '/portal/ticket_view.php?id=' . $ticket['id'];
    $subject = "Update on your request — {$ticket['ticket_number']}";
    $body = "
        <p>Hi {$recipient['name']},</p>
        <p>Himal Tech has replied to your request <strong>{$ticket['ticket_number']}: " . htmlspecialchars($ticket['subject']) . "</strong>.</p>
        <p><a href=\"$link\">View the conversation</a></p>
        <p>— Himal Tech</p>
    ";
    send_email($recipient['email'], $recipient['name'], $subject, $body);
}
