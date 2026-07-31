<?php
// ============================================================
// statement.php — printable monthly account statement.
//
// A classic account statement for one calendar month:
//   opening balance  (all confirmed orders − payments BEFORE the month)
//   + each confirmed order and payment IN the month, in date order
//   = closing balance
//
// Access:
//   * an approved distributor sees their own statement
//   * staff can view any distributor's:  statement.php?distributor_id=N
//
// Pending and cancelled orders are listed for reference but do NOT
// affect the balance — same rule as everywhere else in the system.
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

// ---- Whose statement? ----
$requested_id = clean_id($_GET['distributor_id'] ?? null);
$own_id       = approved_distributor_id($pdo);

if ($requested_id > 0 && is_staff($pdo)) {
    $distributor_id = $requested_id;          // staff viewing someone
} elseif ($own_id !== null) {
    $distributor_id = $own_id;                // distributor viewing self
} else {
    $_SESSION['message'] = 'Only approved distributors (or staff) can view statements.';
    header('Location: ' . url('index.php'));
    exit;
}

$stmt = $pdo->prepare(
    'SELECT d.*, u.username, u.email
       FROM distributors d
       JOIN users u ON u.user_id = d.user_id
      WHERE d.distributor_id = ?'
);
$stmt->execute([$distributor_id]);
$distributor = $stmt->fetch();

if (!$distributor) {
    $_SESSION['message'] = 'Distributor not found.';
    header('Location: ' . url('index.php'));
    exit;
}

// ---- Which month? Default: the current one. ----
// Accepts month=YYYY-MM from the picker; falls back safely on bad input.
$month_input = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month_input)) {
    $month_input = date('Y-m');
}
$month_start = $month_input . '-01';
$month_end   = date('Y-m-t', strtotime($month_start));   // last day of month
$month_label = date('F Y', strtotime($month_start));

// ---- Opening balance: everything confirmed/paid BEFORE the month ----
$c = $pdo->prepare(
    "SELECT COALESCE(SUM(total_amount),0) FROM orders
      WHERE distributor_id = ? AND order_status = 'confirmed'
        AND created_at < ?"
);
$c->execute([$distributor_id, $month_start]);
$opening_orders = (float)$c->fetchColumn();

$p = $pdo->prepare(
    'SELECT COALESCE(SUM(amount),0) FROM payments
      WHERE distributor_id = ? AND payment_date < ?'
);
$p->execute([$distributor_id, $month_start]);
$opening_payments = (float)$p->fetchColumn();

$opening = round($opening_orders - $opening_payments, 2);

// ---- Activity inside the month ----
$orders = $pdo->prepare(
    "SELECT order_id, order_source, payment_status, order_status,
            total_amount, created_at
       FROM orders
      WHERE distributor_id = ?
        AND created_at >= ? AND created_at <= ?
      ORDER BY created_at"
);
$orders->execute([$distributor_id, $month_start, $month_end . ' 23:59:59']);
$orders = $orders->fetchAll();

$payments = $pdo->prepare(
    'SELECT payment_id, amount, payment_date, note
       FROM payments
      WHERE distributor_id = ?
        AND payment_date >= ? AND payment_date <= ?
      ORDER BY payment_date, payment_id'
);
$payments->execute([$distributor_id, $month_start, $month_end]);
$payments = $payments->fetchAll();

// ---- Merge into one dated line list ----
$lines = [];
foreach ($orders as $o) {
    $lines[] = [
        'date'    => substr($o['created_at'], 0, 10),
        'sortkey' => $o['created_at'] . '-A',
        'desc'    => 'Order #' . $o['order_id']
                   . ' (' . $o['order_source'] . ', ' . $o['payment_status'] . ')',
        'status'  => $o['order_status'],
        // Only confirmed orders move the balance.
        'charge'  => $o['order_status'] === 'confirmed' ? (float)$o['total_amount'] : 0.0,
        'payment' => 0.0,
        'amount_display' => (float)$o['total_amount'],
        'kind'    => 'order',
    ];
}
foreach ($payments as $pm) {
    $lines[] = [
        'date'    => $pm['payment_date'],
        'sortkey' => $pm['payment_date'] . ' 99:99:99-B' . $pm['payment_id'],
        'desc'    => 'Payment received' . ($pm['note'] ? ' — ' . $pm['note'] : ''),
        'status'  => '',
        'charge'  => 0.0,
        'payment' => (float)$pm['amount'],
        'amount_display' => (float)$pm['amount'],
        'kind'    => 'payment',
    ];
}
usort($lines, fn($a, $b) => strcmp($a['sortkey'], $b['sortkey']));

// ---- Running balance + closing ----
$running = $opening;
foreach ($lines as &$l) {
    $running += $l['charge'] - $l['payment'];
    $l['running'] = round($running, 2);
}
unset($l);
$closing = round($running, 2);

// Month navigation links.
$prev_month = date('Y-m', strtotime($month_start . ' -1 month'));
$next_month = date('Y-m', strtotime($month_start . ' +1 month'));
$base_qs    = ($requested_id > 0 && is_staff($pdo)) ? 'distributor_id=' . $distributor_id . '&' : '';

$title = 'Statement — ' . $month_label;
require_once __DIR__ . '/includes/header.php';
?>

<div class="receipt">

    <div class="head-row no-print-flex">
        <h1>Account Statement</h1>
        <span>
            <button type="button" class="button" onclick="window.print()">Print</button>
        </span>
    </div>

    <!-- Month picker: hidden when printing -->
    <form method="get" action="<?= url('statement.php') ?>" class="box no-print">
        <?php if ($base_qs): ?>
            <input type="hidden" name="distributor_id" value="<?= (int)$distributor_id ?>">
        <?php endif; ?>
        <label>Month
            <input type="month" name="month" value="<?= e($month_input) ?>">
        </label>
        <button type="submit">Show</button>
        <a class="cancel" href="<?= url('statement.php?' . $base_qs . 'month=' . $prev_month) ?>">&laquo; <?= e(date('M Y', strtotime($prev_month . '-01'))) ?></a>
        <a class="cancel" href="<?= url('statement.php?' . $base_qs . 'month=' . $next_month) ?>"><?= e(date('M Y', strtotime($next_month . '-01'))) ?> &raquo;</a>
    </form>

    <div class="box">
        <table class="detail">
            <tr><th>Distributor</th><td><?= e($distributor['business_name']) ?>
                (<?= e($distributor['username']) ?>, <?= e($distributor['email']) ?>)</td></tr>
            <tr><th>Statement period</th><td><?= e($month_label) ?>
                (<?= e(date('j M', strtotime($month_start))) ?> – <?= e(date('j M Y', strtotime($month_end))) ?>)</td></tr>
            <tr><th>Opening balance</th><td>K<?= number_format($opening, 2) ?></td></tr>
            <tr><th>Closing balance</th><td><strong>K<?= number_format($closing, 2) ?></strong></td></tr>
        </table>
    </div>

    <?php if (!$lines): ?>
        <p class="hint">No orders or payments in <?= e($month_label) ?>.
            The balance carried through unchanged.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>Date</th><th>Description</th>
                <th>Charges</th><th>Payments</th><th>Balance</th>
            </tr>
            <tr>
                <td></td><td><em>Opening balance</em></td>
                <td></td><td></td>
                <td>K<?= number_format($opening, 2) ?></td>
            </tr>
            <?php foreach ($lines as $l): ?>
                <tr>
                    <td><?= e(date('j M', strtotime($l['date']))) ?></td>
                    <td>
                        <?= e($l['desc']) ?>
                        <?php if ($l['kind'] === 'order' && $l['status'] !== 'confirmed'): ?>
                            <span class="meta">(<?= e($l['status']) ?> — not charged)</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $l['charge'] > 0 ? 'K' . number_format($l['charge'], 2) : '' ?></td>
                    <td><?= $l['payment'] > 0 ? 'K' . number_format($l['payment'], 2) : '' ?></td>
                    <td>K<?= number_format($l['running'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <td></td><td><strong>Closing balance</strong></td>
                <td></td><td></td>
                <td><strong>K<?= number_format($closing, 2) ?></strong></td>
            </tr>
        </table>
    <?php endif; ?>

    <p class="meta">
        Charges are confirmed orders only. Pending and cancelled orders are shown
        for reference but do not affect the balance.
        Generated <?= e(date('j M Y, H:i')) ?>.
    </p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
