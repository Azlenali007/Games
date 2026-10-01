<?php
/**
 * Admin Round Management
 * View Active, Upcoming, Completed sequential rounds. Force progression trigger.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/round_engine.php';
require_once __DIR__ . '/../includes/layout_admin.php';

$admin = Auth::requireAdmin();
$db = Database::getInstance()->getConnection();

// Handle Force Round Progression Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['advance_cron'])) {
    Security::requireCsrf();
    $result = RoundEngine::advanceRounds();

    // Log admin activity
    $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, target_entity, target_id, details, ip_address) VALUES (?, 'FORCE_ROUND_ADVANCE', 'rounds', 'all', ?, ?)")
       ->execute([Auth::id(), json_encode($result), Security::getClientIp()]);

    Security::setFlash('success', 'Round progression executed: ' . json_encode($result['actions'] ?? [$result['message']]));
    header("Location: /admin/rounds.php");
    exit;
}

$activeRound = RoundEngine::getActiveRound();
$countdown = RoundEngine::getCountdownData($activeRound);

$upcomingRounds = $db->query("SELECT * FROM rounds WHERE status = 'upcoming' ORDER BY round_number ASC LIMIT 10")->fetchAll();
$completedRounds = $db->query("
    SELECT r.*, 
           (SELECT COUNT(*) FROM bets WHERE round_id = r.id) as total_bets,
           (SELECT COALESCE(SUM(amount), 0) FROM bets WHERE round_id = r.id) as total_bet_amount
    FROM rounds r 
    WHERE r.status = 'completed' 
    ORDER BY r.round_number DESC LIMIT 20
")->fetchAll();

renderAdminHeader('Sequential Round Pipeline', 'rounds');
?>
<div class="space-y-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-800">
        <div>
            <div class="text-xs font-mono text-slate-500 uppercase">SEQUENTIAL TIMELINE ENGINE</div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Round Infrastructure Management</h1>
        </div>

        <form method="POST" action="/admin/rounds.php">
            <?= Security::csrfField() ?>
            <input type="hidden" name="advance_cron" value="1">
            <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg shadow-sm transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                <span>Trigger Round Automation</span>
            </button>
        </form>
    </div>

    <!-- Active Sequential Round Panel -->
    <div class="p-6 rounded-2xl bg-[#0a0d14] border border-blue-900/60 shadow-xl space-y-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                <span class="text-xs font-mono text-blue-400 uppercase font-semibold">Current Active Round</span>
            </div>
            <a href="/admin/live_bets.php" class="text-xs font-mono text-blue-400 hover:underline">Monitor Live Bets &rarr;</a>
        </div>

        <?php if (!$activeRound): ?>
            <p class="text-slate-500 text-xs py-4">No active round in progress. Click "Trigger Round Automation" to generate.</p>
        <?php else: ?>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 font-mono text-xs">
                <div class="p-4 rounded-xl bg-[#07090e] border border-slate-800">
                    <span class="text-slate-500 block uppercase text-[10px]">Sequential Round ID</span>
                    <span class="text-2xl font-bold text-white mt-1 block">#<?= $activeRound['round_number'] ?></span>
                    <span class="text-[10px] text-slate-500">Auto Increment ID: <?= $activeRound['id'] ?></span>
                </div>

                <div class="p-4 rounded-xl bg-[#07090e] border border-slate-800">
                    <span class="text-slate-500 block uppercase text-[10px]">Betting Status</span>
                    <span class="text-lg font-bold uppercase mt-1 block <?= $activeRound['betting_status'] === 'open' ? 'text-emerald-400' : 'text-red-400' ?>">
                        <?= $activeRound['betting_status'] ?>
                    </span>
                    <span class="text-[10px] text-slate-500">Cutoff: <?= substr($activeRound['betting_closed_at'], 11) ?> UTC</span>
                </div>

                <div class="p-4 rounded-xl bg-[#07090e] border border-slate-800">
                    <span class="text-slate-500 block uppercase text-[10px]">Round Time Remaining</span>
                    <span id="rounds-timer" class="text-2xl font-black text-blue-400 tabular-nums mt-1 block">
                        <?= sprintf('%02d:%02d', floor($countdown['seconds_remaining'] / 60), $countdown['seconds_remaining'] % 60) ?>
                    </span>
                    <span class="text-[10px] text-slate-500">Closes at: <?= substr($activeRound['end_time'], 11) ?> UTC</span>
                </div>

                <div class="p-4 rounded-xl bg-[#07090e] border border-slate-800">
                    <span class="text-slate-500 block uppercase text-[10px]">Result Mode</span>
                    <span class="text-lg font-bold text-white uppercase mt-1 block"><?= $activeRound['result_mode'] ?></span>
                    <span class="text-[10px] text-slate-500">Settlement: <?= $activeRound['settlement_status'] ?></span>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- 2 Column Grids: Upcoming Queue & Completed History -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Upcoming Rounds Queue -->
        <div class="lg:col-span-5 p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
            <div>
                <h3 class="text-sm font-bold text-white uppercase tracking-wider">Pre-Scheduled Sequential Queue</h3>
                <p class="text-xs text-slate-400">Rounds ready in database to activate upon active round expiry.</p>
            </div>

            <div class="space-y-2.5 font-mono text-xs">
                <?php foreach ($upcomingRounds as $u): ?>
                    <div class="p-3 rounded-lg bg-slate-900/60 border border-slate-800 flex items-center justify-between">
                        <div>
                            <span class="font-bold text-white block">Round #<?= $u['round_number'] ?></span>
                            <span class="text-[11px] text-slate-500">Start: <?= substr($u['start_time'], 11) ?> UTC</span>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] uppercase font-bold bg-slate-800 text-slate-400 border border-slate-700">
                            Upcoming
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Completed Rounds Table -->
        <div class="lg:col-span-7 p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
            <div>
                <h3 class="text-sm font-bold text-white uppercase tracking-wider">Completed Rounds Settlement Log</h3>
                <p class="text-xs text-slate-400">Rounds with declared results and final audit states.</p>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-800 bg-[#07090e]">
                <table class="w-full text-left text-xs font-mono">
                    <thead class="bg-slate-900/80 border-b border-slate-800 text-slate-400 uppercase text-[11px]">
                        <tr>
                            <th class="py-2.5 px-3">Round ID</th>
                            <th class="py-2.5 px-3">Result</th>
                            <th class="py-2.5 px-3">Result State</th>
                            <th class="py-2.5 px-3">Settlement</th>
                            <th class="py-2.5 px-3">Bets Placed</th>
                            <th class="py-2.5 px-3 text-right">Volume</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <?php foreach ($completedRounds as $cr): ?>
                            <tr class="hover:bg-slate-900/30 transition">
                                <td class="py-2.5 px-3 font-bold text-white">#<?= $cr['round_number'] ?></td>
                                <td class="py-2.5 px-3">
                                    <span class="px-1.5 py-0.5 rounded bg-blue-950 text-blue-400 font-bold border border-blue-800">
                                        <?= e($cr['declared_result']) ?>
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 uppercase text-emerald-400 font-bold"><?= e($cr['result_status']) ?></td>
                                <td class="py-2.5 px-3 uppercase text-slate-300"><?= e($cr['settlement_status']) ?></td>
                                <td class="py-2.5 px-3 text-slate-400"><?= number_format($cr['total_bets']) ?></td>
                                <td class="py-2.5 px-3 text-right text-emerald-400 font-bold">$<?= number_format((float)$cr['total_bet_amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
(function() {
    var timerEl = document.getElementById('rounds-timer');
    function sync() {
        fetch('/api/countdown.php')
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success && res.data && timerEl) {
                    var rem = res.data.seconds_remaining;
                    var m = Math.floor(rem / 60);
                    var s = rem % 60;
                    timerEl.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                }
            });
    }
    setInterval(sync, 1000);
})();
</script>

<?php renderAdminFooter(); ?>
