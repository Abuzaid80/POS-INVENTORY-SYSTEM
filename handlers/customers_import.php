<?php
session_start();
require_once '../config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

// Get database connection
try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Check if a file was uploaded
if (!isset($_FILES['csvFile']) || $_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded or upload error']);
    exit;
}

// Validate file type
$file_extension = strtolower(pathinfo($_FILES['csvFile']['name'], PATHINFO_EXTENSION));
if ($file_extension !== 'csv') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Please upload a CSV file.']);
    exit;
}

// Open the CSV file
$file = fopen($_FILES['csvFile']['tmp_name'], 'r');
if (!$file) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to open the CSV file.']);
    exit;
}

// Skip UTF-8 BOM if present
$bom = fread($file, 3);
if ($bom !== "\xEF\xBB\xBF") {
    // Not a BOM, rewind to start
    rewind($file);
}

// Read the header row
$header = fgetcsv($file);
if (!$header) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to read the CSV header.']);
    fclose($file);
    exit;
}

// Clean header values (remove BOM and trim)
$header = array_map(function($value) {
    return trim(str_replace("\xEF\xBB\xBF", '', $value));
}, $header);

// Log the header and first few rows for debugging
error_log("CSV Header: " . implode(", ", $header));

// Read and log first 5 rows to understand the structure
$sample_rows = [];
for ($i = 0; $i < 5; $i++) {
    $row = fgetcsv($file);
    if ($row) {
        // Clean row values
        $row = array_map('trim', $row);
        $sample_rows[] = $row;
        error_log("Sample Row " . ($i + 1) . ": " . implode(", ", $row));
    }
}

// Reset file pointer to start after header
rewind($file);
fgetcsv($file); // Skip header again

// Prepare the insert statement
$stmt = $conn->prepare("INSERT INTO customers (name, email, phone, address, notes) VALUES (?, ?, ?, ?, ?)");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
    fclose($file);
    exit;
}

// Process each row in the CSV
$success_count = 0;
$error_count = 0;
$row_number = 1; // Start counting from 1 (after header)
$errors = [];

while (($row = fgetcsv($file)) !== FALSE) {
    $row_number++;
    try {
        // Clean row values
        $row = array_map('trim', $row);

        // Map CSV columns to variables with default values
        $name = isset($row[0]) ? $row[0] : '';
        $email = isset($row[1]) ? $row[1] : '';
        $phone = isset($row[2]) ? $row[2] : '';
        $address = isset($row[3]) ? $row[3] : '';
        $notes = isset($row[4]) ? $row[4] : '';

        // Validate required fields
        if (empty($name)) {
            throw new Exception("Name is required");
        }

        // Validate email format if provided
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format: " . $email);
        }

        // Validate phone format if provided
        if (!empty($phone) && !preg_match('/^[0-9]{10,15}$/', $phone)) {
            throw new Exception("Invalid phone format: " . $phone);
        }

        // Check for duplicate email
        if (!empty($email)) {
            $check_email = $conn->prepare("SELECT id FROM customers WHERE email = ?");
            $check_email->execute([$email]);
            if ($check_email->fetch()) {
                throw new Exception("Email already exists: " . $email);
            }
        }

        // Bind parameters and execute
        $stmt->bindParam(1, $name);
        $stmt->bindParam(2, $email);
        $stmt->bindParam(3, $phone);
        $stmt->bindParam(4, $address);
        $stmt->bindParam(5, $notes);
        
        if ($stmt->execute()) {
            $success_count++;
        } else {
            $error = $stmt->errorInfo();
            throw new Exception("Database error: " . $error[2]);
        }
    } catch (Exception $e) {
        $error_count++;
        $errors[] = "Row $row_number: " . $e->getMessage();
        error_log("Error processing row $row_number: " . $e->getMessage());
    }
}

fclose($file);

// Return the result with detailed error information
echo json_encode([
    'status' => 'success',
    'message' => "Import completed. Successfully imported $success_count records. Failed to import $error_count records.",
    'errors' => array_slice($errors, 0, 10), // Return first 10 errors to avoid overwhelming response
    'sample_data' => [
        'header' => $header,
        'rows' => $sample_rows
    ]
]); 