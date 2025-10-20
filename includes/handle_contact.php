<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'contact_submission') {
    $csrf_token = sanitize($_POST['csrf_token']);
    
    if (!validateCSRFToken($csrf_token)) {
        echo json_encode(['success' => false, 'message' => 'Security token invalid.']);
        exit;
    }
    
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $interest_level = sanitize($_POST['interest_level']);
    $message = sanitize($_POST['message']);
    
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "INSERT INTO contact_messages (name, email, phone, subject, message) 
             VALUES (:name, :email, :phone, :subject, :message)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':phone', $phone);
    $stmt->bindParam(':subject', $interest_level);
    $stmt->bindParam(':message', $message);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Thank you for your inquiry!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error submitting form. Please try again.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
?>