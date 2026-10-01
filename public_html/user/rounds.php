<?php
/**
 * User Sequential Rounds Arena Infrastructure
 * Common framework for active, upcoming, and completed sequential rounds.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/round_engine.php';
require_once __DIR__ . '/../includes/layout_user.php';

$user = Auth::requireLogin();
$userId = (int)$user['id'];
$db = Database::getInstance()->getConnection();

$activeRound = RoundEngine::getActiveRound();
$countdown = RoundEngine::getCountdownData($activeRound);
$upcoming = RoundEngine::getUpcomingRounds(5);
$completed = RoundEngine::getCompletedRounds(15);

// User's bets on active round
$myBets = [];
if ($activeRound) {
    $stmt = $db->prepare("SELECT * FROM bets WHERE round_id = ? AND user_id = ? ORDER BY id DESC");
    $stmt->execute([$activeRound['id'], $userId]);
    $myBets = $stmt->fetchAll();
}

renderUserHeader('Sequential Rounds Arena', 'rounds');
?>
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-800">
        <div>
            <div class="text-xs font-mono text-slate-500 uppercase">SERVER-AUTHORITATIVE ROUND INFRASTRUCTURE</div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Active Sequential Arena</h1>
        </div>

        <div class="flex items-center gap-3">
            <span class="text-xs font-mono text-slate-400">Available Balance:</span>
            <span class="text-sm font-bold font-mono text-emerald-400 px-3 py-1.5 rounded-lg bg-[#0a0d14] border border-slate-800">
                $<?= number_format((float)$user['wallet_balance'], 2) ?>
            </span>
        </div>
    </div>

    <!-- Active Round Master Console -->
    <div class="bg-[#0a0d14] border border-blue-900/50 rounded-2xl p-6 sm:p-8 shadow-2xl relative overflow-hidden">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 border-b border-slate-800">
            <div>
                <span class="text-xs font-mono text-blue-400 tracking-wider">LIVE SEQUENTIAL ROUND</span>
                <div class="text-3xl sm:text-4xl font-black font-mono text-white mt-1">
                    ROUND #<?= $countdown['round_number'] ?: '---' ?>
                </div>
                <div class="flex items-center gap-3 mt-2 text-xs font-mono text-slate-400">
                    <span>Start: <?= substr($activeRound['start_time'] ?? '', 11) ?> UTC</span>
                    <span>·</span>
                    <span>End: <?= substr($activeRound['end_time'] ?? '', 11) ?> UTC</span>
                </div>
            </div>

            <!-- Server Authoritative Countdown Display -->
            <div class="flex items-center gap-6">
                <div class="text-center p-4 rounded-xl bg-[#07090e] border border-slate-800 min-w-[160px]">
                    <div class="text-[10px] font-mono text-slate-500 uppercase tracking-widest">Countdown to Close</div>
                    <div id="arena-timer" class="text-4xl font-black font-mono text-blue-400 tabular-nums mt-1">
                        <?= sprintf('%02d:%02d', floor($countdown['seconds_remaining'] / 60), $countdown['seconds_remaining'] % 60) ?>
                    </div>
                </div>

                <div class="space-y-2">
                    <div id="arena-status-badge" class="px-3.5 py-1.5 rounded-lg text-xs font-mono font-bold text-center uppercase <?= $countdown['betting_open'] ? 'bg-emerald-950/80 border border-emerald-700/60 text-emerald-400' : 'bg-red-950/80 border border-red-700/60 text-red-400' ?>">
                        <?= $countdown['betting_open'] ? 'BETTING OPEN' : 'BETTING CLOSED' ?>
                    </div>
                    <div class="text-[11px] font-mono text-slate-500 text-center">
                        Cutoff: <span id="arena-cutoff" class="text-slate-300"><?= $countdown['betting_seconds_remaining'] ?>s</span> lead
                    </div>
                </div>
            </div>
        </div>

        <!-- Participation Channel Buttons -->
        <div class="mt-8">
            <h3 class="text-sm font-semibold text-white mb-3">Select Participation Channel (Standard 2.00x Payout Multiplier)</h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <?php foreach (RoundEngine::OUTCOMES as $opt): ?>
                    <button type="button" onclick="selectArenaOption('<?= $opt ?>')" class="arena-opt-btn p-5 rounded-xl bg-slate-900/60 border border-slate-800 hover:border-blue-500 text-left transition group">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-base font-bold font-mono text-white group-hover:text-blue-400 transition"><?= $opt ?></span>
                            <span class="text-xs font-mono font-bold text-emerald-400">2.00x</span>
                        </div>
                        <span class="text-xs text-slate-500">Standard Channel</span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Inline Quick Bet Slip -->
        <div id="arena-slip" class="mt-8 p-6 rounded-xl bg-[#07090e] border border-blue-900/40 hidden">
            <form id="arena-bet-form" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= Security::csrfToken() ?>">
                <input type="hidden" name="round_id" value="<?= (int)$countdown['round_id'] ?>">
                <input type="hidden" name="option_key" id="slip-option-key" value="">

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <span class="text-xs text-slate-400">Selected Channel:</span>
                        <div id="slip-option-display" class="text-lg font-bold font-mono text-blue-400">ALPHA_1</div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="relative w-40">
                            <span class="absolute left-3 top-2.5 text-slate-500 font-mono text-xs">$</span>
                            <input type="number" step="1" min="1" max="<?= (float)$user['wallet_balance'] ?>" name="amount" id="slip-amount" value="10" required class="w-full bg-slate-900 border border-slate-700 rounded-lg pl-7 pr-3 py-2 text-xs font-mono font-bold text-white focus:outline-none focus:border-blue-500">
                        </div>

                        <div class="text-xs font-mono text-slate-400">
                            Potential Return: <strong id="slip-payout" class="text-emerald-400 font-bold">$20.00</strong>
                        </div>

                        <button type="submit" id="slip-btn" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold uppercase rounded-lg shadow-lg shadow-blue-600/30 transition">
                            Submit Bet &rarr;
                        </button>
                    </div>
                </div>
                <div id="slip-error" class="hidden p-2 rounded bg-red-950/40 border border-red-800 text-red-300 text-xs"></div>
            </form>
        </div>

        <!-- My Active Bets on this Round -->
        <div class="mt-8 pt-6 border-t border-slate-800">
            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">My Placed Bets on Round #<?= $countdown['round_number'] ?></h4>
            <div class="space-y-2 font-mono text-xs">
                <?php if (empty($myBets)): ?>
                    <p class="text-slate-500 text-xs">You have not placed any bets on this round yet.</p>
                <?php else: ?>
                    <?php foreach ($myBets as $mb): ?>
                        <div class="p-3 rounded-lg bg-slate-900/60 border border-slate-800 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="font-bold text-white"><?= e($mb['bet_ref']) ?></span>
                                <span class="px-2 py-0.5 rounded bg-blue-950 text-blue-400 text-xs font-bold"><?= e($mb['option_key']) ?></span>
                            </div>
                            <div class="text-right">
                                <span class="text-slate-300">$<?= number_format((float)$mb['amount'], 2) ?></span>
                                <span class="text-emerald-400 font-bold ml-2">(Potential: $<?= number_format((float)$mb['potential_payout'], 2) ?>)</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Upcoming & Completed Sequential Queue Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Upcoming Rounds (Sequential Pipeline) -->
        <div class="lg:col-span-4 p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
            <div>
                <h3 class="text-sm font-bold text-white uppercase tracking-wider">Upcoming Sequential Pipeline</h3>
                <p class="text-xs text-slate-400">Pre-scheduled sequential rounds generated by the server.</p>
            </div>

            <div class="space-y-3 font-mono text-xs">
                <?php foreach ($upcoming as $ur): ?>
                    <div class="p-3.5 rounded-xl bg-slate-900/60 border border-slate-800/80 flex items-center justify-between">
                        <div>
                            <span class="font-bold text-white block">Round #<?= $ur['round_number'] ?></span>
                            <span class="text-[11px] text-slate-500">Scheduled: <?= substr($ur['start_time'], 11) ?> UTC</span>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-800 text-slate-400 border border-slate-700">
                            Upcoming
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Completed Rounds Verified History (Safe: Only declared results) -->
        <div class="lg:col-span-8 p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
            <div>
                <h3 class="text-sm font-bold text-white uppercase tracking-wider">Completed Rounds Settlement History</h3>
                <p class="text-xs text-slate-400">Declared outcomes are published strictly after round completion and ledger settlement.</p>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-800 bg-[#07090e]">
                <table class="w-full text-left text-xs font-mono">
                    <thead class="bg-slate-900/80 border-b border-slate-800 text-slate-400 uppercase text-[11px]">
                        <tr>
                            <th class="py-3 px-4">Round ID</th>
                            <th class="py-3 px-4">Declared Outcome</th>
                            <th class="py-3 px-4">Result State</th>
                            <th class="py-3 px-4">Settlement State</th>
                            <th class="py-3 px-4 text-right">Completion Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <?php if (empty($completed)): ?>
                            <tr><td colspan="5" class="py-6 text-center text-slate-500">No completed rounds found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($completed as $c): ?>
                                <tr class="hover:bg-slate-900/30 transition">
                                    <td class="py-3 px-4 font-bold text-white">#<?= $c['round_number'] ?></td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded bg-blue-950 border border-blue-800 text-blue-300 font-bold">
                                            <?= e($c['declared_result']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 uppercase text-emerald-400 font-semibold"><?= e($c['result_status']) ?></td>
                                    <td class="py-3 px-4 uppercase text-slate-300"><?= e($c['settlement_status']) ?></td>
                                    <td class="py-3 px-4 text-right text-slate-500"><?= e($c['end_time']) ?> UTC</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</main>

<script>
function selectArenaOption(key) {
    document.getElementById('slip-option-key').value = key;
    document.getElementById('slip-option-display').textContent = key + ' (2.00x Payout)';
    document.getElementById('arena-slip').classList.remove('hidden');
    updateSlipPayout();
}

function updateSlipPayout() {
    var a = parseFloat(document.getElementById('slip-amount').value) || 0;
    document.getElementById('slip-payout').textContent = '$' + (a * 2.0).toFixed(2);
}

document.getElementById('slip-amount').addEventListener('input', updateSlipPayout);

document.getElementById('arena-bet-form').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = document.getElementById('slip-btn');
    var errEl = document.getElementById('slip-error');
    btn.disabled = true;
    btn.textContent = 'Processing...';
    errEl.classList.add('hidden');

    var fd = new FormData(this);

    fetch('/api/place_bet.php', {
        method: 'POST',
        body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        btn.disabled = false;
        btn.textContent = 'Submit Bet →';
        if (data.success) {
            window.location.reload();
        } else {
            errEl.textContent = data.message || 'Bet placement failed.';
            errEl.classList.remove('hidden');
        }
    })
    .catch(function(e) {
        btn.disabled = false;
        btn.textContent = 'Submit Bet →';
        errEl.textContent = 'Connection error.';
        errEl.classList.remove('hidden');
    });
});

// Authoritative Timer Sync
(function() {
    var timerEl = document.getElementById('arena-timer');
    var badgeEl = document.getElementById('arena-status-badge');
    var cutoffEl = document.getElementById('arena-cutoff');

    function sync() {
        fetch('/api/countdown.php')
            .then(function(res) { return res.json(); })
            .then(function(j) {
                if (j.success && j.data) {
                    var d = j.data;
                    var rem = d.seconds_remaining;
                    var mins = Math.floor(rem / 60);
                    var secs = rem % 60;
                    timerEl.textContent = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');

                    if (d.betting_open) {
                        badgeEl.textContent = 'BETTING OPEN';
                        badgeEl.className = 'px-3.5 py-1.5 rounded-lg text-xs font-mono font-bold text-center uppercase bg-emerald-950/80 border border-emerald-700/60 text-emerald-400';
                        cutoffEl.textContent = d.betting_seconds_remaining + 's';
                    } else {
                        badgeEl.textContent = 'BETTING CLOSED';
                        badgeEl.className = 'px-3.5 py-1.5 rounded-lg text-xs font-mono font-bold text-center uppercase bg-red-950/80 border border-red-700/60 text-red-400';
                        cutoffEl.textContent = 'Closed';
                    }
                }
            });
    }
    setInterval(sync, 1000);
})();
</script>

<?php renderUserFooter(); ?>
