<?php
/**
 * Database Singleton Connection Class
 * Real MySQL / MariaDB persistence using PDO Prepared Statements
 */

require_once __DIR__ . '/config.php';

class Database {
    private static ?Database $instance = null;
    private ?PDO $pdo = null;

    private function __construct() {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Fallback for Linux unix socket if TCP is restricted
            try {
                $socketDsn = "mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                $this->pdo = new PDO($socketDsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $ex) {
                error_log("Database Connection Critical Failure: " . $ex->getMessage());
                // Never expose raw DB credentials or stack trace to user
                http_response_code(500);
                if (file_exists(__DIR__ . '/../install.php') && !file_exists(__DIR__ . '/../install.lock')) {
                    header("Location: /install.php");
                    exit;
                }
                echo "<!DOCTYPE html><html lang='en'><head><title>System Offline</title><style>body{background:#0a0d14;color:#94a3b8;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}div{text-align:center;padding:2rem;background:#0f1422;border:1px solid #1e293b;border-radius:12px;max-width:480px;}h2{color:#ef4444;margin-top:0;}p{color:#64748b;line-height:1.5;}</style></head><body><div><h2>Database System Offline</h2><p>The secure platform database is currently synchronizing or unreachable. Please try again in a few moments.</p></div></body></html>";
                exit;
            }
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->pdo;
    }

    // Helper wrapper for quick queries
    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::getInstance()->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
