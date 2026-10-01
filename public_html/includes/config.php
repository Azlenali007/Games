<?php
/**
 * Aura Gaming Platform - Master Configuration
 * Standard LAMP / cPanel / Linux PHP Environment
 */

// Strict error reporting for dev, clean for users
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Enforce standard UTC timezone for synchronized round countdowns
date_default_timezone_set('UTC');

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'gaming_platform');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Security & Sessions
define('SESSION_NAME', 'AURA_SESSID');
define('CSRF_TOKEN_SECRET', 'aura_secure_token_secret_key_88f9104');
define('PASSWORD_PEPPER', 'aura_pass_pepper_99x');

// Site Defaults
define('APP_NAME', 'Aura Gaming Platform');
define('APP_ENV', 'production');
define('ROUND_DURATION_DEFAULT', 60); // 60 seconds per round
define('BETTING_CLOSE_LEAD_DEFAULT', 10); // Close betting 10 seconds before round ends

// Start secure session if not started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_name(SESSION_NAME);
    session_start();
}
