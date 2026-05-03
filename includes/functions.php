<?php
/**
 * Common utility functions for the POS Inventory System
 */

/**
 * Format currency amount
 * @param float $amount The amount to format
 * @param string $currency The currency symbol (default: 'Rp')
 * @return string Formatted currency string
 */
function formatCurrency($amount, $currency = 'Rp') {
    return $currency . ' ' . number_format($amount, 2, ',', '.');
}

/**
 * Format date to Indonesian format
 * @param string $date The date to format
 * @param string $format The output format (default: 'd M Y H:i')
 * @return string Formatted date string
 */
function formatDate($date, $format = 'd M Y H:i') {
    return date($format, strtotime($date));
}

/**
 * Sanitize input data
 * @param string $data The data to sanitize
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Validate email address
 * @param string $email The email to validate
 * @return bool True if email is valid, false otherwise
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Generate a random string
 * @param int $length The length of the string (default: 10)
 * @return string Random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

/**
 * Check if user has required role
 * @param string $requiredRole The required role
 * @return bool True if user has required role, false otherwise
 */
function hasRole($requiredRole) {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    return $_SESSION['user_role'] === $requiredRole;
}

/**
 * Log activity to database
 * @param PDO $db Database connection
 * @param string $action The action performed
 * @param string $description Description of the action
 * @param int $userId User ID who performed the action
 * @return bool True if logged successfully, false otherwise
 */
function logActivity($db, $action, $description, $userId) {
    try {
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, description, created_at) VALUES (?, ?, ?, NOW())");
        return $stmt->execute([$userId, $action, $description]);
    } catch (PDOException $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Get stock status text and class
 * @param int $quantity Current stock quantity
 * @param int $lowStockThreshold Low stock threshold (default: 10)
 * @return array Array containing status text and CSS class
 */
function getStockStatus($quantity, $lowStockThreshold = 10) {
    if ($quantity <= 0) {
        return ['text' => 'Out of Stock', 'class' => 'stock-out'];
    } elseif ($quantity <= $lowStockThreshold) {
        return ['text' => 'Low Stock', 'class' => 'stock-low'];
    } else {
        return ['text' => 'In Stock', 'class' => 'stock-in'];
    }
}

/**
 * Validate CSV file
 * @param array $file The uploaded file array
 * @param array $requiredColumns Required columns in CSV
 * @return array Array containing validation status and message
 */
function validateCsvFile($file, $requiredColumns) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'message' => "File upload failed with error code: " . $file['error']];
    }
    
    if ($file['type'] !== 'text/csv' && $file['type'] !== 'application/vnd.ms-excel') {
        return ['valid' => false, 'message' => "Invalid file type. Please upload a CSV file."];
    }
    
    $handle = fopen($file['tmp_name'], "r");
    if (!$handle) {
        return ['valid' => false, 'message' => "Could not open file for reading."];
    }
    
    $header = fgetcsv($handle);
    fclose($handle);
    
    if (!$header) {
        return ['valid' => false, 'message' => "Could not read CSV header."];
    }
    
    $missingColumns = array_diff($requiredColumns, $header);
    if (!empty($missingColumns)) {
        return ['valid' => false, 'message' => "Missing required columns: " . implode(", ", $missingColumns)];
    }
    
    return ['valid' => true, 'message' => "File is valid."];
}

/**
 * Get pagination data
 * @param int $totalItems Total number of items
 * @param int $currentPage Current page number
 * @param int $itemsPerPage Items per page
 * @return array Array containing pagination data
 */
function getPagination($totalItems, $currentPage = 1, $itemsPerPage = 10) {
    $totalPages = ceil($totalItems / $itemsPerPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    
    return [
        'currentPage' => $currentPage,
        'totalPages' => $totalPages,
        'itemsPerPage' => $itemsPerPage,
        'totalItems' => $totalItems,
        'offset' => ($currentPage - 1) * $itemsPerPage
    ];
}

/**
 * Generate pagination HTML
 * @param array $pagination Pagination data from getPagination()
 * @param string $url Base URL for pagination links
 * @return string HTML for pagination
 */
function generatePaginationHtml($pagination, $url) {
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // Previous button
    $html .= '<li class="page-item ' . ($pagination['currentPage'] <= 1 ? 'disabled' : '') . '">';
    $html .= '<a class="page-link" href="' . $url . '?page=' . ($pagination['currentPage'] - 1) . '" aria-label="Previous">';
    $html .= '<span aria-hidden="true">&laquo;</span></a></li>';
    
    // Page numbers
    for ($i = 1; $i <= $pagination['totalPages']; $i++) {
        $html .= '<li class="page-item ' . ($pagination['currentPage'] == $i ? 'active' : '') . '">';
        $html .= '<a class="page-link" href="' . $url . '?page=' . $i . '">' . $i . '</a></li>';
    }
    
    // Next button
    $html .= '<li class="page-item ' . ($pagination['currentPage'] >= $pagination['totalPages'] ? 'disabled' : '') . '">';
    $html .= '<a class="page-link" href="' . $url . '?page=' . ($pagination['currentPage'] + 1) . '" aria-label="Next">';
    $html .= '<span aria-hidden="true">&raquo;</span></a></li>';
    
    $html .= '</ul></nav>';
    return $html;
}
?> 