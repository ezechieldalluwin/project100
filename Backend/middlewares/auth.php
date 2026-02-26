<?php
// middlewares/auth.php

require_once __DIR__ . '/../config/database.php';

function get_authorization_header(): ?string
{
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        return trim($_SERVER['HTTP_AUTHORIZATION']);
    }

    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (isset($headers['Authorization'])) {
            return trim($headers['Authorization']);
        }
    }

    return null;
}

function get_bearer_token(): ?string
{
    $header = get_authorization_header();
    if (!$header) {
        return null;
    }

    if (stripos($header, 'Bearer ') === 0) {
        return substr($header, 7);
    }

    return null;
}

/**
 * Validate token, load user (with school) and optionally enforce allowed roles.
 * Attaches user to $GLOBALS['currentUser'].
 */
function require_auth(array $allowedRoles = []): array
{
    $token = get_bearer_token();

    if (!$token) {
        json_response(401, ['error' => 'Authorization token missing.']);
    }

    $pdo = db();

    $sql = "
        SELECT
            u.id,
            u.school_id,
            u.name,
            u.email,
            u.role,
            u.is_active,
            s.name AS school_name,
            t.id AS token_id,
            t.expires_at,
            t.revoked_at
        FROM auth_tokens t
        INNER JOIN users u ON t.user_id = u.id
        INNER JOIN schools s ON u.school_id = s.id
        WHERE t.token = :token
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch();

    if (!$user) {
        json_response(401, ['error' => 'Invalid authentication token.']);
    }

    if (!empty($user['revoked_at']) || strtotime($user['expires_at']) < time()) {
        json_response(401, ['error' => 'Authentication token expired or revoked.']);
    }

    if ((int)$user['is_active'] !== 1) {
        json_response(403, ['error' => 'User account is not active.']);
    }

    if (!empty($allowedRoles) && !in_array($user['role'], $allowedRoles, true)) {
        json_response(403, ['error' => 'You do not have permission to perform this action.']);
    }

    $GLOBALS['currentUser'] = $user;
    return $user;
}

function get_current_user(): ?array
{
    return $GLOBALS['currentUser'] ?? null;
}