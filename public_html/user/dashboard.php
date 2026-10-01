<?php
/**
 * User Dashboard
 * Real MySQL database information: wallet, active round, transactions, activity.
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

// Active Round Data
$activeRound = RoundEngine::getActiveRound();
$countdown = RoundEngine::getCountdownData($activeRound);

// Recent Transactions
$txStmt = $db->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 5");
$txStmt->execute([$userId]);
$recentTransactions = $txStmt->fetchAll();

// Recent Bets
$betStmt = $db->prepare("
    SELECT b.*, r.round_number, r.status as round_status, r.declared_result 
    FROM bets b
    JOIN rounds r ON b.round_id = r.id
    WHERE b.user_id = ? 
    ORDER BY b.id DESC LIMIT 5
");
$betStmt->execute([$userId]);
$recentBets = $betStmt->fetchAll();

// Recent Ledger Entries
$ledgerStmt = $db->prepare("SELECT * FROM wallet_ledger WHERE user_id = ? ORDER BY id DESC LIMIT 5");
$ledgerStmt->execute([$userId]);
$recentLedger = $ledgerStmt->fetchAll();

// Recent Notifications
$notifStmt = $db->prepare("SELECT * FROM notifications WHERE (user_id = ? OR user_id IS NULL) ORDER BY id DESC LIMIT 4");
$notifStmt->execute([$userId]);
$notifications = $notifStmt->fetchAll();

renderUserHeader('Player Dashboard', 'dashboard');
?>
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- Top Welcome & Quick Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-800">
        <div>
            <div class="text-xs font-mono text-slate-500 uppercase">AURA PLAYER TERMINAL</div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
                Welcome, <?= e($user['full_name'] ?: $user['username']) ?>
            </h1>
        </div>
        <div class="flex items-center gap-3">
            <a href="/user/wallet.php" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg shadow-lg shadow-blue-600/30 transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                <span>Deposit Funds</span>
            </a>
            <a href="/user/rounds.php" class="px-4 py-2 bg-[#0f1422] hover:bg-slate-800 text-slate-200 text-xs font-semibold rounded-lg border border-slate-700 transition flex items-center gap-2">
                <span>Enter Rounds Arena</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
            </a>
        </div>
    </div>

    <!-- Stats Grid: Real Database Values -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Balance Card -->
        <div class="p-5 rounded-xl bg-[#0a0d14] border border-slate-800">
            <span class="text-xs text-slate-500 uppercase font-mono">Available Balance</span>
            <div class="text-2xl font-bold font-mono text-emerald-400 tabular-nums mt-1">
                $<?= number_format((float)$user['wallet_balance'], 2) ?>
            </div>
            <div class="text-[11px] text-slate-500 mt-2 flex items-center justify-between">
                <span>Locked in Withdrawal:</span>
                <span class="font-mono text-slate-300">$<?= number_format((float)$user['locked_balance'], 2) ?></span>
            </div>
        </div>

        <!-- Active Round ID Card -->
        <div class="p-5 rounded-xl bg-[#0a0d14] border border-blue-900/40">
            <span class="text-xs text-slate-500 uppercase font-mono">Current Active Round</span>
            <div class="text-2xl font-bold font-mono text-white mt-1">
                #<?= $countdown['round_number'] ?: '---' ?>
            </div>
            <div class="text-[11px] text-slate-500 mt-2 flex items-center justify-between">
                <span>Status:</span>
                <span class="font-mono font-semibold <?= $countdown['betting_open'] ? 'text-emerald-400' : 'text-red-400' ?>">
                    <?= $countdown['betting_open'] ? 'BETTING OPEN' : 'BETTING CLOSED' ?>
                </span>
            </div>
        </div>

        <!-- Countdown Card -->
        <div class="p-5 rounded-xl bg-[#0a0d14] border border-slate-800">
            <span class="text-xs text-slate-500 uppercase font-mono">Authoritative Timer</span>
            <div id="dash-countdown" class="text-2xl font-black font-mono text-blue-400 tabular-nums mt-1">
                <?= sprintf('%02d:%02d', floor($countdown['seconds_remaining'] / 60), $countdown['seconds_remaining'] % 60) ?>
            </div>
            <div class="text-[11px] text-slate-500 mt-2">
                Server Synchronized · UTC
            </div>
        </div>

        <!-- Referral Code Card -->
        <div class="p-5 rounded-xl bg-[#0a0d14] border border-slate-800">
            <span class="text-xs text-slate-500 uppercase font-mono">Referral Code</span>
            <div class="text-xl font-bold font-mono text-blue-400 mt-1 truncate">
                <?= e($user['referral_code']) ?>
            </div>
            <div class="text-[11px] text-slate-500 mt-2 flex items-center justify-between">
                <span>Commission:</span>
                <span class="font-mono text-slate-300">5.00%</span>
            </div>
        </div>
    </div>

    <!-- Active Sequential Round Participation Card -->
    <div class="p-6 rounded-2xl bg-[#0a0d14] border border-blue-900/50 shadow-xl">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-800">
            <div>
                <span class="text-xs font-mono text-blue-400">ACTIVE SEQUENTIAL ROUND #<?= $countdown['round_number'] ?></span>
                <h3 class="text-lg font-bold text-white mt-0.5">Common Round Channel Participation</h3>
                <p class="text-xs text-slate-400">Fixed 2.0x base multiplier. All outcomes governed by server database timing.</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="text-right">
                    <span class="text-[11px] text-slate-500 block font-mono">Round Closes In</span>
                    <span id="dash-countdown-badge" class="text-xl font-bold font-mono text-blue-400 tabular-nums">
                        <?= $countdown['seconds_remaining'] ?>s
                    </span>
                </div>
                <div id="dash-betting-state" class="px-3 py-1.5 rounded-lg text-xs font-mono font-bold <?= $countdown['betting_open'] ? 'bg-emerald-950/80 border border-emerald-700/60 text-emerald-400' : 'bg-red-950/80 border border-red-700/60 text-red-400' ?>">
                    <?= $countdown['betting_open'] ? 'OPEN' : 'CLOSED' ?>
                </div>
            </div>
        </div>

        <!-- Bet Options Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 my-6">
            <?php foreach (RoundEngine::OUTCOMES as $opt): ?>
                <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-blue-700/60 transition group cursor-pointer" onclick="openBetModal('<?= $opt ?>', <?= (int)$countdown['round_id'] ?>)">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-mono font-bold text-white"><?= $opt ?></span>
                        <span class="text-[10px] font-mono text-emerald-400">2.0x</span>
                    </div>
                    <p class="text-[11px] text-slate-500">Channel Outcome</p>
                    <button type="button" class="mt-3 w-full py-1.5 rounded bg-blue-950/80 group-hover:bg-blue-600 text-blue-300 group-hover:text-white text-xs font-semibold font-mono transition">
                        Select Channel
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center">
            <a href="/user/rounds.php" class="text-xs text-blue-400 hover:text-blue-300 font-medium">
                Open Full Dedicated Rounds Arena with Live History &rarr;
            </a>
        </div>
    </div>

    <!-- Two Column Layout: Recent Transactions & Activity Ledger -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- Recent Transactions -->
        <div class="p-6 rounded-xl bg-[#0a0d14] border border-slate-800">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider">Recent Transactions</h3>
                <a href="/user/wallet.php" class="text-xs text-blue-400 hover:text-blue-300 font-medium">Full Ledger &rarr;</a>
            </div>

            <div class="space-y-3 font-mono text-xs">
                <?php if (empty($recentTransactions)): ?>
                    <p class="text-slate-500 py-4 text-center">No transaction records found.</p>
                <?php else: ?>
                    <?php foreach ($recentTransactions as $tx): ?>
                        <div class="p-3 rounded-lg bg-slate-900/60 border border-slate-800/80 flex items-center justify-between">
                            <div>
                                <span class="font-bold text-white block uppercase"><?= e($tx['type']) ?></span>
                                <span class="text-[11px] text-slate-500"><?= e($tx['transaction_ref']) ?> · <?= e($tx['payment_method']) ?></span>
                            </div>
                            <div class="text-right">
                                <span class="font-bold <?= $tx['type'] === 'deposit' ? 'text-emerald-400' : 'text-slate-300' ?>">
                                    $<?= number_format((float)$tx['amount'], 2) ?>
                                </span>
                                <span class="block text-[10px] uppercase font-semibold <?= 
                                    $tx['status'] === 'approved' ? 'text-emerald-400' : 
                                    ($tx['status'] === 'pending' ? 'text-amber-400' : 'text-red-400') 
                                ?>">
                                    <?= e($tx['status']) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Round Bets -->
        <div class="p-6 rounded-xl bg-[#0a0d14] border border-slate-800">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider">My Recent Bets</h3>
                <a href="/user/rounds.php" class="text-xs text-blue-400 hover:text-blue-300 font-medium">All Bets &rarr;</a>
            </div>

            <div class="space-y-3 font-mono text-xs">
                <?php if (empty($recentBets)): ?>
                    <p class="text-slate-500 py-4 text-center">No bets placed yet. Participate in the active round!</p>
                <?php else: ?>
                    <?php foreach ($recentBets as $b): ?>
                        <div class="p-3 rounded-lg bg-slate-900/60 border border-slate-800/80 flex items-center justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-white">Round #<?= $b['round_number'] ?></span>
                                    <span class="px-1.5 py-0.2 rounded bg-blue-950 text-blue-400 text-[10px]"><?= e($b['option_key']) ?></span>
                                </div>
                                <span class="text-[11px] text-slate-500"><?= e($b['bet_ref']) ?></span>
                            </div>
                            <div class="text-right">
                                <span class="text-slate-300 font-bold">$<?= number_format((float)$b['amount'], 2) ?></span>
                                <span class="block text-[10px] uppercase font-bold <?= 
                                    $b['status'] === 'won' ? 'text-emerald-400' : 
                                    ($b['status'] === 'placed' ? 'text-blue-400' : 'text-slate-500') 
                                ?>">
                                    <?= e($b['status']) ?> <?= $b['status'] === 'won' ? '(+$' . number_format((float)$b['payout_amount'], 2) . ')' : '' ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

</main>

<!-- Bet Placement Modal -->
<div id="bet-modal" class="hidden fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-[#0a0d14] border border-slate-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl">
        <div class="flex items-center justify-between pb-4 border-b border-slate-800">
            <div>
                <h4 class="text-base font-bold text-white">Place Round Bet</h4>
                <span id="modal-round-title" class="text-xs text-blue-400 font-mono">Round #<?= $countdown['round_number'] ?></span>
            </div>
            <button onclick="closeBetModal()" class="text-slate-400 hover:text-white text-lg">&times;</button>
        </div>

        <form id="modal-bet-form" class="mt-4 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= Security::csrfToken() ?>">
            <input type="hidden" name="round_id" id="modal-round-id" value="<?= (int)$countdown['round_id'] ?>">
            <input type="hidden" name="option_key" id="modal-option-key" value="">

            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1">Selected Option Channel</label>
                <input type="text" id="modal-option-display" readonly class="w-full bg-slate-900/60 border border-slate-800 rounded-lg px-3 py-2 text-xs font-mono font-bold text-white cursor-not-allowed">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1">Bet Amount (USD)</label>
                <div class="relative">
                    <span class="absolute left-3 top-2 text-slate-500 font-mono text-xs">$</span>
                    <input type="number" step="1" min="1" max="<?= (float)$user['wallet_balance'] ?>" name="amount" id="modal-amount" value="10" required class="w-full bg-slate-900 border border-slate-700 rounded-lg pl-7 pr-3 py-2 text-xs font-mono font-bold text-white focus:outline-none focus:border-blue-500">
                </div>
                <div class="flex items-center justify-between mt-1 text-[11px] text-slate-500">
                    <span>Available: $<?= number_format((float)$user['wallet_balance'], 2) ?></span>
                    <button type="button" onclick="document.getElementById('modal-amount').value = '<?= (int)$user['wallet_balance'] ?>'" class="text-blue-400 hover:underline">MAX</button>
                </div>
            </div>

            <div class="p-3 rounded-lg bg-[#07090e] border border-slate-800 text-[11px] font-mono space-y-1 text-slate-400">
                <div class="flex justify-between">
                    <span>Multiplier:</span>
                    <span class="text-white">2.00x</span>
                </div>
                <div class="flex justify-between">
                    <span>Potential Payout:</span>
                    <span id="modal-potential-payout" class="text-emerald-400 font-bold">$20.00</span>
                </div>
            </div>

            <div id="modal-error" class="hidden p-2 rounded bg-red-950/40 border border-red-800 text-red-300 text-xs"></div>

            <button type="submit" id="modal-submit-btn" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold uppercase rounded-lg shadow-lg shadow-blue-600/30 transition">
                Confirm Bet Slip &rarr;
            </button>
        </form>
    </div>
</div>

<script>
function openBetModal(optionKey, roundId) {
    document.getElementById('modal-option-key').value = optionKey;
    document.getElementById('modal-option-display').value = optionKey + ' (2.00x Payout)';
    document.getElementById('modal-round-id').value = roundId;
    document.getElementById('modal-error').classList.add('hidden');
    updatePayout();
    document.getElementById('bet-modal').classList.remove('hidden');
}

function closeBetModal() {
    document.getElementById('bet-modal').classList.add('hidden');
}

function updatePayout() {
    var amt = parseFloat(document.getElementById('modal-amount').value) || 0;
    document.getElementById('modal-potential-payout').textContent = '$' + (amt * 2.0).toFixed(2);
}

document.getElementById('modal-amount').addEventListener('input', updatePayout);

document.getElementById('modal-bet-form').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = document.getElementById('modal-submit-btn');
    var errEl = document.getElementById('modal-error');
    btn.disabled = true;
    btn.textContent = 'Recording in Ledger...';
    errEl.classList.add('hidden');

    var formData = new FormData(this);

    fetch('/api/place_bet.php', {
        method: 'POST',
        body: formData
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        btn.disabled = false;
        btn.textContent = 'Confirm Bet Slip →';
        if (data.success) {
            closeBetModal();
            window.location.reload();
        } else {
            errEl.textContent = data.message || 'Bet placement failed.';
            errEl.classList.remove('hidden');
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.textContent = 'Confirm Bet Slip →';
        errEl.textContent = 'Network or server error.';
        errEl.classList.remove('hidden');
    });
});

// Live Synchronized Countdown
(function() {
    var clockEl = document.getElementById('dash-countdown');
    var badgeEl = document.getElementById('dash-countdown-badge');
    var stateEl = document.getElementById('dash-betting-state');

    function sync() {
        fetch('/api/countdown.php')
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success && res.data) {
                    var d = res.data;
                    var rem = d.seconds_remaining;
                    var m = Math.floor(rem / 60);
                    var s = rem % 60;
                    if (clockEl) clockEl.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                    if (badgeEl) badgeEl.textContent = rem + 's';

                    if (stateEl) {
                        if (d.betting_open) {
                            stateEl.textContent = 'OPEN';
                            stateEl.className = 'px-3 py-1.5 rounded-lg text-xs font-mono font-bold bg-emerald-950/80 border border-emerald-700/60 text-emerald-400';
                        } else {
                            stateEl.textContent = 'CLOSED';
                            stateEl.className = 'px-3 py-1.5 rounded-lg text-xs font-mono font-bold bg-red-950/80 border border-red-700/60 text-red-400';
                        }
                    }
                }
            });
    }
    setInterval(sync, 1000);
})();
</script>

<?php renderUserFooter(); ?>
