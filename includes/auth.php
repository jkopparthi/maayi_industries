<?php
// includes/auth.php — role & distributor-approval helpers.
// Assumes login code sets $_SESSION['user_id'] and $_SESSION['role'].
// Uses PDO ($pdo) — adapt the two queries if you use mysqli.

const ROLES = ['administrator', 'marketing', 'accountant', 'distributor', 'member'];

function current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function current_role(): string {
    // Anyone not logged in is a guest.
    return $_SESSION['role'] ?? 'guest';
}

function is_staff(): bool {
    return in_array(current_role(), ['administrator', 'marketing', 'accountant'], true);
}

function require_role(string ...$roles): void {
    if (!in_array(current_role(), $roles, true)) {
        http_response_code(403);
        exit('Access denied.');
    }
}

/**
 * Returns the distributor row for the logged-in user ONLY if their
 * application has been approved; otherwise null.
 *
 * This single gate is what hides distributor pricing from:
 *   - guests and members            (no distributor row at all)
 *   - pending/rejected distributors (row exists, status != approved)
 *   - staff                         (they see prices via admin, not here)
 */
function approved_distributor(PDO $pdo): ?array {
    $uid = current_user_id();
    if ($uid === null || current_role() !== 'distributor') {
        return null;
    }
    $stmt = $pdo->prepare(
        "SELECT distributor_id, business_name
           FROM distributors
          WHERE user_id = ? AND approval_status = 'approved'"
    );
    $stmt->execute([$uid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}
