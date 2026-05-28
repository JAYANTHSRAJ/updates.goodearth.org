<?php
require_once __DIR__ . '/config.php';

class DB
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            // Detect unfilled placeholders
            if (str_contains(DB_NAME, 'YOUR_') || str_contains(DB_USER, 'YOUR_') || str_contains(DB_PASS, 'YOUR_')) {
                die('<div style="font-family:monospace;background:#fff3cd;border:1px solid #ffc107;padding:20px;margin:20px;border-radius:8px;">
                    <strong>⚠️ Database not configured yet.</strong><br><br>
                    Open <code>cms/includes/config.php</code> and replace the placeholder values:<br><br>
                    <code>DB_NAME</code> → your cPanel database name (e.g. <em>cpanelusername_goodearth</em>)<br>
                    <code>DB_USER</code> → your database username (e.g. <em>cpanelusername_dbuser</em>)<br>
                    <code>DB_PASS</code> → your database password<br><br>
                    Find these in cPanel → <strong>MySQL Databases</strong>.
                </div>');
            }

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log('DB connection failed: ' . $e->getMessage());
                die('<div style="font-family:monospace;background:#f8d7da;border:1px solid #f5c6cb;padding:20px;margin:20px;border-radius:8px;">
                    <strong>❌ Database connection failed.</strong><br><br>
                    <strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '<br><br>
                    <strong>Check in <code>cms/includes/config.php</code>:</strong><br>
                    • DB_HOST is <code>' . DB_HOST . '</code> — on GoDaddy this is usually <em>localhost</em><br>
                    • DB_NAME is <code>' . DB_NAME . '</code> — must match exactly what cPanel shows<br>
                    • DB_USER is <code>' . DB_USER . '</code> — must be a user assigned to that database<br>
                    • DB_PASS — double-check the password you set in cPanel<br><br>
                    Go to cPanel → <strong>MySQL Databases</strong> to verify all details.
                </div>');
            }
        }
        return self::$instance;
    }

    // Prevent cloning/unserialization
    public function __clone() {}
    public function __wakeup() {}
}

/**
 * Shortcut helper — returns the PDO singleton.
 */
function db(): PDO
{
    return DB::getInstance();
}
