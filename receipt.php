<?php
// ============================================================
// receipt.php — printable receipt / invoice for one order.
// Viewable any time after placing; shows the current status.
// Access: the distributor who owns the order, or any staff member.
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$order_id = clean_id($_GET['id'] ?? null);

$stmt = $pdo->prepare(
    "SELECT o.*, d.business_name, d.contact_person, d.address, d.phone,
            d.distributor_id, u.email
       FROM orders o
       JOIN distributors d ON d.distributor_id = o.distributor_id
       JOIN users u        ON u.user_id        = d.user_id
      WHERE o.order_id = ?"
);
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    $_SESSION['message'] = 'Order not found.';
    header('Location: ' . url('index.php'));
    exit;
}

// Access control: owner distributor or staff only.
$my_dist = approved_distributor_id($pdo);
$is_owner = ($my_dist !== null && (int)$my_dist === (int)$order['distributor_id']);
if (!$is_owner && !is_staff($pdo)) {
    $_SESSION['message'] = 'You do not have permission to view that order.';
    header('Location: ' . url('index.php'));
    exit;
}

$items = $pdo->prepare(
    'SELECT oi.*, p.title
       FROM order_items oi
       JOIN pages p ON p.page_id = oi.page_id
      WHERE oi.order_id = ?
      ORDER BY p.title'
);
$items->execute([$order_id]);
$lines = $items->fetchAll();

// Balance shown to the distributor (confirmed orders - payments).
$balance = distributor_balance($pdo, (int)$order['distributor_id']);

$statusLabel = ['pending'=>'Pending','confirmed'=>'Confirmed','cancelled'=>'Cancelled'][$order['order_status']] ?? ucfirst($order['order_status']);

$title = 'Receipt #' . $order_id;
require_once __DIR__ . '/includes/header.php';
?>

<div class="receipt">
    <div class="head-row no-print-flex">
        <h1>Receipt / Invoice</h1>
        <button type="button" class="button" onclick="window.print()">Print</button>
    </div>

    <div class="box">
        <table class="detail">
            <tr><th>Order</th><td>#<?= (int)$order['order_id'] ?></td></tr>
            <tr><th>Date</th><td><?= e(date('j M Y, H:i', strtotime($order['created_at']))) ?></td></tr>
            <tr><th>Status</th><td><strong><?= e($statusLabel) ?></strong></td></tr>
            <tr><th>Payment</th><td>Credit</td></tr>
            <tr><th>Distributor</th><td>
                <?= e($order['business_name']) ?><br>
                <?php if ($order['contact_person']): ?><span class="meta"><?= e($order['contact_person']) ?></span><br><?php endif; ?>
                <?php if ($order['address']): ?><span class="meta"><?= nl2br(e($order['address'])) ?></span><br><?php endif; ?>
                <?php if ($order['phone']): ?><span class="meta"><?= e($order['phone']) ?></span><?php endif; ?>
            </td></tr>
        </table>
    </div>

    <table>
        <tr><th>Product</th><th>Unit price</th><th>Qty</th><th>Line total</th></tr>
        <?php foreach ($lines as $l): ?>
        <tr>
            <td><?= e($l['title']) ?></td>
            <td>K<?= number_format((float)$l['unit_price'], 2) ?></td>
            <td><?= (int)$l['quantity'] ?></td>
            <td>K<?= number_format((float)$l['unit_price'] * (int)$l['quantity'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr>
            <th colspan="3" style="text-align:right">Order total</th>
            <th>K<?= number_format((float)$order['total_amount'], 2) ?></th>
        </tr>
    </table>

    <?php if ($order['order_status'] === 'cancelled'): ?>
        <p class="notice">This order was cancelled.
            <?php if (!empty($order['cancel_note'])): ?>
                Reason: <?= e($order['cancel_note']) ?>
            <?php endif; ?>
        </p>
    <?php elseif ($order['order_status'] === 'pending'): ?>
        <p class="hint">This order is pending confirmation by the accountant.
            It is not yet added to your balance.</p>
    <?php endif; ?>

    <div class="box">
        <table class="detail">
            <tr><th>Current balance owed</th>
                <td><strong>K<?= number_format($balance, 2) ?></strong>
                    <span class="meta">(confirmed orders minus payments)</span></td></tr>
        </table>
    </div>

    <p class="no-print"><a href="<?= url('my-orders.php') ?>">&laquo; My orders</a></p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
