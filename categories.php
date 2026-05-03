<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Debug: Check database connection
if (!$db) {
    die("Database connection failed");
}

// Handle category actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_category':
                    // Validate input
                    if (empty($_POST['name'])) {
                        throw new Exception("Category name is required");
                    }

                    // Check for duplicate category name
                    $check_query = "SELECT id FROM categories WHERE name = :name";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->bindParam(':name', $_POST['name']);
                    $check_stmt->execute();
                    
                    if ($check_stmt->rowCount() > 0) {
                        throw new Exception("Category name already exists");
                    }

                    // Start transaction
                    $db->beginTransaction();

                    // Insert new category
                    $query = "INSERT INTO categories (name, description) VALUES (:name, :description)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':name', $_POST['name'], PDO::PARAM_STR);
                    $stmt->bindParam(':description', $_POST['description'], PDO::PARAM_STR);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to add category to database");
                    }

                    // Commit transaction
                    $db->commit();

                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Category added successfully'
                    ]);
                    exit();
                    break;

                case 'update_category':
                    // Validate required fields
                    $required_fields = ['id', 'name'];
                    foreach ($required_fields as $field) {
                        if (empty($_POST[$field])) {
                            throw new Exception("Please fill in all required fields.");
                        }
                    }

                    // Start transaction
                    $db->beginTransaction();

                    // Check if category exists
                    $check_query = "SELECT id FROM categories WHERE id = :id";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->bindParam(':id', $_POST['id']);
                    $check_stmt->execute();

                    if ($check_stmt->rowCount() === 0) {
                        throw new Exception("Category not found");
                    }

                    // Update category
                    $query = "UPDATE categories 
                             SET name = :name, 
                                 description = :description 
                             WHERE id = :id";
                    $stmt = $db->prepare($query);
                    
                    // Bind parameters
                    $stmt->bindParam(':id', $_POST['id'], PDO::PARAM_INT);
                    $stmt->bindParam(':name', $_POST['name'], PDO::PARAM_STR);
                    $stmt->bindParam(':description', $_POST['description'], PDO::PARAM_STR);

                    if (!$stmt->execute()) {
                        throw new Exception("Failed to update category in database");
                    }

                    // Commit transaction
                    $db->commit();

                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Category updated successfully'
                    ]);
                    exit();
                    break;

                case 'delete_category':
                    // Validate input
                    if (empty($_POST['id'])) {
                        throw new Exception("Category ID is required");
                    }

                    // Check if category exists
                    $check_query = "SELECT id FROM categories WHERE id = :id";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->bindParam(':id', $_POST['id']);
                    $check_stmt->execute();
                    
                    if ($check_stmt->rowCount() === 0) {
                        throw new Exception("Category not found");
                    }

                    // Check if category has associated products
                    $check_products = "SELECT COUNT(*) as count FROM products WHERE category_id = :id";
                    $check_products_stmt = $db->prepare($check_products);
                    $check_products_stmt->bindParam(':id', $_POST['id']);
                    $check_products_stmt->execute();
                    $product_count = $check_products_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    if ($product_count > 0) {
                        throw new Exception("Cannot delete category with associated products");
                    }

                    // Delete category
                    $query = "DELETE FROM categories WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $_POST['id']);
                    $result = $stmt->execute();

                    if (!$result) {
                        throw new Exception("Failed to delete category");
                    }

                    $_SESSION['success'] = "Category deleted successfully";
                    break;
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    // Redirect to prevent form resubmission
    header("Location: categories.php");
    exit();
}

// Fetch all categories with error handling
try {
    $query = "SELECT * FROM categories ORDER BY name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Check if categories were fetched
    if (empty($categories)) {
        echo "No categories found in the database.";
    }
} catch (PDOException $e) {
    echo "Error fetching categories: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - POS Inventory System</title>
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

        /* Category Card Styles */
        .category-card {
            border-radius: 0.5rem;
            transition: transform 0.2s;
        }
        .category-card:hover {
            transform: translateY(-5px);
        }
        .category-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        .category-actions {
            display: flex;
            gap: 0.5rem;
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

            .table-responsive {
                overflow-x: auto;
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
            .category-actions {
                flex-direction: column;
            }
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
            <li class="sidebar-item active">
                <a href="categories.php" class="sidebar-link active">
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
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Categories</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            </div>

            <!-- Categories List -->
            <div class="row">
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $category): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card category-card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <i class="fas fa-tag category-icon text-primary"></i>
                                        <h5 class="card-title mb-0 ms-3"><?php echo htmlspecialchars($category['name']); ?></h5>
                                    </div>
                                    <p class="card-text"><?php echo htmlspecialchars($category['description']); ?></p>
                                    <div class="category-actions">
                                        <button class="btn btn-sm btn-primary" onclick="editCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', '<?php echo htmlspecialchars($category['description']); ?>')">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteCategory(<?php echo $category['id']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-info">
                            No categories found. Click the "Add Category" button to create one.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addCategoryForm">
                        <div class="mb-3">
                            <label for="name" class="form-label">Category Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitAddCategory()">Add Category</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editCategoryForm">
                        <div class="mb-3">
                            <label for="editCategoryId" class="form-label">ID</label>
                            <input type="text" class="form-control" id="editCategoryId" name="id" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="editCategoryName" class="form-label">Name</label>
                            <input type="text" class="form-control" id="editCategoryName" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="editCategoryDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="editCategoryDescription" name="description" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitEditCategory()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function submitEditCategory() {
            const form = document.getElementById('editCategoryForm');
            const formData = new FormData();
            
            // Get form values
            const id = document.getElementById('editCategoryId').value;
            const name = document.getElementById('editCategoryName').value;
            const description = document.getElementById('editCategoryDescription').value;

            // Validate input
            if (!name) {
                showToast('Category name is required', 'error');
                return;
            }

            // Add form data
            formData.append('action', 'update_category');
            formData.append('id', id);
            formData.append('name', name);
            formData.append('description', description);

            // Show loading state
            const submitButton = document.querySelector('#editCategoryModal .btn-primary');
            const originalText = submitButton.innerHTML;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            submitButton.disabled = true;

            fetch('categories.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.status === 'success') {
                        showToast(data.message, 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showToast(data.message || 'Error updating category', 'error');
                        submitButton.innerHTML = originalText;
                        submitButton.disabled = false;
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    console.error('Raw response:', text);
                    showToast('An error occurred while processing the response', 'error');
                    submitButton.innerHTML = originalText;
                    submitButton.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An unexpected error occurred', 'error');
                submitButton.innerHTML = originalText;
                submitButton.disabled = false;
            });
        }

        function submitAddCategory() {
            const form = document.getElementById('addCategoryForm');
            const formData = new FormData();
            
            // Get form values
            const name = document.getElementById('name').value;
            const description = document.getElementById('description').value;

            // Validate input
            if (!name) {
                showToast('Category name is required', 'error');
                return;
            }

            // Add form data
            formData.append('action', 'add_category');
            formData.append('name', name);
            formData.append('description', description);

            // Show loading state
            const submitButton = document.querySelector('#addCategoryModal .btn-primary');
            const originalText = submitButton.innerHTML;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            submitButton.disabled = true;

            fetch('categories.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.status === 'success') {
                        showToast(data.message, 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showToast(data.message || 'Error adding category', 'error');
                        submitButton.innerHTML = originalText;
                        submitButton.disabled = false;
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    console.error('Raw response:', text);
                    showToast('An error occurred while processing the response', 'error');
                    submitButton.innerHTML = originalText;
                    submitButton.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An unexpected error occurred', 'error');
                submitButton.innerHTML = originalText;
                submitButton.disabled = false;
            });
        }

        function editCategory(id, name, description = '') {
            // Set form values
            document.getElementById('editCategoryId').value = id;
            document.getElementById('editCategoryName').value = name;
            document.getElementById('editCategoryDescription').value = description;
            
            // Show modal
            const editModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
            editModal.show();
            
            // Focus on name input
            document.getElementById('editCategoryName').focus();
        }

        function deleteCategory(id) {
            if (confirm('Are you sure you want to delete this category?')) {
                const formData = new FormData();
                formData.append('action', 'delete_category');
                formData.append('id', id);

                fetch('categories.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (response.ok) {
                        window.location.reload();
                    } else {
                        alert('Error deleting category');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting category');
                });
            }
        }

        // Add toast notification function
        function showToast(message, type = 'info') {
            const toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            toastContainer.style.zIndex = '5';
            
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} border-0`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            `;
            
            toastContainer.appendChild(toast);
            document.body.appendChild(toastContainer);
            
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            
            // Remove toast after it's hidden
            toast.addEventListener('hidden.bs.toast', function () {
                toastContainer.remove();
            });
        }
    </script>
</body>
</html> 