<?php
session_start();

class Database {
    private $host = "localhost";
    private $db_name = "school_management";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

// Add these helper functions
function getSystemSetting($key, $default = '') {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT setting_value FROM system_settings WHERE setting_key = :key";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':key', $key);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['setting_value'];
    }
    
    return $default;
}

function getCurrentSession() {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT * FROM academic_sessions WHERE status = 'active' LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getStudentByUserId($user_id) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT s.*, u.full_name, u.gender, u.email, u.phone, c.name as class_name 
              FROM students s 
              JOIN users u ON s.user_id = u.id 
              JOIN classes c ON s.class_id = c.id 
              WHERE s.user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// CSRF Token Generation
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF Token
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Redirect function
function redirect($url) {
    header("Location: $url");
    exit();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check user role
function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

// Password hashing
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

// Verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Sanitize input
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}
?>