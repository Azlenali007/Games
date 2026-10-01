<?php
/**
 * Admin Promotions & Bonus Campaigns
 * Real promotional campaigns, coupon codes, user eligibility, redemption records.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/layout_admin.php';

$admin = Auth::requireAdmin();
$db = Database::getInstance()->getConnection();

$error = '';
$success = '';

// Handle Create Promotion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_promo') {
    Security::requireCsrf();
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $title = trim($_POST['title'] ?? '');
    $type = trim($_POST['bonus_type'] ?? 'percentage');
    $value = (float)($_POST['bonus_value'] ?? 0);
    $minDep = (float)($_POST['min_deposit'] ?? 0);
    $maxBonus = (float)($_POST['max_bonus'] ?? 0);
    $maxUses = (int)($_POST['max_uses'] ?? 1000);
    $expires = date('Y-m-d 23:59:59', strtotime('+30 days'));

    if (empty($code) || empty($title) || $value <= 0) {
        $error = 'Valid code, title, and bonus value are required.';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO promotions (code, title, bonus_type, bonus_value, min_deposit, max_bonus, max_uses, starts_at, expires_at, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, 1)
            ");
            $stmt->execute([$code, $title, $type, $value, $minDep, $maxBonus, $maxUses, $expires]);

            $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, target_entity, target_id, details, ip_address) VALUES (?, 'CREATE_PROMOTION', 'promotions', ?, ?, ?)")
               ->execute([Auth::id(), $code, "Created promo campaign $title", Security::getClientIp()]);

            Security::setFlash('success', "Promotion campaign {$code} created successfully.");
            header("Location: /admin/promotions.php");
            exit;
        } catch (Exception $e) {
            $error = 'Failed to create promotion: ' . $e->getMessage();
        }
    }
}

// Fetch all promotions
$promotions = $db->query("SELECT * FROM promotions ORDER BY id DESC")->fetchAll();

// Fetch redemptions history
$redemptions = $db->query("
    SELECT up.*, u.username, p.code, p.title 
    FROM user_promotions up
    JOIN users u ON up.user_id = u.id
    JOIN promotions p ON up.promotion_id = p.id
    ORDER BY up.id DESC LIMIT 50
")->fetchAll();

renderAdminHeader('Promotions & Bonuses', 'promotions');
?>
<div class="space-y-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-800">
        <div>
            <div class="text-xs font-mono text-slate-500 uppercase">INCENTIVES & ENGAGEMENT</div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Promotions & Bonus Campaigns</h1>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="p-4 rounded-xl bg-red-950/40 border border-red-800 text-red-300 text-xs"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- New Promotion Form -->
        <div class="lg:col-span-5 p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
            <h3 class="text-sm font-bold text-white uppercase tracking-wider font-mono">Create Promotional Campaign</h3>
            <form method="POST" action="/admin/promotions.php" class="space-y-4">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="create_promo">

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Coupon Code (Uppercase)</label>
                    <input type="text" name="code" placeholder="e.g. VIPBOOST50" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Campaign Title</label>
                    <input type="text" name="title" placeholder="e.g. Weekend 50% Reload Bonus" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Bonus Type</label>
                        <select name="bonus_type" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white">
                            <option value="percentage">Percentage (%)</option>
                            <option value="fixed">Fixed Cash ($)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Bonus Value</label>
                        <input type="number" step="0.01" name="bonus_value" value="50" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white font-mono">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Min Deposit Required</label>
                        <input type="number" step="0.01" name="min_deposit" value="25" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Max Cap Amount ($)</label>
                        <input type="number" step="0.01" name="max_bonus" value="200" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white font-mono">
                    </div>
                </div>

                <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold uppercase rounded-lg shadow-sm transition">
                    Provision Promotion &rarr;
                </button>
            </form>
        </div>

        <!-- Active Promotions List -->
        <div class="lg:col-span-7 p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
            <h3 class="text-sm font-bold text-white uppercase tracking-wider font-mono">Active Promotional Campaigns</h3>
            <div class="overflow-x-auto rounded-xl border border-slate-800 bg-[#07090e]">
                <table class="w-full text-left text-xs font-mono">
                    <thead class="bg-slate-900/80 border-b border-slate-800 text-slate-400 uppercase text-[11px]">
                        <tr>
                            <th class="py-3 px-4">Coupon</th>
                            <th class="py-3 px-4">Title</th>
                            <th class="py-3 px-4">Value</th>
                            <th class="py-3 px-4">Redemptions</th>
                            <th class="py-3 px-4 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <?php foreach ($promotions as $p): ?>
                            <tr class="hover:bg-slate-900/30 transition">
                                <td class="py-3 px-4 font-bold text-blue-400"><?= e($p['code']) ?></td>
                                <td class="py-3 px-4 text-white"><?= e($p['title']) ?></td>
                                <td class="py-3 px-4 font-bold text-emerald-400">
                                    <?= $p['bonus_type'] === 'percentage' ? number_format($p['bonus_value'], 0) . '%' : '$' . number_format($p['bonus_value'], 2) ?>
                                </td>
                                <td class="py-3 px-4 text-slate-300"><?= $p['used_count'] ?> / <?= $p['max_uses'] ?></td>
                                <td class="py-3 px-4 text-right">
                                    <span class="px-2 py-0.5 rounded text-[10px] uppercase font-bold <?= $p['is_active'] ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' : 'bg-red-950 text-red-400 border border-red-800' ?>">
                                        <?= $p['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php renderAdminFooter(); ?>
