<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Already signed in? Go straight to the admin list.
if (logged_in()) {
    header('Location: ' . url('admin/pages.php'));
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
            $_SESSION['message']  = 'You are now logged in.';
            header('Location: ' . url('admin/pages.php'));
            exit;
        }
        $error = 'Wrong username or password.';
    }
}

$title = 'Admin Log in';
require_once __DIR__ . '/includes/header.php';
?>

<h1>Admin Log in</h1>
<p class="hint">Use <code>admin</code> / <code>admin123</code> after running setup.php</p>

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
