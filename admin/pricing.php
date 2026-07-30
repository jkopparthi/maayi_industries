<?php
// ============================================================
// admin/pricing.php — set special prices for an approved distributor.
// REQUIREMENT 7.1 — admin only.
//
//   pricing.php                        -> choose a distributor
//   pricing.php?distributor_id=1       -> set prices for that distributor
//
// For each product the admin sees the REGULAR price and can set either
// a percentage discount OR a fixed price (per product). Leaving both
// blank removes any existing deal for that product.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

// ---- Which distributor are we pricing? ----
$distributor_id = clean_id($_GET['distributor_id'] ?? $_POST['distributor_id'] ?? null);

$distributor = null;
if ($distributor_id > 0) {
    $stmt = $pdo->prepare(
        "SELECT d.*, u.username
           FROM distributors d
           JOIN users u ON u.user_id = d.user_id
          WHERE d.distributor_id = ? AND d.approval_status = 'approved'"
    );
    $stmt->execute([$distributor_id]);
    $distributor = $stmt->fetch();

    if (!$distributor) {
        $_SESSION['message'] = 'That distributor was not found or is not approved.';
        header('Location: ' . url('admin/pricing.php'));
        exit;
    }
}

$errors = [];

// ---- Save a single product's price ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $distributor) {

    $page_id = clean_id($_POST['page_id'] ?? null);
    $mode    = $_POST['mode'] ?? '';        // 'percent' | 'fixed' | 'none'
    $percent = trim($_POST['adjustment_percent'] ?? '');
    $fixed   = trim($_POST['fixed_price'] ?? '');
    $note    = trim($_POST['note'] ?? '');

    if ($page_id < 1) {
        $errors[] = 'Unknown product.';
    }

    if (!$errors) {
        if ($mode === 'none') {
            // Remove any existing deal for this product.
            $del = $pdo->prepare(
                'DELETE FROM distributor_pricing WHERE distributor_id = ? AND page_id = ?'
            );
            $del->execute([$distributor_id, $page_id]);
            $_SESSION['message'] = 'Special price removed for that product.';

        } else {
            // Validate the chosen mode.
            $adj = null; $fix = null;
            if ($mode === 'percent') {
                if ($percent === '' || !is_numeric($percent) || $percent <= 0 || $percent >= 100) {
                    $errors[] = 'Enter a discount percentage between 0 and 100.';
                } else {
                    $adj = (float)$percent;
                }
            } elseif ($mode === 'fixed') {
                if ($fixed === '' || !is_numeric($fixed) || $fixed < 0) {
                    $errors[] = 'Enter a valid fixed price.';
                } else {
                    $fix = (float)$fixed;
                }
            } else {
                $errors[] = 'Please choose how to set the price.';
            }

            if ($note === '') {
                $errors[] = 'Please enter a short reason (required).';
            }

            if (!$errors) {
                // Upsert against the UNIQUE(distributor_id, page_id) index.
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
                $sql->execute([$distributor_id, $page_id, $adj, $fix, $note, $_SESSION['user_id']]);
                $_SESSION['message'] = 'Special price saved.';
            }
        }
    }

    if (!$errors) {
        header('Location: ' . url('admin/pricing.php?distributor_id=' . $distributor_id));
        exit;
    }
}

$title = 'Distributor Pricing';
require_once __DIR__ . '/../includes/header.php';
?>

<h1>Distributor Pricing</h1>

<?php if ($errors): ?>
    <div class="error"><ul>
        <?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?>
    </ul></div>
<?php endif; ?>

<?php if (!$distributor): ?>

    <?php
    $approved = $pdo->query(
        "SELECT d.distributor_id, d.business_name, u.username
           FROM distributors d
           JOIN users u ON u.user_id = d.user_id
          WHERE d.approval_status = 'approved'
          ORDER BY d.business_name"
    )->fetchAll();
    ?>

    <p class="hint">Choose an approved distributor to set their prices.</p>

    <?php if (!$approved): ?>
        <p>No approved distributors yet. Approve applications on the
           <a href="<?= url('admin/requests.php') ?>">Applications</a> page first.</p>
    <?php else: ?>
        <table>
            <tr><th>Business</th><th>Login</th><th></th></tr>
            <?php foreach ($approved as $d): ?>
            <tr>
                <td><?= e($d['business_name']) ?></td>
                <td><?= e($d['username']) ?></td>
                <td class="actions">
                    <a href="<?= url('admin/pricing.php?distributor_id=' . $d['distributor_id']) ?>">Set prices &raquo;</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

<?php else: ?>

    <p class="crumb"><a href="<?= url('admin/pricing.php') ?>">&laquo; All distributors</a></p>
    <p class="hint">
        Setting prices for <strong><?= e($distributor['business_name']) ?></strong>
        (<?= e($distributor['username']) ?>). The regular price is shown for reference.
    </p>

    <?php
    // All products with this distributor's current deal (if any) joined in.
    $stmt = $pdo->prepare(
        'SELECT p.page_id, p.title, p.price,
                dp.adjustment_percent, dp.fixed_price, dp.note
           FROM pages p
      LEFT JOIN distributor_pricing dp
             ON dp.page_id = p.page_id AND dp.distributor_id = ?
          ORDER BY p.title'
    );
    $stmt->execute([$distributor_id]);
    $rows = $stmt->fetchAll();
    ?>

    <table>
        <tr>
            <th>Product</th>
            <th>Regular price</th>
            <th>Current deal</th>
            <th>Set new price</th>
        </tr>
        <?php foreach ($rows as $r): ?>
            <?php
                $base = (float)$r['price'];
                if ($r['fixed_price'] !== null) {
                    $current = 'Fixed K' . number_format((float)$r['fixed_price'], 2);
                } elseif ($r['adjustment_percent'] !== null) {
                    $eff = round($base * (1 - (float)$r['adjustment_percent'] / 100), 2);
                    $current = number_format((float)$r['adjustment_percent'], 2)
                             . '% off &rarr; K' . number_format($eff, 2);
                } else {
                    $current = '<span class="hint">— none —</span>';
                }
            ?>
            <tr>
                <td><?= e($r['title']) ?></td>
                <td>K<?= number_format($base, 2) ?></td>
                <td><?= $current ?></td>
                <td>
                    <form method="post" style="margin:0">
                        <input type="hidden" name="distributor_id" value="<?= (int)$distributor_id ?>">
                        <input type="hidden" name="page_id" value="<?= (int)$r['page_id'] ?>">
                        <div style="display:flex; gap:.4rem; flex-wrap:wrap; align-items:center">
                            <select name="mode">
                                <option value="percent">% off</option>
                                <option value="fixed">Fixed K</option>
                                <option value="none">Remove deal</option>
                            </select>
                            <input type="number" step="0.01" min="0" name="adjustment_percent"
                                   placeholder="%" style="width:5rem"
                                   value="<?= $r['adjustment_percent'] !== null ? e((string)$r['adjustment_percent']) : '' ?>">
                            <input type="number" step="0.01" min="0" name="fixed_price"
                                   placeholder="K" style="width:6rem"
                                   value="<?= $r['fixed_price'] !== null ? e((string)$r['fixed_price']) : '' ?>">
                            <input type="text" name="note" placeholder="reason"
                                   style="width:9rem" value="<?= e($r['note'] ?? '') ?>">
                            <button type="submit">Save</button>
                        </div>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <p class="hint">
        Choose <em>% off</em> and fill the % box, or <em>Fixed K</em> and fill the K box.
        A reason is required. Choose <em>Remove deal</em> to delete a special price.
    </p>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
