<?php
// ─── Database Configuration ───────────────────────────────────────────────────
// Edit these values to match your server
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'aquabill');
define('APP_NAME', 'AquaBill');
define('APP_TAGLINE', 'Barangay Water Billing System');
define('RATE_PER_CUBIC', 35.00);
define('BASE_CHARGE', 120.00);

function getDB(): PDO {
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
            die('<div style="font-family:sans-serif;padding:40px;background:#fff0f0;color:#c00;border:2px solid #c00;border-radius:8px;margin:40px auto;max-width:600px">
                <h2>⚠️ Database Connection Failed</h2>
                <p>' . htmlspecialchars($e->getMessage()) . '</p>
                <p>Please check your database settings in <code>config/db.php</code> and ensure MySQL is running.</p>
                <p>Then import <code>config/schema.sql</code> into your database.</p>
            </div>');
        }
    }
    return $pdo;
}
