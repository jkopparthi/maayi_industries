<?php
// Superseded by admin/distributors.php -> distributor-view.php.
// A distributor is now chosen from the list, then priced on their page.
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_staff($pdo);
header('Location: ' . url('admin/distributors.php'));
exit;
