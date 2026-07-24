<?php
// ============================================================
// Database connection (PDO)
// Change these four values if your MySQL setup is different.
// ============================================================

$host = 'localhost';
$db   = 'maayi_cms';
$user = 'root';
$pass = '';          // XAMPP default is an empty password

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}
