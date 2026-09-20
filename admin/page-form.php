<?php
// ============================================================
// REQUIREMENT 2.1 — create a new page from an HTML form.
// REQUIREMENT 2.2 — edit an existing page.
// REQUIREMENT 2.4 — assign a category using a dropdown (select element).
//
// One file does both jobs:
//   page-form.php          -> blank form, creates a new page
//   page-form.php?id=5     -> form filled in, updates page 5
//
// Only logged-in admins can reach it.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();   // req 7.1: admin-only, not just logged in

$id        = clean_id($_GET['id'] ?? null);   // 0 means "new page"
$is_edit   = ($id > 0);
$errors    = [];

// ------------------------------------------------------------
// REQUIREMENT 2.5 — moderate this product's comments right here,
// on the same edit screen, instead of a separate admin page.
// A distinct field name (comment_action) keeps this separate from
// the page-save form submitted further down.
// ------------------------------------------------------------
if ($is_edit && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['comment_action'])) {
    $comment_id = clean_id($_POST['comment_id'] ?? null);
    $action     = $_POST['comment_action'];

    if ($comment_id > 0) {
        switch ($action) {
            case 'hide':
                $pdo->prepare('UPDATE comments SET is_hidden = 1 WHERE comment_id = ?')
                    ->execute([$comment_id]);
                $_SESSION['message'] = 'Comment hidden from public view.';
                break;
            case 'show':
                $pdo->prepare('UPDATE comments SET is_hidden = 0 WHERE comment_id = ?')
                    ->execute([$comment_id]);
                $_SESSION['message'] = 'Comment is public again.';
                break;
            case 'disemvowel':
                $get = $pdo->prepare('SELECT body FROM comments WHERE comment_id = ?');
                $get->execute([$comment_id]);
                if ($row = $get->fetch()) {
                    $pdo->prepare('UPDATE comments SET body = ? WHERE comment_id = ?')
                        ->execute([disemvowel($row['body']), $comment_id]);
                    $_SESSION['message'] = 'Comment disemvowelled.';
                }
                break;
            case 'delete':
                $pdo->prepare('DELETE FROM comments WHERE comment_id = ?')
                    ->execute([$comment_id]);
                $_SESSION['message'] = 'Comment deleted.';
                break;
        }
    }

    // Redirect back to the same edit page (prevents re-submitting on refresh).
    header('Location: ' . url('admin/page-form.php?id=' . $id . '#comments'));
    exit;
}

// Start with empty values for a new page.
$page = ['title' => '', 'slug' => '', 'category_id' => '', 'body' => '', 'price' => ''];

// If we are editing, load the existing row.
if ($is_edit) {
    $stmt = $pdo->prepare('SELECT * FROM pages WHERE page_id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();

    if (!$found) {
        $_SESSION['message'] = 'That page does not exist.';
        header('Location: ' . url('admin/pages.php'));
        exit;
    }
    $page = $found;
}

// Existing image, if any (drives the preview + remove checkbox on edit).
$existing_image = $is_edit ? page_image($pdo, $id) : null;

// Categories for the dropdown (requirement 2.4).
$categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();

// ---- Handle the form submission ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $page['title']       = trim($_POST['title'] ?? '');

    // Requirement 5.4: the admin may type their own slug; leaving it
    // blank derives it from the title. Either way slugify() runs, so
    // spaces automatically become dashes and stray characters are dropped.
    $raw_slug            = trim($_POST['slug'] ?? '');
    $page['slug']        = slugify($raw_slug !== '' ? $raw_slug : $page['title']);
    $page['category_id'] = $_POST['category_id'] ?? '';
    // WYSIWYG sends HTML: whitelist-sanitise it rather than escape it,
    // otherwise the tags would display as literal text (req 2.6).
    $page['body']        = sanitize_html($_POST['body'] ?? '');
    $page['price']       = trim($_POST['price'] ?? '');

    // Validation
    if ($page['title'] === '') {
        $errors[] = 'Please enter a title.';
    }
    if (trim(strip_tags($page['body'])) === '') {
        $errors[] = 'Please enter a description.';
    }
    if ($page['price'] === '' || !is_numeric($page['price'])) {
        $errors[] = 'Please enter a price as a number.';
    }

    if (!$errors) {
        // An empty dropdown choice is stored as NULL, not as an empty string.
        $category = ($page['category_id'] === '') ? null : (int)$page['category_id'];

        if ($is_edit) {
            $stmt = $pdo->prepare(
                'UPDATE pages
                    SET title = ?, slug = ?, category_id = ?, body = ?, price = ?
                  WHERE page_id = ?'
            );
            $stmt->execute([$page['title'], $page['slug'], $category, $page['body'], $page['price'], $id]);

            // Image handling: remove if ticked, then save a new one if chosen.
            if (!empty($_POST['remove_image'])) {
                page_image_delete($pdo, $id);
            }
            $img_error = page_image_save($pdo, $id, $_FILES['image'] ?? []);

            $_SESSION['message'] = 'Page updated.'
                . ($img_error !== '' ? ' But the image was rejected: ' . $img_error : '');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO pages (title, slug, category_id, body, price)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$page['title'], $page['slug'], $category, $page['body'], $page['price']]);
            $new_id = (int)$pdo->lastInsertId();

            // Optional image on create. A rejected image does not stop
            // the product being created - the admin is told why.
            $img_error = page_image_save($pdo, $new_id, $_FILES['image'] ?? []);

            $_SESSION['message'] = 'Page created.'
                . ($img_error !== '' ? ' But the image was rejected: ' . $img_error : '');
        }

        header('Location: ' . url('admin/pages.php'));
        exit;
    }
}

$title = $is_edit ? 'Edit Page' : 'Add New Page';
require_once __DIR__ . '/../includes/header.php';
?>

<h1><?= $is_edit ? 'Edit Page' : 'Add New Page' ?></h1>

<?php if ($errors): ?>
    <div class="error">
        <ul>
            <?php foreach ($errors as $message): ?>
                <li><?= e($message) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" class="box" enctype="multipart/form-data">

    <label>Title
        <input type="text" name="title" value="<?= e($page['title']) ?>" required>
    </label>

    <label>Permalink slug
        <input type="text" name="slug" value="<?= e($page['slug']) ?>"
               placeholder="left blank = generated from the title">
        <span class="meta">
            Lowercase letters, numbers and dashes; spaces convert to dashes automatically.
            <?php if ($is_edit && $page['slug'] !== ''): ?>
                Current URL: <code>/<?= (int)$id ?>/<?= e($page['slug']) ?>/</code>
            <?php endif; ?>
        </span>
    </label>

    <!-- Requirement 2.4: the category dropdown -->
    <label>Category
        <select name="category_id">
            <option value="">No category</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['category_id'] ?>"
                    <?= ((string)$page['category_id'] === (string)$c['category_id']) ? 'selected' : '' ?>>
                    <?= e($c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>Price (Kwacha)
        <input type="number" step="0.01" min="0" name="price" value="<?= e($page['price']) ?>" required>
    </label>

    <label>Description
        <textarea id="body" name="body" rows="12"><?= e($page['body']) ?></textarea>
    </label>

    <label>Product image
        <input type="file" name="image" accept="image/*">
        <span class="meta">JPEG, PNG, GIF or WEBP, up to 5 MB. Uploading replaces the current image.</span>
    </label>

    <?php if ($existing_image): ?>
        <div class="current-image">
            <img src="<?= url('uploads/' . rawurlencode($existing_image['filename'])) ?>"
                 alt="<?= e($page['title']) ?>">
            <label class="checkline">
                <input type="checkbox" name="remove_image" value="1">
                Remove this image
            </label>
        </div>
    <?php endif; ?>

    <button type="submit"><?= $is_edit ? 'Save Changes' : 'Create Page' ?></button>
    <a class="cancel" href="<?= url('admin/pages.php') ?>">Cancel</a>
</form>

<?php if ($is_edit): ?>
    <!-- ============================================================
         REQUIREMENT 2.5 — this product's comments, moderated right here.
         Only shows once the product exists (nothing to comment on yet
         when creating a new one).
         ============================================================ -->
    <?php
    $product_comments = $pdo->prepare(
        'SELECT * FROM comments WHERE page_id = ? ORDER BY created_at DESC'
    );
    $product_comments->execute([$id]);
    $product_comments = $product_comments->fetchAll();
    ?>

    <h2 id="comments">Comments on this product (<?= count($product_comments) ?>)</h2>

    <?php if (!$product_comments): ?>
        <p class="hint">No comments on this product yet.</p>
    <?php else: ?>
        <?php foreach ($product_comments as $c): ?>
            <div class="comment">
                <p class="meta">
                    <strong><?= e($c['author_name']) ?></strong>
                    &middot; <?= date('j M Y, H:i', strtotime($c['created_at'])) ?>
                    <?php if ($c['is_hidden']): ?>
                        <span class="badge-hidden">hidden</span>
                    <?php endif; ?>
                </p>
                <p><?= nl2br(e($c['body'])) ?></p>

                <form method="post" class="mod-row">
                    <input type="hidden" name="comment_id" value="<?= (int)$c['comment_id'] ?>">

                    <?php if ($c['is_hidden']): ?>
                        <button name="comment_action" value="show">Show</button>
                    <?php else: ?>
                        <button name="comment_action" value="hide" class="ghost">Hide</button>
                    <?php endif; ?>

                    <button name="comment_action" value="disemvowel" class="ghost"
                            onclick="return confirm('Strip the vowels from this comment? This cannot be undone.');">
                        Disemvowel
                    </button>

                    <button name="comment_action" value="delete" class="danger"
                            onclick="return confirm('Delete this comment permanently?');">
                        Delete
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>

<!-- ============================================================
     Requirement 2.6 — WYSIWYG editor (TinyMCE, free CDN build).
     valid_elements mirrors the PHP sanitiser whitelist, so what the
     editor produces is what the server accepts.
     ============================================================ -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    tinymce.init({
        selector: '#body',
        height: 320,
        menubar: false,
        plugins: 'lists link',
        toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link | removeformat',
        block_formats: 'Paragraph=p; Heading 2=h2; Heading 3=h3',
        valid_elements: 'p,br,strong/b,em/i,u,s,ul,ol,li,blockquote,h2,h3,h4,a[href|title|target|rel]',
        branding: false,
        setup: function (editor) {
            editor.on('change', function () { editor.save(); });
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
