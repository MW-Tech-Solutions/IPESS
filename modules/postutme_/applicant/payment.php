<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/_applicant.php';

$user = require_login(['applicant']);
[$applicant, $payment, $application] = applicant_context((int) $user['id']);
$session = active_session();
$invoiceStmt = db()->prepare('SELECT * FROM invoices WHERE applicant_id = ? LIMIT 1');
$invoiceStmt->execute([$applicant['id']]);
$invoice = $invoiceStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'invoice';
    if ($invoice && $invoice['status'] === 'successful') {
        flash('info', 'Payment has already been confirmed for this application.');
        redirect('applicant/payment.php');
    }
    if ($action === 'invoice' && !$invoice) {
        $provider = setting('payment_provider', 'paystack');
        $invoiceNumber = 'INV-' . date('Ymd') . '-' . random_int(100000, 999999);
        $reference = strtoupper($provider) . '-' . date('YmdHis') . '-' . random_int(1000, 9999);
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO invoices (applicant_id, invoice_number, amount, provider, rrr_reference, status, gateway_response) VALUES (?, ?, ?, ?, ?, "pending", ?)')->execute([$applicant['id'], $invoiceNumber, $session['application_fee'], $provider, $reference, json_encode(['created_from' => 'portal'])]);
        $pdo->prepare('INSERT INTO payments (applicant_id, provider, invoice_number, reference, rrr_reference, amount, status, metadata) VALUES (?, ?, ?, ?, ?, ?, "pending", ?)')->execute([$applicant['id'], $provider, $invoiceNumber, $reference, $reference, $session['application_fee'], json_encode(['invoice_number' => $invoiceNumber])]);
        $pdo->commit();
        audit_log('generated payment invoice', 'invoice', (int) $applicant['id']);
        flash('success', 'Payment invoice generated.');
    } elseif ($action === 'verify' && $invoice) {
        flash('info', 'Gateway verification endpoint is ready for integration. Finance can reconcile pending payments from the admin console.');
    } elseif ($action === 'pay' && $invoice) {
        flash('info', 'Online payment redirection is controlled by the configured provider. Add live Remita/Paystack keys in settings for production.');
    }
    redirect('applicant/payment.php');
}

render_header('Payment');
?>
<?php render_workspace_start('applicant', $applicant, 'Payment'); ?>
        <div class="dashboard-head">
            <div>
                <p class="eyebrow">Applicant Portal</p>
                <h1>Application Payment</h1>
            </div>
        </div>
        <div class="portal-card">
            <p class="text-muted">Provider is controlled by admin settings. Current provider: <strong><?= e(ucfirst(setting('payment_provider', 'paystack'))) ?></strong>.</p>
            <div class="payment-amount">NGN <?= number_format((float) $session['application_fee'], 2) ?></div>
            <?php if ($invoice): ?>
                <dl class="row">
                    <dt class="col-sm-4">Invoice Number</dt><dd class="col-sm-8"><?= e($invoice['invoice_number']) ?></dd>
                    <dt class="col-sm-4">RRR/Reference</dt><dd class="col-sm-8"><?= e($invoice['rrr_reference']) ?></dd>
                    <dt class="col-sm-4">Status</dt><dd class="col-sm-8"><?= status_badge($invoice['status']) ?></dd>
                </dl>
                <?php if ($invoice['status'] === 'successful'): ?>
                    <a href="<?= e(url('applicant/form.php')) ?>" class="btn btn-portal-green w-100">Continue to Screening Form</a>
                <?php else: ?>
                    <div class="d-grid gap-2 d-md-flex">
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="pay"><button class="btn btn-gold">Pay Online</button></form>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="verify"><button class="btn btn-portal-blue">Verify Payment</button></form>
                        <button onclick="window.print()" class="btn btn-outline-secondary">Print Invoice</button>
                    </div>
                    <div class="alert alert-warning mt-3">Awaiting payment confirmation. Finance can verify manual or gateway callbacks in production.</div>
                <?php endif; ?>
                <button onclick="window.print()" class="btn btn-outline-secondary mt-2">Print Receipt</button>
            <?php else: ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="invoice">
                    <button class="btn btn-gold btn-lg w-100">Generate Payment Reference</button>
                </form>
            <?php endif; ?>
        </div>
<?php render_workspace_end(); ?>
<?php render_footer(); ?>
