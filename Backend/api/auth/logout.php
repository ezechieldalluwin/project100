<?php
// api/auth/logout.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middlewares/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['error' => 'Method not allowed.']);
}

// Validate token and user first (even if revoked, we handle below)
$token = get_bearer_token();
if (!$token) {
    json_response(401, ['error' => 'Authorization token missing.']);
}

$pdo = db();

$stmt = $pdo->prepare("
    UPDATE auth_tokens
    SET revoked_at = NOW()
    WHERE token = :token
      AND revoked_at IS NULL
");
$stmt->execute([':token' => $token]);

json_response(200, ['message' => 'Logged out successfully.']);