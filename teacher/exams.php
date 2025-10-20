<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('teacher')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$teacher_id = $_SESSION['user_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = sanitize($_POST['csrf_token']);
    
    if (!validateCSRFToken($csrf_token)) {
        $message = "Security token invalid.";
    } else {
        $title = sanitize($_POST['title']);
        $subject_id = sanitize($_POST['subject_id']);
        $duration_minutes = sanitize($_POST['duration_minutes']);
        $total_questions = sanitize($_POST['total_questions']);
        $start_time = sanitize($_POST['start_time']);
        $end_time = sanitize($_POST['end_time']);
        
        // Get class_id from subject
        $subject_query = "SELECT class_id FROM subjects WHERE id = :subject_id AND teacher_id = :teacher_id";
        $stmt = $db->prepare($subject_query);
        $stmt->bindParam(':subject_id', $subject_id);
        $stmt->bindParam(':teacher_id', $teacher_id);
        $stmt->execute();
        $subject = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($subject) {
            // Get current session
            $session_query = "SELECT id FROM academic_sessions WHERE status = 'active' LIMIT 1";
            $session_id = $db->query($session_query)->fetchColumn();
            
            $exam_query = "INSERT INTO cbt_exams (title, subject_id, class_id, session_id, duration_minutes, total_questions, start_time, end_time, created_by) 
                         VALUES (:title, :subject_id, :class_id, :session_id, :duration_minutes, :total_questions, :start_time, :end_time, :created_by)";
            $stmt = $db->prepare($exam_query);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':subject_id', $subject_id);
            $stmt->bindParam(':class_id', $subject['class_id']);
            $stmt->bindParam(':session_id', $session_id);
            $stmt->bindParam(':duration_minutes', $duration_minutes);
            $stmt->bindParam(':total_questions', $total_questions);
            $stmt->bindParam(':start_time', $start_time);
            $stmt->bindParam(':end_time', $end_time);
            $stmt->bindParam(':created_by', $teacher_id);
            
            if ($stmt->execute()) {
                $exam_id = $db->lastInsertId();
                $message = "Exam created successfully! <a href='add_questions.php?exam_id=$exam_id'>Add questions now</a>";
            } else {
                $message = "Error creating exam.";
            }
        } else {
            $message = "Invalid subject selected.";
        }
    }
}

// Get teacher's exams
$exams_query = "SELECT e.*, s.name as subject_name, c.name as class_name 
               FROM cbt_exams e 
               JOIN subjects s ON e.subject_id = s.id 
               JOIN classes c ON e.class_id = c.id 
               WHERE e.created_by = :teacher_id 
               ORDER BY e.created_at DESC";
$stmt = $db->prepare($exams_query);
$stmt->bindParam(':teacher_id', $teacher_id);
$stmt->execute();
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get teacher's subjects
$subjects_query = "SELECT s.*, c.name as class_name 
                  FROM subjects s 
                  JOIN classes c ON s.class_id = c.id 
                  WHERE s.teacher_id = :teacher_id 
                  ORDER BY c.name, s.name";
$stmt = $db->prepare($subjects_query);
$stmt->bindParam(':teacher_id', $teacher_id);
$stmt->execute();
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$settings_qry = "SELECT setting_key, setting_value FROM system_settings";
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
    <title>Manage Exams - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">

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
                <small class="text-muted">Teacher Panel</small>
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
            <p class="text-muted small">Teacher Panel</p>
        </div>
        <nav class="nav flex-column scrollable-menu">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
            <a class="nav-link" href="subjects.php"><i class="fas fa-book me-2"></i>My Subjects</a>
            <a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check me-2"></i>Attendance</a>
            <a class="nav-link" href="results.php"><i class="fas fa-chart-line me-2"></i>Results</a>
            <a class="nav-link active" href="exams.php"><i class="fas fa-file-alt me-2"></i>CBT Exams</a>
            <a class="nav-link" href="timetable.php"><i class="fas fa-table me-2"></i>Timetable</a>
            <a class="nav-link" href="events.php"><i class="fas fa-calendar me-2"></i>Events</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a>
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </div>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h2>Manage CBT Exams</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExamModal">
                        <i class="fas fa-plus me-2"></i>Create Exam
                    </button>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-info"><?php echo $message; ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">My Exams</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Exam Title</th>
                                        <th>Subject</th>
                                        <th>Class</th>
                                        <th>Duration</th>
                                        <th>Questions</th>
                                        <th>Schedule</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($exams as $exam): ?>
                                        <tr>
                                            <td><?php echo $exam['title']; ?></td>
                                            <td><?php echo $exam['subject_name']; ?></td>
                                            <td><?php echo $exam['class_name']; ?></td>
                                            <td><?php echo $exam['duration_minutes']; ?> mins</td>
                                            <td><?php echo $exam['total_questions']; ?></td>
                                            <td>
                                                <small>
                                                    <?php echo date('M j, g:i A', strtotime($exam['start_time'])); ?><br>
                                                    to <?php echo date('M j, g:i A', strtotime($exam['end_time'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php
                                                $now = time();
                                                $start = strtotime($exam['start_time']);
                                                $end = strtotime($exam['end_time']);
                                                
                                                if ($now < $start) {
                                                    $status = 'upcoming';
                                                    $badge = 'warning';
                                                } elseif ($now >= $start && $now <= $end) {
                                                    $status = 'active';
                                                    $badge = 'success';
                                                } else {
                                                    $status = 'completed';
                                                    $badge = 'secondary';
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $badge; ?>">
                                                    <?php echo ucfirst($status); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="add_questions.php?exam_id=<?php echo $exam['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-question-circle"></i>
                                                    </a>
                                                    <button class="btn btn-sm btn-outline-info">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Exam Modal -->
    <div class="modal fade" id="addExamModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New CBT Exam</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Exam Title *</label>
                                <input type="text" class="form-control" name="title" required 
                                       placeholder="e.g., First Term Mathematics Exam">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Subject *</label>
                                <select class="form-select" name="subject_id" required>
                                    <option value="">Select Subject</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?php echo $subject['id']; ?>">
                                            <?php echo $subject['name']; ?> - <?php echo $subject['class_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Duration (minutes) *</label>
                                <input type="number" class="form-control" name="duration_minutes" required 
                                       min="1" max="180" value="60">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Total Questions *</label>
                                <input type="number" class="form-control" name="total_questions" required 
                                       min="1" max="100" value="20">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Time *</label>
                                <input type="datetime-local" class="form-control" name="start_time" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Time *</label>
                                <input type="datetime-local" class="form-control" name="end_time" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Create Exam</button>
                    </div>
                </form>
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
        // Set default datetime values
        const now = new Date();
        const startTime = new Date(now.getTime() + 60 * 60 * 1000); // 1 hour from now
        const endTime = new Date(startTime.getTime() + 2 * 60 * 60 * 1000); // 3 hours from now
        
        document.querySelector('input[name="start_time"]').value = startTime.toISOString().slice(0, 16);
        document.querySelector('input[name="end_time"]').value = endTime.toISOString().slice(0, 16);
    </script>
</body>
</html>