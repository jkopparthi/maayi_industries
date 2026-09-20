<?php
// ============================================================
// REQUIREMENT 3.1 — search for pages by keyword.
//   The keyword is matched against the title (and body) using a SQL
//   LIKE query with wildcards. Results are a list of links.
//
// REQUIREMENT 3.2 — narrow the search to one category.
//   A dropdown lists every category, plus "All categories". Choosing
//   a category adds an extra condition to the same search; choosing
//   "All categories" behaves exactly like 3.1 on its own.
//
// REQUIREMENT 3.3 — paginate the results.
//   Only N results are shown per page. Change RESULTS_PER_PAGE below
//   to test with a smaller or larger number. Prev/Next and numbered
//   links only appear when there is more than one page.
//
// No login is required — this is a public page.
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// ---- 3.3: results per page. Change this one number to test. ----
const RESULTS_PER_PAGE = 3;

// ---- Read the inputs ----
$keyword     = trim($_GET['q'] ?? '');
$category_id = clean_id($_GET['category'] ?? null);   // 0 = all categories
$current     = clean_id($_GET['page'] ?? null);
if ($current < 1) {
    $current = 1;
}

$searched = ($keyword !== '');
$results  = [];
$total    = 0;
$last_page = 1;

// Category list for the dropdown (requirement 3.2).
$categories = $pdo->query('SELECT category_id, name FROM categories ORDER BY name')->fetchAll();

if ($searched) {
    // Wildcards on both sides so a partial word matches anywhere in
    // the text. Any literal % or _ the user types is escaped with '!'
    // so it can't be mistaken for a SQL wildcard.
    $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword);
    $like    = '%' . $escaped . '%';

    $where  = "(pages.title LIKE :kw ESCAPE '!' OR pages.body LIKE :kw2 ESCAPE '!')";
    $params = [':kw' => $like, ':kw2' => $like];

    // Requirement 3.2: only add the category condition when one is chosen.
    if ($category_id > 0) {
        $where .= ' AND pages.category_id = :cat';
        $params[':cat'] = $category_id;
    }

    // ---- Count everything that matches, so we know how many pages exist ----
    $count = $pdo->prepare("SELECT COUNT(*) FROM pages WHERE $where");
    $count->execute($params);
    $total = (int)$count->fetchColumn();

    $last_page = max(1, (int)ceil($total / RESULTS_PER_PAGE));
    if ($current > $last_page) {
        $current = $last_page;
    }
    $offset = ($current - 1) * RESULTS_PER_PAGE;

    // ---- Fetch only this page's worth of results ----
    $sql = "SELECT pages.page_id, pages.title, pages.slug, pages.price,
                   categories.name AS category_name
              FROM pages
              LEFT JOIN categories ON pages.category_id = categories.category_id
             WHERE $where
             ORDER BY pages.title
             LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', RESULTS_PER_PAGE, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $results = $stmt->fetchAll();
}

/**
 * Build a link to a results page, keeping the keyword and category.
 */
function results_page_url(int $page_number, string $keyword, int $category_id): string {
    $params = ['q' => $keyword];
    if ($category_id > 0) {
        $params['category'] = $category_id;
    }
    if ($page_number > 1) {
        $params['page'] = $page_number;
    }
    return url('search.php') . '?' . http_build_query($params);
}

$title = 'Search';
// Prices are visible ONLY to approved distributors.
$dist_id = approved_distributor_id($pdo);

require_once __DIR__ . '/includes/header.php';
?>

<h1>Search Products</h1>

<form method="get" action="<?= url('search.php') ?>" class="box">
    <label>Keyword
        <input type="search" name="q" value="<?= e($keyword) ?>" placeholder="e.g. whisky" required>
    </label>

    <!-- Requirement 3.2: category dropdown -->
    <label>Category
        <select name="category">
            <option value="0">All categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['category_id'] ?>"
                    <?= $category_id === (int)$c['category_id'] ? 'selected' : '' ?>>
                    <?= e($c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <button type="submit">Search</button>
</form>

<?php if (!$searched): ?>
    <p class="hint">Enter a keyword above to search the product catalogue.</p>

<?php else: ?>

    <p class="hint">
        <?= $total ?> result<?= $total === 1 ? '' : 's' ?>
        for &ldquo;<strong><?= e($keyword) ?></strong>&rdquo;
        <?php if ($category_id > 0): ?>
            in
            <strong>
                <?php foreach ($categories as $c) {
                    if ((int)$c['category_id'] === $category_id) echo e($c['name']);
                } ?>
            </strong>
        <?php endif; ?>
        <?php if ($last_page > 1): ?>
            &middot; page <?= $current ?> of <?= $last_page ?>
        <?php endif; ?>
    </p>

    <?php if (!$results): ?>
        <p class="hint">No products matched. Try a different keyword or category.</p>

    <?php else: ?>
        <ul class="product-list">
            <?php foreach ($results as $r): ?>
                <li>
                    <a href="<?= permalink((int)$r['page_id'], $r['slug']) ?>"><?= e($r['title']) ?></a>
                    <span class="meta">
                        <?= $r['category_name'] ? e($r['category_name']) : 'Uncategorised' ?>
                        <?php if ($dist_id !== null): ?>
                            <?php $pr = price_for_distributor($pdo, $dist_id, (int)$r['page_id'], (float)$r['price']); ?>
                            &middot; K<?= number_format($pr['price'], 2) ?>
                            <?php if ($pr['special']): ?><span class="hint">(your price)</span><?php endif; ?>
                        <?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($last_page > 1): ?>
            <!-- Requirement 3.3: pagination, shown only when there is more than one page -->
            <nav class="pagination">
                <?php if ($current > 1): ?>
                    <a href="<?= e(results_page_url($current - 1, $keyword, $category_id)) ?>">&laquo; Previous</a>
                <?php endif; ?>

                <?php for ($n = 1; $n <= $last_page; $n++): ?>
                    <?php if ($n === $current): ?>
                        <span class="current"><?= $n ?></span>
                    <?php else: ?>
                        <a href="<?= e(results_page_url($n, $keyword, $category_id)) ?>"><?= $n ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($current < $last_page): ?>
                    <a href="<?= e(results_page_url($current + 1, $keyword, $category_id)) ?>">Next &raquo;</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
