<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

if (!isset($_GET['customer_id'])) {
    echo '<div class="alert alert-danger">Customer ID is required</div>';
    exit();
}

$customer_id = $_GET['customer_id'];

// Fetch customer details
$query = "SELECT * FROM customers WHERE id = :customer_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':customer_id', $customer_id);
$stmt->execute();
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    echo '<div class="alert alert-danger">Customer not found</div>';
    exit();
}

// Fetch purchase history
$query = "SELECT cph.*, s.created_at as sale_date 
          FROM customer_purchase_history cph
          JOIN sales s ON cph.sale_id = s.id
          WHERE cph.customer_id = :customer_id
          ORDER BY s.created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':customer_id', $customer_id);
$stmt->execute();
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="customer-info mb-4">
    <h4><?php echo htmlspecialchars($customer['name']); ?></h4>
    <p class="text-muted">Total Loyalty Points: <span class="badge bg-success"><?php echo $customer['loyalty_points']; ?></span></p>
</div>

<?php if (empty($purchases)): ?>
    <div class="alert alert-info">No purchase history found for this customer.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Total Amount</th>
                    <th>Points Earned</th>
                    <th>Points Redeemed</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($purchases as $purchase): ?>
                <tr>
                    <td><?php echo date('M d, Y H:i', strtotime($purchase['sale_date'])); ?></td>
                    <td>$<?php echo number_format($purchase['total_amount'], 2); ?></td>
                    <td class="text-success">+<?php echo $purchase['points_earned']; ?></td>
                    <td class="text-danger">-<?php echo $purchase['points_redeemed']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?> 