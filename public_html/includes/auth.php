<?php
/**
 * Authentication Engine
 * Secure Sessions, Role Validation, Login Rate Limiting & User State
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

class Auth {
    private static ?array $cachedUser = null;

    /**
     * Check if user is currently logged in
     */
    public static function check(): bool {
        return !empty($_SESSION['user_id']);
    }

    /**
     * Get current authenticated user ID
     */
    public static function id(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get current user record
     */
    public static function user(): ?array {
        if (!self::check()) {
            return null;
        }

        if (self::$cachedUser !== null) {
            return self::$cachedUser;
        }

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT u.*, w.balance as wallet_balance, w.locked_balance, w.currency
            FROM users u
            LEFT JOIN wallets w ON u.id = w.user_id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user || $user['status'] === 'blocked') {
            self::logout();
            return null;
        }

        self::$cachedUser = $user;
        return $user;
    }

    /**
     * Check if current user has admin role
     */
    public static function isAdmin(): bool {
        $u = self::user();
        return $u && $u['role'] === 'admin';
    }

    /**
     * Enforce user authentication
     */
    public static function requireLogin(): array {
        if (!self::check()) {
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
            Security::setFlash('warning', 'Please sign in to access your platform account.');
            header("Location: /login.php");
            exit;
        }
        $user = self::user();
        if (!$user) {
            header("Location: /login.php");
            exit;
        }
        return $user;
    }

    /**
     * Enforce admin authentication
     */
    public static function requireAdmin(): array {
        $user = self::requireLogin();
        if ($user['role'] !== 'admin') {
            http_response_code(403);
            Security::setFlash('danger', 'Unauthorized: Administrative credentials required.');
            header("Location: /user/dashboard.php");
            exit;
        }
        return $user;
    }

    /**
     * Authenticate user with rate limiting and logging
     */
    public static function attempt(string $usernameOrEmail, string $password): array {
        $db = Database::getInstance()->getConnection();
        $ip = Security::getClientIp();

        // 1. Check rate limiting (failed logins in last 15 mins)
        $rateStmt = $db->prepare("
            SELECT COUNT(*) FROM user_logins 
            WHERE ip_address = ? AND status = 'failed' AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $rateStmt->execute([$ip]);
        if ($rateStmt->fetchColumn() >= 10) {
            return ['success' => false, 'message' => 'Too many failed login attempts. Please wait 15 minutes.'];
        }

        // 2. Fetch user by username or email
        $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) LIMIT 1");
        $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
        $user = $stmt->fetch();

        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Log failed attempt
            $logStmt = $db->prepare("INSERT INTO user_logins (user_id, ip_address, user_agent, status) VALUES (?, ?, ?, 'failed')");
            $logStmt->execute([$user['id'] ?? 0, $ip, $userAgent]);
            return ['success' => false, 'message' => 'Invalid username/email or password credentials.'];
        }

        if ($user['status'] === 'blocked') {
            return ['success' => false, 'message' => 'This account has been suspended by the platform administrator.'];
        }

        // 3. Login successful - Regenerate session ID for fixation protection
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['last_activity'] = time();

        // Log successful login
        $logStmt = $db->prepare("INSERT INTO user_logins (user_id, ip_address, user_agent, status) VALUES (?, ?, ?, 'success')");
        $logStmt->execute([$user['id'], $ip, $userAgent]);

        return ['success' => true, 'user' => $user];
    }

    /**
     * Terminate user session
     */
    public static function logout(): void {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
}
