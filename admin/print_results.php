<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get print parameters
$class_id = $_GET['class_id'] ?? '';
$session_id = $_GET['session_id'] ?? '';
$term = $_GET['term'] ?? 'first';
$student_id = $_GET['student_id'] ?? '';

// Get school information
$school_settings = [];
$settings_query = "SELECT setting_key, setting_value FROM system_settings WHERE is_public = TRUE";
$settings_result = $db->query($settings_query);
while ($setting = $settings_result->fetch(PDO::FETCH_ASSOC)) {
    $school_settings[$setting['setting_key']] = $setting['setting_value'];
}

// Get class and session details
$class_query = "SELECT * FROM classes WHERE id = :class_id";
$class_stmt = $db->prepare($class_query);
$class_stmt->bindParam(':class_id', $class_id);
$class_stmt->execute();
$class = $class_stmt->fetch(PDO::FETCH_ASSOC);

$session_query = "SELECT * FROM academic_sessions WHERE id = :session_id";
$session_stmt = $db->prepare($session_query);
$session_stmt->bindParam(':session_id', $session_id);
$session_stmt->execute();
$session = $session_stmt->fetch(PDO::FETCH_ASSOC);

// Get students for the class and session
$students_query = "SELECT s.id, s.student_id, u.full_name, u.gender, u.date_of_birth
                  FROM students s 
                  JOIN users u ON s.user_id = u.id 
                  WHERE s.class_id = :class_id AND s.session_id = :session_id AND s.status = 'active'
                  ORDER BY u.full_name";
$students_stmt = $db->prepare($students_query);
$students_stmt->bindParam(':class_id', $class_id);
$students_stmt->bindParam(':session_id', $session_id);
$students_stmt->execute();
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to get student results
function getStudentResults($db, $student_id, $session_id, $term) {
    $query = "SELECT ar.*, sub.name as subject_name, sub.code as subject_code 
             FROM academic_results ar 
             JOIN subjects sub ON ar.subject_id = sub.id 
             WHERE ar.student_id = :student_id AND ar.session_id = :session_id AND ar.term = :term 
             ORDER BY sub.name";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->bindParam(':session_id', $session_id);
    $stmt->bindParam(':term', $term);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get student attendance summary
function getAttendanceSummary($db, $student_id, $session_id, $term) {
    $query = "SELECT 
              COUNT(*) as total_days,
              SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
              SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
              SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days
              FROM attendance 
              WHERE student_id = :student_id AND session_id = :session_id AND term = :term";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->bindParam(':session_id', $session_id);
    $stmt->bindParam(':term', $term);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to calculate overall performance
function calculateOverallPerformance($results) {
    if (empty($results)) {
        return null;
    }
    
    $total_subjects = count($results);
    $total_score = 0;
    $total_max_score = 0;
    
    foreach ($results as $result) {
        $total_score += $result['total_score'];
        $total_max_score += 100; // Assuming max score per subject is 100
    }
    
    $overall_percentage = $total_max_score > 0 ? ($total_score / $total_max_score) * 100 : 0;
    
    return [
        'total_subjects' => $total_subjects,
        'total_score' => $total_score,
        'total_max_score' => $total_max_score,
        'percentage' => round($overall_percentage, 2),
        'average' => $total_subjects > 0 ? round($total_score / $total_subjects, 2) : 0
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Results - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            @page {
                size: A4;
                margin: 20mm;
            }
            body {
                font-family: Arial, sans-serif;
                font-size: 12px;
            }
            .page-break {
                page-break-after: always;
            }
            .no-print {
                display: none !important;
            }
            .col-md-6{
                width: 50%;
                float:left;
            }

            .col-md-4{
                width: 50%;
                float:left;
            }
            .row::after{
                content: "";
                clear: both;
                display: table;
            }
        }
        .school-header {
            border-bottom: 3px double #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .result-table {
            font-size: 11px;
        }
        .summary-box {
            border: 1px solid #000;
            padding: 10px;
            margin: 10px 0;
        }
        .footer-section {
            border-top: 2px solid #000;
            margin-top: 20px;
            padding-top: 10px;
        }
        .signature-area {
            margin-top: 50px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Print Button -->
        <div class="text-center mb-3 no-print">
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print me-2"></i>Print Results
            </button>
            <a href="student_reports.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Reports
            </a>
        </div>

        <?php foreach ($students as $index => $student): ?>
            <div class="result-sheet <?php echo $index > 0 ? 'page-break' : ''; ?>">
                <!-- School Header -->
                <div class="school-header text-center">
                    <h2 class="mb-1"><?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></h2>
                    <p class="mb-1"><?php echo $school_settings['school_address'] ?? '123 Education Road, Ikeja, Lagos'; ?></p>
                    <p class="mb-1">
                        Tel: <?php echo $school_settings['school_phone'] ?? '+234 801 234 5678'; ?> | 
                        Email: <?php echo $school_settings['school_email'] ?? 'info@excelschools.edu.ng'; ?>
                    </p>
                    <h4 class="mb-0 text-decoration-underline">STUDENT ACADEMIC REPORT</h4>
                </div>

                <!-- Basic Information -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <table class="table table-bordered table-sm">
                            <tr>
                                <td><strong>Student Name:</strong></td>
                                <td><?php echo $student['full_name']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Student ID:</strong></td>
                                <td><?php echo $student['student_id']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Gender:</strong></td>
                                <td><?php echo ucfirst($student['gender']); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-bordered table-sm">
                            <tr>
                                <td><strong>Class:</strong></td>
                                <td><?php echo $class['name'] ?? ''; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Academic Session:</strong></td>
                                <td><?php echo $session['name'] ?? ''; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Term:</strong></td>
                                <td><?php echo ucfirst($term); ?> Term</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php
                $student_results = getStudentResults($db, $student['id'], $session_id, $term);
                $attendance_summary = getAttendanceSummary($db, $student['id'], $session_id, $term);
                $overall_performance = calculateOverallPerformance($student_results);
                ?>

                <!-- Academic Results -->
                <div class="mb-3">
                    <h5 class="text-center bg-light py-1">ACADEMIC PERFORMANCE</h5>
                    <?php if ($student_results): ?>
                        <table class="table table-bordered table-sm result-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Subject</th>
                                    <th>Code</th>
                                    <th>CA Score</th>
                                    <th>Exam Score</th>
                                    <th>Total Score</th>
                                    <th>Grade</th>
                                    <th>Remark</th>
                                    <th>Position</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($student_results as $result): ?>
                                    <tr>
                                        <td><?php echo $result['subject_name']; ?></td>
                                        <td><?php echo $result['subject_code']; ?></td>
                                        <td><?php echo $result['ca_score']; ?></td>
                                        <td><?php echo $result['exam_score']; ?></td>
                                        <td><strong><?php echo $result['total_score']; ?></strong></td>
                                        <td><strong><?php echo $result['grade']; ?></strong></td>
                                        <td><small><?php echo $result['remark']; ?></small></td>
                                        <td><?php echo $result['position_in_subject']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                </div>
                <div class="mb-3">
                        <!-- Overall Summary -->
                        <?php if ($overall_performance): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="summary-box">
                                    <h6 class="text-center">OVERALL PERFORMANCE SUMMARY</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>Total Subjects:</strong></td>
                                            <td><?php echo $overall_performance['total_subjects']; ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Total Score:</strong></td>
                                            <td><?php echo $overall_performance['total_score']; ?> / <?php echo $overall_performance['total_max_score']; ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Average Score:</strong></td>
                                            <td><?php echo $overall_performance['average']; ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Percentage:</strong></td>
                                            <td><strong><?php echo $overall_performance['percentage']; ?>%</strong></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Class Position:</strong></td>
                                            <td>
                                                <?php 
                                                $class_position = $student_results[0]['position_in_class'] ?? 'N/A';
                                                echo $class_position;
                                                if (is_numeric($class_position)) {
                                                    $suffix = ['th','st','nd','rd','th','th','th','th','th','th'];
                                                    $suffix_index = ($class_position % 100 >= 11 && $class_position % 100 <= 13) ? 0 : $class_position % 10;
                                                    echo $suffix[$suffix_index];
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="summary-box">
                                    <h6 class="text-center">GRADING SYSTEM</h6>
                                    <table class="table table-sm">
                                        <tr><td>A1 (80-100%)</td><td>Excellent</td></tr>
                                        <tr><td>B2 (70-79%)</td><td>Very Good</td></tr>
                                        <tr><td>B3 (65-69%)</td><td>Good</td></tr>
                                        <tr><td>C4 (60-64%)</td><td>Credit</td></tr>
                                        <tr><td>C5 (55-59%)</td><td>Credit</td></tr>
                                        <tr><td>C6 (50-54%)</td><td>Credit</td></tr>
                                        <tr><td>D7 (45-49%)</td><td>Pass</td></tr>
                                        <tr><td>E8 (40-44%)</td><td>Pass</td></tr>
                                        <tr><td>F9 (0-39%)</td><td>Fail</td></tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="alert alert-warning text-center">
                            No academic results available for this term.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Footer Section -->
                <div class="footer-section">
                    <!-- Attendance Summary -->
                    <?php if ($attendance_summary && $attendance_summary['total_days'] > 0): ?>
                    <div class="row mb-3">
                        <div class="col-12">
                            <h6>ATTENDANCE RECORD</h6>
                            <table class="table table-bordered table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Total School Days</th>
                                        <th>Days Present</th>
                                        <th>Days Absent</th>
                                        <th>Days Late</th>
                                        <th>Attendance Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><?php echo $attendance_summary['total_days']; ?></td>
                                        <td><?php echo $attendance_summary['present_days']; ?></td>
                                        <td><?php echo $attendance_summary['absent_days']; ?></td>
                                        <td><?php echo $attendance_summary['late_days']; ?></td>
                                        <td><strong><?php echo round(($attendance_summary['present_days'] / $attendance_summary['total_days']) * 100, 1); ?>%</strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Teacher and Principal Remarks -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <h6>CLASS TEACHER'S REMARK:</h6>
                                <div class="border p-2" style="min-height: 80px;">
                                    <?php echo $student_results[0]['teacher_comment'] ?? '...................................................................................'; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <h6>PRINCIPAL'S REMARK:</h6>
                                <div class="border p-2" style="min-height: 80px;">
                                    <?php echo $student_results[0]['principal_comment'] ?? '...................................................................................'; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Signatures -->
                    <div class="row signature-area">
                        <div class="col-md-4 text-center">
                            <div class="border-top pt-2">
                                <strong>Class Teacher</strong>
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="border-top pt-2">
                                <strong>Principal</strong>
                            </div>
                        </div>
                        <!-- <div class="col-md-4 text-center">
                            <div class="border-top pt-2">
                                <strong>Date: <?php echo date('d/m/Y'); ?></strong>
                            </div>
                        </div> -->
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-print when page loads
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 1000);
        };
    </script>
</body>
</html>