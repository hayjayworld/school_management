<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$action = $_GET['action'] ?? '';
$subject_id = $_GET['id'] ?? 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = sanitize($_POST['csrf_token']);
    
    if (!validateCSRFToken($csrf_token)) {
        $message = "Security token invalid.";
    } else {
        switch ($_POST['action']) {
            case 'add_subject':
                $name = sanitize($_POST['name']);
                $code = sanitize($_POST['code']);
                $class_id = sanitize($_POST['class_id']);
                $teacher_id = sanitize($_POST['teacher_id']) ?: null;
                $description = sanitize($_POST['description']);
                
                // Check if subject code already exists
                $check_query = "SELECT id FROM subjects WHERE code = :code";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':code', $code);
                $check_stmt->execute();
                
                if ($check_stmt->fetch()) {
                    $message = "Error: Subject code already exists.";
                } else {
                    $query = "INSERT INTO subjects (name, code, class_id, teacher_id, description) 
                             VALUES (:name, :code, :class_id, :teacher_id, :description)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':code', $code);
                    $stmt->bindParam(':class_id', $class_id);
                    $stmt->bindParam(':teacher_id', $teacher_id);
                    $stmt->bindParam(':description', $description);
                    
                    if ($stmt->execute()) {
                        $message = "Subject added successfully!";
                    } else {
                        $message = "Error adding subject.";
                    }
                }
                break;
                
            case 'update_subject':
                $subject_id = sanitize($_POST['subject_id']);
                $name = sanitize($_POST['name']);
                $code = sanitize($_POST['code']);
                $class_id = sanitize($_POST['class_id']);
                $teacher_id = sanitize($_POST['teacher_id']) ?: null;
                $description = sanitize($_POST['description']);
                $status = sanitize($_POST['status']);
                
                // Check if subject code already exists (excluding current subject)
                $check_query = "SELECT id FROM subjects WHERE code = :code AND id != :subject_id";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':code', $code);
                $check_stmt->bindParam(':subject_id', $subject_id);
                $check_stmt->execute();
                
                if ($check_stmt->fetch()) {
                    $message = "Error: Subject code already exists.";
                } else {
                    $query = "UPDATE subjects SET name = :name, code = :code, class_id = :class_id,
                             teacher_id = :teacher_id, description = :description, status = :status 
                             WHERE id = :subject_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':code', $code);
                    $stmt->bindParam(':class_id', $class_id);
                    $stmt->bindParam(':teacher_id', $teacher_id);
                    $stmt->bindParam(':description', $description);
                    $stmt->bindParam(':status', $status);
                    $stmt->bindParam(':subject_id', $subject_id);
                    
                    if ($stmt->execute()) {
                        $message = "Subject updated successfully!";
                    } else {
                        $message = "Error updating subject.";
                    }
                }
                break;
        }
    }
}

// Handle delete action
if (isset($_GET['delete']) && $subject_id > 0) {
    $delete_id = sanitize($_GET['delete']);
    
    // Check if subject has exam records before deleting
    $check_exams = "SELECT COUNT(*) FROM cbt_exams WHERE subject_id = :subject_id";
    $stmt = $db->prepare($check_exams);
    $stmt->bindParam(':subject_id', $delete_id);
    $stmt->execute();
    $exam_count = $stmt->fetchColumn();
    
    if ($exam_count > 0) {
        $message = "Cannot delete subject. There are exams associated with this subject.";
    } else {
        $delete_query = "DELETE FROM subjects WHERE id = :id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':id', $delete_id);
        
        if ($delete_stmt->execute()) {
            $message = "Subject deleted successfully!";
        } else {
            $message = "Error deleting subject.";
        }
    }
}

// Handle status toggle
if (isset($_GET['toggle_status']) && $subject_id > 0) {
    $toggle_id = sanitize($_GET['toggle_status']);
    
    // Get current status
    $status_query = "SELECT status FROM subjects WHERE id = :id";
    $status_stmt = $db->prepare($status_query);
    $status_stmt->bindParam(':id', $toggle_id);
    $status_stmt->execute();
    $current_status = $status_stmt->fetchColumn();
    
    $new_status = $current_status == 'active' ? 'inactive' : 'active';
    
    $update_query = "UPDATE subjects SET status = :status WHERE id = :id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':status', $new_status);
    $update_stmt->bindParam(':id', $toggle_id);
    
    if ($update_stmt->execute()) {
        $message = "Subject " . $new_status . " successfully!";
    } else {
        $message = "Error updating subject status.";
    }
}

// Get filter parameters
$class_filter = $_GET['class_filter'] ?? '';
$section_filter = $_GET['section_filter'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';

// Build subjects query with filters
$subjects_query = "SELECT s.*, c.name as class_name, c.section as class_section, 
                  u.full_name as teacher_name,
                  (SELECT COUNT(*) FROM cbt_exams WHERE subject_id = s.id) as exam_count
                  FROM subjects s 
                  JOIN classes c ON s.class_id = c.id 
                  LEFT JOIN users u ON s.teacher_id = u.id 
                  WHERE 1=1";

$params = [];

if ($class_filter) {
    $subjects_query .= " AND s.class_id = :class_id";
    $params[':class_id'] = $class_filter;
}

if ($section_filter) {
    $subjects_query .= " AND c.section = :section";
    $params[':section'] = $section_filter;
}

if ($status_filter) {
    $subjects_query .= " AND s.status = :status";
    $params[':status'] = $status_filter;
}

$subjects_query .= " ORDER BY c.section, c.name, s.name";

$stmt = $db->prepare($subjects_query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get classes and teachers for dropdowns
$classes = $db->query("SELECT * FROM classes WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$teachers = $db->query("SELECT id, full_name FROM users WHERE role = 'teacher' AND status = 'active'")->fetchAll(PDO::FETCH_ASSOC);

// Get subject details for edit
$subject = null;
if ($subject_id > 0 && $action == 'edit') {
    $subject_query = "SELECT * FROM subjects WHERE id = :id";
    $stmt = $db->prepare($subject_query);
    $stmt->bindParam(':id', $subject_id);
    $stmt->execute();
    $subject = $stmt->fetch(PDO::FETCH_ASSOC);
}

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
    <title>Manage Subjects - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        
        .subject-card { transition: transform 0.2s; }
        .subject-card:hover { transform: translateY(-2px); }
        .filter-section { background: #f8f9fa; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
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
        <nav class="nav flex-column">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
            <a class="nav-link" href="students.php"><i class="fas fa-users me-2"></i>Students</a>
            <a class="nav-link" href="staff.php"><i class="fas fa-chalkboard-teacher me-2"></i>Staff</a>
            <a class="nav-link" href="classes.php"><i class="fas fa-door-open me-2"></i>Classes</a>
            <a class="nav-link active" href="subjects.php"><i class="fas fa-book me-2"></i>Subjects</a>
            <a class="nav-link" href="sessions.php"><i class="fas fa-calendar-alt me-2"></i>Sessions</a>
            <a class="nav-link" href="exams.php"><i class="fas fa-file-alt me-2"></i>Exams</a>
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
            <h2>Manage Subjects</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                <i class="fas fa-plus me-2"></i>Add Subject
            </button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-info alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo count($subjects); ?></h4>
                                <p class="mb-0">Total Subjects</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-book fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo count(array_filter($subjects, function($s) { return $s['status'] == 'active'; })); ?></h4>
                                <p class="mb-0">Active Subjects</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-check-circle fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo count(array_filter($subjects, function($s) { return $s['teacher_id'] != null; })); ?></h4>
                                <p class="mb-0">Assigned Teachers</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-chalkboard-teacher fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo array_sum(array_column($subjects, 'exam_count')); ?></h4>
                                <p class="mb-0">Total Exams</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-file-alt fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <h5 class="mb-3">Filter Subjects</h5>
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Class</label>
                    <select class="form-select" name="class_filter">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo $class_filter == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo $class['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Section</label>
                    <select class="form-select" name="section_filter">
                        <option value="">All Sections</option>
                        <option value="primary" <?php echo $section_filter == 'primary' ? 'selected' : ''; ?>>Primary</option>
                        <option value="junior" <?php echo $section_filter == 'junior' ? 'selected' : ''; ?>>Junior Secondary</option>
                        <option value="senior" <?php echo $section_filter == 'senior' ? 'selected' : ''; ?>>Senior Secondary</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status_filter">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="subjects.php" class="btn btn-secondary">Clear Filters</a>
                </div>
            </form>
        </div>

        <!-- Edit Subject Form -->
        <?php if ($action == 'edit' && $subject): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Edit Subject</h5>
                    <a href="subjects.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Back to List
                    </a>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="update_subject">
                        <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Subject Name *</label>
                                <input type="text" class="form-control" name="name" required 
                                       value="<?php echo $subject['name']; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Subject Code *</label>
                                <input type="text" class="form-control" name="code" required 
                                       value="<?php echo $subject['code']; ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Class *</label>
                                <select class="form-select" name="class_id" required>
                                    <option value="">Select Class</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>" 
                                            <?php echo $subject['class_id'] == $class['id'] ? 'selected' : ''; ?>>
                                            <?php echo $class['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" required>
                                    <option value="active" <?php echo $subject['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $subject['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Teacher</label>
                            <select class="form-select" name="teacher_id">
                                <option value="">Select Teacher</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>" 
                                        <?php echo $subject['teacher_id'] == $teacher['id'] ? 'selected' : ''; ?>>
                                        <?php echo $teacher['full_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"><?php echo $subject['description']; ?></textarea>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Update Subject</button>
                            <a href="subjects.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- Subjects Grid -->
            <div class="row">
                <?php foreach ($subjects as $subject): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card subject-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="card-title"><?php echo $subject['name']; ?></h5>
                                    <span class="badge bg-<?php echo $subject['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($subject['status']); ?>
                                    </span>
                                </div>
                                
                                <p class="card-text">
                                    <strong>Code:</strong> <?php echo $subject['code']; ?>
                                </p>
                                
                                <p class="card-text">
                                    <strong>Class:</strong> 
                                    <span class="badge bg-<?php echo $subject['class_section'] == 'primary' ? 'info' : ($subject['class_section'] == 'junior' ? 'warning' : 'success'); ?>">
                                        <?php echo $subject['class_name']; ?> (<?php echo ucfirst($subject['class_section']); ?>)
                                    </span>
                                </p>
                                
                                <p class="card-text">
                                    <strong>Teacher:</strong> 
                                    <?php echo $subject['teacher_name'] ?: 'Not assigned'; ?>
                                </p>
                                
                                <?php if ($subject['description']): ?>
                                    <p class="card-text">
                                        <strong>Description:</strong> 
                                        <?php echo $subject['description']; ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-file-alt me-1"></i>
                                        <?php echo $subject['exam_count']; ?> exams
                                    </small>
                                    
                                    <div class="btn-group">
                                        <a href="subjects.php?action=edit&id=<?php echo $subject['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-edit me-1"></i>Edit
                                        </a>
                                        <a href="subjects.php?toggle_status=<?php echo $subject['id']; ?>" 
                                           class="btn btn-outline-<?php echo $subject['status'] == 'active' ? 'warning' : 'success'; ?> btn-sm">
                                            <i class="fas fa-<?php echo $subject['status'] == 'active' ? 'pause' : 'play'; ?> me-1"></i>
                                            <?php echo $subject['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                        </a>
                                        <a href="subjects.php?delete=<?php echo $subject['id']; ?>" 
                                           class="btn btn-outline-danger btn-sm" 
                                           onclick="return confirm('Are you sure you want to delete this subject? This action cannot be undone.')">
                                            <i class="fas fa-trash me-1"></i>Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Subject Modal -->
    <div class="modal fade" id="addSubjectModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Subject</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add_subject">
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Subject Name *</label>
                                <input type="text" class="form-control" name="name" required 
                                       placeholder="e.g., Mathematics, English Language">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Subject Code *</label>
                                <input type="text" class="form-control" name="code" required 
                                       placeholder="e.g., MATH, ENG">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Class *</label>
                            <select class="form-select" name="class_id" required>
                                <option value="">Select Class</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>">
                                        <?php echo $class['name']; ?> (<?php echo ucfirst($class['section']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Teacher</label>
                            <select class="form-select" name="teacher_id">
                                <option value="">Select Teacher</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo $teacher['full_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" 
                                      placeholder="Optional subject description..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Add Subject</button>
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
    </script>
</body>
</html>