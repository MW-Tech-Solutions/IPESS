<?php
$upload_base = __DIR__ . '/../uploads/fees';
$payments_file = $upload_base . '/payments.json';
$payments = [];

if (file_exists($payments_file)) {
    $payments = json_decode(file_get_contents($payments_file), true) ?? [];
}

if (!$payments) {
    $payments = [
        [
            'date' => '2026-01-08',
            'method' => 'Bank Transfer',
            'amount' => 60000,
            'reference' => 'PAY-3041',
            'status' => 'Completed'
        ],
        [
            'date' => '2025-12-15',
            'method' => 'Card Payment',
            'amount' => 40000,
            'reference' => 'PAY-2917',
            'status' => 'Completed'
        ],
    ];
}

$statements = [
    ['date' => '2025-12-05', 'description' => 'Tuition Fees (Semester 1)', 'debit' => 150000, 'credit' => 0],
    ['date' => '2025-12-10', 'description' => 'ICT & Library Charges', 'debit' => 15000, 'credit' => 0],
    ['date' => '2025-12-20', 'description' => 'Postgraduate Support Grant', 'debit' => 0, 'credit' => 30000],
];

$total_debit = 0;
$total_credit = 0;
$running_balance = 0;
$statement_rows = [];
foreach ($statements as $entry) {
    $total_debit += $entry['debit'];
    $total_credit += $entry['credit'];
    $running_balance += $entry['debit'] - $entry['credit'];
    $statement_rows[] = array_merge($entry, ['balance' => $running_balance]);
}

$payments_total = 0;
foreach ($payments as $payment) {
    $payments_total += $payment['amount'];
}

$outstanding = max(0, $running_balance - $payments_total);
?>

<div class="container-fluid">
    <h1 class="h2 mb-4">Fees</h1>
    <ul class="nav nav-tabs" id="feesTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="statement-tab" data-bs-toggle="tab" data-bs-target="#statement" type="button" role="tab" aria-controls="statement" aria-selected="true">Statement</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab" aria-controls="payments" aria-selected="false">Payments</button>
        </li>
    </ul>
    <div class="tab-content pt-3" id="feesTabContent">
        <div class="tab-pane fade show active" id="statement" role="tabpanel" aria-labelledby="statement-tab">
            <div class="card card-jostum">
                <div class="card-body">
                    <h5 class="card-title">Fee Statement - 2024/2025 Session</h5>
                    <div class="table-responsive">
                        <table class="table table-jostum table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Debit (NGN)</th>
                                    <th>Credit (NGN)</th>
                                    <th>Balance (NGN)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($statement_rows as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['description'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo $row['debit'] ? number_format($row['debit']) : '-'; ?></td>
                                        <td><?php echo $row['credit'] ? number_format($row['credit']) : '-'; ?></td>
                                        <td><?php echo number_format($row['balance']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="4" class="text-end">Outstanding Balance:</th>
                                    <th>NGN <?php echo number_format($outstanding); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="payments" role="tabpanel" aria-labelledby="payments-tab">
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="card card-jostum">
                        <div class="card-body">
                            <h5 class="card-title">Make a Payment</h5>
                            <p class="text-muted">Outstanding Balance: <strong>NGN <?php echo number_format($outstanding); ?></strong></p>
                            <form class="portal-form" data-refresh="fees" action="handlers/fees-payment.php" method="post">
                                <div class="mb-3">
                                    <label class="form-label" for="payment-amount">Amount (NGN)</label>
                                    <input class="form-control" id="payment-amount" name="amount" type="number" min="1000" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="payment-method">Payment Method</label>
                                    <select class="form-select" id="payment-method" name="method" required>
                                        <option value="Bank Transfer">Bank Transfer</option>
                                        <option value="Card Payment">Card Payment</option>
                                        <option value="Remita">Remita</option>
                                        <option value="POS">POS</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-jostum">Confirm Payment</button>
                                <div class="portal-feedback mt-2 text-muted small"></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="card card-jostum">
                        <div class="card-body">
                            <h5 class="card-title">Recent Payments</h5>
                            <div class="table-responsive">
                                <table class="table table-jostum table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Method</th>
                                            <th>Amount (NGN)</th>
                                            <th>Reference</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payments as $payment): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($payment['date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($payment['method'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo number_format($payment['amount']); ?></td>
                                                <td><?php echo htmlspecialchars($payment['reference'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($payment['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
