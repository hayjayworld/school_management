<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$exam_id = $_GET['exam_id'] ?? 0;
$message = '';

// Get exam details
$exam_query = "SELECT e.*, s.name as subject_name, c.name as class_name 
              FROM cbt_exams e 
              JOIN subjects s ON e.subject_id = s.id 
              JOIN classes c ON e.class_id = c.id 
              WHERE e.id = :exam_id";
$stmt = $db->prepare($exam_query);
$stmt->bindParam(':exam_id', $exam_id);
$stmt->execute();
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    redirect('exams.php?error=exam_not_found');
}

// Handle question submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = sanitize($_POST['csrf_token']);
    
    if (!validateCSRFToken($csrf_token)) {
        $message = "Security token invalid.";
    } else {
        $question = sanitize($_POST['question']);
        $option_a = sanitize($_POST['option_a']);
        $option_b = sanitize($_POST['option_b']);
        $option_c = sanitize($_POST['option_c']);
        $option_d = sanitize($_POST['option_d']);
        $correct_answer = sanitize($_POST['correct_answer']);
        $marks = sanitize($_POST['marks']) ?: 1;
        
        $query = "INSERT INTO cbt_questions (exam_id, question, option_a, option_b, option_c, option_d, correct_answer, marks) 
                 VALUES (:exam_id, :question, :option_a, :option_b, :option_c, :option_d, :correct_answer, :marks)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':exam_id', $exam_id);
        $stmt->bindParam(':question', $question);
        $stmt->bindParam(':option_a', $option_a);
        $stmt->bindParam(':option_b', $option_b);
        $stmt->bindParam(':option_c', $option_c);
        $stmt->bindParam(':option_d', $option_d);
        $stmt->bindParam(':correct_answer', $correct_answer);
        $stmt->bindParam(':marks', $marks);
        
        if ($stmt->execute()) {
            $message = "Question added successfully!";
            // Clear form
            $_POST = [];
        } else {
            $message = "Error adding question.";
        }
    }
}

// Get existing questions
$questions_query = "SELECT * FROM cbt_questions WHERE exam_id = :exam_id ORDER BY id";
$stmt = $db->prepare($questions_query);
$stmt->bindParam(':exam_id', $exam_id);
$stmt->execute();
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$question_count = count($questions);

$settings_qry = "SELECT setting_key, setting_value FROM system_settings WHERE is_public = 1";
$settings_rst = $db->query($settings_qry);
$school_settings = [];
while ($row = $settings_rst->fetch(PDO::FETCH_ASSOC)) {
    $school_settings[$row['setting_key']] = $row['setting_value'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Questions - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .main-content { margin-left: 250px; padding: 20px; }
        .question-card { border-left: 4px solid #3498db; }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="mobile-header-content">
            <div>
                <h5 class="mb-0">
                    <i class="fas fa-graduation-cap me-2"></i>
                    <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?>
                </h5>
                <small class="text-muted">Admin Panel</small>
            </div>
            <button class="menu-toggle" id="mobileMenuToggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>

    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay"></div>
    <div class="sidebar">
        <div class="p-4 text-center">
            <h4><i class="fas fa-graduation-cap"></i> <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></h4>
            <p class="text-muted small">Admin Panel</p>
        </div>
        <nav class="nav flex-column scrollable-menu">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
            <a class="nav-link" href="students.php"><i class="fas fa-users me-2"></i>Students</a>
            <a class="nav-link" href="staff.php"><i class="fas fa-chalkboard-teacher me-2"></i>Staff</a>
            <a class="nav-link" href="classes.php"><i class="fas fa-door-open me-2"></i>Classes</a>
            <a class="nav-link" href="subjects.php"><i class="fas fa-book me-2"></i>Subjects</a>
            <a class="nav-link" href="sessions.php"><i class="fas fa-calendar-alt me-2"></i>Sessions</a>
            <a class="nav-link" href="exams.php"><i class="fas fa-file-alt me-2"></i>Exams</a>
            <a class="nav-link active" href="#"><i class="fas fa-question-circle me-2"></i>Add Questions</a>
            <a class="nav-link" href="fees.php"><i class="fas fa-money-bill me-2"></i>Fees</a>
            <a class="nav-link" href="library.php"><i class="fas fa-book-open me-2"></i>Library</a>
            <a class="nav-link" href="events.php"><i class="fas fa-calendar me-2"></i>Events</a>
            <a class="nav-link" href="timetable.php"><i class="fas fa-table me-2"></i>Timetable</a>
            <a class="nav-link" href="student_reports.php"><i class="fas fa-chart-bar me-2"></i>Student Reports</a>
            <a class="nav-link" href="student_promotion.php"><i class="fas fa-user-graduate me-2"></i>Student Promotion</a>
            <!-- <a class="nav-link" href="reports.php"><i class="fas fa-chart-bar me-2"></i>Reports</a> -->
            <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a>
            <a class="nav-link" href="settings.php"><i class="fas fa-cogs me-2"></i>System Settings</a>
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>Add Exam Questions</h2>
                <p class="text-muted mb-0">
                    <?php echo $exam['subject_name']; ?> - <?php echo $exam['class_name']; ?>
                </p>
                <p class="text-muted">
                    Exam: <?php echo $exam['title']; ?> | 
                    Questions: <?php echo $question_count; ?>/<?php echo $exam['total_questions']; ?>
                </p>
            </div>
            <div>
                <a href="exams.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Exams
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($question_count >= $exam['total_questions']): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                All questions have been added! This exam is ready for students.
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Add Question Form -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Add New Question</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Question *</label>
                                <textarea class="form-control" name="question" rows="3" required 
                                          placeholder="Enter the question..."><?php echo $_POST['question'] ?? ''; ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Option A *</label>
                                    <input type="text" class="form-control" name="option_a" required 
                                           value="<?php echo $_POST['option_a'] ?? ''; ?>" placeholder="First option">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Option B *</label>
                                    <input type="text" class="form-control" name="option_b" required 
                                           value="<?php echo $_POST['option_b'] ?? ''; ?>" placeholder="Second option">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Option C *</label>
                                    <input type="text" class="form-control" name="option_c" required 
                                           value="<?php echo $_POST['option_c'] ?? ''; ?>" placeholder="Third option">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Option D *</label>
                                    <input type="text" class="form-control" name="option_d" required 
                                           value="<?php echo $_POST['option_d'] ?? ''; ?>" placeholder="Fourth option">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Correct Answer *</label>
                                    <select class="form-select" name="correct_answer" required>
                                        <option value="">Select correct option</option>
                                        <option value="a" <?php echo ($_POST['correct_answer'] ?? '') == 'a' ? 'selected' : ''; ?>>Option A</option>
                                        <option value="b" <?php echo ($_POST['correct_answer'] ?? '') == 'b' ? 'selected' : ''; ?>>Option B</option>
                                        <option value="c" <?php echo ($_POST['correct_answer'] ?? '') == 'c' ? 'selected' : ''; ?>>Option C</option>
                                        <option value="d" <?php echo ($_POST['correct_answer'] ?? '') == 'd' ? 'selected' : ''; ?>>Option D</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Marks</label>
                                    <input type="number" class="form-control" name="marks" value="<?php echo $_POST['marks'] ?? 1; ?>" min="1" max="10">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-plus me-2"></i>Add Question
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Existing Questions -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Existing Questions (<?php echo $question_count; ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($questions)): ?>
                            <p class="text-muted">No questions added yet.</p>
                        <?php else: ?>
                            <div style="max-height: 500px; overflow-y: auto;">
                                <?php foreach ($questions as $index => $question): ?>
                                    <div class="card question-card mb-3">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="card-title mb-0">Q<?php echo $index + 1; ?></h6>
                                                <span class="badge bg-success">
                                                    Correct: <?php echo strtoupper($question['correct_answer']); ?>
                                                </span>
                                            </div>
                                            <p class="card-text small"><?php echo $question['question']; ?></p>
                                            <div class="row small">
                                                <div class="col-6">
                                                    <span class="badge bg-<?php echo $question['correct_answer'] == 'a' ? 'primary' : 'light text-dark'; ?> mb-1">
                                                        A: <?php echo $question['option_a']; ?>
                                                    </span>
                                                </div>
                                                <div class="col-6">
                                                    <span class="badge bg-<?php echo $question['correct_answer'] == 'b' ? 'primary' : 'light text-dark'; ?> mb-1">
                                                        B: <?php echo $question['option_b']; ?>
                                                    </span>
                                                </div>
                                                <div class="col-6">
                                                    <span class="badge bg-<?php echo $question['correct_answer'] == 'c' ? 'primary' : 'light text-dark'; ?> mb-1">
                                                        C: <?php echo $question['option_c']; ?>
                                                    </span>
                                                </div>
                                                <div class="col-6">
                                                    <span class="badge bg-<?php echo $question['correct_answer'] == 'd' ? 'primary' : 'light text-dark'; ?> mb-1">
                                                        D: <?php echo $question['option_d']; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <small class="text-muted">Marks: <?php echo $question['marks']; ?></small>
                                                <div class="btn-group btn-group-sm float-end">
                                                    <button class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger btn-sm">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($question_count >= $exam['total_questions']): ?>
                            <div class="alert alert-success mt-3">
                                <i class="fas fa-check me-2"></i>
                                All questions completed! Exam is ready.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <?php echo $exam['total_questions'] - $question_count; ?> more questions needed.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
         // Mobile Menu Toggle Functionality
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const mobileOverlay = document.getElementById('mobileOverlay');
        const body = document.body;

        function toggleMobileMenu() {
            body.classList.toggle('sidebar-mobile-open');
            mobileOverlay.style.display = body.classList.contains('sidebar-mobile-open') ? 'block' : 'none';
        }

        mobileMenuToggle.addEventListener('click', toggleMobileMenu);
        mobileOverlay.addEventListener('click', toggleMobileMenu);

        // Close menu when clicking on a link (on mobile)
        document.querySelectorAll('.sidebar .nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    toggleMobileMenu();
                }
            });
        });

        // Close menu when window is resized to desktop size
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                body.classList.remove('sidebar-mobile-open');
                mobileOverlay.style.display = 'none';
            }
        });
    </script>
</body>
</html>