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
$stmt = $conn->prepare("INSERT INTO sales (created_at, total_amount, customer_id, payment_method, notes) VALUES (?, ?, ?, ?, ?)");
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
$imported_data = []; // Array to store imported data

while (($row = fgetcsv($file)) !== FALSE) {
    $row_number++;
    try {
        // Clean row values
        $row = array_map('trim', $row);

        // Map CSV columns to variables with default values
        $date_str = isset($row[0]) ? $row[0] : ''; // Date
        $total_amount = isset($row[1]) ? $row[1] : '0'; // Total Amount
        $customer_id = isset($row[2]) ? $row[2] : ''; // Customer ID
        $payment_method = isset($row[3]) ? strtolower($row[3]) : 'cash'; // Payment Method
        $notes = isset($row[4]) ? $row[4] : ''; // Notes

        // Check if customer exists
        if (!empty($customer_id)) {
            $check_customer = $conn->prepare("SELECT id, name FROM customers WHERE id = ?");
            $check_customer->execute([$customer_id]);
            $customer = $check_customer->fetch(PDO::FETCH_ASSOC);
            if (!$customer) {
                // Customer doesn't exist, set to NULL and log warning
                error_log("Warning: Customer ID $customer_id not found in database, setting to NULL");
                $customer_id = null;
            } else {
                // Customer exists, use their name in notes
                $customer_name = $customer['name'];
            }
        } else {
            $customer_id = null;
        }

        // Format the date - try multiple formats
        $date = null;
        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'Y'];
        
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $date_str);
            if ($date !== false) {
                break;
            }
        }

        if ($date === false) {
            throw new Exception("Invalid date format: " . $date_str);
        }

        // If it's just a year, set to January 1st
        if (strlen($date_str) === 4) {
            $date->setDate($date->format('Y'), 1, 1);
        }

        $created_at = $date->format('Y-m-d H:i:s');

        // Validate total amount
        if (!is_numeric($total_amount)) {
            throw new Exception("Invalid total amount: " . $total_amount);
        }

        // Ensure total amount fits within DECIMAL(10,2)
        if (floatval($total_amount) > 99999999.99) {
            $total_amount = 99999999.99;
            error_log("Warning: Total amount truncated to maximum value for row $row_number");
        }

        // Validate payment method
        $allowed_payment_methods = ['cash', 'credit_card', 'debit_card', 'bank_transfer'];
        if (!in_array($payment_method, $allowed_payment_methods)) {
            $payment_method = 'cash'; // Default to cash if invalid
            error_log("Warning: Invalid payment method '$payment_method' for row $row_number, defaulting to 'cash'");
        }

        // Create notes from available data
        $formatted_notes = sprintf(
            "Amount: %s, Payment Method: %s",
            number_format(floatval($total_amount), 2),
            $payment_method
        );

        // Add customer info to notes if available
        if ($customer_id !== null && isset($customer_name)) {
            $formatted_notes = "Customer: " . $customer_name . " (ID: " . $customer_id . "), " . $formatted_notes;
        } elseif ($customer_id !== null) {
            $formatted_notes = "Customer ID: " . $customer_id . ", " . $formatted_notes;
        }

        // If additional notes are provided, append them
        if (!empty($notes)) {
            $formatted_notes .= " - " . $notes;
        }

        // Log the data being inserted
        error_log("Inserting sale: Date=$created_at, Amount=$total_amount, CustomerID=$customer_id, PaymentMethod=$payment_method");

        // Bind parameters and execute
        $stmt->bindParam(1, $created_at);
        $stmt->bindParam(2, $total_amount);
        $stmt->bindParam(3, $customer_id);
        $stmt->bindParam(4, $payment_method);
        $stmt->bindParam(5, $formatted_notes);
        
        if ($stmt->execute()) {
            $success_count++;
            // Store the imported data
            $imported_data[] = [
                'date' => $created_at,
                'total_amount' => number_format(floatval($total_amount), 2),
                'customer_id' => $customer_id,
                'customer_name' => $customer_name ?? null,
                'payment_method' => $payment_method,
                'notes' => $formatted_notes
            ];
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

// Prepare the response data
if ($success_count > 0) {
    // Set success message in session
    $_SESSION['import_success'] = "Import completed. Successfully imported $success_count records. Failed to import $error_count records.";
    
    // Store any errors in session
    if (!empty($errors)) {
        $_SESSION['import_errors'] = array_slice($errors, 0, 10);
    }
    
    // Store imported data summary in session
    if (!empty($imported_data)) {
        $_SESSION['imported_data'] = array_slice($imported_data, 0, 5);
    }
    
    // Redirect back to sales page
    header('Location: ../sales.php');
    exit;
} else {
    // If no records were imported successfully, return error
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'error',
        'message' => 'No records were imported successfully.'
    ]);
    exit;
} 