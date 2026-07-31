<?php
// Superseded by admin/distributors.php — redirect for any old links.
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_staff($pdo);
header('Location: ' . url('admin/distributors.php'));
exit;
