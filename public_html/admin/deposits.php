<?php
/**
 * Admin Deposit Management
 * Approve or Reject pending deposit transactions with atomic ledger balance credit.
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

// Handle Deposit Approval / Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    Security::requireCsrf();
    $txId = (int)$_POST['tx_id'];
    $act = $_POST['action']; // 'approve' or 'reject'
    $adminNotes = trim($_POST['admin_notes'] ?? '');

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ? AND type = 'deposit' FOR UPDATE");
        $stmt->execute([$txId]);
        $tx = $stmt->fetch();

        if (!$tx) {
            throw new Exception("Transaction not found.");
        }
        if ($tx['status'] !== 'pending') {
            throw new Exception("Transaction has already been finalized ({$tx['status']}).");
        }

        $userId = (int)$tx['user_id'];
        $netAmount = (float)$tx['net_amount'];
        $txRef = $tx['transaction_ref'];

        if ($act === 'approve') {
            // 1. Credit User Wallet with atomic ledger logging
            $creditResult = Wallet::credit(
                $userId,
                $netAmount,
                'deposit',
                $txRef,
                'transaction',
                "Approved deposit of $" . number_format($netAmount, 2) . " via {$tx['payment_method']}",
                $db
            );

            if (!$creditResult['success']) {
                throw new Exception($creditResult['message']);
            }

            // 2. Update transaction status
            $upStmt = $db->prepare("
                UPDATE transactions 
                SET status = 'approved', admin_notes = ?, approved_by_user_id = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $upStmt->execute([$adminNotes ?: 'Approved by administrator.', Auth::id(), $txId]);

            // 3. Referral Commission Check
            $refStmt = $db->prepare("SELECT referrer_id, commission_rate FROM referrals WHERE referee_id = ? LIMIT 1");
            $refStmt->execute([$userId]);
            $ref = $refStmt->fetch();

            if ($ref) {
                $referrerId = (int)$ref['referrer_id'];
                $rate = (float)$ref['commission_rate'];
                $commissionAmt = round(($netAmount * $rate) / 100.0, 4);

                if ($commissionAmt > 0) {
                    // Credit referrer wallet
                    Wallet::credit(
                        $referrerId,
                        $commissionAmt,
                        'referral_bonus',
                        $txRef,
                        'referral',
                        "Referral commission on deposit {$txRef}",
                        $db
                    );

                    // Insert commission record
                    $db->prepare("
                        INSERT INTO referral_commissions (referral_id, referrer_id, referee_id, amount, source_type, source_id)
                        VALUES ((SELECT id FROM referrals WHERE referee_id = ? LIMIT 1), ?, ?, ?, 'deposit', ?)
                    ")->execute([$userId, $referrerId, $userId, $commissionAmt, $txId]);

                    $db->prepare("UPDATE referrals SET total_commission = total_commission + ? WHERE referee_id = ?")
                       ->execute([$commissionAmt, $userId]);
                }
            }

            // Notification to user
            $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Deposit Approved', ?, 'transaction')")
               ->execute([$userId, "Your deposit of $" . number_format($netAmount, 2) . " ({$txRef}) has been approved and credited to your wallet balance."]);

            // Admin activity log
            $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, target_entity, target_id, details, ip_address) VALUES (?, 'APPROVE_DEPOSIT', 'transactions', ?, ?, ?)")
               ->execute([Auth::id(), $txId, "Approved deposit {$txRef} of $$netAmount", Security::getClientIp()]);

            $db->commit();
            Security::setFlash('success', "Deposit {$txRef} approved and credited to user wallet.");
        } else {
            // Reject deposit
            $upStmt = $db->prepare("
                UPDATE transactions 
                SET status = 'rejected', admin_notes = ?, approved_by_user_id = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $upStmt->execute([$adminNotes ?: 'Rejected by administrator.', Auth::id(), $txId]);

            // Notification
            $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Deposit Rejected', ?, 'transaction')")
               ->execute([$userId, "Your deposit {$txRef} was rejected. Note: " . ($adminNotes ?: 'Contact support for details.')]);

            $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, target_entity, target_id, details, ip_address) VALUES (?, 'REJECT_DEPOSIT', 'transactions', ?, ?, ?)")
               ->execute([Auth::id(), $txId, "Rejected deposit {$txRef}", Security::getClientIp()]);

            $db->commit();
            Security::setFlash('warning', "Deposit {$txRef} has been rejected.");
        }

        header("Location: /admin/deposits.php");
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        $error = "Action failed: " . $e->getMessage();
    }
}

// Fetch Deposits
$statusFilter = $_GET['status'] ?? 'pending';
$sql = "
    SELECT t.*, u.username, u.email 
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE t.type = 'deposit'
";
$params = [];
if ($statusFilter !== 'all') {
    $sql .= " AND t.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY t.id DESC LIMIT 50";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$deposits = $stmt->fetchAll();

renderAdminHeader('Deposit Management', 'deposits');
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-800">
        <div>
            <div class="text-xs font-mono text-slate-500 uppercase">TREASURY OPERATIONS</div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Deposit Management</h1>
        </div>

        <!-- Filter Tabs -->
        <div class="flex items-center gap-1.5 p-1 bg-slate-900 border border-slate-800 rounded-xl text-xs font-medium font-mono">
            <a href="/admin/deposits.php?status=pending" class="px-3 py-1.5 rounded-lg transition <?= $statusFilter === 'pending' ? 'bg-blue-600 text-white font-bold' : 'text-slate-400 hover:text-white' ?>">Pending</a>
            <a href="/admin/deposits.php?status=approved" class="px-3 py-1.5 rounded-lg transition <?= $statusFilter === 'approved' ? 'bg-blue-600 text-white font-bold' : 'text-slate-400 hover:text-white' ?>">Approved</a>
            <a href="/admin/deposits.php?status=rejected" class="px-3 py-1.5 rounded-lg transition <?= $statusFilter === 'rejected' ? 'bg-blue-600 text-white font-bold' : 'text-slate-400 hover:text-white' ?>">Rejected</a>
            <a href="/admin/deposits.php?status=all" class="px-3 py-1.5 rounded-lg transition <?= $statusFilter === 'all' ? 'bg-blue-600 text-white font-bold' : 'text-slate-400 hover:text-white' ?>">All</a>
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
                        <th class="py-3 px-4">Amount</th>
                        <th class="py-3 px-4">Proof / Memo</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Admin Notes</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($deposits)): ?>
                        <tr><td colspan="8" class="py-8 text-center text-slate-500">No deposits found under current filter.</td></tr>
                    <?php else: ?>
                        <?php foreach ($deposits as $d): ?>
                            <?php $details = json_decode($d['payment_details'] ?? '{}', true); ?>
                            <tr class="hover:bg-slate-900/30 transition">
                                <td class="py-3 px-4 font-bold text-white"><?= e($d['transaction_ref']) ?></td>
                                <td class="py-3 px-4">
                                    <span class="font-bold text-blue-400"><?= e($d['username']) ?></span>
                                    <span class="text-[10px] text-slate-500 block"><?= e($d['email']) ?></span>
                                </td>
                                <td class="py-3 px-4 text-slate-300"><?= e($d['payment_method']) ?></td>
                                <td class="py-3 px-4 font-bold text-emerald-400">$<?= number_format((float)$d['amount'], 2) ?></td>
                                <td class="py-3 px-4 text-slate-400 max-w-xs truncate" title="<?= e($details['payment_reference'] ?? '') ?>">
                                    <?= e($details['payment_reference'] ?? 'None') ?>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded text-[10px] uppercase font-bold <?= 
                                        $d['status'] === 'approved' ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' :
                                        ($d['status'] === 'pending' ? 'bg-amber-950 text-amber-400 border border-amber-800' : 'bg-red-950 text-red-400 border border-red-800')
                                    ?>">
                                        <?= e($d['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-slate-500 text-[11px]"><?= e($d['admin_notes'] ?: '-') ?></td>
                                <td class="py-3 px-4 text-right">
                                    <?php if ($d['status'] === 'pending'): ?>
                                        <div class="flex items-center justify-end gap-2">
                                            <form method="POST" action="/admin/deposits.php" class="inline-block">
                                                <?= Security::csrfField() ?>
                                                <input type="hidden" name="tx_id" value="<?= $d['id'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" onclick="return confirm('Approve deposit of $<?= number_format($d['net_amount'], 2) ?> and credit user wallet?')" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded text-[11px] font-bold">
                                                    Approve
                                                </button>
                                            </form>
                                            <form method="POST" action="/admin/deposits.php" class="inline-block">
                                                <?= Security::csrfField() ?>
                                                <input type="hidden" name="tx_id" value="<?= $d['id'] ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" onclick="return confirm('Reject deposit #<?= $d['transaction_ref'] ?>?')" class="px-2.5 py-1 bg-red-950 hover:bg-red-900 border border-red-800 text-red-300 rounded text-[11px] font-bold">
                                                    Reject
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
