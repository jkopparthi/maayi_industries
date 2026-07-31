<?php
// ============================================================
// admin/distributor-view.php — one distributor's full record.
// Staff only. Shows all their details; lets staff approve/reject a
// pending application; and (once approved) set per-product pricing
// with the regular price shown for reference.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_staff($pdo);

$id = clean_id($_GET['id'] ?? $_POST['distributor_id'] ?? null);

function load_distributor(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare(
        "SELECT d.*, u.username, u.email, u.created_at AS user_created,
                staff.username AS decided_by
           FROM distributors d
           JOIN users u        ON u.user_id     = d.user_id
      LEFT JOIN users staff    ON staff.user_id = d.approved_by
          WHERE d.distributor_id = ?"
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

$distributor = ($id > 0) ? load_distributor($pdo, $id) : null;
if (!$distributor) {
    $_SESSION['message'] = 'Distributor not found.';
    header('Location: ' . url('admin/distributors.php'));
    exit;
}

$errors = [];

// ---- Handle actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = $_POST['do'] ?? '';

    // (a) Approve / reject a pending application.
    if ($do === 'approve' || $do === 'reject') {
        if ($distributor['approval_status'] !== 'pending') {
            $_SESSION['message'] = 'That application has already been decided.';
        } else {
            $pdo->beginTransaction();
            try {
                $status = ($do === 'approve') ? 'approved' : 'rejected';
                $upd = $pdo->prepare(
                    'UPDATE distributors
                        SET approval_status = ?, approved_by = ?, approved_at = NOW()
                      WHERE distributor_id = ?'
                );
                $upd->execute([$status, $_SESSION['user_id'], $id]);

                if ($do === 'approve') {
                    $role = $pdo->prepare("UPDATE users SET role = 'distributor' WHERE user_id = ?");
                    $role->execute([$distributor['user_id']]);
                }
                $pdo->commit();
                $_SESSION['message'] = 'Application ' . $status . '.';
            } catch (Throwable $ex) {
                $pdo->rollBack();
                throw $ex;
            }
        }
        header('Location: ' . url('admin/distributor-view.php?id=' . $id));
        exit;
    }

    // (b) Save / remove a per-product price (approved distributors only).
    if ($do === 'price' && $distributor['approval_status'] === 'approved') {
        $page_id = clean_id($_POST['page_id'] ?? null);
        $mode    = $_POST['mode'] ?? '';
        $percent = trim($_POST['adjustment_percent'] ?? '');
        $fixed   = trim($_POST['fixed_price'] ?? '');
        $note    = trim($_POST['note'] ?? '');

        if ($page_id < 1) {
            $errors[] = 'Unknown product.';
        } elseif ($mode === 'none') {
            $del = $pdo->prepare('DELETE FROM distributor_pricing WHERE distributor_id = ? AND page_id = ?');
            $del->execute([$id, $page_id]);
            $_SESSION['message'] = 'Special price removed.';
            header('Location: ' . url('admin/distributor-view.php?id=' . $id));
            exit;
        } else {
            $adj = null; $fix = null;
            if ($mode === 'percent') {
                if (!is_numeric($percent) || $percent <= 0 || $percent >= 100) {
                    $errors[] = 'Enter a discount percentage between 0 and 100.';
                } else { $adj = (float)$percent; }
            } elseif ($mode === 'fixed') {
                if (!is_numeric($fixed) || $fixed < 0) {
                    $errors[] = 'Enter a valid fixed price.';
                } else { $fix = (float)$fixed; }
            } else {
                $errors[] = 'Choose how to set the price.';
            }
            if ($note === '') {
                $errors[] = 'A short reason is required.';
            }
            if (!$errors) {
                $sql = $pdo->prepare(
                    'INSERT INTO distributor_pricing
                         (distributor_id, page_id, adjustment_percent, fixed_price, note, created_by)
                     VALUES (?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                         adjustment_percent = VALUES(adjustment_percent),
                         fixed_price        = VALUES(fixed_price),
                         note               = VALUES(note),
                         created_by         = VALUES(created_by)'
                );
                $sql->execute([$id, $page_id, $adj, $fix, $note, $_SESSION['user_id']]);
                $_SESSION['message'] = 'Special price saved.';
                header('Location: ' . url('admin/distributor-view.php?id=' . $id));
                exit;
            }
        }
    }
}

// Friendly status
$smap = ['pending'=>['Pending','is-pending'],'approved'=>['Active','is-active'],'rejected'=>['Deactivated','is-deactivated']];
[$slabel, $scss] = $smap[$distributor['approval_status']] ?? [ucfirst($distributor['approval_status']), 'is-pending'];

$title = $distributor['business_name'];
require_once __DIR__ . '/../includes/header.php';
?>

<p class="crumb"><a href="<?= url('admin/distributors.php') ?>">&laquo; All distributors</a></p>

<div class="head-row">
    <h1><?= e($distributor['business_name']) ?></h1>
    <span class="pill <?= $scss ?>"><?= e($slabel) ?></span>
</div>

<?php if ($errors): ?>
    <div class="error"><ul>
        <?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?>
    </ul></div>
<?php endif; ?>

<!-- ---- Full details ---- -->
<div class="box">
    <table class="detail">
        <tr><th>Business name</th><td><?= e($distributor['business_name']) ?></td></tr>
        <tr><th>Contact person</th><td><?= e($distributor['contact_person'] ?: '—') ?></td></tr>
        <tr><th>Phone</th><td><?= e($distributor['phone'] ?: '—') ?></td></tr>
        <tr><th>Address</th><td><?= nl2br(e($distributor['address'] ?: '—')) ?></td></tr>
        <tr><th>Login</th><td><?= e($distributor['username']) ?> &middot; <?= e($distributor['email']) ?></td></tr>
        <tr><th>Registered</th><td><?= e(date('j M Y', strtotime($distributor['user_created']))) ?></td></tr>
        <?php if ($distributor['approval_status'] !== 'pending'): ?>
        <tr><th>Decision</th>
            <td><?= e($slabel) ?>
                <?php if ($distributor['decided_by']): ?>
                    by <?= e($distributor['decided_by']) ?>
                <?php endif; ?>
                <?php if ($distributor['approved_at']): ?>
                    on <?= e(date('j M Y', strtotime($distributor['approved_at']))) ?>
                <?php endif; ?>
            </td>
        </tr>
        <?php endif; ?>
    </table>
</div>

<!-- ---- Approve / reject (pending only) ---- -->
<?php if ($distributor['approval_status'] === 'pending'): ?>
<div class="box">
    <h2>Review application</h2>
    <form method="post" style="display:inline" onsubmit="return confirm('Approve this distributor?');">
        <input type="hidden" name="distributor_id" value="<?= (int)$id ?>">
        <button type="submit" name="do" value="approve">Approve</button>
    </form>
    <form method="post" style="display:inline" onsubmit="return confirm('Reject this distributor?');">
        <input type="hidden" name="distributor_id" value="<?= (int)$id ?>">
        <button type="submit" name="do" value="reject" style="background:#b23b3b">Reject</button>
    </form>
</div>
<?php endif; ?>

<!-- ---- Pricing (approved only) ---- -->
<?php if ($distributor['approval_status'] === 'approved'): ?>
    <?php $bd = distributor_balance_breakdown($pdo, (int)$id); ?>
    <div class="box">
        <table class="detail">
            <tr><th>Balance owed</th>
                <td><strong>K<?= number_format($bd['owed'], 2) ?></strong>
                    <span class="meta">(confirmed K<?= number_format($bd['confirmed'], 2) ?>
                        − paid K<?= number_format($bd['paid'], 2) ?>)</span>
                    &nbsp; <a href="<?= url('admin/payments.php?distributor_id=' . (int)$id) ?>">Record payment &raquo;</a>
                </td></tr>
        </table>
    </div>

    <h2>Pricing</h2>
    <p class="hint">Regular price shown for reference. Set a % discount or a fixed price per product.</p>

    <?php
    $stmt = $pdo->prepare(
        'SELECT p.page_id, p.title, p.price,
                dp.adjustment_percent, dp.fixed_price, dp.note
           FROM pages p
      LEFT JOIN distributor_pricing dp
             ON dp.page_id = p.page_id AND dp.distributor_id = ?
          ORDER BY p.title'
    );
    $stmt->execute([$id]);
    $products = $stmt->fetchAll();
    ?>

    <table>
        <tr><th>Product</th><th>Regular</th><th>Current deal</th><th>Set price</th></tr>
        <?php foreach ($products as $p): ?>
        <?php
            $base = (float)$p['price'];
            if ($p['fixed_price'] !== null) {
                $current = 'Fixed K' . number_format((float)$p['fixed_price'], 2);
            } elseif ($p['adjustment_percent'] !== null) {
                $eff = round($base * (1 - (float)$p['adjustment_percent'] / 100), 2);
                $current = number_format((float)$p['adjustment_percent'], 2) . '% &rarr; K' . number_format($eff, 2);
            } else {
                $current = '<span class="hint">none</span>';
            }
        ?>
        <tr>
            <td><?= e($p['title']) ?></td>
            <td>K<?= number_format($base, 2) ?></td>
            <td><?= $current ?></td>
            <td>
                <form method="post" style="margin:0">
                    <input type="hidden" name="do" value="price">
                    <input type="hidden" name="distributor_id" value="<?= (int)$id ?>">
                    <input type="hidden" name="page_id" value="<?= (int)$p['page_id'] ?>">
                    <div style="display:flex; gap:.4rem; flex-wrap:wrap; align-items:center">
                        <select name="mode">
                            <option value="percent">% off</option>
                            <option value="fixed">Fixed K</option>
                            <option value="none">Remove</option>
                        </select>
                        <input type="number" step="0.01" min="0" name="adjustment_percent" placeholder="%"
                               style="width:4.5rem"
                               value="<?= $p['adjustment_percent'] !== null ? e((string)$p['adjustment_percent']) : '' ?>">
                        <input type="number" step="0.01" min="0" name="fixed_price" placeholder="K"
                               style="width:5.5rem"
                               value="<?= $p['fixed_price'] !== null ? e((string)$p['fixed_price']) : '' ?>">
                        <input type="text" name="note" placeholder="reason" style="width:8rem"
                               value="<?= e($p['note'] ?? '') ?>">
                        <button type="submit">Save</button>
                    </div>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
<?php elseif ($distributor['approval_status'] === 'rejected'): ?>
    <p class="hint">This distributor is deactivated. Pricing is available once a distributor is approved.</p>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
