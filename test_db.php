<?php
require_once 'config/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Test database connection
    echo "Testing database connection...\n";
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        echo "✓ Database connection successful\n";
        echo "Database name: " . $db->query("SELECT DATABASE()")->fetchColumn() . "\n";
        
        // Check if users table exists
        $tableExists = $db->query("SHOW TABLES LIKE 'users'")->rowCount() > 0;
        
        if ($tableExists) {
            echo "✓ Users table exists\n";
            
            // Show table structure
            echo "\nUsers table structure:\n";
            $stmt = $db->query("DESCRIBE users");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "Field: " . $row['Field'] . "\n";
                echo "Type: " . $row['Type'] . "\n";
                echo "Null: " . $row['Null'] . "\n";
                echo "Key: " . $row['Key'] . "\n";
                echo "Default: " . $row['Default'] . "\n";
                echo "Extra: " . $row['Extra'] . "\n\n";
            }
        } else {
            echo "✗ Users table does not exist\n";
            echo "Creating users table...\n";
            
            // Create users table
            $createTable = "CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                full_name VARCHAR(255) NOT NULL,
                role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
                status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
                remember_token VARCHAR(100) DEFAULT NULL,
                token_expiry DATETIME DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($db->exec($createTable) !== false) {
                echo "✓ Users table created successfully\n";
            } else {
                echo "✗ Failed to create users table\n";
            }
        }
    } else {
        echo "✗ Database connection failed\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?> 