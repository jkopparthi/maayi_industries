<?php
// ============================================================
// REQUIREMENT 7.5 — register for an account.
//
// The visitor supplies a username, an email address and a password.
// The password is typed twice, using inputs of type "password".
// If the two do not match they are told, and asked to try again.
//
// New accounts get the 'member' role, so registering does NOT give
// anyone admin rights (that is requirement 7.1).
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Already signed in? No reason to be here.
if (logged_in()) {
    header('Location: ' . url('index.php'));
    exit;
}

$errors   = [];
$username = '';
$email    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password_confirm'] ?? '';

    // ---- Validation ----
    if ($username === '') {
        $errors[] = 'Please choose a username.';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Your username must be at least 3 characters long.';
    }

    if ($email === '') {
        $errors[] = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'That does not look like a valid email address.';
    }

    if ($password === '') {
        $errors[] = 'Please choose a password.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Your password must be at least 6 characters long.';
    }

    // Requirement 7.5: the two passwords must match.
    if ($password !== '' && $password !== $password2) {
        $errors[] = 'The two passwords did not match. Please try again.';
    }

    // ---- Is the username or email already taken? ----
    if (!$errors) {
        $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
        $check->execute([$username]);
        if ($check->fetchColumn() > 0) {
            $errors[] = 'That username is already taken.';
        }

        $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetchColumn() > 0) {
            $errors[] = 'There is already an account using that email address.';
        }
    }

    // ---- Create the account ----
    if (!$errors) {
        // Requirement 7.3: hash and salt the password. password_hash()
        // generates the salt itself and stores it inside the hash string.
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $insert = $pdo->prepare(
            'INSERT INTO users (username, email, password_hash, role)
             VALUES (?, ?, ?, ?)'
        );
        $insert->execute([$username, $email, $hash, 'member']);

        $_SESSION['message'] = 'Your account has been created. You can log in now.';
        header('Location: ' . url('login.php'));
        exit;
    }
}

$title = 'Register';
require_once __DIR__ . '/includes/header.php';
?>

<h1>Create an Account</h1>

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
        <input type="text" name="username" value="<?= e($username) ?>" required autofocus>
    </label>

    <label>Email address
        <input type="email" name="email" value="<?= e($email) ?>" required>
    </label>

    <!-- Requirement 7.5: password entered twice, both type="password" -->
    <label>Password
        <input type="password" name="password" required>
    </label>

    <label>Confirm password
        <input type="password" name="password_confirm" required>
    </label>

    <button type="submit">Register</button>
    <a class="cancel" href="<?= url('login.php') ?>">I already have an account</a>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
