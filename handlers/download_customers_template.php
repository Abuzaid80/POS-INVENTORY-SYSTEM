<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=customers_import_template.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel encoding
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write header row
fputcsv($output, ['Name', 'Email', 'Phone', 'Address', 'Notes']);

// Add example data
$examples = [
    ['John Doe', 'john.doe@example.com', '081234567890', '123 Main St Jakarta', 'Regular customer'],
    ['Jane Smith', 'jane.smith@example.com', '089876543210', '456 Oak Ave Bandung', 'Wholesale buyer'],
    ['Bob Johnson', 'bob.j@example.com', '087654321098', '789 Pine Rd Surabaya', 'New customer'],
    ['Alice Brown', 'alice.b@example.com', '086543210987', '321 Elm St Yogyakarta', 'Corporate client'],
    ['Charlie Wilson', 'charlie.w@example.com', '085432109876', '654 Maple Dr Medan', 'Regular buyer']
];

foreach ($examples as $row) {
    fputcsv($output, $row);
}

fclose($output); 