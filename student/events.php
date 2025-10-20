<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('student')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get events for students
$events_query = "SELECT e.*, u.full_name as created_by_name 
                FROM events e 
                JOIN users u ON e.created_by = u.id 
                WHERE e.is_published = TRUE 
                AND (e.target_audience IN ('all', 'students') OR e.target_audience = 'all')
                AND e.event_date >= CURDATE() 
                ORDER BY e.event_date ASC, e.start_time ASC";
$events = $db->query($events_query)->fetchAll(PDO::FETCH_ASSOC);

// Get current month events for calendar
$current_month = date('Y-m');
$calendar_events_query = "SELECT event_date, title, event_type 
                         FROM events 
                         WHERE is_published = TRUE 
                         AND (target_audience IN ('all', 'students') OR target_audience = 'all')
                         AND DATE_FORMAT(event_date, '%Y-%m') = :current_month 
                         ORDER BY event_date";
$calendar_stmt = $db->prepare($calendar_events_query);
$calendar_stmt->bindParam(':current_month', $current_month);
$calendar_stmt->execute();
$calendar_events = $calendar_stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Events - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .event-card { border-left: 4px solid; margin-bottom: 15px; }
        .event-academic { border-left-color: #007bff; }
        .event-sports { border-left-color: #28a745; }
        .event-cultural { border-left-color: #ffc107; }
        .event-holiday { border-left-color: #dc3545; }
        .event-other { border-left-color: #6c757d; }
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
            <a class="nav-link active" href="events.php"><i class="fas fa-calendar me-2"></i>School Events</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>My Profile</a>
            <!-- <a class="nav-link" href="library.php"><i class="fas fa-book me-2"></i>Library</a> -->
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>School Events & Calendar</h2>
        </div>

        <div class="row">
            <!-- Upcoming Events List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-calendar-alt me-2"></i>Upcoming Events</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($events): ?>
                            <?php foreach ($events as $event): ?>
                                <div class="card event-card event-<?php echo $event['event_type']; ?>">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-2 text-center">
                                                <div class="bg-primary text-white rounded p-2">
                                                    <h5 class="mb-0"><?php echo date('d', strtotime($event['event_date'])); ?></h5>
                                                    <small><?php echo date('M', strtotime($event['event_date'])); ?></small>
                                                </div>
                                                <small class="text-muted mt-1 d-block">
                                                    <?php echo $event['start_time'] ? date('g:i A', strtotime($event['start_time'])) : 'All Day'; ?>
                                                    <?php if ($event['end_time']): ?>
                                                        <br>to <?php echo date('g:i A', strtotime($event['end_time'])); ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                            <div class="col-md-8">
                                                <h6 class="card-title"><?php echo $event['title']; ?></h6>
                                                <p class="card-text text-muted small"><?php echo $event['description']; ?></p>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <span class="badge bg-<?php 
                                                        switch($event['event_type']) {
                                                            case 'academic': echo 'primary'; break;
                                                            case 'sports': echo 'success'; break;
                                                            case 'cultural': echo 'warning'; break;
                                                            case 'holiday': echo 'danger'; break;
                                                            default: echo 'secondary';
                                                        }
                                                    ?>">
                                                        <?php echo ucfirst($event['event_type']); ?>
                                                    </span>
                                                    <?php if ($event['venue']): ?>
                                                        <span class="badge bg-light text-dark">
                                                            <i class="fas fa-map-marker-alt me-1"></i><?php echo $event['venue']; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-2 text-end">
                                                <small class="text-muted">
                                                    Posted by:<br>
                                                    <?php echo $event['created_by_name']; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-calendar-times fa-3x mb-3"></i>
                                <h5>No Upcoming Events</h5>
                                <p>There are no scheduled events at the moment.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Calendar & Quick Info -->
            <div class="col-md-4">
                <!-- Mini Calendar -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-calendar me-2"></i><?php echo date('F Y'); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $first_day = date('Y-m-01');
                                    $last_day = date('Y-m-t');
                                    $start_date = date('Y-m-d', strtotime('last sunday', strtotime($first_day)));
                                    $end_date = date('Y-m-d', strtotime('next saturday', strtotime($last_day)));
                                    
                                    $current = $start_date;
                                    while ($current <= $end_date) {
                                        if (date('w', strtotime($current)) == 0) echo '<tr>';
                                        
                                        $day_events = array_filter($calendar_events, function($event) use ($current) {
                                            return $event['event_date'] == $current;
                                        });
                                        
                                        $is_current_month = date('Y-m', strtotime($current)) == $current_month;
                                        $is_today = $current == date('Y-m-d');
                                        
                                        echo '<td class="' . (!$is_current_month ? 'text-muted' : '') . ' ' . ($is_today ? 'bg-light fw-bold' : '') . '" style="height: 40px; vertical-align: top;">';
                                        echo '<small>' . date('j', strtotime($current)) . '</small>';
                                        
                                        if (count($day_events) > 0) {
                                            echo '<div class="text-primary"><i class="fas fa-circle" style="font-size: 6px;"></i></div>';
                                        }
                                        
                                        echo '</td>';
                                        
                                        if (date('w', strtotime($current)) == 6) echo '</tr>';
                                        $current = date('Y-m-d', strtotime($current . ' +1 day'));
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Event Types Legend -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2"></i>Event Types</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <div class="event-academic" style="width: 20px; height: 20px; border-left: 4px solid #007bff; margin-right: 10px;"></div>
                            <span>Academic Events</span>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <div class="event-sports" style="width: 20px; height: 20px; border-left: 4px solid #28a745; margin-right: 10px;"></div>
                            <span>Sports Events</span>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <div class="event-cultural" style="width: 20px; height: 20px; border-left: 4px solid #ffc107; margin-right: 10px;"></div>
                            <span>Cultural Events</span>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <div class="event-holiday" style="width: 20px; height: 20px; border-left: 4px solid #dc3545; margin-right: 10px;"></div>
                            <span>Holidays</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="event-other" style="width: 20px; height: 20px; border-left: 4px solid #6c757d; margin-right: 10px;"></div>
                            <span>Other Events</span>
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
    </script>
</body>
</html>