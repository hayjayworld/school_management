<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('teacher')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$teacher_id = $_SESSION['user_id'];

// Get teacher's assigned classes and students
$students_query = "SELECT DISTINCT s.*, u.full_name, u.gender, c.name as class_name 
                  FROM students s 
                  JOIN users u ON s.user_id = u.id 
                  JOIN classes c ON s.class_id = c.id 
                  JOIN subjects sub ON c.id = sub.class_id 
                  WHERE sub.teacher_id = :teacher_id 
                  ORDER BY c.name, u.full_name";
$stmt = $db->prepare($students_query);
$stmt->bindParam(':teacher_id', $teacher_id);
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get assigned classes for filter
$classes_query = "SELECT DISTINCT c.id, c.name 
                 FROM classes c 
                 JOIN subjects s ON c.id = s.class_id 
                 WHERE s.teacher_id = :teacher_id 
                 ORDER BY c.name";
$stmt = $db->prepare($classes_query);
$stmt->bindParam(':teacher_id', $teacher_id);
$stmt->execute();
$assigned_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>My Students - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
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
            <a class="nav-link" href="students.php"><i class="fas fa-users me-2"></i>Students</a>
            <a class="nav-link active" href="subjects.php"><i class="fas fa-book me-2"></i>My Subjects</a>
            <a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check me-2"></i>Attendance</a>
            <a class="nav-link" href="results.php"><i class="fas fa-chart-line me-2"></i>Results</a>
            <a class="nav-link" href="exams.php"><i class="fas fa-file-alt me-2"></i>CBT Exams</a>
            <a class="nav-link" href="timetable.php"><i class="fas fa-table me-2"></i>Timetable</a>
            <a class="nav-link" href="events.php"><i class="fas fa-calendar me-2"></i>Events</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a>
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </div>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h2>My Students</h2>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="me-3">Welcome, <?php echo $_SESSION['full_name']; ?></span>
                    </div>
                </div>

                <!-- Class Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Filter by Class:</label>
                                <select class="form-select" id="classFilter">
                                    <option value="">All Classes</option>
                                    <?php foreach ($assigned_classes as $class): ?>
                                        <option value="<?php echo $class['name']; ?>">
                                            <?php echo $class['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Search Students:</label>
                                <input type="text" class="form-control" id="searchStudents" placeholder="Search by name or ID...">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Students List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Students List</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped" id="studentsTable">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Full Name</th>
                                        <th>Class</th>
                                        <th>Gender</th>
                                        <th>Parent</th>
                                        <th>Contact</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td><?php echo $student['student_id']; ?></td>
                                            <td><?php echo $student['full_name']; ?></td>
                                            <td><?php echo $student['class_name']; ?></td>
                                            <td><?php echo ucfirst($student['gender']); ?></td>
                                            <td><?php echo $student['parent_name']; ?></td>
                                            <td><?php echo $student['parent_phone']; ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="viewResults(<?php echo $student['id']; ?>)">
                                                    <i class="fas fa-chart-bar"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info"
                                                        onclick="viewProfile(<?php echo $student['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
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
        // Filter functionality
        document.getElementById('classFilter').addEventListener('change', filterStudents);
        document.getElementById('searchStudents').addEventListener('input', filterStudents);

        function filterStudents() {
            const classFilter = document.getElementById('classFilter').value.toLowerCase();
            const searchFilter = document.getElementById('searchStudents').value.toLowerCase();
            const rows = document.querySelectorAll('#studentsTable tbody tr');
            
            rows.forEach(row => {
                const className = row.cells[2].textContent.toLowerCase();
                const studentName = row.cells[1].textContent.toLowerCase();
                const studentId = row.cells[0].textContent.toLowerCase();
                
                const classMatch = !classFilter || className === classFilter;
                const searchMatch = !searchFilter || 
                    studentName.includes(searchFilter) || 
                    studentId.includes(searchFilter);
                
                row.style.display = classMatch && searchMatch ? '' : 'none';
            });
        }

        function viewResults(studentId) {
            alert('View results for student: ' + studentId);
            // Implement view results functionality
        }

        function viewProfile(studentId) {
            alert('View profile for student: ' + studentId);
            // Implement view profile functionality
        }
    </script>
</body>
</html>