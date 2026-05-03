<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Start transaction
    $db->beginTransaction();
    
    // 1. Create limited_admin role if it doesn't exist
    $query = "SELECT id FROM roles WHERE name = 'limited_admin'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$role) {
        $query = "INSERT INTO roles (name, description) VALUES ('limited_admin', 'Administrator with access to products, sales, and customers only')";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $role_id = $db->lastInsertId();
        echo "Created limited_admin role<br>";
    } else {
        $role_id = $role['id'];
        echo "Limited admin role already exists<br>";
    }
    
    // 2. Ensure required permissions exist
    $required_permissions = [
        'manage_products' => 'Can manage products',
        'manage_sales' => 'Can manage sales',
        'manage_customers' => 'Can manage customers',
        'view_customers' => 'Can view customers'
    ];
    
    $permission_ids = [];
    foreach ($required_permissions as $perm_name => $perm_desc) {
        $query = "SELECT id FROM permissions WHERE name = :name";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $perm_name);
        $stmt->execute();
        $permission = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$permission) {
            $query = "INSERT INTO permissions (name, description) VALUES (:name, :description)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':name', $perm_name);
            $stmt->bindParam(':description', $perm_desc);
            $stmt->execute();
            $permission_ids[] = $db->lastInsertId();
            echo "Created {$perm_name} permission<br>";
        } else {
            $permission_ids[] = $permission['id'];
            echo "{$perm_name} permission already exists<br>";
        }
    }
    
    // 3. Assign permissions to role
    foreach ($permission_ids as $perm_id) {
        $query = "INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':role_id', $role_id);
        $stmt->bindParam(':permission_id', $perm_id);
        $stmt->execute();
    }
    echo "Assigned permissions to role<br>";
    
    // 4. Create limited admin user if it doesn't exist
    $query = "SELECT id FROM users WHERE username = 'limited_admin'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $query = "INSERT INTO users (username, password, role_id, status) VALUES ('limited_admin', :password, :role_id, 'active')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':password', $password_hash);
        $stmt->bindParam(':role_id', $role_id);
        $stmt->execute();
        echo "Created limited_admin account<br>";
    } else {
        echo "Limited admin user already exists<br>";
    }
    
    // Commit transaction
    $db->commit();
    
    echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px;'>";
    echo "<h3>Limited Admin Setup Complete!</h3>";
    echo "<p><strong>Username:</strong> limited_admin</p>";
    echo "<p><strong>Password:</strong> admin123</p>";
    echo "<p>This admin account has access to:</p>";
    echo "<ul>";
    echo "<li>Products Management</li>";
    echo "<li>Sales Management</li>";
    echo "<li>Customer Management</li>";
    echo "</ul>";
    echo "<p>Please change the password after first login.</p>";
    echo "</div>";
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px;'>";
    echo "<h3>Error Setting Up Limited Admin</h3>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?> 