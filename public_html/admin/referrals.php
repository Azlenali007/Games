<?php
/**
 * Admin Referral System Management
 * Commission configuration, referral tree reports, commission ledger.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/layout_admin.php';

$admin = Auth::requireAdmin();
$db = Database::getInstance()->getConnection();

// Handle Commission Rate update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_rate') {
    Security::requireCsrf();
    $rate = (float)($_POST['default_referral_pct'] ?? 5.0);

    $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'default_referral_pct'")->execute([$rate]);
    $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, target_entity, target_id, details, ip_address) VALUES (?, 'UPDATE_REFERRAL_RATE', 'settings', 'default_referral_pct', ?, ?)")
       ->execute([Auth::id(), "Updated default referral rate to $rate%", Security::getClientIp()]);

    Security::setFlash('success', "Default referral commission rate updated to $rate%.");
    header("Location: /admin/referrals.php");
    exit;
}

$defaultRate = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'default_referral_pct'")->fetchColumn() ?: '5.00';

$referralsList = $db->query("
    SELECT r.*, 
           u1.username as referrer_user, u1.email as referrer_email,
           u2.username as referee_user, u2.email as referee_email
    FROM referrals r
    JOIN users u1 ON r.referrer_id = u1.id
    JOIN users u2 ON r.referee_id = u2.id
    ORDER BY r.id DESC LIMIT 50
")->fetchAll();

$commissionsList = $db->query("
    SELECT c.*, 
           u1.username as referrer_user,
           u2.username as referee_user
    FROM referral_commissions c
    JOIN users u1 ON c.referrer_id = u1.id
    JOIN users u2 ON c.referee_id = u2.id
    ORDER BY c.id DESC LIMIT 50
")->fetchAll();

renderAdminHeader('Referral Architecture', 'referrals');
?>
<div class="space-y-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-800">
        <div>
            <div class="text-xs font-mono text-slate-500 uppercase">AFFILIATE ENGINE</div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Referral & Commission Control</h1>
        </div>

        <form method="POST" action="/admin/referrals.php" class="flex items-center gap-2">
            <?= Security::csrfField() ?>
            <input type="hidden" name="action" value="update_rate">
            <label class="text-xs font-mono text-slate-400">Default Rate (%):</label>
            <input type="number" step="0.1" min="0" max="50" name="default_referral_pct" value="<?= e($defaultRate) ?>" class="w-20 bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1 text-xs text-white font-mono">
            <button type="submit" class="px-3 py-1 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg shadow-sm">
                Save
            </button>
        </form>
    </div>

    <!-- Referral Relationships -->
    <div class="p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
        <div>
            <h3 class="text-sm font-bold text-white uppercase tracking-wider font-mono">Active Referral Relationships</h3>
            <p class="text-xs text-slate-400">Database linkage between referrers and attributed new accounts.</p>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-800 bg-[#07090e]">
            <table class="w-full text-left text-xs font-mono">
                <thead class="bg-slate-900/80 border-b border-slate-800 text-slate-400 uppercase text-[11px]">
                    <tr>
                        <th class="py-3 px-4">Ref Link ID</th>
                        <th class="py-3 px-4">Referrer Account</th>
                        <th class="py-3 px-4">Attributed Referee</th>
                        <th class="py-3 px-4">Commission %</th>
                        <th class="py-3 px-4">Cumulative Commission Paid</th>
                        <th class="py-3 px-4 text-right">Established At</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($referralsList)): ?>
                        <tr><td colspan="6" class="py-6 text-center text-slate-500">No referral relationships active yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($referralsList as $rf): ?>
                            <tr class="hover:bg-slate-900/30 transition">
                                <td class="py-3 px-4 text-slate-400">#<?= $rf['id'] ?></td>
                                <td class="py-3 px-4 font-bold text-blue-400"><?= e($rf['referrer_user']) ?></td>
                                <td class="py-3 px-4 text-white"><?= e($rf['referee_user']) ?></td>
                                <td class="py-3 px-4 font-bold text-emerald-400"><?= number_format($rf['commission_rate'], 2) ?>%</td>
                                <td class="py-3 px-4 font-bold text-white">$<?= number_format((float)$rf['total_commission'], 2) ?></td>
                                <td class="py-3 px-4 text-right text-slate-500"><?= e($rf['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Commission Payout Ledger -->
    <div class="p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
        <div>
            <h3 class="text-sm font-bold text-white uppercase tracking-wider font-mono">Commission Distribution Ledger</h3>
            <p class="text-xs text-slate-400">All referral ledger credits dispatched directly into referrer wallets.</p>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-800 bg-[#07090e]">
            <table class="w-full text-left text-xs font-mono">
                <thead class="bg-slate-900/80 border-b border-slate-800 text-slate-400 uppercase text-[11px]">
                    <tr>
                        <th class="py-3 px-4">Commission ID</th>
                        <th class="py-3 px-4">Beneficiary</th>
                        <th class="py-3 px-4">Generated By</th>
                        <th class="py-3 px-4">Amount Credited</th>
                        <th class="py-3 px-4">Event Source</th>
                        <th class="py-3 px-4 text-right">Timestamp</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($commissionsList)): ?>
                        <tr><td colspan="6" class="py-6 text-center text-slate-500">No commissions recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($commissionsList as $cl): ?>
                            <tr class="hover:bg-slate-900/30 transition">
                                <td class="py-3 px-4 text-slate-400">#<?= $cl['id'] ?></td>
                                <td class="py-3 px-4 font-bold text-blue-400"><?= e($cl['referrer_user']) ?></td>
                                <td class="py-3 px-4 text-white"><?= e($cl['referee_user']) ?></td>
                                <td class="py-3 px-4 font-bold text-emerald-400">+$<?= number_format((float)$cl['amount'], 2) ?></td>
                                <td class="py-3 px-4 uppercase text-slate-400"><?= e($cl['source_type']) ?> #<?= $cl['source_id'] ?></td>
                                <td class="py-3 px-4 text-right text-slate-500"><?= e($cl['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php renderAdminFooter(); ?>
