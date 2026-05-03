<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="sales_import_template.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel encoding
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write header row
fputcsv($output, [
    'Date',
    'Total Amount',
    'Customer ID',
    'Payment Method',
    'Notes'
]);

// Write example data
$example_data = [
    ['2024-03-20', '1500.00', '1', 'cash', 'Regular sale for customer John Doe'],
    ['2024-03-20', '2500.00', '2', 'cash', 'Multiple items purchase'],
    ['2024-03-21', '3500.00', '1', 'credit_card', 'Wholesale order'],
    ['2024-03-21', '1200.00', '2', 'debit_card', 'Regular customer purchase'],
    ['2024-03-22', '5000.00', '1', 'cash', 'Bulk order with discount']
];

foreach ($example_data as $row) {
    fputcsv($output, $row);
}

// Close the output stream
fclose($output); 