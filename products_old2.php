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

                    // Start building the update query
                    $query = "UPDATE products SET 
                             name = :name,
                             description = :description,
                             price = :price,
                             quantity = :quantity,
                             category_id = :category_id,
                             low_stock_threshold = :low_stock_threshold";

                    // Handle image upload if a new image is provided
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
                            $query .= ", image = :image";
                        }
                    }

                    $query .= " WHERE id = :id";
                    $stmt = $db->prepare($query);

                    // Bind parameters with proper type casting
                    $stmt->bindParam(':id', $_POST['id'], PDO::PARAM_INT);
                    $stmt->bindParam(':name', $_POST['name'], PDO::PARAM_STR);
                    $stmt->bindParam(':description', $_POST['description'], PDO::PARAM_STR);
                    $stmt->bindParam(':price', $_POST['price'], PDO::PARAM_STR);
                    $stmt->bindParam(':quantity', $_POST['quantity'], PDO::PARAM_INT);
                    $stmt->bindParam(':category_id', $_POST['category_id'], PDO::PARAM_INT);
                    $stmt->bindParam(':low_stock_threshold', $_POST['low_stock_threshold'], PDO::PARAM_INT);
                    
                    if ($image_path) {
                        $stmt->bindParam(':image', $image_path, PDO::PARAM_STR);
                    }

                    if ($stmt->execute()) {
                        echo json_encode(['status' => 'success', 'message' => 'Product updated successfully']);
                    } else {
                        throw new Exception("Failed to update product.");
                    }
                    exit();
                    break;

                case 'get_product':
                    if (!isset($_GET['id'])) {
                        throw new Exception("Product ID is required.");
                    }

                    $query = "SELECT * FROM products WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $_GET['id'], PDO::PARAM_INT);
                    $stmt->execute();
                    
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($product) {
                        echo json_encode($product);
                    } else {
                        throw new Exception("Product not found.");
                    }
                    exit();
                    break;

                case 'delete_product':
                    $query = "DELETE FROM products WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $_POST['id']);
                    $stmt->execute();
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
    <link href="assets/css/navigation.css" rel="stylesheet">
    <style>
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
    <?php include 'components/navigation.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
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
                                        <td>
                                            <img src="<?php echo $product['image'] ? $product['image'] : 'assets/img/no-image.jpg'; ?>" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                 class="img-thumbnail"
                                                 style="width: 50px; height: 50px; object-fit: cover;">
                                        </td>
                                        <td class="fw-medium"><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                        <td class="fw-medium">₱<?php echo number_format($product['price'], 2); ?></td>
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
                            <input type="file" class="form-control" name="image" accept="image/*">
                            <div class="image-preview mt-2">
                                <img src="" alt="Preview" class="img-fluid" id="edit_image_preview">
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
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
                console.log('Edit button clicked for product ID:', productId);
                
                // Fetch product data
                $.ajax({
                    url: 'products.php',
                    type: 'GET',
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
                                $('#edit_image_preview').parent().show();
                            } else {
                                $('#edit_image_preview').attr('src', '');
                                $('#edit_image_preview').parent().hide();
                            }
                            
                            // Show modal
                            $('#editProductModal').modal('show');
                        } catch (e) {
                            console.error('Error parsing product data:', e);
                            alert('Error loading product data. Please try again.');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                        console.error('Status:', status);
                        console.error('Response:', xhr.responseText);
                        alert('Error loading product data. Please try again.');
                    }
                });
            });

            // Handle edit form submission
            $('#editProductForm').on('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                formData.append('action', 'update_product');
                
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
                                $('#editProductModal').modal('hide');
                                location.reload();
                            } else {
                                alert(result.message || 'An error occurred while updating the product.');
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            console.error('Raw response:', response);
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

            // Handle delete product
            $('.delete-product').click(function() {
                if (confirm('Are you sure you want to delete this product?')) {
                    const productId = $(this).data('id');
                    
                    $.ajax({
                        url: 'products.php',
                        type: 'POST',
                        data: { action: 'delete_product', id: productId },
                        success: function(response) {
                            location.reload();
                        },
                        error: function(xhr, status, error) {
                            console.error('Error:', error);
                            alert('An error occurred while processing your request.');
                        }
                    });
                }
            });
        });
    </script>
</body>
</html> 