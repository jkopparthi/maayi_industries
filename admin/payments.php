<?php
// ============================================================
// admin/payments.php — accountant/admin record payments.
// Staff only. Choose an approved distributor to see their balance
// (confirmed orders minus payments), record a new payment, and view
// their payment history.
//
//   payments.php                       -> list distributors + owed
//   payments.php?distributor_id=1      -> record + history for one
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_staff($pdo);

$distributor_id = clean_id($_GET['distributor_id'] ?? $_POST['distributor_id'] ?? null);

$distributor = null;
if ($distributor_id > 0) {
    $stmt = $pdo->prepare(
        "SELECT d.*, u.username, u.email
           FROM distributors d
           JOIN users u ON u.user_id = d.user_id
          WHERE d.distributor_id = ? AND d.approval_status = 'approved'"
    );
    $stmt->execute([$distributor_id]);
    $distributor = $stmt->fetch();

    if (!$distributor) {
        $_SESSION['message'] = 'That distributor was not found or is not approved.';
        header('Location: ' . url('admin/payments.php'));
        exit;
    }
}

$errors = [];

// ---- Record a payment ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $distributor) {

    $amount = trim($_POST['amount'] ?? '');
    $date   = trim($_POST['payment_date'] ?? '');
    $note   = trim($_POST['note'] ?? '');

    if (!is_numeric($amount) || (float)$amount <= 0) {
        $errors[] = 'Enter a payment amount greater than zero.';
    }
    // Basic YYYY-MM-DD sanity check; default to today if blank.
    if ($date === '') {
        $date = date('Y-m-d');
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
        $errors[] = 'Enter a valid date.';
    }

    if (!$errors) {
        $ins = $pdo->prepare(
            'INSERT INTO payments (distributor_id, amount, payment_date, recorded_by, note)
             VALUES (?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $distributor_id, (float)$amount, $date,
            $_SESSION['user_id'], $note ?: null,
        ]);
        $_SESSION['message'] = 'Payment of K' . number_format((float)$amount, 2) . ' recorded.';
        header('Location: ' . url('admin/payments.php?distributor_id=' . $distributor_id));
        exit;
    }
}

$title = 'Payments';
require_once __DIR__ . '/../includes/header.php';
?>

<h1>Payments</h1>

<?php if ($errors): ?>
    <div class="error"><ul>
        <?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?>
    </ul></div>
<?php endif; ?>

<?php if (!$distributor): ?>

    <?php
    // Every approved distributor with their owed balance.
    $rows = $pdo->query(
        "SELECT d.distributor_id, d.business_name, u.username
           FROM distributors d
           JOIN users u ON u.user_id = d.user_id
          WHERE d.approval_status = 'approved'
          ORDER BY d.business_name"
    )->fetchAll();
    ?>

    <p class="hint">Choose a distributor to record a payment or view history.</p>

    <?php if (!$rows): ?>
        <p>No approved distributors yet.</p>
    <?php else: ?>
    <table>
        <tr><th>Business</th><th>Login</th><th>Owed</th><th></th></tr>
        <?php foreach ($rows as $r): ?>
            <?php $owed = distributor_balance($pdo, (int)$r['distributor_id']); ?>
            <tr>
                <td><?= e($r['business_name']) ?></td>
                <td><?= e($r['username']) ?></td>
                <td>
                    <strong>K<?= number_format($owed, 2) ?></strong>
                    <?php if ($owed <= 0): ?><span class="meta">(settled)</span><?php endif; ?>
                </td>
                <td class="actions">
                    <a href="<?= url('admin/payments.php?distributor_id=' . $r['distributor_id']) ?>">Record payment &raquo;</a>
                    <a href="<?= url('statement.php?distributor_id=' . $r['distributor_id']) ?>">Statement</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

<?php else: ?>

    <?php
    $b = distributor_balance_breakdown($pdo, $distributor_id);

    $history = $pdo->prepare(
        "SELECT p.*, staff.username AS recorded_by_name
           FROM payments p
      LEFT JOIN users staff ON staff.user_id = p.recorded_by
          WHERE p.distributor_id = ?
          ORDER BY p.payment_date DESC, p.payment_id DESC"
    );
    $history->execute([$distributor_id]);
    $history = $history->fetchAll();
    ?>

    <p class="crumb"><a href="<?= url('admin/payments.php') ?>">&laquo; All distributors</a></p>

    <div class="head-row">
        <h2><?= e($distributor['business_name']) ?></h2>
        <a href="<?= url('admin/distributor-view.php?id=' . $distributor_id) ?>">View distributor</a>
    </div>

    <div class="box">
        <table class="detail">
            <tr><th>Confirmed orders</th><td>K<?= number_format($b['confirmed'], 2) ?></td></tr>
            <tr><th>Payments received</th><td>&minus; K<?= number_format($b['paid'], 2) ?></td></tr>
            <tr><th>Balance owed</th>
                <td><strong>K<?= number_format($b['owed'], 2) ?></strong>
                    <?php if ($b['owed'] <= 0): ?><span class="meta">(settled)</span><?php endif; ?>
                </td></tr>
        </table>
    </div>

    <h3>Record a payment</h3>
    <form method="post" class="box">
        <input type="hidden" name="distributor_id" value="<?= (int)$distributor_id ?>">
        <label>Amount (K)
            <input type="number" name="amount" step="0.01" min="0.01" required
                   value="<?= e($_POST['amount'] ?? '') ?>">
        </label>
        <label>Payment date
            <input type="date" name="payment_date"
                   value="<?= e($_POST['payment_date'] ?? date('Y-m-d')) ?>">
        </label>
        <label>Note <span class="meta">(optional — e.g. cash, bank transfer ref)</span>
            <input type="text" name="note" maxlength="255"
                   value="<?= e($_POST['note'] ?? '') ?>">
        </label>
        <button type="submit">Record payment</button>
    </form>

    <h3>Payment history</h3>
    <?php if (!$history): ?>
        <p class="hint">No payments recorded yet.</p>
    <?php else: ?>
    <table>
        <tr><th>Date</th><th>Amount</th><th>Note</th><th>Recorded by</th></tr>
        <?php foreach ($history as $h): ?>
        <tr>
            <td><?= e(date('j M Y', strtotime($h['payment_date']))) ?></td>
            <td>K<?= number_format((float)$h['amount'], 2) ?></td>
            <td><?= e($h['note'] ?: '—') ?></td>
            <td><?= e($h['recorded_by_name'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
