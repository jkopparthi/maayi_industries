<?php
// ============================================================
// admin/distributors.php — distributor management home.
// Staff only (admin + accountant + any staff role).
// Lists every distributor; click one to view full details, approve
// or reject, and set their pricing.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_staff($pdo);

$q = trim($_GET['q'] ?? '');

if ($q !== '') {
    // Escape literal % and _ so they match as text, not as wildcards.
    $esc  = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q);
    $like = '%' . $esc . '%';
    $stmt = $pdo->prepare(
        "SELECT d.distributor_id, d.business_name, d.contact_person, d.phone,
                d.approval_status, u.username, u.email
           FROM distributors d
           JOIN users u ON u.user_id = d.user_id
          WHERE d.business_name LIKE ? ESCAPE '!' OR d.contact_person LIKE ? ESCAPE '!'
             OR u.username LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!' OR d.phone LIKE ? ESCAPE '!'
          ORDER BY
            CASE d.approval_status WHEN 'pending' THEN 0 ELSE 1 END,
            d.business_name"
    );
    $stmt->execute([$like, $like, $like, $like, $like]);
    $rows = $stmt->fetchAll();
} else {
    $rows = $pdo->query(
        "SELECT d.distributor_id, d.business_name, d.contact_person, d.phone,
                d.approval_status, u.username, u.email
           FROM distributors d
           JOIN users u ON u.user_id = d.user_id
          ORDER BY
            CASE d.approval_status WHEN 'pending' THEN 0 ELSE 1 END,
            d.business_name"
    )->fetchAll();
}

$pending = array_filter($rows, fn($r) => $r['approval_status'] === 'pending');

// Friendly label + css per stored status (matches the public banner).
function status_badge(string $status): string {
    $map = [
        'pending'  => ['Pending',     'is-pending'],
        'approved' => ['Active',      'is-active'],
        'rejected' => ['Deactivated', 'is-deactivated'],
    ];
    [$label, $css] = $map[$status] ?? [ucfirst($status), 'is-pending'];
    return '<span class="pill ' . $css . '">' . e($label) . '</span>';
}

$title = 'Distributors';
require_once __DIR__ . '/../includes/header.php';
?>

<h1>Distributors</h1>
<?php if ($q !== ''): ?>
    <p class="hint">Showing results for "<strong><?= e($q) ?></strong>".
        <a href="<?= url('admin/distributors.php') ?>">Clear</a></p>
<?php else: ?>
<p class="hint">
    <?= count($pending) ?> application<?= count($pending) === 1 ? '' : 's' ?> awaiting review.
    Click a distributor to view details, approve or reject, and set pricing.
</p>
<?php endif; ?>

<?php if (!$rows): ?>
    <p>No distributor applications yet.</p>
<?php else: ?>
<table>
    <tr>
        <th>Business</th>
        <th>Contact</th>
        <th>Login</th>
        <th>Status</th>
        <th></th>
    </tr>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td><?= e($r['business_name']) ?></td>
        <td>
            <?= e($r['contact_person'] ?: '—') ?>
            <?php if ($r['phone']): ?><br><span class="meta"><?= e($r['phone']) ?></span><?php endif; ?>
        </td>
        <td><?= e($r['username']) ?><br><span class="meta"><?= e($r['email']) ?></span></td>
        <td><?= status_badge($r['approval_status']) ?></td>
        <td class="actions">
            <a href="<?= url('admin/distributor-view.php?id=' . $r['distributor_id']) ?>">View &raquo;</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
