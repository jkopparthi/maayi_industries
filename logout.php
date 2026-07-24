<?php
require_once __DIR__ . '/includes/functions.php';

$_SESSION = [];
session_destroy();

session_start();
$_SESSION['message'] = 'You have been logged out.';

header('Location: ' . url('index.php'));
exit;
