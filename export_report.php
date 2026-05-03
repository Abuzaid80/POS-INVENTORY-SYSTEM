<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get date range
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="sales_report_' . $start_date . '_to_' . $end_date . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write headers
fputcsv($output, [
    'Sale ID',
    'Date',
    'Product',
    'Category',
    'Quantity',
    'Unit Price',
    'Subtotal',
    'Payment Method',
    'User'
]);

// Fetch and write sales data
$query = "SELECT s.id as sale_id,
                 s.created_at,
                 p.name as product_name,
                 c.name as category_name,
                 si.quantity,
                 si.price_per_unit,
                 si.subtotal,
                 s.payment_method,
                 u.username
          FROM sales s
          JOIN sale_items si ON s.id = si.sale_id
          JOIN products p ON si.product_id = p.id
          JOIN categories c ON p.category_id = c.id
          JOIN users u ON s.user_id = u.id
          WHERE DATE(s.created_at) BETWEEN :start_date AND :end_date
          ORDER BY s.created_at DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $row['sale_id'],
        $row['created_at'],
        $row['product_name'],
        $row['category_name'],
        $row['quantity'],
        $row['price_per_unit'],
        $row['subtotal'],
        $row['payment_method'],
        $row['username']
    ]);
}

fclose($output);
exit(); 