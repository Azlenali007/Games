<?php
/**
 * Common Round Status & History API
 * Returns current active round, upcoming round count, and completed rounds with verified results.
 * Strictly guarantees unreleased results are NEVER visible.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/round_engine.php';
require_once __DIR__ . '/../includes/auth.php';

$activeRound = RoundEngine::getActiveRound();
$countdown = RoundEngine::getCountdownData($activeRound);
$upcoming = RoundEngine::getUpcomingRounds(4);
$completed = RoundEngine::getCompletedRounds(10);

$userBets = [];
if (Auth::check() && $activeRound) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM bets WHERE round_id = ? AND user_id = ? ORDER BY id DESC");
    $stmt->execute([$activeRound['id'], Auth::id()]);
    $userBets = $stmt->fetchAll();
}

echo json_encode([
    'success' => true,
    'countdown' => $countdown,
    'active_round' => $activeRound ? [
        'id' => (int)$activeRound['id'],
        'round_number' => (int)$activeRound['round_number'],
        'start_time' => $activeRound['start_time'],
        'end_time' => $activeRound['end_time'],
        'betting_closed_at' => $activeRound['betting_closed_at'],
        'betting_status' => $activeRound['betting_status'],
        'status' => $activeRound['status'],
        'available_options' => RoundEngine::OUTCOMES
    ] : null,
    'upcoming_rounds' => array_map(function($r) {
        return [
            'id' => (int)$r['id'],
            'round_number' => (int)$r['round_number'],
            'start_time' => $r['start_time']
        ];
    }, $upcoming),
    'completed_history' => array_map(function($c) {
        return [
            'round_number' => (int)$c['round_number'],
            'declared_result' => $c['declared_result'],
            'settled_at' => $c['end_time']
        ];
    }, $completed),
    'user_active_bets' => $userBets
]);
