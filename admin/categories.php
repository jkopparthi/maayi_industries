<?php
// ============================================================
// REQUIREMENT 2.4 — create and update categories with an HTML form.
// Only logged-in admins can reach this.
// (Deleting categories is optional in the brief, so it is not included.)
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();   // req 7.1: admin-only, not just logged in

$errors = [];

// If an id is present we are editing that category, otherwise adding a new one.
$edit_id = clean_id($_GET['edit'] ?? null);
$name    = '';

if ($edit_id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE category_id = ?');
    $stmt->execute([$edit_id]);
    $row = $stmt->fetch();

    if ($row) {
        $name = $row['name'];
    } else {
        $edit_id = 0;   // not found, fall back to "add new"
    }
}

// ---- Handle the form ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name    = trim($_POST['name'] ?? '');
    $edit_id = clean_id($_POST['edit_id'] ?? null);

    if ($name === '') {
        $errors[] = 'Please enter a category name.';
    } else {
        // Don't allow two categories with the same name.
        if ($edit_id > 0) {
            $check = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE name = ? AND category_id <> ?');
            $check->execute([$name, $edit_id]);
        } else {
            $check = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE name = ?');
            $check->execute([$name]);
        }

        if ($check->fetchColumn() > 0) {
            $errors[] = 'A category with that name already exists.';
        }
    }

    if (!$errors) {
        if ($edit_id > 0) {
            $stmt = $pdo->prepare('UPDATE categories SET name = ? WHERE category_id = ?');
            $stmt->execute([$name, $edit_id]);
            $_SESSION['message'] = 'Category updated.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO categories (name) VALUES (?)');
            $stmt->execute([$name]);
            $_SESSION['message'] = 'Category added.';
        }

        header('Location: ' . url('admin/categories.php'));
        exit;
    }
}

// List of categories, with how many pages use each one.
$categories = $pdo->query(
    'SELECT categories.category_id, categories.name, COUNT(pages.page_id) AS page_count
       FROM categories
       LEFT JOIN pages ON pages.category_id = categories.category_id
      GROUP BY categories.category_id, categories.name
      ORDER BY categories.name'
)->fetchAll();

$title = 'Categories';
require_once __DIR__ . '/../includes/header.php';
?>

<h1>Categories</h1>

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
    <input type="hidden" name="edit_id" value="<?= (int)$edit_id ?>">

    <label><?= $edit_id > 0 ? 'Rename category' : 'New category' ?>
        <input type="text" name="name" value="<?= e($name) ?>" required>
    </label>

    <button type="submit"><?= $edit_id > 0 ? 'Save Changes' : 'Add Category' ?></button>

    <?php if ($edit_id > 0): ?>
        <a class="cancel" href="<?= url('admin/categories.php') ?>">Cancel</a>
    <?php endif; ?>
</form>

<table>
    <tr>
        <th>Name</th>
        <th>Pages</th>
        <th>Actions</th>
    </tr>
    <?php foreach ($categories as $c): ?>
        <tr>
            <td><?= e($c['name']) ?></td>
            <td><?= (int)$c['page_count'] ?></td>
            <td class="actions">
                <a href="<?= url('admin/categories.php?edit=' . $c['category_id']) ?>">Edit</a>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
