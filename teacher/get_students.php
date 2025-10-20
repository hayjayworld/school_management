<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('teacher')) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$subject_id = $_GET['subject_id'] ?? 0;

// Get class ID from subject
$subject_query = "SELECT class_id FROM subjects WHERE id = :subject_id";
$stmt = $db->prepare($subject_query);
$stmt->bindParam(':subject_id', $subject_id);
$stmt->execute();
$subject = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$subject) {
    echo json_encode([]);
    exit;
}

// Get students in the class with existing results
$students_query = "SELECT s.id, s.student_id, u.full_name, 
                  COALESCE(r.ca_score, 0) as ca_score, 
                  COALESCE(r.exam_score, 0) as exam_score 
                  FROM students s 
                  JOIN users u ON s.user_id = u.id 
                  LEFT JOIN academic_results r ON s.id = r.student_id 
                  AND r.subject_id = :subject_id 
                  AND r.session_id = (SELECT id FROM academic_sessions WHERE status = 'active' LIMIT 1)
                  AND r.term = (SELECT current_term FROM academic_sessions WHERE status = 'active' LIMIT 1)
                  WHERE s.class_id = :class_id 
                  ORDER BY u.full_name";
$stmt = $db->prepare($students_query);
$stmt->bindParam(':subject_id', $subject_id);
$stmt->bindParam(':class_id', $subject['class_id']);
$stmt->execute();

$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($students);
?>