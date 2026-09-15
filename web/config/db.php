<?php
/**
 * Database connection (PDO) - works on InfinityFree / AIC Cloud shared hosting.
 * No Composer. Edit the constants below with your hosting DB credentials.
 */

// ---- EDIT THESE FOR YOUR HOSTING ACCOUNT ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'supermarket_db');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
// ----------------------------------------------

define('APP_NAME', 'Supermarket Suite');
define('APP_BASE_URL', ''); // e.g. https://yourstore.site.je  (leave blank for relative paths)
define('APP_TIMEZONE', 'Asia/Kolkata');

date_default_timezone_set(APP_TIMEZONE);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die('Database connection failed. Please check config/db.php credentials. (' . htmlspecialchars($e->getMessage()) . ')');
        }
    }
    return $pdo;
}
