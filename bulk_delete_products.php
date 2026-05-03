<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

header('Content-Type: application/json');

if (!isset($_POST['ids']) || !is_array($_POST['ids'])) {
    echo json_encode(['success' => false, 'message' => 'No products selected']);
    exit();
}

try {
    $db->beginTransaction();
    
    // Delete product images first
    $query = "SELECT image FROM products WHERE id IN (" . implode(',', array_fill(0, count($_POST['ids']), '?')) . ")";
    $stmt = $db->prepare($query);
    $stmt->execute($_POST['ids']);
    $images = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($images as $image) {
        if (!empty($image)) {
            $imagePath = 'uploads/products/' . $image;
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
    }
    
    // Delete products
    $query = "DELETE FROM products WHERE id IN (" . implode(',', array_fill(0, count($_POST['ids']), '?')) . ")";
    $stmt = $db->prepare($query);
    $stmt->execute($_POST['ids']);
    
    $db->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error deleting products: ' . $e->getMessage()]);
} 