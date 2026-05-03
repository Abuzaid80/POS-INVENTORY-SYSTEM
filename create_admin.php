<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Check if any users exist
    $query = "SELECT COUNT(*) as count FROM users";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] == 0) {
        // Check if admin role exists
        $query = "SELECT id FROM roles WHERE name = 'admin'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $role = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$role) {
            // Create admin role if it doesn't exist
            $query = "INSERT INTO roles (name, description) VALUES ('admin', 'Administrator with full access')";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $role_id = $db->lastInsertId();
        } else {
            $role_id = $role['id'];
        }

        // Create default admin user
        $username = 'admin';
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $query = "INSERT INTO users (username, password, role_id, status) VALUES (:username, :password, :role_id, 'active')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':role_id', $role_id);
        $stmt->execute();

        echo "Default admin user created successfully!<br>";
        echo "Username: admin<br>";
        echo "Password: admin123<br>";
    } else {
        echo "Users already exist in the database.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 