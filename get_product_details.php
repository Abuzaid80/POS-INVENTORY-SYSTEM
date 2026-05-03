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

if (!isset($_GET['id'])) {
    echo '<div class="alert alert-danger">Product ID is required</div>';
    exit();
}

$product_id = $_GET['id'];

// Fetch product details with category name
$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE p.id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $product_id);
$stmt->execute();
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo '<div class="alert alert-danger">Product not found</div>';
    exit();
}

// Fetch recent sales
$query = "SELECT s.created_at, s.quantity, s.total_price 
          FROM sales s 
          WHERE s.product_id = :id 
          ORDER BY s.created_at DESC 
          LIMIT 5";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $product_id);
$stmt->execute();
$recent_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-body text-center">
                <?php if (!empty($product['image'])): ?>
                    <img src="uploads/products/<?php echo $product['image']; ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                         class="img-fluid mb-3" style="max-height: 200px;">
                <?php else: ?>
                    <div class="text-center mb-3">
                        <i class="fas fa-image fa-5x text-muted"></i>
                    </div>
                <?php endif; ?>
                <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                <p class="text-muted"><?php echo htmlspecialchars($product['category_name']); ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Product Information</h5>
                <table class="table">
                    <tr>
                        <th>Description:</th>
                        <td><?php echo nl2br(htmlspecialchars($product['description'])); ?></td>
                    </tr>
                    <tr>
                        <th>Price:</th>
                        <td>$<?php echo number_format($product['price'], 2); ?></td>
                    </tr>
                    <tr>
                        <th>Quantity:</th>
                        <td><?php echo $product['quantity']; ?></td>
                    </tr>
                    <tr>
                        <th>Low Stock Threshold:</th>
                        <td><?php echo $product['low_stock_threshold']; ?></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td>
                            <?php if ($product['quantity'] <= 0): ?>
                                <span class="badge bg-danger">Out of Stock</span>
                            <?php elseif ($product['quantity'] <= $product['low_stock_threshold']): ?>
                                <span class="badge bg-warning">Low Stock</span>
                            <?php else: ?>
                                <span class="badge bg-success">In Stock</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Created:</th>
                        <td><?php echo date('M d, Y H:i', strtotime($product['created_at'])); ?></td>
                    </tr>
                    <tr>
                        <th>Last Updated:</th>
                        <td><?php echo date('M d, Y H:i', strtotime($product['updated_at'])); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Recent Sales</h5>
                <?php if (empty($recent_sales)): ?>
                    <p class="text-muted">No recent sales</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Quantity</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_sales as $sale): ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i', strtotime($sale['created_at'])); ?></td>
                                    <td><?php echo $sale['quantity']; ?></td>
                                    <td>$<?php echo number_format($sale['total_price'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div> 