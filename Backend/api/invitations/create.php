<?php
// api/invitations/create.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middlewares/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['error' => 'Method not allowed.']);
}

// Only head_teacher can invite others
$currentUser = require_auth(['head_teacher']);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$email = isset($input['email']) ? trim($input['email']) : '';
$role  = isset($input['role']) ? trim($input['role']) : '';

$allowedInviteRoles = ['teacher','discipline_officer','parent','admin'];

if ($email === '' || $role === '') {
    json_response(400, ['error' => 'Email and role are required.']);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(400, ['error' => 'Invalid email address.']);
}

if (!in_array($role, $allowedInviteRoles, true)) {
    json_response(400, ['error' => 'Invalid role for invitation.']);
}

$pdo = db();

// Ensure no active user already exists with this email in this school
$stmt = $pdo->prepare("
    SELECT id FROM users
    WHERE email = :email AND school_id = :school_id
    LIMIT 1
");
$stmt->execute([
    ':email'     => $email,
    ':school_id' => $currentUser['school_id'],
]);
$existingUser = $stmt->fetch();

if ($existingUser) {
    json_response(409, ['error' => 'User with this email already exists in the school.']);
}

// Create invitation token
$token = bin2hex(random_bytes(32));
$expiresAt = (new DateTime('+7 days'))->format('Y-m-d H:i:s');

$stmt = $pdo->prepare("
    INSERT INTO invitations (school_id, inviter_user_id, email, role, token, expires_at)
    VALUES (:school_id, :inviter_user_id, :email, :role, :token, :expires_at)
");
$stmt->execute([
    ':school_id'      => $currentUser['school_id'],
    ':inviter_user_id'=> $currentUser['id'],
    ':email'          => $email,
    ':role'           => $role,
    ':token'          => $token,
    ':expires_at'     => $expiresAt,
]);

json_response(201, [
    'message' => 'Invitation created successfully.',
    // Simulated "email": return token in JSON so you can copy it
    'invitation_token' => $token,
]);