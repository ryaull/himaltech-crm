<?php
/**
 * Every uploaded file (client attachments and ticket attachments) is
 * downloaded through this one gateway rather than a direct URL, so access
 * control is enforced in one place: staff/admin can download anything,
 * a client can only download files tied to their own client_id.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$user = current_user();

if ($type === 'client') {
    $stmt = $pdo->prepare('SELECT * FROM client_attachments WHERE id = ?');
    $stmt->execute([$id]);
    $file = $stmt->fetch();
    if (!$file) { http_response_code(404); die('File not found.'); }

    if ($user['role'] === 'client' && $user['client_id'] != $file['client_id']) {
        http_response_code(403); die('Not authorised.');
    }
    $path = __DIR__ . '/../uploads/clients/' . basename($file['stored_filename']);

} elseif ($type === 'ticket') {
    $stmt = $pdo->prepare(
        'SELECT ta.*, t.client_id FROM ticket_attachments ta
         JOIN tickets t ON t.id = ta.ticket_id WHERE ta.id = ?'
    );
    $stmt->execute([$id]);
    $file = $stmt->fetch();
    if (!$file) { http_response_code(404); die('File not found.'); }

    if ($user['role'] === 'client' && $user['client_id'] != $file['client_id']) {
        http_response_code(403); die('Not authorised.');
    }
    $path = __DIR__ . '/../uploads/tickets/' . basename($file['stored_filename']);

} elseif ($type === 'quote_request') {
    $stmt = $pdo->prepare('SELECT * FROM quote_request_attachments WHERE id = ?');
    $stmt->execute([$id]);
    $file = $stmt->fetch();
    if (!$file) { http_response_code(404); die('File not found.'); }

    if ($user['role'] === 'client' && $user['client_id'] != $file['client_id']) {
        http_response_code(403); die('Not authorised.');
    }
    $path = __DIR__ . '/../uploads/quote_requests/' . basename($file['stored_filename']);

} else {
    http_response_code(400); die('Invalid request.');
}

if (!is_file($path)) { http_response_code(404); die('File not found on disk.'); }

// Strip anything that could break out of the header (quotes, newlines, slashes).
$downloadName = preg_replace('/[^A-Za-z0-9._ -]/', '_', basename($file['original_filename']));
if ($downloadName === '' || $downloadName === null) {
    $downloadName = 'download';
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;