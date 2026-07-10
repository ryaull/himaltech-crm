<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin', 'staff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/leads/list.php');
    exit;
}
csrf_check();

$requestId = (int)($_POST['request_id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM quote_requests WHERE id = ?');
$stmt->execute([$requestId]);
$request = $stmt->fetch();

if (!$request) { http_response_code(404); die('Quote request not found.'); }
if ($request['quote_id']) {
    // Already converted — just send them to the existing quote rather than
    // creating a second one from the same request.
    header('Location: ' . APP_URL . '/quotes/view.php?id=' . $request['quote_id']);
    exit;
}

// This intake form collects requirements, not priced selections — there's
// nothing to auto-total. If staff already filled in an internal estimated
// cost, start the draft with that as one line item; otherwise start with a
// single blank line for staff to fill in themselves while reading through
// the client's answers.
if ($request['internal_estimated_cost'] !== null && (float)$request['internal_estimated_cost'] > 0) {
    $subtotal = round((float)$request['internal_estimated_cost'], 2);
    $lineItems = [['Project (initial estimate — review before sending)', 1, $subtotal, $subtotal]];
} else {
    $subtotal = 0;
    $lineItems = [['Project scope — fill in based on client\'s answers', 1, 0, 0]];
}

$taxRate = 20.00;
$taxAmount = round($subtotal * ($taxRate / 100), 2);
$total = $subtotal + $taxAmount;

$quoteId = null;
$attempts = 0;
do {
    $attempts++;
    $quoteNumber = next_quote_number($pdo);
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'INSERT INTO quotes (quote_number, client_id, lead_id, status, issue_date, subtotal, tax_rate, tax_amount, total, notes, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $quoteNumber, $request['client_id'], $request['lead_id'], 'draft', date('Y-m-d'),
            $subtotal, $taxRate, $taxAmount, $total,
            "Linked to quote request #{$request['id']} — see the lead page for the client's full submitted answers.",
            current_user()['id'],
        ]);
        $quoteId = (int)$pdo->lastInsertId();

        $itemStmt = $pdo->prepare(
            'INSERT INTO quote_items (quote_id, description, quantity, unit_price, line_total, sort_order) VALUES (?,?,?,?,?,?)'
        );
        foreach ($lineItems as $idx => $li) {
            $itemStmt->execute([$quoteId, $li[0], $li[1], $li[2], $li[3], $idx]);
        }

        $pdo->prepare('UPDATE quote_requests SET quote_id = ?, status = ? WHERE id = ?')->execute([$quoteId, 'converted', $requestId]);
        $pdo->prepare("UPDATE leads SET stage = 'quoted' WHERE id = ? AND stage NOT IN ('won','lost')")->execute([$request['lead_id']]);

        $pdo->commit();
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $quoteId = null;
        if ($ex->getCode() === '23000' && $attempts < 3) {
            continue;
        }
        error_log('Quote request conversion failed: ' . $ex->getMessage());
        http_response_code(500);
        die('Sorry, the quote could not be created. Please try again.');
    }
} while ($quoteId === null && $attempts < 3);

log_activity($pdo, current_user()['id'], $request['client_id'], $request['lead_id'], "Created quote $quoteNumber linked to this request");

// Land straight in the editable quote builder so staff can review/adjust
// before it's ever sent — nothing here is finalized automatically.
header('Location: ' . APP_URL . '/quotes/edit.php?id=' . $quoteId);
exit;
