<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$database = new Database();
$db = $database->getConnection();

// Debug database connection
if (!$db) {
    die("Database connection failed");
}

// Handle Excel upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excel_file'])) {
    try {
        $inputFileName = $_FILES['excel_file']['tmp_name'];
        $spreadsheet = IOFactory::load($inputFileName);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();
        
        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        
        // Start transaction
        $db->beginTransaction();
        
        for ($row = 2; $row <= $highestRow; $row++) {
            try {
                $name = $worksheet->getCellByColumnAndRow(1, $row)->getValue();
                $price = $worksheet->getCellByColumnAndRow(2, $row)->getValue();
                $quantity = $worksheet->getCellByColumnAndRow(3, $row)->getValue();
                $category = $worksheet->getCellByColumnAndRow(4, $row)->getValue();
                $description = $worksheet->getCellByColumnAndRow(5, $row)->getValue();
                $low_stock_threshold = $worksheet->getCellByColumnAndRow(6, $row)->getValue();
                
                // Validate required fields
                if (empty($name) || empty($price) || empty($quantity) || empty($category)) {
                    throw new Exception("Missing required fields in row $row");
                }
                
                // Get or create category
                $stmt = $db->prepare("SELECT id FROM categories WHERE name = ?");
                $stmt->execute([$category]);
                $category_id = $stmt->fetchColumn();
                
                if (!$category_id) {
                    $stmt = $db->prepare("INSERT INTO categories (name) VALUES (?)");
                    $stmt->execute([$category]);
                    $category_id = $db->lastInsertId();
                }
                
                // Insert product
                $stmt = $db->prepare("INSERT INTO products (name, description, price, quantity, category_id, low_stock_threshold) 
                                    VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $name,
                    $description,
                    $price,
                    $quantity,
                    $category_id,
                    $low_stock_threshold ?: 5 // Default threshold if not provided
                ]);
                
                $successCount++;
            } catch (Exception $e) {
                $errorCount++;
                $errors[] = "Row $row: " . $e->getMessage();
            }
        }
        
        // Commit transaction
        $db->commit();
        
        // Prepare response
        $response = [
            'status' => 'success',
            'message' => "Successfully imported $successCount products. " . 
                        ($errorCount > 0 ? "$errorCount rows had errors." : ""),
            'errors' => $errors
        ];
        
        echo json_encode($response);
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        echo json_encode([
            'status' => 'error',
            'message' => 'Error processing Excel file: ' . $e->getMessage()
        ]);
        exit();
    }
}

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
                    $query = "UPDATE products 
                             SET name = :name, description = :description, price = :price, 
                                 quantity = :quantity, category_id = :category_id, 
                                 low_stock_threshold = :low_stock_threshold
                             WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $_POST['id']);
                    $stmt->bindParam(':name', $_POST['name']);
                    $stmt->bindParam(':description', $_POST['description']);
                    $stmt->bindParam(':price', $_POST['price']);
                    $stmt->bindParam(':quantity', $_POST['quantity']);
                    $stmt->bindParam(':category_id', $_POST['category_id']);
                    $stmt->bindParam(':low_stock_threshold', $_POST['low_stock_threshold']);
                    $stmt->execute();
                    break;

                case 'delete_product':
                    $query = "DELETE FROM products WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $_POST['id']);
                    $stmt->execute();
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
            }
            header("Location: products.php");
            exit();
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit();
        }
    }
}

// Fetch products with error handling
try {
    $query = "SELECT p.*, c.name as category_name 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              ORDER BY p.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    // Debug: Check if any products were found
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($products)) {
        error_log("No products found in database");
    }
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("Error fetching products: " . $e->getMessage());
}

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
                <a href="products.php" class="sidebar-link">
                    <i class="fas fa-box"></i>
                    <span>Products</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="categories.php" class="sidebar-link">
                    <i class="fas fa-tags"></i>
                    <span>Categories</span>
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
            <div class="notification-container"></div>

            <div class="row mb-4">
                <div class="col-12">
                    <h2>Product Management</h2>
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
                    <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#uploadExcelModal">
                        <i class="fas fa-file-excel me-2"></i> Upload Excel
                    </button>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                        <i class="fas fa-plus me-2"></i> Add New Product
                    </button>
                </div>
            </div>

            <!-- Products Table -->
            <div class="card">
                <div class="card-body">
                    <?php if (empty($products)): ?>
                        <div class="alert alert-info">
                            No products found. Add your first product using the "Add New Product" button.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                                            <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                            <td>₱<?php echo number_format($product['price'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $product['quantity'] <= $product['low_stock_threshold'] ? 'danger' : 'success'; 
                                                ?>">
                                                    <?php echo $product['quantity']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($product['quantity'] <= $product['low_stock_threshold']): ?>
                                                    <span class="badge bg-warning">Low Stock</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">In Stock</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary edit-product" 
                                                        data-id="<?php echo $product['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                        data-description="<?php echo htmlspecialchars($product['description']); ?>"
                                                        data-price="<?php echo $product['price']; ?>"
                                                        data-quantity="<?php echo $product['quantity']; ?>"
                                                        data-category-id="<?php echo $product['category_id']; ?>"
                                                        data-low-stock="<?php echo $product['low_stock_threshold']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger delete-product" 
                                                        data-id="<?php echo $product['id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Debug Information (Remove in production) -->
            <?php if (isset($_SESSION['debug']) && $_SESSION['debug']): ?>
                <div class="mt-3">
                    <pre><?php print_r($products); ?></pre>
                </div>
            <?php endif; ?>

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

            <!-- Upload Excel Modal -->
            <div class="modal fade" id="uploadExcelModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Upload Products from Excel</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="uploadExcelForm" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label class="form-label">Excel File</label>
                                    <input type="file" class="form-control" name="excel_file" accept=".xlsx,.xls" required>
                                    <div class="form-text">
                                        Supported formats: .xlsx, .xls<br>
                                        Required columns: Name, Price, Quantity, Category<br>
                                        Optional columns: Description, Low Stock Threshold
                                    </div>
                                </div>
                                <div class="text-end">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary" id="uploadSubmit">Upload</button>
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
            // Debug: Log when document is ready
            console.log('Document ready');
            
            // Handle search
            $('#searchInput').on('input', function() {
                const searchTerm = $(this).val().toLowerCase();
                console.log('Searching for:', searchTerm);
                
                $('tbody tr').each(function() {
                    const productName = $(this).find('td:first').text().toLowerCase();
                    const categoryName = $(this).find('td:nth-child(2)').text().toLowerCase();
                    
                    if (productName.includes(searchTerm) || categoryName.includes(searchTerm)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });

            // Handle edit product
            $('.edit-product').click(function() {
                console.log('Edit button clicked');
                const productId = $(this).data('id');
                const productData = {
                    name: $(this).data('name'),
                    description: $(this).data('description'),
                    price: $(this).data('price'),
                    quantity: $(this).data('quantity'),
                    category_id: $(this).data('category-id'),
                    low_stock_threshold: $(this).data('low-stock')
                };
                
                console.log('Product data:', productData);
                
                // Populate modal with product data
                $('#edit_product_id').val(productId);
                $('#edit_name').val(productData.name);
                $('#edit_description').val(productData.description);
                $('#edit_price').val(productData.price);
                $('#edit_quantity').val(productData.quantity);
                $('#edit_category_id').val(productData.category_id);
                $('#edit_low_stock_threshold').val(productData.low_stock_threshold);
                
                // Show modal
                $('#editProductModal').modal('show');
            });

            // Handle delete product
            $('.delete-product').click(function() {
                console.log('Delete button clicked');
                const productId = $(this).data('id');
                
                if (confirm('Are you sure you want to delete this product?')) {
                    console.log('Deleting product:', productId);
                    
                    $.ajax({
                        url: 'products.php',
                        type: 'POST',
                        data: {
                            action: 'delete',
                            id: productId
                        },
                        success: function(response) {
                            console.log('Delete response:', response);
                            try {
                                const result = JSON.parse(response);
                                if (result.status === 'success') {
                                    location.reload();
                                } else {
                                    alert(result.message);
                                }
                            } catch (e) {
                                console.error('Error parsing response:', e);
                                alert('Error deleting product');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Delete error:', error);
                            alert('Error deleting product');
                        }
                    });
                }
            });

            // Debug: Log any JavaScript errors
            window.onerror = function(msg, url, line) {
                console.error('Error:', msg, 'at', url, ':', line);
                return false;
            };
        });
    </script>
</body>
</html> 