<?php
// ============================================================
// checkout.php — turn the session cart into a real order.
// Creates one `orders` row (status 'pending', credit) plus an
// `order_items` row per line, snapshotting the price charged so a
// later pricing change never alters a placed order. Clears the cart.
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$dist_id = approved_distributor_id($pdo);
if ($dist_id === null) {
    $_SESSION['message'] = 'Only approved distributors can place orders.';
    header('Location: ' . url('index.php'));
    exit;
}

$cart = cart_detailed($pdo, $dist_id);

// Confirm-and-place step.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'place') {
    if (!$cart['lines']) {
        $_SESSION['message'] = 'Your cart is empty.';
        header('Location: ' . url('cart.php'));
        exit;
    }

    $pdo->beginTransaction();
    try {
        $ord = $pdo->prepare(
            "INSERT INTO orders
                 (distributor_id, order_source, payment_status, order_status, total_amount, created_by)
             VALUES (?, 'online', 'credit', 'pending', ?, ?)"
        );
        // created_by is the distributor's own user id (self-service).
        $ord->execute([$dist_id, $cart['total'], $_SESSION['user_id']]);
        $order_id = (int)$pdo->lastInsertId();

        $item = $pdo->prepare(
            'INSERT INTO order_items (order_id, page_id, quantity, unit_price)
             VALUES (?, ?, ?, ?)'
        );
        foreach ($cart['lines'] as $l) {
            $item->execute([$order_id, $l['page_id'], $l['quantity'], $l['unit_price']]);
        }

        $pdo->commit();
        cart_clear();
        $_SESSION['message'] = 'Order #' . $order_id . ' placed. It is pending confirmation.';
        header('Location: ' . url('receipt.php?id=' . $order_id));
        exit;
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

$title = 'Checkout';
require_once __DIR__ . '/includes/header.php';
?>

<h1>Checkout</h1>

<?php if (!$cart['lines']): ?>
    <p class="hint">Your cart is empty. <a href="<?= url('index.php') ?>">Browse products</a>.</p>
<?php else: ?>

<p class="hint">Review your order. It will be placed on credit and marked
   <strong>pending</strong> until the accountant confirms it.</p>

<table>
    <tr><th>Product</th><th>Unit price</th><th>Qty</th><th>Line total</th></tr>
    <?php foreach ($cart['lines'] as $l): ?>
    <tr>
        <td><?= e($l['title']) ?></td>
        <td>K<?= number_format($l['unit_price'], 2) ?></td>
        <td><?= (int)$l['quantity'] ?></td>
        <td>K<?= number_format($l['line_total'], 2) ?></td>
    </tr>
    <?php endforeach; ?>
    <tr>
        <th colspan="3" style="text-align:right">Order total</th>
        <th>K<?= number_format($cart['total'], 2) ?></th>
    </tr>
</table>

<div style="margin-top:1rem; display:flex; gap:.5rem; flex-wrap:wrap">
    <a class="cancel" href="<?= url('cart.php') ?>">&laquo; Back to cart</a>
    <form method="post">
        <input type="hidden" name="action" value="place">
        <button type="submit">Place order on credit</button>
    </form>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
