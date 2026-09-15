<?php
// config.php
// Database connection settings.
//
// IMPORTANT — before deploying:
// 1. Fill in your real DB_HOST / DB_NAME / DB_USER / DB_PASS below.
// 2. Keep this file OUTSIDE your public web root if possible (e.g. one level
//    above public_html), or make sure your server config prevents .php files
//    from being served as plain text.
// 3. Never commit real credentials to a public git repo.

define('DB_HOST', 'localhost');
define('DB_NAME', 'fracture_point');
// XAMPP's default MySQL account is root with no password. Change these two
// values if you configured a password or a different database user.
define('DB_USER', 'root');
define('DB_PASS', '');

// Password to view admin.php (who has registered). Change this to something
// only organizers know — it's the only thing gating that page.
define('ADMIN_PASSWORD', '1308');

function get_db_connection(): PDO {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    return new PDO($dsn, DB_USER, DB_PASS, $options);
}
