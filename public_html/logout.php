<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

Auth::logout();
Security::setFlash('info', 'You have been securely signed out.');
header("Location: /login.php");
exit;
