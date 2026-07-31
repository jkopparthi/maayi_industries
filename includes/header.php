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
                <!-- Admin-only: content + staff management -->
                <a href="<?= url('admin/pages.php') ?>">Manage Pages</a>
                <a href="<?= url('admin/categories.php') ?>">Categories</a>
                <a href="<?= url('admin/users.php') ?>">Staff</a>
                <a href="<?= url('admin/roles.php') ?>">Roles</a>
            <?php endif; ?>
            <?php if (isset($pdo) && is_staff($pdo)): ?>
                <!-- Staff (admin + accountant + custom staff roles) -->
                <a href="<?= url('admin/distributors.php') ?>">Distributors</a>
                <a href="<?= url('admin/orders.php') ?>">Orders</a>
                <a href="<?= url('admin/payments.php') ?>">Payments</a>
            <?php endif; ?>
            <?php
                // Distributor shopping links — only for an approved distributor.
                if (isset($pdo) && approved_distributor_id($pdo) !== null): ?>
                <a href="<?= url('my-orders.php') ?>">My Orders</a>
                <a href="<?= url('my-payments.php') ?>">My Payments</a>
                <a href="<?= url('cart.php') ?>">Cart<?php
                    $n = cart_count(); echo $n > 0 ? ' (' . $n . ')' : ''; ?></a>
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

<!-- Global search: adapts to the current page (products, distributors, orders, staff) -->
<?php $search = search_context(); ?>
<div class="searchbar">
    <form action="<?= e($search['action']) ?>" method="get" class="searchform">
        <input type="search" name="q" placeholder="<?= e($search['placeholder']) ?>"
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
