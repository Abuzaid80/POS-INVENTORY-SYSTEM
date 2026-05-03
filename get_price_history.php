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

// Fetch price history
$query = "SELECT 
            ph.old_price,
            ph.new_price,
            ph.created_at as date,
            u.username as changed_by,
            ph.reason
          FROM price_history ph
          LEFT JOIN users u ON ph.user_id = u.id
          WHERE ph.product_id = :id
          ORDER BY ph.created_at DESC
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
                <th>Old Price</th>
                <th>New Price</th>
                <th>Change</th>
                <th>Changed By</th>
                <th>Reason</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($history)): ?>
                <tr>
                    <td colspan="6" class="text-center">No price history found</td>
                </tr>
            <?php else: ?>
                <?php foreach ($history as $record): ?>
                    <tr>
                        <td><?php echo date('M d, Y H:i', strtotime($record['date'])); ?></td>
                        <td>$<?php echo number_format($record['old_price'], 2); ?></td>
                        <td>$<?php echo number_format($record['new_price'], 2); ?></td>
                        <td>
                            <?php 
                            $change = $record['new_price'] - $record['old_price'];
                            $percentage = ($change / $record['old_price']) * 100;
                            if ($change > 0) {
                                echo '<span class="text-success">+$' . number_format($change, 2) . ' (+' . number_format($percentage, 1) . '%)</span>';
                            } else {
                                echo '<span class="text-danger">-$' . number_format(abs($change), 2) . ' (' . number_format($percentage, 1) . '%)</span>';
                            }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($record['changed_by']); ?></td>
                        <td><?php echo htmlspecialchars($record['reason']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div> 