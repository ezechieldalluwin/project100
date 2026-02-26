<?php
// api/students/risk-list.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middlewares/auth.php';
require_once __DIR__ . '/../../services/StudentProfileService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(405, ['error' => 'Method not allowed.']);
}

// Head teacher, teacher, discipline officer, admin can view full risk list
$currentUser = require_auth(['head_teacher', 'teacher', 'discipline_officer', 'admin']);

$pdo = db();

$stmt = $pdo->prepare("
    SELECT id, full_name, admission_no
    FROM students
    WHERE school_id = :school_id
    ORDER BY full_name ASC
");
$stmt->execute([':school_id' => $currentUser['school_id']]);
$students = $stmt->fetchAll();

$service = new StudentProfileService();
$result = [];

foreach ($students as $student) {
    $analytics = $service->getRiskScore((int)$student['id']);

    $result[] = [
        'student_id'    => (int)$student['id'],
        'full_name'     => $student['full_name'],
        'admission_no'  => $student['admission_no'],
        'attendance'    => $analytics['attendance'],
        'discipline'    => $analytics['discipline'],
        'marks'         => $analytics['marks'],
        'risk_score'    => $analytics['risk_score'],
        'risk_percent'  => $analytics['risk_percent'],
    ];
}

json_response(200, ['students' => $result]);