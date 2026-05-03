<?php
session_start();
require_once '../config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Log the start of registration
error_log("Registration attempt started");

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get and sanitize input
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);

error_log("Registration attempt for email: " . $email);

// Validate input
if (empty($email) || empty($password) || empty($confirm_password) || empty($full_name)) {
    error_log("Missing required fields");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

if ($password !== $confirm_password) {
    error_log("Passwords do not match");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
    exit();
}

if (strlen($password) < 8) {
    error_log("Password too short");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
    exit();
}

try {
    // Initialize database connection
    error_log("Attempting database connection");
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception("Database connection failed");
    }
    error_log("Database connection successful");

    // Check if email already exists
    error_log("Checking if email exists: " . $email);
    $check_query = "SELECT id FROM users WHERE email = :email";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':email', $email);
    $check_stmt->execute();

    if ($check_stmt->rowCount() > 0) {
        error_log("Email already exists: " . $email);
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit();
    }

    // Hash password
    error_log("Hashing password");
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user
    error_log("Attempting to insert new user");
    $query = "INSERT INTO users (email, password, full_name, role, status) 
              VALUES (:email, :password, :full_name, 'user', 'active')";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':password', $hashed_password);
    $stmt->bindParam(':full_name', $full_name);
    
    if ($stmt->execute()) {
        error_log("User created successfully");
        // Get the new user's ID
        $user_id = $db->lastInsertId();
        
        // Set session variables
        $_SESSION['user_id'] = $user_id;
        $_SESSION['email'] = $email;
        $_SESSION['full_name'] = $full_name;
        $_SESSION['role'] = 'user';

        echo json_encode(['success' => true, 'message' => 'Registration successful']);
    } else {
        $error = $stmt->errorInfo();
        error_log("Failed to create user: " . print_r($error, true));
        throw new Exception("Failed to create user: " . $error[2]);
    }
} catch (PDOException $e) {
    error_log("Database Error in register.php: " . $e->getMessage());
    error_log("Error Code: " . $e->getCode());
    error_log("Error Info: " . print_r($e->errorInfo, true));
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred',
        'debug' => [
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'info' => $e->errorInfo
        ]
    ]);
} catch (Exception $e) {
    error_log("General Error in register.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred. Please try again later.',
        'debug' => [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}
?> 