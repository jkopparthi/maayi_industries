<?php
// ============================================================
// admin/order-create.php — staff record an OFFLINE / DIRECT order.
//
// This is the proposal's "Marketing In-Charge enters offline orders"
// promise: an order that arrived by phone, email or paper is entered
// here on the distributor's behalf, tagged with its real source and
// whether it was paid on the spot or placed on credit.
//
// Same rules as the online cart:
//   * per-distributor pricing is applied automatically
//   * unit_price is snapshotted into order_items
//   * header + lines are written in one transaction
//
// Unlike the online cart, a PAID direct order can be confirmed
// immediately (there is nothing to review — the money is in hand),
// while CREDIT orders start pending like online ones.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_staff($pdo);

$errors = [];

// Approved distributors to choose from.
$distributors = $pdo->query(
    "SELECT d.distributor_id, d.business_name, u.username
       FROM distributors d
       JOIN users u ON u.user_id = d.user_id
      WHERE d.approval_status = 'approved'
      ORDER BY d.business_name"
)->fetchAll();

// Product list for the line-item rows.
$products = $pdo->query(
    'SELECT page_id, title, price FROM pages ORDER BY title'
)->fetchAll();

// Values that survive a validation error.
$distributor_id = clean_id($_POST['distributor_id'] ?? null);
$order_source   = ($_POST['order_source'] ?? 'offline') === 'direct' ? 'direct' : 'offline';
$payment_status = ($_POST['payment_status'] ?? 'credit') === 'paid' ? 'paid' : 'credit';
$qty_input      = $_POST['qty'] ?? [];   // page_id => quantity

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($distributor_id < 1) {
        $errors[] = 'Please choose a distributor.';
    }

    // Collect the ordered lines: any product with a quantity above zero.
    $wanted = [];
    foreach ((array)$qty_input as $pid => $q) {
        $pid = clean_id($pid);
        $q   = clean_id($q);
        if ($pid > 0 && $q > 0) {
            $wanted[$pid] = $q;
        }
    }
    if (!$wanted) {
        $errors[] = 'Enter a quantity for at least one product.';
    }

    if (!$errors) {
        // Price each line for THIS distributor, snapshotting like checkout.
        $catalogue = [];
        foreach ($products as $p) {
            $catalogue[(int)$p['page_id']] = $p;
        }

        $lines = [];
        $total = 0.0;
        foreach ($wanted as $pid => $q) {
            if (!isset($catalogue[$pid])) {
                continue;
            }
            $base    = (float)$catalogue[$pid]['price'];
            $pricing = price_for_distributor($pdo, $distributor_id, $pid, $base);
            $lines[] = ['page_id' => $pid, 'quantity' => $q, 'unit_price' => $pricing['price']];
            $total  += $pricing['price'] * $q;
        }
        $total = round($total, 2);

        // A paid direct order is confirmed immediately; credit stays pending
        // for the usual accountant review.
        $order_status = ($payment_status === 'paid') ? 'confirmed' : 'pending';

        $pdo->beginTransaction();
        try {
            $ord = $pdo->prepare(
                'INSERT INTO orders
                     (distributor_id, order_source, payment_status, order_status, total_amount, created_by)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            // created_by is the STAFF member entering it — that is the audit
            // trail distinguishing an offline entry from self-service.
            $ord->execute([
                $distributor_id, $order_source, $payment_status,
                $order_status, $total, $_SESSION['user_id'],
            ]);
            $order_id = (int)$pdo->lastInsertId();

            $item = $pdo->prepare(
                'INSERT INTO order_items (order_id, page_id, quantity, unit_price)
                 VALUES (?, ?, ?, ?)'
            );
            foreach ($lines as $l) {
                $item->execute([$order_id, $l['page_id'], $l['quantity'], $l['unit_price']]);
            }

            // A PAID order also settles itself: record the matching payment
            // so the derived balance stays correct without special cases.
            if ($payment_status === 'paid') {
                $pay = $pdo->prepare(
                    'INSERT INTO payments (distributor_id, amount, payment_date, recorded_by, note)
                     VALUES (?, ?, CURDATE(), ?, ?)'
                );
                $pay->execute([
                    $distributor_id, $total, $_SESSION['user_id'],
                    'Paid with ' . $order_source . ' order #' . $order_id,
                ]);
            }

            $pdo->commit();
            $_SESSION['message'] = 'Order #' . $order_id . ' recorded ('
                . $order_source . ', ' . $payment_status
                . ($payment_status === 'paid' ? ', payment logged' : '') . ').';
            header('Location: ' . url('receipt.php?id=' . $order_id));
            exit;
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
    }
}

$title = 'Record Offline Order';
require_once __DIR__ . '/../includes/header.php';
?>

<h1>Record Offline / Direct Order</h1>
<p class="hint">
    For orders received by phone, email or paper. The distributor's own
    pricing is applied automatically, exactly as if they had ordered online.
</p>

<?php if ($errors): ?>
    <div class="error">
        <ul>
            <?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!$distributors): ?>
    <p class="hint">There are no approved distributors yet.
        <a href="<?= url('admin/distributors.php') ?>">Review applications</a>.</p>
<?php else: ?>

<form method="post" class="box">

    <label>Distributor
        <select name="distributor_id" required>
            <option value="">— choose —</option>
            <?php foreach ($distributors as $d): ?>
                <option value="<?= (int)$d['distributor_id'] ?>"
                    <?= $distributor_id === (int)$d['distributor_id'] ? 'selected' : '' ?>>
                    <?= e($d['business_name']) ?> (<?= e($d['username']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>Order source
        <select name="order_source">
            <option value="offline" <?= $order_source === 'offline' ? 'selected' : '' ?>>Offline (phone / email / paper)</option>
            <option value="direct"  <?= $order_source === 'direct'  ? 'selected' : '' ?>>Direct (in person)</option>
        </select>
    </label>

    <label>Payment
        <select name="payment_status">
            <option value="credit" <?= $payment_status === 'credit' ? 'selected' : '' ?>>On credit (adds to dues, needs confirmation)</option>
            <option value="paid"   <?= $payment_status === 'paid'   ? 'selected' : '' ?>>Paid now (confirmed + payment recorded)</option>
        </select>
    </label>

    <h2>Items</h2>
    <p class="hint">Enter a quantity for each product ordered. Leave the rest at 0.</p>

    <table>
        <tr><th>Product</th><th>Base price</th><th style="width:110px">Quantity</th></tr>
        <?php foreach ($products as $p): ?>
            <tr>
                <td><?= e($p['title']) ?></td>
                <td>K<?= number_format((float)$p['price'], 2) ?>
                    <span class="meta">before any distributor deal</span></td>
                <td>
                    <input type="number" min="0" step="1" name="qty[<?= (int)$p['page_id'] ?>]"
                           value="<?= isset($qty_input[$p['page_id']]) ? (int)$qty_input[$p['page_id']] : 0 ?>"
                           style="width:90px">
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <br>
    <button type="submit">Record Order</button>
    <a class="cancel" href="<?= url('admin/orders.php') ?>">Cancel</a>
</form>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
