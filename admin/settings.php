<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = sanitize($_POST['csrf_token']);
    
    if (!validateCSRFToken($csrf_token)) {
        $message = "Security token invalid.";
    } else {
        // Handle different setting groups
        if (isset($_POST['update_general_settings'])) {
            $settings_to_update = [
                'school_name' => $_POST['school_name'],
                'school_address' => $_POST['school_address'],
                'school_phone' => $_POST['school_phone'],
                'school_email' => $_POST['school_email'],
                'school_website' => $_POST['school_website'],
                'school_motto' => $_POST['school_motto']
            ];
            
            foreach ($settings_to_update as $key => $value) {
                $query = "INSERT INTO system_settings (setting_key, setting_value, setting_type, is_public, description) 
                         VALUES (:key, :value, 'string', 1, 'School setting') 
                         ON DUPLICATE KEY UPDATE setting_value = :value, updated_at = NOW()";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':key', $key);
                $stmt->bindParam(':value', $value);
                $stmt->execute();
            }
            $message = "General settings updated successfully!";
        }
        
        elseif (isset($_POST['update_academic_settings'])) {
            $settings_to_update = [
                'current_session_id' => $_POST['current_session_id'],
                'ca_max_score' => $_POST['ca_max_score'],
                'exam_max_score' => $_POST['exam_max_score'],
                'passing_percentage' => $_POST['passing_percentage'],
                'grade_a_min' => $_POST['grade_a_min'],
                'grade_b_min' => $_POST['grade_b_min'],
                'grade_c_min' => $_POST['grade_c_min'],
                'grade_d_min' => $_POST['grade_d_min']
            ];
            
            foreach ($settings_to_update as $key => $value) {
                $query = "INSERT INTO system_settings (setting_key, setting_value, setting_type, is_public, description) 
                         VALUES (:key, :value, 'number', 0, 'Academic setting') 
                         ON DUPLICATE KEY UPDATE setting_value = :value, updated_at = NOW()";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':key', $key);
                $stmt->bindParam(':value', $value);
                $stmt->execute();
            }
            $message = "Academic settings updated successfully!";
        }
        
        elseif (isset($_POST['update_system_settings'])) {
            $settings_to_update = [
                'student_id_prefix' => $_POST['student_id_prefix'],
                'staff_id_prefix' => $_POST['staff_id_prefix'],
                'default_password' => $_POST['default_password'],
                'max_login_attempts' => $_POST['max_login_attempts'],
                'session_timeout' => $_POST['session_timeout']
            ];
            
            foreach ($settings_to_update as $key => $value) {
                $query = "INSERT INTO system_settings (setting_key, setting_value, setting_type, is_public, description) 
                         VALUES (:key, :value, 'string', 0, 'System setting') 
                         ON DUPLICATE KEY UPDATE setting_value = :value, updated_at = NOW()";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':key', $key);
                $stmt->bindParam(':value', $value);
                $stmt->execute();
            }
            $message = "System settings updated successfully!";
        }
    }
}

// Get all settings
$settings_query = "SELECT setting_key, setting_value, setting_type, description, is_public 
                  FROM system_settings 
                  ORDER BY setting_key";
$settings_result = $db->query($settings_query);
$settings = [];
while ($row = $settings_result->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row;
}

// Get academic sessions for dropdown
$sessions = $db->query("SELECT * FROM academic_sessions ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Function to get setting value with default
function getSetting($key, $default = '') {
    global $settings;
    return isset($settings[$key]) ? $settings[$key]['setting_value'] : $default;
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
    <title>System Settings - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .settings-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .settings-card .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        .setting-group {
            border-left: 4px solid #3498db;
            padding-left: 15px;
            margin-bottom: 20px;
        }
        .setting-description {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: -5px;
            margin-bottom: 10px;
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

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="p-4 text-center d-none d-md-block">
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
            <a class="nav-link" href="#"><i class="fas fa-question-circle me-2"></i>Add Questions</a>
            <a class="nav-link" href="fees.php"><i class="fas fa-money-bill me-2"></i>Fees</a>
            <a class="nav-link" href="library.php"><i class="fas fa-book-open me-2"></i>Library</a>
            <a class="nav-link" href="events.php"><i class="fas fa-calendar me-2"></i>Events</a>
            <a class="nav-link" href="timetable.php"><i class="fas fa-table me-2"></i>Timetable</a>
            <a class="nav-link" href="student_reports.php"><i class="fas fa-chart-bar me-2"></i>Student Reports</a>
            <a class="nav-link" href="student_promotion.php"><i class="fas fa-user-graduate me-2"></i>Student Promotion</a>
            <!-- <a class="nav-link" href="reports.php"><i class="fas fa-chart-bar me-2"></i>Reports</a> -->
            <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a>
            <a class="nav-link active" href="settings.php"><i class="fas fa-cogs me-2"></i>System Settings</a>
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>System Settings</h2>
            <div class="text-muted">
                <i class="fas fa-cogs me-2"></i>Manage System Configuration
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- General Settings -->
        <div class="card settings-card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-school me-2"></i>General Settings
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="setting-group">
                                <label class="form-label fw-bold">School Name</label>
                                <div class="setting-description">The official name of your institution</div>
                                <input type="text" class="form-control" name="school_name" 
                                       value="<?php echo getSetting('school_name', 'Excel Schools'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="setting-group">
                                <label class="form-label fw-bold">School Motto</label>
                                <div class="setting-description">Institution motto or tagline</div>
                                <input type="text" class="form-control" name="school_motto" 
                                       value="<?php echo getSetting('school_motto', 'Excellence in Education'); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="setting-group">
                                <label class="form-label fw-bold">School Address</label>
                                <div class="setting-description">Complete physical address</div>
                                <textarea class="form-control" name="school_address" rows="2"><?php echo getSetting('school_address', '123 Education Road, Ikeja, Lagos'); ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="setting-group">
                                <label class="form-label fw-bold">Contact Information</label>
                                <div class="setting-description">Phone and email for public display</div>
                                <div class="row g-2">
                                    <div class="col-12">
                                        <input type="text" class="form-control" name="school_phone" 
                                               value="<?php echo getSetting('school_phone', '+234 801 234 5678'); ?>" 
                                               placeholder="Phone Number">
                                    </div>
                                    <div class="col-12">
                                        <input type="email" class="form-control" name="school_email" 
                                               value="<?php echo getSetting('school_email', 'info@excelschools.edu.ng'); ?>" 
                                               placeholder="Email Address">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="setting-group">
                                <label class="form-label fw-bold">Website URL</label>
                                <div class="setting-description">Official school website address</div>
                                <input type="url" class="form-control" name="school_website" 
                                       value="<?php echo getSetting('school_website', 'https://www.excelschools.edu.ng'); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" name="update_general_settings" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save General Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Academic Settings -->
        <div class="card settings-card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-graduation-cap me-2"></i>Academic Settings
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="setting-group">
                                <label class="form-label fw-bold">Current Academic Session</label>
                                <div class="setting-description">Active academic session for the system</div>
                                <select class="form-select" name="current_session_id" required>
                                    <option value="">Select Session</option>
                                    <?php foreach ($sessions as $session): ?>
                                        <option value="<?php echo $session['id']; ?>" 
                                            <?php echo getSetting('current_session_id', '1') == $session['id'] ? 'selected' : ''; ?>>
                                            <?php echo $session['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="setting-group">
                                <label class="form-label fw-bold">Passing Percentage</label>
                                <div class="setting-description">Minimum percentage required to pass</div>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="passing_percentage" 
                                           value="<?php echo getSetting('passing_percentage', '50'); ?>" min="0" max="100">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="setting-group">
                                <label class="form-label fw-bold">Maximum CA Score</label>
                                <div class="setting-description">Maximum marks for Continuous Assessment</div>
                                <input type="number" class="form-control" name="ca_max_score" 
                                       value="<?php echo getSetting('ca_max_score', '30'); ?>" min="0" max="100">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="setting-group">
                                <label class="form-label fw-bold">Maximum Exam Score</label>
                                <div class="setting-description">Maximum marks for Examination</div>
                                <input type="number" class="form-control" name="exam_max_score" 
                                       value="<?php echo getSetting('exam_max_score', '70'); ?>" min="0" max="100">
                            </div>
                        </div>
                    </div>
                    
                    <h6 class="mt-4 mb-3">Grading System</h6>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Grade A (Minimum %)</label>
                            <input type="number" class="form-control" name="grade_a_min" 
                                   value="<?php echo getSetting('grade_a_min', '80'); ?>" min="0" max="100">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Grade B (Minimum %)</label>
                            <input type="number" class="form-control" name="grade_b_min" 
                                   value="<?php echo getSetting('grade_b_min', '70'); ?>" min="0" max="100">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Grade C (Minimum %)</label>
                            <input type="number" class="form-control" name="grade_c_min" 
                                   value="<?php echo getSetting('grade_c_min', '60'); ?>" min="0" max="100">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Grade D (Minimum %)</label>
                            <input type="number" class="form-control" name="grade_d_min" 
                                   value="<?php echo getSetting('grade_d_min', '50'); ?>" min="0" max="100">
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" name="update_academic_settings" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Academic Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- System Settings -->
        <div class="card settings-card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-sliders-h me-2"></i>System Configuration
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="setting-group">
                                <label class="form-label fw-bold">Student ID Prefix</label>
                                <div class="setting-description">Prefix for auto-generated student IDs</div>
                                <input type="text" class="form-control" name="student_id_prefix" 
                                       value="<?php echo getSetting('student_id_prefix', 'STD'); ?>" maxlength="10">
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="setting-group">
                                <label class="form-label fw-bold">Staff ID Prefix</label>
                                <div class="setting-description">Prefix for auto-generated staff IDs</div>
                                <input type="text" class="form-control" name="staff_id_prefix" 
                                       value="<?php echo getSetting('staff_id_prefix', 'STAFF'); ?>" maxlength="10">
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="setting-group">
                                <label class="form-label fw-bold">Default Password</label>
                                <div class="setting-description">Default password for new users</div>
                                <input type="text" class="form-control" name="default_password" 
                                       value="<?php echo getSetting('default_password', 'password123'); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="setting-group">
                                <label class="form-label fw-bold">Max Login Attempts</label>
                                <div class="setting-description">Maximum failed login attempts before lockout</div>
                                <input type="number" class="form-control" name="max_login_attempts" 
                                       value="<?php echo getSetting('max_login_attempts', '5'); ?>" min="1" max="10">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="setting-group">
                                <label class="form-label fw-bold">Session Timeout (minutes)</label>
                                <div class="setting-description">User session timeout duration</div>
                                <input type="number" class="form-control" name="session_timeout" 
                                       value="<?php echo getSetting('session_timeout', '30'); ?>" min="5" max="240">
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" name="update_system_settings" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save System Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Database Backup -->
        <div class="card settings-card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-database me-2"></i>Database Management
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="setting-group">
                            <label class="form-label fw-bold">Database Backup</label>
                            <div class="setting-description">Create a backup of the entire database</div>
                            <button type="button" class="btn btn-outline-primary" onclick="createBackup()">
                                <i class="fas fa-download me-2"></i>Create Backup
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="setting-group">
                            <label class="form-label fw-bold">System Information</label>
                            <div class="setting-description">Current system status and statistics</div>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-info" onclick="showSystemInfo()">
                                    <i class="fas fa-info-circle me-2"></i>View System Info
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile Menu Toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const mobileOverlay = document.getElementById('mobileOverlay');
        const body = document.body;

        function toggleMobileMenu() {
            body.classList.toggle('sidebar-mobile-open');
            mobileOverlay.style.display = body.classList.contains('sidebar-mobile-open') ? 'block' : 'none';
        }

        mobileMenuToggle.addEventListener('click', toggleMobileMenu);
        mobileOverlay.addEventListener('click', toggleMobileMenu);

        // Close menu when clicking on a link
        document.querySelectorAll('.sidebar .nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    toggleMobileMenu();
                }
            });
        });

        // System functions
        function createBackup() {
            if (confirm('Are you sure you want to create a database backup? This may take a few moments.')) {
                // In a real implementation, this would call a PHP script to create the backup
                alert('Backup feature would be implemented here. In production, this would generate a SQL dump file.');
            }
        }

        function showSystemInfo() {
            const info = `
                System Information:
                - PHP Version: <?php echo phpversion(); ?>
                - Database: MySQL
                - Server: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?>
                - Users: <?php echo $db->query("SELECT COUNT(*) FROM users")->fetchColumn(); ?>
                - Students: <?php echo $db->query("SELECT COUNT(*) FROM students")->fetchColumn(); ?>
                - Teachers: <?php echo $db->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn(); ?>
            `;
            alert(info);
        }

        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
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
                }
            });
        });
    </script>
</body>
</html>