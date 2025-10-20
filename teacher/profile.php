<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('teacher')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$user_id = $_SESSION['user_id'];

// Get user and staff details
$query = "SELECT u.*, s.staff_id, s.qualification, s.specialization, s.employment_date, 
                 s.salary_grade, s.bank_name, s.account_number, s.marital_status
          FROM users u 
          LEFT JOIN staff s ON u.id = s.user_id 
          WHERE u.id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = sanitize($_POST['csrf_token']);
    
    if (!validateCSRFToken($csrf_token)) {
        $message = "Security token invalid.";
    } else {
        if (isset($_POST['action']) && $_POST['action'] == 'update_profile') {
            $full_name = sanitize($_POST['full_name']);
            $email = sanitize($_POST['email']);
            $phone = sanitize($_POST['phone']);
            $gender = sanitize($_POST['gender']);
            $date_of_birth = sanitize($_POST['date_of_birth']);
            $address = sanitize($_POST['address']);
            $qualification = sanitize($_POST['qualification']);
            $specialization = sanitize($_POST['specialization']);
            $marital_status = sanitize($_POST['marital_status']);
            
            // Update users table
            $user_query = "UPDATE users SET full_name = :full_name, email = :email, phone = :phone, 
                          gender = :gender, date_of_birth = :date_of_birth, address = :address, 
                          updated_at = NOW() WHERE id = :user_id";
            $user_stmt = $db->prepare($user_query);
            $user_stmt->bindParam(':full_name', $full_name);
            $user_stmt->bindParam(':email', $email);
            $user_stmt->bindParam(':phone', $phone);
            $user_stmt->bindParam(':gender', $gender);
            $user_stmt->bindParam(':date_of_birth', $date_of_birth);
            $user_stmt->bindParam(':address', $address);
            $user_stmt->bindParam(':user_id', $user_id);
            
            // Update staff table
            $staff_query = "UPDATE staff SET qualification = :qualification, specialization = :specialization,
                           marital_status = :marital_status, updated_at = NOW() WHERE user_id = :user_id";
            $staff_stmt = $db->prepare($staff_query);
            $staff_stmt->bindParam(':qualification', $qualification);
            $staff_stmt->bindParam(':specialization', $specialization);
            $staff_stmt->bindParam(':marital_status', $marital_status);
            $staff_stmt->bindParam(':user_id', $user_id);
            
            if ($user_stmt->execute() && $staff_stmt->execute()) {
                $message = "Profile updated successfully!";
                // Refresh user data
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $message = "Error updating profile.";
            }
        } elseif (isset($_POST['action']) && $_POST['action'] == 'change_password') {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if (!password_verify($current_password, $user['password'])) {
                $message = "Current password is incorrect.";
            } elseif ($new_password !== $confirm_password) {
                $message = "New passwords do not match.";
            } elseif (strlen($new_password) < 6) {
                $message = "New password must be at least 6 characters long.";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $query = "UPDATE users SET password = :password, updated_at = NOW() WHERE id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':user_id', $user_id);
                
                if ($stmt->execute()) {
                    $message = "Password changed successfully!";
                } else {
                    $message = "Error changing password.";
                }
            }
        }
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
    <title>Teacher Profile - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
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
            <a class="nav-link" href="my_classes.php"><i class="fas fa-door-open me-2"></i>My Classes</a>
            <a class="nav-link" href="my_subjects.php"><i class="fas fa-book me-2"></i>My Subjects</a>
            <a class="nav-link" href="attendance.php"><i class="fas fa-clipboard-list me-2"></i>Attendance</a>
            <a class="nav-link" href="results.php"><i class="fas fa-chart-bar me-2"></i>Results</a>
            <a class="nav-link" href="timetable.php"><i class="fas fa-table me-2"></i>Timetable</a>
            <a class="nav-link active" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a>
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Teacher Profile</h2>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Profile Information -->
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-user-edit me-2"></i>Profile Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" name="full_name" required 
                                           value="<?php echo $user['full_name']; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Staff ID</label>
                                    <input type="text" class="form-control" value="<?php echo $user['staff_id']; ?>" disabled>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" required 
                                           value="<?php echo $user['email']; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" name="phone" 
                                           value="<?php echo $user['phone']; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Gender</label>
                                    <select class="form-select" name="gender">
                                        <option value="male" <?php echo $user['gender'] == 'male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="female" <?php echo $user['gender'] == 'female' ? 'selected' : ''; ?>>Female</option>
                                        <option value="other" <?php echo $user['gender'] == 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" name="date_of_birth" 
                                           value="<?php echo $user['date_of_birth']; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Qualification</label>
                                    <input type="text" class="form-control" name="qualification" 
                                           value="<?php echo $user['qualification']; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Specialization</label>
                                    <input type="text" class="form-control" name="specialization" 
                                           value="<?php echo $user['specialization']; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Marital Status</label>
                                    <select class="form-select" name="marital_status">
                                        <option value="single" <?php echo $user['marital_status'] == 'single' ? 'selected' : ''; ?>>Single</option>
                                        <option value="married" <?php echo $user['marital_status'] == 'married' ? 'selected' : ''; ?>>Married</option>
                                        <option value="divorced" <?php echo $user['marital_status'] == 'divorced' ? 'selected' : ''; ?>>Divorced</option>
                                        <option value="widowed" <?php echo $user['marital_status'] == 'widowed' ? 'selected' : ''; ?>>Widowed</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="3"><?php echo $user['address']; ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Change Password & Account Info -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="card-title mb-0"><i class="fas fa-lock me-2"></i>Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="mb-3">
                                <label class="form-label">Current Password *</label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">New Password *</label>
                                <input type="password" class="form-control" name="new_password" required minlength="6">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password *</label>
                                <input type="password" class="form-control" name="confirm_password" required minlength="6">
                            </div>
                            
                            <button type="submit" class="btn btn-warning">Change Password</button>
                        </form>
                    </div>
                </div>
                
                <!-- Account Info -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2"></i>Employment Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <strong>Staff ID:</strong>
                            <br><?php echo $user['staff_id']; ?>
                        </div>
                        <div class="mb-2">
                            <strong>Employment Date:</strong>
                            <br><?php echo $user['employment_date'] ? date('F j, Y', strtotime($user['employment_date'])) : 'Not set'; ?>
                        </div>
                        <div class="mb-2">
                            <strong>Salary Grade:</strong>
                            <br><?php echo $user['salary_grade'] ?: 'Not set'; ?>
                        </div>
                        <div class="mb-2">
                            <strong>Bank:</strong>
                            <br><?php echo $user['bank_name'] ?: 'Not set'; ?>
                        </div>
                        <div class="mb-2">
                            <strong>Status:</strong>
                            <span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </div>
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