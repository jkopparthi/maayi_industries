<?php
// ============================================================
// REQUIREMENT 7.2 — add a new user, or update an existing one.
// REQUIREMENT 7.1 — admin only.
//
//   user-form.php        -> blank form, creates a user
//   user-form.php?id=5   -> edit user 5
//
// When editing, leaving the password blank keeps the current one.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$id      = clean_id($_GET['id'] ?? null);   // 0 means "new user"
$is_edit = ($id > 0);
$errors  = [];

// Staff roles available for assignment (admin, accountant, any custom).
$staff_roles = $pdo->query(
    'SELECT name, label FROM roles WHERE is_staff = 1 ORDER BY name'
)->fetchAll();
$staff_names = array_column($staff_roles, 'name');
$default_role = in_array('accountant', $staff_names, true) ? 'accountant'
              : ($staff_names[0] ?? 'admin');

$user = ['username' => '', 'email' => '', 'role' => $default_role];

if ($is_edit) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();

    if (!$found) {
        $_SESSION['message'] = 'That user does not exist.';
        header('Location: ' . url('admin/users.php'));
        exit;
    }
    $user = $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $user['username'] = trim($_POST['username'] ?? '');
    $user['email']    = trim($_POST['email'] ?? '');
    // Only allow assigning a valid staff role.
    $posted_role      = $_POST['role'] ?? $default_role;
    $user['role']     = in_array($posted_role, $staff_names, true) ? $posted_role : $default_role;
    $password         = $_POST['password'] ?? '';
    $password2        = $_POST['password_confirm'] ?? '';

    // ---- Validation ----
    if ($user['username'] === '') {
        $errors[] = 'Please enter a username.';
    }

    if ($user['email'] === '') {
        $errors[] = 'Please enter an email address.';
    } elseif (!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'That does not look like a valid email address.';
    }

    // A new user must have a password. When editing, a blank password
    // simply means "leave it as it is".
    if (!$is_edit && $password === '') {
        $errors[] = 'Please set a password for the new user.';
    }
    if ($password !== '' && strlen($password) < 6) {
        $errors[] = 'The password must be at least 6 characters long.';
    }
    if ($password !== '' && $password !== $password2) {
        $errors[] = 'The two passwords did not match.';
    }

    // ---- Uniqueness ----
    if (!$errors) {
        $sql = $is_edit
            ? 'SELECT COUNT(*) FROM users WHERE username = ? AND user_id <> ?'
            : 'SELECT COUNT(*) FROM users WHERE username = ?';
        $check = $pdo->prepare($sql);
        $check->execute($is_edit ? [$user['username'], $id] : [$user['username']]);
        if ($check->fetchColumn() > 0) {
            $errors[] = 'That username is already taken.';
        }

        $sql = $is_edit
            ? 'SELECT COUNT(*) FROM users WHERE email = ? AND user_id <> ?'
            : 'SELECT COUNT(*) FROM users WHERE email = ?';
        $check = $pdo->prepare($sql);
        $check->execute($is_edit ? [$user['email'], $id] : [$user['email']]);
        if ($check->fetchColumn() > 0) {
            $errors[] = 'That email address is already in use.';
        }
    }

    // Don't let the last admin demote themselves away from admin.
    if (!$errors && $is_edit && $user['role'] !== 'admin') {
        $admins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        $was    = $pdo->prepare('SELECT role FROM users WHERE user_id = ?');
        $was->execute([$id]);
        if ($was->fetchColumn() === 'admin' && $admins <= 1) {
            $errors[] = 'You cannot remove the only administrator.';
        }
    }

    // ---- Save ----
    if (!$errors) {
        if ($is_edit) {
            if ($password !== '') {
                // Requirement 7.3: hash the new password.
                $stmt = $pdo->prepare(
                    'UPDATE users SET username = ?, email = ?, role = ?, password_hash = ?
                      WHERE user_id = ?'
                );
                $stmt->execute([
                    $user['username'], $user['email'], $user['role'],
                    password_hash($password, PASSWORD_DEFAULT), $id
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE users SET username = ?, email = ?, role = ? WHERE user_id = ?'
                );
                $stmt->execute([$user['username'], $user['email'], $user['role'], $id]);
            }

            // If the admin edited their own account, refresh the session role.
            if ($id === (int)$_SESSION['user_id']) {
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];
            }

            $_SESSION['message'] = 'User updated.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([
                $user['username'], $user['email'],
                password_hash($password, PASSWORD_DEFAULT), $user['role']
            ]);
            $_SESSION['message'] = 'User created.';
        }

        header('Location: ' . url('admin/users.php'));
        exit;
    }
}

$title = $is_edit ? 'Edit User' : 'Add User';
require_once __DIR__ . '/../includes/header.php';
?>

<h1><?= $is_edit ? 'Edit User' : 'Add New User' ?></h1>

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

    <label>Username
        <input type="text" name="username" value="<?= e($user['username']) ?>" required>
    </label>

    <label>Email address
        <input type="email" name="email" value="<?= e($user['email']) ?>" required>
    </label>

    <label>Role
        <select name="role">
            <?php foreach ($staff_roles as $sr): ?>
                <option value="<?= e($sr['name']) ?>"
                    <?= $user['role'] === $sr['name'] ? 'selected' : '' ?>>
                    <?= e($sr['label']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>Password<?= $is_edit ? ' <span class="meta">(leave blank to keep the current one)</span>' : '' ?>
        <input type="password" name="password" <?= $is_edit ? '' : 'required' ?>>
    </label>

    <label>Confirm password
        <input type="password" name="password_confirm" <?= $is_edit ? '' : 'required' ?>>
    </label>

    <button type="submit"><?= $is_edit ? 'Save Changes' : 'Create User' ?></button>
    <a class="cancel" href="<?= url('admin/users.php') ?>">Cancel</a>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
