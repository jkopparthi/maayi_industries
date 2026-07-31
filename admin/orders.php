<?php
// ============================================================
// admin/orders.php — accountant/admin review orders.
// Staff only. Pending orders can be CONFIRMED or CANCELLED.
// A note is REQUIRED when cancelling. Confirming an order is what
// makes its amount count toward the distributor's balance.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_staff($pdo);

$errors = [];

// ---- Handle a status change ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = clean_id($_POST['order_id'] ?? null);
    $action   = $_POST['action'] ?? '';
    $note     = trim($_POST['cancel_note'] ?? '');

    // Load the order and make sure it is still pending.
    $stmt = $pdo->prepare('SELECT order_status FROM orders WHERE order_id = ?');
    $stmt->execute([$order_id]);
    $current = $stmt->fetchColumn();

    if ($current === false) {
        $errors[] = 'Order not found.';
    } elseif ($current !== 'pending') {
        $errors[] = 'Only pending orders can be changed.';
    } elseif ($action === 'confirm') {
        $upd = $pdo->prepare(
            "UPDATE orders SET order_status = 'confirmed' WHERE order_id = ?"
        );
        $upd->execute([$order_id]);
        $_SESSION['message'] = 'Order #' . $order_id . ' confirmed. Balance updated for the distributor.';
    } elseif ($action === 'cancel') {
        if ($note === '') {
            $errors[] = 'A note is required to cancel an order.';
        } else {
            $upd = $pdo->prepare(
                "UPDATE orders SET order_status = 'cancelled', cancel_note = ? WHERE order_id = ?"
            );
            $upd->execute([$note, $order_id]);
            $_SESSION['message'] = 'Order #' . $order_id . ' cancelled.';
        }
    } else {
        $errors[] = 'Unknown action.';
    }

    if (!$errors) {
        header('Location: ' . url('admin/orders.php'));
        exit;
    }
}

// ---- Fetch orders (pending first) ----
$q = trim($_GET['q'] ?? '');

if ($q !== '') {
    $like = '%' . $q . '%';
    // Numeric query also matches an order number.
    $stmt = $pdo->prepare(
        "SELECT o.order_id, o.order_status, o.cancel_note, o.total_amount, o.created_at,
                d.business_name
           FROM orders o
           JOIN distributors d ON d.distributor_id = o.distributor_id
          WHERE d.business_name LIKE ? OR o.order_status LIKE ? OR CAST(o.order_id AS CHAR) = ?
          ORDER BY
            CASE o.order_status WHEN 'pending' THEN 0 ELSE 1 END,
            o.created_at DESC"
    );
    $stmt->execute([$like, $like, $q]);
    $orders = $stmt->fetchAll();
} else {
    $orders = $pdo->query(
        "SELECT o.order_id, o.order_status, o.cancel_note, o.total_amount, o.created_at,
                d.business_name
           FROM orders o
           JOIN distributors d ON d.distributor_id = o.distributor_id
          ORDER BY
            CASE o.order_status WHEN 'pending' THEN 0 ELSE 1 END,
            o.created_at DESC"
    )->fetchAll();
}

function ord_badge(string $s): string {
    $m = ['pending'=>['Pending','is-pending'],'confirmed'=>['Confirmed','is-active'],'cancelled'=>['Cancelled','is-deactivated']];
    [$l,$c] = $m[$s] ?? [ucfirst($s),'is-pending'];
    return '<span class="pill ' . $c . '">' . e($l) . '</span>';
}

$title = 'Orders';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="head-row">
    <h1>Orders</h1>
    <a class="button" href="<?= url('admin/order-create.php') ?>">Record Offline Order</a>
</div>
<?php if ($q !== ''): ?>
    <p class="hint">Showing results for "<strong><?= e($q) ?></strong>".
        <a href="<?= url('admin/orders.php') ?>">Clear</a></p>
<?php else: ?>
<p class="hint">Confirming an order adds its total to the distributor's balance.
   Cancelling requires a note.</p>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="error"><ul>
        <?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?>
    </ul></div>
<?php endif; ?>

<?php if (!$orders): ?>
    <p>No orders yet.</p>
<?php else: ?>
<table>
    <tr><th>Order</th><th>Distributor</th><th>Date</th><th>Total</th><th>Status</th><th>Action</th></tr>
    <?php foreach ($orders as $o): ?>
    <tr>
        <td><a href="<?= url('receipt.php?id=' . (int)$o['order_id']) ?>">#<?= (int)$o['order_id'] ?></a></td>
        <td><?= e($o['business_name']) ?></td>
        <td><?= e(date('j M Y', strtotime($o['created_at']))) ?></td>
        <td>K<?= number_format((float)$o['total_amount'], 2) ?></td>
        <td>
            <?= ord_badge($o['order_status']) ?>
            <?php if ($o['order_status'] === 'cancelled' && $o['cancel_note']): ?>
                <br><span class="meta"><?= e($o['cancel_note']) ?></span>
            <?php endif; ?>
        </td>
        <td>
            <?php if ($o['order_status'] === 'pending'): ?>
                <form method="post" style="display:inline"
                      onsubmit="return confirm('Confirm this order?');">
                    <input type="hidden" name="order_id" value="<?= (int)$o['order_id'] ?>">
                    <button type="submit" name="action" value="confirm">Confirm</button>
                </form>
                <!-- Cancel needs a note, so it toggles a small inline form -->
                <details style="display:inline-block; vertical-align:top">
                    <summary class="button-like">Cancel…</summary>
                    <form method="post" style="margin-top:.4rem">
                        <input type="hidden" name="order_id" value="<?= (int)$o['order_id'] ?>">
                        <input type="text" name="cancel_note" placeholder="reason (required)"
                               style="width:12rem" required>
                        <button type="submit" name="action" value="cancel"
                                style="background:#b23b3b">Cancel order</button>
                    </form>
                </details>
            <?php else: ?>
                <span class="meta">—</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
