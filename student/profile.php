<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('student')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$user_id = $_SESSION['user_id'];

// Get user and student details
$query = "SELECT u.*, s.student_id, s.class_id, s.session_id, s.admission_date, s.admission_number,
                 s.parent_name, s.parent_email, s.parent_phone, s.parent_occupation, 
                 s.emergency_contact, s.religion, s.blood_group, s.medical_conditions, 
                 s.allergies, s.previous_school, c.name as class_name, ses.name as session_name
          FROM users u 
          JOIN students s ON u.id = s.user_id 
          JOIN classes c ON s.class_id = c.id 
          JOIN academic_sessions ses ON s.session_id = ses.id 
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
            $city = sanitize($_POST['city']);
            $state = sanitize($_POST['state']);
            $religion = sanitize($_POST['religion']);
            $blood_group = sanitize($_POST['blood_group']);
            $medical_conditions = sanitize($_POST['medical_conditions']);
            $allergies = sanitize($_POST['allergies']);
            
            $query = "UPDATE users SET full_name = :full_name, email = :email, phone = :phone, 
                     gender = :gender, date_of_birth = :date_of_birth, address = :address, 
                     city = :city, state = :state, updated_at = NOW() WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':gender', $gender);
            $stmt->bindParam(':date_of_birth', $date_of_birth);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':city', $city);
            $stmt->bindParam(':state', $state);
            $stmt->bindParam(':user_id', $user_id);
            
            if ($stmt->execute()) {
                // Update student-specific information
                $student_query = "UPDATE students SET religion = :religion, blood_group = :blood_group,
                                medical_conditions = :medical_conditions, allergies = :allergies,
                                updated_at = NOW() WHERE user_id = :user_id";
                $student_stmt = $db->prepare($student_query);
                $student_stmt->bindParam(':religion', $religion);
                $student_stmt->bindParam(':blood_group', $blood_group);
                $student_stmt->bindParam(':medical_conditions', $medical_conditions);
                $student_stmt->bindParam(':allergies', $allergies);
                $student_stmt->bindParam(':user_id', $user_id);
                $student_stmt->execute();
                
                $message = "Profile updated successfully!";
                // Refresh user data
                $stmt->execute($query);
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
    <title>Student Profile - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
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
                <small class="text-muted">Student Portal</small>
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
            <p class="text-muted small">Student Portal</p>
        </div>
        <nav class="nav flex-column scrollable-menu">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
            <a class="nav-link" href="results.php"><i class="fas fa-chart-line me-2"></i>My Results</a>
            <!-- <a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check me-2"></i>Attendance</a> -->
            <a class="nav-link" href="exams.php"><i class="fas fa-laptop me-2"></i>CBT Exams</a>
            <a class="nav-link" href="timetable.php"><i class="fas fa-table me-2"></i>Class Timetable</a>
            <a class="nav-link" href="events.php"><i class="fas fa-calendar me-2"></i>School Events</a>
            <a class="nav-link active" href="profile.php"><i class="fas fa-user me-2"></i>My Profile</a>
            <!-- <a class="nav-link" href="library.php"><i class="fas fa-book me-2"></i>Library</a> -->
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Student Profile</h2>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Personal Information -->
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-user-edit me-2"></i>Personal Information</h5>
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
                                    <label class="form-label">Student ID</label>
                                    <input type="text" class="form-control" value="<?php echo $user['student_id']; ?>" disabled>
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
                                    <label class="form-label">Religion</label>
                                    <select class="form-select" name="religion">
                                        <option value="christianity" <?php echo $user['religion'] == 'christianity' ? 'selected' : ''; ?>>Christianity</option>
                                        <option value="islam" <?php echo $user['religion'] == 'islam' ? 'selected' : ''; ?>>Islam</option>
                                        <option value="other" <?php echo $user['religion'] == 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Blood Group</label>
                                    <select class="form-select" name="blood_group">
                                        <option value="">Select Blood Group</option>
                                        <option value="A+" <?php echo $user['blood_group'] == 'A+' ? 'selected' : ''; ?>>A+</option>
                                        <option value="A-" <?php echo $user['blood_group'] == 'A-' ? 'selected' : ''; ?>>A-</option>
                                        <option value="B+" <?php echo $user['blood_group'] == 'B+' ? 'selected' : ''; ?>>B+</option>
                                        <option value="B-" <?php echo $user['blood_group'] == 'B-' ? 'selected' : ''; ?>>B-</option>
                                        <option value="AB+" <?php echo $user['blood_group'] == 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                        <option value="AB-" <?php echo $user['blood_group'] == 'AB-' ? 'selected' : ''; ?>>AB-</option>
                                        <option value="O+" <?php echo $user['blood_group'] == 'O+' ? 'selected' : ''; ?>>O+</option>
                                        <option value="O-" <?php echo $user['blood_group'] == 'O-' ? 'selected' : ''; ?>>O-</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="3"><?php echo $user['address']; ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">City</label>
                                    <input type="text" class="form-control" name="city" 
                                           value="<?php echo $user['city']; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">State</label>
                                    <input type="text" class="form-control" name="state" 
                                           value="<?php echo $user['state']; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Medical Conditions</label>
                                    <textarea class="form-control" name="medical_conditions" rows="2"><?php echo $user['medical_conditions']; ?></textarea>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Allergies</label>
                                    <textarea class="form-control" name="allergies" rows="2"><?php echo $user['allergies']; ?></textarea>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Change Password & Academic Info -->
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
                
                <!-- Academic Information -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-graduation-cap me-2"></i>Academic Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <strong>Student ID:</strong>
                            <br><?php echo $user['student_id']; ?>
                        </div>
                        <div class="mb-2">
                            <strong>Class:</strong>
                            <br><?php echo $user['class_name']; ?>
                        </div>
                        <div class="mb-2">
                            <strong>Academic Session:</strong>
                            <br><?php echo $user['session_name']; ?>
                        </div>
                        <div class="mb-2">
                            <strong>Admission Date:</strong>
                            <br><?php echo date('F j, Y', strtotime($user['admission_date'])); ?>
                        </div>
                        <div class="mb-2">
                            <strong>Status:</strong>
                            <span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Parent Information -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-users me-2"></i>Parent Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <strong>Parent Name:</strong>
                            <br><?php echo $user['parent_name']; ?>
                        </div>
                        <div class="mb-2">
                            <strong>Parent Email:</strong>
                            <br><?php echo $user['parent_email']; ?>
                        </div>
                        <div class="mb-2">
                            <strong>Parent Phone:</strong>
                            <br><?php echo $user['parent_phone']; ?>
                        </div>
                        <div class="mb-2">
                            <strong>Parent Occupation:</strong>
                            <br><?php echo $user['parent_occupation']; ?>
                        </div>
                        <div class="mb-2">
                            <strong>Emergency Contact:</strong>
                            <br><?php echo $user['emergency_contact']; ?>
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