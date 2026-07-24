<?php
// ============================================================
// REQUIREMENT 2.7 — a public list of all pages, with links to each one.
// Every link here is built from a row in the database.
// No login is needed to see this.
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pages = $pdo->query(
    'SELECT pages.page_id, pages.title, pages.price, categories.name AS category_name
       FROM pages
       LEFT JOIN categories ON pages.category_id = categories.category_id
      ORDER BY categories.name, pages.title'
)->fetchAll();

$title = 'Our Products';
require_once __DIR__ . '/includes/header.php';
?>

<h1>Our Products</h1>
<p class="hint">Click any product </p>

<ul class="product-list">
    <?php foreach ($pages as $p): ?>
        <li>
            <a href="<?= url('page.php?id=' . $p['page_id']) ?>"><?= e($p['title']) ?></a>
            <span class="meta">
                <?= $p['category_name'] ? e($p['category_name']) : 'Uncategorised' ?>
                &middot; K<?= number_format((float)$p['price'], 2) ?>
            </span>
        </li>
    <?php endforeach; ?>
</ul>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
