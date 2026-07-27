<?php
// ============================================================
// Run this ONCE in your browser to create the admin account:
//     http://localhost/cms/setup.php
// The password is hashed here by PHP, so the stored hash is always valid.
// ============================================================
require_once __DIR__ . '/includes/db.php';

$username = 'admin';
$email    = 'admin@maayi.example';
$password = 'admin123';

$check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
$check->execute([$username]);

if ($check->fetchColumn() > 0) {
    echo "The admin account already exists.";
} else {
    // The first account is an admin, so it can manage other users (req 7.2).
    $insert = $pdo->prepare(
        'INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)'
    );
    $insert->execute([
        $username,
        $email,
        password_hash($password, PASSWORD_DEFAULT),   // req 7.3
        'admin'
    ]);
    echo "Admin account created.";
}

echo "<p>Log in with <strong>$username</strong> / <strong>$password</strong></p>";
echo '<p><a href="login.php">Go to the login page</a></p>';
