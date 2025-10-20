<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? 'list';
$message = '';
$fee_id = $_GET['id'] ?? 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = sanitize($_POST['csrf_token']);
    
    if (!validateCSRFToken($csrf_token)) {
        $message = "Security token invalid.";
    } else {
        switch ($_POST['action']) {
            case 'add_fee_structure':
                $class_id = sanitize($_POST['class_id']);
                $session_id = sanitize($_POST['session_id']);
                $term = sanitize($_POST['term']);
                $fee_type = sanitize($_POST['fee_type']);
                $amount = sanitize($_POST['amount']);
                $due_date = sanitize($_POST['due_date']);
                $is_optional = isset($_POST['is_optional']) ? 1 : 0;
                
                $query = "INSERT INTO fee_structure (class_id, session_id, term, fee_type, amount, due_date, is_optional) 
                         VALUES (:class_id, :session_id, :term, :fee_type, :amount, :due_date, :is_optional)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':class_id', $class_id);
                $stmt->bindParam(':session_id', $session_id);
                $stmt->bindParam(':term', $term);
                $stmt->bindParam(':fee_type', $fee_type);
                $stmt->bindParam(':amount', $amount);
                $stmt->bindParam(':due_date', $due_date);
                $stmt->bindParam(':is_optional', $is_optional);
                
                if ($stmt->execute()) {
                    $message = "Fee structure added successfully!";
                } else {
                    $message = "Error adding fee structure.";
                }
                break;
                
            case 'record_payment':
                $student_id = sanitize($_POST['student_id']);
                $fee_structure_id = sanitize($_POST['fee_structure_id']);
                $amount_paid = sanitize($_POST['amount_paid']);
                $payment_date = sanitize($_POST['payment_date']);
                $payment_method = sanitize($_POST['payment_method']);
                $transaction_id = sanitize($_POST['transaction_id']);
                $receipt_number = sanitize($_POST['receipt_number']);
                $notes = sanitize($_POST['notes']);
                
                $query = "INSERT INTO fee_payments (student_id, fee_structure_id, amount_paid, payment_date, 
                         payment_method, transaction_id, receipt_number, notes, recorded_by) 
                         VALUES (:student_id, :fee_structure_id, :amount_paid, :payment_date, 
                         :payment_method, :transaction_id, :receipt_number, :notes, :recorded_by)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':student_id', $student_id);
                $stmt->bindParam(':fee_structure_id', $fee_structure_id);
                $stmt->bindParam(':amount_paid', $amount_paid);
                $stmt->bindParam(':payment_date', $payment_date);
                $stmt->bindParam(':payment_method', $payment_method);
                $stmt->bindParam(':transaction_id', $transaction_id);
                $stmt->bindParam(':receipt_number', $receipt_number);
                $stmt->bindParam(':notes', $notes);
                $stmt->bindParam(':recorded_by', $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $message = "Payment recorded successfully! Receipt: " . $receipt_number;
                } else {
                    $message = "Error recording payment.";
                }
                break;
        }
    }
}

// Get data for dropdowns
$classes = $db->query("SELECT * FROM classes WHERE status = 'active' ORDER BY section, name")->fetchAll(PDO::FETCH_ASSOC);
$sessions = $db->query("SELECT * FROM academic_sessions ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$current_session = $db->query("SELECT * FROM academic_sessions WHERE status = 'active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

// Get fee structures
$fee_structures_query = "SELECT fs.*, c.name as class_name, s.name as session_name 
                        FROM fee_structure fs 
                        JOIN classes c ON fs.class_id = c.id 
                        JOIN academic_sessions s ON fs.session_id = s.id 
                        ORDER BY s.name, fs.term, c.name";
$fee_structures = $db->query($fee_structures_query)->fetchAll(PDO::FETCH_ASSOC);

// Get fee payments with student info
$payments_query = "SELECT fp.*, s.student_id, u.full_name as student_name, c.name as class_name,
                          fs.fee_type, fs.amount as expected_amount
                   FROM fee_payments fp 
                   JOIN students s ON fp.student_id = s.id 
                   JOIN users u ON s.user_id = u.id 
                   JOIN classes c ON s.class_id = c.id 
                   JOIN fee_structure fs ON fp.fee_structure_id = fs.id 
                   ORDER BY fp.payment_date DESC";
$payments = $db->query($payments_query)->fetchAll(PDO::FETCH_ASSOC);

// Get students for payment recording
$students_query = "SELECT s.id, s.student_id, u.full_name, c.name as class_name 
                   FROM students s 
                   JOIN users u ON s.user_id = u.id 
                   JOIN classes c ON s.class_id = c.id 
                   WHERE s.status = 'active' 
                   ORDER BY c.name, u.full_name";
$students = $db->query($students_query)->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Fees Management - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .fee-card { border-left: 4px solid #27ae60; }
        .payment-card { border-left: 4px solid #3498db; }
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
            <a class="nav-link active" href="fees.php"><i class="fas fa-money-bill me-2"></i>Fees</a>
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
            <h2>Fees Management</h2>
            <div>
                <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addFeeStructureModal">
                    <i class="fas fa-plus me-2"></i>Add Fee Structure
                </button>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#recordPaymentModal">
                    <i class="fas fa-money-bill me-2"></i>Record Payment
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
                                <h4><?php echo count($fee_structures); ?></h4>
                                <p class="mb-0">Fee Structures</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-list fa-2x opacity-50"></i>
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
                                <h4><?php echo count($payments); ?></h4>
                                <p class="mb-0">Total Payments</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-receipt fa-2x opacity-50"></i>
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
                                <h4>₦<?php echo number_format(array_sum(array_column($payments, 'amount_paid')), 2); ?></h4>
                                <p class="mb-0">Total Collected</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-money-bill-wave fa-2x opacity-50"></i>
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
                                <h4><?php echo count(array_filter($payments, function($p) { return $p['status'] == 'pending'; })); ?></h4>
                                <p class="mb-0">Pending Payments</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-clock fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Fee Structures -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Fee Structures</h5>
                        <input type="text" id="searchFees" class="form-control form-control-sm" placeholder="Search fees..." style="width: 200px;">
                    </div>
                    <div class="card-body">
                        <div class="table-responsive" style="max-height: 400px;">
                            <table class="table table-striped" id="feeStructuresTable">
                                <thead>
                                    <tr>
                                        <th>Fee Type</th>
                                        <th>Class</th>
                                        <th>Term</th>
                                        <th>Amount</th>
                                        <th>Due Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fee_structures as $fee): ?>
                                        <tr>
                                            <td>
                                                <?php echo $fee['fee_type']; ?>
                                                <?php if ($fee['is_optional']): ?>
                                                    <span class="badge bg-warning">Optional</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $fee['class_name']; ?></td>
                                            <td><?php echo ucfirst($fee['term']); ?> Term</td>
                                            <td>₦<?php echo number_format($fee['amount'], 2); ?></td>
                                            <td><?php echo formatDate($fee['due_date']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Payments -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Recent Payments</h5>
                        <input type="text" id="searchPayments" class="form-control form-control-sm" placeholder="Search payments..." style="width: 200px;">
                    </div>
                    <div class="card-body">
                        <div class="table-responsive" style="max-height: 400px;">
                            <table class="table table-striped" id="paymentsTable">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Fee Type</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $payment['student_name']; ?></strong>
                                                <br><small class="text-muted"><?php echo $payment['class_name']; ?></small>
                                            </td>
                                            <td><?php echo $payment['fee_type']; ?></td>
                                            <td>
                                                ₦<?php echo number_format($payment['amount_paid'], 2); ?>
                                                <?php if ($payment['amount_paid'] < $payment['expected_amount']): ?>
                                                    <br><small class="text-danger">Balance: ₦<?php echo number_format($payment['expected_amount'] - $payment['amount_paid'], 2); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatDate($payment['payment_date']); ?></td>
                                            <td><?php echo getStatusBadge($payment['status']); ?></td>
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

    <!-- Add Fee Structure Modal -->
    <div class="modal fade" id="addFeeStructureModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Fee Structure</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add_fee_structure">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Class *</label>
                            <select class="form-select" name="class_id" required>
                                <option value="">Select Class</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>"><?php echo $class['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Academic Session *</label>
                            <select class="form-select" name="session_id" required>
                                <option value="">Select Session</option>
                                <?php foreach ($sessions as $session): ?>
                                    <option value="<?php echo $session['id']; ?>" <?php echo $session['status'] == 'active' ? 'selected' : ''; ?>>
                                        <?php echo $session['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Term *</label>
                            <select class="form-select" name="term" required>
                                <option value="first">First Term</option>
                                <option value="second">Second Term</option>
                                <option value="third">Third Term</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Fee Type *</label>
                            <input type="text" class="form-control" name="fee_type" required 
                                   placeholder="e.g., Tuition Fee, Development Levy">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Amount (₦) *</label>
                            <input type="number" class="form-control" name="amount" required 
                                   step="0.01" min="0" placeholder="0.00">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Due Date</label>
                            <input type="date" class="form-control" name="due_date">
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_optional" id="is_optional">
                            <label class="form-check-label" for="is_optional">
                                Optional Fee (Not mandatory for all students)
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Fee Structure</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Record Payment Modal -->
    <div class="modal fade" id="recordPaymentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Record Fee Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="record_payment">
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Student *</label>
                                <select class="form-select" name="student_id" id="studentSelect" required>
                                    <option value="">Select Student</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?php echo $student['id']; ?>" data-class="<?php echo $student['class_name']; ?>">
                                            <?php echo $student['full_name']; ?> (<?php echo $student['student_id']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Class</label>
                                <input type="text" class="form-control" id="studentClass" readonly>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fee Type *</label>
                                <select class="form-select" name="fee_structure_id" id="feeStructureSelect" required>
                                    <option value="">Select Fee Type</option>
                                    <!-- Will be populated by JavaScript -->
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Expected Amount</label>
                                <input type="text" class="form-control" id="expectedAmount" readonly>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Amount Paid (₦) *</label>
                                <input type="number" class="form-control" name="amount_paid" required 
                                       step="0.01" min="0" placeholder="0.00">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Date *</label>
                                <input type="date" class="form-control" name="payment_date" required 
                                       value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Method *</label>
                                <select class="form-select" name="payment_method" required>
                                    <option value="cash">Cash</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="online">Online Payment</option>
                                    <option value="cheque">Cheque</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Transaction ID</label>
                                <input type="text" class="form-control" name="transaction_id" 
                                       placeholder="For bank/online payments">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Receipt Number *</label>
                                <input type="text" class="form-control" name="receipt_number" required 
                                       value="RCPT<?php echo date('YmdHis'); ?>" readonly>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Any additional notes..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Record Payment</button>
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
        document.getElementById('searchFees').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#feeStructuresTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        document.getElementById('searchPayments').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#paymentsTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Student and fee structure selection
        document.getElementById('studentSelect').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const className = selectedOption.getAttribute('data-class');
            document.getElementById('studentClass').value = className || '';
            
            // Update fee structures based on class
            updateFeeStructures(className);
        });

        function updateFeeStructures(className) {
            const feeStructureSelect = document.getElementById('feeStructureSelect');
            feeStructureSelect.innerHTML = '<option value="">Select Fee Type</option>';
            
            if (!className) return;
            
            // This would typically make an AJAX call to get fee structures for the class
            // For now, we'll simulate with available data
            const feeStructures = <?php echo json_encode($fee_structures); ?>;
            const matchingFees = feeStructures.filter(fee => fee.class_name === className);
            
            matchingFees.forEach(fee => {
                const option = document.createElement('option');
                option.value = fee.id;
                option.textContent = `${fee.fee_type} - ₦${parseFloat(fee.amount).toLocaleString()} (${fee.term} Term)`;
                option.setAttribute('data-amount', fee.amount);
                feeStructureSelect.appendChild(option);
            });
        }

        document.getElementById('feeStructureSelect').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const amount = selectedOption.getAttribute('data-amount');
            document.getElementById('expectedAmount').value = amount ? `₦${parseFloat(amount).toLocaleString()}` : '';
        });

        // Auto-generate receipt number
        function generateReceiptNumber() {
            const timestamp = new Date().getTime();
            return 'RCPT' + timestamp;
        }
    </script>
</body>
</html>