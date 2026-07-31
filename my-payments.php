<?php
// ============================================================
// my-payments.php — an approved distributor's own payment history.
//
// Staff could already see this from admin/payments.php, but the
// distributor themselves had no view of their own money — this page
// mirrors that history for the account owner, alongside the same
// balance breakdown staff see.
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$dist_id = approved_distributor_id($pdo);
if ($dist_id === null) {
    $_SESSION['message'] = 'Only approved distributors have a payment history.';
    header('Location: ' . url('index.php'));
    exit;
}

$b = distributor_balance_breakdown($pdo, $dist_id);

$payments = $pdo->prepare(
    'SELECT p.payment_id, p.amount, p.payment_date, p.note,
            staff.username AS recorded_by_name
       FROM payments p
  LEFT JOIN users staff ON staff.user_id = p.recorded_by
      WHERE p.distributor_id = ?
      ORDER BY p.payment_date DESC, p.payment_id DESC'
);
$payments->execute([$dist_id]);
$payments = $payments->fetchAll();

$title = 'My Payments';
require_once __DIR__ . '/includes/header.php';
?>

<div class="head-row">
    <h1>My Payments</h1>
    <a class="button" href="<?= url('statement.php') ?>">Monthly statement</a>
</div>

<div class="box">
    <table class="detail">
        <tr><th>Confirmed orders</th><td>K<?= number_format($b['confirmed'], 2) ?></td></tr>
        <tr><th>Payments made</th><td>K<?= number_format($b['paid'], 2) ?></td></tr>
        <tr><th>Balance owed</th>
            <td><strong>K<?= number_format($b['owed'], 2) ?></strong>
                <?php if ($b['owed'] <= 0): ?><span class="meta">(settled)</span><?php endif; ?>
            </td></tr>
    </table>
</div>

<?php if (!$payments): ?>
    <p class="hint">No payments have been recorded on your account yet.
        Payments are entered by the accounts office when they are received.</p>
<?php else: ?>
    <table>
        <tr><th>Date</th><th>Amount</th><th>Reference</th><th>Recorded by</th></tr>
        <?php foreach ($payments as $p): ?>
            <tr>
                <td><?= e(date('j M Y', strtotime($p['payment_date']))) ?></td>
                <td>K<?= number_format((float)$p['amount'], 2) ?></td>
                <td><?= $p['note'] ? e($p['note']) : '—' ?></td>
                <td><?= $p['recorded_by_name'] ? e($p['recorded_by_name']) : '—' ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
