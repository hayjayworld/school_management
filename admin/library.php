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
$book_id = $_GET['id'] ?? 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = sanitize($_POST['csrf_token']);
    
    if (!validateCSRFToken($csrf_token)) {
        $message = "Security token invalid.";
    } else {
        switch ($_POST['action']) {
            case 'add_book':
                $isbn = sanitize($_POST['isbn']);
                $title = sanitize($_POST['title']);
                $author = sanitize($_POST['author']);
                $publisher = sanitize($_POST['publisher']);
                $publication_year = sanitize($_POST['publication_year']);
                $edition = sanitize($_POST['edition']);
                $category = sanitize($_POST['category']);
                $total_copies = sanitize($_POST['total_copies']);
                $location = sanitize($_POST['location']);
                $description = sanitize($_POST['description']);
                
                $query = "INSERT INTO library_books (isbn, title, author, publisher, publication_year, 
                         edition, category, total_copies, available_copies, location, description) 
                         VALUES (:isbn, :title, :author, :publisher, :publication_year, 
                         :edition, :category, :total_copies, :available_copies, :location, :description)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':isbn', $isbn);
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':author', $author);
                $stmt->bindParam(':publisher', $publisher);
                $stmt->bindParam(':publication_year', $publication_year);
                $stmt->bindParam(':edition', $edition);
                $stmt->bindParam(':category', $category);
                $stmt->bindParam(':total_copies', $total_copies);
                $stmt->bindParam(':available_copies', $total_copies);
                $stmt->bindParam(':location', $location);
                $stmt->bindParam(':description', $description);
                
                if ($stmt->execute()) {
                    $message = "Book added to library successfully!";
                } else {
                    $message = "Error adding book to library.";
                }
                break;
                
            case 'issue_book':
                $book_id = sanitize($_POST['book_id']);
                $student_id = sanitize($_POST['student_id']);
                $issue_date = sanitize($_POST['issue_date']);
                $due_date = sanitize($_POST['due_date']);
                
                // Check book availability
                $check_query = "SELECT available_copies FROM library_books WHERE id = :book_id";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':book_id', $book_id);
                $check_stmt->execute();
                $book = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($book && $book['available_copies'] > 0) {
                    $query = "INSERT INTO book_issues (book_id, student_id, issue_date, due_date, issued_by) 
                             VALUES (:book_id, :student_id, :issue_date, :due_date, :issued_by)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':book_id', $book_id);
                    $stmt->bindParam(':student_id', $student_id);
                    $stmt->bindParam(':issue_date', $issue_date);
                    $stmt->bindParam(':due_date', $due_date);
                    $stmt->bindParam(':issued_by', $_SESSION['user_id']);
                    
                    if ($stmt->execute()) {
                        // Update available copies
                        $update_query = "UPDATE library_books SET available_copies = available_copies - 1 WHERE id = :book_id";
                        $update_stmt = $db->prepare($update_query);
                        $update_stmt->bindParam(':book_id', $book_id);
                        $update_stmt->execute();
                        
                        $message = "Book issued successfully!";
                    } else {
                        $message = "Error issuing book.";
                    }
                } else {
                    $message = "Book is not available for issue.";
                }
                break;
                
            case 'return_book':
                $issue_id = sanitize($_POST['issue_id']);
                
                // Get issue details
                $issue_query = "SELECT * FROM book_issues WHERE id = :issue_id";
                $issue_stmt = $db->prepare($issue_query);
                $issue_stmt->bindParam(':issue_id', $issue_id);
                $issue_stmt->execute();
                $issue = $issue_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($issue) {
                    $return_date = date('Y-m-d');
                    $fine_amount = 0;
                    
                    // Calculate fine if overdue
                    if (strtotime($return_date) > strtotime($issue['due_date'])) {
                        $days_overdue = floor((strtotime($return_date) - strtotime($issue['due_date'])) / (60 * 60 * 24));
                        $fine_amount = $days_overdue * 50; // ₦50 per day
                    }
                    
                    $query = "UPDATE book_issues SET return_date = :return_date, fine_amount = :fine_amount, 
                             status = 'returned' WHERE id = :issue_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':return_date', $return_date);
                    $stmt->bindParam(':fine_amount', $fine_amount);
                    $stmt->bindParam(':issue_id', $issue_id);
                    
                    if ($stmt->execute()) {
                        // Update available copies
                        $update_query = "UPDATE library_books SET available_copies = available_copies + 1 WHERE id = :book_id";
                        $update_stmt = $db->prepare($update_query);
                        $update_stmt->bindParam(':book_id', $issue['book_id']);
                        $update_stmt->execute();
                        
                        $message = "Book returned successfully!" . ($fine_amount > 0 ? " Fine: ₦" . $fine_amount : "");
                    } else {
                        $message = "Error returning book.";
                    }
                }
                break;
        }
    }
}

// Get library books
$books_query = "SELECT * FROM library_books ORDER BY title";
$books = $db->query($books_query)->fetchAll(PDO::FETCH_ASSOC);

// Get book issues with student and book info
$issues_query = "SELECT bi.*, lb.title as book_title, lb.isbn, s.student_id, u.full_name as student_name, 
                        c.name as class_name, us.full_name as issued_by_name
                 FROM book_issues bi 
                 JOIN library_books lb ON bi.book_id = lb.id 
                 JOIN students s ON bi.student_id = s.id 
                 JOIN users u ON s.user_id = u.id 
                 JOIN classes c ON s.class_id = c.id 
                 JOIN users us ON bi.issued_by = us.id 
                 ORDER BY bi.issue_date DESC";
$issues = $db->query($issues_query)->fetchAll(PDO::FETCH_ASSOC);

// Get students for book issuing
$students = $db->query("SELECT s.id, s.student_id, u.full_name, c.name as class_name 
                       FROM students s 
                       JOIN users u ON s.user_id = u.id 
                       JOIN classes c ON s.class_id = c.id 
                       WHERE s.status = 'active' 
                       ORDER BY c.name, u.full_name")->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filtering
$categories_query = "SELECT DISTINCT category FROM library_books WHERE category IS NOT NULL ORDER BY category";
$categories = $db->query($categories_query)->fetchAll(PDO::FETCH_COLUMN);

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
    <title>Library Management - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .book-card { border-left: 4px solid #27ae60; }
        .issue-card { border-left: 4px solid #3498db; }
        .overdue { background-color: #ffe6e6; }
        .scrollable-table { max-height: 400px; overflow-y: auto; }
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
            <a class="nav-link" href="sessions.php"><i class="fas fa-calendar-alt me-2"></i>Sessions</a>
            <a class="nav-link" href="exams.php"><i class="fas fa-file-alt me-2"></i>Exams</a>
            <a class="nav-link" href="fees.php"><i class="fas fa-money-bill me-2"></i>Fees</a>
            <a class="nav-link active" href="library.php"><i class="fas fa-book-open me-2"></i>Library</a>
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
            <h2>Library Management</h2>
            <div>
                <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addBookModal">
                    <i class="fas fa-plus me-2"></i>Add Book
                </button>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#issueBookModal">
                    <i class="fas fa-hand-holding me-2"></i>Issue Book
                </button>
            </div>
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
                                <h4><?php echo count($books); ?></h4>
                                <p class="mb-0">Total Books</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-book fa-2x opacity-50"></i>
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
                                <h4><?php echo array_sum(array_column($books, 'available_copies')); ?></h4>
                                <p class="mb-0">Available Books</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-book-open fa-2x opacity-50"></i>
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
                                <h4><?php echo count(array_filter($issues, function($i) { return $i['status'] == 'issued'; })); ?></h4>
                                <p class="mb-0">Books Issued</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-hand-holding fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card text-white bg-danger">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo count(array_filter($issues, function($i) { 
                                    return $i['status'] == 'issued' && strtotime($i['due_date']) < time(); 
                                })); ?></h4>
                                <p class="mb-0">Overdue Books</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-exclamation-triangle fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Library Books -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Library Books</h5>
                        <div class="d-flex gap-2">
                            <input type="text" id="searchBooks" class="form-control form-control-sm" placeholder="Search books...">
                            <select id="categoryFilter" class="form-select form-select-sm" style="width: 150px;">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category; ?>"><?php echo $category; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="scrollable-table">
                            <table class="table table-striped" id="booksTable">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Author</th>
                                        <th>Category</th>
                                        <th>Available</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($books as $book): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $book['title']; ?></strong>
                                                <?php if ($book['isbn']): ?>
                                                    <br><small class="text-muted">ISBN: <?php echo $book['isbn']; ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $book['author'] ?: 'N/A'; ?></td>
                                            <td><?php echo $book['category'] ?: 'General'; ?></td>
                                            <td>
                                                <?php echo $book['available_copies']; ?>/<?php echo $book['total_copies']; ?>
                                            </td>
                                            <td>
                                                <?php if ($book['available_copies'] > 0): ?>
                                                    <span class="badge bg-success">Available</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Unavailable</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Book Issues -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Book Issues</h5>
                        <input type="text" id="searchIssues" class="form-control form-control-sm" placeholder="Search issues..." style="width: 200px;">
                    </div>
                    <div class="card-body">
                        <div class="scrollable-table">
                            <table class="table table-striped" id="issuesTable">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Book</th>
                                        <th>Issue Date</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($issues as $issue): 
                                        $isOverdue = $issue['status'] == 'issued' && strtotime($issue['due_date']) < time();
                                    ?>
                                        <tr class="<?php echo $isOverdue ? 'overdue' : ''; ?>">
                                            <td>
                                                <strong><?php echo $issue['student_name']; ?></strong>
                                                <br><small class="text-muted"><?php echo $issue['class_name']; ?></small>
                                            </td>
                                            <td><?php echo $issue['book_title']; ?></td>
                                            <td><?php echo formatDate($issue['issue_date']); ?></td>
                                            <td>
                                                <?php echo formatDate($issue['due_date']); ?>
                                                <?php if ($isOverdue): ?>
                                                    <br><small class="text-danger">Overdue</small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo getStatusBadge($issue['status']); ?></td>
                                            <td>
                                                <?php if ($issue['status'] == 'issued'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="return_book">
                                                        <input type="hidden" name="issue_id" value="<?php echo $issue['id']; ?>">
                                                        <button type="submit" class="btn btn-success btn-sm">
                                                            <i class="fas fa-undo me-1"></i>Return
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted">Returned</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Book Modal -->
    <div class="modal fade" id="addBookModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Book to Library</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add_book">
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">ISBN</label>
                                <input type="text" class="form-control" name="isbn" placeholder="International Standard Book Number">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Title *</label>
                                <input type="text" class="form-control" name="title" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Author *</label>
                                <input type="text" class="form-control" name="author" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Publisher</label>
                                <input type="text" class="form-control" name="publisher">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Publication Year</label>
                                <input type="number" class="form-control" name="publication_year" 
                                       min="1900" max="<?php echo date('Y'); ?>" 
                                       placeholder="<?php echo date('Y'); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Edition</label>
                                <input type="text" class="form-control" name="edition" placeholder="e.g., 1st, 2nd">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Category</label>
                                <input type="text" class="form-control" name="category" placeholder="e.g., Fiction, Science, History">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Total Copies *</label>
                                <input type="number" class="form-control" name="total_copies" required min="1" value="1">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Location</label>
                                <input type="text" class="form-control" name="location" placeholder="Shelf location">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Book description..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Book</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Issue Book Modal -->
    <div class="modal fade" id="issueBookModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Issue Book to Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="issue_book">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Student *</label>
                            <select class="form-select" name="student_id" required>
                                <option value="">Select Student</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>">
                                        <?php echo $student['full_name']; ?> (<?php echo $student['student_id']; ?>) - <?php echo $student['class_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Book *</label>
                            <select class="form-select" name="book_id" required>
                                <option value="">Select Book</option>
                                <?php foreach ($books as $book): ?>
                                    <?php if ($book['available_copies'] > 0): ?>
                                        <option value="<?php echo $book['id']; ?>">
                                            <?php echo $book['title']; ?> by <?php echo $book['author']; ?> 
                                            (Available: <?php echo $book['available_copies']; ?>)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Issue Date *</label>
                                <input type="date" class="form-control" name="issue_date" required 
                                       value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Due Date *</label>
                                <input type="date" class="form-control" name="due_date" required 
                                       value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Issue Book</button>
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
        // Search functionality
        document.getElementById('searchBooks').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#booksTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        document.getElementById('searchIssues').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#issuesTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Category filter
        document.getElementById('categoryFilter').addEventListener('change', function() {
            const category = this.value.toLowerCase();
            const rows = document.querySelectorAll('#booksTable tbody tr');
            
            rows.forEach(row => {
                const rowCategory = row.cells[2].textContent.toLowerCase();
                const shouldShow = !category || rowCategory.includes(category);
                row.style.display = shouldShow ? '' : 'none';
            });
        });

        // Auto-calculate due date (14 days from issue date)
        document.querySelector('input[name="issue_date"]').addEventListener('change', function() {
            const issueDate = new Date(this.value);
            const dueDate = new Date(issueDate);
            dueDate.setDate(issueDate.getDate() + 14);
            
            const dueDateInput = document.querySelector('input[name="due_date"]');
            dueDateInput.value = dueDate.toISOString().split('T')[0];
        });
    </script>
</body>
</html>