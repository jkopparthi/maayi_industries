<?php
// ============================================================
// REQUIREMENT 2.2 — delete a page.
// Only logged-in admins can reach this.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$id = clean_id($_GET['id'] ?? null);

if ($id > 0) {

    $stmt = $pdo->prepare('DELETE FROM pages WHERE page_id = ?');
    $stmt->execute([$id]);
    $_SESSION['message'] = 'Page deleted.';
} else {
    $_SESSION['message'] = 'Invalid page id.';
}

header('Location: ' . url('admin/pages.php'));
exit;
