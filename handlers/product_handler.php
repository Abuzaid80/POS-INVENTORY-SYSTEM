<?php
session_start();
require_once '../config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

// Get database connection
try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Handle different actions
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add':
        handleAddProduct($conn);
        break;
    case 'edit':
        handleEditProduct($conn);
        break;
    case 'delete':
        handleDeleteProduct($conn);
        break;
    case 'get':
        handleGetProduct($conn);
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}

function handleAddProduct($conn) {
    try {
        // Log received data for debugging
        error_log("Received POST data: " . print_r($_POST, true));
        error_log("Received FILES data: " . print_r($_FILES, true));

        // Validate required fields
        $required_fields = ['name', 'price', 'quantity', 'category_id', 'low_stock_threshold'];
        $missing_fields = [];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $missing_fields[] = $field;
            }
        }
        if (!empty($missing_fields)) {
            throw new Exception("Missing required fields: " . implode(', ', $missing_fields));
        }

        // Validate price and quantity
        if (!is_numeric($_POST['price']) || $_POST['price'] <= 0) {
            throw new Exception("Price must be a positive number");
        }
        if (!is_numeric($_POST['quantity']) || $_POST['quantity'] < 0) {
            throw new Exception("Quantity must be a non-negative number");
        }
        if (!is_numeric($_POST['low_stock_threshold']) || $_POST['low_stock_threshold'] < 0) {
            throw new Exception("Low stock threshold must be a non-negative number");
        }

        // Handle image upload
        $image_path = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/products/';
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception("Failed to create upload directory");
                }
            }

            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception("Invalid file type. Allowed types: " . implode(', ', $allowed_extensions));
            }

            $filename = uniqid() . '.' . $file_extension;
            $target_path = $upload_dir . $filename;

            if (!move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                throw new Exception("Failed to upload image. Error: " . $_FILES['image']['error']);
            }

            $image_path = 'uploads/products/' . $filename;
        }

        // Prepare and execute the insert query
        $stmt = $conn->prepare("
            INSERT INTO products (
                name, description, price, quantity, category_id, 
                low_stock_threshold, image, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        // Bind parameters
        $name = $_POST['name'];
        $description = $_POST['description'] ?? '';
        $price = floatval($_POST['price']);
        $quantity = intval($_POST['quantity']);
        $category_id = intval($_POST['category_id']);
        $low_stock_threshold = intval($_POST['low_stock_threshold']);

        $stmt->bind_param(
            "ssdiiis",
            $name,
            $description,
            $price,
            $quantity,
            $category_id,
            $low_stock_threshold,
            $image_path
        );

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        // Get the inserted product ID
        $product_id = $conn->insert_id;

        // Log success
        error_log("Product added successfully. ID: " . $product_id);

        echo json_encode([
            'status' => 'success',
            'message' => 'Product added successfully',
            'product_id' => $product_id
        ]);

    } catch (Exception $e) {
        error_log("Error in handleAddProduct: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
            'debug_info' => [
                'post_data' => $_POST,
                'files_data' => $_FILES
            ]
        ]);
    }
}

function handleEditProduct($conn) {
    try {
        if (empty($_POST['id'])) {
            throw new Exception("Product ID is required");
        }

        // Validate required fields
        $required_fields = ['name', 'price', 'quantity', 'category_id', 'low_stock_threshold'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("$field is required");
            }
        }

        // Handle image upload if new image is provided
        $image_path = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/products/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception("Invalid file type. Allowed types: " . implode(', ', $allowed_extensions));
            }

            $filename = uniqid() . '.' . $file_extension;
            $target_path = $upload_dir . $filename;

            if (!move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                throw new Exception("Failed to upload image");
            }

            $image_path = 'uploads/products/' . $filename;
        }

        // Prepare the update query
        $sql = "UPDATE products SET 
                name = ?, 
                description = ?, 
                price = ?, 
                quantity = ?, 
                category_id = ?, 
                low_stock_threshold = ?";
        
        if ($image_path) {
            $sql .= ", image = ?";
        }
        
        $sql .= " WHERE id = ?";

        $stmt = $conn->prepare($sql);

        if ($image_path) {
            $stmt->bind_param(
                "ssdiiisi",
                $_POST['name'],
                $_POST['description'],
                $_POST['price'],
                $_POST['quantity'],
                $_POST['category_id'],
                $_POST['low_stock_threshold'],
                $image_path,
                $_POST['id']
            );
        } else {
            $stmt->bind_param(
                "ssdiiii",
                $_POST['name'],
                $_POST['description'],
                $_POST['price'],
                $_POST['quantity'],
                $_POST['category_id'],
                $_POST['low_stock_threshold'],
                $_POST['id']
            );
        }

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Product updated successfully']);
        } else {
            throw new Exception("Database error: " . $stmt->error);
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function handleDeleteProduct($conn) {
    try {
        if (empty($_POST['id'])) {
            throw new Exception("Product ID is required");
        }

        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $_POST['id']);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Product deleted successfully']);
        } else {
            throw new Exception("Database error: " . $stmt->error);
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function handleGetProduct($conn) {
    try {
        if (empty($_POST['id'])) {
            throw new Exception("Product ID is required");
        }

        $product_id = intval($_POST['id']);
        
        $query = "SELECT p.*, c.name as category_name 
                 FROM products p 
                 LEFT JOIN categories c ON p.category_id = c.id 
                 WHERE p.id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        
        if (!$product) {
            throw new Exception("Product not found");
        }

        echo json_encode([
            'status' => 'success',
            'data' => $product
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}
?> 