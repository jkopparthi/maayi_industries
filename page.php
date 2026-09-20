<?php
// ============================================================
// REQUIREMENT 2.7 — the page a visitor lands on after clicking a link.
// REQUIREMENT 2.9 — a comment form, with the comments shown underneath
//                   in reverse chronological order (newest first).
//                   The comment form is plain text, not WYSIWYG.
// No login is needed.
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$id   = clean_id($_GET['id'] ?? null);
// Requirement 5.4/5.5: the URL carries BOTH the numeric id and the slug
// (mod_rewrite passes them here as ?id=..&slug=..). They must match the
// database TOGETHER - change either one and the URL stops working.
$slug = strtolower(trim($_GET['slug'] ?? ''));

$stmt = $pdo->prepare(
    'SELECT pages.*, categories.name AS category_name
       FROM pages
       LEFT JOIN categories ON pages.category_id = categories.category_id
      WHERE pages.page_id = ?'
);
$stmt->execute([$id]);
$page = $stmt->fetch();

// The id must exist AND the slug in the URL must be that page's slug.
if ($page && $slug !== $page['slug']) {
    $page = false;   // wrong slug for this id -> treat as not found
}

// No such page: show a simple message rather than a broken screen.
if (!$page) {
    http_response_code(404);
    $title = 'Page not found';
    require_once __DIR__ . '/includes/header.php';
    echo '<h1>Page not found</h1>';
    echo '<p><a href="' . url('index.php') . '">Back to all products</a></p>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Prices are visible ONLY to approved distributors.
$dist_id = approved_distributor_id($pdo);
$img     = page_image($pdo, (int)$page['page_id']);
$pricing = ($dist_id !== null)
    ? price_for_distributor($pdo, $dist_id, (int)$page['page_id'], (float)$page['price'])
    : null;

$errors = [];
$author = '';
$body   = '';

// ---- Handle a new comment (requirement 2.9) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Visitors are not logged in, so they supply their own name.
    $author = trim($_POST['author_name'] ?? '');
    $body   = trim($_POST['body'] ?? '');

    if ($author === '') {
        $errors[] = 'Please enter your name.';
    }
    if ($body === '') {
        $errors[] = 'Please enter a comment.';
    }

    if (!$errors) {
        $insert = $pdo->prepare(
            'INSERT INTO comments (page_id, author_name, body) VALUES (?, ?, ?)'
        );
        $insert->execute([$id, $author, $body]);

        // Redirect after saving so a refresh doesn't post the comment twice.
        $_SESSION['message'] = 'Thank you, your comment has been posted.';
        header('Location: ' . permalink($id, $page['slug']));
        exit;
    }
}

// Newest comments first (requirement 2.9).
// Hidden comments (moderated by an admin) stay out of the public page.
$stmt = $pdo->prepare(
    'SELECT * FROM comments WHERE page_id = ? AND is_hidden = 0
      ORDER BY created_at DESC'
);
$stmt->execute([$id]);
$comments = $stmt->fetchAll();

$title = $page['title'];
require_once __DIR__ . '/includes/header.php';
?>

<p class="crumb"><a href="<?= url('index.php') ?>">&laquo; All products</a></p>

<article class="product-detail">
    <div class="row g-4">

        <!-- LEFT: fixed image container, image centered inside it -->
        <div class="col-md-5">
            <div class="product-media">
                <?php if ($img): ?>
                    <img src="<?= url('uploads/' . rawurlencode($img['filename'])) ?>"
                         alt="<?= e($page['title']) ?>">
                <?php else: ?>
                    <span class="product-media-placeholder">
                        <span class="ph-letter"><?= e(mb_substr($page['title'], 0, 1)) ?></span>
                        <span class="ph-brand">Maayi</span>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT: title, price, and add-to-cart beside the image -->
        <div class="col-md-7">
            <h1><?= e($page['title']) ?></h1>

            <p class="meta">
                <?= $page['category_name'] ? e($page['category_name']) : 'Uncategorised' ?>
                <?php if ($pricing !== null): ?>
                    <?php if ($pricing['special']): ?>
                        &middot; <s>K<?= number_format($pricing['base'], 2) ?></s>
                        <strong>K<?= number_format($pricing['price'], 2) ?></strong>
                        <span class="hint">(your distributor price)</span>
                    <?php else: ?>
                        &middot; <strong>K<?= number_format($pricing['price'], 2) ?></strong>
                    <?php endif; ?>
                <?php endif; ?>
            </p>

            <?php if ($pricing === null): ?>
                <p class="hint">Prices are shown to approved distributors. <?php
                    if (!logged_in()) {
                        echo '<a href="' . url('login.php') . '">Log in</a> or <a href="'
                           . url('register.php') . '">register</a>.';
                    } elseif (($_SESSION['role'] ?? '') === 'member') {
                        echo '<a href="' . url('apply.php') . '">Apply to become a distributor</a>.';
                    }
                ?></p>
            <?php endif; ?>

            <?php if ($dist_id !== null): ?>
                <form method="post" action="<?= url('cart.php') ?>" class="add-to-cart">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="page_id" value="<?= (int)$page['page_id'] ?>">
                    <input type="hidden" name="return" value="<?= e(permalink((int)$page['page_id'], $page['slug'])) ?>">
                    <label>Qty
                        <input type="number" name="quantity" value="1" min="1" style="width:4.5rem">
                    </label>
                    <button type="submit" class="btn btn-primary">Add to cart</button>
                </form>
            <?php endif; ?>

            <div class="product-body"><?= display_body($page['body']) ?></div>
        </div>
    </div>
</article>

<h2>Comments (<?= count($comments) ?>)</h2>

<?php if (!$comments): ?>
    <p class="hint">No comments yet. Be the first.</p>
<?php else: ?>
    <?php foreach ($comments as $c): ?>
        <div class="comment">
            <p class="meta">
                <strong><?= e($c['author_name']) ?></strong>
                &middot; <?= date('j M Y, H:i', strtotime($c['created_at'])) ?>
            </p>
            <p><?= nl2br(e($c['body'])) ?></p>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<h2>Leave a comment</h2>

<?php if ($errors): ?>
    <div class="error">
        <ul>
            <?php foreach ($errors as $message): ?>
                <li><?= e($message) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Requirement 2.9: a plain text comment form (deliberately not WYSIWYG) -->
<form method="post" class="box">
    <label>Your name
        <input type="text" name="author_name" value="<?= e($author) ?>" required>
    </label>
    <label>Comment
        <textarea name="body" rows="4" required><?= e($body) ?></textarea>
    </label>
    <button type="submit">Post Comment</button>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
