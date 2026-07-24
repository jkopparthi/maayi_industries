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

$id = clean_id($_GET['id'] ?? null);

$stmt = $pdo->prepare(
    'SELECT pages.*, categories.name AS category_name
       FROM pages
       LEFT JOIN categories ON pages.category_id = categories.category_id
      WHERE pages.page_id = ?'
);
$stmt->execute([$id]);
$page = $stmt->fetch();

// No such page
if (!$page) {
    $title = 'Page not found';
    require_once __DIR__ . '/includes/header.php';
    echo '<h1>Page not found</h1>';
    echo '<p><a href="' . url('index.php') . '">Back to all products</a></p>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$errors = [];
$author = '';
$body   = '';

// ---- Handle a new comment (requirement 2.9) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    
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

        
        $_SESSION['message'] = 'Thank you, your comment has been posted.';
        header('Location: ' . url('page.php?id=' . $id));
        exit;
    }
}

// Newest comments first (requirement 2.9).
$stmt = $pdo->prepare('SELECT * FROM comments WHERE page_id = ? ORDER BY created_at DESC');
$stmt->execute([$id]);
$comments = $stmt->fetchAll();

$title = $page['title'];
require_once __DIR__ . '/includes/header.php';
?>

<p class="crumb"><a href="<?= url('index.php') ?>">&laquo; All products</a></p>

<article class="box">
    <h1><?= e($page['title']) ?></h1>
    <p class="meta">
        <?= $page['category_name'] ? e($page['category_name']) : 'Uncategorised' ?>
        &middot; K<?= number_format((float)$page['price'], 2) ?>
    </p>
    <p><?= nl2br(e($page['body'])) ?></p>
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

<!-- Requirement 2.9:  -->
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
