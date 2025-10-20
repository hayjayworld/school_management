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
$class_id = $_GET['id'] ?? 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = sanitize($_POST['csrf_token']);
    
    if (!validateCSRFToken($csrf_token)) {
        $message = "Security token invalid.";
    } else {
        switch ($_POST['action']) {
            case 'add_class':
                $name = sanitize($_POST['name']);
                $section = sanitize($_POST['section']);
                $class_teacher_id = sanitize($_POST['class_teacher_id']) ?: null;
                $capacity = sanitize($_POST['capacity']) ?: 40;
                $room_number = sanitize($_POST['room_number']);
                
                $query = "INSERT INTO classes (name, section, class_teacher_id, capacity, room_number) 
                         VALUES (:name, :section, :class_teacher_id, :capacity, :room_number)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':section', $section);
                $stmt->bindParam(':class_teacher_id', $class_teacher_id);
                $stmt->bindParam(':capacity', $capacity);
                $stmt->bindParam(':room_number', $room_number);
                
                if ($stmt->execute()) {
                    $message = "Class added successfully!";
                } else {
                    $message = "Error adding class.";
                }
                break;
                
            case 'update_class':
                $class_id = sanitize($_POST['class_id']);
                $name = sanitize($_POST['name']);
                $section = sanitize($_POST['section']);
                $class_teacher_id = sanitize($_POST['class_teacher_id']) ?: null;
                $capacity = sanitize($_POST['capacity']);
                $room_number = sanitize($_POST['room_number']);
                $status = sanitize($_POST['status']);
                
                $query = "UPDATE classes SET name = :name, section = :section, 
                         class_teacher_id = :class_teacher_id, capacity = :capacity, 
                         room_number = :room_number, status = :status 
                         WHERE id = :class_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':section', $section);
                $stmt->bindParam(':class_teacher_id', $class_teacher_id);
                $stmt->bindParam(':capacity', $capacity);
                $stmt->bindParam(':room_number', $room_number);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':class_id', $class_id);
                
                if ($stmt->execute()) {
                    $message = "Class updated successfully!";
                } else {
                    $message = "Error updating class.";
                }
                break;
        }
    }
}

// Handle delete action
if (isset($_GET['delete']) && $class_id > 0) {
    $delete_id = sanitize($_GET['delete']);
    
    // Check if class has students before deleting
    $check_students = "SELECT COUNT(*) FROM students WHERE class_id = :class_id";
    $stmt = $db->prepare($check_students);
    $stmt->bindParam(':class_id', $delete_id);
    $stmt->execute();
    $student_count = $stmt->fetchColumn();
    
    if ($student_count > 0) {
        $message = "Cannot delete class. There are students assigned to this class.";
    } else {
        $delete_query = "DELETE FROM classes WHERE id = :id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':id', $delete_id);
        
        if ($delete_stmt->execute()) {
            $message = "Class deleted successfully!";
        } else {
            $message = "Error deleting class.";
        }
    }
}

// Handle status toggle
if (isset($_GET['toggle_status']) && $class_id > 0) {
    $toggle_id = sanitize($_GET['toggle_status']);
    
    // Get current status
    $status_query = "SELECT status FROM classes WHERE id = :id";
    $status_stmt = $db->prepare($status_query);
    $status_stmt->bindParam(':id', $toggle_id);
    $status_stmt->execute();
    $current_status = $status_stmt->fetchColumn();
    
    $new_status = $current_status == 'active' ? 'inactive' : 'active';
    
    $update_query = "UPDATE classes SET status = :status WHERE id = :id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':status', $new_status);
    $update_stmt->bindParam(':id', $toggle_id);
    
    if ($update_stmt->execute()) {
        $message = "Class " . $new_status . " successfully!";
    } else {
        $message = "Error updating class status.";
    }
}

// Get classes with teacher info and student count
$classes_query = "SELECT c.*, u.full_name as teacher_name, 
                 (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id AND s.status = 'active') as student_count
                 FROM classes c 
                 LEFT JOIN users u ON c.class_teacher_id = u.id 
                 ORDER BY 
                 CASE c.section 
                     WHEN 'primary' THEN 1 
                     WHEN 'junior' THEN 2 
                     WHEN 'senior' THEN 3 
                 END, c.name";
$classes = $db->query($classes_query)->fetchAll(PDO::FETCH_ASSOC);

// Get teachers for dropdown
$teachers_query = "SELECT id, full_name FROM users WHERE role = 'teacher' AND status = 'active'";
$teachers = $db->query($teachers_query)->fetchAll(PDO::FETCH_ASSOC);

// Get class details for edit
$class = null;
if ($class_id > 0 && $action == 'edit') {
    $class_query = "SELECT * FROM classes WHERE id = :id";
    $stmt = $db->prepare($class_query);
    $stmt->bindParam(':id', $class_id);
    $stmt->execute();
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
}

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
    <title>Manage Classes - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .class-card { transition: transform 0.2s; }
        .class-card:hover { transform: translateY(-5px); }
        .capacity-bar { height: 8px; border-radius: 4px; }
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
            <a class="nav-link active" href="classes.php"><i class="fas fa-door-open me-2"></i>Classes</a>
            <a class="nav-link" href="subjects.php"><i class="fas fa-book me-2"></i>Subjects</a>
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
            <h2>Manage Classes</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClassModal">
                <i class="fas fa-plus me-2"></i>Add Class
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
                                <h4><?php echo count($classes); ?></h4>
                                <p class="mb-0">Total Classes</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-door-open fa-2x opacity-50"></i>
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
                                <h4><?php echo count(array_filter($classes, function($c) { return $c['status'] == 'active'; })); ?></h4>
                                <p class="mb-0">Active Classes</p>
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
                                <h4><?php echo array_sum(array_column($classes, 'student_count')); ?></h4>
                                <p class="mb-0">Total Students</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-2x opacity-50"></i>
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
                                <h4><?php echo count(array_filter($classes, function($c) { return $c['section'] == 'primary'; })); ?></h4>
                                <p class="mb-0">Primary Classes</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-child fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Class Form -->
        <?php if ($action == 'edit' && $class): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Edit Class</h5>
                    <a href="classes.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Back to List
                    </a>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="update_class">
                        <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Class Name *</label>
                                <input type="text" class="form-control" name="name" required 
                                       value="<?php echo $class['name']; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Section *</label>
                                <select class="form-select" name="section" required>
                                    <option value="primary" <?php echo $class['section'] == 'primary' ? 'selected' : ''; ?>>Primary</option>
                                    <option value="junior" <?php echo $class['section'] == 'junior' ? 'selected' : ''; ?>>Junior Secondary</option>
                                    <option value="senior" <?php echo $class['section'] == 'senior' ? 'selected' : ''; ?>>Senior Secondary</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Class Teacher</label>
                                <select class="form-select" name="class_teacher_id">
                                    <option value="">Select Class Teacher</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['id']; ?>" 
                                            <?php echo $class['class_teacher_id'] == $teacher['id'] ? 'selected' : ''; ?>>
                                            <?php echo $teacher['full_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" required>
                                    <option value="active" <?php echo $class['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $class['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Capacity</label>
                                <input type="number" class="form-control" name="capacity" 
                                       value="<?php echo $class['capacity'] ?: 40; ?>" min="1" max="100">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Room Number</label>
                                <input type="text" class="form-control" name="room_number" 
                                       value="<?php echo $class['room_number']; ?>">
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Update Class</button>
                            <a href="classes.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- Classes Grid -->
            <div class="row">
                <?php foreach ($classes as $class): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card class-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="card-title"><?php echo $class['name']; ?></h5>
                                    <span class="badge bg-<?php echo $class['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($class['status']); ?>
                                    </span>
                                </div>
                                
                                <p class="card-text">
                                    <strong>Section:</strong> 
                                    <span class="badge bg-<?php echo $class['section'] == 'primary' ? 'info' : ($class['section'] == 'junior' ? 'warning' : 'success'); ?>">
                                        <?php echo ucfirst($class['section']); ?>
                                    </span>
                                </p>
                                
                                <p class="card-text">
                                    <strong>Class Teacher:</strong> 
                                    <?php echo $class['teacher_name'] ?: 'Not assigned'; ?>
                                </p>
                                
                                <p class="card-text">
                                    <strong>Room:</strong> 
                                    <?php echo $class['room_number'] ?: 'Not assigned'; ?>
                                </p>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>Capacity: <?php echo $class['student_count']; ?>/<?php echo $class['capacity']; ?></small>
                                        <small><?php echo round(($class['student_count'] / $class['capacity']) * 100); ?>%</small>
                                    </div>
                                    <div class="capacity-bar bg-light">
                                        <div class="capacity-bar bg-<?php echo ($class['student_count'] / $class['capacity']) > 0.9 ? 'danger' : (($class['student_count'] / $class['capacity']) > 0.7 ? 'warning' : 'success'); ?>" 
                                             style="width: <?php echo min(100, ($class['student_count'] / $class['capacity']) * 100); ?>%">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="btn-group w-100">
                                    <a href="classes.php?action=edit&id=<?php echo $class['id']; ?>" 
                                       class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-edit me-1"></i>Edit
                                    </a>
                                    <a href="classes.php?toggle_status=<?php echo $class['id']; ?>" 
                                       class="btn btn-outline-<?php echo $class['status'] == 'active' ? 'warning' : 'success'; ?> btn-sm">
                                        <i class="fas fa-<?php echo $class['status'] == 'active' ? 'pause' : 'play'; ?> me-1"></i>
                                        <?php echo $class['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                    </a>
                                    <a href="classes.php?delete=<?php echo $class['id']; ?>" 
                                       class="btn btn-outline-danger btn-sm" 
                                       onclick="return confirm('Are you sure you want to delete this class? This action cannot be undone.')">
                                        <i class="fas fa-trash me-1"></i>Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Class Modal -->
    <div class="modal fade" id="addClassModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Class</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add_class">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Class Name *</label>
                            <input type="text" class="form-control" name="name" required 
                                   placeholder="e.g., Primary 1, JSS 2, SSS 3">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Section *</label>
                            <select class="form-select" name="section" required>
                                <option value="primary">Primary</option>
                                <option value="junior">Junior Secondary</option>
                                <option value="senior">Senior Secondary</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Class Teacher</label>
                            <select class="form-select" name="class_teacher_id">
                                <option value="">Select Class Teacher</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo $teacher['full_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Capacity</label>
                                <input type="number" class="form-control" name="capacity" value="40" min="1" max="100">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Room Number</label>
                                <input type="text" class="form-control" name="room_number" placeholder="e.g., P1-01, J2-B">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Add Class</button>
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