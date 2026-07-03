<?php
/**
 * Shared file-upload handling for client attachments and ticket attachments.
 * Files are stored outside any web-executable path assumptions: the uploads/
 * folder has a .htaccess that blocks script execution, and every file is
 * renamed on disk (original name is kept only in the database) so an
 * uploaded file can never be requested directly by a guessed URL.
 */

define('UPLOAD_MAX_BYTES', 8 * 1024 * 1024); // 8MB per file
define('UPLOAD_ALLOWED_EXT', ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx','csv','txt','zip']);

/**
 * Validate and move an uploaded file into /uploads/{subfolder}/.
 * Returns ['original_filename', 'stored_filename', 'filesize'] on success,
 * or throws a RuntimeException with a user-safe message on failure.
 */
function handle_upload(array $file, string $subfolder): array {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('No file was selected.');
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('File is too large (max 8MB).');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed. Please try again.');
    }
    // Guard against a spoofed tmp path — only accept genuine PHP uploads.
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Upload failed. Please try again.');
    }
    if ($file['size'] > UPLOAD_MAX_BYTES) {
        throw new RuntimeException('File is too large (max 8MB).');
    }

    $originalName = trim((string)($file['name'] ?? ''));
    if ($originalName === '') {
        throw new RuntimeException('That file has no name.');
    }
    // Keep only the base name (defence against directory components in the name)
    // and cap length so it always fits the 255-char database column.
    $originalName = basename($originalName);
    if (strlen($originalName) > 200) {
        $ext0 = pathinfo($originalName, PATHINFO_EXTENSION);
        $originalName = substr($originalName, 0, 190) . ($ext0 !== '' ? '.' . $ext0 : '');
    }

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === '' || !in_array($ext, UPLOAD_ALLOWED_EXT, true)) {
        throw new RuntimeException('That file type is not allowed.');
    }

    $uploadRoot = __DIR__ . '/../uploads/' . $subfolder;
    if (!is_dir($uploadRoot)) {
        mkdir($uploadRoot, 0755, true);
    }

    $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = $uploadRoot . '/' . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Could not save the uploaded file.');
    }

    // Use the actual size on disk, not the client-reported figure. If the real
    // file somehow exceeds the limit, remove it and reject.
    $actualSize = filesize($destination);
    if ($actualSize === false || $actualSize > UPLOAD_MAX_BYTES) {
        @unlink($destination);
        throw new RuntimeException('File is too large (max 8MB).');
    }

    return [
        'original_filename' => $originalName,
        'stored_filename'   => $storedName,
        'filesize'          => (int)$actualSize,
    ];
}

function human_filesize(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}
