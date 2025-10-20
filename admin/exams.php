<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = sanitize($_POST['csrf_token']);
    
    if (!validateCSRFToken($csrf_token)) {
        $message = "Security token invalid.";
    } else {
        // Handle different actions
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_exam':
                    $title = sanitize($_POST['title']);
                    $subject_id = sanitize($_POST['subject_id']);
                    $class_id = sanitize($_POST['class_id']);
                    $duration_minutes = sanitize($_POST['duration_minutes']);
                    $total_questions = sanitize($_POST['total_questions']);
                    $start_time = sanitize($_POST['start_time']);
                    $end_time = sanitize($_POST['end_time']);
                    
                    // Get current session
                    $session_query = "SELECT id FROM academic_sessions WHERE status = 'active' LIMIT 1";
                    $session_id = $db->query($session_query)->fetchColumn();
                    
                    $query = "INSERT INTO cbt_exams (title, subject_id, class_id, session_id, duration_minutes, total_questions, start_time, end_time, created_by) 
                             VALUES (:title, :subject_id, :class_id, :session_id, :duration_minutes, :total_questions, :start_time, :end_time, :created_by)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':title', $title);
                    $stmt->bindParam(':subject_id', $subject_id);
                    $stmt->bindParam(':class_id', $class_id);
                    $stmt->bindParam(':session_id', $session_id);
                    $stmt->bindParam(':duration_minutes', $duration_minutes);
                    $stmt->bindParam(':total_questions', $total_questions);
                    $stmt->bindParam(':start_time', $start_time);
                    $stmt->bindParam(':end_time', $end_time);
                    $stmt->bindParam(':created_by', $_SESSION['user_id']);
                    
                    if ($stmt->execute()) {
                        $exam_id = $db->lastInsertId();
                        $message = "Exam created successfully! <a href='add_questions.php?exam_id=$exam_id'>Add questions now</a>";
                    } else {
                        $message = "Error creating exam.";
                    }
                    break;
                
                case 'update_exam':
                    $exam_id = sanitize($_POST['exam_id']);
                    $title = sanitize($_POST['title']);
                    $subject_id = sanitize($_POST['subject_id']);
                    $class_id = sanitize($_POST['class_id']);
                    $duration_minutes = sanitize($_POST['duration_minutes']);
                    $total_questions = sanitize($_POST['total_questions']);
                    $start_time = sanitize($_POST['start_time']);
                    $end_time = sanitize($_POST['end_time']);
                    
                    $query = "UPDATE cbt_exams SET title = :title, subject_id = :subject_id, class_id = :class_id, 
                             duration_minutes = :duration_minutes, total_questions = :total_questions, 
                             start_time = :start_time, end_time = :end_time, updated_at = NOW() 
                             WHERE id = :exam_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':title', $title);
                    $stmt->bindParam(':subject_id', $subject_id);
                    $stmt->bindParam(':class_id', $class_id);
                    $stmt->bindParam(':duration_minutes', $duration_minutes);
                    $stmt->bindParam(':total_questions', $total_questions);
                    $stmt->bindParam(':start_time', $start_time);
                    $stmt->bindParam(':end_time', $end_time);
                    $stmt->bindParam(':exam_id', $exam_id);
                    
                    if ($stmt->execute()) {
                        $message = "Exam updated successfully!";
                    } else {
                        $message = "Error updating exam.";
                    }
                    break;
                
                case 'delete_exam':
                    $exam_id = sanitize($_POST['exam_id']);
                    
                    // Check if exam has questions or results before deleting
                    $check_questions = $db->query("SELECT COUNT(*) FROM cbt_questions WHERE exam_id = $exam_id")->fetchColumn();
                    $check_results = $db->query("SELECT COUNT(*) FROM cbt_results WHERE exam_id = $exam_id")->fetchColumn();
                    
                    if ($check_questions > 0 || $check_results > 0) {
                        $message = "Cannot delete exam. It has existing questions or student results.";
                    } else {
                        $query = "DELETE FROM cbt_exams WHERE id = :exam_id";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':exam_id', $exam_id);
                        
                        if ($stmt->execute()) {
                            $message = "Exam deleted successfully!";
                        } else {
                            $message = "Error deleting exam.";
                        }
                    }
                    break;
            }
        } else {
            // Original add exam logic for backward compatibility
            $title = sanitize($_POST['title']);
            $subject_id = sanitize($_POST['subject_id']);
            $class_id = sanitize($_POST['class_id']);
            $duration_minutes = sanitize($_POST['duration_minutes']);
            $total_questions = sanitize($_POST['total_questions']);
            $start_time = sanitize($_POST['start_time']);
            $end_time = sanitize($_POST['end_time']);
            
            // Get current session
            $session_query = "SELECT id FROM academic_sessions WHERE status = 'active' LIMIT 1";
            $session_id = $db->query($session_query)->fetchColumn();
            
            $query = "INSERT INTO cbt_exams (title, subject_id, class_id, session_id, duration_minutes, total_questions, start_time, end_time, created_by) 
                     VALUES (:title, :subject_id, :class_id, :session_id, :duration_minutes, :total_questions, :start_time, :end_time, :created_by)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':subject_id', $subject_id);
            $stmt->bindParam(':class_id', $class_id);
            $stmt->bindParam(':session_id', $session_id);
            $stmt->bindParam(':duration_minutes', $duration_minutes);
            $stmt->bindParam(':total_questions', $total_questions);
            $stmt->bindParam(':start_time', $start_time);
            $stmt->bindParam(':end_time', $end_time);
            $stmt->bindParam(':created_by', $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $exam_id = $db->lastInsertId();
                $message = "Exam created successfully! <a href='add_questions.php?exam_id=$exam_id'>Add questions now</a>";
            } else {
                $message = "Error creating exam.";
            }
        }
    }
}

// Get exams with details
$exams_query = "SELECT e.*, s.name as subject_name, c.name as class_name, u.full_name as created_by_name 
               FROM cbt_exams e 
               JOIN subjects s ON e.subject_id = s.id 
               JOIN classes c ON e.class_id = c.id 
               JOIN users u ON e.created_by = u.id 
               ORDER BY e.created_at DESC";
$exams = $db->query($exams_query)->fetchAll(PDO::FETCH_ASSOC);

// Get classes and subjects for dropdowns
$classes = $db->query("SELECT * FROM classes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$subjects = $db->query("SELECT * FROM subjects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Manage Exams - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .exam-status { font-size: 0.8rem; padding: 3px 8px; border-radius: 10px; }
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
            <a class="nav-link active" href="exams.php"><i class="fas fa-file-alt me-2"></i>Exams</a>
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
                <h5 class="card-title mb-0">Exams List</h5>
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
                                        <span class="badge bg-<?php echo $badge; ?> exam-status">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="add_questions.php?exam_id=<?php echo $exam['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-question-circle"></i>
                                            </a>
                                            <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#editExamModal"
                                                    data-exam-id="<?php echo $exam['id']; ?>"
                                                    data-title="<?php echo htmlspecialchars($exam['title']); ?>"
                                                    data-subject-id="<?php echo $exam['subject_id']; ?>"
                                                    data-class-id="<?php echo $exam['class_id']; ?>"
                                                    data-duration-minutes="<?php echo $exam['duration_minutes']; ?>"
                                                    data-total-questions="<?php echo $exam['total_questions']; ?>"
                                                    data-start-time="<?php echo date('Y-m-d\TH:i', strtotime($exam['start_time'])); ?>"
                                                    data-end-time="<?php echo date('Y-m-d\TH:i', strtotime($exam['end_time'])); ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this exam?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="action" value="delete_exam">
                                                <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
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
                    <input type="hidden" name="action" value="add_exam">
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Exam Title *</label>
                                <input type="text" class="form-control" name="title" required 
                                       placeholder="e.g., First Term Mathematics Exam">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Class *</label>
                                <select class="form-select" name="class_id" required onchange="loadClassSubjects(this.value, 'add')">
                                    <option value="">Select Class</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>">
                                            <?php echo $class['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Subject *</label>
                                <select class="form-select" name="subject_id" id="add_subject_id" required disabled>
                                    <option value="">First select a class</option>
                                </select>
                                <div class="loading-spinner" id="subject_loading">
                                    <i class="fas fa-spinner fa-spin"></i> Loading subjects...
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Duration (minutes) *</label>
                                <input type="number" class="form-control" name="duration_minutes" required 
                                       min="1" max="180" value="60">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Total Questions *</label>
                                <input type="number" class="form-control" name="total_questions" required 
                                       min="1" max="100" value="20">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Time *</label>
                                <input type="datetime-local" class="form-control" name="start_time" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Time *</label>
                                <input type="datetime-local" class="form-control" name="end_time" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" id="submitBtn" class="btn btn-primary">Create Exam</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Exam Modal -->
    <div class="modal fade" id="editExamModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit CBT Exam</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="update_exam">
                    <input type="hidden" name="exam_id" id="editExamId">
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Exam Title *</label>
                                <input type="text" class="form-control" name="title" required id="editTitle"
                                       placeholder="e.g., First Term Mathematics Exam">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Subject *</label>
                                <select class="form-select" name="subject_id" required id="editSubjectId">
                                    <option value="">Select Subject</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?php echo $subject['id']; ?>">
                                            <?php echo $subject['name']; ?> (<?php echo $subject['code']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Class *</label>
                                <select class="form-select" name="class_id" required id="editClassId">
                                    <option value="">Select Class</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>">
                                            <?php echo $class['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Duration (minutes) *</label>
                                <input type="number" class="form-control" name="duration_minutes" required 
                                       min="1" max="180" id="editDurationMinutes">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Total Questions *</label>
                                <input type="number" class="form-control" name="total_questions" required 
                                       min="1" max="100" id="editTotalQuestions">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Time *</label>
                                <input type="datetime-local" class="form-control" name="start_time" required id="editStartTime">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Time *</label>
                                <input type="datetime-local" class="form-control" name="end_time" required id="editEndTime">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Update Exam</button>
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
        function loadClassSubjects(classId, formType) {
            if (!classId) {
                // Reset dropdowns if no class selected
                document.getElementById(formType + '_subject_id').innerHTML = '<option value="">First select a class</option>';
                document.getElementById(formType + '_subject_id').disabled = true;                
                if (formType === 'add') {
                    document.getElementById('submitBtn').disabled = true;
                }
                return;
            }

            // Show loading spinners
            if (formType === 'add') {
                document.getElementById('subject_loading').style.display = 'block';
                document.getElementById('submitBtn').disabled = true;
            }

            // Create FormData object
            const formData = new FormData();
            formData.append('class_id', classId);

            // Make AJAX request
            fetch('get_class_data.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Update subjects dropdown
                const subjectSelect = document.getElementById(formType + '_subject_id');
                subjectSelect.innerHTML = '<option value="">Select Subject</option>';
                
                if (data.subjects && data.subjects.length > 0) {
                    data.subjects.forEach(subject => {
                        const option = document.createElement('option');
                        option.value = subject.id;
                        option.textContent = subject.name + ' (' + subject.code + ')';
                        subjectSelect.appendChild(option);
                    });
                    subjectSelect.disabled = false;
                } else {
                    subjectSelect.innerHTML = '<option value="">No subjects found for this class</option>';
                    subjectSelect.disabled = true;
                }

                // Hide loading spinners and enable submit button if both dropdowns have options
                if (formType === 'add') {
                    document.getElementById('subject_loading').style.display = 'none';
                    
                    if (data.subjects && data.subjects.length > 0) {
                        document.getElementById('submitBtn').disabled = false;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (formType === 'add') {
                    document.getElementById('subject_loading').style.display = 'none';
                }
                
                const subjectSelect = document.getElementById(formType + '_subject_id');

                subjectSelect.innerHTML = '<option value="">Error loading subjects</option>';
                subjectSelect.disabled = true;
                
                if (formType === 'add') {
                    document.getElementById('submitBtn').disabled = true;
                }
            });
        }
        // Set default datetime values for add modal
        const now = new Date();
        const startTime = new Date(now.getTime() + 60 * 60 * 1000); // 1 hour from now
        const endTime = new Date(startTime.getTime() + 2 * 60 * 60 * 1000); // 3 hours from now
        
        document.querySelector('#addExamModal input[name="start_time"]').value = startTime.toISOString().slice(0, 16);
        document.querySelector('#addExamModal input[name="end_time"]').value = endTime.toISOString().slice(0, 16);

        // Edit Exam Modal
        document.getElementById('editExamModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('editExamId').value = button.getAttribute('data-exam-id');
            document.getElementById('editTitle').value = button.getAttribute('data-title');
            document.getElementById('editSubjectId').value = button.getAttribute('data-subject-id');
            document.getElementById('editClassId').value = button.getAttribute('data-class-id');
            document.getElementById('editDurationMinutes').value = button.getAttribute('data-duration-minutes');
            document.getElementById('editTotalQuestions').value = button.getAttribute('data-total-questions');
            document.getElementById('editStartTime').value = button.getAttribute('data-start-time');
            document.getElementById('editEndTime').value = button.getAttribute('data-end-time');
        });
    </script>
</body>
</html>