<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Handle product actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add_product':
                    // Validate required fields
                    $required_fields = ['name', 'price', 'quantity', 'category_id', 'low_stock_threshold'];
                    foreach ($required_fields as $field) {
                        if (empty($_POST[$field])) {
                            throw new Exception("Please fill in all required fields.");
                        }
                    }

                    // Validate numeric fields
                    if (!is_numeric($_POST['price']) || $_POST['price'] <= 0) {
                        throw new Exception("Price must be a positive number.");
                    }
                    if (!is_numeric($_POST['quantity']) || $_POST['quantity'] < 0) {
                        throw new Exception("Quantity must be a non-negative number.");
                    }
                    if (!is_numeric($_POST['low_stock_threshold']) || $_POST['low_stock_threshold'] < 0) {
                        throw new Exception("Low stock threshold must be a non-negative number.");
                    }

                    $query = "INSERT INTO products (name, description, price, quantity, category_id, low_stock_threshold, image) 
                             VALUES (:name, :description, :price, :quantity, :category_id, :low_stock_threshold, :image)";
                    $stmt = $db->prepare($query);
                    
                    // Bind parameters with proper type casting
                    $stmt->bindParam(':name', $_POST['name'], PDO::PARAM_STR);
                    $stmt->bindParam(':description', $_POST['description'], PDO::PARAM_STR);
                    $stmt->bindParam(':price', $_POST['price'], PDO::PARAM_STR);
                    $stmt->bindParam(':quantity', $_POST['quantity'], PDO::PARAM_INT);
                    $stmt->bindParam(':category_id', $_POST['category_id'], PDO::PARAM_INT);
                    $stmt->bindParam(':low_stock_threshold', $_POST['low_stock_threshold'], PDO::PARAM_INT);
                    
                    // Handle image upload
                    $image_path = null;
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = __DIR__ . '/uploads/products/';
                    if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        $filename = uniqid() . '_' . $_FILES['image']['name'];
                        $target_path = $upload_dir . $filename;
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                            $image_path = 'uploads/products/' . $filename;
                        }
                    }
                    $stmt->bindParam(':image', $image_path, PDO::PARAM_STR);
                    
                    if ($stmt->execute()) {
                        echo json_encode(['status' => 'success', 'message' => 'Product added successfully']);
                    } else {
                        throw new Exception("Failed to add product to database.");
                    }
                    exit();
                    break;

                case 'update_product':
                    try {
                        // Validate required fields
                        $required_fields = ['id', 'name', 'price', 'quantity', 'category_id', 'low_stock_threshold'];
                        foreach ($required_fields as $field) {
                            if (empty($_POST[$field])) {
                                throw new Exception("Please fill in all required fields.");
                            }
                        }

                        // Validate numeric fields
                        if (!is_numeric($_POST['price']) || $_POST['price'] <= 0) {
                            throw new Exception("Price must be a positive number.");
                        }
                        if (!is_numeric($_POST['quantity']) || $_POST['quantity'] < 0) {
                            throw new Exception("Quantity must be a non-negative number.");
                        }
                        if (!is_numeric($_POST['low_stock_threshold']) || $_POST['low_stock_threshold'] < 0) {
                            throw new Exception("Low stock threshold must be a non-negative number.");
                        }

                        // Start transaction
                        $db->beginTransaction();

                        // Check if product exists
                        $check_query = "SELECT id, image FROM products WHERE id = :id";
                        $check_stmt = $db->prepare($check_query);
                        $check_stmt->bindParam(':id', $_POST['id']);
                        $check_stmt->execute();

                        if ($check_stmt->rowCount() === 0) {
                            throw new Exception("Product not found");
                        }

                        $current_product = $check_stmt->fetch(PDO::FETCH_ASSOC);
                        $current_image = $current_product['image'];

                        // Handle image upload if new image is provided
                        $image_path = $current_image;
                        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                            // Validate file type
                            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                            if (!in_array($_FILES['image']['type'], $allowed_types)) {
                                throw new Exception("Invalid file type. Only JPG, PNG and GIF images are allowed.");
                            }

                            // Validate file size (max 5MB)
                            if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                                throw new Exception("File size too large. Maximum size is 5MB.");
                            }

                            $upload_dir = 'uploads/products/';
                            $absolute_upload_dir = __DIR__ . '/' . $upload_dir;
                            
                            // Create directory if it doesn't exist
                            if (!file_exists($absolute_upload_dir)) {
                                if (!mkdir($absolute_upload_dir, 0755, true)) {
                                    throw new Exception("Failed to create upload directory. Please check permissions.");
                                }
                            }

                            // Check if directory is writable
                            if (!is_writable($absolute_upload_dir)) {
                                throw new Exception("Upload directory is not writable. Please check permissions.");
                            }

                            // Generate unique filename
                            $filename = uniqid() . '_' . basename($_FILES['image']['name']);
                            $target_path = $absolute_upload_dir . $filename;
                            
                            // Move uploaded file
                            if (!move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                                throw new Exception("Failed to upload image. Please try again.");
                            }

                            // Delete old image if exists
                            if (!empty($current_image)) {
                                $old_image_path = __DIR__ . '/' . $current_image;
                                if (file_exists($old_image_path)) {
                                    unlink($old_image_path);
                                }
                            }

                            $image_path = $upload_dir . $filename;
                        } elseif (isset($_POST['current_image'])) {
                            // Keep existing image if no new image is uploaded
                            $image_path = $_POST['current_image'];
                        }

                        // Update product
                        $query = "UPDATE products 
                                 SET name = :name, 
                                     description = :description, 
                                     price = :price, 
                                     quantity = :quantity, 
                                     category_id = :category_id, 
                                     low_stock_threshold = :low_stock_threshold,
                                     image = :image
                                 WHERE id = :id";
                        $stmt = $db->prepare($query);
                        
                        // Bind parameters with proper type casting
                        $stmt->bindParam(':id', $_POST['id'], PDO::PARAM_INT);
                        $stmt->bindParam(':name', $_POST['name'], PDO::PARAM_STR);
                        $stmt->bindParam(':description', $_POST['description'], PDO::PARAM_STR);
                        $stmt->bindParam(':price', $_POST['price'], PDO::PARAM_STR);
                        $stmt->bindParam(':quantity', $_POST['quantity'], PDO::PARAM_INT);
                        $stmt->bindParam(':category_id', $_POST['category_id'], PDO::PARAM_INT);
                        $stmt->bindParam(':low_stock_threshold', $_POST['low_stock_threshold'], PDO::PARAM_INT);
                        $stmt->bindParam(':image', $image_path, PDO::PARAM_STR);

                        if (!$stmt->execute()) {
                            throw new Exception("Failed to update product in database");
                        }

                        // Commit transaction
                        $db->commit();

                        echo json_encode([
                            'status' => 'success',
                            'message' => 'Product updated successfully'
                        ]);
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        echo json_encode([
                            'status' => 'error',
                            'message' => $e->getMessage()
                        ]);
                    }
                    exit();
                    break;

                case 'delete_product':
                    // Start transaction
                    $db->beginTransaction();
                    
                    try {
                        // Check if product exists
                        $check_query = "SELECT id, name FROM products WHERE id = :id";
                        $check_stmt = $db->prepare($check_query);
                        $check_stmt->bindParam(':id', $_POST['id']);
                        $check_stmt->execute();
                        
                        if ($check_stmt->rowCount() === 0) {
                            throw new Exception("Product not found");
                        }
                        
                        $product = $check_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Check if product has associated sale items
                        $check_sales_query = "SELECT COUNT(*) as count, GROUP_CONCAT(s.id) as sale_ids FROM sale_items si 
                                            JOIN sales s ON si.sale_id = s.id 
                                            WHERE si.product_id = :id";
                        $check_sales_stmt = $db->prepare($check_sales_query);
                        $check_sales_stmt->bindParam(':id', $_POST['id']);
                        $check_sales_stmt->execute();
                        $sales_data = $check_sales_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($sales_data['count'] > 0) {
                            if (isset($_POST['force_delete']) && $_POST['force_delete'] === 'true') {
                                // Force delete: First delete sale items, then the product
                                $delete_sale_items = "DELETE FROM sale_items WHERE product_id = :id";
                                $stmt = $db->prepare($delete_sale_items);
                                $stmt->bindParam(':id', $_POST['id']);
                                $stmt->execute();
                                
                                // Delete the product
                                $query = "DELETE FROM products WHERE id = :id";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':id', $_POST['id']);
                                
                                if (!$stmt->execute()) {
                                    throw new Exception("Failed to delete product from database");
                                }
                                
                                // Commit transaction
                                $db->commit();
                                
                                echo json_encode([
                                    'status' => 'success',
                                    'message' => 'Product "' . htmlspecialchars($product['name']) . '" and its associated sales records have been deleted'
                                ]);
                            } else {
                                // Return detailed error with sale information
                                echo json_encode([
                                    'status' => 'error',
                                    'message' => 'Cannot delete product with associated sales records',
                                    'details' => [
                                        'product_name' => $product['name'],
                                        'sale_count' => $sales_data['count'],
                                        'sale_ids' => explode(',', $sales_data['sale_ids'])
                                    ]
                                ]);
                                exit();
                            }
                        } else {
                            // No associated sales, proceed with normal deletion
                            // Delete product image if exists
                            $image_query = "SELECT image FROM products WHERE id = :id";
                            $image_stmt = $db->prepare($image_query);
                            $image_stmt->bindParam(':id', $_POST['id']);
                            $image_stmt->execute();
                            $image = $image_stmt->fetch(PDO::FETCH_ASSOC)['image'];
                            
                            if (!empty($image)) {
                                $image_path = __DIR__ . '/' . $image;
                                if (file_exists($image_path)) {
                                    unlink($image_path);
                                }
                            }
                            
                            // Delete the product
                    $query = "DELETE FROM products WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $_POST['id']);
                    
                    if (!$stmt->execute()) {
                                throw new Exception("Failed to delete product from database");
                            }
                            
                            // Commit transaction
                            $db->commit();
                            
                            echo json_encode([
                                'status' => 'success',
                                'message' => 'Product "' . htmlspecialchars($product['name']) . '" deleted successfully'
                            ]);
            }
        } catch (Exception $e) {
                        // Rollback transaction on error
                        $db->rollBack();
                        echo json_encode([
                            'status' => 'error',
                            'message' => $e->getMessage()
                        ]);
                    }
                    exit();
                    break;

                case 'get_product':
                    $query = "SELECT * FROM products WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $_POST['id']);
                    $stmt->execute();
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo json_encode($product);
        exit();
                    break;

                case 'upload_csv':
                    try {
                        // Validate file
                        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                            throw new Exception("Please select a valid CSV file to upload.");
                        }

                        // Check file type
                        $file_type = $_FILES['csv_file']['type'];
                        $allowed_types = ['text/csv', 'application/csv', 'application/vnd.ms-excel'];
                        if (!in_array($file_type, $allowed_types)) {
                            throw new Exception("Invalid file type. Please upload a CSV file.");
                        }

                        // Start transaction
                        $db->beginTransaction();

                        // Read CSV file
                        $csv_file = $_FILES['csv_file']['tmp_name'];
                        $handle = fopen($csv_file, "r");
                        if ($handle === false) {
                            throw new Exception("Failed to open CSV file.");
                        }

                        // Read header row
                        $header = fgetcsv($handle);
                        if ($header === false) {
                            throw new Exception("CSV file is empty.");
                        }

                        // Validate header
                        $required_columns = ['name', 'description', 'price', 'quantity', 'category_id', 'low_stock_threshold'];
                        $header_map = array_flip($header);
                        foreach ($required_columns as $column) {
                            if (!isset($header_map[$column])) {
                                throw new Exception("Missing required column: " . $column);
                            }
                        }

                        // Process rows
                        $row_number = 1;
                        $success_count = 0;
                        $error_rows = [];
                        $query = "INSERT INTO products (name, description, price, quantity, category_id, low_stock_threshold) 
                                 VALUES (:name, :description, :price, :quantity, :category_id, :low_stock_threshold)";
                        $stmt = $db->prepare($query);

                        while (($data = fgetcsv($handle)) !== false) {
                            $row_number++;
                            try {
                                // Validate data
                                if (count($data) < count($required_columns)) {
                                    throw new Exception("Insufficient columns");
                                }

                                // Map data to columns
                                $product = [
                                    'name' => trim($data[$header_map['name']]),
                                    'description' => trim($data[$header_map['description']]),
                                    'price' => trim($data[$header_map['price']]),
                                    'quantity' => trim($data[$header_map['quantity']]),
                                    'category_id' => trim($data[$header_map['category_id']]),
                                    'low_stock_threshold' => trim($data[$header_map['low_stock_threshold']])
                                ];

                                // Validate required fields
                                if (empty($product['name'])) {
                                    throw new Exception("Product name is required");
                                }

                                // Validate numeric fields
                                if (!is_numeric($product['price']) || $product['price'] <= 0) {
                                    throw new Exception("Price must be a positive number");
                                }
                                if (!is_numeric($product['quantity']) || $product['quantity'] < 0) {
                                    throw new Exception("Quantity must be a non-negative number");
                                }
                                if (!is_numeric($product['low_stock_threshold']) || $product['low_stock_threshold'] < 0) {
                                    throw new Exception("Low stock threshold must be a non-negative number");
                                }

                                // Check if category exists
                                $check_category = "SELECT id FROM categories WHERE id = :category_id";
                                $check_stmt = $db->prepare($check_category);
                                $check_stmt->bindParam(':category_id', $product['category_id']);
                                $check_stmt->execute();
                                if ($check_stmt->rowCount() === 0) {
                                    throw new Exception("Invalid category ID");
                                }

                                // Insert product
                                $stmt->bindParam(':name', $product['name'], PDO::PARAM_STR);
                                $stmt->bindParam(':description', $product['description'], PDO::PARAM_STR);
                                $stmt->bindParam(':price', $product['price'], PDO::PARAM_STR);
                                $stmt->bindParam(':quantity', $product['quantity'], PDO::PARAM_INT);
                                $stmt->bindParam(':category_id', $product['category_id'], PDO::PARAM_INT);
                                $stmt->bindParam(':low_stock_threshold', $product['low_stock_threshold'], PDO::PARAM_INT);

                                if ($stmt->execute()) {
                                    $success_count++;
                                } else {
                                    throw new Exception("Failed to insert product");
                                }
                            } catch (Exception $e) {
                                $error_rows[] = [
                                    'row' => $row_number,
                                    'error' => $e->getMessage()
                                ];
                            }
                        }
                        fclose($handle);

                        // Commit transaction if no errors
                        if (empty($error_rows)) {
                            $db->commit();
                            echo json_encode([
                                'status' => 'success',
                                'message' => "Successfully imported $success_count products."
                            ]);
                        } else {
                            // Rollback transaction if there were errors
                            $db->rollBack();
                            echo json_encode([
                                'status' => 'error',
                                'message' => "Import completed with errors.",
                                'details' => [
                                    'success_count' => $success_count,
                                    'error_rows' => $error_rows
                                ]
                            ]);
                        }
                    } catch (Exception $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        echo json_encode([
                            'status' => 'error',
                            'message' => $e->getMessage()
                        ]);
                    }
                    exit();
                    break;
            }
            header("Location: products.php");
            exit();
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit();
        }
    }
}

// Fetch all products with their categories
$query = "SELECT p.*, c.name as category_name 
                  FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id 
                  ORDER BY p.name";
$stmt = $db->prepare($query);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all categories
$query = "SELECT * FROM categories ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate product statistics
$total_products = count($products);
$in_stock = 0;
$low_stock = 0;
$out_of_stock = 0;

foreach ($products as $product) {
    if ($product['quantity'] == 0) {
        $out_of_stock++;
    } elseif ($product['quantity'] <= $product['low_stock_threshold']) {
        $low_stock++;
    } else {
        $in_stock++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - POS Inventory System</title>
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
            <li class="sidebar-item active">
                <a href="products.php" class="sidebar-link active">
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
    <div class="main-content" id="mainContent">
        <div class="container-fluid">
            <!-- Notification Container -->
            <div id="notificationContainer" style="position: fixed; top: 20px; right: 20px; z-index: 1050;"></div>

            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2>Product Management</h2>
                        
                    </div>
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

            <!-- Search and Add Product -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" class="form-control" id="searchInput" placeholder="Search products...">
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#uploadCsvModal">
                        <i class="fas fa-file-import me-2"></i> Import CSV
                    </button>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                        <i class="fas fa-plus me-2"></i> Add New Product
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
                                                <div class="product-image-container" style="width: 200px; height: 200px; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                                                    <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                         style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                                </div>
                                            <?php else: ?>
                                                <div class="product-image-placeholder" style="width: 200px; height: 200px; background-color: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-image text-muted" style="font-size: 2rem;"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-medium"><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                        <td class="fw-medium">Rp <?php echo number_format($product['price'], 2); ?></td>
                                        <td class="fw-medium"><?php echo $product['quantity']; ?></td>
                                        <td>
                                            <?php if ($product['quantity'] == 0): ?>
                                                <span class="stock-status stock-out">Out of Stock</span>
                                            <?php elseif ($product['quantity'] <= $product['low_stock_threshold']): ?>
                                                <span class="stock-status stock-low">Low Stock</span>
                                            <?php else: ?>
                                                <span class="stock-status stock-in">In Stock</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-primary edit-product" 
                                                    data-id="<?php echo $product['id']; ?>"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editProductModal">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-product"
                                                    data-id="<?php echo $product['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Add Product Modal -->
            <div class="modal fade" id="addProductModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Add New Product</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="addProductForm" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="add_product">
                                
                                <div class="mb-3">
                                    <label class="form-label">Product Name</label>
                                    <input type="text" class="form-control" name="name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" rows="3"></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Price</label>
                                        <input type="number" class="form-control" name="price" step="0.01" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Initial Quantity</label>
                                        <input type="number" class="form-control" name="quantity" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Category</label>
                                        <select class="form-select" name="category_id" required>
                                            <option value="">Select Category</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['id']; ?>">
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Low Stock Threshold</label>
                                        <input type="number" class="form-control" name="low_stock_threshold" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Product Image</label>
                                    <input type="file" class="form-control" name="image" accept="image/*">
                                    <div class="image-preview mt-2" style="display: none;">
                                        <img src="" alt="Preview" class="img-fluid">
                                    </div>
                                </div>
                                
                                <div class="text-end">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Add Product</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Product Modal -->
            <div class="modal fade" id="editProductModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Product</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="editProductForm" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="update_product">
                                <input type="hidden" name="id" id="edit_product_id">
                                
                                <div class="mb-3">
                                    <label class="form-label">Product Name</label>
                                    <input type="text" class="form-control" name="name" id="edit_name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Price</label>
                                        <input type="number" class="form-control" name="price" id="edit_price" step="0.01" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Quantity</label>
                                        <input type="number" class="form-control" name="quantity" id="edit_quantity" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Category</label>
                                        <select class="form-select" name="category_id" id="edit_category_id" required>
                                            <option value="">Select Category</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['id']; ?>">
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Low Stock Threshold</label>
                                        <input type="number" class="form-control" name="low_stock_threshold" id="edit_low_stock_threshold" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Product Image</label>
                                    <input type="file" class="form-control" name="image" id="edit_image" accept="image/*">
                                    <div class="image-preview mt-2">
                                        <img src="" alt="Preview" class="img-fluid" id="edit_image_preview">
                                    </div>
                                </div>
                                
                                <div class="text-end">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary" id="edit_submit">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upload CSV Modal -->
            <div class="modal fade" id="uploadCsvModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Import Products from CSV</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="uploadCsvForm" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label class="form-label">CSV File</label>
                                    <input type="file" class="form-control" name="csv_file" accept=".csv" required>
                                    <div class="form-text">
                                        Please upload a CSV file with the following columns:<br>
                                        name, description, price, quantity, category_id, low_stock_threshold
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <a href="templates/products_template.csv" download class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-download me-2"></i>Download Template
                                    </a>
                                    <div class="form-text mt-2">
                                        Note: Make sure to use the correct category_id from your categories list.
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
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#productsTable').DataTable({
                "order": [[1, "asc"]],
                "pageLength": 10,
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]]
            });

            // Custom search box
            $('#searchInput').on('keyup', function() {
                $('#productsTable').DataTable().search(this.value).draw();
            });

            // Image preview functionality
            $('input[type="file"]').change(function(e) {
                const file = e.target.files[0];
                const preview = $(this).siblings('.image-preview');
                const previewImg = preview.find('img');
                
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImg.attr('src', e.target.result);
                        preview.show();
                    }
                    reader.readAsDataURL(file);
                } else {
                    previewImg.attr('src', '');
                    preview.hide();
                }
            });

            // Handle form submissions
            $('#addProductForm').submit(function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                $.ajax({
                    url: 'products.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        try {
                            const result = JSON.parse(response);
                            if (result.status === 'success') {
                                location.reload();
                            } else {
                                alert(result.message || 'An error occurred while adding the product.');
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            alert('An error occurred while processing the response.');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                        console.error('Status:', status);
                        console.error('Response:', xhr.responseText);
                        alert('An error occurred while processing your request. Please check the console for details.');
                    }
                });
            });

            // Handle edit product button click
            $('.edit-product').click(function() {
                const productId = $(this).data('id');
                
                // Fetch product details
                $.ajax({
                    url: 'products.php',
                    type: 'POST',
                    data: {
                        action: 'get_product',
                        id: productId
                    },
                    success: function(response) {
                        try {
                            const product = JSON.parse(response);
                            
                            // Populate form fields
                            $('#edit_product_id').val(product.id);
                            $('#edit_name').val(product.name);
                            $('#edit_description').val(product.description);
                            $('#edit_price').val(product.price);
                            $('#edit_quantity').val(product.quantity);
                            $('#edit_category_id').val(product.category_id);
                            $('#edit_low_stock_threshold').val(product.low_stock_threshold);
                            
                            // Handle image preview
                            if (product.image) {
                                $('#edit_image_preview').attr('src', product.image);
                                $('.image-preview').show();
                            } else {
                                $('#edit_image_preview').attr('src', '');
                                $('.image-preview').hide();
                            }
                            
                            // Show modal
                            $('#editProductModal').modal('show');
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            alert('An error occurred while loading product details.');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                        alert('An error occurred while fetching product details.');
                    }
                });
            });

            // Handle edit form submission
            $('#editProductForm').submit(function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                // Add the current image path if no new image is selected
                if (!formData.get('image').size) {
                    const currentImage = $('#edit_image_preview').attr('src');
                    if (currentImage) {
                        formData.set('current_image', currentImage);
                    }
                }
                
                // Disable submit button and show loading state
                const submitButton = $(this).find('button[type="submit"]');
                const originalButtonText = submitButton.html();
                submitButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
                
                $.ajax({
                    url: 'products.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        try {
                            // Check if response is already an object
                            const result = typeof response === 'object' ? response : JSON.parse(response);
                            
                            if (result.status === 'success') {
                                // Show success toast
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
                                
                                // Remove toast and reload after it's hidden
                                toast.on('hidden.bs.toast', function() {
                                    $(this).remove();
                                    location.reload();
                                });
                            } else {
                                // Show error toast with details
                                let errorMessage = result.message;
                                if (result.details) {
                                    errorMessage += `<br><br>Successfully imported: ${result.details.success_count} products`;
                                    if (result.details.error_rows && result.details.error_rows.length > 0) {
                                        errorMessage += '<br><br>Errors occurred in the following rows:';
                                        result.details.error_rows.forEach(row => {
                                            errorMessage += `<br>Row ${row.row}: ${row.error}`;
                                        });
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
                            
                            // Show error toast
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
                        
                        // Show error toast
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
                        submitButton.prop('disabled', false).html(originalButtonText);
                    }
                });
            });

            // Handle delete product
            $('.delete-product').click(function() {
                const productId = $(this).data('id');
                const productName = $(this).closest('tr').find('td:nth-child(2)').text().trim();
                
                if (confirm(`Are you sure you want to delete the product "${productName}"?`)) {
                    const deleteButton = $(this);
                    deleteButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
                    
                    $.ajax({
                        url: 'products.php',
                        type: 'POST',
                        data: {
                            action: 'delete_product',
                            id: productId
                        },
                        success: function(response) {
                            try {
                                const result = JSON.parse(response);
                                if (result.status === 'success') {
                                    // Show success notification
                                    showNotification(`Product "${productName}" has been deleted successfully!`);
                                    
                                    // Refresh the page after a short delay
                                    setTimeout(() => {
                                        location.reload();
                                    }, 1000);
                                } else if (result.status === 'error' && result.details) {
                                    // Show modal with sales information
                                    const modal = $(
                                        `<div class="modal fade" id="salesModal" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Associated Sales Found</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Product "${productName}" has ${result.details.sale_count} associated sales records.</p>
                                                        <p>You can either:</p>
                                                        <ol>
                                                            <li>Delete the sales first from the <a href="sales.php">Sales</a> page</li>
                                                            <li>Force delete the product (this will also delete all associated sales records)</li>
                                                        </ol>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="button" class="btn btn-danger" id="forceDeleteBtn">Force Delete</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>`
                                    );
                                    
                                    $('body').append(modal);
                                    const bsModal = new bootstrap.Modal(modal);
                                    bsModal.show();
                                    
                                    // Handle force delete
                                    $('#forceDeleteBtn').click(function() {
                                        if (confirm(`WARNING: This will permanently delete "${productName}" and all its sales records. This action cannot be undone. Are you sure?`)) {
                                            $.ajax({
                                                url: 'products.php',
                                                type: 'POST',
                                                data: {
                                                    action: 'delete_product',
                                                    id: productId,
                                                    force_delete: 'true'
                                                },
                                                success: function(response) {
                                                    try {
                                                        const result = JSON.parse(response);
                                                        if (result.status === 'success') {
                                                            bsModal.hide();
                                                            modal.remove();
                                                            showNotification(`Product "${productName}" and its associated sales records have been deleted successfully!`);
                                                            setTimeout(() => {
                                                                location.reload();
                                                            }, 1000);
                                                        } else {
                                                            showNotification(result.message, 'danger');
                                                        }
                                                    } catch (e) {
                                                        console.error('Error parsing response:', e);
                                                        showNotification('Error deleting product', 'danger');
                                                    }
                                                },
                                                error: function(xhr, status, error) {
                                                    console.error('Delete error:', error);
                                                    showNotification('Error deleting product', 'danger');
                                                }
                                            });
                                        }
                                    });
                                    
                                    // Clean up modal on close
                                    modal.on('hidden.bs.modal', function() {
                                        modal.remove();
                                    });
                                } else {
                                    showNotification(result.message, 'danger');
                                }
                            } catch (e) {
                                console.error('Error parsing response:', e);
                                showNotification('Error deleting product', 'danger');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Delete error:', error);
                            showNotification('Error deleting product', 'danger');
                        },
                        complete: function() {
                            deleteButton.prop('disabled', false).html('<i class="fas fa-trash"></i>');
                        }
                    });
                }
            });

            // Handle CSV Upload
            $('#uploadCsvForm').on('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                formData.append('action', 'upload_csv');
                
                // Show loading state
                const submitButton = $(this).find('button[type="submit"]');
                const originalText = submitButton.html();
                submitButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Uploading...');
                
                $.ajax({
                    url: 'products.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        try {
                            const result = typeof response === 'object' ? response : JSON.parse(response);
                            
                            if (result.status === 'success') {
                                // Show success toast
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
                                
                                // Remove toast and reload after it's hidden
                                toast.on('hidden.bs.toast', function() {
                                    $(this).remove();
                                    location.reload();
                                });
                                
                                // Close modal and reset form
                                $('#uploadCsvModal').modal('hide');
                                $('#uploadCsvForm')[0].reset();
                            } else {
                                // Show error toast with details
                                let errorMessage = result.message;
                                if (result.details) {
                                    errorMessage += `<br><br>Successfully imported: ${result.details.success_count} products`;
                                    if (result.details.error_rows && result.details.error_rows.length > 0) {
                                        errorMessage += '<br><br>Errors occurred in the following rows:';
                                        result.details.error_rows.forEach(row => {
                                            errorMessage += `<br>Row ${row.row}: ${row.error}`;
                                        });
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
                            
                            // Show error toast
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
                        
                        // Show error toast
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
        });

        // Function to show notification
        function showNotification(message, type = 'success') {
            const notificationContainer = document.getElementById('notificationContainer');
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show`;
            notification.innerHTML = `
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} me-2"></i>
                ${message}
            `;
            notificationContainer.appendChild(notification);

            // Auto remove after 3 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 150); // Wait for fade out animation
            }, 3000);
        }

        // Function to handle product update
        function updateProduct(productId) {
            const form = document.getElementById('editProductForm');
            const formData = new FormData(form);
            formData.append('id', productId);

            // Disable submit button and show loading state
            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';

            fetch('api/update_product.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success notification
                    showNotification('Product updated successfully!');
                    
                    // Close the modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editProductModal'));
                    modal.hide();
                    
                    // Refresh the page after a short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message || 'Error updating product', 'danger');
                    // Re-enable submit button
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred while updating the product', 'danger');
                // Re-enable submit button
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            });
        }

        // Function to edit product
        function editProduct(id) {
            fetch(`api/get_product.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const product = data.product;
                        document.getElementById('edit_product_id').value = product.id;
                        document.getElementById('edit_name').value = product.name;
                        document.getElementById('edit_description').value = product.description;
                        document.getElementById('edit_price').value = product.price;
                        document.getElementById('edit_quantity').value = product.quantity;
                        document.getElementById('edit_category_id').value = product.category_id;
                        document.getElementById('edit_low_stock_threshold').value = product.low_stock_threshold;
                        
                        // Show the modal
                        const modal = new bootstrap.Modal(document.getElementById('editProductModal'));
                        modal.show();
                    } else {
                        showNotification('Error loading product data', 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('An error occurred while loading product data', 'danger');
                });
        }
    </script>
</body>
</html> 