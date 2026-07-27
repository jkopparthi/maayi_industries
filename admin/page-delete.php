<?php
// ============================================================
// REQUIREMENT 2.2 — delete a page.
// Only logged-in admins can reach this.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();   // req 7.1: admin-only, not just logged in

$id = clean_id($_GET['id'] ?? null);

if ($id > 0) {
    // Comments are removed automatically by the foreign key
    // (ON DELETE CASCADE on the comments table).
    $stmt = $pdo->prepare('DELETE FROM pages WHERE page_id = ?');
    $stmt->execute([$id]);
    $_SESSION['message'] = 'Page deleted.';
} else {
    $_SESSION['message'] = 'Invalid page id.';
}

header('Location: ' . url('admin/pages.php'));
exit;
