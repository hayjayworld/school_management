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
        // Handle different actions
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_session':
                    $name = sanitize($_POST['name']);
                    $current_term = sanitize($_POST['current_term']);
                    $start_date = sanitize($_POST['start_date']);
                    $end_date = sanitize($_POST['end_date']);
                    
                    // Validate dates
                    if (empty($start_date) || empty($end_date) || $start_date >= $end_date) {
                        $message = "Invalid dates. Start date must be before end date.";
                        break;
                    }
                    
                    // Check if session name already exists
                    $check_query = "SELECT id FROM academic_sessions WHERE name = :name";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->bindParam(':name', $name);
                    $check_stmt->execute();
                    
                    if ($check_stmt->fetch()) {
                        $message = "Session name already exists.";
                        break;
                    }
                    
                    // Deactivate other sessions
                    $deactivate_query = "UPDATE academic_sessions SET status = 'inactive'";
                    $db->query($deactivate_query);
                    
                    // Insert new session
                    $query = "INSERT INTO academic_sessions (name, current_term, start_date, end_date, status, created_by) 
                             VALUES (:name, :current_term, :start_date, :end_date, 'active', :created_by)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':current_term', $current_term);
                    $stmt->bindParam(':start_date', $start_date);
                    $stmt->bindParam(':end_date', $end_date);
                    $stmt->bindParam(':created_by', $_SESSION['user_id']);
                    
                    if ($stmt->execute()) {
                        $message = "Academic session activated successfully!";
                    } else {
                        $message = "Error activating session.";
                    }
                    break;
                    
                case 'change_term':
                    $session_id = sanitize($_POST['session_id']);
                    $new_term = sanitize($_POST['new_term']);
                    
                    $query = "UPDATE academic_sessions SET current_term = :term, updated_at = NOW() WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':term', $new_term);
                    $stmt->bindParam(':id', $session_id);
                    
                    if ($stmt->execute()) {
                        $message = "Term updated successfully!";
                    } else {
                        $message = "Error updating term.";
                    }
                    break;
                    
                case 'end_session':
                    $session_id = sanitize($_POST['session_id']);
                    
                    $query = "UPDATE academic_sessions SET status = 'completed', updated_at = NOW() WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $session_id);
                    
                    if ($stmt->execute()) {
                        $message = "Session ended successfully!";
                    } else {
                        $message = "Error ending session.";
                    }
                    break;
                    
                case 'reactivate_session':
                    $session_id = sanitize($_POST['session_id']);
                    
                    // Deactivate all sessions first
                    $deactivate_query = "UPDATE academic_sessions SET status = 'inactive'";
                    $db->query($deactivate_query);
                    
                    // Reactivate selected session
                    $query = "UPDATE academic_sessions SET status = 'active', updated_at = NOW() WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $session_id);
                    
                    if ($stmt->execute()) {
                        $message = "Session reactivated successfully!";
                    } else {
                        $message = "Error reactivating session.";
                    }
                    break;
                    
                case 'update_session':
                    $session_id = sanitize($_POST['session_id']);
                    $name = sanitize($_POST['name']);
                    $current_term = sanitize($_POST['current_term']);
                    $start_date = sanitize($_POST['start_date']);
                    $end_date = sanitize($_POST['end_date']);
                    $status = sanitize($_POST['status']);
                    
                    $query = "UPDATE academic_sessions SET name = :name, current_term = :term, 
                             start_date = :start_date, end_date = :end_date, status = :status, 
                             updated_at = NOW() WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':term', $current_term);
                    $stmt->bindParam(':start_date', $start_date);
                    $stmt->bindParam(':end_date', $end_date);
                    $stmt->bindParam(':status', $status);
                    $stmt->bindParam(':id', $session_id);
                    
                    if ($stmt->execute()) {
                        $message = "Session updated successfully!";
                    } else {
                        $message = "Error updating session.";
                    }
                    break;
            }
        }
    }
}

// Get current session
$current_session_query = "SELECT * FROM academic_sessions WHERE status = 'active' LIMIT 1";
$current_session = $db->query($current_session_query)->fetch(PDO::FETCH_ASSOC);

// Get all sessions
$sessions_query = "SELECT * FROM academic_sessions ORDER BY created_at DESC";
$sessions = $db->query($sessions_query)->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Manage Sessions - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .badge-completed { background-color: #6c757d; }
        .badge-active { background-color: #198754; }
        .badge-inactive { background-color: #6c757d; }
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
            <a class="nav-link active" href="sessions.php"><i class="fas fa-calendar-alt me-2"></i>Sessions</a>
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
            <h2>Academic Sessions</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSessionModal">
                <i class="fas fa-plus me-2"></i>New Session
            </button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Current Session Card -->
        <?php if ($current_session): ?>
            <div class="card mb-4 border-success">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-star me-2"></i>Current Academic Session
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h4><?php echo $current_session['name']; ?></h4>
                            <p class="mb-1">
                                <strong>Current Term:</strong> 
                                <span class="badge bg-primary">
                                    <?php echo ucfirst($current_session['current_term']); ?> Term
                                </span>
                            </p>
                            <p class="mb-1">
                                <strong>Duration:</strong> 
                                <?php echo date('M j, Y', strtotime($current_session['start_date'])); ?> - 
                                <?php echo date('M j, Y', strtotime($current_session['end_date'])); ?>
                            </p>
                            <p class="mb-0 text-muted">
                                Activated on: <?php echo date('F j, Y', strtotime($current_session['created_at'])); ?>
                            </p>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="btn-group">
                                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#changeTermModal" 
                                        data-session-id="<?php echo $current_session['id']; ?>"
                                        data-current-term="<?php echo $current_session['current_term']; ?>">
                                    Change Term
                                </button>
                                <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#endSessionModal"
                                        data-session-id="<?php echo $current_session['id']; ?>"
                                        data-session-name="<?php echo $current_session['name']; ?>">
                                    End Session
                                </button>
                                <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#editSessionModal"
                                        data-session-id="<?php echo $current_session['id']; ?>"
                                        data-name="<?php echo $current_session['name']; ?>"
                                        data-current-term="<?php echo $current_session['current_term']; ?>"
                                        data-start-date="<?php echo $current_session['start_date']; ?>"
                                        data-end-date="<?php echo $current_session['end_date']; ?>"
                                        data-status="<?php echo $current_session['status']; ?>">
                                    Edit
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                No active academic session. Please create a new session to continue.
            </div>
        <?php endif; ?>

        <!-- All Sessions -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">All Academic Sessions</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Session Name</th>
                                <th>Current Term</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Created Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $session): ?>
                                <tr>
                                    <td><?php echo $session['name']; ?></td>
                                    <td><?php echo ucfirst($session['current_term']); ?> Term</td>
                                    <td>
                                        <?php if ($session['start_date'] && $session['end_date']): ?>
                                            <?php echo date('M j, Y', strtotime($session['start_date'])); ?> - 
                                            <?php echo date('M j, Y', strtotime($session['end_date'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($session['status'] == 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php elseif ($session['status'] == 'completed'): ?>
                                            <span class="badge bg-secondary">Completed</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($session['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($session['status'] != 'active'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="reactivate_session">
                                                    <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-success" 
                                                            onclick="return confirm('Are you sure you want to activate this session?')">
                                                        Activate
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#editSessionModal"
                                                    data-session-id="<?php echo $session['id']; ?>"
                                                    data-name="<?php echo $session['name']; ?>"
                                                    data-current-term="<?php echo $session['current_term']; ?>"
                                                    data-start-date="<?php echo $session['start_date']; ?>"
                                                    data-end-date="<?php echo $session['end_date']; ?>"
                                                    data-status="<?php echo $session['status']; ?>">
                                                Edit
                                            </button>
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

    <!-- Add Session Modal -->
    <div class="modal fade" id="addSessionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Start New Academic Session</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add_session">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Session Name *</label>
                            <input type="text" class="form-control" name="name" required 
                                   placeholder="e.g., 2024/2025, 2025/2026">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Current Term *</label>
                            <select class="form-select" name="current_term" required>
                                <option value="first">First Term</option>
                                <option value="second">Second Term</option>
                                <option value="third">Third Term</option>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Start Date *</label>
                                    <input type="date" class="form-control" name="start_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">End Date *</label>
                                    <input type="date" class="form-control" name="end_date" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Starting a new session will deactivate the current session.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Start Session</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Change Term Modal -->
    <div class="modal fade" id="changeTermModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Current Term</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="change_term">
                    <input type="hidden" name="session_id" id="changeTermSessionId">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select New Term *</label>
                            <select class="form-select" name="new_term" required id="newTermSelect">
                                <option value="first">First Term</option>
                                <option value="second">Second Term</option>
                                <option value="third">Third Term</option>
                            </select>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Changing the term will affect all academic activities for this session.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Term</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- End Session Modal -->
    <div class="modal fade" id="endSessionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">End Academic Session</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="end_session">
                    <input type="hidden" name="session_id" id="endSessionId">
                    
                    <div class="modal-body">
                        <p>Are you sure you want to end the session: <strong id="endSessionName"></strong>?</p>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            This action cannot be undone. All academic activities for this session will be marked as completed.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">End Session</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Session Modal -->
    <div class="modal fade" id="editSessionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Academic Session</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="update_session">
                    <input type="hidden" name="session_id" id="editSessionId">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Session Name *</label>
                            <input type="text" class="form-control" name="name" required id="editSessionName">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Current Term *</label>
                            <select class="form-select" name="current_term" required id="editCurrentTerm">
                                <option value="first">First Term</option>
                                <option value="second">Second Term</option>
                                <option value="third">Third Term</option>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Start Date *</label>
                                    <input type="date" class="form-control" name="start_date" required id="editStartDate">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">End Date *</label>
                                    <input type="date" class="form-control" name="end_date" required id="editEndDate">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Status *</label>
                            <select class="form-select" name="status" required id="editStatus">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Session</button>
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
        // Change Term Modal
        document.getElementById('changeTermModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const sessionId = button.getAttribute('data-session-id');
            const currentTerm = button.getAttribute('data-current-term');
            
            document.getElementById('changeTermSessionId').value = sessionId;
            document.getElementById('newTermSelect').value = currentTerm;
        });

        // End Session Modal
        document.getElementById('endSessionModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const sessionId = button.getAttribute('data-session-id');
            const sessionName = button.getAttribute('data-session-name');
            
            document.getElementById('endSessionId').value = sessionId;
            document.getElementById('endSessionName').textContent = sessionName;
        });

        // Edit Session Modal
        document.getElementById('editSessionModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const sessionId = button.getAttribute('data-session-id');
            const name = button.getAttribute('data-name');
            const currentTerm = button.getAttribute('data-current-term');
            const startDate = button.getAttribute('data-start-date');
            const endDate = button.getAttribute('data-end-date');
            const status = button.getAttribute('data-status');
            
            document.getElementById('editSessionId').value = sessionId;
            document.getElementById('editSessionName').value = name;
            document.getElementById('editCurrentTerm').value = currentTerm;
            document.getElementById('editStartDate').value = startDate;
            document.getElementById('editEndDate').value = endDate;
            document.getElementById('editStatus').value = status;
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