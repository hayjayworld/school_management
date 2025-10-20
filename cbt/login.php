<?php
require_once '../includes/config.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $password = sanitize($_POST['password']);
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if user exists and is a student
    $query = "SELECT u.id, u.username, u.password, u.role, u.full_name, 
                     s.id as studentid, s.student_id, s.class_id, c.name as class_name
              FROM users u 
              JOIN students s ON u.id = s.user_id 
              JOIN classes c ON s.class_id = c.id 
              WHERE u.username = :username AND u.role = 'student' AND u.status = 'active'";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    if ($stmt->rowCount() == 1) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Set CBT session
            $_SESSION['student_id'] = $user['studentid'];
            $_SESSION['cbt_student_id'] = $user['student_id'];
            $_SESSION['cbt_user_id'] = $user['id'];
            $_SESSION['cbt_username'] = $user['username'];
            $_SESSION['cbt_full_name'] = $user['full_name'];
            $_SESSION['cbt_class_id'] = $user['class_id'];
            $_SESSION['cbt_class_name'] = $user['class_name'];
            $_SESSION['cbt_logged_in'] = true;
            
            header("Location: index.php");
            exit();
        } else {
            $message = "Invalid password.";
        }
    } else {
        $message = "Student not found or account inactive.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CBT Portal Login - Excel Schools</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        .login-header {
            background: #2c3e50;
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .login-body {
            padding: 30px;
        }
        .btn-login {
            background: #3498db;
            border: none;
            padding: 12px;
            font-weight: 600;
        }
        .btn-login:hover {
            background: #2980b9;
        }
        .school-logo {
            font-size: 3rem;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="login-container">
                    <div class="login-header">
                        <div class="school-logo">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <h3>Excel Schools</h3>
                        <p class="mb-0">Computer Based Test Portal</p>
                    </div>
                    <div class="login-body">
                        <?php if ($message): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" name="username" required 
                                           placeholder="Enter your username">
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" name="password" required 
                                           placeholder="Enter your password">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-login btn-lg w-100 text-white">
                                <i class="fas fa-sign-in-alt me-2"></i>Login to CBT Portal
                            </button>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Use your student credentials to access available exams
                            </p>
                            <a href="../auth/login.php" class="text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i>Back to Main Portal
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>