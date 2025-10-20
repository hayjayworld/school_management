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
$event_id = $_GET['id'] ?? 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = sanitize($_POST['csrf_token']);
    
    if (!validateCSRFToken($csrf_token)) {
        $message = "Security token invalid.";
    } else {
        switch ($_POST['action']) {
            case 'add_event':
                $title = sanitize($_POST['title']);
                $description = sanitize($_POST['description']);
                $event_date = sanitize($_POST['event_date']);
                $start_time = sanitize($_POST['start_time']);
                $end_time = sanitize($_POST['end_time']);
                $venue = sanitize($_POST['venue']);
                $event_type = sanitize($_POST['event_type']);
                $target_audience = sanitize($_POST['target_audience']);
                $is_published = isset($_POST['is_published']) ? 1 : 0;
                
                $query = "INSERT INTO events (title, description, event_date, start_time, end_time, 
                         venue, event_type, target_audience, is_published, created_by) 
                         VALUES (:title, :description, :event_date, :start_time, :end_time, 
                         :venue, :event_type, :target_audience, :is_published, :created_by)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':event_date', $event_date);
                $stmt->bindParam(':start_time', $start_time);
                $stmt->bindParam(':end_time', $end_time);
                $stmt->bindParam(':venue', $venue);
                $stmt->bindParam(':event_type', $event_type);
                $stmt->bindParam(':target_audience', $target_audience);
                $stmt->bindParam(':is_published', $is_published);
                $stmt->bindParam(':created_by', $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $message = "Event created successfully!";
                } else {
                    $message = "Error creating event.";
                }
                break;
                
            case 'update_event':
                $event_id = sanitize($_POST['event_id']);
                $title = sanitize($_POST['title']);
                $description = sanitize($_POST['description']);
                $event_date = sanitize($_POST['event_date']);
                $start_time = sanitize($_POST['start_time']);
                $end_time = sanitize($_POST['end_time']);
                $venue = sanitize($_POST['venue']);
                $event_type = sanitize($_POST['event_type']);
                $target_audience = sanitize($_POST['target_audience']);
                $is_published = isset($_POST['is_published']) ? 1 : 0;
                
                $query = "UPDATE events SET title = :title, description = :description, event_date = :event_date,
                         start_time = :start_time, end_time = :end_time, venue = :venue, event_type = :event_type,
                         target_audience = :target_audience, is_published = :is_published 
                         WHERE id = :event_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':event_date', $event_date);
                $stmt->bindParam(':start_time', $start_time);
                $stmt->bindParam(':end_time', $end_time);
                $stmt->bindParam(':venue', $venue);
                $stmt->bindParam(':event_type', $event_type);
                $stmt->bindParam(':target_audience', $target_audience);
                $stmt->bindParam(':is_published', $is_published);
                $stmt->bindParam(':event_id', $event_id);
                
                if ($stmt->execute()) {
                    $message = "Event updated successfully!";
                } else {
                    $message = "Error updating event.";
                }
                break;
        }
    }
}

// Handle delete action
if (isset($_GET['delete']) && $event_id > 0) {
    $delete_id = sanitize($_GET['delete']);
    
    $delete_query = "DELETE FROM events WHERE id = :id";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(':id', $delete_id);
    
    if ($delete_stmt->execute()) {
        $message = "Event deleted successfully!";
    } else {
        $message = "Error deleting event.";
    }
}

// Handle publish/unpublish action
if (isset($_GET['toggle_publish']) && $event_id > 0) {
    $toggle_id = sanitize($_GET['toggle_publish']);
    
    // Get current status
    $status_query = "SELECT is_published FROM events WHERE id = :id";
    $status_stmt = $db->prepare($status_query);
    $status_stmt->bindParam(':id', $toggle_id);
    $status_stmt->execute();
    $current_status = $status_stmt->fetchColumn();
    
    $new_status = $current_status ? 0 : 1;
    
    $update_query = "UPDATE events SET is_published = :status WHERE id = :id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':status', $new_status);
    $update_stmt->bindParam(':id', $toggle_id);
    
    if ($update_stmt->execute()) {
        $message = "Event " . ($new_status ? "published" : "unpublished") . " successfully!";
    } else {
        $message = "Error updating event status.";
    }
}

// Get events
$events_query = "SELECT e.*, u.full_name as created_by_name 
                FROM events e 
                JOIN users u ON e.created_by = u.id 
                ORDER BY e.event_date DESC, e.start_time DESC";
$events = $db->query($events_query)->fetchAll(PDO::FETCH_ASSOC);

// Get event details for edit
$event = null;
if ($event_id > 0 && $action == 'edit') {
    $event_query = "SELECT * FROM events WHERE id = :id";
    $stmt = $db->prepare($event_query);
    $stmt->bindParam(':id', $event_id);
    $stmt->execute();
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get upcoming events for dashboard
$upcoming_events = $db->query("SELECT * FROM events WHERE event_date >= CURDATE() AND is_published = 1 ORDER BY event_date LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Events Management - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .event-card { border-left: 4px solid; margin-bottom: 15px; }
        .academic-event { border-left-color: #3498db; }
        .sports-event { border-left-color: #27ae60; }
        .cultural-event { border-left-color: #e74c3c; }
        .holiday-event { border-left-color: #f39c12; }
        .other-event { border-left-color: #9b59b6; }
        .calendar-view { background: #f8f9fa; border-radius: 10px; padding: 20px; }
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
        <div class="p-4 text-center ">
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
            <a class="nav-link active" href="events.php"><i class="fas fa-calendar me-2"></i>Events</a>
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
            <h2>Events Management</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
                <i class="fas fa-plus me-2"></i>Add Event
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
                                <h4><?php echo count($events); ?></h4>
                                <p class="mb-0">Total Events</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-calendar fa-2x opacity-50"></i>
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
                                <h4><?php echo count(array_filter($events, function($e) { return $e['is_published']; })); ?></h4>
                                <p class="mb-0">Published Events</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-eye fa-2x opacity-50"></i>
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
                                <h4><?php echo count(array_filter($events, function($e) { 
                                    return strtotime($e['event_date']) >= time(); 
                                })); ?></h4>
                                <p class="mb-0">Upcoming Events</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-clock fa-2x opacity-50"></i>
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
                                <h4><?php echo count(array_filter($events, function($e) { 
                                    return $e['event_type'] == 'academic'; 
                                })); ?></h4>
                                <p class="mb-0">Academic Events</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-graduation-cap fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Event Form -->
        <?php if ($action == 'edit' && $event): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Edit Event</h5>
                    <a href="events.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Back to List
                    </a>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="update_event">
                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Event Title *</label>
                                <input type="text" class="form-control" name="title" required 
                                       value="<?php echo $event['title']; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Event Type *</label>
                                <select class="form-select" name="event_type" required>
                                    <option value="academic" <?php echo $event['event_type'] == 'academic' ? 'selected' : ''; ?>>Academic</option>
                                    <option value="sports" <?php echo $event['event_type'] == 'sports' ? 'selected' : ''; ?>>Sports</option>
                                    <option value="cultural" <?php echo $event['event_type'] == 'cultural' ? 'selected' : ''; ?>>Cultural</option>
                                    <option value="holiday" <?php echo $event['event_type'] == 'holiday' ? 'selected' : ''; ?>>Holiday</option>
                                    <option value="other" <?php echo $event['event_type'] == 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="4"><?php echo $event['description']; ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Event Date *</label>
                                <input type="date" class="form-control" name="event_date" required 
                                       value="<?php echo $event['event_date']; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Start Time</label>
                                <input type="time" class="form-control" name="start_time" 
                                       value="<?php echo $event['start_time']; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">End Time</label>
                                <input type="time" class="form-control" name="end_time" 
                                       value="<?php echo $event['end_time']; ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Venue</label>
                                <input type="text" class="form-control" name="venue" 
                                       value="<?php echo $event['venue']; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Target Audience</label>
                                <select class="form-select" name="target_audience">
                                    <option value="all" <?php echo $event['target_audience'] == 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="students" <?php echo $event['target_audience'] == 'students' ? 'selected' : ''; ?>>Students</option>
                                    <option value="teachers" <?php echo $event['target_audience'] == 'teachers' ? 'selected' : ''; ?>>Teachers</option>
                                    <option value="parents" <?php echo $event['target_audience'] == 'parents' ? 'selected' : ''; ?>>Parents</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="is_published" 
                                   id="is_published" <?php echo $event['is_published'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_published">Publish Event</label>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Update Event</button>
                            <a href="events.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- Events List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">All Events</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Venue</th>
                                    <th>Audience</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($events as $event): ?>
                                    <tr>
                                        <td><?php echo $event['title']; ?></td>
                                        <td><?php echo date('M j, Y', strtotime($event['event_date'])); ?></td>
                                        <td>
                                            <span class="badge 
                                                <?php echo $event['event_type'] == 'academic' ? 'bg-primary' : ''; ?>
                                                <?php echo $event['event_type'] == 'sports' ? 'bg-success' : ''; ?>
                                                <?php echo $event['event_type'] == 'cultural' ? 'bg-danger' : ''; ?>
                                                <?php echo $event['event_type'] == 'holiday' ? 'bg-warning' : ''; ?>
                                                <?php echo $event['event_type'] == 'other' ? 'bg-secondary' : ''; ?>">
                                                <?php echo ucfirst($event['event_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $event['venue'] ?: 'N/A'; ?></td>
                                        <td><?php echo ucfirst($event['target_audience']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $event['is_published'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo $event['is_published'] ? 'Published' : 'Draft'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="events.php?action=edit&id=<?php echo $event['id']; ?>" 
                                                   class="btn btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="events.php?toggle_publish=<?php echo $event['id']; ?>" 
                                                   class="btn btn-outline-<?php echo $event['is_published'] ? 'warning' : 'success'; ?>">
                                                    <i class="fas fa-<?php echo $event['is_published'] ? 'eye-slash' : 'eye'; ?>"></i>
                                                </a>
                                                <a href="events.php?delete=<?php echo $event['id']; ?>" 
                                                   class="btn btn-outline-danger" 
                                                   onclick="return confirm('Are you sure you want to delete this event?')">
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

    <!-- Add Event Modal -->
    <div class="modal fade" id="addEventModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add_event">
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Event Title *</label>
                                <input type="text" class="form-control" name="title" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Event Type *</label>
                                <select class="form-select" name="event_type" required>
                                    <option value="academic">Academic</option>
                                    <option value="sports">Sports</option>
                                    <option value="cultural">Cultural</option>
                                    <option value="holiday">Holiday</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="4"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Event Date *</label>
                                <input type="date" class="form-control" name="event_date" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Start Time</label>
                                <input type="time" class="form-control" name="start_time">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">End Time</label>
                                <input type="time" class="form-control" name="end_time">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Venue</label>
                                <input type="text" class="form-control" name="venue">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Target Audience</label>
                                <select class="form-select" name="target_audience">
                                    <option value="all">All</option>
                                    <option value="students">Students</option>
                                    <option value="teachers">Teachers</option>
                                    <option value="parents">Parents</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="is_published" id="new_is_published">
                            <label class="form-check-label" for="new_is_published">Publish Event</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Event</button>
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