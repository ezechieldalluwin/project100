<?php
// api/attendance/add.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middlewares/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['error' => 'Method not allowed.']);
}

// Teachers and head_teacher can record attendance
$currentUser = require_auth(['teacher', 'head_teacher']);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$studentId = isset($input['student_id']) ? (int)$input['student_id'] : 0;
$status    = isset($input['status']) ? trim($input['status']) : '';
$date      = isset($input['date']) ? trim($input['date']) : date('Y-m-d');

$allowedStatuses = ['present','absent','late'];

if ($studentId <= 0 || $status === '') {
    json_response(400, ['error' => 'student_id and status are required.']);
}

if (!in_array($status, $allowedStatuses, true)) {
    json_response(400, ['error' => 'Invalid attendance status.']);
}

$pdo = db();

// Verify student belongs to the same school
$stmt = $pdo->prepare("
    SELECT id, school_id
    FROM students
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $studentId]);
$student = $stmt->fetch();

if (!$student || (int)$student['school_id'] !== (int)$currentUser['school_id']) {
    json_response(404, ['error' => 'Student not found in your school.']);
}

$stmt = $pdo->prepare("
    INSERT INTO attendance_records (school_id, student_id, recorded_by, record_date, status)
    VALUES (:school_id, :student_id, :recorded_by, :record_date, :status)
");
$stmt->execute([
    ':school_id'   => $currentUser['school_id'],
    ':student_id'  => $studentId,
    ':recorded_by' => $currentUser['id'],
    ':record_date' => $date,
    ':status'      => $status,
]);

json_response(201, ['message' => 'Attendance record added.']);