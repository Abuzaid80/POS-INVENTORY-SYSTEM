<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Create a new password hash
    $password = 'admin123';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // First, try to update existing admin user
    $query = "UPDATE users SET 
              password = :password,
              status = 'active'
              WHERE username = 'admin'";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':password', $hashed_password);
    $result = $stmt->execute();

    if ($stmt->rowCount() > 0) {
        echo "Admin password has been reset successfully!<br>";
        echo "Username: admin<br>";
        echo "Password: admin123<br>";
    } else {
        // If no admin user exists, create one
        $query = "INSERT INTO users (username, password, role_id, status) 
                 VALUES ('admin', :password, 1, 'active')";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->execute();
        
        echo "New admin user created successfully!<br>";
        echo "Username: admin<br>";
        echo "Password: admin123<br>";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 