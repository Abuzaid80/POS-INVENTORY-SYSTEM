<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$database = new Database();
$db = $database->getConnection();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fetch inventory data
try {
    // Fetch products with low stock
    $low_stock_query = "SELECT p.*, c.name as category_name 
                       FROM products p 
                       LEFT JOIN categories c ON p.category_id = c.id 
                       WHERE p.stock_quantity <= 10
                       ORDER BY p.stock_quantity ASC";
    $low_stock_stmt = $db->prepare($low_stock_query);
    $low_stock_stmt->execute();
    $low_stock_products = $low_stock_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch recent inventory movements
    $movements_query = "SELECT il.*, p.name as product_name
                       FROM inventory_logs il
                       LEFT JOIN products p ON il.product_id = p.id
                       ORDER BY il.created_at DESC
                       LIMIT 50";
    $movements_stmt = $db->prepare($movements_query);
    $movements_stmt->execute();
    $recent_movements = $movements_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all products for adjustment
    $products_query = "SELECT p.*, c.name as category_name 
                      FROM products p 
                      LEFT JOIN categories c ON p.category_id = c.id 
                      ORDER BY p.name";
    $products_stmt = $db->prepare($products_query);
    $products_stmt->execute();
    $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate inventory statistics
    $total_products = count($products);
    $in_stock = 0;
    $low_stock = 0;
    $out_of_stock = 0;

    foreach ($products as $product) {
        if ($product['stock_quantity'] == 0) {
            $out_of_stock++;
        } elseif ($product['stock_quantity'] <= 10) {
            $low_stock++;
        } else {
            $in_stock++;
        }
    }

    // Fetch inventory movement statistics
    $movement_stats_query = "SELECT 
        COUNT(*) as total_movements,
        SUM(CASE WHEN type = 'in' THEN 1 ELSE 0 END) as stock_in_count,
        SUM(CASE WHEN type = 'out' THEN 1 ELSE 0 END) as stock_out_count,
        SUM(CASE WHEN type = 'adjustment' THEN 1 ELSE 0 END) as adjustment_count
        FROM inventory_logs";
    $movement_stats_stmt = $db->prepare($movement_stats_query);
    $movement_stats_stmt->execute();
    $movement_stats = $movement_stats_stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database Error in inventory.php: " . $e->getMessage());
    // Set default values in case of error
    $total_products = 0;
    $in_stock = 0;
    $low_stock = 0;
    $out_of_stock = 0;
    $products = [];
    $low_stock_products = [];
    $recent_movements = [];
    $movement_stats = [
        'total_movements' => 0,
        'stock_in_count' => 0,
        'stock_out_count' => 0,
        'adjustment_count' => 0
    ];
}

// Handle CSV Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    try {
        $file = $_FILES['csv_file'];
        $importType = $_POST['import_type'] ?? 'products';
        
        // Log upload attempt
        error_log("Starting file upload process - Type: $importType, File: " . $file['name']);
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error_message = "File upload failed with error code: " . $file['error'];
            error_log("File upload error: $error_message");
            throw new Exception($error_message);
        }
        
        if ($file['type'] !== 'text/csv' && $file['type'] !== 'application/vnd.ms-excel') {
            $error_message = "Invalid file type: " . $file['type'];
            error_log("Invalid file type: $error_message");
            throw new Exception("Invalid file type. Please upload a CSV file.");
        }
        
        // Start transaction
        $db->beginTransaction();
        error_log("Database transaction started");
        
        // Open file
        $handle = fopen($file['tmp_name'], "r");
        if (!$handle) {
            error_log("Failed to open file: " . $file['tmp_name']);
            throw new Exception("Could not open file for reading.");
        }
        
        // Read header row
        $header = fgetcsv($handle);
        if (!$header) {
            error_log("Failed to read CSV header from file: " . $file['name']);
            throw new Exception("Could not read CSV header.");
        }
        
        error_log("CSV Header: " . implode(", ", $header));
        
        // Skip comment lines
        while (isset($header[0]) && strpos($header[0], '#') === 0) {
            $header = fgetcsv($handle);
            if (!$header) {
                error_log("Failed to read CSV header after comments in file: " . $file['name']);
                throw new Exception("Could not read CSV header after comments.");
            }
        }
        
        // Define required columns based on import type
        $requiredColumns = $importType === 'products' 
            ? ['name', 'description', 'price', 'quantity', 'category_id', 'low_stock_threshold']  // Added low_stock_threshold
            : ['product_id', 'quantity_change', 'type', 'reference_type', 'reference_id', 'notes'];
        
        // Validate required columns
        $missingColumns = array_diff($requiredColumns, $header);
        if (!empty($missingColumns)) {
            $error_message = "Missing required columns: " . implode(", ", $missingColumns);
            error_log("Missing columns in file: $error_message");
            throw new Exception($error_message . ". Please download the template and try again.");
        }
        
        // Get column indices
        $indices = [];
        foreach ($requiredColumns as $column) {
            $indices[$column] = array_search($column, $header);
            if ($indices[$column] === false) {
                error_log("Column '$column' not found in CSV header: " . implode(", ", $header));
                throw new Exception("Column '$column' not found in CSV file.");
            }
        }
        
        error_log("Column indices: " . json_encode($indices));
        
        // Prepare statements based on import type
        if ($importType === 'products') {
            $category_stmt = $db->prepare("INSERT IGNORE INTO categories (name) VALUES (?)");
            $product_stmt = $db->prepare("INSERT INTO products (name, description, category_id, stock_quantity, price, low_stock_threshold) 
                                        VALUES (?, ?, (SELECT id FROM categories WHERE name = ?), ?, ?, ?)");
            error_log("Prepared statements for products import");
        } else {
            $movement_stmt = $db->prepare("INSERT INTO inventory_logs (product_id, quantity_change, type, 
                                         reference_type, reference_id, notes, created_at) 
                                         VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $update_stock_stmt = $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? 
                                             WHERE id = ?");
            error_log("Prepared statements for movements import");
        }
        
        $row = 2; // Start from row 2 (after header)
        $stats = [
            'total_rows' => 0,
            'success_count' => 0,
            'error_count' => 0,
            'skipped_count' => 0,
            'errors' => [],
            'warnings' => [],
            'categories_created' => 0
        ];
        
        // Process each row
        while (($data = fgetcsv($handle)) !== FALSE) {
            // Skip empty rows
            if (empty(array_filter($data))) {
                error_log("Skipping empty row at line $row");
                continue;
            }
            
            $stats['total_rows']++;
            try {
                if ($importType === 'products') {
                    // Process product import
                    $name = trim($data[$indices['name']]);
                    $description = trim($data[$indices['description']] ?? '');
                    $category_id = (int)$data[$indices['category_id']];
                    $price = (float)$data[$indices['price']];
                    $low_stock_threshold = (int)($data[$indices['low_stock_threshold']] ?? 10); // Default to 10 if not provided
                    
                    // Handle both stock_quantity and quantity column names
                    $stock_quantity = 0;
                    if (isset($indices['stock_quantity'])) {
                        $stock_quantity = (int)$data[$indices['stock_quantity']];
                    } elseif (isset($indices['quantity'])) {
                        $stock_quantity = (int)$data[$indices['quantity']];
                    }
                    
                    error_log("Processing row $row - Product: $name, Category ID: $category_id, Quantity: $stock_quantity, Low Stock Threshold: $low_stock_threshold");
                    
                    // Validate data
                    if (empty($name)) {
                        error_log("Empty product name at row $row");
                        throw new Exception("Product name cannot be empty");
                    }
                    if ($category_id <= 0) {
                        error_log("Invalid category ID at row $row: $category_id");
                        throw new Exception("Invalid category ID");
                    }
                    if ($stock_quantity < 0) {
                        error_log("Invalid quantity at row $row: $stock_quantity");
                        throw new Exception("Stock quantity must be greater than or equal to 0");
                    }
                    if ($price < 0) {
                        error_log("Invalid price at row $row: $price");
                        throw new Exception("Price must be greater than or equal to 0");
                    }
                    if ($low_stock_threshold < 0) {
                        error_log("Invalid low stock threshold at row $row: $low_stock_threshold");
                        throw new Exception("Low stock threshold must be greater than or equal to 0");
                    }
                    
                    // Check for duplicate products
                    $check_stmt = $db->prepare("SELECT id FROM products WHERE name = ?");
                    $check_stmt->execute([$name]);
                    if ($check_stmt->fetch()) {
                        $warning = "Row $row: Product '$name' already exists - skipped";
                        error_log($warning);
                        $stats['warnings'][] = $warning;
                        $stats['skipped_count']++;
                        continue;
                    }
                    
                    // Check if category exists
                    $check_category_stmt = $db->prepare("SELECT id FROM categories WHERE id = ?");
                    $check_category_stmt->execute([$category_id]);
                    if (!$check_category_stmt->fetch()) {
                        error_log("Category ID not found at row $row: $category_id");
                        throw new Exception("Category ID $category_id does not exist");
                    }
                    
                    // Insert product with all fields including low_stock_threshold
                    $product_stmt = $db->prepare("INSERT INTO products (name, description, category_id, stock_quantity, price, low_stock_threshold) 
                                                VALUES (?, ?, ?, ?, ?, ?)");
                    $product_stmt->execute([$name, $description, $category_id, $stock_quantity, $price, $low_stock_threshold]);
                    error_log("Successfully inserted product: $name");
                    $stats['success_count']++;
                } else {
                    // Process inventory movement
                    $product_id = (int)$data[$indices['product_id']];
                    $quantity_change = (int)$data[$indices['quantity_change']];
                    $type = trim($data[$indices['type']]);
                    $reference_type = trim($data[$indices['reference_type']]);
                    $reference_id = (int)$data[$indices['reference_id']];
                    $notes = trim($data[$indices['notes']] ?? '');
                    
                    error_log("Processing row $row - Product ID: $product_id, Quantity: $quantity_change, Type: $type");
                    
                    // Validate data
                    if ($product_id <= 0) {
                        error_log("Invalid product ID at row $row: $product_id");
                        throw new Exception("Invalid product ID");
                    }
                    if (!in_array($type, ['in', 'out', 'adjustment'])) {
                        error_log("Invalid type at row $row: $type");
                        throw new Exception("Invalid type. Must be one of: in, out, adjustment");
                    }
                    if (!in_array($reference_type, ['sale', 'purchase', 'adjustment'])) {
                        error_log("Invalid reference type at row $row: $reference_type");
                        throw new Exception("Invalid reference type. Must be one of: sale, purchase, adjustment");
                    }
                    
                    // Check if product exists
                    $check_stmt = $db->prepare("SELECT id, stock_quantity FROM products WHERE id = ?");
                    $check_stmt->execute([$product_id]);
                    $product = $check_stmt->fetch();
                    
                    if (!$product) {
                        error_log("Product not found at row $row - ID: $product_id");
                        throw new Exception("Product ID $product_id does not exist");
                    }
                    
                    // Check for negative stock
                    if ($type === 'out' && ($product['stock_quantity'] + $quantity_change) < 0) {
                        $warning = "Row $row: Insufficient stock for product ID $product_id - skipped";
                        error_log($warning);
                        $stats['warnings'][] = $warning;
                        $stats['skipped_count']++;
                        continue;
                    }
                    
                    // Insert movement log
                    $movement_stmt->execute([
                        $product_id, 
                        $quantity_change, 
                        $type, 
                        $reference_type, 
                        $reference_id, 
                        $notes
                    ]);
                    
                    // Update product stock
                    $update_stock_stmt->execute([$quantity_change, $product_id]);
                    
                    error_log("Successfully processed row $row - Product ID: $product_id");
                    $stats['success_count']++;
                }
            } catch (Exception $e) {
                $error_message = "Row $row: " . $e->getMessage();
                error_log("Error processing row $row: " . $e->getMessage());
                $stats['error_count']++;
                $stats['errors'][] = $error_message;
            }
            $row++;
        }
        
        fclose($handle);
        error_log("File processing completed. Stats: " . json_encode($stats));
        
        // Commit or rollback based on results
        if ($stats['error_count'] === 0) {
            $db->commit();
            error_log("Transaction committed successfully");
            
            if ($importType === 'products') {
                $successMessage = "Products imported successfully!\n";
                $successMessage .= "Total products processed: " . $stats['total_rows'] . "\n";
                $successMessage .= "Successfully imported: " . $stats['success_count'] . "\n";
                
                if ($stats['categories_created'] > 0) {
                    $successMessage .= "New categories created: " . $stats['categories_created'] . "\n";
                }
                
                if ($stats['skipped_count'] > 0) {
                    $successMessage .= "Skipped products: " . $stats['skipped_count'] . "\n";
                    foreach ($stats['warnings'] as $warning) {
                        $successMessage .= "- " . $warning . "\n";
                    }
                }
            } else {
                $successMessage = "Inventory movements imported successfully!\n";
                $successMessage .= "Total movements processed: " . $stats['total_rows'] . "\n";
                $successMessage .= "Successfully imported: " . $stats['success_count'] . "\n";
                
                if ($stats['skipped_count'] > 0) {
                    $successMessage .= "Skipped movements: " . $stats['skipped_count'] . "\n";
                    foreach ($stats['warnings'] as $warning) {
                        $successMessage .= "- " . $warning . "\n";
                    }
                }
            }
            
            $_SESSION['success'] = nl2br($successMessage);
        } else {
            $db->rollBack();
            error_log("Transaction rolled back due to errors. Error count: " . $stats['error_count']);
            $_SESSION['error'] = "Import completed with errors. Please check the details below.";
        }
        
    } catch (Exception $e) {
        if (isset($handle) && is_resource($handle)) {
            fclose($handle);
        }
        if ($db->inTransaction()) {
            $db->rollBack();
            error_log("Transaction rolled back due to exception: " . $e->getMessage());
        }
        error_log("Fatal error in file upload process: " . $e->getMessage());
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header("Location: inventory.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - POS Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="assets/css/navigation.css" rel="stylesheet">
    <style>
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background-color: #2c3e50;
            color: #fff;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            color: #fff;
            text-decoration: none;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .sidebar-brand i {
            margin-right: 10px;
            font-size: 1.5rem;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-item {
            margin: 5px 0;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #ecf0f1;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .sidebar-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
            transform: translateX(5px);
        }

        .sidebar-link.active {
            background-color: #3498db;
            color: #fff;
        }

        .sidebar-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        /* Main Content Styles */
        .main-content {
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
            background-color: #f8f9fa;
            transition: all 0.3s ease;
            width: calc(100% - 280px);
        }

        .container-fluid {
            padding-right: 30px;
            padding-left: 30px;
            width: 100%;
            margin-right: auto;
            margin-left: auto;
        }

        /* Card Styles */
        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }

        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.25rem;
        }

        .card-body {
            padding: 1.25rem;
        }

        /* Table Styles */
        .table-responsive {
            width: 100%;
            margin-bottom: 1rem;
        }

        .table {
            width: 100%;
            margin-bottom: 1rem;
            background-color: transparent;
        }

        /* Search Box */
        .search-box {
            position: relative;
            width: 100%;
            max-width: 400px;
        }

        /* Responsive Styles */
        @media (max-width: 1200px) {
            .container-fluid {
                padding-right: 20px;
                padding-left: 20px;
            }
        }

        @media (max-width: 992px) {
            .main-content {
                padding: 20px;
            }
            .container-fluid {
                padding-right: 15px;
                padding-left: 15px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 15px;
            }

            .main-content.active {
                margin-left: 280px;
                width: calc(100% - 280px);
            }

            .container-fluid {
                padding-right: 10px;
                padding-left: 10px;
            }

            .card {
                margin-bottom: 1rem;
            }

            .search-box {
                max-width: 100%;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 10px;
            }
            .container-fluid {
                padding-right: 5px;
                padding-left: 5px;
            }
            .card-body {
                padding: 1rem;
            }
            .table-responsive {
                overflow-x: auto;
            }
        }

        /* Product Card Styles */
        .product-card {
            border-radius: 0.5rem;
            transition: transform 0.2s;
        }
        .product-card:hover {
            transform: translateY(-5px);
        }
        .product-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 0.5rem;
        }
        .product-info {
            flex: 1;
        }
        .product-actions {
            display: flex;
            gap: 0.5rem;
        }
        .search-box {
            position: relative;
        }
        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        .search-box input {
            padding-left: 2.5rem;
        }
        .stats-card {
            border-radius: 0.5rem;
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-icon {
            font-size: 2rem;
            opacity: 0.8;
        }
        .stock-status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.875rem;
        }
        .stock-in {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        .stock-low {
            background-color: #fff3cd;
            color: #856404;
        }
        .stock-out {
            background-color: #f8d7da;
            color: #842029;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="sidebar-brand">
                <i class="fas fa-store"></i>
                <span>POS System</span>
            </a>
        </div>
        <ul class="sidebar-menu">
            <li class="sidebar-item">
                <a href="index.php" class="sidebar-link">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="products.php" class="sidebar-link">
                    <i class="fas fa-box"></i>
                    <span>Products</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="doctors.php" class="sidebar-link">
                    <i class="fas fa-user-md"></i>
                    <span>Doctors</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="categories.php" class="sidebar-link">
                    <i class="fas fa-tags"></i>
                    <span>Categories</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="business_visit.php" class="sidebar-link">
                    <i class="fas fa-building"></i>
                    <span>Business Visits</span>
                </a>
            </li>
            <li class="sidebar-item active">
                <a href="inventory.php" class="sidebar-link">
                    <i class="fas fa-warehouse"></i>
                    <span>Inventory</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="sales.php" class="sidebar-link">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Sales</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="customers.php" class="sidebar-link">
                    <i class="fas fa-users"></i>
                    <span>Customers</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="reports.php" class="sidebar-link">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="settings.php" class="sidebar-link">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="logout.php" class="sidebar-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Notification Container -->
            <div class="notification-container">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-circle me-2"></i>
                            <div>
                                <?php 
                                echo $_SESSION['success'];
                                unset($_SESSION['success']);
                                ?>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <div>
                                <?php 
                                echo $_SESSION['error'];
                                unset($_SESSION['error']);
                                ?>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="row mb-4">
                <div class="col-12">
                    <h2>Inventory Management</h2>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stats-card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-2">Total Products</h6>
                                    <h3 class="mb-0 fw-bold"><?php echo $total_products; ?></h3>
                                </div>
                                <i class="fas fa-box stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-2">In Stock</h6>
                                    <h3 class="mb-0 fw-bold"><?php echo $in_stock; ?></h3>
                                </div>
                                <i class="fas fa-check-circle stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-2">Low Stock</h6>
                                    <h3 class="mb-0 fw-bold"><?php echo $low_stock; ?></h3>
                                </div>
                                <i class="fas fa-exclamation-triangle stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card bg-danger text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-2">Out of Stock</h6>
                                    <h3 class="mb-0 fw-bold"><?php echo $out_of_stock; ?></h3>
                                </div>
                                <i class="fas fa-times-circle stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Add Inventory -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" class="form-control" id="searchInput" placeholder="Search inventory...">
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#uploadCsvModal">
                        <i class="fas fa-file-import me-2"></i> Import CSV
                    </button>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addInventoryModal">
                        <i class="fas fa-plus me-2"></i> Add Inventory
                    </button>
                </div>
            </div>

            <!-- Products Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="productsTable">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td class="align-middle">
                                            <?php if (!empty($product['image'])): ?>
                                                <div class="product-image-container" style="width: 50px; height: 50px; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                                                    <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                         style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                                </div>
                                            <?php else: ?>
                                                <div class="product-image-placeholder" style="width: 50px; height: 50px; background-color: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-image text-muted" style="font-size: 1.5rem;"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-medium"><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                        <td class="fw-medium">Rp <?php echo number_format($product['price'], 2); ?></td>
                                        <td class="fw-medium"><?php echo number_format($product['stock_quantity']); ?></td>
                                        <td>
                                            <?php if ($product['stock_quantity'] == 0): ?>
                                                <span class="stock-status stock-out">Out of Stock</span>
                                            <?php elseif ($product['stock_quantity'] <= 10): ?>
                                                <span class="stock-status stock-low">Low Stock</span>
                                            <?php else: ?>
                                                <span class="stock-status stock-in">In Stock</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-primary" onclick="adjustStock(<?php echo $product['id']; ?>)">
                                                <i class="fas fa-edit"></i> Adjust
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Movements -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Inventory Movements</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="movementsTable">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Type</th>
                                    <th>Reference</th>
                                    <th>Quantity</th>
                                    <th>Notes</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_movements as $movement): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($movement['product_name']); ?></td>
                                        <td>
                                            <?php
                                            $type_class = $movement['type'] == 'in' ? 'success' : 
                                                ($movement['type'] == 'out' ? 'danger' : 'warning');
                                            $type_text = ucfirst($movement['type']);
                                            ?>
                                            <span class="badge bg-<?php echo $type_class; ?>"><?php echo $type_text; ?></span>
                                        </td>
                                        <td><?php echo ucfirst($movement['reference_type']); ?></td>
                                        <td>
                                            <?php
                                            $quantity_class = $movement['quantity_change'] > 0 ? 'success' : 'danger';
                                            $quantity_text = $movement['quantity_change'] > 0 ? '+' : '';
                                            $quantity_text .= $movement['quantity_change'];
                                            ?>
                                            <span class="badge bg-<?php echo $quantity_class; ?>"><?php echo $quantity_text; ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($movement['notes'] ?? ''); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($movement['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Adjust Stock Modal -->
    <div class="modal fade" id="adjustStockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Adjust Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="adjustStockForm">
                        <input type="hidden" name="action" value="adjust_stock">
                        <input type="hidden" name="product_id" id="adjust_product_id">
                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <select class="form-select" name="product_id" required>
                                <option value="">Select Product</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo $product['id']; ?>">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quantity Change</label>
                            <div class="input-group">
                                <button type="button" class="btn btn-outline-secondary" onclick="decrementQuantity()">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number" class="form-control text-center" name="quantity_change" value="0" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="incrementQuantity()">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="type" required>
                                <option value="">Select Type</option>
                                <option value="in">Stock In</option>
                                <option value="out">Stock Out</option>
                                <option value="adjustment">Adjustment</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reference Type</label>
                            <select class="form-select" name="reference_type" required>
                                <option value="">Select Reference Type</option>
                                <option value="sale">Sale</option>
                                <option value="purchase">Purchase</option>
                                <option value="adjustment">Adjustment</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitAdjustment()">Adjust Stock</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload CSV Modal -->
    <div class="modal fade" id="uploadCsvModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Data from CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadCsvForm" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Import Type</label>
                            <select class="form-select" name="import_type" id="importType" required>
                                <option value="products">Import Products</option>
                                <option value="movements">Import Inventory Movements</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">CSV File</label>
                            <input type="file" class="form-control" name="csv_file" accept=".csv" required>
                        </div>
                        <div class="mb-3">
                            <div id="productsTemplate" class="import-template">
                                <h6>Products Import Format:</h6>
                                <p>Required columns: name, description, price, stock_quantity, category</p>
                                <a href="templates/products_template.csv" download class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-download me-2"></i>Download Products Template
                                </a>
                            </div>
                            <div id="movementsTemplate" class="import-template" style="display: none;">
                                <h6>Inventory Movements Import Format:</h6>
                                <p>Required columns: product_id, quantity_change, type, reference_type, reference_id, notes</p>
                                <a href="templates/movements_template.csv" download class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-download me-2"></i>Download Movements Template
                                </a>
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Upload</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTables
            $('#productsTable').DataTable({
                order: [[1, 'asc']],
                pageLength: 10
            });
            
            $('#movementsTable').DataTable({
                order: [[5, 'desc']],
                pageLength: 10
            });

            // Handle search
            $('#searchInput').on('keyup', function() {
                $('#productsTable').DataTable().search(this.value).draw();
            });
        });

        // Handle stock adjustment
        function incrementQuantity() {
            const input = document.querySelector('input[name="quantity_change"]');
            input.value = parseInt(input.value) + 1;
        }

        function decrementQuantity() {
            const input = document.querySelector('input[name="quantity_change"]');
            input.value = parseInt(input.value) - 1;
        }

        function adjustStock(id) {
            document.getElementById('adjust_product_id').value = id;
            new bootstrap.Modal(document.getElementById('adjustStockModal')).show();
        }

        function submitAdjustment() {
            const form = document.getElementById('adjustStockForm');
            const formData = new FormData(form);

            fetch('inventory.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    location.reload();
                } else {
                    alert(data.message || 'An error occurred');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
            });
        }

        // Handle import type change
        $('#importType').change(function() {
            const type = $(this).val();
            $('.import-template').hide();
            $(`#${type}Template`).show();
        });

        // Enhanced CSV Upload Handler
        $('#uploadCsvForm').on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitButton = $(this).find('button[type="submit"]');
            const originalText = submitButton.html();
            
            // Show loading state
            submitButton.prop('disabled', true)
                .html('<i class="fas fa-spinner fa-spin"></i> Processing...');
            
            $.ajax({
                url: 'inventory.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    try {
                        // Check if response is JSON
                        const result = typeof response === 'object' ? response : JSON.parse(response);
                        
                        if (result.status === 'success') {
                            // Show success notification
                            const toast = $(
                                `<div class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                                    <div class="d-flex">
                                        <div class="toast-body">
                                            <i class="fas fa-check-circle me-2"></i>
                                            ${result.message}
                                        </div>
                                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                                    </div>
                                </div>`
                            );
                            $('.notification-container').append(toast);
                            const bsToast = new bootstrap.Toast(toast);
                            bsToast.show();
                            
                            // Close modal and reset form
                            $('#uploadCsvModal').modal('hide');
                            $('#uploadCsvForm')[0].reset();
                            
                            // Reload page after a short delay
                            setTimeout(() => {
                                location.reload();
                            }, 2000);
                        } else {
                            // Show error notification with details
                            let errorMessage = result.message;
                            if (result.details) {
                                errorMessage += `<br><br>Import Summary:<br>`;
                                errorMessage += `- Total Rows: ${result.details.total_rows}<br>`;
                                errorMessage += `- Successfully Imported: ${result.details.success_count}<br>`;
                                if (result.details.skipped_count > 0) {
                                    errorMessage += `- Skipped: ${result.details.skipped_count}<br>`;
                                }
                                if (result.details.error_count > 0) {
                                    errorMessage += `- Errors: ${result.details.error_count}<br>`;
                                }
                            }
                            
                            const toast = $(
                                `<div class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                                    <div class="d-flex">
                                        <div class="toast-body">
                                            <i class="fas fa-exclamation-circle me-2"></i>
                                            ${errorMessage}
                                        </div>
                                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                                    </div>
                                </div>`
                            );
                            $('.notification-container').append(toast);
                            const bsToast = new bootstrap.Toast(toast);
                            bsToast.show();
                            
                            // If there were some successful imports, reload the page
                            if (result.details && result.details.success_count > 0) {
                                setTimeout(() => {
                                    location.reload();
                                }, 5000);
                            }
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                        console.error('Raw response:', response);
                        
                        // Show generic error notification
                        const toast = $(
                            `<div class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                                <div class="d-flex">
                                    <div class="toast-body">
                                        <i class="fas fa-exclamation-circle me-2"></i>
                                        An error occurred while processing the response. Please try again.
                                    </div>
                                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                                </div>
                            </div>`
                        );
                        $('.notification-container').append(toast);
                        const bsToast = new bootstrap.Toast(toast);
                        bsToast.show();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    console.error('Status:', status);
                    console.error('Response:', xhr.responseText);
                    
                    // Show error notification
                    const toast = $(
                        `<div class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                            <div class="d-flex">
                                <div class="toast-body">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    An error occurred while processing your request. Please try again.
                                </div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                            </div>
                        </div>`
                    );
                    $('.notification-container').append(toast);
                    const bsToast = new bootstrap.Toast(toast);
                    bsToast.show();
                },
                complete: function() {
                    // Re-enable submit button and restore original text
                    submitButton.prop('disabled', false).html(originalText);
                }
            });
        });
    </script>
</body>
</html>