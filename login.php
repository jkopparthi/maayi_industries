<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Already signed in? Admins get the admin list; everyone else —
// distributors included — goes straight to the products they can order.
if (logged_in()) {
    header('Location: ' . url(is_admin() ? 'admin/pages.php' : 'index.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please fill in both fields.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // password_verify checks the plain password against the stored hash.
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];   // req 7.1

            // Requirement 7.4: confirm the login succeeded.
            $_SESSION['message'] = 'Welcome back, ' . $user['username'] . '. You are now logged in.';

            // Admins go to the admin area; members go to the public site.
            header('Location: ' . url(is_admin() ? 'admin/pages.php' : 'index.php'));
            exit;
        }
        $error = 'Wrong username or password.';
    }
}

$title = 'Admin Log in';
require_once __DIR__ . '/includes/header.php';
?>

<h1>Admin Log in</h1>
<p class="hint">No account yet? <a href="<?= url('register.php') ?>">Register here</a>.</p>

<?php if ($error !== ''): ?>
    <p class="error"><?= e($error) ?></p>
<?php endif; ?>

<form method="post" class="box">
    <label>Username
        <input type="text" name="username" required autofocus>
    </label>
    <label>Password
        <input type="password" name="password" required>
    </label>
    <button type="submit">Log in</button>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
