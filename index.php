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

// Prices are visible ONLY to approved distributors. Everyone else
// (guests, members, pending/rejected) gets null and sees no price.
$dist_id = approved_distributor_id($pdo);

$title = 'Our Products';
require_once __DIR__ . '/includes/header.php';
?>

<h1>Our Products</h1>
<p class="hint">Click any product to read more and leave a comment.</p>

<ul class="product-list">
    <?php foreach ($pages as $p): ?>
        <li>
            <a href="<?= url('page.php?id=' . $p['page_id']) ?>"><?= e($p['title']) ?></a>
            <span class="meta">
                <?= $p['category_name'] ? e($p['category_name']) : 'Uncategorised' ?>
                <?php if ($dist_id !== null): ?>
                    <?php $pr = price_for_distributor($pdo, $dist_id, (int)$p['page_id'], (float)$p['price']); ?>
                    &middot; K<?= number_format($pr['price'], 2) ?>
                    <?php if ($pr['special']): ?><span class="hint">(your price)</span><?php endif; ?>
                <?php endif; ?>
            </span>
        </li>
    <?php endforeach; ?>
</ul>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
