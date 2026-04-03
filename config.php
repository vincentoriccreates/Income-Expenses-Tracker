<?php
// ============================================================
// config.php - Database Configuration
// White Villas Resort - Income & Expenses Tracker
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'wvr_tracker');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'WVR Income & Expenses Tracker');
define('APP_VERSION', '1.0.0');
define('CURRENCY', '₱');
define('CURRENCY_CODE', 'PHP');

class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                die('<div style="font-family:sans-serif;padding:40px;background:#fee;border:2px solid red;border-radius:8px;margin:20px;">
                    <h2>Database Connection Error</h2>
                    <p>' . htmlspecialchars($e->getMessage()) . '</p>
                    <p>Please check your database settings in <code>config.php</code></p>
                </div>');
            }
        }
        return self::$instance;
    }

    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetchAll(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    public static function fetch(string $sql, array $params = []): array|false {
        return self::query($sql, $params)->fetch();
    }

    public static function execute(string $sql, array $params = []): bool {
        return self::query($sql, $params)->rowCount() >= 0;
    }

    public static function lastInsertId(): string {
        return self::getInstance()->lastInsertId();
    }
}
