<?php
// services/StudentProfileService.php

require_once __DIR__ . '/../config/database.php';

class StudentProfileService
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = db();
    }

    public function getAttendanceStats(int $studentId): array
    {
        $sql = "
            SELECT
                COUNT(*) AS total_days,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_days
            FROM attendance_records
            WHERE student_id = :student_id
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':student_id' => $studentId]);
        $row = $stmt->fetch() ?: ['total_days' => 0, 'present_days' => 0];

        $totalDays = (int)$row['total_days'];
        $presentDays = (int)$row['present_days'];

        $attendanceRate = $totalDays > 0 ? $presentDays / $totalDays : 0.0;

        return [
            'total_days'      => $totalDays,
            'present_days'    => $presentDays,
            'attendance_rate' => $attendanceRate,
        ];
    }

    public function getDisciplineStats(int $studentId): array
    {
        $sql = "
            SELECT
                COALESCE(SUM(severity), 0) AS points_last_30_days
            FROM discipline_records
            WHERE student_id = :student_id
              AND incident_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':student_id' => $studentId]);
        $row = $stmt->fetch() ?: ['points_last_30_days' => 0];

        $points = (int)$row['points_last_30_days'];

        return [
            'points_last_30_days' => $points,
        ];
    }

    public function getMarksStats(int $studentId): array
    {
        // Determine current term from student record
        $stmt = $this->pdo->prepare("
            SELECT current_term
            FROM students
            WHERE id = :student_id
            LIMIT 1
        ");
        $stmt->execute([':student_id' => $studentId]);
        $student = $stmt->fetch();

        $currentTerm = $student ? $student['current_term'] : 'T1';

        $sql = "
            SELECT
                COALESCE(AVG(score), 0) AS average_mark
            FROM marks
            WHERE student_id = :student_id
              AND term = :term
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':student_id' => $studentId,
            ':term'       => $currentTerm,
        ]);
        $row = $stmt->fetch() ?: ['average_mark' => 0];

        $averageMark = (float)$row['average_mark'];

        return [
            'term'          => $currentTerm,
            'average_mark'  => $averageMark,
        ];
    }

    public function getRiskScore(int $studentId): array
    {
        $attendance = $this->getAttendanceStats($studentId);
        $discipline = $this->getDisciplineStats($studentId);
        $marks      = $this->getMarksStats($studentId);

        $attendanceRate  = $attendance['attendance_rate'];
        $disciplinePoints = $discipline['points_last_30_days'];
        $averageMark     = $marks['average_mark'];

        // Risk score calculation (do NOT store this in DB)
        // risk_score =
        // (1 - attendance_rate) * 0.5
        // + (discipline_points / 10) * 0.3
        // + (1 - average_mark / 100) * 0.2
        $riskScore =
            (1 - $attendanceRate) * 0.5 +
            ($disciplinePoints / 10.0) * 0.3 +
            (1 - ($averageMark / 100.0)) * 0.2;

        // Clamp between 0 and 1
        if ($riskScore < 0) {
            $riskScore = 0;
        }
        if ($riskScore > 1) {
            $riskScore = 1;
        }

        return [
            'attendance'  => $attendance,
            'discipline'  => $discipline,
            'marks'       => $marks,
            'risk_score'  => $riskScore,
            'risk_percent'=> round($riskScore * 100, 2),
        ];
    }
}