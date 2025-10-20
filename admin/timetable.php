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
$timetable_id = $_GET['id'] ?? 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = sanitize($_POST['csrf_token']);
    
    if (!validateCSRFToken($csrf_token)) {
        $message = "Security token invalid.";
    } else {
        switch ($_POST['action']) {
            case 'add_timetable':
                $class_id = sanitize($_POST['class_id']);
                $subject_id = sanitize($_POST['subject_id']);
                $teacher_id = sanitize($_POST['teacher_id']);
                $day_of_week = sanitize($_POST['day_of_week']);
                $start_time = sanitize($_POST['start_time']);
                $end_time = sanitize($_POST['end_time']);
                $room = sanitize($_POST['room']);
                $session_id = sanitize($_POST['session_id']);
                
                // Check for time conflict
                $conflict_query = "SELECT id FROM timetable 
                                 WHERE class_id = :class_id 
                                 AND day_of_week = :day_of_week 
                                 AND ((start_time <= :start_time AND end_time > :start_time) 
                                 OR (start_time < :end_time AND end_time >= :end_time)
                                 OR (start_time >= :start_time AND end_time <= :end_time))";
                $stmt = $db->prepare($conflict_query);
                $stmt->bindParam(':class_id', $class_id);
                $stmt->bindParam(':day_of_week', $day_of_week);
                $stmt->bindParam(':start_time', $start_time);
                $stmt->bindParam(':end_time', $end_time);
                $stmt->execute();
                
                if ($stmt->fetch()) {
                    $message = "Error: Time slot conflict with existing timetable entry.";
                } else {
                    $query = "INSERT INTO timetable (class_id, subject_id, teacher_id, day_of_week, 
                             start_time, end_time, room, session_id) 
                             VALUES (:class_id, :subject_id, :teacher_id, :day_of_week, 
                             :start_time, :end_time, :room, :session_id)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':class_id', $class_id);
                    $stmt->bindParam(':subject_id', $subject_id);
                    $stmt->bindParam(':teacher_id', $teacher_id);
                    $stmt->bindParam(':day_of_week', $day_of_week);
                    $stmt->bindParam(':start_time', $start_time);
                    $stmt->bindParam(':end_time', $end_time);
                    $stmt->bindParam(':room', $room);
                    $stmt->bindParam(':session_id', $session_id);
                    
                    if ($stmt->execute()) {
                        $message = "Timetable entry added successfully!";
                    } else {
                        $message = "Error adding timetable entry.";
                    }
                }
                break;
                
            case 'update_timetable':
                $timetable_id = sanitize($_POST['timetable_id']);
                $class_id = sanitize($_POST['class_id']);
                $subject_id = sanitize($_POST['subject_id']);
                $teacher_id = sanitize($_POST['teacher_id']);
                $day_of_week = sanitize($_POST['day_of_week']);
                $start_time = sanitize($_POST['start_time']);
                $end_time = sanitize($_POST['end_time']);
                $room = sanitize($_POST['room']);
                $session_id = sanitize($_POST['session_id']);
                $status = sanitize($_POST['status']);
                
                // Check for time conflict (excluding current entry)
                $conflict_query = "SELECT id FROM timetable 
                                 WHERE class_id = :class_id 
                                 AND day_of_week = :day_of_week 
                                 AND id != :timetable_id
                                 AND ((start_time <= :start_time AND end_time > :start_time) 
                                 OR (start_time < :end_time AND end_time >= :end_time)
                                 OR (start_time >= :start_time AND end_time <= :end_time))";
                $stmt = $db->prepare($conflict_query);
                $stmt->bindParam(':class_id', $class_id);
                $stmt->bindParam(':day_of_week', $day_of_week);
                $stmt->bindParam(':timetable_id', $timetable_id);
                $stmt->bindParam(':start_time', $start_time);
                $stmt->bindParam(':end_time', $end_time);
                $stmt->execute();
                
                if ($stmt->fetch()) {
                    $message = "Error: Time slot conflict with existing timetable entry.";
                } else {
                    $query = "UPDATE timetable SET class_id = :class_id, subject_id = :subject_id,
                             teacher_id = :teacher_id, day_of_week = :day_of_week, 
                             start_time = :start_time, end_time = :end_time, room = :room,
                             session_id = :session_id, status = :status 
                             WHERE id = :timetable_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':class_id', $class_id);
                    $stmt->bindParam(':subject_id', $subject_id);
                    $stmt->bindParam(':teacher_id', $teacher_id);
                    $stmt->bindParam(':day_of_week', $day_of_week);
                    $stmt->bindParam(':start_time', $start_time);
                    $stmt->bindParam(':end_time', $end_time);
                    $stmt->bindParam(':room', $room);
                    $stmt->bindParam(':session_id', $session_id);
                    $stmt->bindParam(':status', $status);
                    $stmt->bindParam(':timetable_id', $timetable_id);
                    
                    if ($stmt->execute()) {
                        $message = "Timetable entry updated successfully!";
                    } else {
                        $message = "Error updating timetable entry.";
                    }
                }
                break;
        }
    }
}

// Handle delete action
if (isset($_GET['delete']) && $timetable_id > 0) {
    $delete_id = sanitize($_GET['delete']);
    
    $delete_query = "DELETE FROM timetable WHERE id = :id";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(':id', $delete_id);
    
    if ($delete_stmt->execute()) {
        $message = "Timetable entry deleted successfully!";
    } else {
        $message = "Error deleting timetable entry.";
    }
}

// Handle status toggle
if (isset($_GET['toggle_status']) && $timetable_id > 0) {
    $toggle_id = sanitize($_GET['toggle_status']);
    
    // Get current status
    $status_query = "SELECT status FROM timetable WHERE id = :id";
    $status_stmt = $db->prepare($status_query);
    $status_stmt->bindParam(':id', $toggle_id);
    $status_stmt->execute();
    $current_status = $status_stmt->fetchColumn();
    
    $new_status = $current_status == 'active' ? 'inactive' : 'active';
    
    $update_query = "UPDATE timetable SET status = :status WHERE id = :id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':status', $new_status);
    $update_stmt->bindParam(':id', $toggle_id);
    
    if ($update_stmt->execute()) {
        $message = "Timetable entry " . $new_status . " successfully!";
    } else {
        $message = "Error updating timetable entry status.";
    }
}

// Get filter parameters
$class_filter = $_GET['class_filter'] ?? '';
$day_filter = $_GET['day_filter'] ?? '';

// Build timetable query with filters
$timetable_query = "SELECT t.*, c.name as class_name, s.name as subject_name, 
                   u.full_name as teacher_name, ses.name as session_name
                   FROM timetable t 
                   JOIN classes c ON t.class_id = c.id 
                   JOIN subjects s ON t.subject_id = s.id 
                   JOIN users u ON t.teacher_id = u.id 
                   JOIN academic_sessions ses ON t.session_id = ses.id 
                   WHERE 1=1";

$params = [];

if ($class_filter) {
    $timetable_query .= " AND t.class_id = :class_id";
    $params[':class_id'] = $class_filter;
}

if ($day_filter) {
    $timetable_query .= " AND t.day_of_week = :day_of_week";
    $params[':day_of_week'] = $day_filter;
}

$timetable_query .= " ORDER BY 
                     CASE t.day_of_week 
                         WHEN 'monday' THEN 1 
                         WHEN 'tuesday' THEN 2 
                         WHEN 'wednesday' THEN 3 
                         WHEN 'thursday' THEN 4 
                         WHEN 'friday' THEN 5 
                         WHEN 'saturday' THEN 6 
                     END, t.start_time";

$stmt = $db->prepare($timetable_query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$timetable_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get data for dropdowns
$classes = $db->query("SELECT * FROM classes WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$sessions = $db->query("SELECT * FROM academic_sessions WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get subjects and teachers for a specific class (for AJAX)
$selected_class_id = $_POST['class_id'] ?? ($timetable['class_id'] ?? '');
$subjects = [];
$teachers = [];

if ($selected_class_id) {
    // Get subjects for the selected class
    $subjects_query = "SELECT s.id, s.name, s.code 
                      FROM subjects s 
                      WHERE s.class_id = :class_id 
                      AND s.status = 'active' 
                      ORDER BY s.name";
    $stmt = $db->prepare($subjects_query);
    $stmt->bindParam(':class_id', $selected_class_id);
    $stmt->execute();
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get teachers who teach subjects for this class
    $teachers_query = "SELECT DISTINCT u.id, u.full_name 
                      FROM users u 
                      JOIN subjects s ON u.id = s.teacher_id 
                      WHERE s.class_id = :class_id 
                      AND u.role = 'teacher' 
                      AND u.status = 'active' 
                      ORDER BY u.full_name";
    $stmt = $db->prepare($teachers_query);
    $stmt->bindParam(':class_id', $selected_class_id);
    $stmt->execute();
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get timetable details for edit
$timetable = null;
if ($timetable_id > 0 && $action == 'edit') {
    $timetable_query = "SELECT * FROM timetable WHERE id = :id";
    $stmt = $db->prepare($timetable_query);
    $stmt->bindParam(':id', $timetable_id);
    $stmt->execute();
    $timetable = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If editing, get subjects and teachers for the class of this timetable entry
    if ($timetable) {
        $selected_class_id = $timetable['class_id'];
        
        // Get subjects for the selected class
        $subjects_query = "SELECT s.id, s.name, s.code 
                          FROM subjects s 
                          WHERE s.class_id = :class_id 
                          AND s.status = 'active' 
                          ORDER BY s.name";
        $stmt = $db->prepare($subjects_query);
        $stmt->bindParam(':class_id', $selected_class_id);
        $stmt->execute();
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get teachers who teach subjects for this class
        $teachers_query = "SELECT DISTINCT u.id, u.full_name 
                          FROM users u 
                          JOIN subjects s ON u.id = s.teacher_id 
                          WHERE s.class_id = :class_id 
                          AND u.role = 'teacher' 
                          AND u.status = 'active' 
                          ORDER BY u.full_name";
        $stmt = $db->prepare($teachers_query);
        $stmt->bindParam(':class_id', $selected_class_id);
        $stmt->execute();
        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Generate timetable grid for visualization
$days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
$time_slots = [
    '08:00:00', '09:00:00', '10:00:00', '11:00:00', '12:00:00',
    '13:00:00', '14:00:00', '15:00:00', '16:00:00'
];

$timetable_grid = [];
foreach ($days as $day) {
    $timetable_grid[$day] = [];
    foreach ($time_slots as $time) {
        $timetable_grid[$day][$time] = null;
    }
}

foreach ($timetable_entries as $entry) {
    if ($entry['status'] == 'active') {
        $timetable_grid[$entry['day_of_week']][$entry['start_time']] = $entry;
    }
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
    <title>Manage Timetable - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .sidebar { 
            background: #2c3e50; 
            color: white; 
            height: 100vh; 
            position: fixed; 
            width: 250px; 
            transition: transform 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
        }
        .sidebar .nav-link { 
            color: white; 
            padding: 15px 20px; 
            border-bottom: 1px solid #34495e; 
        }
        .sidebar .nav-link:hover { background: #34495e; }
        .sidebar .nav-link.active { background: #3498db; }
        .main-content { 
            margin-left: 250px; 
            padding: 20px; 
            transition: margin-left 0.3s ease;
        }
        
        /* Mobile Styles */
        .mobile-header {
            display: none;
            background: #2c3e50;
            color: white;
            padding: 15px 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1001;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .menu-toggle {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        .mobile-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        /* When sidebar is hidden on mobile */
        .sidebar-hidden .sidebar {
            transform: translateX(-100%);
        }
        
        .sidebar-hidden .main-content {
            margin-left: 0;
        }
        
        .sidebar-hidden .mobile-overlay {
            display: block;
        }
        
        /* Mobile overlay for closing sidebar */
        .mobile-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .mobile-header {
                display: block;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
                padding-top: 80px; /* Account for mobile header */
            }
            
            .sidebar-mobile-open .sidebar {
                transform: translateX(0);
            }
            
            .sidebar-mobile-open .main-content {
                margin-left: 0;
            }
        }

        /* Existing timetable styles */
        .timetable-cell { 
            height: 80px; 
            border: 1px solid #dee2e6; 
            padding: 5px; 
            font-size: 0.8rem;
            position: relative;
        }
        .timetable-cell.filled { 
            background: #e3f2fd; 
            border-color: #2196f3;
        }
        .timetable-header { 
            background: #2c3e50; 
            color: white; 
            font-weight: bold; 
            text-align: center;
        }
        .time-slot-header {
            background: #34495e;
            color: white;
            font-weight: bold;
            text-align: center;
        }
        .subject-badge {
            font-size: 0.7rem;
            margin-bottom: 2px;
        }
        .teacher-name {
            font-size: 0.65rem;
            color: #666;
        }
        .room-number {
            font-size: 0.6rem;
            color: #888;
            position: absolute;
            bottom: 2px;
            right: 5px;
        }
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 10px;
        }
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
        <div class="p-4 text-cente">
            <h4><i class="fas fa-graduation-cap"></i> <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></h4>
            <p class="text-muted small">Admin Panel</p>
        </div>
        <nav class="nav flex-column">
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
            <a class="nav-link active" href="timetable.php"><i class="fas fa-table me-2"></i>Timetable</a>
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
            <h2>Manage Timetable</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTimetableModal">
                <i class="fas fa-plus me-2"></i>Add Timetable Entry
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
                                <h4><?php echo count($timetable_entries); ?></h4>
                                <p class="mb-0">Total Entries</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-table fa-2x opacity-50"></i>
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
                                <h4><?php echo count(array_filter($timetable_entries, function($t) { return $t['status'] == 'active'; })); ?></h4>
                                <p class="mb-0">Active Entries</p>
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
                                <h4><?php echo count(array_unique(array_column($timetable_entries, 'class_id'))); ?></h4>
                                <p class="mb-0">Classes Scheduled</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-door-open fa-2x opacity-50"></i>
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
                                <h4><?php echo count(array_unique(array_column($timetable_entries, 'teacher_id'))); ?></h4>
                                <p class="mb-0">Teachers Assigned</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-chalkboard-teacher fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Filter Timetable</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Class</label>
                        <select class="form-select" name="class_filter" onchange="this.form.submit()">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>" <?php echo $class_filter == $class['id'] ? 'selected' : ''; ?>>
                                    <?php echo $class['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Day of Week</label>
                        <select class="form-select" name="day_filter" onchange="this.form.submit()">
                            <option value="">All Days</option>
                            <option value="monday" <?php echo $day_filter == 'monday' ? 'selected' : ''; ?>>Monday</option>
                            <option value="tuesday" <?php echo $day_filter == 'tuesday' ? 'selected' : ''; ?>>Tuesday</option>
                            <option value="wednesday" <?php echo $day_filter == 'wednesday' ? 'selected' : ''; ?>>Wednesday</option>
                            <option value="thursday" <?php echo $day_filter == 'thursday' ? 'selected' : ''; ?>>Thursday</option>
                            <option value="friday" <?php echo $day_filter == 'friday' ? 'selected' : ''; ?>>Friday</option>
                            <option value="saturday" <?php echo $day_filter == 'saturday' ? 'selected' : ''; ?>>Saturday</option>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Timetable Form -->
        <?php if ($action == 'edit' && $timetable): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Edit Timetable Entry</h5>
                    <a href="timetable.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Back to List
                    </a>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="update_timetable">
                        <input type="hidden" name="timetable_id" value="<?php echo $timetable['id']; ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Class *</label>
                                <select class="form-select" name="class_id" id="edit_class_id" required onchange="loadClassSubjectsAndTeachers(this.value, 'edit')">
                                    <option value="">Select Class</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>" 
                                            <?php echo $timetable['class_id'] == $class['id'] ? 'selected' : ''; ?>>
                                            <?php echo $class['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Subject *</label>
                                <select class="form-select" name="subject_id" id="edit_subject_id" required>
                                    <option value="">Select Subject</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?php echo $subject['id']; ?>" 
                                            <?php echo $timetable['subject_id'] == $subject['id'] ? 'selected' : ''; ?>>
                                            <?php echo $subject['name']; ?> (<?php echo $subject['code']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Teacher *</label>
                                <select class="form-select" name="teacher_id" id="edit_teacher_id" required>
                                    <option value="">Select Teacher</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['id']; ?>" 
                                            <?php echo $timetable['teacher_id'] == $teacher['id'] ? 'selected' : ''; ?>>
                                            <?php echo $teacher['full_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" required>
                                    <option value="active" <?php echo $timetable['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $timetable['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Day of Week *</label>
                                <select class="form-select" name="day_of_week" required>
                                    <option value="monday" <?php echo $timetable['day_of_week'] == 'monday' ? 'selected' : ''; ?>>Monday</option>
                                    <option value="tuesday" <?php echo $timetable['day_of_week'] == 'tuesday' ? 'selected' : ''; ?>>Tuesday</option>
                                    <option value="wednesday" <?php echo $timetable['day_of_week'] == 'wednesday' ? 'selected' : ''; ?>>Wednesday</option>
                                    <option value="thursday" <?php echo $timetable['day_of_week'] == 'thursday' ? 'selected' : ''; ?>>Thursday</option>
                                    <option value="friday" <?php echo $timetable['day_of_week'] == 'friday' ? 'selected' : ''; ?>>Friday</option>
                                    <option value="saturday" <?php echo $timetable['day_of_week'] == 'saturday' ? 'selected' : ''; ?>>Saturday</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Start Time *</label>
                                <input type="time" class="form-control" name="start_time" required 
                                       value="<?php echo substr($timetable['start_time'], 0, 5); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">End Time *</label>
                                <input type="time" class="form-control" name="end_time" required 
                                       value="<?php echo substr($timetable['end_time'], 0, 5); ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Room</label>
                                <input type="text" class="form-control" name="room" 
                                       value="<?php echo $timetable['room']; ?>" placeholder="e.g., Room 101">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Academic Session *</label>
                                <select class="form-select" name="session_id" required>
                                    <option value="">Select Session</option>
                                    <?php foreach ($sessions as $session): ?>
                                        <option value="<?php echo $session['id']; ?>" 
                                            <?php echo $timetable['session_id'] == $session['id'] ? 'selected' : ''; ?>>
                                            <?php echo $session['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Update Timetable Entry</button>
                            <a href="timetable.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- Timetable Grid View -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Timetable Overview</h5>
                    <div>
                        <span class="badge bg-primary me-2">Active: <?php echo count(array_filter($timetable_entries, function($t) { return $t['status'] == 'active'; })); ?></span>
                        <span class="badge bg-secondary">Total: <?php echo count($timetable_entries); ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th class="time-slot-header">Time</th>
                                    <?php foreach ($days as $day): ?>
                                        <th class="timetable-header"><?php echo ucfirst($day); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($time_slots as $time_slot): ?>
                                    <tr>
                                        <td class="time-slot-header">
                                            <?php echo date('g:i A', strtotime($time_slot)); ?>
                                        </td>
                                        <?php foreach ($days as $day): ?>
                                            <td class="timetable-cell <?php echo $timetable_grid[$day][$time_slot] ? 'filled' : ''; ?>">
                                                <?php if ($timetable_grid[$day][$time_slot]): ?>
                                                    <?php $entry = $timetable_grid[$day][$time_slot]; ?>
                                                    <div class="subject-badge badge bg-primary">
                                                        <?php echo $entry['subject_name']; ?>
                                                    </div>
                                                    <div class="teacher-name">
                                                        <?php echo $entry['teacher_name']; ?>
                                                    </div>
                                                    <div class="room-number">
                                                        <?php echo $entry['room'] ?: 'N/A'; ?>
                                                    </div>
                                                    <div class="text-end">
                                                        <small>
                                                            <a href="timetable.php?action=edit&id=<?php echo $entry['id']; ?>" 
                                                               class="text-warning me-1">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="timetable.php?delete=<?php echo $entry['id']; ?>" 
                                                               class="text-danger"
                                                               onclick="return confirm('Are you sure you want to delete this timetable entry?')">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        </small>
                                                    </div>
                                                <?php else: ?>
                                                    <small class="text-muted">Free</small>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- List View -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">All Timetable Entries</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Class</th>
                                    <th>Subject</th>
                                    <th>Teacher</th>
                                    <th>Day</th>
                                    <th>Time</th>
                                    <th>Room</th>
                                    <th>Session</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($timetable_entries as $entry): ?>
                                    <tr>
                                        <td><?php echo $entry['class_name']; ?></td>
                                        <td><?php echo $entry['subject_name']; ?></td>
                                        <td><?php echo $entry['teacher_name']; ?></td>
                                        <td><?php echo ucfirst($entry['day_of_week']); ?></td>
                                        <td>
                                            <?php echo date('g:i A', strtotime($entry['start_time'])); ?> - 
                                            <?php echo date('g:i A', strtotime($entry['end_time'])); ?>
                                        </td>
                                        <td><?php echo $entry['room'] ?: 'N/A'; ?></td>
                                        <td><?php echo $entry['session_name']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $entry['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($entry['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="timetable.php?action=edit&id=<?php echo $entry['id']; ?>" 
                                                   class="btn btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="timetable.php?toggle_status=<?php echo $entry['id']; ?>" 
                                                   class="btn btn-outline-<?php echo $entry['status'] == 'active' ? 'warning' : 'success'; ?>">
                                                    <i class="fas fa-<?php echo $entry['status'] == 'active' ? 'pause' : 'play'; ?>"></i>
                                                </a>
                                                <a href="timetable.php?delete=<?php echo $entry['id']; ?>" 
                                                   class="btn btn-outline-danger" 
                                                   onclick="return confirm('Are you sure you want to delete this timetable entry?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Timetable Modal -->
    <div class="modal fade" id="addTimetableModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Timetable Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="addTimetableForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add_timetable">
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Class *</label>
                                <select class="form-select" name="class_id" id="add_class_id" required onchange="loadClassSubjectsAndTeachers(this.value, 'add')">
                                    <option value="">Select Class</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>">
                                            <?php echo $class['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Subject *</label>
                                <select class="form-select" name="subject_id" id="add_subject_id" required disabled>
                                    <option value="">First select a class</option>
                                </select>
                                <div class="loading-spinner" id="subject_loading">
                                    <i class="fas fa-spinner fa-spin"></i> Loading subjects...
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Teacher *</label>
                                <select class="form-select" name="teacher_id" id="add_teacher_id" required disabled>
                                    <option value="">First select a class</option>
                                </select>
                                <div class="loading-spinner" id="teacher_loading">
                                    <i class="fas fa-spinner fa-spin"></i> Loading teachers...
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Academic Session *</label>
                                <select class="form-select" name="session_id" required>
                                    <option value="">Select Session</option>
                                    <?php foreach ($sessions as $session): ?>
                                        <option value="<?php echo $session['id']; ?>">
                                            <?php echo $session['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Day of Week *</label>
                                <select class="form-select" name="day_of_week" required>
                                    <option value="monday">Monday</option>
                                    <option value="tuesday">Tuesday</option>
                                    <option value="wednesday">Wednesday</option>
                                    <option value="thursday">Thursday</option>
                                    <option value="friday">Friday</option>
                                    <option value="saturday">Saturday</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Start Time *</label>
                                <input type="time" class="form-control" name="start_time" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">End Time *</label>
                                <input type="time" class="form-control" name="end_time" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Room</label>
                            <input type="text" class="form-control" name="room" placeholder="e.g., Room 101">
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Please select a class first to see available subjects and teachers for that class.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="submitBtn" disabled>Add Timetable Entry</button>
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

        // Existing AJAX functions for timetable
        function loadClassSubjectsAndTeachers(classId, formType) {
            if (!classId) {
                // Reset dropdowns if no class selected
                document.getElementById(formType + '_subject_id').innerHTML = '<option value="">First select a class</option>';
                document.getElementById(formType + '_teacher_id').innerHTML = '<option value="">First select a class</option>';
                document.getElementById(formType + '_subject_id').disabled = true;
                document.getElementById(formType + '_teacher_id').disabled = true;
                
                if (formType === 'add') {
                    document.getElementById('submitBtn').disabled = true;
                }
                return;
            }

            // Show loading spinners
            if (formType === 'add') {
                document.getElementById('subject_loading').style.display = 'block';
                document.getElementById('teacher_loading').style.display = 'block';
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

                // Update teachers dropdown
                const teacherSelect = document.getElementById(formType + '_teacher_id');
                teacherSelect.innerHTML = '<option value="">Select Teacher</option>';
                
                if (data.teachers && data.teachers.length > 0) {
                    data.teachers.forEach(teacher => {
                        const option = document.createElement('option');
                        option.value = teacher.id;
                        option.textContent = teacher.full_name;
                        teacherSelect.appendChild(option);
                    });
                    teacherSelect.disabled = false;
                } else {
                    teacherSelect.innerHTML = '<option value="">No teachers found for this class</option>';
                    teacherSelect.disabled = true;
                }

                // Hide loading spinners and enable submit button if both dropdowns have options
                if (formType === 'add') {
                    document.getElementById('subject_loading').style.display = 'none';
                    document.getElementById('teacher_loading').style.display = 'none';
                    
                    if (data.subjects && data.subjects.length > 0 && data.teachers && data.teachers.length > 0) {
                        document.getElementById('submitBtn').disabled = false;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (formType === 'add') {
                    document.getElementById('subject_loading').style.display = 'none';
                    document.getElementById('teacher_loading').style.display = 'none';
                }
                
                const subjectSelect = document.getElementById(formType + '_subject_id');
                const teacherSelect = document.getElementById(formType + '_teacher_id');
                
                subjectSelect.innerHTML = '<option value="">Error loading subjects</option>';
                teacherSelect.innerHTML = '<option value="">Error loading teachers</option>';
                subjectSelect.disabled = true;
                teacherSelect.disabled = true;
                
                if (formType === 'add') {
                    document.getElementById('submitBtn').disabled = true;
                }
            });
        }

        // Initialize the form when modal is shown
        document.getElementById('addTimetableModal').addEventListener('show.bs.modal', function () {
            // Reset form
            document.getElementById('addTimetableForm').reset();
            document.getElementById('add_subject_id').innerHTML = '<option value="">First select a class</option>';
            document.getElementById('add_teacher_id').innerHTML = '<option value="">First select a class</option>';
            document.getElementById('add_subject_id').disabled = true;
            document.getElementById('add_teacher_id').disabled = true;
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('subject_loading').style.display = 'none';
            document.getElementById('teacher_loading').style.display = 'none';
        });

        // If editing, load the data for the selected class
        <?php if ($action == 'edit' && $timetable): ?>
            document.addEventListener('DOMContentLoaded', function() {
                // The data is already loaded server-side for edit form
                document.getElementById('edit_subject_id').disabled = false;
                document.getElementById('edit_teacher_id').disabled = false;
            });
        <?php endif; ?>
    </script>
</body>
</html>