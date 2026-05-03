<?php
session_start();
require_once 'config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        // Get user with role information
        $query = "SELECT u.*, r.name as role_name, r.id as role_id 
                 FROM users u 
                 LEFT JOIN roles r ON u.role_id = r.id 
                 WHERE u.username = :username AND u.status = 'active'";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (password_verify($password, $user['password'])) {
                // Get user permissions
                $perm_query = "SELECT p.name 
                             FROM permissions p 
                             JOIN role_permissions rp ON p.id = rp.permission_id 
                             WHERE rp.role_id = :role_id";
                $perm_stmt = $db->prepare($perm_query);
                $perm_stmt->bindParam(':role_id', $user['role_id']);
                $perm_stmt->execute();
                $permissions = $perm_stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Store user data in session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role_name'] = $user['role_name'];
                $_SESSION['role'] = $user['role_name'];
                $_SESSION['permissions'] = $permissions;
                $_SESSION['logged_in'] = true;
                
                // Update last login
                $update = "UPDATE users SET last_login = NOW() WHERE id = :id";
                $update_stmt = $db->prepare($update);
                $update_stmt->bindParam(':id', $user['id']);
                $update_stmt->execute();
                
                // Redirect based on role
                if ($user['role_name'] === 'report_viewer') {
                    header('Location: reports.php');
                } elseif ($user['role_name'] === 'limited_admin') {
                    header('Location: products.php');
                } else {
                    header('Location: index.php');
                }
                exit();
            } else {
                $error = 'Invalid password.';
            }
        } else {
            $error = 'User not found or inactive.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Shop Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            max-width: 400px;
            width: 90%;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header i {
            font-size: 3rem;
            color: #3498db;
        }
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        .btn-primary {
            background-color: #3498db;
            border-color: #3498db;
        }
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        .alert {
            margin-bottom: 1rem;
        }
    </style>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-cash-register mb-3"></i>
            <h3>Shop Inventory System</h3>
            <p class="text-muted">Please login to continue</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 