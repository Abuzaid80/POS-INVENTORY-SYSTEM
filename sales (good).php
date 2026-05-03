<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Helper function for number formatting
function formatNumber($number) {
    return number_format((float)$number ?? 0, 2);
}

// Enable error reporting and logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Database connection with error handling
try {
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();
    
    // Test the connection
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->query("SELECT 1");
    error_log("Database connection successful");

    // Add notes column to sales table if it doesn't exist
    try {
        $alter_query = "ALTER TABLE sales ADD COLUMN notes TEXT DEFAULT NULL";
        $db->exec($alter_query);
        error_log("Successfully added notes column to sales table");
    } catch (PDOException $e) {
        error_log("Notes column might already exist: " . $e->getMessage());
    }
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Database Connection Error: " . $e->getMessage());
} catch (Exception $e) {
    error_log("General Error: " . $e->getMessage());
    die("Error: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    error_log("POST request received: " . print_r($_POST, true));
    try {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_product':
                // Create uploads directory if it doesn't exist
                $upload_dir = 'uploads/products/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                // Handle file upload
                $image_path = null;
                if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                    $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    
                    if (in_array($file_extension, $allowed_types)) {
                        $new_filename = uniqid() . '.' . $file_extension;
                        $target_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                            $image_path = $target_path;
                        }
                    }
                }

                // Insert product
                $query = "INSERT INTO products (name, description, price, quantity, category_id, low_stock_threshold, image_path) 
                         VALUES (:name, :description, :price, :quantity, :category_id, :low_stock_threshold, :image_path)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':name', $_POST['name']);
                $stmt->bindParam(':description', $_POST['description']);
                $stmt->bindParam(':price', $_POST['price']);
                $stmt->bindParam(':quantity', $_POST['quantity']);
                $stmt->bindParam(':category_id', $_POST['category_id']);
                $stmt->bindParam(':low_stock_threshold', $_POST['low_stock_threshold']);
                $stmt->bindParam(':image_path', $image_path);
                $stmt->execute();
                break;

            case 'add_sale':
                    error_log("Processing add_sale action");
                    // Debug input data
                    error_log("Add Sale Data: " . print_r($_POST, true));
                    
                    // Validate required fields
                    if (empty($_POST['customer_id'])) {
                        error_log("Validation Error: Customer ID is required");
                        throw new Exception("Please select a customer");
                    }
                    if (empty($_POST['products']) || !is_array($_POST['products'])) {
                        error_log("Validation Error: No products selected");
                        throw new Exception("Please add at least one product to the sale");
                    }
                    if (empty($_POST['payment_method'])) {
                        error_log("Validation Error: Payment method not selected");
                        throw new Exception("Please select a payment method");
                    }
                    if (empty($_POST['total_amount']) || $_POST['total_amount'] <= 0) {
                        error_log("Validation Error: Invalid total amount");
                        throw new Exception("Invalid total amount");
                    }
                    
                    // Validate product quantities
                    foreach ($_POST['products'] as $index => $product_id) {
                        if (!empty($product_id)) {
                            error_log("Validating product ID: " . $product_id);
                            // Check if product exists and has sufficient quantity
                            $query = "SELECT name, quantity FROM products WHERE id = :product_id";
                            $stmt = $db->prepare($query);
                            $stmt->bindParam(':product_id', $product_id);
                            $stmt->execute();
                            $product = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if (!$product) {
                                error_log("Product not found: " . $product_id);
                                throw new Exception("Product not found");
                            }
                            
                            if ($product['quantity'] < $_POST['quantities'][$index]) {
                                error_log("Insufficient stock for product: " . $product['name'] . 
                                        " (Required: " . $_POST['quantities'][$index] . 
                                        ", Available: " . $product['quantity'] . ")");
                                throw new Exception("Insufficient stock for product: " . $product['name']);
                            }
                        }
                    }
                    
                    // Start transaction
                    $db->beginTransaction();
                    error_log("Transaction started");

                    try {
                        // Insert sale record
                        $query = "INSERT INTO sales (customer_id, total_amount, payment_method, notes) 
                                 VALUES (:customer_id, :total_amount, :payment_method, :notes)";
                $stmt = $db->prepare($query);
                        
                        // Debug SQL query
                        error_log("SQL Query: " . $query);
                        error_log("Parameters: " . print_r([
                            'customer_id' => $_POST['customer_id'],
                            'total_amount' => $_POST['total_amount'],
                            'payment_method' => $_POST['payment_method'],
                            'notes' => $_POST['notes']
                        ], true));
                        
                        // Bind parameters
                $stmt->bindParam(':customer_id', $_POST['customer_id']);
                $stmt->bindParam(':total_amount', $_POST['total_amount']);
                $stmt->bindParam(':payment_method', $_POST['payment_method']);
                        $stmt->bindParam(':notes', $_POST['notes']);
                        
                        // Execute query
                        $result = $stmt->execute();
                        
                        // Debug execution result
                        error_log("Sale Insert Result: " . ($result ? "Success" : "Failed"));
                        
                        if ($result) {
                $sale_id = $db->lastInsertId();
                            error_log("New Sale ID: " . $sale_id);

                            // Insert sale items
                            if (isset($_POST['products']) && is_array($_POST['products'])) {
                    $query = "INSERT INTO sale_items (sale_id, product_id, quantity, price) 
                             VALUES (:sale_id, :product_id, :quantity, :price)";
                    $stmt = $db->prepare($query);

                                foreach ($_POST['products'] as $index => $product_id) {
                                    if (!empty($product_id)) {
                                        // Debug sale item data
                                        error_log("Adding Sale Item - Product ID: " . $product_id . 
                                                ", Quantity: " . $_POST['quantities'][$index] . 
                                                ", Price: " . $_POST['prices'][$index]);

                    $stmt->bindParam(':sale_id', $sale_id);
                                        $stmt->bindParam(':product_id', $product_id);
                                        $stmt->bindParam(':quantity', $_POST['quantities'][$index]);
                                        $stmt->bindParam(':price', $_POST['prices'][$index]);
                    $stmt->execute();

                                        // Update product stock
                                        $query = "UPDATE products SET quantity = quantity - :quantity WHERE id = :product_id";
                                        $update_stmt = $db->prepare($query);
                                        $update_stmt->bindParam(':quantity', $_POST['quantities'][$index]);
                                        $update_stmt->bindParam(':product_id', $product_id);
                                        $update_stmt->execute();
                                        error_log("Updated stock for product ID: " . $product_id);
                                    }
                                }
                            }

                            // Commit transaction
                            $db->commit();
                            error_log("Transaction committed successfully");
                            
                            header('Content-Type: application/json');
                            echo json_encode(['success' => true, 'message' => 'Sale created successfully']);
                            exit();
                        } else {
                            error_log("Failed to create sale record");
                            throw new Exception("Failed to create sale record");
                        }
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        $db->rollBack();
                        error_log("Transaction rolled back due to error: " . $e->getMessage());
                        throw $e;
                }
                break;
            }
        }
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
        exit();
    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit();
    }
}

// Fetch recent sales
try {
    error_log("Fetching recent sales");
    $query = "SELECT s.*, c.name as customer_name, 
              GROUP_CONCAT(p.name) as products,
              GROUP_CONCAT(si.quantity) as quantities,
              GROUP_CONCAT(si.price) as prices
                      FROM sales s
                      LEFT JOIN customers c ON s.customer_id = c.id
                      LEFT JOIN sale_items si ON s.id = si.sale_id
              LEFT JOIN products p ON si.product_id = p.id
                      GROUP BY s.id
                      ORDER BY s.created_at DESC
              LIMIT 50";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug sales data
    error_log("Fetched " . count($sales) . " sales records");
    if (count($sales) === 0) {
        error_log("No sales records found in the database");
    }
    
} catch (PDOException $e) {
    error_log("Error fetching sales: " . $e->getMessage());
    $sales = [];
}

// Fetch sales summary
$summary_query = "SELECT 
                  COUNT(*) as total_sales,
                  SUM(total_amount) as total_revenue,
                  SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END) as cash_sales,
                  SUM(CASE WHEN payment_method = 'card' THEN total_amount ELSE 0 END) as card_sales,
                  SUM(CASE WHEN payment_method = 'gcash' THEN total_amount ELSE 0 END) as gcash_sales,
                  SUM(CASE WHEN payment_method = 'maya' THEN total_amount ELSE 0 END) as maya_sales
                  FROM sales
                  WHERE DATE(created_at) = CURDATE()";
$summary_stmt = $db->prepare($summary_query);
$summary_stmt->execute();
$sales_summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);

// Fetch products with stock
$products_query = "SELECT products.*, categories.name as category_name 
                  FROM products 
                  LEFT JOIN categories ON products.category_id = categories.id 
                  WHERE products.quantity > 0 
                  ORDER BY products.name";
$products_stmt = $db->prepare($products_query);
$products_stmt->execute();
$products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch customers
$customers_query = "SELECT * FROM customers ORDER BY name";
$customers_stmt = $db->prepare($customers_query);
$customers_stmt->execute();
$customers = $customers_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales - POS Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <link href="assets/css/sidebar.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .dataTables_wrapper .dataTables_length select {
            width: 60px;
            display: inline-block;
        }
        .dataTables_wrapper .dataTables_filter input {
            width: 200px;
            margin-left: 10px;
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .dt-buttons {
            margin-bottom: 15px;
        }
        .dt-button {
            padding: 5px 15px;
            border-radius: 4px;
            margin-right: 5px;
        }
        .badge {
            font-size: 0.875rem;
            padding: 0.5em 0.75em;
        }
        .payment-badge {
            text-transform: capitalize;
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
            <a href="index.php" class="sidebar-brand">POS System</a>
        </div>
        <div class="sidebar-menu">
            <div class="sidebar-item">
                <a href="index.php" class="sidebar-link">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="sidebar-item">
                <a href="products.php" class="sidebar-link">
                    <i class="fas fa-box"></i>
                    <span>Products</span>
                </a>
            </div>
            <div class="sidebar-item">
                <a href="sales.php" class="sidebar-link active">
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
    <div class="row mb-4">
        <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
            <h2>Sales Management</h2>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSaleModal">
                        <i class="fas fa-plus"></i> New Sale
                    </button>
                </div>
            </div>
    </div>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stats-card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Today's Sales</h6>
                                <h3 class="mb-0">Rp <?php echo formatNumber($sales_summary['total_revenue']); ?></h3>
                        </div>
                        <i class="fas fa-shopping-cart stats-icon"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Total Orders</h6>
                            <h3 class="mb-0"><?php echo $sales_summary['total_sales']; ?></h3>
                        </div>
                        <i class="fas fa-receipt stats-icon"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Cash Sales</h6>
                                <h3 class="mb-0">Rp <?php echo formatNumber($sales_summary['cash_sales']); ?></h3>
                        </div>
                        <i class="fas fa-money-bill-wave stats-icon"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Digital Sales</h6>
                            <h3 class="mb-0">Rp <?php 
                                $digital_sales = $sales_summary['card_sales'] + $sales_summary['gcash_sales'] + $sales_summary['maya_sales'];
                                    echo formatNumber($digital_sales);
                            ?></h3>
                        </div>
                        <i class="fas fa-credit-card stats-icon"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

        <!-- Sales Table -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Recent Sales</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="salesTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Products</th>
                                <th>Total</th>
                                <th>Payment Method</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales as $sale): ?>
                                <tr>
                                    <td><?php echo date('M d, Y h:i A', strtotime($sale['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer'); ?></td>
                                    <td>
                                        <?php 
                                        $products = explode(',', $sale['products']);
                                        $quantities = explode(',', $sale['quantities']);
                                        $prices = explode(',', $sale['prices']);
                                        for ($i = 0; $i < count($products); $i++) {
                                            echo htmlspecialchars($products[$i]) . ' x ' . $quantities[$i] . 
                                                 ' (Rp ' . formatNumber($prices[$i]) . ')<br>';
                                        }
                                        ?>
                                    </td>
                                    <td class="fw-medium">Rp <?php echo formatNumber($sale['total_amount']); ?></td>
                                    <td>
                                        <?php 
                                        $payment_method = $sale['payment_method'];
                                        $badge_class = match($payment_method) {
                                            'cash' => 'bg-success',
                                            'credit_card' => 'bg-info',
                                            'bank_transfer' => 'bg-primary',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?> payment-badge">
                                            <?php echo ucfirst(str_replace('_', ' ', $payment_method)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success">Completed</span>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-info view-sale" data-id="<?php echo $sale['id']; ?>" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-primary print-receipt" data-id="<?php echo $sale['id']; ?>" title="Print Receipt">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($sales)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No sales records found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add Sale Modal -->
        <div class="modal fade" id="addSaleModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New Sale</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addSaleForm">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="customer_id" class="form-label">Customer</label>
                                    <select class="form-select" id="customer_id" name="customer_id" required>
                            <option value="">Select Customer</option>
                                        <?php
                                        $query = "SELECT id, name FROM customers ORDER BY name";
                                        $stmt = $db->prepare($query);
                                        $stmt->execute();
                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            echo "<option value='{$row['id']}'>{$row['name']}</option>";
                                        }
                                        ?>
                        </select>
                    </div>
                                <div class="col-md-6">
                                    <label for="sale_date" class="form-label">Sale Date</label>
                                    <input type="datetime-local" class="form-control" id="sale_date" name="sale_date" 
                                           value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                            </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Products</label>
                                <div id="productItems">
                                    <div class="product-item row mb-2">
                                        <div class="col-md-5">
                                            <select class="form-select product-select" name="products[]" required>
                                                <option value="">Select Product</option>
                                                <?php
                                                $query = "SELECT id, name, price, quantity FROM products WHERE quantity > 0 ORDER BY name";
                                                $stmt = $db->prepare($query);
                                                $stmt->execute();
                                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                    echo "<option value='{$row['id']}' data-price='{$row['price']}' data-stock='{$row['quantity']}'>{$row['name']}</option>";
                                                }
                                                ?>
                                            </select>
                            </div>
                                        <div class="col-md-2">
                                            <input type="number" class="form-control quantity" name="quantities[]" min="1" value="1" required>
                            </div>
                                        <div class="col-md-3">
                                            <input type="number" class="form-control price" name="prices[]" step="0.01" readonly>
                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-danger remove-product">
                                                <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
                                <button type="button" class="btn btn-secondary mt-2" id="addProduct">
                                    <i class="fas fa-plus"></i> Add Product
                                </button>
                        </div>
                        
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="payment_method" class="form-label">Payment Method</label>
                                    <select class="form-select" id="payment_method" name="payment_method" required>
                                        <option value="cash">Cash</option>
                                        <option value="credit_card">Credit Card</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                </select>
                            </div>
                                <div class="col-md-6">
                                    <label for="total_amount" class="form-label">Total Amount</label>
                                    <input type="number" class="form-control" id="total_amount" name="total_amount" 
                                           step="0.01" readonly>
                                </div>
                        </div>
                        
                        <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="submitAddSale()">Add Sale</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Receipt Modal -->
        <div class="modal fade" id="receiptModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Sales Receipt</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="receiptContent">
                        <div class="text-center mb-4">
                            <h4 class="mb-1">POS Inventory System</h4>
                            <p class="mb-1">Sales Receipt</p>
                            <p class="mb-0" id="receiptDate"></p>
                        </div>
                        <div class="mb-3">
                            <p class="mb-1"><strong>Customer:</strong> <span id="receiptCustomer"></span></p>
                            <p class="mb-1"><strong>Receipt #:</strong> <span id="receiptNumber"></span></p>
                        </div>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-end">Price</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody id="receiptItems">
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Total Amount:</strong></td>
                                        <td class="text-end" id="receiptTotal"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="mb-3">
                            <p class="mb-1"><strong>Payment Method:</strong> <span id="receiptPayment"></span></p>
                            <p class="mb-0"><strong>Notes:</strong> <span id="receiptNotes"></span></p>
                        </div>
                        <div class="text-center mt-4">
                            <p class="mb-0">Thank you for your purchase!</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" onclick="printReceipt()">
                            <i class="fas fa-print"></i> Print Receipt
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script src="assets/js/sidebar.js"></script>
    <script>
        $(document).ready(function() {
            $('#salesTable').DataTable({
                processing: true,
                responsive: true,
                dom: '<"row"<"col-sm-12 col-md-6"B><"col-sm-12 col-md-6"f>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                buttons: [
                    {
                        extend: 'collection',
                        text: '<i class="fas fa-download me-2"></i>Export',
                        buttons: [
                            {
                                extend: 'excel',
                                text: '<i class="fas fa-file-excel me-2"></i>Excel',
                                className: 'btn btn-success btn-sm',
                                exportOptions: {
                                    columns: [0, 1, 2, 3, 4, 5]
                                }
                            },
                            {
                                extend: 'pdf',
                                text: '<i class="fas fa-file-pdf me-2"></i>PDF',
                                className: 'btn btn-danger btn-sm',
                                exportOptions: {
                                    columns: [0, 1, 2, 3, 4, 5]
                                }
                            },
                            {
                                extend: 'print',
                                text: '<i class="fas fa-print me-2"></i>Print',
                                className: 'btn btn-info btn-sm',
                                exportOptions: {
                                    columns: [0, 1, 2, 3, 4, 5]
                                }
                            }
                        ]
                    }
                ],
                language: {
                    search: "",
                    searchPlaceholder: "Search sales...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ sales",
                    infoEmpty: "No sales available",
                    infoFiltered: "(filtered from _MAX_ total sales)",
                    zeroRecords: "No matching sales found",
                    paginate: {
                        first: '<i class="fas fa-angle-double-left"></i>',
                        previous: '<i class="fas fa-angle-left"></i>',
                        next: '<i class="fas fa-angle-right"></i>',
                        last: '<i class="fas fa-angle-double-right"></i>'
                    }
                },
                order: [[0, 'desc']],
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                data: <?php echo json_encode($sales); ?>,
                columns: [
                    { 
                        data: 'created_at',
                        render: function(data, type, row) {
                            return moment(data).format('MMM D, YYYY h:mm A');
                        }
                    },
                    { 
                        data: 'customer_name',
                        render: function(data, type, row) {
                            return data || 'Walk-in Customer';
                        }
                    },
                    { 
                        data: null,
                        orderable: false,
                        render: function(data, type, row) {
                            var products = row.products ? row.products.split(',') : [];
                            var quantities = row.quantities ? row.quantities.split(',') : [];
                            var prices = row.prices ? row.prices.split(',') : [];
                            var html = '';
                            for (var i = 0; i < products.length; i++) {
                                if (products[i]) {
                                    html += products[i] + ' x ' + quantities[i] + ' (Rp ' + formatNumber(prices[i]) + ')<br>';
                                }
                            }
                            return html;
                        }
                    },
                    { 
                        data: 'total_amount',
                        render: function(data, type, row) {
                            return 'Rp ' + formatNumber(data);
                        }
                    },
                    { 
                        data: 'payment_method',
                        render: function(data, type, row) {
                            var badgeClass = {
                                'cash': 'bg-success',
                                'credit_card': 'bg-info',
                                'bank_transfer': 'bg-primary'
                            }[data] || 'bg-secondary';
                            
                            return '<span class="badge ' + badgeClass + ' payment-badge">' + 
                                   data.replace('_', ' ').charAt(0).toUpperCase() + 
                                   data.slice(1) + '</span>';
                        }
                    },
                    { 
                        data: null,
                        orderable: false,
                        defaultContent: '<span class="badge bg-success">Completed</span>'
                    },
                    { 
                        data: 'id',
                        orderable: false,
                        searchable: false,
                        className: 'text-end',
                        render: function(data, type, row) {
                            return '<button class="btn btn-sm btn-info view-sale" data-id="' + data + '" title="View Details">' +
                                   '<i class="fas fa-eye"></i></button> ' +
                                   '<button class="btn btn-sm btn-primary print-receipt" data-id="' + data + '" title="Print Receipt">' +
                                   '<i class="fas fa-print"></i></button>';
                        }
                    }
                ]
            });

            // Add tooltips to action buttons
            $('.view-sale, .print-receipt').tooltip({
                placement: 'top'
            });

            // Handle print receipt button click
            $('.print-receipt').click(function() {
                const saleId = $(this).data('id');
                loadReceiptData(saleId);
            });

            // Product row template
            function getProductRowHtml() {
                return `
                    <div class="product-item row mb-2">
                        <div class="col-md-5">
                            <select class="form-select product-select" name="products[]" required>
                                <option value="">Select Product</option>
                                <?php
                                $query = "SELECT id, name, price, quantity FROM products WHERE quantity > 0 ORDER BY name";
                                $stmt = $db->prepare($query);
                                $stmt->execute();
                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value='{$row['id']}' data-price='{$row['price']}' data-stock='{$row['quantity']}'>{$row['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="number" class="form-control quantity" name="quantities[]" min="1" value="1" required>
                        </div>
                        <div class="col-md-3">
                            <input type="number" class="form-control price" name="prices[]" step="0.01" readonly>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-danger remove-product">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                `;
            }

            // Add new product row
            $('#addProduct').click(function() {
                $('#productItems').append(getProductRowHtml());
                updateTotalAmount();
            });

            // Remove product row
            $(document).on('click', '.remove-product', function() {
                $(this).closest('.product-item').remove();
                updateTotalAmount();
            });

            // Handle product selection
            $(document).on('change', '.product-select', function() {
                const row = $(this).closest('.product-item');
                const selectedOption = $(this).find('option:selected');
                const price = selectedOption.data('price') || 0;
                const stock = selectedOption.data('stock') || 0;
                
                // Update price field
                row.find('.price').val(price);
                
                // Update quantity max attribute
                row.find('.quantity').attr('max', stock);
                
                updateTotalAmount();
            });

            // Handle quantity change
            $(document).on('change', '.quantity', function() {
                const row = $(this).closest('.product-item');
                const quantity = parseInt($(this).val()) || 0;
                const max = parseInt($(this).attr('max')) || 0;
                
                // Validate quantity against stock
                if (quantity > max) {
                    alert('Quantity cannot exceed available stock: ' + max);
                    $(this).val(max);
                }
                
                updateTotalAmount();
            });

            // Update total amount
            function updateTotalAmount() {
                let total = 0;
                $('.product-item').each(function() {
                    const price = parseFloat($(this).find('.price').val()) || 0;
                    const quantity = parseInt($(this).find('.quantity').val()) || 0;
                    total += price * quantity;
                });
                $('#total_amount').val(total.toFixed(2));
            }

            // Reset form when modal is closed
            $('#addSaleModal').on('hidden.bs.modal', function() {
                $('#addSaleForm')[0].reset();
                $('#productItems').html(getProductRowHtml());
                updateTotalAmount();
            });

            // Initialize the first row
            updateTotalAmount();
        });

        // Handle form submission
        function submitAddSale() {
            // Validate form
            const form = $('#addSaleForm');
            if (!form[0].checkValidity()) {
                form[0].reportValidity();
                return;
            }

            // Check if products are selected
            if ($('.product-select').filter(function() { return $(this).val(); }).length === 0) {
                alert('Please add at least one product');
                return;
            }

            // Prepare form data
            const formData = new FormData(form[0]);
            formData.append('action', 'add_sale');

            // Disable submit button and show loading state
            const submitBtn = $('.modal-footer .btn-primary');
            const originalText = submitBtn.html();
            submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Processing...').prop('disabled', true);

            // Submit form
            $.ajax({
                url: 'sales.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        alert('Sale created successfully');
                        
                        // Close modal and reset form
                        $('#addSaleModal').modal('hide');
                        
                        // Reload page to show new sale
                        location.reload();
                    } else {
                        // Show error message
                        alert(response.message || 'Error creating sale');
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error creating sale: ' + error);
                },
                complete: function() {
                    // Reset button state
                    submitBtn.html(originalText).prop('disabled', false);
                }
            });
        }

        function loadReceiptData(saleId) {
            // Show loading state
            $('#receiptModal').modal('show');
            $('#receiptContent').html('<div class="text-center"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Loading receipt data...</p></div>');
            
            $.ajax({
                url: 'get_receipt.php',
                method: 'POST',
                data: { sale_id: saleId },
                success: function(response) {
                    if (response.success) {
                        const sale = response.data;
                        
                        // Format date
                        const saleDate = new Date(sale.created_at);
                        
                        // Generate items HTML
                        let itemsHtml = '';
                        let total = 0;
                        let itemNumber = 1; // Initialize item counter
                        
                        const products = sale.products.split('||'); // Changed separator to match get_receipt.php
                        const quantities = sale.quantities.split('||');
                        const prices = sale.prices.split('||');
                        
                        for (let i = 0; i < products.length; i++) {
                            if (!products[i]) continue; // Skip empty entries
                            
                            const itemTotal = parseFloat(quantities[i]) * parseFloat(prices[i]);
                            total += itemTotal;
                            
                            // Add item with sequential number and proper spacing
                            itemsHtml += `
                                <tr>
                                    <td class="text-start" style="width: 40px;">${itemNumber}.</td>
                                    <td class="text-start" style="padding-left: 10px;">${products[i].padEnd(15)}</td>
                                    <td class="text-center" style="width: 60px;">${quantities[i]}</td>
                                    <td class="text-end" style="width: 120px;">Rp ${formatNumber(prices[i])}</td>
                                    <td class="text-end" style="width: 120px;">Rp ${formatNumber(itemTotal)}</td>
                                </tr>
                            `;
                            itemNumber++; // Increment counter for next item
                        }
                        
                        // Generate receipt content with the new format
                        const receiptHtml = `
                            <div style="font-family: monospace; width: 100%; max-width: 400px; margin: 0 auto;">
                                <div class="text-center mb-4">
                                    <h4 class="mb-1">POS Inventory System</h4>
                                    <p class="mb-1">Sales Receipt</p>
                                    <p class="mb-3">${saleDate.toLocaleString()}</p>
                                    <div class="text-start mb-3">
                                        <p class="mb-1">Customer: ${sale.customer_name || 'Walk-in Customer'}</p>
                                        <p class="mb-1">Receipt #: ${sale.id.toString().padStart(6, '0')}</p>
                                        <p class="mb-3">Printed By: ${sale.printed_by || 'admin'}</p>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <table class="table table-sm" style="width: 100%; font-family: monospace;">
                                        <thead>
                                            <tr>
                                                <th class="text-start" style="width: 40px;">No.</th>
                                                <th class="text-start" style="padding-left: 10px;">Item</th>
                                                <th class="text-center" style="width: 60px;">Qty</th>
                                                <th class="text-end" style="width: 120px;">Price</th>
                                                <th class="text-end" style="width: 120px;">Total</th>
                                            </tr>
                                            <tr>
                                                <td colspan="5" style="border-bottom: 1px solid #000;"></td>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${itemsHtml}
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="5" style="border-bottom: 1px solid #000;"></td>
                                            </tr>
                                            <tr>
                                                <td colspan="4" class="text-end"><strong>Total Amount:</strong></td>
                                                <td class="text-end"><strong>Rp ${formatNumber(total)}</strong></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <div class="mb-3">
                                    <p class="mb-1"><strong>Payment Method:</strong> ${sale.payment_method.replace('_', ' ').toUpperCase()}</p>
                                    <p class="mb-3"><strong>Notes:</strong> ${sale.notes || 'N/A'}</p>
                                </div>
                                <div class="text-center mt-4">
                                    <p class="mb-0">Thank you for your purchase!</p>
                                </div>
                            </div>
                        `;
                        
                        // Update modal content
                        $('#receiptContent').html(receiptHtml);
                    } else {
                        $('#receiptContent').html(`
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                ${response.message || 'Error loading receipt data'}
                            </div>
                        `);
                    }
                },
                error: function(xhr, status, error) {
                    $('#receiptContent').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Error loading receipt data. Please try again.
                        </div>
                    `);
                    console.error('Error:', error);
                }
            });
        }

        function printReceipt() {
            const receiptContent = document.getElementById('receiptContent').innerHTML;
            const printWindow = window.open('', '_blank');
            
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Sales Receipt</title>
                        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                        <style>
                            body { 
                                padding: 20px;
                                font-size: 12px;
                                font-family: monospace;
                            }
                            @media print {
                                body { 
                                    padding: 0;
                                    margin: 0;
                                    width: 80mm; /* Standard thermal receipt width */
                                    font-family: monospace !important;
                                }
                                .no-print { 
                                    display: none; 
                                }
                                table {
                                    width: 100% !important;
                                    margin-bottom: 1rem;
                                    border-collapse: collapse !important;
                                }
                                th, td {
                                    padding: 0.15rem !important;
                                    border: none !important;
                                    font-family: monospace !important;
                                    white-space: nowrap !important;
                                }
                                th {
                                    font-weight: bold !important;
                                }
                                .text-end {
                                    text-align: right !important;
                                }
                                .text-center {
                                    text-align: center !important;
                                }
                                .text-start {
                                    text-align: left !important;
                                }
                                .mb-1 { margin-bottom: 0.25rem !important; }
                                .mb-3 { margin-bottom: 1rem !important; }
                                .mb-4 { margin-bottom: 1.5rem !important; }
                                .mt-4 { margin-top: 1.5rem !important; }
                                
                                /* Custom receipt styles */
                                h4 { 
                                    font-size: 14px !important;
                                    margin-bottom: 0.5rem !important;
                                }
                                p {
                                    margin-bottom: 0.25rem !important;
                                }
                                .table-sm td,
                                .table-sm th {
                                    padding: 0.15rem !important;
                                }
                            }
                        </style>
                    </head>
                    <body>
                        ${receiptContent}
                        <div class="text-center mt-4 no-print">
                            <button onclick="window.print()" class="btn btn-primary">Print</button>
                        </div>
                    </body>
                </html>
            `);
            
            printWindow.document.close();
        }

        function formatNumber(number) {
            return parseFloat(number).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }
    </script>
</body>
</html> 