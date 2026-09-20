<?php
// ============================================================
// showcase-data.php — the product feed for the 3D showcase.
//
//   PHP/MySQL  ->  JSON  ->  JavaScript  ->  Three.js
//
// showcase.php embeds the same payload inline so the first paint needs
// no round trip. This endpoint exists for everything after that: a
// client-side refresh, a second showcase surface, or checking what a
// given role actually receives.
//
// The role check happens HERE, not in JavaScript. A guest's response
// contains no price field at all, so there is nothing to reveal by
// reading the network tab.
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/showcase.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');          // pricing is per-user; never cache
header('X-Content-Type-Options: nosniff');

if (!showcase_installed($pdo)) {
    http_response_code(503);
    echo json_encode([
        'error'   => 'not_installed',
        'message' => 'The showcase fields are not in the database yet. An admin can add them from Manage Showcase.',
    ]);
    exit;
}

echo json_encode(showcase_payload($pdo), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
