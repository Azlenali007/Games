<?php
/**
 * Aura Automated Cron Engine
 * Can be executed via CLI: `php cron.php`
 * Or via secured HTTP request: `/cron.php?key=aura_cron_sec_88921a9`
 */

$isCli = (php_sapi_name() === 'cli');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/round_engine.php';

// Verify access key if triggered via HTTP
if (!$isCli) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'cron_secret_key' LIMIT 1");
    $stmt->execute();
    $expectedKey = $stmt->fetchColumn() ?: 'aura_cron_sec_88921a9';

    $providedKey = $_GET['key'] ?? $_SERVER['HTTP_X_CRON_KEY'] ?? '';
    if (!hash_equals($expectedKey, $providedKey)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized cron trigger key.']);
        exit;
    }
}

$startTime = microtime(true);
$db = Database::getInstance()->getConnection();

try {
    // 1. Advance sequential rounds and settlements
    $roundResult = RoundEngine::advanceRounds();

    // 2. Synchronize expired pending transactions (cancel unpaid pending deposits > 24 hours)
    $db->query("
        UPDATE transactions 
        SET status = 'cancelled', admin_notes = 'Auto-expired after 24h of inactivity' 
        WHERE status = 'pending' AND type = 'deposit' AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");

    $executionMs = round((microtime(true) - $startTime) * 1000);
    $status = ($roundResult['status'] === 'locked') ? 'locked' : 'success';
    $message = json_encode($roundResult['actions'] ?? [$roundResult['message'] ?? 'Cron executed normally']);

    // Log cron execution
    $logStmt = $db->prepare("INSERT INTO cron_logs (task_name, status, message, execution_time_ms) VALUES ('master_cron', ?, ?, ?)");
    $logStmt->execute([$status, $message, $executionMs]);

    if ($isCli) {
        echo "[CRON SUCCESS] Runtime: {$executionMs}ms | Status: {$status} | Actions: {$message}\n";
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'execution_time_ms' => $executionMs,
            'round_progression' => $roundResult
        ]);
    }

} catch (Exception $e) {
    $executionMs = round((microtime(true) - $startTime) * 1000);
    $errorMsg = $e->getMessage();

    $logStmt = $db->prepare("INSERT INTO cron_logs (task_name, status, message, execution_time_ms) VALUES ('master_cron', 'failed', ?, ?)");
    $logStmt->execute([$errorMsg, $executionMs]);

    if ($isCli) {
        echo "[CRON ERROR] {$errorMsg}\n";
        exit(1);
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $errorMsg]);
    }
}
