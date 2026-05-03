<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!isset($_POST['sale_id'])) {
        throw new Exception('Sale ID is required');
    }
    
    $sale_id = intval($_POST['sale_id']);
    
    // Get sale details with customer and items
    $query = "SELECT s.*, 
              c.name as customer_name,
              GROUP_CONCAT(DISTINCT p.name ORDER BY si.id SEPARATOR '||') as products,
              GROUP_CONCAT(DISTINCT si.quantity ORDER BY si.id SEPARATOR '||') as quantities,
              GROUP_CONCAT(DISTINCT si.price ORDER BY si.id SEPARATOR '||') as prices
              FROM sales s
              LEFT JOIN customers c ON s.customer_id = c.id
              LEFT JOIN sale_items si ON s.id = si.sale_id
              LEFT JOIN products p ON si.product_id = p.id
              WHERE s.id = :sale_id
              GROUP BY s.id, s.customer_id, s.total_amount, s.payment_method, s.notes, s.created_at, c.name";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':sale_id', $sale_id);
    $stmt->execute();
    
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sale) {
        throw new Exception('Sale not found');
    }
    
    // Add printed by information
    $sale['printed_by'] = $_SESSION['username'] ?? 'System User';
    
    echo json_encode([
        'success' => true,
        'data' => $sale
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 