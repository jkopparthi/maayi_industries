<?php
// ============================================================
// admin/requests.php — review distributor applications.
// REQUIREMENT 7.1 — admin only (uses require_admin()).
//
//   Approve -> status 'approved', records who/when, and upgrades the
//              applicant's users.role from 'member' to 'distributor'.
//   Reject  -> status 'rejected', records who/when. Role is unchanged.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

// ---- Handle an approve / reject action ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = clean_id($_POST['distributor_id'] ?? null);
    $action = $_POST['action'] ?? '';

    if ($id > 0 && in_array($action, ['approve', 'reject'], true)) {
        $pdo->beginTransaction();
        try {
            // Only pending applications can be decided.
            $stmt = $pdo->prepare(
                "SELECT user_id FROM distributors
                  WHERE distributor_id = ? AND approval_status = 'pending'"
            );
            $stmt->execute([$id]);
            $applicant = $stmt->fetch();

            if ($applicant) {
                $status = ($action === 'approve') ? 'approved' : 'rejected';

                $upd = $pdo->prepare(
                    'UPDATE distributors
                        SET approval_status = ?, approved_by = ?, approved_at = NOW()
                      WHERE distributor_id = ?'
                );
                $upd->execute([$status, $_SESSION['user_id'], $id]);

                if ($action === 'approve') {
                    $role = $pdo->prepare(
                        "UPDATE users SET role = 'distributor' WHERE user_id = ?"
                    );
                    $role->execute([$applicant['user_id']]);
                }

                $_SESSION['message'] = 'Application ' . $status . '.';
            } else {
                $_SESSION['message'] = 'That application has already been decided.';
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
    }
    header('Location: ' . url('admin/requests.php'));
    exit;
}

// ---- Fetch applications ----
$pending = $pdo->query(
    "SELECT d.*, u.username, u.email
       FROM distributors d
       JOIN users u ON u.user_id = d.user_id
      WHERE d.approval_status = 'pending'
      ORDER BY d.distributor_id"
)->fetchAll();

$decided = $pdo->query(
    "SELECT d.*, u.username, staff.username AS decided_by
       FROM distributors d
       JOIN users u          ON u.user_id     = d.user_id
  LEFT JOIN users staff      ON staff.user_id = d.approved_by
      WHERE d.approval_status <> 'pending'
      ORDER BY d.approved_at DESC
      LIMIT 20"
)->fetchAll();

$title = 'Distributor Applications';
require_once __DIR__ . '/../includes/header.php';
?>

<h1>Distributor Applications</h1>

<h2>Pending (<?= count($pending) ?>)</h2>
<?php if (!$pending): ?>
    <p class="hint">No applications are waiting for review.</p>
<?php else: ?>
<table>
    <tr>
        <th>Applicant</th><th>Business</th><th>Contact</th>
        <th>Phone</th><th>Address</th><th>Decision</th>
    </tr>
    <?php foreach ($pending as $a): ?>
    <tr>
        <td><?= e($a['username']) ?><br><span class="meta"><?= e($a['email']) ?></span></td>
        <td><?= e($a['business_name']) ?></td>
        <td><?= e($a['contact_person']) ?></td>
        <td><?= e($a['phone']) ?></td>
        <td><?= e($a['address']) ?></td>
        <td>
            <form method="post" style="display:inline"
                  onsubmit="return confirm('Approve this applicant?');">
                <input type="hidden" name="distributor_id" value="<?= (int)$a['distributor_id'] ?>">
                <button type="submit" name="action" value="approve">Approve</button>
            </form>
            <form method="post" style="display:inline"
                  onsubmit="return confirm('Reject this applicant?');">
                <input type="hidden" name="distributor_id" value="<?= (int)$a['distributor_id'] ?>">
                <button type="submit" name="action" value="reject"
                        style="background:#b23b3b">Reject</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<h2>Recent decisions</h2>
<?php if (!$decided): ?>
    <p class="hint">No decisions yet.</p>
<?php else: ?>
<table>
    <tr><th>Applicant</th><th>Business</th><th>Status</th><th>Decided by</th><th>When</th></tr>
    <?php foreach ($decided as $a): ?>
    <tr>
        <td><?= e($a['username']) ?></td>
        <td><?= e($a['business_name']) ?></td>
        <td><?= e(ucfirst($a['approval_status'])) ?></td>
        <td><?= e($a['decided_by'] ?? '—') ?></td>
        <td class="meta"><?= e($a['approved_at']) ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
