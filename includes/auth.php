<?php
// Authentication helper functions
require_once 'config.php';

function checkAuth() {
    if (!isLoggedIn()) {
        redirect('../auth/login.php');
    }
}

function checkRole($allowed_roles) {
    if (!isLoggedIn()) {
        redirect('../auth/login.php');
    }
    
    if (!in_array($_SESSION['user_role'], (array)$allowed_roles)) {
        http_response_code(403);
        die('Access Denied: Insufficient permissions.');
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT * FROM users WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $_SESSION['user_id']);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>