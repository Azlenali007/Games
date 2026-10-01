<?php
/**
 * Admin Result Management & Settlement Control
 * Manual result entry mode, result locking, atomic settlement execution, and audit logs.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/round_engine.php';
require_once __DIR__ . '/../includes/layout_admin.php';

$admin = Auth::requireAdmin();
$db = Database::getInstance()->getConnection();

$error = '';
$success = '';

// Handle Mode Toggle (Auto / Manual)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_mode') {
    Security::requireCsrf();
    $newMode = ($_POST['mode'] === 'manual') ? 'manual' : 'auto';
    $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'round_result_mode'")->execute([$newMode]);

    // Also update active and upcoming rounds
    $db->prepare("UPDATE rounds SET result_mode = ? WHERE status IN ('active', 'upcoming')")->execute([$newMode]);

    // Audit log
    $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, target_entity, target_id, details, ip_address) VALUES (?, 'RESULT_MODE_CHANGED', 'settings', 'round_result_mode', ?, ?)")
       ->execute([Auth::id(), "Result generation mode changed to $newMode", Security::getClientIp()]);

    Security::setFlash('success', "Result generation mode updated to: " . strtoupper($newMode));
    header("Location: /admin/results.php");
    exit;
}

// Handle Manual Result Entry & Locking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'declare_manual_result') {
    Security::requireCsrf();
    $roundId = (int)$_POST['round_id'];
    $selectedOutcome = trim($_POST['declared_outcome'] ?? '');
    $reason = trim($_POST['reason'] ?? 'Official Manual Verification');

    if (!in_array($selectedOutcome, RoundEngine::OUTCOMES, true)) {
        $error = 'Invalid outcome selected.';
    } else {
        $rStmt = $db->prepare("SELECT * FROM rounds WHERE id = ? FOR UPDATE");
        $db->beginTransaction();
        $rStmt->execute([$roundId]);
        $round = $rStmt->fetch();

        if (!$round) {
            $db->rollBack();
            $error = 'Target round not found.';
        } elseif ($round['result_status'] === 'locked' || $round['settlement_status'] === 'settled') {
            $db->rollBack();
            $error = 'Strict Audit Lock: This round result is already officially locked and settled. It cannot be altered.';
        } else {
            // Lock result and execute settlement
            $prevState = $round['result_status'];
            $db->prepare("
                UPDATE rounds 
                SET status = 'completed', betting_status = 'closed', declared_result = ?, result_status = 'locked', updated_at = NOW()
                WHERE id = ?
            ")->execute([$selectedOutcome, $roundId]);

            // Create mandatory immutable audit record
            $db->prepare("
                INSERT INTO round_audit_logs (round_id, admin_id, action, previous_state, new_state, reason)
                VALUES (?, ?, 'MANUAL_RESULT_DECLARED', ?, ?, ?)
            ")->execute([$roundId, Auth::id(), $prevState, $selectedOutcome, $reason]);

            // Execute settlement
            RoundEngine::settleRoundBets($roundId, $selectedOutcome);

            $db->commit();
            Security::setFlash('success', "Result {$selectedOutcome} locked for Round #{$round['round_number']} and bets successfully settled.");
            header("Location: /admin/results.php");
            exit;
        }
    }
}

// Current system mode
$currentMode = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'round_result_mode'")->fetchColumn() ?: 'auto';

// Rounds pending manual result declaration (completed or active past close without result)
$pendingDeclaration = $db->query("
    SELECT * FROM rounds 
    WHERE result_status = 'pending' AND (status = 'completed' OR betting_status = 'closed')
    ORDER BY round_number ASC
")->fetchAll();

// Recent Audit Records
$auditLogs = $db->query("
    SELECT a.*, r.round_number, u.username as admin_user 
    FROM round_audit_logs a
    JOIN rounds r ON a.round_id = r.id
    LEFT JOIN users u ON a.admin_id = u.id
    ORDER BY a.id DESC LIMIT 20
")->fetchAll();

renderAdminHeader('Result & Settlement System', 'results');
?>
<div class="space-y-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-800">
        <div>
            <div class="text-xs font-mono text-slate-500 uppercase">RESULT GOVERNANCE & SETTLEMENT ENGINE</div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Result System & Settlement Engine</h1>
        </div>

        <!-- Mode Toggle Switch -->
        <form method="POST" action="/admin/results.php" class="flex items-center gap-3 p-1.5 rounded-xl bg-slate-900 border border-slate-800">
            <?= Security::csrfField() ?>
            <input type="hidden" name="action" value="toggle_mode">
            <span class="text-xs font-mono text-slate-400 pl-2">Mode:</span>
            
            <button type="submit" name="mode" value="auto" class="px-3 py-1 rounded-lg text-xs font-mono font-bold transition <?= $currentMode === 'auto' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-400 hover:text-white' ?>">
                AUTOMATIC
            </button>
            <button type="submit" name="mode" value="manual" class="px-3 py-1 rounded-lg text-xs font-mono font-bold transition <?= $currentMode === 'manual' ? 'bg-amber-600 text-white shadow-sm' : 'text-slate-400 hover:text-white' ?>">
                MANUAL ENTRY
            </button>
        </form>
    </div>

    <?php if ($error): ?>
        <div class="p-4 rounded-xl bg-red-950/40 border border-red-800 text-red-300 text-xs">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- Pending Result Declaration Queue (If in manual mode or awaiting result) -->
    <div class="p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
        <div>
            <h3 class="text-sm font-bold text-white uppercase tracking-wider font-mono">Rounds Awaiting Result Declaration</h3>
            <p class="text-xs text-slate-400">When manual mode is enabled, operator confirmation is required before payout settlement unlocks.</p>
        </div>

        <?php if (empty($pendingDeclaration)): ?>
            <div class="p-6 rounded-xl bg-[#07090e] border border-slate-800 text-center text-xs text-slate-500 font-mono">
                No rounds currently pending result declaration.
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($pendingDeclaration as $p): ?>
                    <div class="p-5 rounded-xl bg-[#07090e] border border-blue-900/40 flex flex-col md:flex-row md:items-center justify-between gap-4 font-mono">
                        <div>
                            <span class="text-xs text-blue-400 font-bold">ROUND #<?= $p['round_number'] ?></span>
                            <div class="text-xs text-slate-400 mt-1">
                                Betting closed at: <?= $p['betting_closed_at'] ?> UTC · Mode: <?= strtoupper($p['result_mode']) ?>
                            </div>
                        </div>

                        <form method="POST" action="/admin/results.php" class="flex flex-wrap items-center gap-2">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="action" value="declare_manual_result">
                            <input type="hidden" name="round_id" value="<?= $p['id'] ?>">

                            <select name="declared_outcome" required class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white">
                                <option value="">Select Official Outcome...</option>
                                <?php foreach (RoundEngine::OUTCOMES as $o): ?>
                                    <option value="<?= $o ?>"><?= $o ?></option>
                                <?php endforeach; ?>
                            </select>

                            <input type="text" name="reason" placeholder="Audit Reason (e.g. Scored Verification)" required class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white">

                            <button type="submit" onclick="return confirm('Lock result and execute irrevocable wallet settlement?')" class="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold uppercase rounded-lg shadow-sm">
                                Lock & Settle
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Administrative Result Audit Logs Table -->
    <div class="p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
        <div>
            <h3 class="text-sm font-bold text-white uppercase tracking-wider font-mono">Mandatory Result Audit Trail</h3>
            <p class="text-xs text-slate-400">Every declared outcome and locked settlement creates an immutable audit trail entry.</p>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-800 bg-[#07090e]">
            <table class="w-full text-left text-xs font-mono">
                <thead class="bg-slate-900/80 border-b border-slate-800 text-slate-400 uppercase text-[11px]">
                    <tr>
                        <th class="py-3 px-4">Audit ID</th>
                        <th class="py-3 px-4">Round #</th>
                        <th class="py-3 px-4">Action</th>
                        <th class="py-3 px-4">Previous State</th>
                        <th class="py-3 px-4">New Declared State</th>
                        <th class="py-3 px-4">Operator / Source</th>
                        <th class="py-3 px-4">Audit Reason</th>
                        <th class="py-3 px-4 text-right">Timestamp</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($auditLogs)): ?>
                        <tr><td colspan="8" class="py-8 text-center text-slate-500">No audit records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($auditLogs as $al): ?>
                            <tr class="hover:bg-slate-900/30 transition">
                                <td class="py-3 px-4 text-slate-400">#<?= $al['id'] ?></td>
                                <td class="py-3 px-4 font-bold text-white">#<?= $al['round_number'] ?></td>
                                <td class="py-3 px-4 uppercase text-blue-400 font-bold"><?= e($al['action']) ?></td>
                                <td class="py-3 px-4 text-slate-500"><?= e($al['previous_state'] ?: 'None') ?></td>
                                <td class="py-3 px-4 font-bold text-emerald-400"><?= e($al['new_state']) ?></td>
                                <td class="py-3 px-4 text-slate-300"><?= e($al['admin_user'] ?: 'System Engine') ?></td>
                                <td class="py-3 px-4 text-slate-400 max-w-xs truncate"><?= e($al['reason']) ?></td>
                                <td class="py-3 px-4 text-right text-slate-500"><?= e($al['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php renderAdminFooter(); ?>
