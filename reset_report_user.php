<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Reset report_user password
    $password_hash = password_hash('password', PASSWORD_DEFAULT);
    $query = "UPDATE users SET password = :password WHERE username = 'report_user'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':password', $password_hash);
    
    if ($stmt->execute()) {
        echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px;'>";
        echo "<h3>Password Reset Successful!</h3>";
        echo "<p><strong>Username:</strong> report_user</p>";
        echo "<p><strong>New Password:</strong> password</p>";
        echo "<p>You can now log in with these credentials.</p>";
        echo "</div>";
    } else {
        throw new Exception("Failed to reset password");
    }
    
} catch (PDOException $e) {
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px;'>";
    echo "<h3>Error Resetting Password</h3>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?> 