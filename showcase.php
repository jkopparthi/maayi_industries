<?php
// ============================================================
// showcase.php — the scroll-driven 3D product showcase.
//
// Sits in front of the catalog: Showcase -> Browse all products ->
// the existing index.php grid. It does not replace anything; it reads
// the same `pages` rows and honours the same roles.
//
// What PHP decides here:  which products appear, in what order, with
// which materials, and whether this visitor may see a price.
// What JavaScript decides: nothing about permissions — only how the
// bottle turns.
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/showcase.php';

$dist_id = approved_distributor_id($pdo);
$payload = showcase_installed($pdo) ? showcase_payload($pdo) : ['products' => [], 'categories' => []];
$products = $payload['products'];

$title = 'Showcase';
require_once __DIR__ . '/includes/header.php';
?>
<script>document.body.classList.add('sc-page');</script>

<link rel="stylesheet" href="<?= url('assets/showcase/showcase.css') ?>?v=<?= @filemtime(__DIR__ . '/assets/showcase/showcase.css') ?: 1 ?>">

<?php if (!showcase_installed($pdo)): ?>

    <div class="sc-setup">
        <h1>The showcase is not set up yet</h1>
        <p>The 3D showcase stores its settings on the product records, and those
           fields are not in the database yet.</p>
        <?php if (is_admin()): ?>
            <p><a class="button" href="<?= url('admin/showcase.php') ?>">Add the showcase fields</a></p>
            <p class="hint">This adds nine optional columns to <code>pages</code>. Nothing existing changes.</p>
        <?php else: ?>
            <p class="hint">An administrator needs to finish setting it up.</p>
            <p><a class="button" href="<?= url('index.php') ?>">Browse all products</a></p>
        <?php endif; ?>
    </div>

<?php elseif (!$products): ?>

    <div class="sc-setup">
        <h1>No products in the showcase yet</h1>
        <p>The showcase is ready — it just needs products chosen for it.</p>
        <?php if (is_admin()): ?>
            <p><a class="button" href="<?= url('admin/showcase.php') ?>">Choose showcase products</a></p>
        <?php endif; ?>
        <p><a class="button" href="<?= url('index.php') ?>">Browse all products</a></p>
    </div>

<?php else: ?>

<div class="sc-root" id="sc-root" style="--sc-accent: <?= e($products[0]['accent']) ?>">

    <!-- ============ Sticky category rail ============
         Stays pinned while the whole experience scrolls. The active
         category follows whichever bottle is currently facing you. -->
    <nav class="sc-rail" id="sc-rail" aria-label="Product categories">
        <div class="sc-rail-inner">
            <ul class="sc-cats" id="sc-cats">
                <li><button type="button" class="sc-cat is-active" data-index="0" data-all="1">All</button></li>
                <?php foreach ($payload['categories'] as $c): ?>
                    <li>
                        <button type="button" class="sc-cat" data-index="<?= (int)$c['firstIndex'] ?>">
                            <?= e($c['name']) ?>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
            <a class="sc-browse" href="<?= url('index.php') ?>">
                Browse all products <span aria-hidden="true">&rarr;</span>
            </a>
        </div>
    </nav>

    <!-- ============ The scroll track ============
         Tall empty element. Its height is what the user actually
         scrolls; the stage inside it sticks to the viewport, so
         scroll distance becomes rotation instead of movement. -->
    <div class="sc-track" id="sc-track">
        <div class="sc-stage" id="sc-stage">

            <canvas id="sc-canvas" aria-hidden="true"></canvas>

            <!-- Loading state. Replaced by the scene once assets are ready. -->
            <div class="sc-loading" id="sc-loading">
                <span class="sc-loading-bar"><i id="sc-loading-fill"></i></span>
                <span class="sc-loading-text" id="sc-loading-text">Preparing the showcase</span>
            </div>

            <!-- Product information, left of centre. Contents are swapped
                 by JavaScript as each bottle comes round to face you. -->
            <div class="sc-panel" id="sc-panel">
                <p class="sc-eyebrow">
                    <span id="sc-count"><?= str_pad('1', 2, '0', STR_PAD_LEFT) ?></span>
                    <span class="sc-slash">/</span>
                    <span id="sc-cat"><?= e($products[0]['category']) ?></span>
                </p>

                <h1 class="sc-name" id="sc-name"><?= e($products[0]['name']) ?></h1>

                <p class="sc-teaser" id="sc-teaser"><?= e($products[0]['teaser']) ?></p>

                <!-- Price block: only ever populated for approved distributors,
                     because the price never leaves the server for anyone else. -->
                <div class="sc-price" id="sc-price" <?= $dist_id === null ? 'hidden' : '' ?>>
                    <?php if ($dist_id !== null): ?>
                        <span class="sc-price-now" id="sc-price-now"><?= e($products[0]['price'] ?? '') ?></span>
                        <span class="sc-price-was" id="sc-price-was" hidden></span>
                    <?php endif; ?>
                </div>

                <div class="sc-actions">
                    <button type="button" class="sc-btn sc-btn-ghost" id="sc-more"
                            aria-expanded="false" aria-controls="sc-drawer">Show more</button>

                    <?php if ($dist_id !== null): ?>
                        <!-- Posts to the existing cart, no separate ordering system -->
                        <form method="post" action="<?= url('cart.php') ?>" class="sc-add" id="sc-add">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="page_id" id="sc-add-id" value="<?= (int)$products[0]['id'] ?>">
                            <input type="hidden" name="return" value="<?= e(url('showcase.php')) ?>">
                            <input type="number" name="quantity" value="1" min="1" step="1"
                                   class="sc-qty" aria-label="Quantity">
                            <button type="submit" class="sc-btn">Add to cart</button>
                        </form>
                    <?php elseif (!logged_in()): ?>
                        <a class="sc-btn" href="<?= url('register.php') ?>">See trade prices</a>
                    <?php elseif (distributor_status($pdo) === null): ?>
                        <a class="sc-btn" href="<?= url('apply.php') ?>">Apply for wholesale pricing</a>
                    <?php endif; ?>
                </div>

                <?php if ($dist_id === null): ?>
                    <p class="sc-note">
                        <?= logged_in()
                            ? 'Trade prices appear here once your distributor account is active.'
                            : 'Prices are shown to approved distributors.' ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- ============ Rotation dial ============
                 The signature of this page: it reads out the bottle's
                 actual angle, so the scroll never feels like it is just
                 moving a page — it is turning glass. -->
            <div class="sc-dial" id="sc-dial" aria-hidden="true">
                <svg viewBox="0 0 60 260" class="sc-dial-svg">
                    <path class="sc-dial-track" pathLength="360"
                          d="M30 14 A 120 120 0 0 1 30 246" />
                    <path class="sc-dial-fill" id="sc-dial-fill" pathLength="360"
                          stroke-dasharray="0 360" d="M30 14 A 120 120 0 0 1 30 246" />
                </svg>
                <span class="sc-degrees" id="sc-degrees">0&deg;</span>
            </div>

            <!-- Fades out as soon as the user scrolls -->
            <p class="sc-hint" id="sc-hint">
                <span class="sc-hint-key">Scroll</span>
                <span class="sc-hint-sep">·</span>
                <span class="sc-hint-key">Swipe</span>
                <span class="sc-hint-sep">·</span>
                <span class="sc-hint-key">&uarr;&darr;</span>
                to turn the bottle
            </p>
        </div><!-- .sc-stage -->
    </div><!-- .sc-track -->

    <!-- ============ Show more drawer ============
         Sits over the stage rather than in the scroll flow, so opening
         it never moves the user's place in the rotation. -->
    <div class="sc-drawer" id="sc-drawer" role="dialog" aria-modal="true"
         aria-labelledby="sc-drawer-name" hidden>
        <div class="sc-drawer-panel">
            <button type="button" class="sc-drawer-close" id="sc-drawer-close" aria-label="Close">&times;</button>
            <p class="sc-eyebrow"><span id="sc-drawer-cat"></span></p>
            <h2 id="sc-drawer-name"></h2>
            <div class="sc-drawer-body" id="sc-drawer-body"></div>
            <p class="sc-drawer-links">
                <a id="sc-drawer-link" href="#">Full product page &rarr;</a>
            </p>
        </div>
    </div>

    <!-- ============ The exit ============
         The showcase is the front door, not the whole shop. -->
    <section class="sc-outro">
        <p class="sc-eyebrow">End of the showcase</p>
        <h2>The full range</h2>
        <p class="sc-outro-copy">
            <?= count($products) ?> product<?= count($products) === 1 ? '' : 's' ?>
            in the showcase. The catalog has everything else — waters, kombuchas,
            wines and spirits, with search and category filters.
        </p>
        <a class="sc-btn sc-btn-lg" href="<?= url('index.php') ?>">Browse all products</a>
    </section>

    <!-- ============ Fallback ============
         Shown if WebGL is unavailable, the 3D library cannot load, or
         the visitor has asked for reduced motion. Same products, same
         permissions, no canvas. -->
    <section class="sc-fallback" id="sc-fallback" hidden>
        <h2>Product showcase</h2>
        <p class="sc-outro-copy">Showing the featured range without the 3D view.</p>
        <ul class="sc-fallback-list">
            <?php foreach ($products as $p): ?>
                <li>
                    <?php if ($p['photo']): ?>
                        <img src="<?= e($p['photo']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
                    <?php endif; ?>
                    <div>
                        <p class="sc-eyebrow"><?= e($p['category']) ?></p>
                        <h3><a href="<?= e($p['permalink']) ?>"><?= e($p['name']) ?></a></h3>
                        <p><?= e($p['teaser']) ?></p>
                        <?php if ($dist_id !== null): ?>
                            <p class="sc-fallback-price"><?= e($p['price']) ?></p>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <a class="sc-btn" href="<?= url('index.php') ?>">Browse all products</a>
    </section>
</div><!-- .sc-root -->

<noscript>
    <style>#sc-root .sc-track, #sc-root .sc-dial { display: none; }
           #sc-fallback { display: block !important; }</style>
</noscript>

<!-- ============================================================
     PHP -> JSON -> JS. The payload is embedded rather than fetched so
     the first frame needs no round trip. showcase-data.php serves the
     identical structure for anything that wants it later.
     ============================================================ -->
<script id="sc-data" type="application/json"><?= json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
    ) ?></script>

<script type="importmap">
{
  "imports": {
    "three": "https://cdn.jsdelivr.net/npm/three@0.169.0/build/three.module.js",
    "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.169.0/examples/jsm/"
  }
}
</script>
<script type="module"
        src="<?= url('assets/showcase/showcase.js') ?>?v=<?= @filemtime(__DIR__ . '/assets/showcase/showcase.js') ?: 1 ?>"></script>

<!-- If the module never runs (old browser, blocked CDN), reveal the fallback. -->
<script>
    setTimeout(function () {
        if (!document.getElementById('sc-root').classList.contains('is-ready')) {
            var f = document.getElementById('sc-fallback');
            if (f && !document.getElementById('sc-root').classList.contains('is-loading')) {
                document.getElementById('sc-root').classList.add('is-fallback');
                f.hidden = false;
            }
        }
    }, 8000);
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
