<?php
require_once '../includes/config.php';

// Check if student is logged into CBT portal
if (!isset($_SESSION['cbt_logged_in']) || !$_SESSION['cbt_logged_in']) {
    header("Location: login.php");
    exit();
}

$exam_id = $_GET['exam_id'] ?? 0;
$student_id = $_SESSION['cbt_student_id'];

if (!$exam_id) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Check if exam exists and is available
$exam_query = "SELECT ce.*, s.name as subject_name 
               FROM cbt_exams ce 
               JOIN subjects s ON ce.subject_id = s.id 
               WHERE ce.id = :exam_id 
               AND ce.class_id = :class_id 
               AND ce.status = 'active'
               AND NOW() BETWEEN ce.start_time AND ce.end_time";
$stmt = $db->prepare($exam_query);
$stmt->bindParam(':exam_id', $exam_id);
$stmt->bindParam(':class_id', $_SESSION['cbt_class_id']);
$stmt->execute();
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    header("Location: index.php");
    exit();
}

// Check if student has already attempted this exam
$attempt_query = "SELECT id FROM cbt_results WHERE exam_id = :exam_id AND student_id = :student_id";
$stmt = $db->prepare($attempt_query);
$stmt->bindParam(':exam_id', $exam_id);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();

if ($stmt->fetch()) {
    header("Location: index.php");
    exit();
}

// Get exam questions
$questions_query = "SELECT * FROM cbt_questions 
                   WHERE exam_id = :exam_id 
                   AND status = 'active' 
                   ORDER BY id";
$stmt = $db->prepare($questions_query);
$stmt->bindParam(':exam_id', $exam_id);
$stmt->execute();
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Start exam session if not already started
if (!isset($_SESSION['exam_started']) || $_SESSION['exam_started'] != $exam_id) {
    $_SESSION['exam_started'] = $exam_id;
    $_SESSION['exam_start_time'] = time();
    $_SESSION['exam_answers'] = array();
    $_SESSION['current_question'] = 0;
    
    // Record exam start in database
    $start_query = "INSERT INTO cbt_results (exam_id, student_id, score, total_marks, percentage, grade, started_at) 
                   VALUES (:exam_id, :student_id, 0, :total_marks, 0, 'F', NOW())";
    $stmt = $db->prepare($start_query);
    $stmt->bindParam(':exam_id', $exam_id);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->bindParam(':total_marks', $exam['total_marks']);
    $stmt->execute();
    $_SESSION['result_id'] = $db->lastInsertId();
}

// Handle answer submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['answer'])) {
    $question_id = sanitize($_POST['question_id']);
    $answer = sanitize($_POST['answer']);
    
    $_SESSION['exam_answers'][$question_id] = $answer;
    
    // Move to next question or finish exam
    if (isset($_POST['action']) && $_POST['action'] == 'finish') {
        header("Location: submit_exam.php");
        exit();
    } else {
        $_SESSION['current_question'] = min($_SESSION['current_question'] + 1, count($questions) - 1);
    }
}

$current_question_index = $_SESSION['current_question'];
$current_question = $questions[$current_question_index] ?? null;

if (!$current_question) {
    header("Location: submit_exam.php");
    exit();
}

$time_remaining = ($exam['duration_minutes'] * 60) - (time() - $_SESSION['exam_start_time']);
if ($time_remaining <= 0) {
    header("Location: submit_exam.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam: <?php echo $exam['title']; ?> - Excel Schools CBT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
        }
        .exam-header {
            background: white;
            border-bottom: 2px solid #e9ecef;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .question-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .option-label {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .option-label:hover {
            background-color: #f8f9fa;
        }
        .option-input:checked + .option-label {
            background-color: #e3f2fd;
            border-color: #2196f3;
        }
        .timer-critical {
            color: #dc3545;
            animation: blink 1s infinite;
        }
        @keyframes blink {
            50% { opacity: 0.5; }
        }
        .question-nav-btn {
            width: 40px;
            height: 40px;
        }
        .answered {
            background-color: #28a745 !important;
            color: white !important;
        }
    </style>
</head>
<body>
    <!-- Exam Header -->
    <div class="exam-header py-3">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <h5 class="mb-0"><?php echo $exam['title']; ?></h5>
                    <small class="text-muted"><?php echo $exam['subject_name']; ?></small>
                </div>
                <div class="col-md-4 text-center">
                    <div class="countdown-timer">
                        <i class="fas fa-clock me-2"></i>
                        Time Left: <span id="timer" class="fw-bold"></span>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <span class="badge bg-primary">
                        Question <?php echo $current_question_index + 1; ?> of <?php echo count($questions); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <div class="row">
            <!-- Questions Navigation -->
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Questions</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2">
                            <?php for ($i = 0; $i < count($questions); $i++): ?>
                                <button class="btn btn-sm question-nav-btn 
                                    <?php echo $i == $current_question_index ? 'btn-primary' : 'btn-outline-primary'; ?>
                                    <?php echo isset($_SESSION['exam_answers'][$questions[$i]['id']]) ? 'answered' : ''; ?>"
                                    onclick="goToQuestion(<?php echo $i; ?>)">
                                    <?php echo $i + 1; ?>
                                </button>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-body text-center">
                        <form method="POST" id="finishForm">
                            <input type="hidden" name="action" value="finish">
                            <button type="submit" class="btn btn-danger w-100" 
                                    onclick="return confirm('Are you sure you want to finish the exam? This action cannot be undone.')">
                                <i class="fas fa-flag-checkered me-2"></i>Finish Exam
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Question Area -->
            <div class="col-md-9">
                <div class="card question-card">
                    <div class="card-body">
                        <form method="POST" id="answerForm">
                            <input type="hidden" name="question_id" value="<?php echo $current_question['id']; ?>">
                            
                            <h5 class="card-title">Question <?php echo $current_question_index + 1; ?></h5>
                            <p class="card-text fs-6"><?php echo nl2br(htmlspecialchars($current_question['question'])); ?></p>
                            
                            <div class="options-container mt-4">
                                <?php if ($current_question['question_type'] == 'multiple_choice'): ?>
                                    <?php foreach (['A', 'B', 'C', 'D'] as $option): ?>
                                        <?php if (!empty($current_question['option_' . strtolower($option)])): ?>
                                            <div class="mb-3">
                                                <input type="radio" name="answer" value="<?php echo $option; ?>" 
                                                       id="option_<?php echo $option; ?>" 
                                                       class="option-input d-none"
                                                       <?php echo (isset($_SESSION['exam_answers'][$current_question['id']]) && $_SESSION['exam_answers'][$current_question['id']] == $option) ? 'checked' : ''; ?>>
                                                <label for="option_<?php echo $option; ?>" 
                                                       class="option-label d-block p-3 border rounded">
                                                    <strong><?php echo $option; ?>.</strong> 
                                                    <?php echo htmlspecialchars($current_question['option_' . strtolower($option)]); ?>
                                                </label>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php elseif ($current_question['question_type'] == 'true_false'): ?>
                                    <div class="mb-3">
                                        <input type="radio" name="answer" value="true" 
                                               id="option_true" class="option-input d-none"
                                               <?php echo (isset($_SESSION['exam_answers'][$current_question['id']]) && $_SESSION['exam_answers'][$current_question['id']] == 'true') ? 'checked' : ''; ?>>
                                        <label for="option_true" class="option-label d-block p-3 border rounded">
                                            <strong>True</strong>
                                        </label>
                                    </div>
                                    <div class="mb-3">
                                        <input type="radio" name="answer" value="false" 
                                               id="option_false" class="option-input d-none"
                                               <?php echo (isset($_SESSION['exam_answers'][$current_question['id']]) && $_SESSION['exam_answers'][$current_question['id']] == 'false') ? 'checked' : ''; ?>>
                                        <label for="option_false" class="option-label d-block p-3 border rounded">
                                            <strong>False</strong>
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-outline-primary" 
                                        onclick="goToQuestion(<?php echo max(0, $current_question_index - 1); ?>)"
                                        <?php echo $current_question_index == 0 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-arrow-left me-2"></i>Previous
                                </button>
                                
                                <button type="submit" class="btn btn-success">
                                    <?php echo ($current_question_index == count($questions) - 1) ? 'Save & Finish' : 'Save & Next'; ?>
                                    <i class="fas fa-arrow-right ms-2"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Timer functionality
        let timeRemaining = <?php echo $time_remaining; ?>;
        
        function updateTimer() {
            const minutes = Math.floor(timeRemaining / 60);
            const seconds = timeRemaining % 60;
            const timerElem = document.getElementById('timer');
            
            timerElem.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeRemaining <= 300) { // 5 minutes
                timerElem.classList.add('timer-critical');
            }
            
            if (timeRemaining <= 0) {
                window.location.href = 'submit_exam.php';
            }
            
            timeRemaining--;
        }
        
        setInterval(updateTimer, 1000);
        updateTimer();
        
        // Auto-save answer when option is selected
        document.querySelectorAll('.option-input').forEach(input => {
            input.addEventListener('change', function() {
                setTimeout(() => {
                    document.getElementById('answerForm').dispatchEvent(new Event('submit', {bubbles: true}));
                }, 500);
            });
        });
        
        function goToQuestion(index) {
            // Submit current answer before navigating
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const questionId = document.createElement('input');
            questionId.name = 'question_id';
            questionId.value = '<?php echo $current_question['id']; ?>';
            form.appendChild(questionId);
            
            const currentAnswer = document.querySelector('input[name="answer"]:checked');
            if (currentAnswer) {
                const answer = document.createElement('input');
                answer.name = 'answer';
                answer.value = currentAnswer.value;
                form.appendChild(answer);
            }
            
            document.body.appendChild(form);
            form.submit();
            
            // Update session and redirect
            <?php $_SESSION['current_question'] = 'index'; ?> // This will be updated by the form submission
            window.location.href = 'exam.php?exam_id=<?php echo $exam_id; ?>&question=' + index;
        }
        
        // Prevent accidental navigation
        window.addEventListener('beforeunload', function(e) {
            e.preventDefault();
            e.returnValue = '';
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>