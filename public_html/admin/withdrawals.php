<?php
/**
 * Admin Withdrawal Management
 * Approve or Reject pending withdrawal transactions with escrow balance resolution.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/wallet.php';
require_once __DIR__ . '/../includes/layout_admin.php';

$admin = Auth::requireAdmin();
$db = Database::getInstance()->getConnection();

$error = '';
$success = '';

// Handle Approval / Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    Security::requireCsrf();
    $txId = (int)$_POST['tx_id'];
    $act = $_POST['action'];
    $adminNotes = trim($_POST['admin_notes'] ?? '');

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ? AND type = 'withdrawal' FOR UPDATE");
        $stmt->execute([$txId]);
        $tx = $stmt->fetch();

        if (!$tx) {
            throw new Exception("Withdrawal transaction not found.");
        }
        if ($tx['status'] !== 'pending') {
            throw new Exception("Withdrawal has already been finalized ({$tx['status']}).");
        }

        $userId = (int)$tx['user_id'];
        $amount = (float)$tx['amount'];
        $txRef = $tx['transaction_ref'];

        if ($act === 'approve') {
            // 1. Finalize and burn locked funds
            $burned = Wallet::finalizeLockedFunds($userId, $amount, $db);
            if (!$burned) {
                throw new Exception("Failed to release locked funds.");
            }

            // 2. Insert ledger record for finalized withdrawal
            $w = Wallet::getWallet($userId);
            $currBal = (float)$w['balance'];
            $db->prepare("
                INSERT INTO wallet_ledger (user_id, amount, balance_before, balance_after, type, reference_id, reference_type, description)
                VALUES (?, ?, ?, ?, 'withdrawal', ?, 'transaction', ?)
            ")->execute([$userId, -$amount, $currBal + $amount, $currBal, $txRef, "Finalized payout release via {$tx['payment_method']}"]);

            // 3. Mark transaction approved
            $db->prepare("
                UPDATE transactions 
                SET status = 'approved', admin_notes = ?, approved_by_user_id = ?, updated_at = NOW() 
                WHERE id = ?
            ")->execute([$adminNotes ?: 'Payout released by administrator.', Auth::id(), $txId]);

            // Notification
            $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Withdrawal Approved & Released', ?, 'transaction')")
               ->execute([$userId, "Your withdrawal of $" . number_format($amount, 2) . " ({$txRef}) has been sent."]);

            $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, target_entity, target_id, details, ip_address) VALUES (?, 'APPROVE_WITHDRAWAL', 'transactions', ?, ?, ?)")
               ->execute([Auth::id(), $txId, "Approved withdrawal {$txRef} of $$amount", Security::getClientIp()]);

            $db->commit();
            Security::setFlash('success', "Withdrawal {$txRef} approved and released from escrow.");
        } else {
            // Reject and unlock funds back to active wallet balance
            $unlocked = Wallet::unlockFunds($userId, $amount, $db);
            if (!$unlocked) {
                throw new Exception("Failed to unlock escrow funds.");
            }

            // Ledger record for refund to active balance
            $w = Wallet::getWallet($userId);
            $newBal = (float)$w['balance'];
            $db->prepare("
                INSERT INTO wallet_ledger (user_id, amount, balance_before, balance_after, type, reference_id, reference_type, description)
                VALUES (?, ?, ?, ?, 'withdrawal_refund', ?, 'transaction', ?)
            ")->execute([$userId, $amount, $newBal - $amount, $newBal, $txRef, "Withdrawal {$txRef} rejected: funds returned to active balance"]);

            // Mark rejected
            $db->prepare("
                UPDATE transactions 
                SET status = 'rejected', admin_notes = ?, approved_by_user_id = ?, updated_at = NOW() 
                WHERE id = ?
            ")->execute([$adminNotes ?: 'Rejected by administrator. Funds unlocked.', Auth::id(), $txId]);

            // Notification
            $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Withdrawal Rejected - Funds Refunded', ?, 'transaction')")
               ->execute([$userId, "Withdrawal {$txRef} was rejected. $" . number_format($amount, 2) . " returned to your active wallet balance."]);

            $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, target_entity, target_id, details, ip_address) VALUES (?, 'REJECT_WITHDRAWAL', 'transactions', ?, ?, ?)")
               ->execute([Auth::id(), $txId, "Rejected withdrawal {$txRef}", Security::getClientIp()]);

            $db->commit();
            Security::setFlash('warning', "Withdrawal {$txRef} rejected and refunded to user balance.");
        }

        header("Location: /admin/withdrawals.php");
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        $error = "Operation failed: " . $e->getMessage();
    }
}

// Fetch withdrawals
$statusFilter = $_GET['status'] ?? 'pending';
$sql = "
    SELECT t.*, u.username, u.email 
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE t.type = 'withdrawal'
";
$params = [];
if ($statusFilter !== 'all') {
    $sql .= " AND t.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY t.id DESC LIMIT 50";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$withdrawals = $stmt->fetchAll();

renderAdminHeader('Withdrawal Management', 'withdrawals');
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-800">
        <div>
            <div class="text-xs font-mono text-slate-500 uppercase">TREASURY PAYOUT OPERATIONS</div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Withdrawal Management</h1>
        </div>

        <div class="flex items-center gap-1.5 p-1 bg-slate-900 border border-slate-800 rounded-xl text-xs font-medium font-mono">
            <a href="/admin/withdrawals.php?status=pending" class="px-3 py-1.5 rounded-lg transition <?= $statusFilter === 'pending' ? 'bg-blue-600 text-white font-bold' : 'text-slate-400 hover:text-white' ?>">Pending</a>
            <a href="/admin/withdrawals.php?status=approved" class="px-3 py-1.5 rounded-lg transition <?= $statusFilter === 'approved' ? 'bg-blue-600 text-white font-bold' : 'text-slate-400 hover:text-white' ?>">Approved</a>
            <a href="/admin/withdrawals.php?status=rejected" class="px-3 py-1.5 rounded-lg transition <?= $statusFilter === 'rejected' ? 'bg-blue-600 text-white font-bold' : 'text-slate-400 hover:text-white' ?>">Rejected</a>
            <a href="/admin/withdrawals.php?status=all" class="px-3 py-1.5 rounded-lg transition <?= $statusFilter === 'all' ? 'bg-blue-600 text-white font-bold' : 'text-slate-400 hover:text-white' ?>">All</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="p-4 rounded-xl bg-red-950/40 border border-red-800 text-red-300 text-xs">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="p-6 rounded-2xl bg-[#0a0d14] border border-slate-800 space-y-4">
        <div class="overflow-x-auto rounded-xl border border-slate-800 bg-[#07090e]">
            <table class="w-full text-left text-xs font-mono">
                <thead class="bg-slate-900/80 border-b border-slate-800 text-slate-400 uppercase text-[11px]">
                    <tr>
                        <th class="py-3 px-4">Ref #</th>
                        <th class="py-3 px-4">User</th>
                        <th class="py-3 px-4">Method</th>
                        <th class="py-3 px-4">Requested Amount</th>
                        <th class="py-3 px-4">Fee (1.5%)</th>
                        <th class="py-3 px-4">Net Payout</th>
                        <th class="py-3 px-4">Destination</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($withdrawals)): ?>
                        <tr><td colspan="9" class="py-8 text-center text-slate-500">No withdrawal requests found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($withdrawals as $w): ?>
                            <?php $details = json_decode($w['payment_details'] ?? '{}', true); ?>
                            <tr class="hover:bg-slate-900/30 transition">
                                <td class="py-3 px-4 font-bold text-white"><?= e($w['transaction_ref']) ?></td>
                                <td class="py-3 px-4">
                                    <span class="font-bold text-blue-400"><?= e($w['username']) ?></span>
                                    <span class="text-[10px] text-slate-500 block"><?= e($w['email']) ?></span>
                                </td>
                                <td class="py-3 px-4 text-slate-300"><?= e($w['payment_method']) ?></td>
                                <td class="py-3 px-4 font-bold text-slate-200">$<?= number_format((float)$w['amount'], 2) ?></td>
                                <td class="py-3 px-4 text-slate-500">$<?= number_format((float)$w['fee'], 2) ?></td>
                                <td class="py-3 px-4 font-bold text-emerald-400">$<?= number_format((float)$w['net_amount'], 2) ?></td>
                                <td class="py-3 px-4 text-slate-400 max-w-xs truncate" title="<?= e($details['destination'] ?? '') ?>">
                                    <?= e($details['destination'] ?? 'None') ?>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded text-[10px] uppercase font-bold <?= 
                                        $w['status'] === 'approved' ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' :
                                        ($w['status'] === 'pending' ? 'bg-amber-950 text-amber-400 border border-amber-800' : 'bg-red-950 text-red-400 border border-red-800')
                                    ?>">
                                        <?= e($w['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <?php if ($w['status'] === 'pending'): ?>
                                        <div class="flex items-center justify-end gap-2">
                                            <form method="POST" action="/admin/withdrawals.php" class="inline-block">
                                                <?= Security::csrfField() ?>
                                                <input type="hidden" name="tx_id" value="<?= $w['id'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" onclick="return confirm('Authorize and release payout of $<?= number_format($w['net_amount'], 2) ?>?')" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded text-[11px] font-bold">
                                                    Release
                                                </button>
                                            </form>
                                            <form method="POST" action="/admin/withdrawals.php" class="inline-block">
                                                <?= Security::csrfField() ?>
                                                <input type="hidden" name="tx_id" value="<?= $w['id'] ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" onclick="return confirm('Reject and refund $<?= number_format($w['amount'], 2) ?> to user active balance?')" class="px-2.5 py-1 bg-red-950 hover:bg-red-900 border border-red-800 text-red-300 rounded text-[11px] font-bold">
                                                    Reject & Refund
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-slate-500 text-[11px]">Finalized</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php renderAdminFooter(); ?>
