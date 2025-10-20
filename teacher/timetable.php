<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('teacher')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();
$teacher_id = $_SESSION['user_id'];

// Get teacher's timetable
$timetable_query = "SELECT t.*, c.name as class_name, s.name as subject_name, 
                   ses.name as session_name
                   FROM timetable t 
                   JOIN classes c ON t.class_id = c.id 
                   JOIN subjects s ON t.subject_id = s.id 
                   JOIN academic_sessions ses ON t.session_id = ses.id 
                   WHERE t.teacher_id = :teacher_id 
                   AND t.status = 'active'
                   ORDER BY 
                   CASE t.day_of_week 
                       WHEN 'monday' THEN 1 
                       WHEN 'tuesday' THEN 2 
                       WHEN 'wednesday' THEN 3 
                       WHEN 'thursday' THEN 4 
                       WHEN 'friday' THEN 5 
                       WHEN 'saturday' THEN 6 
                   END, t.start_time";

$stmt = $db->prepare($timetable_query);
$stmt->bindParam(':teacher_id', $teacher_id);
$stmt->execute();
$timetable_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get teacher details
$teacher_query = "SELECT u.full_name FROM users u WHERE u.id = :teacher_id";
$stmt = $db->prepare($teacher_query);
$stmt->bindParam(':teacher_id', $teacher_id);
$stmt->execute();
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

// Generate timetable grid
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
    $timetable_grid[$entry['day_of_week']][$entry['start_time']] = $entry;
}

// Get today's schedule
$today = strtolower(date('l'));
$today_schedule = array_filter($timetable_entries, function($entry) use ($today) {
    return $entry['day_of_week'] == $today;
});

// Sort today's schedule by time
usort($today_schedule, function($a, $b) {
    return strtotime($a['start_time']) - strtotime($b['start_time']);
});

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
    <title>My Timetable - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
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
        .class-badge {
            font-size: 0.7rem;
            margin-bottom: 2px;
        }
        .subject-name {
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
        .today-highlight {
            background-color: #fff3cd !important;
            border-color: #ffc107 !important;
        }
        @media (max-width: 768px) {
            .timetable-cell { height: 60px; font-size: 0.7rem; }
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
        <nav class="nav flex-column">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
            <a class="nav-link" href="subjects.php"><i class="fas fa-book me-2"></i>My Subjects</a>
            <a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check me-2"></i>Attendance</a>
            <a class="nav-link" href="results.php"><i class="fas fa-chart-line me-2"></i>Results</a>
            <a class="nav-link" href="exams.php"><i class="fas fa-laptop me-2"></i>CBT Exams</a>
            <a class="nav-link active" href="timetable.php"><i class="fas fa-table me-2"></i>Timetable</a>
            <a class="nav-link" href="events.php"><i class="fas fa-calendar me-2"></i>Events</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a>
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>My Teaching Timetable</h2>
            <div class="text-end">
                <h6 class="mb-0">Welcome, <?php echo $teacher['full_name']; ?></h6>
                <small class="text-muted"><?php echo count($timetable_entries); ?> scheduled classes</small>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo count($timetable_entries); ?></h4>
                                <p class="mb-0">Total Classes</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-door-open fa-2x opacity-50"></i>
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
                                <h4><?php echo count(array_unique(array_column($timetable_entries, 'class_id'))); ?></h4>
                                <p class="mb-0">Different Classes</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-2x opacity-50"></i>
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
                                <h4><?php echo count(array_unique(array_column($timetable_entries, 'subject_id'))); ?></h4>
                                <p class="mb-0">Subjects</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-book fa-2x opacity-50"></i>
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
                                <h4><?php echo count($today_schedule); ?></h4>
                                <p class="mb-0">Today's Classes</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-calendar-day fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Schedule -->
        <?php if (count($today_schedule) > 0): ?>
            <div class="card mb-4">
                <div class="card-header bg-warning">
                    <h5 class="card-title mb-0 text-white">
                        <i class="fas fa-calendar-day me-2"></i>Today's Schedule (<?php echo ucfirst($today); ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($today_schedule as $schedule): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card border-warning">
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo $schedule['subject_name']; ?></h6>
                                        <p class="card-text mb-1">
                                            <strong>Class:</strong> <?php echo $schedule['class_name']; ?>
                                        </p>
                                        <p class="card-text mb-1">
                                            <strong>Time:</strong> 
                                            <?php echo date('g:i A', strtotime($schedule['start_time'])); ?> - 
                                            <?php echo date('g:i A', strtotime($schedule['end_time'])); ?>
                                        </p>
                                        <p class="card-text mb-0">
                                            <strong>Room:</strong> <?php echo $schedule['room'] ?: 'N/A'; ?>
                                        </p>
                                        <div class="mt-2">
                                            <a href="attendance.php?class_id=<?php echo $schedule['class_id']; ?>&subject_id=<?php echo $schedule['subject_id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-calendar-check me-1"></i>Take Attendance
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info mb-4">
                <i class="fas fa-info-circle me-2"></i>
                No classes scheduled for today. Enjoy your day!
            </div>
        <?php endif; ?>

        <!-- Full Timetable -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Weekly Timetable</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th class="time-slot-header">Time</th>
                                <?php foreach ($days as $day): ?>
                                    <th class="timetable-header <?php echo $day == $today ? 'today-highlight' : ''; ?>">
                                        <?php echo ucfirst($day); ?>
                                        <?php if ($day == $today): ?>
                                            <br><small class="text-warning">Today</small>
                                        <?php endif; ?>
                                    </th>
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
                                        <td class="timetable-cell <?php echo $timetable_grid[$day][$time_slot] ? 'filled' : ''; ?> <?php echo $day == $today ? 'today-highlight' : ''; ?>">
                                            <?php if ($timetable_grid[$day][$time_slot]): ?>
                                                <?php $entry = $timetable_grid[$day][$time_slot]; ?>
                                                <div class="class-badge badge bg-primary">
                                                    <?php echo $entry['class_name']; ?>
                                                </div>
                                                <div class="subject-name">
                                                    <?php echo $entry['subject_name']; ?>
                                                </div>
                                                <div class="room-number">
                                                    <?php echo $entry['room'] ?: 'N/A'; ?>
                                                </div>
                                            <?php else: ?>
                                                <small class="text-muted">Free Period</small>
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

        <!-- Class List -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">My Classes Summary</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Class</th>
                                <th>Subject</th>
                                <th>Day</th>
                                <th>Time</th>
                                <th>Room</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($timetable_entries as $entry): ?>
                                <tr>
                                    <td><?php echo $entry['class_name']; ?></td>
                                    <td><?php echo $entry['subject_name']; ?></td>
                                    <td><?php echo ucfirst($entry['day_of_week']); ?></td>
                                    <td>
                                        <?php echo date('g:i A', strtotime($entry['start_time'])); ?> - 
                                        <?php echo date('g:i A', strtotime($entry['end_time'])); ?>
                                    </td>
                                    <td><?php echo $entry['room'] ?: 'N/A'; ?></td>
                                    <td>
                                        <a href="attendance.php?class_id=<?php echo $entry['class_id']; ?>&subject_id=<?php echo $entry['subject_id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-calendar-check me-1"></i>Attendance
                                        </a>
                                        <a href="results.php?class_id=<?php echo $entry['class_id']; ?>&subject_id=<?php echo $entry['subject_id']; ?>" 
                                           class="btn btn-sm btn-outline-success">
                                            <i class="fas fa-chart-line me-1"></i>Results
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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