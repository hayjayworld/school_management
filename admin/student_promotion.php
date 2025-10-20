<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$message = '';

// Get current active session
$current_session_query = "SELECT * FROM academic_sessions WHERE status = 'active' LIMIT 1";
$current_session = $db->query($current_session_query)->fetch(PDO::FETCH_ASSOC);

// Get classes
$classes = $db->query("SELECT * FROM classes WHERE status = 'active' ORDER BY section, name")->fetchAll(PDO::FETCH_ASSOC);

// Handle promotion
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = sanitize($_POST['csrf_token']);
    
    if (!validateCSRFToken($csrf_token)) {
        $message = "Security token invalid.";
    } else {
        if (isset($_POST['action']) && $_POST['action'] == 'promote_students') {
            $current_class_id = sanitize($_POST['current_class_id']);
            $new_class_id = sanitize($_POST['new_class_id']);
            $new_session_id = sanitize($_POST['new_session_id']);
            $student_ids = $_POST['student_ids'] ?? [];
            
            if (empty($student_ids)) {
                $message = "Please select at least one student to promote.";
            } elseif ($current_class_id == $new_class_id) {
                $message = "New class must be different from current class.";
            } else {
                $success_count = 0;
                $error_count = 0;
                
                foreach ($student_ids as $student_id) {
                    // Check if student already exists in the new session and class
                    $check_query = "SELECT id FROM students WHERE student_id = 
                                   (SELECT student_id FROM students WHERE id = :student_id) 
                                   AND session_id = :session_id AND class_id = :class_id";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->bindParam(':student_id', $student_id);
                    $check_stmt->bindParam(':session_id', $new_session_id);
                    $check_stmt->bindParam(':class_id', $new_class_id);
                    $check_stmt->execute();
                    
                    if ($check_stmt->fetch()) {
                        $error_count++;
                        continue;
                    }
                    
                    // Update student to new class and session
                    $update_query = "UPDATE students SET class_id = :new_class_id, session_id = :new_session_id, 
                                   updated_at = NOW() WHERE id = :student_id";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->bindParam(':new_class_id', $new_class_id);
                    $update_stmt->bindParam(':new_session_id', $new_session_id);
                    $update_stmt->bindParam(':student_id', $student_id);
                    
                    if ($update_stmt->execute()) {
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                }
                
                if ($success_count > 0) {
                    $message = "Successfully promoted $success_count student(s).";
                    if ($error_count > 0) {
                        $message .= " $error_count student(s) could not be promoted (possibly already in target class).";
                    }
                } else {
                    $message = "No students were promoted. They may already be in the target class.";
                }
            }
        }
    }
}

// Get students for selected class
$students = [];
if (isset($_GET['class_id']) && !empty($_GET['class_id'])) {
    $class_id = $_GET['class_id'];
    $students_query = "SELECT s.id, s.student_id, u.full_name, u.gender, c.name as class_name, ses.name as session_name
                      FROM students s 
                      JOIN users u ON s.user_id = u.id 
                      JOIN classes c ON s.class_id = c.id 
                      JOIN academic_sessions ses ON s.session_id = ses.id 
                      WHERE s.class_id = :class_id AND s.status = 'active' 
                      ORDER BY u.full_name";
    $stmt = $db->prepare($students_query);
    $stmt->bindParam(':class_id', $class_id);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get next classes based on current class section
$next_classes = [];
if (isset($_GET['class_id']) && !empty($_GET['class_id'])) {
    $current_class_query = "SELECT section FROM classes WHERE id = :class_id";
    $stmt = $db->prepare($current_class_query);
    $stmt->bindParam(':class_id', $class_id);
    $stmt->execute();
    $current_class = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($current_class) {
        $section = $current_class['section'];
        $next_classes_query = "SELECT * FROM classes WHERE section = :section AND status = 'active' AND id != :class_id ORDER BY name";
        $stmt = $db->prepare($next_classes_query);
        $stmt->bindParam(':section', $section);
        $stmt->bindParam(':class_id', $class_id);
        $stmt->execute();
        $next_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Get available sessions for promotion (excluding current session)
$sessions_query = "SELECT * FROM academic_sessions WHERE id != :current_session_id ORDER BY created_at DESC";
$stmt = $db->prepare($sessions_query);
$current_session_id = $current_session ? $current_session['id'] : 0;
$stmt->bindParam(':current_session_id', $current_session_id);
$stmt->execute();
$available_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Student Promotion - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .student-list { max-height: 400px; overflow-y: auto; }
        .promotion-card { border-left: 4px solid #28a745; }
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
            <a class="nav-link" href="fees.php"><i class="fas fa-money-bill me-2"></i>Fees</a>
            <a class="nav-link" href="library.php"><i class="fas fa-book-open me-2"></i>Library</a>
            <a class="nav-link" href="events.php"><i class="fas fa-calendar me-2"></i>Events</a>
            <a class="nav-link" href="timetable.php"><i class="fas fa-table me-2"></i>Timetable</a>
            <a class="nav-link" href="student_reports.php"><i class="fas fa-chart-bar me-2"></i>Student Reports</a>
            <a class="nav-link active" href="student_promotion.php"><i class="fas fa-user-graduate me-2"></i>Student Promotion</a>
            <!-- <a class="nav-link" href="reports.php"><i class="fas fa-chart-pie me-2"></i>Reports</a> -->
            <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a>
            <a class="nav-link" href="settings.php"><i class="fas fa-cogs me-2"></i>System Settings</a>
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Student Promotion</h2>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!$current_session): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                No active academic session found. Please activate a session first.
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Class Selection -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-filter me-2"></i>Select Current Class</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET">
                            <div class="mb-3">
                                <label class="form-label">Current Class</label>
                                <select class="form-select" name="class_id" onchange="this.form.submit()">
                                    <option value="">Select Class</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>" 
                                                <?php echo isset($_GET['class_id']) && $_GET['class_id'] == $class['id'] ? 'selected' : ''; ?>>
                                            <?php echo $class['name']; ?> (<?php echo ucfirst($class['section']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>

                        <?php if (isset($_GET['class_id']) && !empty($_GET['class_id'])): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Current Session:</strong> <?php echo $current_session ? $current_session['name'] : 'None'; ?><br>
                                <strong>Selected Class:</strong> <?php echo $students[0]['class_name'] ?? ''; ?><br>
                                <strong>Total Students:</strong> <?php echo count($students); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Promotion Settings -->
            <div class="col-md-8">
                <?php if (isset($_GET['class_id']) && !empty($_GET['class_id'])): ?>
                    <div class="card promotion-card">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0"><i class="fas fa-user-graduate me-2"></i>Promotion Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="promote_students">
                                <input type="hidden" name="current_class_id" value="<?php echo $_GET['class_id']; ?>">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">New Class *</label>
                                        <select class="form-select" name="new_class_id" required>
                                            <option value="">Select New Class</option>
                                            <?php foreach ($next_classes as $class): ?>
                                                <option value="<?php echo $class['id']; ?>">
                                                    <?php echo $class['name']; ?> (<?php echo ucfirst($class['section']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Students will be moved to this class</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">New Academic Session *</label>
                                        <select class="form-select" name="new_session_id" required>
                                            <option value="">Select Session</option>
                                            <?php foreach ($available_sessions as $session): ?>
                                                <option value="<?php echo $session['id']; ?>">
                                                    <?php echo $session['name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Students will be assigned to this session</small>
                                    </div>
                                </div>

                                <!-- Students List -->
                                <div class="mb-3">
                                    <label class="form-label">Select Students to Promote</label>
                                    <div class="student-list border rounded p-3">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="selectAll">
                                            <label class="form-check-label fw-bold" for="selectAll">
                                                Select All Students (<?php echo count($students); ?>)
                                            </label>
                                        </div>
                                        <hr>
                                        <?php if ($students): ?>
                                            <?php foreach ($students as $student): ?>
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input student-checkbox" type="checkbox" 
                                                           name="student_ids[]" value="<?php echo $student['id']; ?>" 
                                                           id="student_<?php echo $student['id']; ?>">
                                                    <label class="form-check-label" for="student_<?php echo $student['id']; ?>">
                                                        <?php echo $student['full_name']; ?> 
                                                        (<?php echo $student['student_id']; ?>)
                                                        <small class="text-muted">- <?php echo ucfirst($student['gender']); ?></small>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="text-center text-muted">
                                                <i class="fas fa-users fa-2x mb-2"></i>
                                                <p>No students found in this class.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Warning:</strong> This action cannot be undone. Students will be moved to the new class and session.
                                </div>

                                <button type="submit" class="btn btn-success" 
                                        onclick="return confirm('Are you sure you want to promote the selected students?')">
                                    <i class="fas fa-user-graduate me-2"></i>Promote Selected Students
                                </button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center text-muted">
                            <i class="fas fa-user-graduate fa-3x mb-3"></i>
                            <h5>Select a class to begin student promotion</h5>
                            <p>Choose a class from the left panel to view and promote students</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Promotion History -->
        <?php if (isset($_GET['class_id']) && !empty($_GET['class_id'])): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-history me-2"></i>Recent Promotions</h5>
            </div>
            <div class="card-body">
                <?php
                $promotion_history_query = "SELECT s.student_id, u.full_name, old_c.name as old_class, 
                                          new_c.name as new_class, old_ses.name as old_session,
                                          new_ses.name as new_session, s.updated_at
                                          FROM students s
                                          JOIN users u ON s.user_id = u.id
                                          JOIN classes old_c ON s.class_id = old_c.id
                                          JOIN classes new_c ON s.class_id = new_c.id
                                          JOIN academic_sessions old_ses ON s.session_id = old_ses.id  
                                          JOIN academic_sessions new_ses ON s.session_id = new_ses.id
                                          WHERE s.class_id = :class_id 
                                          ORDER BY s.updated_at DESC LIMIT 10";
                $stmt = $db->prepare($promotion_history_query);
                $stmt->bindParam(':class_id', $_GET['class_id']);
                $stmt->execute();
                $promotion_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                
                <?php if ($promotion_history): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Session</th>
                                    <th>Promoted On</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($promotion_history as $history): ?>
                                    <tr>
                                        <td><?php echo $history['full_name']; ?></td>
                                        <td><?php echo $history['old_class']; ?></td>
                                        <td><?php echo $history['new_class']; ?></td>
                                        <td><?php echo $history['new_session']; ?></td>
                                        <td><?php echo date('M j, Y', strtotime($history['updated_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted">
                        <p>No promotion history found for this class.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
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
        // Select all students functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.student-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>