<?php
// includes/distributor_status.php
// One place that answers: "does this logged-in user have a distributor
// application, and what state is it in?" Returns null if they have NOT
// applied — the caller uses that to hide the banner entirely.

/**
 * @return array{status:string, label:string, css:string, business_name:string}|null
 *         null  = user has not applied (or not logged in) -> show nothing
 */
function distributor_status(PDO $pdo): ?array {
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT approval_status, business_name
           FROM distributors
          WHERE user_id = ?"
    );
    $stmt->execute([(int)$_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;   // never applied
    }

    // Map DB values -> friendly labels + a CSS state class.
    $map = [
        'pending'  => ['label' => 'Pending',     'css' => 'is-pending'],
        'approved' => ['label' => 'Active',       'css' => 'is-active'],
        'rejected' => ['label' => 'Deactivated',  'css' => 'is-deactivated'],
    ];
    $status = $row['approval_status'];
    $meta   = $map[$status] ?? ['label' => ucfirst($status), 'css' => 'is-pending'];

    return [
        'status'        => $status,
        'label'         => $meta['label'],
        'css'           => $meta['css'],
        'business_name' => $row['business_name'],
    ];
}
