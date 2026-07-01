<?php
/**
 * Database connection.
 * Copy this file to db.php (same folder) and fill in your own values
 * from cPanel > MySQL Databases after you create the database and a
 * user attached to it (see README.md, Step 1). db.php is gitignored,
 * so your real credentials never end up in version control.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'your_db_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// Base URL of this app, no trailing slash. Used for redirects/links.
// e.g. https://example.com/crm
define('APP_URL', 'https://example.com/crm');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    die('Something went wrong connecting to the database. Please try again shortly.');
}
