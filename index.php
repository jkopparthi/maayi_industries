<?php
// ============================================================
// index.php — the role-aware home page.
//
// GUESTS + plain members  -> product GALLERY: image, name, category,
//                            description teaser. NO prices anywhere
//                            (proposal Business Rule 1).
// APPROVED DISTRIBUTORS   -> STOREFRONT: same grid but with their own
//                            prices, add-to-cart on every tile, and a
//                            cart summary panel at the top (the
//                            "Amazon-style" logged-in experience).
//
// Requirement 2.7 still holds: every link is generated from the DB.
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$dist_id = approved_distributor_id($pdo);   // null unless approved distributor

// Optional category filter for browsing the grid.
$cat_filter = clean_id($_GET['category'] ?? null);

$categories = $pdo->query(
    'SELECT c.category_id, c.name, COUNT(p.page_id) AS n
       FROM categories c
       LEFT JOIN pages p ON p.category_id = c.category_id
      GROUP BY c.category_id, c.name
      ORDER BY c.name'
)->fetchAll();

$sql = 'SELECT pages.page_id, pages.title, pages.slug, pages.price, pages.body,
               categories.name AS category_name
          FROM pages
          LEFT JOIN categories ON pages.category_id = categories.category_id';
$params = [];
if ($cat_filter > 0) {
    $sql .= ' WHERE pages.category_id = ?';
    $params[] = $cat_filter;
}
$sql .= ' ORDER BY categories.name, pages.title';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pages = $stmt->fetchAll();

// One query for all the images on this page.
$images = page_images($pdo, array_column($pages, 'page_id'));

// Cart summary for the distributor panel.
$cart = $dist_id !== null ? cart_detailed($pdo, $dist_id) : ['lines' => [], 'total' => 0.0];

$title = 'Our Products';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($dist_id !== null): ?>
    <!-- ============ DISTRIBUTOR STOREFRONT ============ -->

    <?php if ($cart['lines']): ?>
        <!-- Cart panel: what is in the basket right now, right on the home page -->
        <div class="cart-panel">
            <div class="head-row">
                <h2 style="margin:0">Your cart (<?= cart_count() ?> item<?= cart_count() === 1 ? '' : 's' ?>)</h2>
                <span>
                    <a class="button" href="<?= url('cart.php') ?>">View cart</a>
                    <a class="button" href="<?= url('checkout.php') ?>">Checkout — K<?= number_format($cart['total'], 2) ?></a>
                </span>
            </div>
            <p class="meta" style="margin:.4rem 0 0">
                <?php
                $names = array_map(fn($l) => $l['title'] . ' ×' . $l['quantity'], $cart['lines']);
                echo e(implode(' · ', array_slice($names, 0, 4)));
                if (count($names) > 4) echo ' · …';
                ?>
            </p>
        </div>
    <?php endif; ?>

    <h1>Order Products</h1>
    <p class="hint">Prices shown are your prices. Quantities can be adjusted in the cart.</p>

<?php else: ?>
    <!-- ============ PUBLIC GALLERY ============ -->
    <section class="home-hero">
        <div class="home-hero-inner">
            <p class="eyebrow">Kasama · Zambia · Since day one</p>
            <h1>Drinks made in Zambia, poured across the region.</h1>
            <p>
                Purified waters, small-batch kombuchas, wines and spirits — supplied
                wholesale to distributors who move them town to town.
            </p>
            <p class="hero-actions">
                <a class="button" href="<?= url('showcase.php') ?>">Enter the 3D showcase</a>
                <?php if (!logged_in()): ?>
                    <a class="button ghost-light" href="<?= url('register.php') ?>">Become a distributor</a>
                <?php elseif (($_SESSION['role'] ?? '') === 'member' && distributor_status($pdo) === null): ?>
                    <a class="button ghost-light" href="<?= url('apply.php') ?>">Apply for wholesale pricing</a>
                <?php endif; ?>
            </p>
        </div>
    </section>
<?php endif; ?>

<!-- Category filter row (both audiences) -->
<p class="catbar">
    <a class="<?= $cat_filter === 0 ? 'active' : '' ?>" href="<?= url('index.php') ?>">All</a>
    <?php foreach ($categories as $c): ?>
        <?php if ((int)$c['n'] === 0) continue; ?>
        <a class="<?= $cat_filter === (int)$c['category_id'] ? 'active' : '' ?>"
           href="<?= url('index.php?category=' . $c['category_id']) ?>">
            <?= e($c['name']) ?>
        </a>
    <?php endforeach; ?>
</p>

<?php if (!$pages): ?>
    <p class="hint">No products in this category yet.</p>
<?php else: ?>
<div class="grid">
    <?php foreach ($pages as $p): ?>
        <div class="tile" data-cat="<?= e($p['category_name'] ?? '') ?>">
            <a class="tile-link" href="<?= permalink((int)$p['page_id'], $p['slug']) ?>">
                <span class="tile-media">
                    <?php if (isset($images[$p['page_id']])): ?>
                        <img src="<?= url('uploads/' . rawurlencode($images[$p['page_id']])) ?>"
                             alt="<?= e($p['title']) ?>">
                    <?php else: ?>
                        <!-- Clean placeholder: brand mark + first letter,
                             same square footprint as a real photo -->
                        <span class="tile-placeholder">
                            <span class="ph-letter"><?= e(mb_substr($p['title'], 0, 1)) ?></span>
                            <span class="ph-brand">Maayi</span>
                        </span>
                    <?php endif; ?>
                </span>
                <span class="tile-body">
                    <span class="tile-title"><?= e($p['title']) ?></span>
                    <span class="tile-cat"><?= $p['category_name'] ? e($p['category_name']) : 'Uncategorised' ?></span>
                </span>
            </a>

            <?php if ($dist_id !== null): ?>
                <?php $pr = price_for_distributor($pdo, $dist_id, (int)$p['page_id'], (float)$p['price']); ?>
                <div class="tile-buy">
                    <span class="tile-price">
                        K<?= number_format($pr['price'], 2) ?>
                        <?php if ($pr['special']): ?>
                            <span class="meta"><s>K<?= number_format($pr['base'], 2) ?></s> your price</span>
                        <?php endif; ?>
                    </span>
                    <form method="post" action="<?= url('cart.php') ?>" class="addform">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="page_id" value="<?= (int)$p['page_id'] ?>">
                        <input type="hidden" name="return" value="<?= e(url('index.php' . ($cat_filter ? '?category=' . $cat_filter : ''))) ?>">
                        <input type="number" name="quantity" value="1" min="1" step="1" class="qty">
                        <button type="submit">Add</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
