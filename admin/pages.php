<?php
// ============================================================
// REQUIREMENT 2.3 — list all pages, sortable.
// Only logged-in admins can reach this.
// Sorting is done by MySQL in the ORDER BY clause, not in the browser.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();   // req 7.1: admin-only, not just logged in

// ---- Which column are we sorting by? ----------------------------------
// The value from the URL is looked up in this list. Anything not in the
// list is ignored and we fall back to 'title'. This is what makes it safe
// to drop the column name into the SQL: it can only ever be one of these
// three strings, never something the user typed.
$allowed_sorts = [
    'title'      => 'title',
    'created_at' => 'created_at',
    'updated_at' => 'updated_at',
];

$sort = $_GET['sort'] ?? 'title';
if (!isset($allowed_sorts[$sort])) {
    $sort = 'title';
}
$column = $allowed_sorts[$sort];

// ---- Ascending or descending? ----
$dir = (($_GET['dir'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';

// ---- The query. MySQL does the sorting. ----
$sql = "SELECT pages.*, categories.name AS category_name
        FROM pages
        LEFT JOIN categories ON pages.category_id = categories.category_id
        ORDER BY $column $dir";

$pages = $pdo->query($sql)->fetchAll();

/**
 * Build a clickable column heading. Clicking the column you are already
 * sorting by flips the direction, and an arrow shows the current state.
 */
function heading(string $col, string $label, string $sort, string $dir): string {
    $next = ($sort === $col && $dir === 'ASC') ? 'desc' : 'asc';

    $arrow = '';
    if ($sort === $col) {
        $arrow = ($dir === 'ASC') ? ' &uarr;' : ' &darr;';
    }

    return '<a href="' . url('admin/pages.php') . '?sort=' . $col . '&dir=' . $next . '">'
         . $label . $arrow . '</a>';
}

$title = 'Manage Pages';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="head-row">
    <h1>Manage Pages</h1>
    <a class="button" href="<?= url('admin/page-form.php') ?>">Add New Page</a>
</div>

<!-- Requirement 2.3: show which sort is currently applied -->
<p class="hint">
    Sorted by <strong><?= e(str_replace('_', ' ', $sort)) ?></strong>,
    <?= $dir === 'ASC' ? 'ascending' : 'descending' ?>.
    Click a heading to change it.
</p>

<table>
    <tr>
        <th><?= heading('title', 'Title', $sort, $dir) ?></th>
        <th>Category</th>
        <th>Price</th>
        <th><?= heading('created_at', 'Created', $sort, $dir) ?></th>
        <th><?= heading('updated_at', 'Updated', $sort, $dir) ?></th>
        <th>Actions</th>
    </tr>

    <?php foreach ($pages as $p): ?>
        <tr>
            <td><?= e($p['title']) ?></td>
            <td><?= $p['category_name'] ? e($p['category_name']) : '—' ?></td>
            <td>K<?= number_format((float)$p['price'], 2) ?></td>
            <td><?= date('Y-m-d', strtotime($p['created_at'])) ?></td>
            <td><?= date('Y-m-d', strtotime($p['updated_at'])) ?></td>
            <td class="actions">
                <a href="<?= url('page.php?id=' . $p['page_id']) ?>">View</a>
                <a href="<?= url('admin/page-form.php?id=' . $p['page_id']) ?>">Edit</a>
                <a class="delete"
                   href="<?= url('admin/page-delete.php?id=' . $p['page_id']) ?>"
                   onclick="return confirm('Delete this page?');">Delete</a>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
