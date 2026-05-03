<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Start transaction
    $db->beginTransaction();

    // 1. Create admin role if it doesn't exist
    $query = "SELECT id FROM roles WHERE name = 'admin'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        $query = "INSERT INTO roles (name, description) VALUES ('admin', 'Administrator with full access')";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $role_id = $db->lastInsertId();
    } else {
        $role_id = $role['id'];
    }

    // 2. Create new admin user
    $username = 'superadmin';
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    
    $query = "INSERT INTO users (username, password, role_id, status) 
              VALUES (:username, :password, :role_id, 'active')";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':password', $password);
    $stmt->bindParam(':role_id', $role_id);
    $stmt->execute();

    // 3. Ensure all permissions exist and are assigned to admin role
    $permissions = [
        'view_dashboard',
        'manage_users',
        'manage_roles',
        'manage_products',
        'manage_categories',
        'manage_sales',
        'view_reports',
        'manage_settings'
    ];

    foreach ($permissions as $permission) {
        // Check if permission exists
        $query = "SELECT id FROM permissions WHERE name = :name";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $permission);
        $stmt->execute();
        $perm = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$perm) {
            // Create permission if it doesn't exist
            $query = "INSERT INTO permissions (name, description) VALUES (:name, :description)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':name', $permission);
            $stmt->bindParam(':description', $permission);
            $stmt->execute();
            $perm_id = $db->lastInsertId();
        } else {
            $perm_id = $perm['id'];
        }

        // Assign permission to admin role
        $query = "INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (:role_id, :perm_id)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':role_id', $role_id);
        $stmt->bindParam(':perm_id', $perm_id);
        $stmt->execute();
    }

    // Commit transaction
    $db->commit();

    echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px;'>";
    echo "<h3>New Admin User Created Successfully!</h3>";
    echo "<p><strong>Username:</strong> superadmin</p>";
    echo "<p><strong>Password:</strong> admin123</p>";
    echo "<p>Please use these credentials to log in. For security reasons, please change your password after first login.</p>";
    echo "</div>";

} catch (PDOException $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px;'>";
    echo "<h3>Error Creating Admin User</h3>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?> 