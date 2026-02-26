<?php
// api/messages/conversation.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middlewares/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(405, ['error' => 'Method not allowed.']);
}

$currentUser = require_auth(); // any authenticated role

$otherUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($otherUserId <= 0) {
    json_response(400, ['error' => 'user_id is required.']);
}

$pdo = db();

// Ensure conversation partner is in same school
$stmt = $pdo->prepare("
    SELECT id, school_id, name
    FROM users
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $otherUserId]);
$otherUser = $stmt->fetch();

if (!$otherUser || (int)$otherUser['school_id'] !== (int)$currentUser['school_id']) {
    json_response(404, ['error' => 'User not found in your school.']);
}

$stmt = $pdo->prepare("
    SELECT id, sender_id, receiver_id, body, created_at
    FROM messages
    WHERE school_id = :school_id
      AND (
            (sender_id = :me AND receiver_id = :other)
         OR (sender_id = :other AND receiver_id = :me)
      )
    ORDER BY created_at ASC
");
$stmt->execute([
    ':school_id' => $currentUser['school_id'],
    ':me'        => $currentUser['id'],
    ':other'     => $otherUserId,
]);

$messages = $stmt->fetchAll();

json_response(200, [
    'with_user' => [
        'id'   => (int)$otherUser['id'],
        'name' => $otherUser['name'],
    ],
    'messages' => $messages,
]);