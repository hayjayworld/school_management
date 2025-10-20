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
$staff_id = $_GET['id'] ?? 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = sanitize($_POST['csrf_token']);
    
    if (!validateCSRFToken($csrf_token)) {
        $message = "Security token invalid.";
    } else {
        switch ($_POST['action']) {
            case 'add_staff':
                $username = sanitize($_POST['username']);
                $email = sanitize($_POST['email']);
                $password = hashPassword('password123');
                $full_name = sanitize($_POST['full_name']);
                $phone = sanitize($_POST['phone']);
                $gender = sanitize($_POST['gender']);
                $date_of_birth = sanitize($_POST['date_of_birth']);
                $address = sanitize($_POST['address']);
                $city = sanitize($_POST['city']);
                $state = sanitize($_POST['state']);
                $role = sanitize($_POST['role']);
                
                $staff_id = sanitize($_POST['staff_id']);
                $qualification = sanitize($_POST['qualification']);
                $specialization = sanitize($_POST['specialization']);
                $employment_date = sanitize($_POST['employment_date']);
                $salary_grade = sanitize($_POST['salary_grade']);
                $bank_name = sanitize($_POST['bank_name']);
                $account_number = sanitize($_POST['account_number']);
                $tax_id = sanitize($_POST['tax_id']);
                $emergency_contact = sanitize($_POST['emergency_contact']);
                $marital_status = sanitize($_POST['marital_status']);
                
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
                    
                    // Check if staff ID already exists
                    $check_staff_query = "SELECT id FROM staff WHERE staff_id = :staff_id";
                    $check_staff_stmt = $db->prepare($check_staff_query);
                    $check_staff_stmt->bindParam(':staff_id', $staff_id);
                    $check_staff_stmt->execute();
                    
                    if ($check_staff_stmt->rowCount() > 0) {
                        throw new Exception("Staff ID already exists.");
                    }
                    
                    // Create user account
                    $user_query = "INSERT INTO users (username, email, password, role, full_name, phone, gender, date_of_birth, address, city, state) 
                                  VALUES (:username, :email, :password, :role, :full_name, :phone, :gender, :date_of_birth, :address, :city, :state)";
                    $user_stmt = $db->prepare($user_query);
                    $user_stmt->bindParam(':username', $username);
                    $user_stmt->bindParam(':email', $email);
                    $user_stmt->bindParam(':password', $password);
                    $user_stmt->bindParam(':role', $role);
                    $user_stmt->bindParam(':full_name', $full_name);
                    $user_stmt->bindParam(':phone', $phone);
                    $user_stmt->bindParam(':gender', $gender);
                    $user_stmt->bindParam(':date_of_birth', $date_of_birth);
                    $user_stmt->bindParam(':address', $address);
                    $user_stmt->bindParam(':city', $city);
                    $user_stmt->bindParam(':state', $state);
                    $user_stmt->execute();
                    
                    $user_id = $db->lastInsertId();
                    
                    // Create staff record
                    $staff_query = "INSERT INTO staff (user_id, staff_id, qualification, specialization, employment_date, 
                                     salary_grade, bank_name, account_number, tax_id, emergency_contact, marital_status) 
                                     VALUES (:user_id, :staff_id, :qualification, :specialization, :employment_date, 
                                     :salary_grade, :bank_name, :account_number, :tax_id, :emergency_contact, :marital_status)";
                    $staff_stmt = $db->prepare($staff_query);
                    $staff_stmt->bindParam(':user_id', $user_id);
                    $staff_stmt->bindParam(':staff_id', $staff_id);
                    $staff_stmt->bindParam(':qualification', $qualification);
                    $staff_stmt->bindParam(':specialization', $specialization);
                    $staff_stmt->bindParam(':employment_date', $employment_date);
                    $staff_stmt->bindParam(':salary_grade', $salary_grade);
                    $staff_stmt->bindParam(':bank_name', $bank_name);
                    $staff_stmt->bindParam(':account_number', $account_number);
                    $staff_stmt->bindParam(':tax_id', $tax_id);
                    $staff_stmt->bindParam(':emergency_contact', $emergency_contact);
                    $staff_stmt->bindParam(':marital_status', $marital_status);
                    $staff_stmt->execute();
                    
                    $db->commit();
                    $message = "Staff member registered successfully! Default password: password123";
                } catch (Exception $e) {
                    $db->rollBack();
                    $message = "Error: " . $e->getMessage();
                }
                break;
                
            case 'update_staff':
                $user_id = sanitize($_POST['user_id']);
                $full_name = sanitize($_POST['full_name']);
                $email = sanitize($_POST['email']);
                $phone = sanitize($_POST['phone']);
                $gender = sanitize($_POST['gender']);
                $date_of_birth = sanitize($_POST['date_of_birth']);
                $address = sanitize($_POST['address']);
                $city = sanitize($_POST['city']);
                $state = sanitize($_POST['state']);
                $role = sanitize($_POST['role']);
                
                $qualification = sanitize($_POST['qualification']);
                $specialization = sanitize($_POST['specialization']);
                $employment_date = sanitize($_POST['employment_date']);
                $salary_grade = sanitize($_POST['salary_grade']);
                $bank_name = sanitize($_POST['bank_name']);
                $account_number = sanitize($_POST['account_number']);
                $tax_id = sanitize($_POST['tax_id']);
                $emergency_contact = sanitize($_POST['emergency_contact']);
                $marital_status = sanitize($_POST['marital_status']);
                
                try {
                    $db->beginTransaction();
                    
                    // Update user account
                    $user_query = "UPDATE users SET full_name = :full_name, email = :email, phone = :phone, 
                                  gender = :gender, date_of_birth = :date_of_birth, address = :address, 
                                  city = :city, state = :state, role = :role 
                                  WHERE id = :user_id";
                    $user_stmt = $db->prepare($user_query);
                    $user_stmt->bindParam(':full_name', $full_name);
                    $user_stmt->bindParam(':email', $email);
                    $user_stmt->bindParam(':phone', $phone);
                    $user_stmt->bindParam(':gender', $gender);
                    $user_stmt->bindParam(':date_of_birth', $date_of_birth);
                    $user_stmt->bindParam(':address', $address);
                    $user_stmt->bindParam(':city', $city);
                    $user_stmt->bindParam(':state', $state);
                    $user_stmt->bindParam(':role', $role);
                    $user_stmt->bindParam(':user_id', $user_id);
                    $user_stmt->execute();
                    
                    // Update staff record
                    $staff_query = "UPDATE staff SET qualification = :qualification, specialization = :specialization, 
                                   employment_date = :employment_date, salary_grade = :salary_grade, 
                                   bank_name = :bank_name, account_number = :account_number, tax_id = :tax_id, 
                                   emergency_contact = :emergency_contact, marital_status = :marital_status 
                                   WHERE user_id = :user_id";
                    $staff_stmt = $db->prepare($staff_query);
                    $staff_stmt->bindParam(':qualification', $qualification);
                    $staff_stmt->bindParam(':specialization', $specialization);
                    $staff_stmt->bindParam(':employment_date', $employment_date);
                    $staff_stmt->bindParam(':salary_grade', $salary_grade);
                    $staff_stmt->bindParam(':bank_name', $bank_name);
                    $staff_stmt->bindParam(':account_number', $account_number);
                    $staff_stmt->bindParam(':tax_id', $tax_id);
                    $staff_stmt->bindParam(':emergency_contact', $emergency_contact);
                    $staff_stmt->bindParam(':marital_status', $marital_status);
                    $staff_stmt->bindParam(':user_id', $user_id);
                    $staff_stmt->execute();
                    
                    $db->commit();
                    $message = "Staff member updated successfully!";
                } catch (Exception $e) {
                    $db->rollBack();
                    $message = "Error: " . $e->getMessage();
                }
                break;
                
            case 'update_status':
                $status = sanitize($_POST['status']);
                $user_id = sanitize($_POST['user_id']);
                
                $update_query = "UPDATE users SET status = :status WHERE id = :id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':status', $status);
                $update_stmt->bindParam(':id', $user_id);
                
                if ($update_stmt->execute()) {
                    $message = "Staff status updated successfully!";
                } else {
                    $message = "Error updating staff status.";
                }
                break;
        }
    }
}

// Handle delete action
if (isset($_GET['delete']) && $staff_id > 0) {
    $delete_id = sanitize($_GET['delete']);
    
    try {
        $db->beginTransaction();
        
        // Get user_id for deletion
        $user_query = "SELECT user_id FROM staff WHERE id = :id";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->bindParam(':id', $delete_id);
        $user_stmt->execute();
        $user_id = $user_stmt->fetchColumn();
        
        // Delete staff record
        $delete_query = "DELETE FROM staff WHERE id = :id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':id', $delete_id);
        $delete_stmt->execute();
        
        // Delete user account
        $delete_user_query = "DELETE FROM users WHERE id = :id";
        $delete_user_stmt = $db->prepare($delete_user_query);
        $delete_user_stmt->bindParam(':id', $user_id);
        $delete_user_stmt->execute();
        
        $db->commit();
        $message = "Staff member deleted successfully!";
    } catch (Exception $e) {
        $db->rollBack();
        $message = "Error deleting staff member: " . $e->getMessage();
    }
}

// Get staff list with enhanced information
$staff_query = "SELECT s.*, u.full_name, u.id as user_id, u.username, u.email, u.phone, u.gender, u.date_of_birth, 
                       u.address, u.city, u.state, u.role, u.status as user_status
                FROM staff s 
                JOIN users u ON s.user_id = u.id 
                WHERE u.role IN ('admin', 'teacher')
                ORDER BY u.role, u.full_name";
$staff_members = $db->query($staff_query)->fetchAll(PDO::FETCH_ASSOC);

// Get staff details for edit/view
$staff = null;
if ($staff_id > 0 && ($action == 'edit' || $action == 'view')) {
    $staff_query = "SELECT s.*, u.id as user_id, u.full_name, u.username, u.email, u.phone, u.gender, u.date_of_birth, 
                           u.address, u.city, u.state, u.role, u.status as user_status
                    FROM staff s 
                    JOIN users u ON s.user_id = u.id 
                    WHERE s.id = :id";
    $stmt = $db->prepare($staff_query);
    $stmt->bindParam(':id', $staff_id);
    $stmt->execute();
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
}

$settings_qry = "SELECT setting_key, setting_value FROM system_settings WHERE is_public = 1";
$settings_rst = $db->query($settings_qry);
$school_settings = [];
while ($row = $settings_rst->fetch(PDO::FETCH_ASSOC)) {
    $school_settings[$row['setting_key']] = $row['setting_value'];
}

// Generate staff ID if not provided
function generateStaffId($prefix) {
    $prefix = $prefix ?? 'STAFF';
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
    <title>Manage Staff - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .staff-photo { width: 100px; height: 100px; object-fit: cover; border-radius: 50%; }
        .status-badge { font-size: 0.75rem; }
        .info-card { border-left: 4px solid #3498db; }
        .employment-card { border-left: 4px solid #27ae60; }
        .bank-card { border-left: 4px solid #e74c3c; }
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
            <a class="nav-link active" href="staff.php"><i class="fas fa-chalkboard-teacher me-2"></i>Staff</a>
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
            <h2>Manage Staff</h2>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                    <i class="fas fa-user-plus me-2"></i>Add Staff
                </button>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-info alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Staff Details View -->
        <?php if ($action == 'view' && $staff): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Staff Details</h5>
                    <div>
                        <a href="staff.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i>Back to List
                        </a>
                        <a href="staff.php?action=edit&id=<?php echo $staff_id; ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit me-1"></i>Edit
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 text-center">
                            <div class="staff-photo-placeholder bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" 
                                 style="width: 150px; height: 150px;">
                                <i class="fas fa-user-tie fa-3x text-muted"></i>
                            </div>
                            <h5><?php echo $staff['full_name']; ?></h5>
                            <p class="text-muted"><?php echo $staff['staff_id']; ?></p>
                            <span class="badge bg-<?php echo $staff['user_status'] == 'active' ? 'success' : 'secondary'; ?> me-1">
                                <?php echo ucfirst($staff['user_status']); ?>
                            </span>
                            <span class="badge bg-<?php echo $staff['role'] == 'admin' ? 'danger' : 'primary'; ?>">
                                <?php echo ucfirst($staff['role']); ?>
                            </span>
                        </div>
                        <div class="col-md-9">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card info-card mb-3">
                                        <div class="card-body">
                                            <h6 class="card-title"><i class="fas fa-info-circle me-2"></i>Personal Information</h6>
                                            <p><strong>Username:</strong> <?php echo $staff['username']; ?></p>
                                            <p><strong>Email:</strong> <?php echo $staff['email']; ?></p>
                                            <p><strong>Phone:</strong> <?php echo $staff['phone'] ?: 'N/A'; ?></p>
                                            <p><strong>Gender:</strong> <?php echo ucfirst($staff['gender']); ?></p>
                                            <p><strong>Date of Birth:</strong> <?php echo $staff['date_of_birth'] ? date('M j, Y', strtotime($staff['date_of_birth'])) : 'N/A'; ?></p>
                                            <p><strong>Marital Status:</strong> <?php echo ucfirst($staff['marital_status']); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card employment-card mb-3">
                                        <div class="card-body">
                                            <h6 class="card-title"><i class="fas fa-briefcase me-2"></i>Employment Information</h6>
                                            <p><strong>Qualification:</strong> <?php echo $staff['qualification'] ?: 'N/A'; ?></p>
                                            <p><strong>Specialization:</strong> <?php echo $staff['specialization'] ?: 'N/A'; ?></p>
                                            <p><strong>Employment Date:</strong> <?php echo $staff['employment_date'] ? date('M j, Y', strtotime($staff['employment_date'])) : 'N/A'; ?></p>
                                            <p><strong>Salary Grade:</strong> <?php echo $staff['salary_grade'] ?: 'N/A'; ?></p>
                                            <p><strong>Tax ID:</strong> <?php echo $staff['tax_id'] ?: 'N/A'; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card bank-card mb-3">
                                        <div class="card-body">
                                            <h6 class="card-title"><i class="fas fa-university me-2"></i>Bank Information</h6>
                                            <p><strong>Bank Name:</strong> <?php echo $staff['bank_name'] ?: 'N/A'; ?></p>
                                            <p><strong>Account Number:</strong> <?php echo $staff['account_number'] ?: 'N/A'; ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card info-card mb-3">
                                        <div class="card-body">
                                            <h6 class="card-title"><i class="fas fa-phone-alt me-2"></i>Contact Information</h6>
                                            <p><strong>Address:</strong> <?php echo $staff['address'] ?: 'N/A'; ?></p>
                                            <p><strong>City:</strong> <?php echo $staff['city'] ?: 'N/A'; ?></p>
                                            <p><strong>State:</strong> <?php echo $staff['state'] ?: 'N/A'; ?></p>
                                            <p><strong>Emergency Contact:</strong> <?php echo $staff['emergency_contact'] ?: 'N/A'; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <!-- Edit Staff Form -->
        <?php elseif ($action == 'edit' && $staff): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Edit Staff Member</h5>
                    <a href="staff.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Back to List
                    </a>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="update_staff">
                        <input type="hidden" name="user_id" value="<?php echo $staff['user_id']; ?>">
                        
                        <ul class="nav nav-pills mb-4" id="staffFormTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="edit-basic-tab" data-bs-toggle="pill" data-bs-target="#edit-basic" type="button" role="tab">Basic Info</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="edit-employment-tab" data-bs-toggle="pill" data-bs-target="#edit-employment" type="button" role="tab">Employment Info</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="edit-bank-tab" data-bs-toggle="pill" data-bs-target="#edit-bank" type="button" role="tab">Bank Info</button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="staffFormTabsContent">
                            <!-- Basic Information Tab -->
                            <div class="tab-pane fade show active" id="edit-basic" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" name="full_name" required 
                                               value="<?php echo $staff['full_name']; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email *</label>
                                        <input type="email" class="form-control" name="email" required 
                                               value="<?php echo $staff['email']; ?>">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Phone</label>
                                        <input type="tel" class="form-control" name="phone" 
                                               value="<?php echo $staff['phone']; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Gender *</label>
                                        <select class="form-select" name="gender" required>
                                            <option value="male" <?php echo $staff['gender'] == 'male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="female" <?php echo $staff['gender'] == 'female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="other" <?php echo $staff['gender'] == 'other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" name="date_of_birth" 
                                               value="<?php echo $staff['date_of_birth']; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Role *</label>
                                        <select class="form-select" name="role" required>
                                            <option value="teacher" <?php echo $staff['role'] == 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                                            <option value="admin" <?php echo $staff['role'] == 'admin' ? 'selected' : ''; ?>>Administrator</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" name="address" rows="3"><?php echo $staff['address']; ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">City</label>
                                        <input type="text" class="form-control" name="city" 
                                               value="<?php echo $staff['city']; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">State</label>
                                        <input type="text" class="form-control" name="state" 
                                               value="<?php echo $staff['state']; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Employment Information Tab -->
                            <div class="tab-pane fade" id="edit-employment" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Qualification</label>
                                        <input type="text" class="form-control" name="qualification" 
                                               value="<?php echo $staff['qualification']; ?>" 
                                               placeholder="e.g., B.Sc. Education, M.Ed.">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Specialization</label>
                                        <input type="text" class="form-control" name="specialization" 
                                               value="<?php echo $staff['specialization']; ?>" 
                                               placeholder="e.g., Mathematics, English Language">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Employment Date</label>
                                        <input type="date" class="form-control" name="employment_date" 
                                               value="<?php echo $staff['employment_date']; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Salary Grade</label>
                                        <input type="text" class="form-control" name="salary_grade" 
                                               value="<?php echo $staff['salary_grade']; ?>">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Tax ID</label>
                                        <input type="text" class="form-control" name="tax_id" 
                                               value="<?php echo $staff['tax_id']; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Marital Status</label>
                                        <select class="form-select" name="marital_status">
                                            <option value="single" <?php echo $staff['marital_status'] == 'single' ? 'selected' : ''; ?>>Single</option>
                                            <option value="married" <?php echo $staff['marital_status'] == 'married' ? 'selected' : ''; ?>>Married</option>
                                            <option value="divorced" <?php echo $staff['marital_status'] == 'divorced' ? 'selected' : ''; ?>>Divorced</option>
                                            <option value="widowed" <?php echo $staff['marital_status'] == 'widowed' ? 'selected' : ''; ?>>Widowed</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Emergency Contact</label>
                                    <input type="tel" class="form-control" name="emergency_contact" 
                                           value="<?php echo $staff['emergency_contact']; ?>">
                                </div>
                            </div>
                            
                            <!-- Bank Information Tab -->
                            <div class="tab-pane fade" id="edit-bank" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Bank Name</label>
                                        <input type="text" class="form-control" name="bank_name" 
                                               value="<?php echo $staff['bank_name']; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Account Number</label>
                                        <input type="text" class="form-control" name="account_number" 
                                               value="<?php echo $staff['account_number']; ?>">
                                    </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Bank information is used for salary payments. Please ensure accuracy.
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="staff.php?action=view&id=<?php echo $staff_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Staff
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        <!-- Staff List View -->
        <?php else: ?>
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo count($staff_members); ?></h4>
                                    <p class="mb-0">Total Staff</p>
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
                                    <h4><?php echo count(array_filter($staff_members, function($s) { return $s['role'] == 'teacher'; })); ?></h4>
                                    <p class="mb-0">Teachers</p>
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
                                    <h4><?php echo count(array_filter($staff_members, function($s) { return $s['role'] == 'admin'; })); ?></h4>
                                    <p class="mb-0">Administrators</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-user-shield fa-2x opacity-50"></i>
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
                                    <h4><?php echo count(array_filter($staff_members, function($s) { return $s['user_status'] == 'active'; })); ?></h4>
                                    <p class="mb-0">Active Staff</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-user-check fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Staff List -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Staff List</h5>
                    <div class="d-flex gap-2">
                        <input type="text" id="searchStaff" class="form-control form-control-sm" placeholder="Search staff...">
                        <select id="roleFilter" class="form-select form-select-sm" style="width: auto;">
                            <option value="">All Roles</option>
                            <option value="teacher">Teachers</option>
                            <option value="admin">Administrators</option>
                        </select>
                        <select id="statusFilter" class="form-select form-select-sm" style="width: auto;">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="staffTable">
                            <thead>
                                <tr>
                                    <th>Staff ID</th>
                                    <th>Full Name</th>
                                    <th>Role</th>
                                    <th>Qualification</th>
                                    <th>Specialization</th>
                                    <th>Employment Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staff_members as $staff): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $staff['staff_id']; ?></strong>
                                        </td>
                                        <td>
                                            <?php echo $staff['full_name']; ?>
                                            <br><small class="text-muted"><?php echo $staff['email']; ?></small>
                                            <?php if ($staff['phone']): ?>
                                                <br><small class="text-muted"><?php echo $staff['phone']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $staff['role'] == 'admin' ? 'danger' : 'primary'; ?>">
                                                <?php echo ucfirst($staff['role']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $staff['qualification'] ?: 'N/A'; ?></td>
                                        <td><?php echo $staff['specialization'] ?: 'N/A'; ?></td>
                                        <td>
                                            <?php if ($staff['employment_date']): ?>
                                                <?php echo date('M j, Y', strtotime($staff['employment_date'])); ?>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge status-badge bg-<?php echo $staff['user_status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($staff['user_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="staff.php?action=view&id=<?php echo $staff['id']; ?>" 
                                                   class="btn btn-outline-primary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="staff.php?action=edit&id=<?php echo $staff['id']; ?>" 
                                                   class="btn btn-outline-info" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button class="btn btn-outline-warning dropdown-toggle" type="button" 
                                                        data-bs-toggle="dropdown" title="Status">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $staff['user_id']; ?>, 'active')">Active</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $staff['user_id']; ?>, 'inactive')">Inactive</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $staff['user_id']; ?>, 'suspended')">Suspended</a></li>
                                                </ul>
                                                <button class="btn btn-outline-danger" 
                                                        onclick="confirmDelete(<?php echo $staff['id']; ?>, '<?php echo $staff['full_name']; ?>')"
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
                    
                    <?php if (empty($staff_members)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No staff members found. Add your first staff member to get started.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                                <i class="fas fa-user-plus me-2"></i>Add First Staff
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Staff Modal -->
    <div class="modal fade" id="addStaffModal" tabindex="-1" aria-labelledby="addStaffModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addStaffModalLabel">Register New Staff Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="addStaffForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add_staff">
                    
                    <div class="modal-body">
                        <ul class="nav nav-pills mb-4" id="staffFormTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="basic-tab" data-bs-toggle="pill" data-bs-target="#basic" type="button" role="tab">Basic Info</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="employment-tab" data-bs-toggle="pill" data-bs-target="#employment" type="button" role="tab">Employment Info</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="bank-tab" data-bs-toggle="pill" data-bs-target="#bank" type="button" role="tab">Bank Info</button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="staffFormTabsContent">
                            <!-- Basic Information Tab -->
                            <div class="tab-pane fade show active" id="basic" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Staff ID *</label>
                                        <input type="text" class="form-control" name="staff_id" required 
                                               value="<?php echo generateStaffId($school_settings['staff_id_prefix']); ?>" readonly>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Role *</label>
                                        <select class="form-select" name="role" required>
                                            <option value="teacher">Teacher</option>
                                            <option value="admin">Administrator</option>
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
                                        <label class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" name="date_of_birth">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Marital Status</label>
                                        <select class="form-select" name="marital_status">
                                            <option value="single">Single</option>
                                            <option value="married">Married</option>
                                            <option value="divorced">Divorced</option>
                                            <option value="widowed">Widowed</option>
                                        </select>
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
                            
                            <!-- Employment Information Tab -->
                            <div class="tab-pane fade" id="employment" role="tabpanel">
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
                                        <label class="form-label">Employment Date</label>
                                        <input type="date" class="form-control" name="employment_date" 
                                               value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Qualification</label>
                                        <input type="text" class="form-control" name="qualification" 
                                               placeholder="e.g., B.Sc. Education, M.Ed.">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Specialization</label>
                                        <input type="text" class="form-control" name="specialization" 
                                               placeholder="e.g., Mathematics, English Language">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Salary Grade</label>
                                        <input type="text" class="form-control" name="salary_grade">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Tax ID</label>
                                        <input type="text" class="form-control" name="tax_id">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Emergency Contact</label>
                                    <input type="tel" class="form-control" name="emergency_contact">
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Default password will be set to: <strong>password123</strong>. Staff can change it after first login.
                                </div>
                            </div>
                            
                            <!-- Bank Information Tab -->
                            <div class="tab-pane fade" id="bank" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Bank Name</label>
                                        <input type="text" class="form-control" name="bank_name">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Account Number</label>
                                        <input type="text" class="form-control" name="account_number">
                                    </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Bank information is used for salary payments. Please ensure accuracy.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Register Staff
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
        <input type="hidden" name="user_id" id="statusUserId">
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
        // Staff search and filter functionality
        document.getElementById('searchStaff').addEventListener('input', filterStaff);
        document.getElementById('roleFilter').addEventListener('change', filterStaff);
        document.getElementById('statusFilter').addEventListener('change', filterStaff);

        function filterStaff() {
            const searchTerm = document.getElementById('searchStaff').value.toLowerCase();
            const roleFilter = document.getElementById('roleFilter').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
            const rows = document.querySelectorAll('#staffTable tbody tr');
            
            rows.forEach(row => {
                const staffName = row.cells[1].textContent.toLowerCase();
                const staffId = row.cells[0].textContent.toLowerCase();
                const role = row.cells[2].textContent.toLowerCase();
                const status = row.cells[6].textContent.toLowerCase();
                
                const searchMatch = !searchTerm || 
                    staffName.includes(searchTerm) || 
                    staffId.includes(searchTerm);
                const roleMatch = !roleFilter || role.includes(roleFilter);
                const statusMatch = !statusFilter || status.includes(statusFilter);
                
                row.style.display = searchMatch && roleMatch && statusMatch ? '' : 'none';
            });
        }

        // Update staff status
        function updateStatus(userId, status) {
            if (confirm(`Are you sure you want to change this staff member's status to ${status}?`)) {
                document.getElementById('statusUserId').value = userId;
                document.getElementById('statusValue').value = status;
                document.getElementById('statusForm').submit();
            }
        }

        // Confirm staff deletion
        function confirmDelete(staffId, staffName) {
            if (confirm(`Are you sure you want to delete staff member "${staffName}"? This action cannot be undone and will remove all associated data.`)) {
                window.location.href = `staff.php?delete=${staffId}`;
            }
        }

        // Form validation for staff registration
        document.getElementById('addStaffForm').addEventListener('submit', function(e) {
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

        // Auto-generate username from staff name
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