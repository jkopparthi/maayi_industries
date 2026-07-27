<?php
// ============================================================
// REQUIREMENT 7.2 — admin area for managing user accounts.
// REQUIREMENT 7.1 — this is an admin-only task, so it uses
//                   require_admin(), not just require_login().
//
// Admins can view all users, add, update and delete them.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

// ---- Deleting a user ----
if (isset($_GET['delete'])) {
    $delete_id = clean_id($_GET['delete']);

    if ($delete_id < 1) {
        $_SESSION['message'] = 'Invalid user id.';
    } elseif ($delete_id === (int)$_SESSION['user_id']) {
        // Stop an admin locking themselves out.
        $_SESSION['message'] = 'You cannot delete the account you are logged in with.';
    } else {
        // Don't allow the last admin to be removed.
        $admins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

        $check = $pdo->prepare('SELECT role FROM users WHERE user_id = ?');
        $check->execute([$delete_id]);
        $target = $check->fetch();

        if ($target && $target['role'] === 'admin' && $admins <= 1) {
            $_SESSION['message'] = 'You cannot delete the only administrator.';
        } else {
            $stmt = $pdo->prepare('DELETE FROM users WHERE user_id = ?');
            $stmt->execute([$delete_id]);
            $_SESSION['message'] = 'User deleted.';
        }
    }

    header('Location: ' . url('admin/users.php'));
    exit;
}

$users = $pdo->query(
    'SELECT user_id, username, email, role, created_at
       FROM users
      ORDER BY role, username'
)->fetchAll();

$title = 'Manage Users';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="head-row">
    <h1>Manage Users</h1>
    <a class="button" href="<?= url('admin/user-form.php') ?>">Add New User</a>
</div>

<p class="hint">
    Administrators can manage pages, categories and other users.
    Members can log in but cannot reach the admin area.
</p>

<table>
    <tr>
        <th>Username</th>
        <th>Email</th>
        <th>Role</th>
        <th>Created</th>
        <th>Actions</th>
    </tr>

    <?php foreach ($users as $u): ?>
        <tr>
            <td>
                <?= e($u['username']) ?>
                <?php if ((int)$u['user_id'] === (int)$_SESSION['user_id']): ?>
                    <span class="meta">(you)</span>
                <?php endif; ?>
            </td>
            <td><?= e($u['email']) ?></td>
            <td><?= e($u['role']) ?></td>
            <td><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
            <td class="actions">
                <a href="<?= url('admin/user-form.php?id=' . $u['user_id']) ?>">Edit</a>

                <?php if ((int)$u['user_id'] !== (int)$_SESSION['user_id']): ?>
                    <a class="delete"
                       href="<?= url('admin/users.php?delete=' . $u['user_id']) ?>"
                       onclick="return confirm('Delete this user account?');">Delete</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
