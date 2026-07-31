<?php
// ============================================================
// admin/roles.php — admin creates and names staff roles.
// Admin only. System roles (admin/member/distributor) cannot be
// deleted; new roles default to staff so they gain admin-area access.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$errors = [];

// ---- Delete a role ----
if (isset($_GET['delete'])) {
    $rid = clean_id($_GET['delete']);
    $role = null;
    if ($rid > 0) {
        $stmt = $pdo->prepare('SELECT * FROM roles WHERE role_id = ?');
        $stmt->execute([$rid]);
        $role = $stmt->fetch();
    }

    if (!$role) {
        $_SESSION['message'] = 'Role not found.';
    } elseif ((int)$role['is_system'] === 1) {
        $_SESSION['message'] = 'Built-in roles cannot be deleted.';
    } else {
        $inUse = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role = ?');
        $inUse->execute([$role['name']]);
        if ($inUse->fetchColumn() > 0) {
            $_SESSION['message'] = 'That role is assigned to one or more users; reassign them first.';
        } else {
            $del = $pdo->prepare('DELETE FROM roles WHERE role_id = ?');
            $del->execute([$rid]);
            $_SESSION['message'] = 'Role deleted.';
        }
    }
    header('Location: ' . url('admin/roles.php'));
    exit;
}

// ---- Create a role ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $label = trim($_POST['label'] ?? '');
    // Machine name: lowercase, letters/numbers/underscore only.
    $name  = strtolower(trim($_POST['name'] ?? ''));
    $name  = preg_replace('/[^a-z0-9_]/', '', $name);

    if ($label === '') {
        $errors[] = 'Please enter a display label.';
    }
    if ($name === '') {
        $errors[] = 'Please enter a role key (letters, numbers, underscore).';
    } else {
        $check = $pdo->prepare('SELECT COUNT(*) FROM roles WHERE name = ?');
        $check->execute([$name]);
        if ($check->fetchColumn() > 0) {
            $errors[] = 'A role with that key already exists.';
        }
    }

    if (!$errors) {
        $ins = $pdo->prepare(
            'INSERT INTO roles (name, label, is_staff, is_system) VALUES (?, ?, 1, 0)'
        );
        $ins->execute([$name, $label]);
        $_SESSION['message'] = 'Role created.';
        header('Location: ' . url('admin/roles.php'));
        exit;
    }
}

$roles = $pdo->query('SELECT * FROM roles ORDER BY is_staff DESC, name')->fetchAll();

$title = 'Roles';
require_once __DIR__ . '/../includes/header.php';
?>

<h1>Roles</h1>
<p class="hint">
    Staff roles can reach the admin area. Member and distributor are
    account types for the public side and are managed automatically.
</p>

<?php if ($errors): ?>
    <div class="error"><ul>
        <?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?>
    </ul></div>
<?php endif; ?>

<h2>Staff roles</h2>
<table>
    <tr><th>Label</th><th>Key</th><th>Actions</th></tr>
    <?php foreach ($roles as $r): ?>
        <?php if ((int)$r['is_staff'] !== 1) continue; ?>
    <tr>
        <td><?= e($r['label']) ?></td>
        <td><span class="meta"><?= e($r['name']) ?></span></td>
        <td class="actions">
            <?php if ((int)$r['is_system'] === 1): ?>
                <span class="meta">built-in</span>
            <?php else: ?>
                <a class="delete" href="<?= url('admin/roles.php?delete=' . $r['role_id']) ?>"
                   onclick="return confirm('Delete this role?');">Delete</a>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<h2>Add a role</h2>
<form method="post" class="box">
    <label>Display label
        <input type="text" name="label" placeholder="e.g. Accountant"
               value="<?= e($_POST['label'] ?? '') ?>" required>
    </label>
    <label>Role key <span class="meta">(lowercase, no spaces — stored on the account)</span>
        <input type="text" name="name" placeholder="e.g. accountant"
               value="<?= e($_POST['name'] ?? '') ?>" required>
    </label>
    <button type="submit">Create Role</button>
    <a class="cancel" href="<?= url('admin/users.php') ?>">Back to users</a>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
