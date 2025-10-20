<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$message = '';

// Get current user data
$user_query = "SELECT * FROM users WHERE id = :id";
$stmt = $db->prepare($user_query);
$stmt->bindParam(':id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = sanitize($_POST['csrf_token']);
    
    if (!validateCSRFToken($csrf_token)) {
        $message = "Security token invalid.";
    } else {
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        
        // Handle password change if provided
        $password_change = '';
        if (!empty($_POST['new_password'])) {
            if ($_POST['new_password'] !== $_POST['confirm_password']) {
                $message = "Passwords do not match.";
            } else {
                $new_password_hash = hashPassword($_POST['new_password']);
                $password_change = ", password = :password";
            }
        }
        
        // Handle file upload
        $photo_change = '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload_result = uploadFile($_FILES['photo']);
            if ($upload_result['success']) {
                $photo_change = ", photo = :photo";
                // Delete old photo if exists
                if ($user['photo']) {
                    unlink('../uploads/' . $user['photo']);
                }
            } else {
                $message = $upload_result['error'];
            }
        }
        
        if (empty($message)) {
            $query = "UPDATE users SET full_name = :full_name, email = :email, phone = :phone $password_change $photo_change WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':id', $user_id);
            
            if (!empty($_POST['new_password'])) {
                $stmt->bindParam(':password', $new_password_hash);
            }
            if (!empty($photo_change)) {
                $stmt->bindParam(':photo', $upload_result['filename']);
            }
            
            if ($stmt->execute()) {
                $_SESSION['full_name'] = $full_name;
                $message = "Profile updated successfully!";
                // Refresh user data
                $stmt = $db->prepare($user_query);
                $stmt->bindParam(':id', $user_id);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $message = "Error updating profile.";
            }
        }
    }
}

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
    <title>Update Profile - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php
    $role = $_SESSION['user_role'];
    include "includes/header.php";
    ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Update Profile</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-info"><?php echo $message; ?></div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="row">
                                <div class="col-md-4 text-center mb-4">
                                    <div class="mb-3">
                                        <?php if ($user['photo']): ?>
                                            <img src="../uploads/<?php echo $user['photo']; ?>" 
                                                 class="rounded-circle" width="150" height="150" 
                                                 style="object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" 
                                                 style="width: 150px; height: 150px;">
                                                <i class="fas fa-user text-white fa-3x"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <input type="file" class="form-control" name="photo" accept="image/*">
                                    <small class="text-muted">Max 5MB, JPG/PNG/GIF</small>
                                </div>
                                
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" name="full_name" 
                                               value="<?php echo $user['full_name']; ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Email Address *</label>
                                        <input type="email" class="form-control" name="email" 
                                               value="<?php echo $user['email']; ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" name="phone" 
                                               value="<?php echo $user['phone'] ?? ''; ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control" value="<?php echo $user['username']; ?>" disabled>
                                        <small class="text-muted">Username cannot be changed</small>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <h5>Change Password</h5>
                            <p class="text-muted">Leave blank to keep current password</p>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" class="form-control" name="new_password">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirm Password</label>
                                    <input type="password" class="form-control" name="confirm_password">
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="<?php echo $role; ?>/dashboard.php" class="btn btn-secondary me-md-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">Update Profile</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>