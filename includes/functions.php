<?php
// Enhanced Utility functions for the new schema
function formatDate($date, $format = 'F j, Y') {
    if (empty($date) || $date == '0000-00-00') return 'N/A';
    return date($format, strtotime($date));
}

function formatDateTime($datetime, $format = 'F j, Y g:i A') {
    if (empty($datetime) || $datetime == '0000-00-00 00:00:00') return 'N/A';
    return date($format, strtotime($datetime));
}

function calculateGrade($score) {
    if ($score >= 80) return ['grade' => 'A', 'remark' => 'Excellent'];
    if ($score >= 70) return ['grade' => 'B', 'remark' => 'Very Good'];
    if ($score >= 60) return ['grade' => 'C', 'remark' => 'Good'];
    if ($score >= 50) return ['grade' => 'D', 'remark' => 'Fair'];
    return ['grade' => 'F', 'remark' => 'Fail'];
}

function calculatePercentage($obtained, $total) {
    if ($total == 0) return 0;
    return round(($obtained / $total) * 100, 2);
}

function generateStudentId() {
    $prefix = 'STU';
    $year = date('Y');
    $random = mt_rand(1000, 9999);
    return $prefix . $year . $random;
}

function generateStaffId() {
    $prefix = 'STAFF';
    $year = date('Y');
    $random = mt_rand(1000, 9999);
    return $prefix . $year . $random;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePhone($phone) {
    return preg_match('/^[0-9+]{10,15}$/', $phone);
}

function uploadFile($file, $allowed_types = ['image/jpeg', 'image/png', 'image/gif']) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error'];
    }
    
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }
    
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File too large'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $upload_path = '../uploads/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return ['success' => true, 'filename' => $filename];
    }
    
    return ['success' => false, 'error' => 'Upload failed'];
}

// New functions for enhanced functionality
function getStudentAge($date_of_birth) {
    if (empty($date_of_birth)) return 'N/A';
    $today = new DateTime();
    $birth_date = new DateTime($date_of_birth);
    $age = $today->diff($birth_date);
    return $age->y;
}

function formatCurrency($amount) {
    return '₦' . number_format($amount, 2);
}

function getStatusBadge($status) {
    $badges = [
        'active' => 'success',
        'inactive' => 'secondary',
        'suspended' => 'warning',
        'graduated' => 'info',
        'transferred' => 'primary',
        'withdrawn' => 'dark',
        'retired' => 'secondary',
        'pending' => 'warning',
        'completed' => 'success',
        'failed' => 'danger',
        'refunded' => 'info',
        'draft' => 'secondary',
        'cancelled' => 'danger',
        'issued' => 'primary',
        'returned' => 'success',
        'overdue' => 'danger',
        'new' => 'primary',
        'read' => 'secondary',
        'replied' => 'success',
        'spam' => 'danger'
    ];
    
    $color = $badges[$status] ?? 'secondary';
    return '<span class="badge bg-' . $color . '">' . ucfirst($status) . '</span>';
}

function logActivity($user_id, $action, $table_name = null, $record_id = null, $old_values = null, $new_values = null) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
              VALUES (:user_id, :action, :table_name, :record_id, :old_values, :new_values, :ip_address, :user_agent)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':action', $action);
    $stmt->bindParam(':table_name', $table_name);
    $stmt->bindParam(':record_id', $record_id);
    $stmt->bindParam(':old_values', $old_values);
    $stmt->bindParam(':new_values', $new_values);
    $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
    $stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT']);
    
    return $stmt->execute();
}

function sendNotification($title, $message, $type = 'info', $target_audience = 'all', $specific_users = null, $created_by = null) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$created_by) {
        $created_by = $_SESSION['user_id'] ?? 1;
    }
    
    $query = "INSERT INTO notifications (title, message, type, target_audience, specific_users, created_by) 
              VALUES (:title, :message, :type, :target_audience, :specific_users, :created_by)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':message', $message);
    $stmt->bindParam(':type', $type);
    $stmt->bindParam(':target_audience', $target_audience);
    $stmt->bindParam(':specific_users', $specific_users);
    $stmt->bindParam(':created_by', $created_by);
    
    return $stmt->execute();
}

function getUnreadNotificationsCount($user_id) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT COUNT(*) FROM user_notifications un 
              JOIN notifications n ON un.notification_id = n.id 
              WHERE un.user_id = :user_id AND un.is_read = FALSE 
              AND (n.publish_at IS NULL OR n.publish_at <= NOW()) 
              AND (n.expires_at IS NULL OR n.expires_at >= NOW())";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    return $stmt->fetchColumn();
}

function calculateStudentPosition($student_id, $class_id, $session_id, $term) {
    $database = new Database();
    $db = $database->getConnection();
    
    // Calculate total scores for all students in the class
    $query = "SELECT ar.student_id, SUM(ar.total_score) as total 
              FROM academic_results ar 
              JOIN students s ON ar.student_id = s.id 
              WHERE s.class_id = :class_id 
              AND ar.session_id = :session_id 
              AND ar.term = :term 
              GROUP BY ar.student_id 
              ORDER BY total DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':class_id', $class_id);
    $stmt->bindParam(':session_id', $session_id);
    $stmt->bindParam(':term', $term);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Find student position
    $position = 1;
    foreach ($results as $result) {
        if ($result['student_id'] == $student_id) {
            return $position;
        }
        $position++;
    }
    
    return null;
}

function getClassTeacher($class_id) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT u.full_name, u.email, u.phone 
              FROM classes c 
              JOIN users u ON c.class_teacher_id = u.id 
              WHERE c.id = :class_id AND u.status = 'active'";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':class_id', $class_id);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getStudentAttendanceSummary($student_id, $session_id, $term) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
                SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused_days
              FROM attendance 
              WHERE student_id = :student_id 
              AND session_id = :session_id 
              AND term = :term";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->bindParam(':session_id', $session_id);
    $stmt->bindParam(':term', $term);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>