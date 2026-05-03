<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get MySQL root password from environment variable or use default
$root_password = getenv('MYSQL_ROOT_PASSWORD') ?: 'root';

try {
    // Connect to MySQL as root
    $root_conn = new PDO("mysql:host=localhost", "root", $root_password);
    $root_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database if it doesn't exist
    $root_conn->exec("CREATE DATABASE IF NOT EXISTS pos_inventory_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database created successfully\n";

    // Create user if it doesn't exist
    $root_conn->exec("CREATE USER IF NOT EXISTS 'abahzaid'@'localhost' IDENTIFIED BY '@mbuhbogor2024'");
    echo "User created successfully\n";

    // Grant privileges
    $root_conn->exec("GRANT ALL PRIVILEGES ON pos_inventory_system.* TO 'abahzaid'@'localhost'");
    $root_conn->exec("FLUSH PRIVILEGES");
    echo "Privileges granted successfully\n";

    // Connect to the new database
    $db = new PDO("mysql:host=localhost;dbname=pos_inventory_system", "abahzaid", "@mbuhbogor2024");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create users table
    $db->exec("CREATE TABLE IF NOT EXISTS users (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Users table created successfully\n";

    echo "Database setup completed successfully!\n";

} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    echo "\nPlease make sure:\n";
    echo "1. MySQL server is running\n";
    echo "2. You have the correct MySQL root password\n";
    echo "3. You have sufficient privileges to create databases and users\n";
}
?> 