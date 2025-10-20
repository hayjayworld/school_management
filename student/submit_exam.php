<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('student')) {
    redirect('../auth/login.php');
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    redirect('exams.php');
}

$database = new Database();
$db = $database->getConnection();

$exam_id = $_POST['exam_id'] ?? 0;
$csrf_token = sanitize($_POST['csrf_token']);
$answers = $_POST['answers'] ?? [];

// Validate CSRF token
if (!validateCSRFToken($csrf_token)) {
    die('Security token invalid.');
}

// Get student info
$student_id = $_SESSION['user_id'];
$student_query = "SELECT s.* FROM students s WHERE s.user_id = :user_id";
$stmt = $db->prepare($student_query);
$stmt->bindParam(':user_id', $student_id);
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Verify exam access
$exam_query = "SELECT e.* FROM cbt_exams e 
              WHERE e.id = :exam_id 
              AND e.class_id = :class_id 
              AND NOW() BETWEEN e.start_time AND e.end_time";
$stmt = $db->prepare($exam_query);
$stmt->bindParam(':exam_id', $exam_id);
$stmt->bindParam(':class_id', $student['class_id']);
$stmt->execute();
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    redirect('exams.php?error=exam_not_found');
}

// Check if already submitted
$existing_query = "SELECT id FROM cbt_results WHERE exam_id = :exam_id AND student_id = :student_id";
$stmt = $db->prepare($existing_query);
$stmt->bindParam(':exam_id', $exam_id);
$stmt->bindParam(':student_id', $student['id']);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    redirect('exams.php?error=already_submitted');
}

// Get questions and calculate score
$questions_query = "SELECT * FROM cbt_questions WHERE exam_id = :exam_id";
$stmt = $db->prepare($questions_query);
$stmt->bindParam(':exam_id', $exam_id);
$stmt->execute();
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$score = 0;
$total_marks = 0;

foreach ($questions as $question) {
    $total_marks += $question['marks'];
    $student_answer = $answers[$question['id']] ?? null;
    
    if ($student_answer && $student_answer == $question['correct_answer']) {
        $score += $question['marks'];
    }
}

$percentage = calculatePercentage($score, $total_marks);

// Save result
$result_query = "INSERT INTO cbt_results (exam_id, student_id, score, total_marks, percentage) 
                VALUES (:exam_id, :student_id, :score, :total_marks, :percentage)";
$stmt = $db->prepare($result_query);
$stmt->bindParam(':exam_id', $exam_id);
$stmt->bindParam(':student_id', $student['id']);
$stmt->bindParam(':score', $score);
$stmt->bindParam(':total_marks', $total_marks);
$stmt->bindParam(':percentage', $percentage);

if ($stmt->execute()) {
    redirect('results.php?success=exam_submitted&score=' . $score . '&total=' . $total_marks);
} else {
    redirect('exams.php?error=submission_failed');
}
?>