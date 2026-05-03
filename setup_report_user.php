<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Start transaction
    $db->beginTransaction();
    
    // 1. Create report_viewer role if it doesn't exist
    $query = "SELECT id FROM roles WHERE name = 'report_viewer'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$role) {
        $query = "INSERT INTO roles (name, description) VALUES ('report_viewer', 'User with reports-only access')";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $role_id = $db->lastInsertId();
        echo "Created report_viewer role<br>";
    } else {
        $role_id = $role['id'];
        echo "Report viewer role already exists<br>";
    }
    
    // 2. Ensure manage_reports permission exists
    $query = "SELECT id FROM permissions WHERE name = 'manage_reports'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $permission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$permission) {
        $query = "INSERT INTO permissions (name, description) VALUES ('manage_reports', 'Can view and generate reports')";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $permission_id = $db->lastInsertId();
        echo "Created manage_reports permission<br>";
    } else {
        $permission_id = $permission['id'];
        echo "Manage reports permission already exists<br>";
    }
    
    // 3. Assign permission to role
    $query = "INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':role_id', $role_id);
    $stmt->bindParam(':permission_id', $permission_id);
    $stmt->execute();
    echo "Assigned permission to role<br>";
    
    // 4. Create report_user if it doesn't exist
    $query = "SELECT id FROM users WHERE username = 'report_user'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $password_hash = password_hash('password', PASSWORD_DEFAULT);
        $query = "INSERT INTO users (username, password, role_id, status) VALUES ('report_user', :password, :role_id, 'active')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':password', $password_hash);
        $stmt->bindParam(':role_id', $role_id);
        $stmt->execute();
        echo "Created report_user account<br>";
    } else {
        echo "Report user already exists<br>";
    }
    
    // Commit transaction
    $db->commit();
    
    echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px;'>";
    echo "<h3>Report User Setup Complete!</h3>";
    echo "<p><strong>Username:</strong> report_user</p>";
    echo "<p><strong>Password:</strong> password</p>";
    echo "<p>Please use these credentials to log in. For security reasons, please change the password after first login.</p>";
    echo "</div>";
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px;'>";
    echo "<h3>Error Setting Up Report User</h3>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?> 