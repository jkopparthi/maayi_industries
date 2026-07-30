<?php
// ============================================================
// apply.php — a logged-in MEMBER applies to become a distributor.
//
// Creates one 'pending' row in `distributors` (one per user, enforced
// by the UNIQUE key on distributors.user_id). Members only: guests are
// sent to log in, and an existing application is shown instead of the
// form so nobody can apply twice.
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

require_login();   // must be signed in

// Admins don't apply to be distributors.
if (is_admin()) {
    $_SESSION['message'] = 'Admins do not need a distributor application.';
    header('Location: ' . url('index.php'));
    exit;
}

// Existing application for this user? (any status)
$stmt = $pdo->prepare('SELECT * FROM distributors WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$existing = $stmt->fetch();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing) {

    $business = trim($_POST['business_name']  ?? '');
    $contact  = trim($_POST['contact_person'] ?? '');
    $phone    = trim($_POST['phone']          ?? '');
    $address  = trim($_POST['address']        ?? '');

    if ($business === '') {
        $errors[] = 'Please enter your business name.';
    } elseif (strlen($business) > 120) {
        $errors[] = 'Business name is too long (max 120 characters).';
    }
    if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{6,30}$/', $phone)) {
        $errors[] = 'That does not look like a valid phone number.';
    }

    if (!$errors) {
        $insert = $pdo->prepare(
            'INSERT INTO distributors
                 (user_id, business_name, contact_person, phone, address, approval_status)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $_SESSION['user_id'], $business,
            $contact ?: null, $phone ?: null, $address ?: null,
            'pending',
        ]);

        $_SESSION['message'] = 'Your distributor application has been submitted for review.';
        header('Location: ' . url('apply.php'));
        exit;
    }
}

$title = 'Become a Distributor';
require_once __DIR__ . '/includes/header.php';
?>

<h1>Become a Maayi Distributor</h1>

<?php if ($existing): ?>

    <?php
        // Friendly wording for each stored status.
        $labels = [
            'pending'  => ['Pending review',
                           'Your application is being reviewed. We will be in touch soon.'],
            'approved' => ['Approved',
                           'Your distributor account is active. Log out and back in to see your trade pricing.'],
            'rejected' => ['Not approved',
                           'Your application was not approved. Please contact us if you think this is a mistake.'],
        ];
        [$label, $blurb] = $labels[$existing['approval_status']]
                           ?? [ucfirst($existing['approval_status']), ''];
    ?>
    <div class="box">
        <p><strong>Business:</strong> <?= e($existing['business_name']) ?></p>
        <p><strong>Status:</strong> <?= e($label) ?></p>
        <p class="hint"><?= e($blurb) ?></p>
    </div>

<?php else: ?>

    <?php if ($errors): ?>
        <div class="error">
            <ul>
                <?php foreach ($errors as $message): ?>
                    <li><?= e($message) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <p class="hint">
        Tell us about your business. Once approved, you will see your agreed
        trade pricing on every product and can place orders directly.
    </p>

    <form method="post" class="box">
        <label>Business name
            <input type="text" name="business_name" maxlength="120" required autofocus
                   value="<?= e($_POST['business_name'] ?? '') ?>">
        </label>

        <label>Contact person
            <input type="text" name="contact_person" maxlength="80"
                   value="<?= e($_POST['contact_person'] ?? '') ?>">
        </label>

        <label>Phone
            <input type="text" name="phone" maxlength="30"
                   value="<?= e($_POST['phone'] ?? '') ?>">
        </label>

        <label>Business address
            <textarea name="address" rows="3"><?= e($_POST['address'] ?? '') ?></textarea>
        </label>

        <button type="submit">Submit application</button>
        <a class="cancel" href="<?= url('index.php') ?>">Cancel</a>
    </form>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
