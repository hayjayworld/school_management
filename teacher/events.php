<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('teacher')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get events
$events_query = "SELECT e.*, u.full_name as created_by_name 
                FROM events e 
                JOIN users u ON e.created_by = u.id 
                WHERE e.is_published = TRUE 
                AND (e.target_audience IN ('all', 'teachers') OR e.target_audience = 'all')
                AND e.event_date >= CURDATE() 
                ORDER BY e.event_date ASC, e.start_time ASC";
$events = $db->query($events_query)->fetchAll(PDO::FETCH_ASSOC);

// Get current month events for calendar
$current_month = date('Y-m');
$calendar_events_query = "SELECT event_date, title, event_type 
                         FROM events 
                         WHERE is_published = TRUE 
                         AND (target_audience IN ('all', 'teachers') OR target_audience = 'all')
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
        .calendar-day { position: relative; min-height: 100px; border: 1px solid #dee2e6; }
        .calendar-event { font-size: 0.8rem; padding: 2px 5px; margin: 1px; border-radius: 3px; }
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
            <a class="nav-link active" href="events.php"><i class="fas fa-calendar me-2"></i>Events</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a>
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>School Events & Calendar</h2>
            <div class="btn-group">
                <button class="btn btn-outline-primary" id="viewList">
                    <i class="fas fa-list me-2"></i>List View
                </button>
                <button class="btn btn-primary" id="viewCalendar">
                    <i class="fas fa-calendar me-2"></i>Calendar View
                </button>
            </div>
        </div>

        <div class="row">
            <!-- Upcoming Events List -->
            <div class="col-md-8" id="listView">
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
                                                    <span class="badge bg-info">
                                                        <i class="fas fa-users me-1"></i><?php echo ucfirst($event['target_audience']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="col-md-2 text-end">
                                                <small class="text-muted">
                                                    Created by:<br>
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

            <!-- Calendar & Quick Stats -->
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
                                        
                                        echo '<td class="' . (!$is_current_month ? 'text-muted' : '') . ' ' . ($is_today ? 'bg-light' : '') . '" style="height: 40px; vertical-align: top;">';
                                        echo '<small>' . date('j', strtotime($current)) . '</small>';
                                        
                                        foreach ($day_events as $event) {
                                            $bg_color = '';
                                            switch($event['event_type']) {
                                                case 'academic': $bg_color = 'bg-primary'; break;
                                                case 'sports': $bg_color = 'bg-success'; break;
                                                case 'cultural': $bg_color = 'bg-warning'; break;
                                                case 'holiday': $bg_color = 'bg-danger'; break;
                                                default: $bg_color = 'bg-secondary';
                                            }
                                            echo '<div class="calendar-event ' . $bg_color . ' text-white">' . substr($event['title'], 0, 10) . '...</div>';
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

                <!-- Event Statistics -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-chart-pie me-2"></i>Event Statistics</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $event_counts = [];
                        foreach ($events as $event) {
                            $type = $event['event_type'];
                            $event_counts[$type] = ($event_counts[$type] ?? 0) + 1;
                        }
                        ?>
                        <div class="row text-center">
                            <div class="col-4">
                                <h4 class="text-primary"><?php echo count($events); ?></h4>
                                <small>Total Events</small>
                            </div>
                            <div class="col-4">
                                <h4 class="text-success"><?php echo $event_counts['academic'] ?? 0; ?></h4>
                                <small>Academic</small>
                            </div>
                            <div class="col-4">
                                <h4 class="text-warning"><?php echo $event_counts['sports'] ?? 0; ?></h4>
                                <small>Sports</small>
                            </div>
                        </div>
                        <hr>
                        <div class="small">
                            <div class="d-flex justify-content-between">
                                <span>Cultural Events:</span>
                                <strong><?php echo $event_counts['cultural'] ?? 0; ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Holidays:</span>
                                <strong><?php echo $event_counts['holiday'] ?? 0; ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Other Events:</span>
                                <strong><?php echo $event_counts['other'] ?? 0; ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Calendar View (Hidden by Default) -->
        <div class="row d-none" id="calendarView">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-calendar me-2"></i>Monthly Calendar</h5>
                    </div>
                    <div class="card-body">
                        <!-- Full calendar view would be implemented here -->
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-calendar fa-3x mb-3"></i>
                            <h5>Full Calendar View</h5>
                            <p>This would show a full monthly calendar with all events.</p>
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
        // Toggle between list and calendar view
        document.getElementById('viewList').addEventListener('click', function() {
            document.getElementById('listView').classList.remove('d-none');
            document.getElementById('calendarView').classList.add('d-none');
            this.classList.add('btn-primary');
            this.classList.remove('btn-outline-primary');
            document.getElementById('viewCalendar').classList.remove('btn-primary');
            document.getElementById('viewCalendar').classList.add('btn-outline-primary');
        });

        document.getElementById('viewCalendar').addEventListener('click', function() {
            document.getElementById('listView').classList.add('d-none');
            document.getElementById('calendarView').classList.remove('d-none');
            this.classList.add('btn-primary');
            this.classList.remove('btn-outline-primary');
            document.getElementById('viewList').classList.remove('btn-primary');
            document.getElementById('viewList').classList.add('btn-outline-primary');
        });
    </script>
</body>
</html>