<?php
// ============================================================
// REQUIREMENT 2.5 — view and moderate comments.
//
// Comments are submitted publicly (requirement 2.9) and appear
// immediately; this queue lets an ADMIN act on them afterwards
// with all three moderation styles the brief allows:
//   * hide / show   (toggle public visibility, keeps the record)
//   * disemvowel    (strip the vowels; visible but defanged)
//   * delete        (remove permanently)
//
// require_admin(): only admins moderate — staff and members cannot.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

// ---- Handle an action ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action'] ?? '';
    $comment_id = clean_id($_POST['comment_id'] ?? null);

    if ($comment_id > 0) {
        switch ($action) {
            case 'hide':
                $pdo->prepare('UPDATE comments SET is_hidden = 1 WHERE comment_id = ?')
                    ->execute([$comment_id]);
                $_SESSION['message'] = 'Comment hidden from public view.';
                break;

            case 'show':
                $pdo->prepare('UPDATE comments SET is_hidden = 0 WHERE comment_id = ?')
                    ->execute([$comment_id]);
                $_SESSION['message'] = 'Comment is public again.';
                break;

            case 'disemvowel':
                $get = $pdo->prepare('SELECT body FROM comments WHERE comment_id = ?');
                $get->execute([$comment_id]);
                if ($row = $get->fetch()) {
                    $pdo->prepare('UPDATE comments SET body = ? WHERE comment_id = ?')
                        ->execute([disemvowel($row['body']), $comment_id]);
                    $_SESSION['message'] = 'Comment disemvowelled.';
                }
                break;

            case 'delete':
                $pdo->prepare('DELETE FROM comments WHERE comment_id = ?')
                    ->execute([$comment_id]);
                $_SESSION['message'] = 'Comment deleted.';
                break;
        }
    }

    $back = 'admin/comments.php';
    if (!empty($_POST['filter'])) {
        $back .= '?filter=' . urlencode($_POST['filter']);
    }
    header('Location: ' . url($back));
    exit;
}

// ---- Filter: all / visible / hidden ----
$filter = $_GET['filter'] ?? 'all';
$where  = '';
if ($filter === 'visible') {
    $where = 'WHERE c.is_hidden = 0';
} elseif ($filter === 'hidden') {
    $where = 'WHERE c.is_hidden = 1';
} else {
    $filter = 'all';
}

$comments = $pdo->query(
    "SELECT c.*, p.title AS page_title
       FROM comments c
       JOIN pages p ON p.page_id = c.page_id
       $where
      ORDER BY c.created_at DESC"
)->fetchAll();

$visible_n = (int)$pdo->query('SELECT COUNT(*) FROM comments WHERE is_hidden = 0')->fetchColumn();
$hidden_n  = (int)$pdo->query('SELECT COUNT(*) FROM comments WHERE is_hidden = 1')->fetchColumn();

$title = 'Moderate Comments';
require_once __DIR__ . '/../includes/header.php';
?>

<h1>Moderate Comments</h1>

<p class="catbar">
    <a class="<?= $filter === 'all' ? 'active' : '' ?>"
       href="<?= url('admin/comments.php') ?>">All (<?= $visible_n + $hidden_n ?>)</a>
    <a class="<?= $filter === 'visible' ? 'active' : '' ?>"
       href="<?= url('admin/comments.php?filter=visible') ?>">Visible (<?= $visible_n ?>)</a>
    <a class="<?= $filter === 'hidden' ? 'active' : '' ?>"
       href="<?= url('admin/comments.php?filter=hidden') ?>">Hidden (<?= $hidden_n ?>)</a>
</p>

<?php if (!$comments): ?>
    <p class="hint">Nothing to show in this view.</p>
<?php else: ?>
    <?php foreach ($comments as $c): ?>
        <div class="comment">
            <p class="meta">
                <strong><?= e($c['author_name']) ?></strong>
                on <a href="<?= url('page.php?id=' . $c['page_id']) ?>"><?= e($c['page_title']) ?></a>
                &middot; <?= date('j M Y, H:i', strtotime($c['created_at'])) ?>
                <?php if ($c['is_hidden']): ?>
                    <span class="badge-hidden">hidden</span>
                <?php endif; ?>
            </p>
            <p><?= nl2br(e($c['body'])) ?></p>

            <form method="post" class="mod-row">
                <input type="hidden" name="comment_id" value="<?= (int)$c['comment_id'] ?>">
                <input type="hidden" name="filter" value="<?= e($filter) ?>">

                <?php if ($c['is_hidden']): ?>
                    <button name="action" value="show">Show</button>
                <?php else: ?>
                    <button name="action" value="hide" class="ghost">Hide</button>
                <?php endif; ?>

                <button name="action" value="disemvowel" class="ghost"
                        onclick="return confirm('Strip the vowels from this comment? This cannot be undone.');">
                    Disemvowel
                </button>

                <button name="action" value="delete" class="danger"
                        onclick="return confirm('Delete this comment permanently?');">
                    Delete
                </button>
            </form>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
