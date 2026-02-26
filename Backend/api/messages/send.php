<?php
// api/messages/send.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middlewares/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['error' => 'Method not allowed.']);
}

$currentUser = require_auth(); // any authenticated role

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$receiverId = isset($input['receiver_id']) ? (int)$input['receiver_id'] : 0;
$body       = isset($input['body']) ? trim($input['body']) : '';

if ($receiverId <= 0 || $body === '') {
    json_response(400, ['error' => 'receiver_id and body are required.']);
}

$pdo = db();

// Verify receiver is in same school
$stmt = $pdo->prepare("
    SELECT id, school_id
    FROM users
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $receiverId]);
$receiver = $stmt->fetch();

if (!$receiver || (int)$receiver['school_id'] !== (int)$currentUser['school_id']) {
    json_response(404, ['error' => 'Receiver not found in your school.']);
}

$stmt = $pdo->prepare("
    INSERT INTO messages (school_id, sender_id, receiver_id, body)
    VALUES (:school_id, :sender_id, :receiver_id, :body)
");
$stmt->execute([
    ':school_id'  => $currentUser['school_id'],
    ':sender_id'  => $currentUser['id'],
    ':receiver_id'=> $receiverId,
    ':body'       => $body,
]);

json_response(201, ['message' => 'Message sent.']);