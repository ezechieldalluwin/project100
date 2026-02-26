<?php
// api/students/profile.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middlewares/auth.php';
require_once __DIR__ . '/../../services/StudentProfileService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(405, ['error' => 'Method not allowed.']);
}

// Head teacher, teacher, discipline officer, admin, parent can view
$currentUser = require_auth(['head_teacher', 'teacher', 'discipline_officer', 'admin', 'parent']);

$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
if ($studentId <= 0) {
    json_response(400, ['error' => 'student_id is required.']);
}

$pdo = db();

// Ensure student is in same school
$stmt = $pdo->prepare("
    SELECT id, school_id, full_name, admission_no, current_term
    FROM students
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $studentId]);
$student = $stmt->fetch();

if (!$student || (int)$student['school_id'] !== (int)$currentUser['school_id']) {
    json_response(404, ['error' => 'Student not found in your school.']);
}

$service = new StudentProfileService();
$profile = $service->getRiskScore($studentId);

json_response(200, [
    'student' => [
        'id'           => (int)$student['id'],
        'school_id'    => (int)$student['school_id'],
        'full_name'    => $student['full_name'],
        'admission_no' => $student['admission_no'],
        'current_term' => $student['current_term'],
    ],
    'analytics' => $profile,
]);