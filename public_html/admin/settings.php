<?php
/**
 * Admin System Settings & Payment Gateways Configuration
 * Maintenance mode toggle, legal text, payment gateways, cron automation settings.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/layout_admin.php';

$admin = Auth::requireAdmin();
$db = Database::getInstance()->getConnection();

$tab = $_GET['tab'] ?? 'general'; // 'general', 'gateways', 'automation'
$error = '';
$success = '';

// Handle General Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_general') {
    Security::requireCsrf();
    $siteName = trim($_POST['site_name'] ?? 'Aura Gaming Platform');
    $maintMode = isset($_POST['maintenance_mode']) ? '1' : '0';
    $maintMsg = trim($_POST['maintenance_message'] ?? '');
    $contactEmail = trim($_POST['contact_email'] ?? '');
    $contactPhone = trim($_POST['contact_phone'] ?? '');

    $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'site_name'")->execute([$siteName]);
    $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'maintenance_mode'")->execute([$maintMode]);
    $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'maintenance_message'")->execute([$maintMsg]);
    $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'contact_email'")->execute([$contactEmail]);
    $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'contact_phone'")->execute([$contactPhone]);

    $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, target_entity, target_id, details, ip_address) VALUES (?, 'UPDATE_SETTINGS', 'settings', 'general', 'Updated site configuration', ?)")
       ->execute([Auth::id(), Security::getClientIp()]);

    Security::setFlash('success', 'General platform settings updated.');
    header("Location: /admin/settings.php?tab=general");
    exit;
}

// Handle Payment Gateway Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_gateway') {
    Security::requireCsrf();
    $gwId = (int)$_POST['gateway_id'];
    $enabled = isset($_POST['is_enabled']) ? 1 : 0;
    $minDep = (float)($_POST['min_deposit'] ?? 10);
    $maxDep = (float)($_POST['max_deposit'] ?? 5000);
    $minWth = (float)($_POST['min_withdrawal'] ?? 20);
    $maxWth = (float)($_POST['max_withdrawal'] ?? 2500);
    $depFee = (float)($_POST['deposit_fee_pct'] ?? 0);
    $wthFee = (float)($_POST['withdrawal_fee_pct'] ?? 1.5);
    $instructions = trim($_POST['instructions'] ?? '');

    $stmt = $db->prepare("
        UPDATE payment_gateways 
        SET is_enabled = ?, min_deposit = ?, max_deposit = ?, min_withdrawal = ?, max_withdrawal = ?, deposit_fee_pct = ?, withdrawal_fee_pct = ?, instructions = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$enabled, $minDep, $maxDep, $minWth, $maxWth, $depFee, $wthFee, $instructions, $gwId]);

    $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, target_entity, target_id, details, ip_address) VALUES (?, 'UPDATE_GATEWAY', 'payment_gateways', ?, 'Updated gateway parameters', ?)")
       ->execute([Auth::id(), $gwId, Security::getClientIp()]);

    Security::setFlash('success', 'Payment gateway configuration saved.');
    header("Location: /admin/settings.php?tab=gateways");
    exit;
}

// Handle Automation / Cron Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_automation') {
    Security::requireCsrf();
    $duration = (int)($_POST['round_duration_seconds'] ?? 60);
    $cutoff = (int)($_POST['betting_close_lead_seconds'] ?? 10);

    $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'round_duration_seconds'")->execute([$duration]);
    $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'betting_close_lead_seconds'")->execute([$cutoff]);

    Security::setFlash('success', 'Round automation parameters saved.');
    header("Location: /admin/settings.php?tab=automation");
    exit;
}

// Fetch all settings
$settingsRows = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$gateways = $db->query("SELECT * FROM payment_gateways ORDER BY id ASC")->fetchAll();
$cronLogs = $db->query("SELECT * FROM cron_logs ORDER BY id DESC LIMIT 10")->fetchAll();

renderAdminHeader('System Settings', 'settings');
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-800">
        <div>
            <div class="text-xs font-mono text-slate-500 uppercase">PLATFORM CONFIGURATION</div>
            <h1 class="text-2xl font-bold text-white tracking-tight">System Settings & Gateways</h1>
        </div>

        <div class="flex items-center gap-1.5 p-1 bg-slate-900 border border-slate-800 rounded-xl text-xs font-mono font-medium">
            <a href="/admin/settings.php?tab=general" class="px-3.5 py-1.5 rounded-lg transition <?= $tab === 'general' ? 'bg-blue-600 text-white font-bold' : 'text-slate-400 hover:text-white' ?>">General</a>
            <a href="/admin/settings.php?tab=gateways" class="px-3.5 py-1.5 rounded-lg transition <?= $tab === 'gateways' ? 'bg-blue-600 text-white font-bold' : 'text-slate-400 hover:text-white' ?>">Payment Gateways</a>
            <a href="/admin/settings.php?tab=automation" class="px-3.5 py-1.5 rounded-lg transition <?= $tab === 'automation' ? 'bg-blue-600 text-white font-bold' : 'text-slate-400 hover:text-white' ?>">Cron Automation</a>
        </div>
    </div>

    <!-- TAB 1: GENERAL SETTINGS -->
    <?php if ($tab === 'general'): ?>
        <div class="max-w-3xl p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-6">
            <div>
                <h3 class="text-base font-bold text-white font-mono uppercase">Global Website Settings</h3>
                <p class="text-xs text-slate-400">Core identity and maintenance mode controls.</p>
            </div>

            <form method="POST" action="/admin/settings.php?tab=general" class="space-y-4 text-xs font-mono">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="save_general">

                <div>
                    <label class="block text-slate-400 mb-1">Platform Brand Name</label>
                    <input type="text" name="site_name" value="<?= e($settingsRows['site_name'] ?? 'Aura Gaming Platform') ?>" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-slate-400 mb-1">Support Contact Email</label>
                        <input type="email" name="contact_email" value="<?= e($settingsRows['contact_email'] ?? 'support@auragaming.io') ?>" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white">
                    </div>
                    <div>
                        <label class="block text-slate-400 mb-1">Support Hotline / Phone</label>
                        <input type="text" name="contact_phone" value="<?= e($settingsRows['contact_phone'] ?? '+1 (800) 555-AURA') ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white">
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-[#07090e] border border-slate-800 space-y-3">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="maintenance_mode" value="1" <?= ($settingsRows['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?> class="w-4 h-4 rounded text-blue-600 bg-slate-900 border-slate-700">
                        <span class="text-xs text-white font-bold">Enable Maintenance Mode (Restricts public access)</span>
                    </label>
                    <div>
                        <label class="block text-slate-400 mb-1">Maintenance Message Displayed</label>
                        <textarea name="maintenance_message" rows="2" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white"><?= e($settingsRows['maintenance_message'] ?? 'Platform under scheduled maintenance.') ?></textarea>
                    </div>
                </div>

                <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-lg shadow-sm">
                    Save General Configuration
                </button>
            </form>
        </div>
    <?php endif; ?>

    <!-- TAB 2: PAYMENT GATEWAYS -->
    <?php if ($tab === 'gateways'): ?>
        <div class="space-y-6">
            <div>
                <h3 class="text-base font-bold text-white font-mono uppercase">Payment Gateway Management</h3>
                <p class="text-xs text-slate-400">Configure deposit and withdrawal limits, fees, and instructions.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 font-mono text-xs">
                <?php foreach ($gateways as $gw): ?>
                    <div class="p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                            <div>
                                <span class="font-bold text-white block text-sm"><?= e($gw['name']) ?></span>
                                <span class="text-[10px] text-slate-500"><?= e($gw['code']) ?></span>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] uppercase font-bold <?= $gw['is_enabled'] ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' : 'bg-red-950 text-red-400 border border-red-800' ?>">
                                <?= $gw['is_enabled'] ? 'Active' : 'Disabled' ?>
                            </span>
                        </div>

                        <form method="POST" action="/admin/settings.php?tab=gateways" class="space-y-3">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="action" value="save_gateway">
                            <input type="hidden" name="gateway_id" value="<?= $gw['id'] ?>">

                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="is_enabled" value="1" <?= $gw['is_enabled'] ? 'checked' : '' ?> class="rounded text-blue-600 bg-slate-900 border-slate-700">
                                <span class="text-slate-300">Enable this gateway</span>
                            </label>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-[10px] text-slate-500 block">Min Deposit ($)</label>
                                    <input type="number" step="0.01" name="min_deposit" value="<?= (float)$gw['min_deposit'] ?>" class="w-full bg-slate-900 border border-slate-700 rounded p-1.5 text-white">
                                </div>
                                <div>
                                    <label class="text-[10px] text-slate-500 block">Max Deposit ($)</label>
                                    <input type="number" step="0.01" name="max_deposit" value="<?= (float)$gw['max_deposit'] ?>" class="w-full bg-slate-900 border border-slate-700 rounded p-1.5 text-white">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-[10px] text-slate-500 block">Min Withdraw ($)</label>
                                    <input type="number" step="0.01" name="min_withdrawal" value="<?= (float)$gw['min_withdrawal'] ?>" class="w-full bg-slate-900 border border-slate-700 rounded p-1.5 text-white">
                                </div>
                                <div>
                                    <label class="text-[10px] text-slate-500 block">Max Withdraw ($)</label>
                                    <input type="number" step="0.01" name="max_withdrawal" value="<?= (float)$gw['max_withdrawal'] ?>" class="w-full bg-slate-900 border border-slate-700 rounded p-1.5 text-white">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-[10px] text-slate-500 block">Deposit Fee (%)</label>
                                    <input type="number" step="0.1" name="deposit_fee_pct" value="<?= (float)$gw['deposit_fee_pct'] ?>" class="w-full bg-slate-900 border border-slate-700 rounded p-1.5 text-white">
                                </div>
                                <div>
                                    <label class="text-[10px] text-slate-500 block">Withdraw Fee (%)</label>
                                    <input type="number" step="0.1" name="withdrawal_fee_pct" value="<?= (float)$gw['withdrawal_fee_pct'] ?>" class="w-full bg-slate-900 border border-slate-700 rounded p-1.5 text-white">
                                </div>
                            </div>

                            <div>
                                <label class="text-[10px] text-slate-500 block mb-1">User Instructions / Escrow Info</label>
                                <textarea name="instructions" rows="2" class="w-full bg-slate-900 border border-slate-700 rounded p-1.5 text-white text-[11px]"><?= e($gw['instructions']) ?></textarea>
                            </div>

                            <button type="submit" class="w-full py-1.5 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded">
                                Update Gateway
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- TAB 3: CRON & AUTOMATION SETTINGS -->
    <?php if ($tab === 'automation'): ?>
        <div class="max-w-3xl space-y-6 font-mono text-xs">
            <div class="p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
                <div>
                    <h3 class="text-base font-bold text-white uppercase">Sequential Round Timing Rules</h3>
                    <p class="text-slate-400">Controls round lifespan and betting closure thresholds.</p>
                </div>

                <form method="POST" action="/admin/settings.php?tab=automation" class="space-y-4">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="action" value="save_automation">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-slate-400 mb-1">Round Duration (Seconds)</label>
                            <input type="number" step="5" min="30" max="3600" name="round_duration_seconds" value="<?= e($settingsRows['round_duration_seconds'] ?? '60') ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg p-2 text-white">
                            <span class="text-[10px] text-slate-500 mt-1 block">Default: 60s per round</span>
                        </div>
                        <div>
                            <label class="block text-slate-400 mb-1">Betting Close Lead Time (Seconds)</label>
                            <input type="number" step="1" min="5" max="120" name="betting_close_lead_seconds" value="<?= e($settingsRows['betting_close_lead_seconds'] ?? '10') ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg p-2 text-white">
                            <span class="text-[10px] text-slate-500 mt-1 block">Closes betting N seconds prior to round end</span>
                        </div>
                    </div>

                    <div class="p-4 rounded-xl bg-[#07090e] border border-slate-800 text-[11px] text-slate-400 space-y-2">
                        <div><strong>CLI Cron Command:</strong> <code>* * * * * php <?= __DIR__ ?>/../cron.php</code></div>
                        <div><strong>HTTP Trigger Endpoint:</strong> <code>/cron.php?key=<?= e($settingsRows['cron_secret_key'] ?? 'aura_cron_sec_88921a9') ?></code></div>
                    </div>

                    <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-lg shadow-sm">
                        Save Automation Timing
                    </button>
                </form>
            </div>

            <!-- Recent Cron Logs -->
            <div class="p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider">Recent Cron Execution Logs</h3>
                <div class="overflow-x-auto rounded-xl border border-slate-800 bg-[#07090e]">
                    <table class="w-full text-left text-xs font-mono">
                        <thead class="bg-slate-900/80 border-b border-slate-800 text-slate-400 uppercase text-[10px]">
                            <tr>
                                <th class="p-2.5">ID</th>
                                <th class="p-2.5">Task</th>
                                <th class="p-2.5">Status</th>
                                <th class="p-2.5">Execution Time</th>
                                <th class="p-2.5 text-right">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <?php foreach ($cronLogs as $cl): ?>
                                <tr>
                                    <td class="p-2.5 text-slate-500">#<?= $cl['id'] ?></td>
                                    <td class="p-2.5 text-white font-bold"><?= e($cl['task_name']) ?></td>
                                    <td class="p-2.5 uppercase font-bold <?= $cl['status'] === 'success' ? 'text-emerald-400' : 'text-amber-400' ?>"><?= e($cl['status']) ?></td>
                                    <td class="p-2.5 text-slate-300"><?= $cl['execution_time_ms'] ?>ms</td>
                                    <td class="p-2.5 text-right text-slate-500"><?= e($cl['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php renderAdminFooter(); ?>
