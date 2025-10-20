<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// This file should be accessible without full authentication since it's called via AJAX
// But we should still validate the request

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit();
}

$class_id = $_POST['class_id'] ?? '';

if (empty($class_id)) {
    echo json_encode(['error' => 'Class ID is required']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get subjects for the selected class
$subjects_query = "SELECT s.id, s.name, s.code 
                  FROM subjects s 
                  WHERE s.class_id = :class_id 
                  AND s.status = 'active' 
                  ORDER BY s.name";
$stmt = $db->prepare($subjects_query);
$stmt->bindParam(':class_id', $class_id);
$stmt->execute();
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get teachers who teach subjects for this class
$teachers_query = "SELECT DISTINCT u.id, u.full_name 
                  FROM users u 
                  JOIN subjects s ON u.id = s.teacher_id 
                  WHERE s.class_id = :class_id 
                  AND u.role = 'teacher' 
                  AND u.status = 'active' 
                  ORDER BY u.full_name";
$stmt = $db->prepare($teachers_query);
$stmt->bindParam(':class_id', $class_id);
$stmt->execute();
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'subjects' => $subjects,
    'teachers' => $teachers
]);
?>