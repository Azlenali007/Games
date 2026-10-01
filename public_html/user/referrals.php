<?php
/**
 * User Referral Program Management
 * Real referral code, link, referred users, commission records.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/layout_user.php';

$user = Auth::requireLogin();
$userId = (int)$user['id'];
$db = Database::getInstance()->getConnection();

$referralCode = $user['referral_code'];
$appUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$referralLink = $appUrl . '/register.php?ref=' . $referralCode;

// Fetch referred users
$refStmt = $db->prepare("
    SELECT r.*, u.username, u.email, u.created_at as joined_at,
           (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = u.id AND type='deposit' AND status='approved') as total_deposited
    FROM referrals r
    JOIN users u ON r.referee_id = u.id
    WHERE r.referrer_id = ?
    ORDER BY r.id DESC
");
$refStmt->execute([$userId]);
$referredUsers = $refStmt->fetchAll();

// Fetch commission ledger
$commStmt = $db->prepare("SELECT * FROM referral_commissions WHERE referrer_id = ? ORDER BY id DESC LIMIT 50");
$commStmt->execute([$userId]);
$commissions = $commStmt->fetchAll();

// Total commission earned
$totalCommission = (float)$db->query("SELECT COALESCE(SUM(total_commission), 0) FROM referrals WHERE referrer_id = $userId")->fetchColumn();

renderUserHeader('Referral Program', 'referrals');
?>
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-800">
        <div>
            <div class="text-xs font-mono text-slate-500 uppercase">AFFILIATE & COMMISSION ENGINE</div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Referral Program</h1>
        </div>
        <div class="px-4 py-2 rounded-xl bg-blue-950/60 border border-blue-800 text-xs font-mono text-blue-300">
            Tier 1 Commission Rate: <strong>5.00% Net</strong>
        </div>
    </div>

    <!-- Referral Link Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2 p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
            <h3 class="text-base font-bold text-white">Your Unique Referral Invite Link</h3>
            <p class="text-xs text-slate-400">Share your invite link with potential players. Commission is automatically deposited into your wallet upon referee activity.</p>
            
            <div class="flex items-center gap-2">
                <input type="text" id="ref-link" readonly value="<?= e($referralLink) ?>" class="flex-1 bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs font-mono text-white select-all">
                <button type="button" onclick="copyRefLink()" id="copy-btn" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                    Copy Link
                </button>
            </div>

            <div class="flex items-center gap-4 text-xs font-mono text-slate-400 pt-2">
                <span>Direct Code: <strong class="text-white"><?= e($referralCode) ?></strong></span>
                <span>·</span>
                <span>Attribution: Lifetime Cookie</span>
            </div>
        </div>

        <div class="p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 flex flex-col justify-center">
            <span class="text-xs text-slate-500 uppercase font-mono">Total Commission Earned</span>
            <div class="text-3xl font-extrabold font-mono text-emerald-400 tabular-nums mt-1">
                $<?= number_format($totalCommission, 2) ?>
            </div>
            <span class="text-xs text-slate-400 mt-2">Referred Players: <?= count($referredUsers) ?></span>
        </div>
    </div>

    <!-- Referred Users Table -->
    <div class="p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
        <h3 class="text-base font-bold text-white tracking-tight">Referred Player Records</h3>
        <div class="overflow-x-auto rounded-xl border border-slate-800 bg-[#07090e]">
            <table class="w-full text-left text-xs font-mono">
                <thead class="bg-slate-900/80 border-b border-slate-800 text-slate-400 uppercase text-[11px]">
                    <tr>
                        <th class="py-3 px-4">Referee Username</th>
                        <th class="py-3 px-4">Joined Date</th>
                        <th class="py-3 px-4">Commission Rate</th>
                        <th class="py-3 px-4">Total Deposited</th>
                        <th class="py-3 px-4 text-right">Commission Generated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($referredUsers)): ?>
                        <tr><td colspan="5" class="py-8 text-center text-slate-500">No players referred yet. Share your invite link above!</td></tr>
                    <?php else: ?>
                        <?php foreach ($referredUsers as $ru): ?>
                            <tr class="hover:bg-slate-900/30 transition">
                                <td class="py-3 px-4 font-bold text-white"><?= e($ru['username']) ?></td>
                                <td class="py-3 px-4 text-slate-400"><?= substr($ru['joined_at'], 0, 10) ?></td>
                                <td class="py-3 px-4 text-blue-400"><?= number_format($ru['commission_rate'], 2) ?>%</td>
                                <td class="py-3 px-4 text-slate-200">$<?= number_format($ru['total_deposited'], 2) ?></td>
                                <td class="py-3 px-4 text-right text-emerald-400 font-bold">$<?= number_format($ru['total_commission'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
function copyRefLink() {
    var copyText = document.getElementById("ref-link");
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value);
    var btn = document.getElementById("copy-btn");
    btn.textContent = "Copied!";
    setTimeout(function() { btn.textContent = "Copy Link"; }, 2000);
}
</script>

<?php renderUserFooter(); ?>
