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

// Fetch stock movement history
$query = "SELECT 
            'Sale' as type,
            s.created_at as date,
            -s.quantity as quantity,
            s.total_price as total,
            'Sale #' || s.id as reference
          FROM sales s
          WHERE s.product_id = :id
          UNION ALL
          SELECT 
            'Stock Adjustment' as type,
            sa.created_at as date,
            sa.quantity as quantity,
            NULL as total,
            sa.reason as reference
          FROM stock_adjustments sa
          WHERE sa.product_id = :id
          ORDER BY date DESC
          LIMIT 50";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $product_id);
$stmt->execute();
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="table-responsive">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Quantity</th>
                <th>Total</th>
                <th>Reference</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($history)): ?>
                <tr>
                    <td colspan="5" class="text-center">No stock movement history found</td>
                </tr>
            <?php else: ?>
                <?php foreach ($history as $record): ?>
                    <tr>
                        <td><?php echo date('M d, Y H:i', strtotime($record['date'])); ?></td>
                        <td>
                            <?php if ($record['type'] == 'Sale'): ?>
                                <span class="badge bg-danger">Sale</span>
                            <?php else: ?>
                                <span class="badge bg-info">Adjustment</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($record['quantity'] < 0): ?>
                                <span class="text-danger"><?php echo $record['quantity']; ?></span>
                            <?php else: ?>
                                <span class="text-success">+<?php echo $record['quantity']; ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($record['total'] !== null): ?>
                                $<?php echo number_format($record['total'], 2); ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($record['reference']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div> 