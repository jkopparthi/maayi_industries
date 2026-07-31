<?php
// ============================================================
// cart.php — the approved distributor's shopping cart.
// The cart lives in the session, so it persists as they browse from
// product to product and is reachable from the nav at any time.
//
// Flow: edit quantities inline -> "Checkout" opens a confirmation
// dialog listing every product -> "Place order" inside the dialog
// posts to checkout.php to create the pending credit order.
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$dist_id = approved_distributor_id($pdo);
if ($dist_id === null) {
    $_SESSION['message'] = 'Only approved distributors can use the cart.';
    header('Location: ' . url('index.php'));
    exit;
}

// ---- Handle cart actions (add from product page, update, remove, clear) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $return = $_POST['return'] ?? url('cart.php');

    if ($action === 'add') {
        $pid = clean_id($_POST['page_id'] ?? null);
        $qty = max(1, (int)($_POST['quantity'] ?? 1));
        if ($pid > 0) {
            cart_add($pid, $qty);
            $_SESSION['message'] = 'Added to cart.';
        }
        header('Location: ' . $return);
        exit;
    }

    if ($action === 'update') {
        foreach (($_POST['qty'] ?? []) as $pid => $qty) {
            cart_set((int)$pid, (int)$qty);
        }
        $_SESSION['message'] = 'Cart updated.';
        header('Location: ' . url('cart.php'));
        exit;
    }

    if ($action === 'clear') {
        cart_clear();
        $_SESSION['message'] = 'Cart emptied.';
        header('Location: ' . url('cart.php'));
        exit;
    }
}

$cart = cart_detailed($pdo, $dist_id);

$title = 'Your Cart';
require_once __DIR__ . '/includes/header.php';
?>

<h1>Your Cart</h1>

<?php if (!$cart['lines']): ?>
    <p class="hint">Your cart is empty. <a href="<?= url('index.php') ?>">Browse products</a>.</p>
<?php else: ?>

<!-- Editable cart. Changing a qty and pressing Enter or "Save changes"
     updates the session cart. "Checkout" opens the confirmation dialog. -->
<form method="post" id="cartForm">
    <input type="hidden" name="action" value="update">
    <table>
        <tr><th>Product</th><th>Unit price</th><th>Qty</th><th>Line total</th></tr>
        <?php foreach ($cart['lines'] as $l): ?>
        <tr>
            <td><?= e($l['title']) ?></td>
            <td>K<?= number_format($l['unit_price'], 2) ?></td>
            <td>
                <input type="number"
                       name="qty[<?= (int)$l['page_id'] ?>]"
                       class="qty-input"
                       data-unit="<?= (float)$l['unit_price'] ?>"
                       data-title="<?= e($l['title']) ?>"
                       value="<?= (int)$l['quantity'] ?>" min="0" style="width:4.5rem">
                <span class="meta">(0 removes)</span>
            </td>
            <td class="line-total" data-unit="<?= (float)$l['unit_price'] ?>">
                K<?= number_format($l['line_total'], 2) ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <tr>
            <th colspan="3" style="text-align:right">Total</th>
            <th id="cartTotal">K<?= number_format($cart['total'], 2) ?></th>
        </tr>
    </table>

    <div style="margin-top:1rem; display:flex; gap:.5rem; flex-wrap:wrap; align-items:center">
        <!-- Only shows when a quantity has been edited but not yet saved -->
        <button type="submit" id="saveBtn" style="display:none">Save changes</button>
        <!-- Primary action: opens the confirmation dialog -->
        <button type="button" id="checkoutBtn" class="button">Checkout &raquo;</button>
    </div>
</form>

<form method="post" onsubmit="return confirm('Empty the cart?');" style="margin-top:.5rem">
    <input type="hidden" name="action" value="clear">
    <button type="submit" class="cancel">Empty cart</button>
</form>

<p class="hint" style="margin-top:.75rem">
    Orders are placed on credit. Once the accountant confirms your order,
    the amount is added to your balance.
</p>

<!-- ---- Confirmation dialog ---- -->
<dialog id="confirmDialog" class="cart-dialog">
    <h2>Confirm your order</h2>
    <table>
        <thead><tr><th>Product</th><th>Unit</th><th>Qty</th><th>Line total</th></tr></thead>
        <tbody id="confirmBody"><!-- filled by JS --></tbody>
        <tfoot>
            <tr><th colspan="3" style="text-align:right">Order total</th>
                <th id="confirmTotal"></th></tr>
        </tfoot>
    </table>
    <p class="hint">This order will be placed on credit and marked
        <strong>pending</strong> until the accountant confirms it.</p>
    <div style="display:flex; gap:.5rem; justify-content:flex-end; margin-top:1rem">
        <button type="button" class="cancel" id="cancelDialog">Back</button>
        <!-- Posts to checkout.php to actually create the order -->
        <form method="post" action="<?= url('checkout.php') ?>" style="margin:0">
            <input type="hidden" name="action" value="place">
            <button type="submit">Place order</button>
        </form>
    </div>
</dialog>

<script>
(function () {
    var form      = document.getElementById('cartForm');
    var saveBtn   = document.getElementById('saveBtn');
    var checkout  = document.getElementById('checkoutBtn');
    var dialog    = document.getElementById('confirmDialog');
    var body      = document.getElementById('confirmBody');
    var totalCell = document.getElementById('confirmTotal');
    var cartTotal = document.getElementById('cartTotal');
    var edited    = false;

    function money(n) { return 'K' + n.toFixed(2); }

    // Live-recalculate line + cart totals as quantities change, and reveal
    // the Save button so edits get persisted before ordering.
    function recalc() {
        var total = 0;
        document.querySelectorAll('.qty-input').forEach(function (inp) {
            var qty  = parseInt(inp.value, 10) || 0;
            var unit = parseFloat(inp.dataset.unit);
            var cell = inp.closest('tr').querySelector('.line-total');
            var line = qty * unit;
            total += line;
            cell.textContent = money(line);
        });
        cartTotal.textContent = money(total);
    }

    document.querySelectorAll('.qty-input').forEach(function (inp) {
        inp.addEventListener('input', function () {
            edited = true;
            saveBtn.style.display = '';
            recalc();
        });
    });

    // Build the dialog contents from the current (possibly edited) rows.
    function openDialog() {
        body.innerHTML = '';
        var total = 0;
        document.querySelectorAll('.qty-input').forEach(function (inp) {
            var qty = parseInt(inp.value, 10) || 0;
            if (qty < 1) return;                 // skip removed lines
            var unit = parseFloat(inp.dataset.unit);
            var line = qty * unit;
            total += line;
            var tr = document.createElement('tr');
            tr.innerHTML = '<td>' + inp.dataset.title + '</td>' +
                           '<td>' + money(unit) + '</td>' +
                           '<td>' + qty + '</td>' +
                           '<td>' + money(line) + '</td>';
            body.appendChild(tr);
        });
        totalCell.textContent = money(total);
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', 'open'); // very old browser fallback
        }
    }

    checkout.addEventListener('click', function () {
        // If quantities were edited, save them first so the placed order
        // matches what the distributor sees. Submitting reloads the page.
        if (edited) {
            form.submit();
            return;
        }
        openDialog();
    });

    document.getElementById('cancelDialog').addEventListener('click', function () {
        if (typeof dialog.close === 'function') dialog.close();
        else dialog.removeAttribute('open');
    });
})();
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
