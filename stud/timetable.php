<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('student')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();
$student_id = $_SESSION['user_id'];

// Get student details and class
$student_query = "SELECT s.*, u.full_name, u.gender, c.name as class_name, c.section 
                 FROM students s 
                 JOIN users u ON s.user_id = u.id 
                 JOIN classes c ON s.class_id = c.id 
                 WHERE s.user_id = :user_id";
$stmt = $db->prepare($student_query);
$stmt->bindParam(':user_id', $student_id);
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get student's class timetable
$timetable_query = "SELECT t.*, c.name as class_name, s.name as subject_name, 
                   u.full_name as teacher_name, ses.name as session_name
                   FROM timetable t 
                   JOIN classes c ON t.class_id = c.id 
                   JOIN subjects s ON t.subject_id = s.id 
                   JOIN users u ON t.teacher_id = u.id 
                   JOIN academic_sessions ses ON t.session_id = ses.id 
                   WHERE t.class_id = :class_id 
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
$stmt->bindParam(':class_id', $student['class_id']);
$stmt->execute();
$timetable_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Get unique teachers
$teachers = array_unique(array_column($timetable_entries, 'teacher_name'));
sort($teachers);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Class Timetable - Excel Schools</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { background: #2c3e50; color: white; min-height: 100vh; position: fixed; width: 250px; }
        .sidebar .nav-link { color: white; padding: 15px 20px; border-bottom: 1px solid #34495e; }
        .sidebar .nav-link:hover { background: #34495e; }
        .sidebar .nav-link.active { background: #3498db; }
        .main-content { margin-left: 250px; padding: 20px; }
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
        .subject-badge {
            font-size: 0.7rem;
            margin-bottom: 2px;
        }
        .teacher-name {
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
        .teacher-card {
            transition: transform 0.2s;
        }
        .teacher-card:hover {
            transform: translateY(-2px);
        }
        @media (max-width: 768px) {
            .sidebar { width: 100%; position: relative; min-height: auto; }
            .main-content { margin-left: 0; }
            .timetable-cell { height: 60px; font-size: 0.7rem; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="p-4 text-center">
            <h4><i class="fas fa-graduation-cap"></i> Excel Schools</h4>
            <p class="text-muted small">Student Portal</p>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>My Profile</a>
            <a class="nav-link" href="results.php"><i class="fas fa-chart-line me-2"></i>My Results</a>
            <a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check me-2"></i>Attendance</a>
            <a class="nav-link" href="cbt.php"><i class="fas fa-laptop me-2"></i>CBT Exams</a>
            <a class="nav-link active" href="timetable.php"><i class="fas fa-table me-2"></i>Class Timetable</a>
            <a class="nav-link" href="events.php"><i class="fas fa-calendar me-2"></i>School Events</a>
            <a class="nav-link" href="library.php"><i class="fas fa-book me-2"></i>Library</a>
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>My Class Timetable</h2>
            <div class="text-end">
                <h6 class="mb-0"><?php echo $student['full_name']; ?></h6>
                <small class="text-muted"><?php echo $student['class_name']; ?></small>
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
                                <p class="mb-0">Weekly Classes</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-table fa-2x opacity-50"></i>
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
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo count($teachers); ?></h4>
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
                        <i class="fas fa-calendar-day me-2"></i>Today's Classes (<?php echo ucfirst($today); ?>)
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
                                            <strong>Teacher:</strong> <?php echo $schedule['teacher_name']; ?>
                                        </p>
                                        <p class="card-text mb-1">
                                            <strong>Time:</strong> 
                                            <?php echo date('g:i A', strtotime($schedule['start_time'])); ?> - 
                                            <?php echo date('g:i A', strtotime($schedule['end_time'])); ?>
                                        </p>
                                        <p class="card-text mb-0">
                                            <strong>Room:</strong> <?php echo $schedule['room'] ?: 'N/A'; ?>
                                        </p>
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
                No classes scheduled for today.
            </div>
        <?php endif; ?>

        <!-- Full Timetable -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Weekly Class Timetable</h5>
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
                                                <div class="subject-badge badge bg-primary">
                                                    <?php echo $entry['subject_name']; ?>
                                                </div>
                                                <div class="teacher-name">
                                                    <?php echo $entry['teacher_name']; ?>
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

        <!-- Teachers List -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">My Teachers</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($teachers as $teacher): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card teacher-card">
                                <div class="card-body text-center">
                                    <div class="teacher-avatar mb-3">
                                        <i class="fas fa-chalkboard-teacher fa-3x text-primary"></i>
                                    </div>
                                    <h6 class="card-title"><?php echo $teacher; ?></h6>
                                    <p class="card-text text-muted small">
                                        <?php 
                                        $teacher_subjects = array_unique(array_filter(array_column(
                                            array_filter($timetable_entries, function($entry) use ($teacher) {
                                                return $entry['teacher_name'] == $teacher;
                                            }), 'subject_name'
                                        )));
                                        echo implode(', ', $teacher_subjects);
                                        ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Subjects Summary -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Subjects Summary</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Teacher</th>
                                <th>Days</th>
                                <th>Time Slots</th>
                                <th>Room</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $subjects_summary = [];
                            foreach ($timetable_entries as $entry) {
                                $subject = $entry['subject_name'];
                                if (!isset($subjects_summary[$subject])) {
                                    $subjects_summary[$subject] = [
                                        'teacher' => $entry['teacher_name'],
                                        'days' => [],
                                        'times' => [],
                                        'room' => $entry['room']
                                    ];
                                }
                                $subjects_summary[$subject]['days'][] = ucfirst($entry['day_of_week']);
                                $subjects_summary[$subject]['times'][] = date('g:i A', strtotime($entry['start_time'])) . ' - ' . date('g:i A', strtotime($entry['end_time']));
                            }
                            
                            foreach ($subjects_summary as $subject => $details): 
                                $unique_days = array_unique($details['days']);
                                $unique_times = array_unique($details['times']);
                            ?>
                                <tr>
                                    <td><strong><?php echo $subject; ?></strong></td>
                                    <td><?php echo $details['teacher']; ?></td>
                                    <td><?php echo implode(', ', $unique_days); ?></td>
                                    <td><?php echo implode('<br>', $unique_times); ?></td>
                                    <td><?php echo $details['room'] ?: 'N/A'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>