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
require_login();

$id        = clean_id($_GET['id'] ?? null);   // 0 means "new page"
$is_edit   = ($id > 0);
$errors    = [];


$page = ['title' => '', 'category_id' => '', 'body' => '', 'price' => ''];


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


$categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $page['title']       = trim($_POST['title'] ?? '');
    $page['category_id'] = $_POST['category_id'] ?? '';
    $page['body']        = trim($_POST['body'] ?? '');
    $page['price']       = trim($_POST['price'] ?? '');

    // Validation
    if ($page['title'] === '') {
        $errors[] = 'Please enter a title.';
    }
    if ($page['body'] === '') {
        $errors[] = 'Please enter a description.';
    }
    if ($page['price'] === '' || !is_numeric($page['price'])) {
        $errors[] = 'Please enter a price as a number.';
    }

    if (!$errors) {
   
        $category = ($page['category_id'] === '') ? null : (int)$page['category_id'];

        if ($is_edit) {
            $stmt = $pdo->prepare(
                'UPDATE pages
                    SET title = ?, category_id = ?, body = ?, price = ?
                  WHERE page_id = ?'
            );
            $stmt->execute([$page['title'], $category, $page['body'], $page['price'], $id]);
            $_SESSION['message'] = 'Page updated.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO pages (title, category_id, body, price)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$page['title'], $category, $page['body'], $page['price']]);
            $_SESSION['message'] = 'Page created.';
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

<form method="post" class="box">

    <label>Title
        <input type="text" name="title" value="<?= e($page['title']) ?>" required>
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
        <textarea name="body" rows="7" required><?= e($page['body']) ?></textarea>
    </label>

    <button type="submit"><?= $is_edit ? 'Save Changes' : 'Create Page' ?></button>
    <a class="cancel" href="<?= url('admin/pages.php') ?>">Cancel</a>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
