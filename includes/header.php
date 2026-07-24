<?php
require_once __DIR__ . '/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($title) ? e($title) : 'Maayi Industries' ?></title>
    <link rel="stylesheet" href="<?= url('style.css') ?>">
</head>
<body>

<header class="topbar">
    <a class="brand" href="<?= url('index.php') ?>">Maayi Industries</a>
    <nav>
        <a href="<?= url('index.php') ?>">Products</a>
        <?php if (logged_in()): ?>
            <a href="<?= url('admin/pages.php') ?>">Manage Pages</a>
            <a href="<?= url('admin/categories.php') ?>">Categories</a>
            <span class="user"><?= e($_SESSION['username']) ?></span>
            <a href="<?= url('logout.php') ?>">Log out</a>
        <?php else: ?>
            <a href="<?= url('login.php') ?>">Admin Log in</a>
        <?php endif; ?>
    </nav>
</header>

<main class="wrap">

<?php if (!empty($_SESSION['message'])): ?>
    <p class="notice"><?= e($_SESSION['message']) ?></p>
    <?php unset($_SESSION['message']); ?>
<?php endif; ?>
