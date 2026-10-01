<?php
/**
 * User Wallet & Financial Ledger Management
 * Handles Real Deposits, Withdrawals, Transaction Statuses, and Authoritative Ledger.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/wallet.php';
require_once __DIR__ . '/../includes/layout_user.php';

$user = Auth::requireLogin();
$userId = (int)$user['id'];
$db = Database::getInstance()->getConnection();

$tab = $_GET['tab'] ?? 'overview'; // 'overview', 'deposit', 'withdraw', 'ledger'
$error = '';
$success = '';

// Fetch enabled payment gateways
$gwStmt = $db->query("SELECT * FROM payment_gateways WHERE is_enabled = 1 ORDER BY id ASC");
$gateways = $gwStmt->fetchAll();

// Handle Deposit Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'deposit') {
    Security::requireCsrf();
    $gatewayCode = trim($_POST['gateway_code'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $paymentRef = trim($_POST['payment_reference'] ?? '');

    // Validate gateway
    $gStmt = $db->prepare("SELECT * FROM payment_gateways WHERE code = ? AND is_enabled = 1 LIMIT 1");
    $gStmt->execute([$gatewayCode]);
    $gateway = $gStmt->fetch();

    if (!$gateway) {
        $error = 'Selected payment gateway is unavailable.';
    } elseif ($amount < (float)$gateway['min_deposit'] || $amount > (float)$gateway['max_deposit']) {
        $error = "Deposit amount must be between $" . number_format($gateway['min_deposit'], 2) . " and $" . number_format($gateway['max_deposit'], 2);
    } elseif (empty($paymentRef)) {
        $error = 'Please provide the transaction reference / wire memo / blockchain TXID.';
    } else {
        $txRef = 'TX-DEP-' . strtoupper(substr(uniqid(), -6));
        $feePct = (float)$gateway['deposit_fee_pct'];
        $fee = ($amount * $feePct) / 100.0;
        $netAmount = $amount - $fee;

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                INSERT INTO transactions (transaction_ref, user_id, type, amount, fee, net_amount, payment_method, payment_details, status, admin_notes)
                VALUES (?, ?, 'deposit', ?, ?, ?, ?, ?, 'pending', 'User submitted deposit awaiting administrative approval.')
            ");
            $detailsJson = json_encode(['payment_reference' => $paymentRef, 'gateway_code' => $gatewayCode]);
            $stmt->execute([$txRef, $userId, $amount, $fee, $netAmount, $gateway['name'], $detailsJson]);
            $txId = (int)$db->lastInsertId();

            // Insert Payment Log
            $logStmt = $db->prepare("INSERT INTO payment_logs (transaction_id, gateway_code, request_payload, ip_address) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$txId, $gatewayCode, $detailsJson, Security::getClientIp()]);

            // Create user notification
            $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Deposit Request Submitted', ?, 'transaction')")
               ->execute([$userId, "Deposit of $" . number_format($amount, 2) . " ({$txRef}) submitted. Balance will update upon verification."]);

            $db->commit();
            Security::setFlash('success', "Deposit request {$txRef} submitted successfully! Awaiting verification.");
            header("Location: /user/wallet.php?tab=overview");
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Deposit submission failed: ' . $e->getMessage();
        }
    }
}

// Handle Withdrawal Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'withdraw') {
    Security::requireCsrf();
    $amount = (float)($_POST['amount'] ?? 0);
    $method = trim($_POST['method'] ?? 'Bank Wire');
    $destination = trim($_POST['destination'] ?? '');

    $currentBalance = Wallet::getBalance($userId);
    $minWithdraw = 20.00;
    $maxWithdraw = 5000.00;
    $fee = $amount * 0.015; // 1.5% fee
    $netAmount = $amount - $fee;

    if ($amount < $minWithdraw || $amount > $maxWithdraw) {
        $error = "Withdrawal must be between $" . number_format($minWithdraw, 2) . " and $" . number_format($maxWithdraw, 2);
    } elseif ($amount > $currentBalance) {
        $error = "Insufficient available balance. You have $" . number_format($currentBalance, 2) . " available.";
    } elseif (empty($destination)) {
        $error = "Please provide your destination bank account or crypto payout address.";
    } else {
        $txRef = 'TX-WTH-' . strtoupper(substr(uniqid(), -6));
        $db->beginTransaction();

        try {
            // Lock funds from wallet
            $locked = Wallet::lockFunds($userId, $amount, $db);
            if (!$locked) {
                throw new Exception("Unable to lock wallet funds. Check available balance.");
            }

            // Create pending withdrawal transaction
            $stmt = $db->prepare("
                INSERT INTO transactions (transaction_ref, user_id, type, amount, fee, net_amount, payment_method, payment_details, status, admin_notes)
                VALUES (?, ?, 'withdrawal', ?, ?, ?, ?, ?, 'pending', 'Awaiting administrative verification and payout release.')
            ");
            $detailsJson = json_encode(['destination' => $destination, 'method' => $method]);
            $stmt->execute([$txRef, $userId, $amount, $fee, $netAmount, $method, $detailsJson]);

            // Notification
            $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Withdrawal Request Placed', ?, 'transaction')")
               ->execute([$userId, "Withdrawal of $" . number_format($amount, 2) . " ({$txRef}) placed. Funds locked in escrow pending release."]);

            $db->commit();
            Security::setFlash('success', "Withdrawal request {$txRef} placed. Funds are secured in escrow.");
            header("Location: /user/wallet.php?tab=overview");
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Withdrawal failed: ' . $e->getMessage();
        }
    }
}

// Fetch user data refreshed
$wallet = Wallet::getWallet($userId);
$ledger = Wallet::getLedger($userId, 50);

$txStmt = $db->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 50");
$txStmt->execute([$userId]);
$transactions = $txStmt->fetchAll();

renderUserHeader('Wallet & Ledger', 'wallet');
?>
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- Header & Wallet Overview Card -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-800">
        <div>
            <div class="text-xs font-mono text-slate-500 uppercase">TREASURY & AUDIT LEDGER</div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Financial Wallet</h1>
        </div>
        
        <!-- Tab Navigation (Buttons with proper active states) -->
        <div class="flex items-center gap-1.5 p-1 bg-slate-900/80 border border-slate-800 rounded-xl text-xs font-medium">
            <a href="/user/wallet.php?tab=overview" class="px-3.5 py-1.5 rounded-lg transition <?= $tab === 'overview' ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-white' ?>">Overview</a>
            <a href="/user/wallet.php?tab=deposit" class="px-3.5 py-1.5 rounded-lg transition <?= $tab === 'deposit' ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-white' ?>">Deposit</a>
            <a href="/user/wallet.php?tab=withdraw" class="px-3.5 py-1.5 rounded-lg transition <?= $tab === 'withdraw' ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-white' ?>">Withdrawal</a>
            <a href="/user/wallet.php?tab=ledger" class="px-3.5 py-1.5 rounded-lg transition <?= $tab === 'ledger' ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-slate-400 hover:text-white' ?>">Audit Ledger</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="p-4 rounded-xl bg-red-950/40 border border-red-800 text-red-300 text-xs flex items-center gap-3">
            <svg class="w-5 h-5 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Balances Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="p-6 rounded-2xl bg-[#0a0d14] border border-blue-900/40 shadow-xl">
            <span class="text-xs text-slate-500 uppercase font-mono">Liquid Available Balance</span>
            <div class="text-3xl font-extrabold font-mono text-emerald-400 tabular-nums mt-1">
                $<?= number_format((float)$wallet['balance'], 2) ?>
            </div>
            <p class="text-xs text-slate-400 mt-2">Immediately available for active round participation.</p>
        </div>

        <div class="p-6 rounded-2xl bg-[#0a0d14] border border-slate-800">
            <span class="text-xs text-slate-500 uppercase font-mono">Locked in Withdrawal Escrow</span>
            <div class="text-3xl font-extrabold font-mono text-slate-300 tabular-nums mt-1">
                $<?= number_format((float)$wallet['locked_balance'], 2) ?>
            </div>
            <p class="text-xs text-slate-400 mt-2">Held securely pending administrative blockchain/wire release.</p>
        </div>

        <div class="p-6 rounded-2xl bg-[#0a0d14] border border-slate-800">
            <span class="text-xs text-slate-500 uppercase font-mono">Platform Currency</span>
            <div class="text-3xl font-extrabold font-mono text-white mt-1">
                USD ($)
            </div>
            <p class="text-xs text-slate-400 mt-2">Fixed-precision DECIMAL(16,4) MySQL storage.</p>
        </div>
    </div>

    <!-- TAB 1: OVERVIEW & TRANSACTIONS TABLE -->
    <?php if ($tab === 'overview'): ?>
        <div class="p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-white tracking-tight">Recent Transactions</h3>
                    <p class="text-xs text-slate-400">Official deposits and withdrawals recorded in MySQL.</p>
                </div>
                <div class="flex gap-2">
                    <a href="/user/wallet.php?tab=deposit" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                        + New Deposit
                    </a>
                    <a href="/user/wallet.php?tab=withdraw" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-lg border border-slate-700 transition">
                        Withdraw
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-800 bg-[#07090e]">
                <table class="w-full text-left text-xs font-mono">
                    <thead class="bg-slate-900/80 border-b border-slate-800 text-slate-400 uppercase text-[11px]">
                        <tr>
                            <th class="py-3 px-4">Reference</th>
                            <th class="py-3 px-4">Type</th>
                            <th class="py-3 px-4">Method</th>
                            <th class="py-3 px-4">Gross Amount</th>
                            <th class="py-3 px-4">Fee</th>
                            <th class="py-3 px-4">Net Amount</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <?php if (empty($transactions)): ?>
                            <tr><td colspan="8" class="py-8 text-center text-slate-500">No transactions recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $t): ?>
                                <tr class="hover:bg-slate-900/30 transition">
                                    <td class="py-3 px-4 font-bold text-white"><?= e($t['transaction_ref']) ?></td>
                                    <td class="py-3 px-4 uppercase font-semibold <?= $t['type'] === 'deposit' ? 'text-emerald-400' : 'text-blue-400' ?>"><?= e($t['type']) ?></td>
                                    <td class="py-3 px-4 text-slate-300"><?= e($t['payment_method']) ?></td>
                                    <td class="py-3 px-4 text-slate-200 font-bold">$<?= number_format((float)$t['amount'], 2) ?></td>
                                    <td class="py-3 px-4 text-slate-500">$<?= number_format((float)$t['fee'], 2) ?></td>
                                    <td class="py-3 px-4 text-white font-bold">$<?= number_format((float)$t['net_amount'], 2) ?></td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= 
                                            $t['status'] === 'approved' ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' : 
                                            ($t['status'] === 'pending' ? 'bg-amber-950 text-amber-400 border border-amber-800' : 'bg-red-950 text-red-400 border border-red-800') 
                                        ?>">
                                            <?= e($t['status']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-right text-slate-500"><?= e($t['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- TAB 2: DEPOSIT FORM -->
    <?php if ($tab === 'deposit'): ?>
        <div class="max-w-2xl mx-auto p-6 rounded-2xl bg-[#0a0d14] border border-slate-800">
            <h3 class="text-lg font-bold text-white tracking-tight mb-1">Deposit Funds</h3>
            <p class="text-xs text-slate-400 mb-6">Choose an approved payment gateway and transfer funds. All transactions are logged in the ledger upon verification.</p>

            <form method="POST" action="/user/wallet.php?tab=deposit" class="space-y-4">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="deposit">

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Select Payment Gateway</label>
                    <select name="gateway_code" id="dep-gateway" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                        <?php foreach ($gateways as $g): ?>
                            <option value="<?= e($g['code']) ?>" data-min="<?= $g['min_deposit'] ?>" data-max="<?= $g['max_deposit'] ?>" data-fee="<?= $g['deposit_fee_pct'] ?>" data-instructions="<?= e($g['instructions']) ?>">
                                <?= e($g['name']) ?> (Min: $<?= number_format($g['min_deposit'], 2) ?> · Max: $<?= number_format($g['max_deposit'], 2) ?> · Fee: <?= $g['deposit_fee_pct'] ?>%)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="dep-instructions" class="p-4 rounded-xl bg-[#07090e] border border-blue-900/40 text-xs text-slate-300">
                    <!-- Dynamic Gateway Instructions -->
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Deposit Amount (USD)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 text-slate-500 font-mono text-xs">$</span>
                        <input type="number" step="0.01" min="10" name="amount" value="100.00" required class="w-full bg-slate-900 border border-slate-700 rounded-lg pl-7 pr-3 py-2 text-xs font-mono font-bold text-white focus:outline-none focus:border-blue-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Transaction Proof / Wire Reference / Blockchain TXID</label>
                    <input type="text" name="payment_reference" placeholder="e.g. WIRE-884920 or USDT TRC20 64-char Hash" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                    <span class="text-[11px] text-slate-500 mt-1 block">Used by administrators to match bank or blockchain receipts.</span>
                </div>

                <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg shadow-lg shadow-blue-600/30 transition">
                    Submit Deposit Verification &rarr;
                </button>
            </form>
        </div>

        <script>
        function updateInstructions() {
            var sel = document.getElementById('dep-gateway');
            var opt = sel.options[sel.selectedIndex];
            var instr = opt.getAttribute('data-instructions');
            document.getElementById('dep-instructions').innerHTML = '<strong>Payment Instructions:</strong> ' + instr;
        }
        document.getElementById('dep-gateway').addEventListener('change', updateInstructions);
        updateInstructions();
        </script>
    <?php endif; ?>

    <!-- TAB 3: WITHDRAWAL FORM -->
    <?php if ($tab === 'withdraw'): ?>
        <div class="max-w-2xl mx-auto p-6 rounded-2xl bg-[#0a0d14] border border-slate-800">
            <h3 class="text-lg font-bold text-white tracking-tight mb-1">Request Withdrawal</h3>
            <p class="text-xs text-slate-400 mb-6">Withdraw platform funds to your bank or crypto wallet. Funds are placed into escrow immediately.</p>

            <form method="POST" action="/user/wallet.php?tab=withdraw" class="space-y-4">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="withdraw">

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Withdrawal Method</label>
                    <select name="method" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                        <option value="Direct Bank Wire / Instant ACH">Direct Bank Wire / ACH (Min: $50.00)</option>
                        <option value="USDT (TRC-20 Crypto)">USDT (TRC-20 Crypto) (Min: $20.00)</option>
                        <option value="Visa / Mastercard Direct Debit">Visa / Mastercard Direct Debit (Min: $25.00)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Withdrawal Amount (USD)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 text-slate-500 font-mono text-xs">$</span>
                        <input type="number" step="0.01" min="20" max="<?= (float)$wallet['balance'] ?>" name="amount" id="wth-amount" value="50.00" required class="w-full bg-slate-900 border border-slate-700 rounded-lg pl-7 pr-3 py-2 text-xs font-mono font-bold text-white focus:outline-none focus:border-blue-500">
                    </div>
                    <div class="flex items-center justify-between text-[11px] text-slate-500 mt-1">
                        <span>Available: $<?= number_format((float)$wallet['balance'], 2) ?></span>
                        <button type="button" onclick="document.getElementById('wth-amount').value = '<?= (float)$wallet['balance'] ?>'" class="text-blue-400 hover:underline">MAX</button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Destination Details (IBAN / Account # / Crypto Address)</label>
                    <textarea name="destination" rows="2" placeholder="e.g. TRC-20: T9yD14Nj9j7xABz... or IBAN: US89370400440532013000" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500"></textarea>
                </div>

                <div class="p-3 rounded-lg bg-[#07090e] border border-slate-800 text-[11px] font-mono space-y-1 text-slate-400">
                    <div class="flex justify-between">
                        <span>Withdrawal Fee (1.50%):</span>
                        <span id="wth-fee" class="text-slate-300">$0.75</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Net Payout Amount:</span>
                        <span id="wth-net" class="text-emerald-400 font-bold">$49.25</span>
                    </div>
                </div>

                <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg shadow-lg shadow-blue-600/30 transition">
                    Submit Withdrawal Request &rarr;
                </button>
            </form>
        </div>

        <script>
        function updateWth() {
            var a = parseFloat(document.getElementById('wth-amount').value) || 0;
            var fee = a * 0.015;
            var net = Math.max(0, a - fee);
            document.getElementById('wth-fee').textContent = '$' + fee.toFixed(2);
            document.getElementById('wth-net').textContent = '$' + net.toFixed(2);
        }
        document.getElementById('wth-amount').addEventListener('input', updateWth);
        updateWth();
        </script>
    <?php endif; ?>

    <!-- TAB 4: AUTHORITATIVE WALLET LEDGER -->
    <?php if ($tab === 'ledger'): ?>
        <div class="p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
            <div>
                <h3 class="text-base font-bold text-white tracking-tight">Authoritative Financial Ledger</h3>
                <p class="text-xs text-slate-400">Strictly immutable double-entry ledger. Every balance change is accounted for.</p>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-800 bg-[#07090e]">
                <table class="w-full text-left text-xs font-mono">
                    <thead class="bg-slate-900/80 border-b border-slate-800 text-slate-400 uppercase text-[11px]">
                        <tr>
                            <th class="py-3 px-4">Ledger ID</th>
                            <th class="py-3 px-4">Mutation Type</th>
                            <th class="py-3 px-4">Delta Amount</th>
                            <th class="py-3 px-4">Balance Before</th>
                            <th class="py-3 px-4">Balance After</th>
                            <th class="py-3 px-4">Reference</th>
                            <th class="py-3 px-4">Audit Description</th>
                            <th class="py-3 px-4 text-right">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <?php if (empty($ledger)): ?>
                            <tr><td colspan="8" class="py-8 text-center text-slate-500">No ledger entries recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($ledger as $l): ?>
                                <tr class="hover:bg-slate-900/30 transition">
                                    <td class="py-3 px-4 font-bold text-slate-400">#<?= $l['id'] ?></td>
                                    <td class="py-3 px-4 uppercase font-semibold text-blue-400"><?= e($l['type']) ?></td>
                                    <td class="py-3 px-4 font-bold <?= (float)$l['amount'] >= 0 ? 'text-emerald-400' : 'text-red-400' ?>">
                                        <?= (float)$l['amount'] >= 0 ? '+' : '' ?>$<?= number_format((float)$l['amount'], 2) ?>
                                    </td>
                                    <td class="py-3 px-4 text-slate-500">$<?= number_format((float)$l['balance_before'], 2) ?></td>
                                    <td class="py-3 px-4 text-white font-bold">$<?= number_format((float)$l['balance_after'], 2) ?></td>
                                    <td class="py-3 px-4 text-slate-400"><?= e($l['reference_id']) ?></td>
                                    <td class="py-3 px-4 text-slate-400 max-w-xs truncate"><?= e($l['description']) ?></td>
                                    <td class="py-3 px-4 text-right text-slate-500"><?= e($l['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</main>
<?php renderUserFooter(); ?>
