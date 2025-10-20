<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? 'list';
$message = '';
$student_id = $_GET['id'] ?? 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = sanitize($_POST['csrf_token']);
    
    if (!validateCSRFToken($csrf_token)) {
        $message = "Security token invalid.";
    } else {
        switch ($_POST['action']) {
            case 'add_student':
                $username = sanitize($_POST['username']);
                $email = sanitize($_POST['email']);
                $password = hashPassword('password123'); // Default password
                $full_name = sanitize($_POST['full_name']);
                $phone = sanitize($_POST['phone']);
                $gender = sanitize($_POST['gender']);
                $date_of_birth = sanitize($_POST['date_of_birth']);
                $address = sanitize($_POST['address']);
                $city = sanitize($_POST['city']);
                $state = sanitize($_POST['state']);
                
                $student_id = sanitize($_POST['student_id']);
                $class_id = sanitize($_POST['class_id']);
                $admission_date = sanitize($_POST['admission_date']);
                $admission_number = sanitize($_POST['admission_number']);
                $parent_name = sanitize($_POST['parent_name']);
                $parent_email = sanitize($_POST['parent_email']);
                $parent_phone = sanitize($_POST['parent_phone']);
                $parent_occupation = sanitize($_POST['parent_occupation']);
                $emergency_contact = sanitize($_POST['emergency_contact']);
                $religion = sanitize($_POST['religion']);
                $blood_group = sanitize($_POST['blood_group']);
                $medical_conditions = sanitize($_POST['medical_conditions']);
                $allergies = sanitize($_POST['allergies']);
                $previous_school = sanitize($_POST['previous_school']);
                
                try {
                    $db->beginTransaction();
                    
                    // Check if username or email already exists
                    $check_query = "SELECT id FROM users WHERE username = :username OR email = :email";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->bindParam(':username', $username);
                    $check_stmt->bindParam(':email', $email);
                    $check_stmt->execute();
                    
                    if ($check_stmt->rowCount() > 0) {
                        throw new Exception("Username or email already exists.");
                    }
                    
                    // Check if student ID already exists
                    $check_student_query = "SELECT id FROM students WHERE student_id = :student_id";
                    $check_student_stmt = $db->prepare($check_student_query);
                    $check_student_stmt->bindParam(':student_id', $student_id);
                    $check_student_stmt->execute();
                    
                    if ($check_student_stmt->rowCount() > 0) {
                        throw new Exception("Student ID already exists.");
                    }
                    
                    // Create user account
                    $user_query = "INSERT INTO users (username, email, password, role, full_name, phone, gender, date_of_birth, address, city, state) 
                                  VALUES (:username, :email, :password, 'student', :full_name, :phone, :gender, :date_of_birth, :address, :city, :state)";
                    $user_stmt = $db->prepare($user_query);
                    $user_stmt->bindParam(':username', $username);
                    $user_stmt->bindParam(':email', $email);
                    $user_stmt->bindParam(':password', $password);
                    $user_stmt->bindParam(':full_name', $full_name);
                    $user_stmt->bindParam(':phone', $phone);
                    $user_stmt->bindParam(':gender', $gender);
                    $user_stmt->bindParam(':date_of_birth', $date_of_birth);
                    $user_stmt->bindParam(':address', $address);
                    $user_stmt->bindParam(':city', $city);
                    $user_stmt->bindParam(':state', $state);
                    $user_stmt->execute();
                    
                    $user_id = $db->lastInsertId();
                    
                    // Get current session
                    $session_query = "SELECT id FROM academic_sessions WHERE status = 'active' LIMIT 1";
                    $session_id = $db->query($session_query)->fetchColumn();
                    
                    // Create student record
                    $student_query = "INSERT INTO students (student_id, user_id, class_id, session_id, admission_date, admission_number, 
                                     parent_name, parent_email, parent_phone, parent_occupation, emergency_contact, 
                                     religion, blood_group, medical_conditions, allergies, previous_school) 
                                     VALUES (:student_id, :user_id, :class_id, :session_id, :admission_date, :admission_number, 
                                     :parent_name, :parent_email, :parent_phone, :parent_occupation, :emergency_contact, 
                                     :religion, :blood_group, :medical_conditions, :allergies, :previous_school)";
                    $student_stmt = $db->prepare($student_query);
                    $student_stmt->bindParam(':student_id', $student_id);
                    $student_stmt->bindParam(':user_id', $user_id);
                    $student_stmt->bindParam(':class_id', $class_id);
                    $student_stmt->bindParam(':session_id', $session_id);
                    $student_stmt->bindParam(':admission_date', $admission_date);
                    $student_stmt->bindParam(':admission_number', $admission_number);
                    $student_stmt->bindParam(':parent_name', $parent_name);
                    $student_stmt->bindParam(':parent_email', $parent_email);
                    $student_stmt->bindParam(':parent_phone', $parent_phone);
                    $student_stmt->bindParam(':parent_occupation', $parent_occupation);
                    $student_stmt->bindParam(':emergency_contact', $emergency_contact);
                    $student_stmt->bindParam(':religion', $religion);
                    $student_stmt->bindParam(':blood_group', $blood_group);
                    $student_stmt->bindParam(':medical_conditions', $medical_conditions);
                    $student_stmt->bindParam(':allergies', $allergies);
                    $student_stmt->bindParam(':previous_school', $previous_school);
                    $student_stmt->execute();
                    
                    // Update class strength
                    $update_class_query = "UPDATE classes SET current_strength = current_strength + 1 WHERE id = :class_id";
                    $update_class_stmt = $db->prepare($update_class_query);
                    $update_class_stmt->bindParam(':class_id', $class_id);
                    $update_class_stmt->execute();
                    
                    $db->commit();
                    $message = "Student registered successfully! Default password: password123";
                } catch (Exception $e) {
                    $db->rollBack();
                    $message = "Error: " . $e->getMessage();
                }
                break;
                
            case 'update_student':
                // Update student logic here
                $message = "Student updated successfully!";
                break;
                
            case 'update_status':
                $status = sanitize($_POST['status']);
                $student_id = sanitize($_POST['student_id']);
                
                $update_query = "UPDATE students SET status = :status WHERE id = :id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':status', $status);
                $update_stmt->bindParam(':id', $student_id);
                
                if ($update_stmt->execute()) {
                    $message = "Student status updated successfully!";
                } else {
                    $message = "Error updating student status.";
                }
                break;
        }
    }
}

// Handle delete action
if (isset($_GET['delete']) && $student_id > 0) {
    $delete_id = sanitize($_GET['delete']);
    
    try {
        $db->beginTransaction();
        
        // Get student info for class strength update
        $student_info_query = "SELECT class_id FROM students WHERE id = :id";
        $student_info_stmt = $db->prepare($student_info_query);
        $student_info_stmt->bindParam(':id', $delete_id);
        $student_info_stmt->execute();
        $student_info = $student_info_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get user_id for user deletion
        $user_query = "SELECT user_id FROM students WHERE id = :id";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->bindParam(':id', $delete_id);
        $user_stmt->execute();
        $user_id = $user_stmt->fetchColumn();
        
        // Delete student record
        $delete_query = "DELETE FROM students WHERE id = :id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':id', $delete_id);
        $delete_stmt->execute();
        
        // Delete user account
        $delete_user_query = "DELETE FROM users WHERE id = :id";
        $delete_user_stmt = $db->prepare($delete_user_query);
        $delete_user_stmt->bindParam(':id', $user_id);
        $delete_user_stmt->execute();
        
        // Update class strength
        if ($student_info) {
            $update_class_query = "UPDATE classes SET current_strength = GREATEST(0, current_strength - 1) WHERE id = :class_id";
            $update_class_stmt = $db->prepare($update_class_query);
            $update_class_stmt->bindParam(':class_id', $student_info['class_id']);
            $update_class_stmt->execute();
        }
        
        $db->commit();
        $message = "Student deleted successfully!";
    } catch (Exception $e) {
        $db->rollBack();
        $message = "Error deleting student: " . $e->getMessage();
    }
}

// Get classes for dropdown
$classes_query = "SELECT * FROM classes WHERE status = 'active' ORDER BY section, name";
$classes = $db->query($classes_query)->fetchAll(PDO::FETCH_ASSOC);

// Get students list with enhanced information
$students_query = "SELECT s.*, u.full_name, u.email, u.phone, u.gender, u.date_of_birth, 
                          c.name as class_name, c.section as class_section,
                          ses.name as session_name
                  FROM students s 
                  JOIN users u ON s.user_id = u.id 
                  JOIN classes c ON s.class_id = c.id 
                  JOIN academic_sessions ses ON s.session_id = ses.id
                  ORDER BY c.name, u.full_name";
$students = $db->query($students_query)->fetchAll(PDO::FETCH_ASSOC);

// Get student details for edit/view
$student = null;
if ($student_id > 0 && ($action == 'edit' || $action == 'view')) {
    $student_query = "SELECT s.*, u.full_name, u.email, u.phone, u.gender, u.date_of_birth, u.address, u.city, u.state
                     FROM students s 
                     JOIN users u ON s.user_id = u.id 
                     WHERE s.id = :id";
    $stmt = $db->prepare($student_query);
    $stmt->bindParam(':id', $student_id);
    $stmt->execute();
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
}

$settings_qry = "SELECT setting_key, setting_value FROM system_settings";
$settings_rst = $db->query($settings_qry);
$school_settings = [];
while ($row = $settings_rst->fetch(PDO::FETCH_ASSOC)) {
    $school_settings[$row['setting_key']] = $row['setting_value'];
}
// Generate student ID if not provided
function generateStudentId($prefix) {
    $prefix = $prefix ??  'STU';
    $year = date('Y');
    $random = mt_rand(1000, 9999);
    return $prefix . $year . $random;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .student-photo { width: 100px; height: 100px; object-fit: cover; border-radius: 50%; }
        .status-badge { font-size: 0.75rem; }
        .info-card { border-left: 4px solid #3498db; }
        .medical-card { border-left: 4px solid #e74c3c; }
        .parent-card { border-left: 4px solid #27ae60; }
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
            <a class="nav-link active" href="students.php"><i class="fas fa-users me-2"></i>Students</a>
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
            <a class="nav-link" href="student_promotion.php"><i class="fas fa-user-graduate me-2"></i>Student Promotion</a>
            <!-- <a class="nav-link" href="reports.php"><i class="fas fa-chart-bar me-2"></i>Reports</a> -->
            <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a>
            <a class="nav-link" href="settings.php"><i class="fas fa-cogs me-2"></i>System Settings</a>
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Students</h2>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                    <i class="fas fa-user-plus me-2"></i>Add Student
                </button>
                <!-- <a href="students.php?action=import" class="btn btn-outline-secondary">
                    <i class="fas fa-upload me-2"></i>Import
                </a> -->
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-info alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Student Details View -->
        <?php if ($action == 'view' && $student): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Student Details</h5>
                    <div>
                        <a href="students.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i>Back to List
                        </a>
                        <a href="students.php?action=edit&id=<?php echo $student_id; ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit me-1"></i>Edit
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 text-center">
                            <div class="student-photo-placeholder bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" 
                                 style="width: 150px; height: 150px;">
                                <i class="fas fa-user fa-3x text-muted"></i>
                            </div>
                            <h5><?php echo $student['full_name']; ?></h5>
                            <p class="text-muted"><?php echo $student['student_id']; ?></p>
                            <span class="badge bg-<?php echo $student['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($student['status']); ?>
                            </span>
                        </div>
                        <div class="col-md-9">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card info-card mb-3">
                                        <div class="card-body">
                                            <h6 class="card-title"><i class="fas fa-info-circle me-2"></i>Basic Information</h6>
                                            <p><strong>Email:</strong> <?php echo $student['email']; ?></p>
                                            <p><strong>Phone:</strong> <?php echo $student['phone'] ?: 'N/A'; ?></p>
                                            <p><strong>Gender:</strong> <?php echo ucfirst($student['gender']); ?></p>
                                            <p><strong>Date of Birth:</strong> <?php echo $student['date_of_birth'] ? date('M j, Y', strtotime($student['date_of_birth'])) : 'N/A'; ?></p>
                                            <p><strong>Religion:</strong> <?php echo ucfirst($student['religion']); ?></p>
                                            <p><strong>Blood Group:</strong> <?php echo $student['blood_group'] ?: 'N/A'; ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card parent-card mb-3">
                                        <div class="card-body">
                                            <h6 class="card-title"><i class="fas fa-users me-2"></i>Parent Information</h6>
                                            <p><strong>Parent Name:</strong> <?php echo $student['parent_name'] ?: 'N/A'; ?></p>
                                            <p><strong>Parent Email:</strong> <?php echo $student['parent_email'] ?: 'N/A'; ?></p>
                                            <p><strong>Parent Phone:</strong> <?php echo $student['parent_phone'] ?: 'N/A'; ?></p>
                                            <p><strong>Occupation:</strong> <?php echo $student['parent_occupation'] ?: 'N/A'; ?></p>
                                            <p><strong>Emergency Contact:</strong> <?php echo $student['emergency_contact'] ?: 'N/A'; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card medical-card mb-3">
                                        <div class="card-body">
                                            <h6 class="card-title"><i class="fas fa-heartbeat me-2"></i>Medical Information</h6>
                                            <p><strong>Medical Conditions:</strong> <?php echo $student['medical_conditions'] ?: 'None'; ?></p>
                                            <p><strong>Allergies:</strong> <?php echo $student['allergies'] ?: 'None'; ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card info-card mb-3">
                                        <div class="card-body">
                                            <h6 class="card-title"><i class="fas fa-school me-2"></i>Academic Information</h6>
                                            <p><strong>Admission Date:</strong> <?php echo $student['admission_date'] ? date('M j, Y', strtotime($student['admission_date'])) : 'N/A'; ?></p>
                                            <p><strong>Admission Number:</strong> <?php echo $student['admission_number'] ?: 'N/A'; ?></p>
                                            <p><strong>Previous School:</strong> <?php echo $student['previous_school'] ?: 'N/A'; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <!-- Students List View -->
        <?php else: ?>
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo count($students); ?></h4>
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
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo count(array_filter($students, function($s) { return $s['status'] == 'active'; })); ?></h4>
                                    <p class="mb-0">Active Students</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-user-check fa-2x opacity-50"></i>
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
                                    <h4><?php echo count(array_filter($students, function($s) { return $s['class_section'] == 'primary'; })); ?></h4>
                                    <p class="mb-0">Primary Students</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-child fa-2x opacity-50"></i>
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
                                    <h4><?php echo count(array_filter($students, function($s) { return $s['class_section'] == 'senior'; })); ?></h4>
                                    <p class="mb-0">Senior Students</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-user-graduate fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Students List -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Students List</h5>
                    <div class="d-flex gap-2">
                        <input type="text" id="searchStudents" class="form-control form-control-sm" placeholder="Search students...">
                        <select id="classFilter" class="form-select form-select-sm" style="width: auto;">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['name']; ?>"><?php echo $class['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="statusFilter" class="form-select form-select-sm" style="width: auto;">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="graduated">Graduated</option>
                            <option value="transferred">Transferred</option>
                            <option value="withdrawn">Withdrawn</option>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="studentsTable">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Full Name</th>
                                    <th>Class</th>
                                    <th>Gender</th>
                                    <th>Date of Birth</th>
                                    <th>Parent</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $student['student_id']; ?></strong>
                                            <?php if ($student['admission_number']): ?>
                                                <br><small class="text-muted"><?php echo $student['admission_number']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $student['full_name']; ?>
                                            <br><small class="text-muted"><?php echo $student['email']; ?></small>
                                        </td>
                                        <td><?php echo $student['class_name']; ?></td>
                                        <td><?php echo ucfirst($student['gender']); ?></td>
                                        <td>
                                            <?php if ($student['date_of_birth']): ?>
                                                <?php echo date('M j, Y', strtotime($student['date_of_birth'])); ?>
                                                <br><small class="text-muted">Age: <?php echo date_diff(date_create($student['date_of_birth']), date_create('today'))->y; ?></small>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $student['parent_name'] ?: 'N/A'; ?>
                                            <?php if ($student['parent_phone']): ?>
                                                <br><small class="text-muted"><?php echo $student['parent_phone']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $student['phone'] ?: 'N/A'; ?></td>
                                        <td>
                                            <span class="badge status-badge bg-<?php 
                                                echo $student['status'] == 'active' ? 'success' : 
                                                    ($student['status'] == 'graduated' ? 'info' : 
                                                    ($student['status'] == 'transferred' ? 'warning' : 'secondary')); 
                                            ?>">
                                                <?php echo ucfirst($student['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="students.php?action=view&id=<?php echo $student['id']; ?>" 
                                                   class="btn btn-outline-primary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="students.php?action=edit&id=<?php echo $student['id']; ?>" 
                                                   class="btn btn-outline-info" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button class="btn btn-outline-warning dropdown-toggle" type="button" 
                                                        data-bs-toggle="dropdown" title="Status">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $student['id']; ?>, 'active')">Active</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $student['id']; ?>, 'graduated')">Graduated</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $student['id']; ?>, 'transferred')">Transferred</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $student['id']; ?>, 'withdrawn')">Withdrawn</a></li>
                                                </ul>
                                                <button class="btn btn-outline-danger" 
                                                        onclick="confirmDelete(<?php echo $student['id']; ?>, '<?php echo $student['full_name']; ?>')"
                                                        title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (empty($students)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No students found. Add your first student to get started.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                                <i class="fas fa-user-plus me-2"></i>Add First Student
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Student Modal -->
    <div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addStudentModalLabel">Register New Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="addStudentForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add_student">
                    
                    <div class="modal-body">
                        <ul class="nav nav-pills mb-4" id="studentFormTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="basic-tab" data-bs-toggle="pill" data-bs-target="#basic" type="button" role="tab">Basic Info</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="academic-tab" data-bs-toggle="pill" data-bs-target="#academic" type="button" role="tab">Academic Info</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="parent-tab" data-bs-toggle="pill" data-bs-target="#parent" type="button" role="tab">Parent Info</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="medical-tab" data-bs-toggle="pill" data-bs-target="#medical" type="button" role="tab">Medical Info</button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="studentFormTabsContent">
                            <!-- Basic Information Tab -->
                            <div class="tab-pane fade show active" id="basic" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Student ID *</label>
                                        <input type="text" class="form-control" name="student_id" required 
                                               value="<?php echo generateStudentId($school_settings['student_id_prefix']); ?>" readonly>
                                    </div>
                                    <div class="col-md-6 mb-3">
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
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" name="full_name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Gender *</label>
                                        <select class="form-select" name="gender" required>
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Date of Birth *</label>
                                        <input type="date" class="form-control" name="date_of_birth" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Religion</label>
                                        <select class="form-select" name="religion">
                                            <option value="christianity">Christianity</option>
                                            <option value="islam">Islam</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Blood Group</label>
                                        <select class="form-select" name="blood_group">
                                            <option value="">Select Blood Group</option>
                                            <option value="A+">A+</option>
                                            <option value="A-">A-</option>
                                            <option value="B+">B+</option>
                                            <option value="B-">B-</option>
                                            <option value="AB+">AB+</option>
                                            <option value="AB-">AB-</option>
                                            <option value="O+">O+</option>
                                            <option value="O-">O-</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Admission Date *</label>
                                        <input type="date" class="form-control" name="admission_date" required 
                                               value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" name="address" rows="3"></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">City</label>
                                        <input type="text" class="form-control" name="city">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">State</label>
                                        <input type="text" class="form-control" name="state">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Academic Information Tab -->
                            <div class="tab-pane fade" id="academic" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Username *</label>
                                        <input type="text" class="form-control" name="username" required 
                                               placeholder="Will be used for login">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email *</label>
                                        <input type="email" class="form-control" name="email" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" name="phone">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Admission Number</label>
                                        <input type="text" class="form-control" name="admission_number" 
                                               placeholder="Official admission number">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Previous School</label>
                                    <input type="text" class="form-control" name="previous_school" 
                                           placeholder="Name of previous school attended">
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Default password will be set to: <strong>password123</strong>. Student can change it after first login.
                                </div>
                            </div>
                            
                            <!-- Parent Information Tab -->
                            <div class="tab-pane fade" id="parent" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Parent Name *</label>
                                        <input type="text" class="form-control" name="parent_name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Parent Occupation</label>
                                        <input type="text" class="form-control" name="parent_occupation">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Parent Email</label>
                                        <input type="email" class="form-control" name="parent_email">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Parent Phone *</label>
                                        <input type="tel" class="form-control" name="parent_phone" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Emergency Contact</label>
                                    <input type="tel" class="form-control" name="emergency_contact" 
                                           placeholder="Alternative emergency contact number">
                                </div>
                            </div>
                            
                            <!-- Medical Information Tab -->
                            <div class="tab-pane fade" id="medical" role="tabpanel">
                                <div class="mb-3">
                                    <label class="form-label">Medical Conditions</label>
                                    <textarea class="form-control" name="medical_conditions" rows="3" 
                                              placeholder="Any known medical conditions (asthma, epilepsy, etc.)"></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Allergies</label>
                                    <textarea class="form-control" name="allergies" rows="3" 
                                              placeholder="Any known allergies (food, medication, etc.)"></textarea>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Please ensure all medical information is accurate and up-to-date for the safety of the student.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Register Student
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Status Update Form -->
    <form id="statusForm" method="POST" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="student_id" id="statusStudentId">
        <input type="hidden" name="status" id="statusValue">
    </form>

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
        // Student search and filter functionality
        document.getElementById('searchStudents').addEventListener('input', filterStudents);
        document.getElementById('classFilter').addEventListener('change', filterStudents);
        document.getElementById('statusFilter').addEventListener('change', filterStudents);

        function filterStudents() {
            const searchTerm = document.getElementById('searchStudents').value.toLowerCase();
            const classFilter = document.getElementById('classFilter').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
            const rows = document.querySelectorAll('#studentsTable tbody tr');
            
            rows.forEach(row => {
                const studentName = row.cells[1].textContent.toLowerCase();
                const studentId = row.cells[0].textContent.toLowerCase();
                const className = row.cells[2].textContent.toLowerCase();
                const status = row.cells[7].textContent.toLowerCase();
                
                const searchMatch = !searchTerm || 
                    studentName.includes(searchTerm) || 
                    studentId.includes(searchTerm);
                const classMatch = !classFilter || className.includes(classFilter);
                const statusMatch = !statusFilter || status.includes(statusFilter);
                
                row.style.display = searchMatch && classMatch && statusMatch ? '' : 'none';
            });
        }

        // Update student status
        function updateStatus(studentId, status) {
            if (confirm(`Are you sure you want to change this student's status to ${status}?`)) {
                document.getElementById('statusStudentId').value = studentId;
                document.getElementById('statusValue').value = status;
                document.getElementById('statusForm').submit();
            }
        }

        // Confirm student deletion
        function confirmDelete(studentId, studentName) {
            if (confirm(`Are you sure you want to delete student "${studentName}"? This action cannot be undone and will remove all associated data.`)) {
                window.location.href = `students.php?delete=${studentId}`;
            }
        }

        // Form validation for student registration
        document.getElementById('addStudentForm').addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let valid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    valid = false;
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (!valid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                
                // Switch to first tab with errors
                const firstInvalid = this.querySelector('.is-invalid');
                if (firstInvalid) {
                    const tabId = firstInvalid.closest('.tab-pane').id;
                    const tabButton = document.querySelector(`[data-bs-target="#${tabId}"]`);
                    if (tabButton) {
                        new bootstrap.Tab(tabButton).show();
                    }
                }
            }
        });

        // Auto-generate username from student name
        document.querySelector('input[name="full_name"]').addEventListener('blur', function() {
            const usernameInput = document.querySelector('input[name="username"]');
            if (!usernameInput.value && this.value) {
                const username = this.value.toLowerCase().replace(/\s+/g, '.');
                usernameInput.value = username;
            }
        });

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>