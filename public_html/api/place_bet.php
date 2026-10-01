<?php
/**
 * Common Bet Placement API Endpoint
 * Handles secure user bet placement across sequential rounds with ACID balance debit.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/round_engine.php';

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please sign in to place bets.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// CSRF check
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!Security::validateCsrf($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security token invalid. Refresh page.']);
    exit;
}

$roundId = (int)($_POST['round_id'] ?? 0);
$optionKey = trim($_POST['option_key'] ?? '');
$amount = (float)($_POST['amount'] ?? 0);

if ($amount < 1.0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Minimum bet amount is $1.00.']);
    exit;
}

if (!in_array($optionKey, RoundEngine::OUTCOMES, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid round option selected.']);
    exit;
}

$result = RoundEngine::placeBet(Auth::id(), $roundId, $optionKey, $amount);

if (!$result['success']) {
    http_response_code(400);
}

echo json_encode($result);
