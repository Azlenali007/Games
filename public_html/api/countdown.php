<?php
/**
 * Server-Synchronized Countdown API
 * Authoritative countdown source for client displays.
 * Server time and state are strictly authoritative.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/round_engine.php';

// Advance rounds if active round expired
$round = RoundEngine::getActiveRound();
$countdown = RoundEngine::getCountdownData($round);

echo json_encode([
    'success' => true,
    'data' => $countdown
]);
