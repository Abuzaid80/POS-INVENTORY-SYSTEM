<?php
session_start();
require_once '../config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get and sanitize input
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$password = $_POST['password'] ?? '';
$remember_me = filter_input(INPUT_POST, 'remember_me', FILTER_VALIDATE_BOOLEAN);

// Validate input
if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit();
}

try {
    // Initialize database connection
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception("Database connection failed");
    }

    // Prepare the query to get user by email
    $query = "SELECT id, email, password, full_name, role FROM users WHERE email = :email AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    if ($stmt->rowCount() === 1) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];

            // Set remember me cookie if requested
            if ($remember_me) {
                $token = bin2hex(random_bytes(32));
                $expiry = time() + (30 * 24 * 60 * 60); // 30 days
                
                // Store token in database
                $query = "UPDATE users SET remember_token = :token, token_expiry = :expiry WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':token', $token);
                $stmt->bindParam(':expiry', date('Y-m-d H:i:s', $expiry));
                $stmt->bindParam(':id', $user['id']);
                $stmt->execute();

                // Set cookie
                setcookie('remember_token', $token, $expiry, '/', '', true, true);
            }

            echo json_encode(['success' => true, 'message' => 'Login successful']);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        }
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
    }
} catch (PDOException $e) {
    error_log("Database Error in login.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("General Error in login.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
}
?> 