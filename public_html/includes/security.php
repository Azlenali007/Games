<?php
/**
 * Aura Security & Helper Utilities
 * CSRF Protection, XSS Escaping, Input Validation & Flash Messaging
 */

require_once __DIR__ . '/config.php';

class Security {
    /**
     * Generate or return existing CSRF token
     */
    public static function csrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Output a hidden CSRF token input field
     */
    public static function csrfField(): string {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Validate submitted CSRF token
     */
    public static function validateCsrf(?string $token): bool {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Terminate with 403 if CSRF validation fails
     */
    public static function requireCsrf(): void {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!self::validateCsrf($token)) {
            http_response_code(403);
            die("Security Token Verification Failed (CSRF). Please refresh and try again.");
        }
    }

    /**
     * Output escaping against XSS
     */
    public static function e(?string $string): string {
        return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Set a flash notification message
     */
    public static function setFlash(string $type, string $message): void {
        $_SESSION['flash_message'] = [
            'type' => $type, // 'success', 'danger', 'warning', 'info'
            'message' => $message
        ];
    }

    /**
     * Retrieve and clear flash notification
     */
    public static function getFlash(): ?array {
        if (!empty($_SESSION['flash_message'])) {
            $flash = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
            return $flash;
        }
        return null;
    }

    /**
     * Format currency display
     */
    public static function formatMoney($amount, string $symbol = '$'): string {
        return $symbol . number_format((float)$amount, 2, '.', ',');
    }

    /**
     * Client IP Address retrieval
     */
    public static function getClientIp(): string {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}

// Convenience alias for output escaping
function e(?string $str): string {
    return Security::e($str);
}
