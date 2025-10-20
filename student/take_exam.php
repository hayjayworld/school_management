<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('student')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$exam_id = $_GET['exam_id'] ?? 0;
$student_id = $_SESSION['user_id'];

// Get student info
$student_query = "SELECT s.* FROM students s WHERE s.user_id = :user_id";
$stmt = $db->prepare($student_query);
$stmt->bindParam(':user_id', $student_id);
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get exam details
$exam_query = "SELECT e.*, s.name as subject_name 
              FROM cbt_exams e 
              JOIN subjects s ON e.subject_id = s.id 
              WHERE e.id = :exam_id 
              AND e.class_id = :class_id 
              AND NOW() BETWEEN e.start_time AND e.end_time
              AND e.id NOT IN (SELECT exam_id FROM cbt_results WHERE student_id = :student_id)";
$stmt = $db->prepare($exam_query);
$stmt->bindParam(':exam_id', $exam_id);
$stmt->bindParam(':class_id', $student['class_id']);
$stmt->bindParam(':student_id', $student['id']);
$stmt->execute();
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    redirect('exams.php?error=exam_not_found');
}

// Get exam questions
$questions_query = "SELECT * FROM cbt_questions WHERE exam_id = :exam_id ORDER BY id";
$stmt = $db->prepare($questions_query);
$stmt->bindParam(':exam_id', $exam_id);
$stmt->execute();
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Exam - Excel Schools</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .exam-container { max-width: 800px; margin: 0 auto; }
        .question-card { margin-bottom: 20px; }
        .timer { font-size: 1.5rem; font-weight: bold; }
        .option-label { cursor: pointer; }
    </style>
</head>
<body>
    <div class="exam-container py-4">
        <!-- Exam Header -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-0"><?php echo $exam['subject_name']; ?> - <?php echo $exam['title']; ?></h4>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="timer" id="timer">00:00:00</div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Duration:</strong> <?php echo $exam['duration_minutes']; ?> minutes</p>
                        <p><strong>Total Questions:</strong> <?php echo $exam['total_questions']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Student:</strong> <?php echo $_SESSION['full_name']; ?></p>
                        <p><strong>Instructions:</strong> Answer all questions. Time will auto-submit.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Exam Form -->
        <form id="examForm" action="submit_exam.php" method="POST">
            <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <?php foreach ($questions as $index => $question): ?>
                <div class="card question-card">
                    <div class="card-header">
                        <h6 class="mb-0">Question <?php echo $index + 1; ?></h6>
                    </div>
                    <div class="card-body">
                        <p class="card-text"><?php echo $question['question']; ?></p>
                        
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" 
                                   name="answers[<?php echo $question['id']; ?>]" 
                                   value="a" id="q<?php echo $question['id']; ?>_a">
                            <label class="form-check-label option-label" for="q<?php echo $question['id']; ?>_a">
                                A. <?php echo $question['option_a']; ?>
                            </label>
                        </div>
                        
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" 
                                   name="answers[<?php echo $question['id']; ?>]" 
                                   value="b" id="q<?php echo $question['id']; ?>_b">
                            <label class="form-check-label option-label" for="q<?php echo $question['id']; ?>_b">
                                B. <?php echo $question['option_b']; ?>
                            </label>
                        </div>
                        
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" 
                                   name="answers[<?php echo $question['id']; ?>]" 
                                   value="c" id="q<?php echo $question['id']; ?>_c">
                            <label class="form-check-label option-label" for="q<?php echo $question['id']; ?>_c">
                                C. <?php echo $question['option_c']; ?>
                            </label>
                        </div>
                        
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" 
                                   name="answers[<?php echo $question['id']; ?>]" 
                                   value="d" id="q<?php echo $question['id']; ?>_d">
                            <label class="form-check-label option-label" for="q<?php echo $question['id']; ?>_d">
                                D. <?php echo $question['option_d']; ?>
                            </label>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="text-center mt-4">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-paper-plane me-2"></i>Submit Exam
                </button>
            </div>
        </form>
    </div>

    <script>
        // Timer functionality
        const duration = <?php echo $exam['duration_minutes'] * 60; ?>;
        let timeLeft = duration;
        
        function updateTimer() {
            const hours = Math.floor(timeLeft / 3600);
            const minutes = Math.floor((timeLeft % 3600) / 60);
            const seconds = timeLeft % 60;
            
            document.getElementById('timer').textContent = 
                `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 0) {
                document.getElementById('examForm').submit();
            } else {
                timeLeft--;
                setTimeout(updateTimer, 1000);
            }
        }
        
        // Start timer
        updateTimer();
        
        // Auto-save answers (optional enhancement)
        function autoSave() {
            const formData = new FormData(document.getElementById('examForm'));
            // Implement auto-save functionality here
        }
        
        // Auto-save every 30 seconds
        setInterval(autoSave, 30000);
    </script>
</body>
</html>