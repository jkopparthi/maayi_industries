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

$q = trim($_GET['q'] ?? '');

if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = $pdo->prepare(
        "SELECT u.user_id, u.username, u.email, u.role, u.created_at,
                COALESCE(r.label, u.role) AS role_label
           FROM users u
      LEFT JOIN roles r ON r.name = u.role
          WHERE COALESCE(r.is_staff, 0) = 1
            AND (u.username LIKE ? OR u.email LIKE ? OR COALESCE(r.label, u.role) LIKE ?)
          ORDER BY u.role, u.username"
    );
    $stmt->execute([$like, $like, $like]);
    $users = $stmt->fetchAll();
} else {
    $users = $pdo->query(
        "SELECT u.user_id, u.username, u.email, u.role, u.created_at,
                COALESCE(r.label, u.role) AS role_label
           FROM users u
      LEFT JOIN roles r ON r.name = u.role
          WHERE COALESCE(r.is_staff, 0) = 1
          ORDER BY u.role, u.username"
    )->fetchAll();
}

$title = 'Manage Staff';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="head-row">
    <h1>Manage Staff</h1>
    <a class="button" href="<?= url('admin/user-form.php') ?>">Add Staff User</a>
</div>

<p class="hint">
    <?php if ($q !== ''): ?>
        Showing staff matching "<strong><?= e($q) ?></strong>".
        <a href="<?= url('admin/users.php') ?>">Clear</a>
    <?php else: ?>
        Company staff accounts only. Distributors and their applications are
        managed under <a href="<?= url('admin/distributors.php') ?>">Distributors</a>.
        Create or name roles under <a href="<?= url('admin/roles.php') ?>">Roles</a>.
    <?php endif; ?>
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
            <td><?= e($u['role_label']) ?></td>
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
