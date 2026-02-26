<?php
// api/invitations/accept.php

require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['error' => 'Method not allowed.']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$token    = isset($input['token']) ? trim($input['token']) : '';
$name     = isset($input['name']) ? trim($input['name']) : '';
$password = $input['password'] ?? '';

if ($token === '' || $name === '' || $password === '') {
    json_response(400, ['error' => 'Token, name and password are required.']);
}

$pdo = db();

$stmt = $pdo->prepare("
    SELECT *
    FROM invitations
    WHERE token = :token
      AND status = 'pending'
      AND expires_at > NOW()
    LIMIT 1
");
$stmt->execute([':token' => $token]);
$invitation = $stmt->fetch();

if (!$invitation) {
    json_response(400, ['error' => 'Invalid or expired invitation token.']);
}

try {
    $pdo->beginTransaction();

    // Create user
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO users (school_id, name, email, password_hash, role, is_active)
        VALUES (:school_id, :name, :email, :password_hash, :role, 1)
    ");
    $stmt->execute([
        ':school_id'    => $invitation['school_id'],
        ':name'         => $name,
        ':email'        => $invitation['email'],
        ':password_hash'=> $passwordHash,
        ':role'         => $invitation['role'],
    ]);

    // Mark invitation as accepted
    $stmt = $pdo->prepare("
        UPDATE invitations
        SET status = 'accepted', used_at = NOW()
        WHERE id = :id
    ");
    $stmt->execute([':id' => $invitation['id']]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    json_response(500, ['error' => 'Failed to accept invitation.']);
}

json_response(200, ['message' => 'Account activated successfully. You can now log in.']);