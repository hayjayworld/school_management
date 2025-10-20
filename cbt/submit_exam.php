<?php
require_once '../includes/config.php';

// Check if student is logged into CBT portal and has an active exam
if (!isset($_SESSION['cbt_logged_in']) || !$_SESSION['cbt_logged_in'] || !isset($_SESSION['exam_started'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$student_id = $_SESSION['cbt_student_id'];
$exam_id = $_SESSION['exam_started'];
$result_id = $_SESSION['result_id'];

// Calculate score
$score = 0;
$total_questions = 0;

if (isset($_SESSION['exam_answers'])) {
    // Get correct answers
    $answers_query = "SELECT id, correct_answer, marks FROM cbt_questions WHERE exam_id = :exam_id";
    $stmt = $db->prepare($answers_query);
    $stmt->bindParam(':exam_id', $exam_id);
    $stmt->execute();
    $correct_answers = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $question_marks = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Re-execute to get marks
    $stmt->execute();
    $questions_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($questions_data as $question) {
        $total_questions++;
        if (isset($_SESSION['exam_answers'][$question['id']])) {
            if ($_SESSION['exam_answers'][$question['id']] == $question['correct_answer']) {
                $score += $question['marks'];
            }
            
            // Record student answer
            $answer_query = "INSERT INTO cbt_student_answers (result_id, question_id, student_answer, is_correct, marks_obtained) 
                           VALUES (:result_id, :question_id, :student_answer, :is_correct, :marks_obtained)";
            $answer_stmt = $db->prepare($answer_query);
            $answer_stmt->bindParam(':result_id', $result_id);
            $answer_stmt->bindParam(':question_id', $question['id']);
            $answer_stmt->bindParam(':student_answer', $_SESSION['exam_answers'][$question['id']]);
            $is_correct = ($_SESSION['exam_answers'][$question['id']] == $question['correct_answer']) ? 1 : 0;
            $answer_stmt->bindParam(':is_correct', $is_correct);
            $marks_obtained = $is_correct ? $question['marks'] : 0;
            $answer_stmt->bindParam(':marks_obtained', $marks_obtained);
            $answer_stmt->execute();
        }
    }
}

// Calculate percentage and grade
$exam_query = "SELECT total_marks FROM cbt_exams WHERE id = :exam_id";
$stmt = $db->prepare($exam_query);
$stmt->bindParam(':exam_id', $exam_id);
$stmt->execute();
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

$percentage = $exam['total_marks'] > 0 ? round(($score / $exam['total_marks']) * 100, 2) : 0;

if ($percentage >= 80) $grade = 'A';
elseif ($percentage >= 70) $grade = 'B';
elseif ($percentage >= 60) $grade = 'C';
elseif ($percentage >= 50) $grade = 'D';
else $grade = 'F';

// Update result in database
$update_query = "UPDATE cbt_results SET score = :score, percentage = :percentage, grade = :grade, 
                submitted_at = NOW(), time_taken = :time_taken 
                WHERE id = :result_id";
$stmt = $db->prepare($update_query);
$stmt->bindParam(':score', $score);
$stmt->bindParam(':percentage', $percentage);
$stmt->bindParam(':grade', $grade);
$time_taken = time() - $_SESSION['exam_start_time'];
$stmt->bindParam(':time_taken', $time_taken);
$stmt->bindParam(':result_id', $result_id);
$stmt->execute();

// Get exam details for display
$exam_details_query = "SELECT ce.title, s.name as subject_name 
                      FROM cbt_exams ce 
                      JOIN subjects s ON ce.subject_id = s.id 
                      WHERE ce.id = :exam_id";
$stmt = $db->prepare($exam_details_query);
$stmt->bindParam(':exam_id', $exam_id);
$stmt->execute();
$exam_details = $stmt->fetch(PDO::FETCH_ASSOC);

// Clear exam session
unset($_SESSION['exam_started']);
unset($_SESSION['exam_start_time']);
unset($_SESSION['exam_answers']);
unset($_SESSION['current_question']);
unset($_SESSION['result_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Submitted - Excel Schools CBT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .result-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 100%;
        }
        .result-header {
            background: #2c3e50;
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 15px 15px 0 0;
        }
        .result-body {
            padding: 30px;
        }
        .score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            margin: 0 auto 20px;
        }
        .score-excellent { background: #d4edda; color: #155724; }
        .score-good { background: #d1ecf1; color: #0c5460; }
        .score-average { background: #fff3cd; color: #856404; }
        .score-poor { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="result-container">
                    <div class="result-header">
                        <div class="school-logo" style="font-size: 3rem; margin-bottom: 15px;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3>Exam Submitted Successfully!</h3>
                        <p class="mb-0"><?php echo $exam_details['title']; ?></p>
                    </div>
                    <div class="result-body">
                        <div class="text-center mb-4">
                            <div class="score-circle 
                                <?php echo $percentage >= 70 ? 'score-excellent' : ''; ?>
                                <?php echo $percentage >= 50 && $percentage < 70 ? 'score-good' : ''; ?>
                                <?php echo $percentage >= 40 && $percentage < 50 ? 'score-average' : ''; ?>
                                <?php echo $percentage < 40 ? 'score-poor' : ''; ?>">
                                <?php echo $percentage; ?>%
                            </div>
                            <h4>Your Score: <?php echo $score; ?>/<?php echo $exam['total_marks']; ?></h4>
                            <p class="text-muted">Grade: <strong><?php echo $grade; ?></strong></p>
                        </div>

                        <div class="row text-center mb-4">
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body">
                                        <h5><?php echo $total_questions; ?></h5>
                                        <small class="text-muted">Total Questions</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body">
                                        <h5><?php echo $score; ?></h5>
                                        <small class="text-muted">Marks Obtained</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body">
                                        <h5><?php echo $grade; ?></h5>
                                        <small class="text-muted">Final Grade</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alert 
                            <?php echo $percentage >= 50 ? 'alert-success' : 'alert-warning'; ?>">
                            <i class="fas fa-<?php echo $percentage >= 50 ? 'trophy' : 'exclamation-triangle'; ?> me-2"></i>
                            <?php if ($percentage >= 70): ?>
                                Excellent work! You have passed with distinction.
                            <?php elseif ($percentage >= 50): ?>
                                Good job! You have passed the exam.
                            <?php else: ?>
                                You need to improve. Better luck next time!
                            <?php endif; ?>
                        </div>

                        <div class="text-center mt-4">
                            <a href="index.php" class="btn btn-primary btn-lg me-3">
                                <i class="fas fa-home me-2"></i>Back to Dashboard
                            </a>
                            <a href="logout.php" class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>