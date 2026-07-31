<?php
// ============================================================
// my-orders.php — an approved distributor's own orders + balance.
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$dist_id = approved_distributor_id($pdo);
if ($dist_id === null) {
    $_SESSION['message'] = 'Only approved distributors have orders.';
    header('Location: ' . url('index.php'));
    exit;
}

$orders = $pdo->prepare(
    'SELECT order_id, order_status, cancel_note, total_amount, created_at
       FROM orders
      WHERE distributor_id = ?
      ORDER BY created_at DESC'
);
$orders->execute([$dist_id]);
$orders = $orders->fetchAll();

$balance = distributor_balance($pdo, $dist_id);

function badge_class(string $s): string {
    return ['pending'=>'is-pending','confirmed'=>'is-active','cancelled'=>'is-deactivated'][$s] ?? 'is-pending';
}
function badge_label(string $s): string {
    return ['pending'=>'Pending','confirmed'=>'Confirmed','cancelled'=>'Cancelled'][$s] ?? ucfirst($s);
}

$title = 'My Orders';
require_once __DIR__ . '/includes/header.php';
?>

<div class="head-row">
    <h1>My Orders</h1>
    <span>
        <a class="button" href="<?= url('my-payments.php') ?>">My Payments</a>
        <a class="button" href="<?= url('statement.php') ?>">Monthly statement</a>
    </span>
</div>

<div class="box">
    <table class="detail">
        <tr><th>Balance owed</th>
            <td><strong>K<?= number_format($balance, 2) ?></strong>
                <span class="meta">= confirmed orders − payments recorded</span></td></tr>
    </table>
</div>

<?php if (!$orders): ?>
    <p class="hint">You have not placed any orders yet.
        <a href="<?= url('index.php') ?>">Browse products</a>.</p>
<?php else: ?>
<table>
    <tr><th>Order</th><th>Date</th><th>Total</th><th>Status</th><th></th></tr>
    <?php foreach ($orders as $o): ?>
    <tr>
        <td>#<?= (int)$o['order_id'] ?></td>
        <td><?= e(date('j M Y', strtotime($o['created_at']))) ?></td>
        <td>K<?= number_format((float)$o['total_amount'], 2) ?></td>
        <td>
            <span class="pill <?= badge_class($o['order_status']) ?>"><?= e(badge_label($o['order_status'])) ?></span>
            <?php if ($o['order_status'] === 'cancelled' && $o['cancel_note']): ?>
                <br><span class="meta"><?= e($o['cancel_note']) ?></span>
            <?php endif; ?>
        </td>
        <td class="actions"><a href="<?= url('receipt.php?id=' . (int)$o['order_id']) ?>">Receipt</a></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
