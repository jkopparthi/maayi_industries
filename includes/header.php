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
            <?php if (is_admin()): ?>
                <!-- Requirement 7.1: admin-only links -->
                <a href="<?= url('admin/pages.php') ?>">Manage Pages</a>
                <a href="<?= url('admin/categories.php') ?>">Categories</a>
                <a href="<?= url('admin/users.php') ?>">Users</a>
                <a href="<?= url('admin/requests.php') ?>">Applications</a>
                <a href="<?= url('admin/pricing.php') ?>">Pricing</a>
            <?php endif; ?>
            <?php
                // "Become a distributor" — only for a plain member who
                // has NOT already applied. Needs $pdo (every page that
                // includes this header has already required db.php).
                if (($_SESSION['role'] ?? '') === 'member'
                    && isset($pdo) && distributor_status($pdo) === null): ?>
                <a href="<?= url('apply.php') ?>">Become a distributor</a>
            <?php endif; ?>
            <span class="user">
                <?= e($_SESSION['username']) ?><?= is_admin() ? ' (admin)' : '' ?>
            </span>
            <a href="<?= url('logout.php') ?>">Log out</a>
        <?php else: ?>
            <a href="<?= url('login.php') ?>">Log in</a>
            <a href="<?= url('register.php') ?>">Register</a>
        <?php endif; ?>
    </nav>
</header>

<!-- Requirement 3.1: a search form available at the top of every page -->
<div class="searchbar">
    <form action="<?= url('search.php') ?>" method="get" class="searchform">
        <input type="search" name="q" placeholder="Search products…"
               value="<?= isset($_GET['q']) ? e($_GET['q']) : '' ?>">
        <button type="submit">Search</button>
    </form>
</div>

<main class="wrap">

<?php
    // Distributor status banner — shown ONLY after the user has applied.
    $dstat = (isset($pdo)) ? distributor_status($pdo) : null;
    if ($dstat !== null):
?>
    <div class="status-banner <?= $dstat['css'] ?>">
        <span class="dot"></span>
        <span class="label">Distributor: <?= e($dstat['label']) ?></span>
        <span class="msg">
            <?php if ($dstat['status'] === 'pending'): ?>
                Your application for <strong><?= e($dstat['business_name']) ?></strong>
                is under review.
            <?php elseif ($dstat['status'] === 'approved'): ?>
                <strong><?= e($dstat['business_name']) ?></strong> is an active
                distributor account.
            <?php else: ?>
                Your distributor account (<?= e($dstat['business_name']) ?>)
                is not active. Contact us to reactivate.
            <?php endif; ?>
        </span>
    </div>
<?php endif; ?>

<?php if (!empty($_SESSION['message'])): ?>
    <p class="notice"><?= e($_SESSION['message']) ?></p>
    <?php unset($_SESSION['message']); ?>
<?php endif; ?>
