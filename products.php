<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Fetch categories
try {
    $categories_query = "SELECT * FROM categories ORDER BY name";
    $categories_stmt = $db->prepare($categories_query);
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $categories = [];
}

// Handle product actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add_product':
                    error_log("Processing add_product action");
                    // Debug input data
                    error_log("Add Product Data: " . print_r($_POST, true));
                    
                    // Validate required fields
                    $required_fields = ['name', 'price', 'quantity', 'category_id', 'low_stock_threshold'];
                    foreach ($required_fields as $field) {
                        if (empty($_POST[$field])) {
                            error_log("Validation Error: {$field} is required");
                            throw new Exception("Please fill in all required fields.");
                        }
                    }

                    // Validate numeric fields
                    if (!is_numeric($_POST['price']) || $_POST['price'] <= 0) {
                        error_log("Validation Error: Invalid price");
                        throw new Exception("Price must be a positive number.");
                    }
                    if (!is_numeric($_POST['quantity']) || $_POST['quantity'] < 0) {
                        error_log("Validation Error: Invalid quantity");
                        throw new Exception("Quantity must be a non-negative number.");
                    }
                    if (!is_numeric($_POST['low_stock_threshold']) || $_POST['low_stock_threshold'] < 0) {
                        error_log("Validation Error: Invalid low stock threshold");
                        throw new Exception("Low stock threshold must be a non-negative number.");
                    }

                    try {
                        $db->beginTransaction();
                        // Enable error logging
                        error_log("Starting product addition process");
                        
                        // Validate required fields
                        $required_fields = ['name', 'price', 'quantity', 'category_id', 'low_stock_threshold'];
                        foreach ($required_fields as $field) {
                            if (empty($_POST[$field])) {
                                throw new Exception("Please fill in all required fields. Missing: " . $field);
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

                        // Log the received data
                        error_log("Received product data: " . json_encode($_POST));
                        
                        // Start transaction
                        $db->beginTransaction();
                        error_log("Transaction started");

                        // Handle image upload
                        $image_path = null;
                        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                            error_log("Processing image upload");
                            
                            // Validate file type
                            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                            if (!in_array($_FILES['image']['type'], $allowed_types)) {
                                throw new Exception("Invalid file type. Only JPG, PNG and GIF images are allowed. Received: " . $_FILES['image']['type']);
                            }

                            // Validate file size (max 5MB)
                            if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                                throw new Exception("File size too large. Maximum size is 5MB.");
                            }

                            // Set up upload directory
                            $upload_dir = 'uploads/products/';
                            if (!file_exists($upload_dir)) {
                                if (!mkdir($upload_dir, 0777, true)) {
                                    error_log("Failed to create directory: " . $upload_dir);
                                    throw new Exception("Failed to create upload directory. Please contact administrator.");
                                }
                            }

                            // Generate filename and move file
                            $filename = uniqid() . '_' . basename($_FILES['image']['name']);
                            $target_path = $upload_dir . $filename;
                            
                            if (!move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                                error_log("Failed to move uploaded file. Error details: " . error_get_last()['message']);
                                throw new Exception("Failed to save image. Please try again.");
                            }
                            
                            $image_path = $target_path;
                            error_log("Image uploaded successfully to: " . $image_path);
                        } else {
                            error_log("No image uploaded or upload error occurred: " . 
                                    (isset($_FILES['image']) ? $_FILES['image']['error'] : 'No image file'));
                        }

                        // Insert product
                        $query = "INSERT INTO products (name, description, price, quantity, category_id, low_stock_threshold, image) 
                                 VALUES (:name, :description, :price, :quantity, :category_id, :low_stock_threshold, :image)";
                        $stmt = $db->prepare($query);
                        
                        // Bind parameters
                        $params = [
                            ':name' => $_POST['name'],
                            ':description' => $_POST['description'] ?? '',
                            ':price' => $_POST['price'],
                            ':quantity' => $_POST['quantity'],
                            ':category_id' => $_POST['category_id'],
                            ':low_stock_threshold' => $_POST['low_stock_threshold'],
                            ':image' => $image_path
                        ];
                        
                        foreach ($params as $key => $value) {
                            $stmt->bindValue($key, $value);
                        }

                        if (!$stmt->execute()) {
                            $error = $stmt->errorInfo();
                            error_log("Database error: " . json_encode($error));
                            throw new Exception("Failed to add product to database. Error: " . $error[2]);
                        }

                        // Commit transaction
                        $db->commit();
                        error_log("Product added successfully");
                        
                        echo json_encode([
                            'status' => 'success',
                            'message' => 'Product added successfully'
                        ]);
                        exit();
                    } catch (Exception $e) {
                        error_log("Error in add_product: " . $e->getMessage());
                        
                        // Rollback transaction
                        if ($db->inTransaction()) {
                            $db->rollBack();
                            error_log("Transaction rolled back");
                        }
                        
                        // Clean up uploaded file if it exists
                        if (isset($target_path) && file_exists($target_path)) {
                            unlink($target_path);
                            error_log("Uploaded file removed: " . $target_path);
                        }
                        
                        echo json_encode([
                            'status' => 'error',
                            'message' => $e->getMessage()
                        ]);
                        exit();
                    }
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

                        // Check if product exists and get current image
                        $check_query = "SELECT id, image FROM products WHERE id = :id";
                        $check_stmt = $db->prepare($check_query);
                        $check_stmt->bindParam(':id', $_POST['id']);
                        $check_stmt->execute();

                        if ($check_stmt->rowCount() === 0) {
                            throw new Exception("Product not found");
                        }

                        $current_product = $check_stmt->fetch(PDO::FETCH_ASSOC);
                        $image_path = $current_product['image'];

                        // Handle new image upload if provided
                        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
                            $upload_dir = 'uploads/products/';
                            if (!file_exists($upload_dir)) {
                                mkdir($upload_dir, 0777, true);
                            }

                            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

                            if (!in_array($file_extension, $allowed_types)) {
                                throw new Exception("Invalid file type. Allowed types: " . implode(', ', $allowed_types));
                            }

                            // Generate unique filename
                            $new_filename = uniqid() . '.' . $file_extension;
                            $target_path = $upload_dir . $new_filename;

                            // Move uploaded file
                            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                                // Delete old image if exists
                                if (!empty($current_product['image'])) {
                                    $old_image_path = $upload_dir . $current_product['image'];
                                    if (file_exists($old_image_path)) {
                                        unlink($old_image_path);
                                    }
                                }
                                $image_path = $new_filename;
                            } else {
                                throw new Exception("Failed to upload image");
                            }
                        }

                        // Update product
                        $query = "UPDATE products 
                                 SET name = :name, 
                                     description = :description, 
                                     price = :price, 
                                     quantity = :quantity, 
                                     category_id = :category_id, 
                                     low_stock_threshold = :low_stock_threshold" .
                                     ($image_path !== $current_product['image'] ? ", image = :image" : "") .
                                 " WHERE id = :id";
                        
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':id', $_POST['id']);
                        $stmt->bindParam(':name', $_POST['name']);
                        $stmt->bindParam(':description', $_POST['description']);
                        $stmt->bindParam(':price', $_POST['price']);
                        $stmt->bindParam(':quantity', $_POST['quantity']);
                        $stmt->bindParam(':category_id', $_POST['category_id']);
                        $stmt->bindParam(':low_stock_threshold', $_POST['low_stock_threshold']);
                        
                        if ($image_path !== $current_product['image']) {
                            $stmt->bindParam(':image', $image_path);
                        }

                        if (!$stmt->execute()) {
                            throw new Exception("Failed to update product");
                        }

                        // Commit transaction
                        $db->commit();

                        echo json_encode([
                            'status' => 'success',
                            'message' => 'Product updated successfully',
                            'data' => [
                                'id' => $_POST['id'],
                                'image_url' => $image_path ? 'uploads/products/' . $image_path : null
                            ]
                        ]);
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        error_log("Error in update_product: " . $e->getMessage());
                        http_response_code(400);
                        echo json_encode([
                            'status' => 'error',
                            'message' => $e->getMessage()
                        ]);
                    }
                    exit();
                    break;

                case 'delete_product':
                    try {
                        // Set proper content type
                        header('Content-Type: application/json');

                        // Validate product ID
                        if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
                            throw new Exception("Invalid product ID");
                        }

                        // Start transaction
                        $db->beginTransaction();
                        
                        try {
                            // Check if product exists and get its details
                            $check_query = "SELECT id, name, image FROM products WHERE id = :id";
                            $check_stmt = $db->prepare($check_query);
                            $check_stmt->bindParam(':id', $_POST['id'], PDO::PARAM_INT);
                            $check_stmt->execute();
                            
                            if ($check_stmt->rowCount() === 0) {
                                throw new Exception("Product not found");
                            }
                            
                            $product = $check_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            // Check if product has associated sale items
                            $check_sales_query = "SELECT COUNT(*) as count, GROUP_CONCAT(s.id) as sale_ids 
                                                FROM sale_items si 
                                                JOIN sales s ON si.sale_id = s.id 
                                                WHERE si.product_id = :id";
                            $check_sales_stmt = $db->prepare($check_sales_query);
                            $check_sales_stmt->bindParam(':id', $_POST['id'], PDO::PARAM_INT);
                            $check_sales_stmt->execute();
                            $sales_data = $check_sales_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($sales_data['count'] > 0) {
                                if (isset($_POST['force_delete']) && $_POST['force_delete'] === 'true') {
                                    // Force delete: First delete sale items
                                    $delete_sale_items = "DELETE FROM sale_items WHERE product_id = :id";
                                    $stmt = $db->prepare($delete_sale_items);
                                    $stmt->bindParam(':id', $_POST['id'], PDO::PARAM_INT);
                                    
                                    if (!$stmt->execute()) {
                                        throw new Exception("Failed to delete associated sale items");
                                    }
                                } else {
                                    // Return error with sale information
                                    $db->rollBack();
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
                            }
                            
                            // Delete product image if exists
                            if (!empty($product['image'])) {
                                $image_path = __DIR__ . '/' . $product['image'];
                                if (file_exists($image_path)) {
                                    if (!unlink($image_path)) {
                                        error_log("Failed to delete image file: " . $image_path);
                                    }
                                }
                            }
                            
                            // Delete the product
                            $query = "DELETE FROM products WHERE id = :id";
                            $stmt = $db->prepare($query);
                            $stmt->bindParam(':id', $_POST['id'], PDO::PARAM_INT);
                            
                            if (!$stmt->execute()) {
                                throw new Exception("Failed to delete product from database");
                            }
                            
                            // Commit transaction
                            $db->commit();
                            
                            echo json_encode([
                                'status' => 'success',
                                'message' => 'Product "' . htmlspecialchars($product['name']) . '" deleted successfully'
                            ]);
                        } catch (Exception $e) {
                            // Rollback transaction on error
                            if ($db->inTransaction()) {
                                $db->rollBack();
                            }
                            throw $e;
                        }
                    } catch (Exception $e) {
                        error_log("Error in delete_product: " . $e->getMessage());
                        http_response_code(400);
                        echo json_encode([
                            'status' => 'error',
                            'message' => $e->getMessage()
                        ]);
                    }
                    exit();
                    break;

                case 'get_product':
                    try {
                        // Set proper content type
                        header('Content-Type: application/json');

                        // Log request
                        error_log("Get product request - ID: " . ($_POST['id'] ?? 'not set'));

                        // Validate product ID
                        if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
                            error_log("Invalid product ID provided: " . ($_POST['id'] ?? 'not set'));
                            throw new Exception("Invalid product ID");
                        }

                        // Get product with category information
                        $query = "SELECT p.*, c.name as category_name 
                                FROM products p 
                                LEFT JOIN categories c ON p.category_id = c.id 
                                WHERE p.id = :id";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':id', $_POST['id'], PDO::PARAM_INT);
                        
                        if (!$stmt->execute()) {
                            $error = $stmt->errorInfo();
                            error_log("Database error while fetching product: " . json_encode($error));
                            throw new Exception("Database error while fetching product");
                        }
                        
                        $product = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$product) {
                            error_log("Product not found - ID: " . $_POST['id']);
                            throw new Exception("Product not found");
                        }

                        // Format image path if exists
                        if (!empty($product['image'])) {
                            $product['image_url'] = 'uploads/products/' . $product['image'];
                        } else {
                            $product['image_url'] = null;
                        }

                        // Log success
                        error_log("Product fetched successfully - ID: " . $_POST['id']);

                        echo json_encode([
                            'status' => 'success',
                            'data' => $product
                        ]);
                    } catch (Exception $e) {
                        error_log("Error in get_product: " . $e->getMessage());
                        http_response_code(400);
                        echo json_encode([
                            'status' => 'error',
                            'message' => $e->getMessage()
                        ]);
                    }
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
    <title>Products - Shop Inventory System</title>
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="assets/css/sidebar.css" rel="stylesheet">
    
    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="assets/js/sidebar.js"></script>
    <script src="assets/js/products.js"></script>

    <style>
        /* Mobile-first styles */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0 !important;
                padding: 15px !important;
                width: 100% !important;
            }

            .container-fluid {
                padding-right: 15px !important;
                padding-left: 15px !important;
            }

            .card {
                margin-bottom: 15px;
            }

            /* Stats cards */
            .stats-card {
                margin-bottom: 15px;
            }

            /* Table responsiveness */
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            /* Action buttons */
            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.875rem;
            }

            /* Search and filters */
            .search-box {
                margin-bottom: 15px;
            }

            /* Modal adjustments */
            .modal-dialog {
                margin: 0.5rem;
                max-width: calc(100% - 1rem);
            }

            /* DataTables adjustments */
            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter,
            .dataTables_wrapper .dataTables_info,
            .dataTables_wrapper .dataTables_paginate {
                text-align: left;
                float: none;
                margin-bottom: 10px;
            }

            .dataTables_wrapper .dataTables_filter input {
                width: 100%;
                margin-left: 0;
                margin-top: 5px;
            }

            /* Button group responsiveness */
            .dt-buttons {
                width: 100%;
                margin-bottom: 15px;
            }

            .dt-button {
                width: 100%;
                margin-bottom: 5px;
            }
        }

        /* General styles */
        body {
            background-color: #f8f9fa;
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .product-image-container {
            width: 50px;
            height: 50px;
            overflow: hidden;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
        }

        .product-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .stock-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 500;
            white-space: nowrap;
        }

        /* Improved table responsiveness */
        #productsTable {
            width: 100% !important;
        }

        @media (max-width: 768px) {
            #productsTable td {
                white-space: normal;
            }

            .table-responsive {
                border: none;
            }

            .action-buttons {
                display: flex;
                gap: 5px;
                justify-content: flex-end;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle Button -->
    <button class="mobile-menu-toggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay"></div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
        
            <a href="index.php" class="sidebar-brand"><i class="fas fa-cash-register"></i>SHOP System</a>
        </div>
        <div class="sidebar-menu">
            <div class="sidebar-item">
                <a href="index.php" class="sidebar-link">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="sidebar-item">
                <a href="products.php" class="sidebar-link active">
                    <i class="fas fa-box"></i>
                    <span>Products</span>
                </a>
            </div>
            <div class="sidebar-item">
                <a href="sales.php" class="sidebar-link">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Sales</span>
                </a>
            </div>
            <div class="sidebar-item">
                <a href="customers.php" class="sidebar-link">
                    <i class="fas fa-users"></i>
                    <span>Customers</span>
                </a>
            </div>
            <div class="sidebar-item">
                <a href="reports.php" class="sidebar-link">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </div>
            <div class="sidebar-item">
                <a href="settings.php" class="sidebar-link">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </div>
            <div class="sidebar-item mt-auto">
                <a href="logout.php" class="sidebar-link text-danger">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Notification Container -->
        <div id="notificationContainer" style="position: fixed; top: 20px; right: 20px; z-index: 1060;"></div>
        
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
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="productsTable">
                        <thead>
                            <tr>
                                <th style="width: 60px;">Image</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th style="width: 100px;" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <div class="product-image-container">
                                            <?php if (!empty($product['image'])): ?>
                                                <img src="uploads/products/<?php echo htmlspecialchars($product['image']); ?>" 
                                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                     loading="lazy">
                                            <?php else: ?>
                                                <i class="fas fa-box text-muted"></i>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-medium text-wrap"><?php echo htmlspecialchars($product['name']); ?></div>
                                        <small class="text-muted d-block d-md-none"><?php echo htmlspecialchars($product['category_name']); ?></small>
                                    </td>
                                    <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($product['category_name']); ?></td>
                                    <td class="fw-medium">Rp <?php echo number_format($product['price'], 2); ?></td>
                                    <td class="fw-medium"><?php echo $product['quantity']; ?></td>
                                    <td>
                                        <?php if ($product['quantity'] == 0): ?>
                                            <span class="badge bg-danger">Out of Stock</span>
                                        <?php elseif ($product['quantity'] <= $product['low_stock_threshold']): ?>
                                            <span class="badge bg-warning text-dark">Low Stock</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">In Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-primary edit-product" 
                                                    data-id="<?php echo $product['id']; ?>"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editProductModal"
                                                    title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-product"
                                                    data-id="<?php echo $product['id']; ?>"
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
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
                                    <input type="number" class="form-control" name="price" step="0.01" min="0" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Initial Quantity</label>
                                    <input type="number" class="form-control" name="quantity" min="0" required>
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
                                    <input type="number" class="form-control" name="low_stock_threshold" min="0" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Product Image</label>
                                <input type="file" class="form-control" name="image" accept="image/*">
                                <div class="image-preview mt-2" style="display: none;">
                                    <img src="" alt="Preview" class="img-fluid">
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="$('#addProductForm').submit()">Add Product</button>
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
</body>
</html> 