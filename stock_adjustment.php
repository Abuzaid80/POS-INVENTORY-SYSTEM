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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_id = $_POST['product_id'];
    $quantity = $_POST['quantity'];
    $reason = $_POST['reason'];
    $user_id = $_SESSION['user_id'];

    try {
        $db->beginTransaction();

        // Insert stock adjustment record
        $query = "INSERT INTO stock_adjustments (product_id, quantity, reason, user_id) 
                 VALUES (:product_id, :quantity, :reason, :user_id)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':product_id', $product_id);
        $stmt->bindParam(':quantity', $quantity);
        $stmt->bindParam(':reason', $reason);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        // Update product quantity
        $query = "UPDATE products SET quantity = quantity + :quantity WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':quantity', $quantity);
        $stmt->bindParam(':id', $product_id);
        $stmt->execute();

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Stock adjustment successful']);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// Fetch product details for the form
if (isset($_GET['id'])) {
    $product_id = $_GET['id'];
    $query = "SELECT id, name, quantity FROM products WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $product_id);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="modal fade" id="stockAdjustmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Stock Adjustment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="stockAdjustmentForm">
                <div class="modal-body">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    <div class="mb-3">
                        <label class="form-label">Product</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($product['name']); ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Current Quantity</label>
                        <input type="text" class="form-control" value="<?php echo $product['quantity']; ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adjustment Quantity</label>
                        <input type="number" name="quantity" class="form-control" required>
                        <small class="text-muted">Use positive numbers to add stock, negative to remove stock</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea name="reason" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('stockAdjustmentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('stock_adjustment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Stock adjustment successful');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error);
    });
});
</script> 