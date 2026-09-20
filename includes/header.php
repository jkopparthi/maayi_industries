<?php
require_once __DIR__ . '/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($title) ? e($title) : 'Maayi Industries' ?></title>

    <!-- Display + body faces (free Google Fonts) — replacing the system
         font stack that makes generic sites look generic. -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Archivo:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Requirement 5.3: Bootstrap 5 (navbar + grid). Custom style.css
         layered on top for branding, loaded AFTER Bootstrap so it wins. -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <?php
        $cssv = @filemtime(dirname(__DIR__) . '/style.css') ?: 1;   // cache-bust
    ?>
    <link rel="stylesheet" href="<?= url('style.css') ?>?v=<?= $cssv ?>">
</head>
<body>

<!-- Requirement 5.3: main navigation built on Bootstrap's navbar component,
     available on every page (this header is included everywhere). -->
<nav class="navbar navbar-expand-lg navbar-dark maayi-navbar">
    <div class="container">
        <a class="navbar-brand" href="<?= url('index.php') ?>">Maayi Industries</a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#mainnav" aria-controls="mainnav"
                aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainnav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?= url('showcase.php') ?>">Showcase</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= url('index.php') ?>">Products</a>
                </li>

                <?php if (logged_in()): ?>
                    <?php if (is_admin()): ?>
                        <li class="nav-item"><a class="nav-link" href="<?= url('admin/pages.php') ?>">Manage Pages</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= url('admin/showcase.php') ?>">Showcase</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= url('admin/categories.php') ?>">Categories</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= url('admin/users.php') ?>">Staff</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= url('admin/roles.php') ?>">Roles</a></li>
                    <?php endif; ?>

                    <?php if (isset($pdo) && is_staff($pdo)): ?>
                        <li class="nav-item"><a class="nav-link" href="<?= url('admin/distributors.php') ?>">Distributors</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= url('admin/orders.php') ?>">Orders</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= url('admin/payments.php') ?>">Payments</a></li>
                    <?php endif; ?>

                    <?php if (isset($pdo) && approved_distributor_id($pdo) !== null): ?>
                        <li class="nav-item"><a class="nav-link" href="<?= url('my-orders.php') ?>">My Orders</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= url('my-payments.php') ?>">My Payments</a></li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= url('cart.php') ?>">
                                Cart<?php $n = cart_count(); echo $n > 0 ? ' (' . $n . ')' : ''; ?>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (($_SESSION['role'] ?? '') === 'member'
                              && isset($pdo) && distributor_status($pdo) === null): ?>
                        <li class="nav-item"><a class="nav-link" href="<?= url('apply.php') ?>">Become a distributor</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>

            <!-- Search: Bootstrap inline form, on every page -->
            <?php $search = search_context(); ?>
            <form class="d-flex me-3" action="<?= e($search['action']) ?>" method="get" role="search">
                <input class="form-control form-control-sm me-2" type="search" name="q"
                       placeholder="<?= e($search['placeholder']) ?>"
                       value="<?= isset($_GET['q']) ? e($_GET['q']) : '' ?>">
                <button class="btn btn-sm btn-light" type="submit">Search</button>
            </form>

            <ul class="navbar-nav">
                <?php if (logged_in()): ?>
                    <li class="nav-item">
                        <span class="navbar-text me-2">
                            <?= e($_SESSION['username']) ?><?= is_admin() ? ' (admin)' : '' ?>
                        </span>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="<?= url('logout.php') ?>">Log out</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= url('login.php') ?>">Log in</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= url('register.php') ?>">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Requirement 5.3: page content sits inside Bootstrap's grid container -->
<main class="container wrap">
    <div class="row">
        <div class="col-12">

<?php
    $dstat = (isset($pdo)) ? distributor_status($pdo) : null;
    if ($dstat !== null):
?>
    <div class="status-banner <?= $dstat['css'] ?>">
        <span class="dot"></span>
        <span class="label">Distributor: <?= e($dstat['label']) ?></span>
        <span class="msg">
            <?php if ($dstat['status'] === 'pending'): ?>
                Your application for <strong><?= e($dstat['business_name']) ?></strong> is under review.
            <?php elseif ($dstat['status'] === 'approved'): ?>
                <strong><?= e($dstat['business_name']) ?></strong> is an active distributor account.
            <?php else: ?>
                Your distributor account (<?= e($dstat['business_name']) ?>) is not active. Contact us to reactivate.
            <?php endif; ?>
        </span>
    </div>
<?php endif; ?>

<?php if (!empty($_SESSION['message'])): ?>
    <div class="alert alert-info notice"><?= e($_SESSION['message']) ?></div>
    <?php unset($_SESSION['message']); ?>
<?php endif; ?>
