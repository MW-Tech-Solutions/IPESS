<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/_admin.php';

$user = require_admin(['finance_officer', 'super_admin']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pdo = db();
    $pdo->beginTransaction();
    $paymentStmt = $pdo->prepare('SELECT * FROM payments WHERE id = ? FOR UPDATE');
    $paymentStmt->execute([(int) $_POST['payment_id']]);
    $payment = $paymentStmt->fetch();
    $pdo->prepare('UPDATE payments SET status = "successful", paid_at = NOW(), verified_by = ?, gateway_response = ? WHERE id = ?')->execute([$user['id'], json_encode(['manual_reconciliation' => true, 'by' => $user['email']]), (int) $_POST['payment_id']]);
    if ($payment && $payment['invoice_number']) {
        $pdo->prepare('UPDATE invoices SET status = "successful", paid_at = NOW(), gateway_response = ? WHERE invoice_number = ?')->execute([json_encode(['manual_reconciliation' => true, 'by' => $user['email']]), $payment['invoice_number']]);
    }
    $pdo->commit();
    audit_log('verified payment', 'payment', (int) $_POST['payment_id']);
    flash('success', 'Payment marked as paid.');
}
$payments = db()->query('SELECT p.*, a.jamb_reg_no, a.surname, a.first_name FROM payments p JOIN applicants a ON a.id = p.applicant_id ORDER BY p.id DESC LIMIT 200')->fetchAll();

render_header('Verify Payments');
?>
<?php render_workspace_start('admin', $user, 'Payments'); ?>
        <div class="portal-card">
            <h1>Payment Verification</h1>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>Reference</th><th>Applicant</th><th>Provider</th><th>Amount</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?= e($payment['reference']) ?></td>
                            <td><?= e($payment['surname'] . ' ' . $payment['first_name']) ?><br><small><?= e($payment['jamb_reg_no']) ?></small></td>
                            <td><?= e(ucfirst($payment['provider'])) ?></td>
                            <td>NGN <?= number_format((float) $payment['amount'], 2) ?></td>
                            <td><?= status_badge($payment['status']) ?></td>
                            <td>
                                <?php if ($payment['status'] !== 'paid'): ?>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="payment_id" value="<?= e((string) $payment['id']) ?>">
                                        <button class="btn btn-sm btn-portal-green">Mark Paid</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
<?php render_workspace_end(); ?>
<?php render_footer(); ?>
