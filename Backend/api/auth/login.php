<?php
// api/auth/login.php

require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['error' => 'Method not allowed.']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$email = isset($input['email']) ? trim($input['email']) : '';
$password = $input['password'] ?? '';

if ($email === '' || $password === '') {
    json_response(400, ['error' => 'Email and password are required.']);
}

$pdo = db();

$stmt = $pdo->prepare("
    SELECT id, school_id, name, email, password_hash, role, is_active
    FROM users
    WHERE email = :email
    LIMIT 1
");
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

if (!$user) {
    json_response(401, ['error' => 'Invalid credentials.']);
}

if (!password_verify($password, $user['password_hash'])) {
    json_response(401, ['error' => 'Invalid credentials.']);
}

if ((int)$user['is_active'] !== 1) {
    json_response(403, ['error' => 'Account is not active.']);
}

// Generate secure auth token
$token = bin2hex(random_bytes(32));
$expiresAt = (new DateTime('+1 day'))->format('Y-m-d H:i:s');

$stmt = $pdo->prepare("
    INSERT INTO auth_tokens (user_id, token, expires_at)
    VALUES (:user_id, :token, :expires_at)
");
$stmt->execute([
    ':user_id'   => $user['id'],
    ':token'     => $token,
    ':expires_at'=> $expiresAt,
]);

json_response(200, [
    'token' => $token,
    'user'  => [
        'id'        => (int)$user['id'],
        'school_id' => (int)$user['school_id'],
        'name'      => $user['name'],
        'email'     => $user['email'],
        'role'      => $user['role'],
    ],
]);