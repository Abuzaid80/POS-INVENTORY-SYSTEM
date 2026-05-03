<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'config/database.php';

// Initialize error and success messages
$error = '';
$success = '';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        try {
            // Validate input
            if (empty($_POST['company_name'])) {
                throw new Exception("Company name is required");
            }
            if (empty($_POST['tax_rate'])) {
                throw new Exception("Tax rate is required");
            } elseif (!is_numeric($_POST['tax_rate']) || $_POST['tax_rate'] < 0) {
                throw new Exception("Tax rate must be a positive number");
            }
            if (empty($_POST['currency_symbol'])) {
                throw new Exception("Currency symbol is required");
            }

            // Check if settings record exists
            $check_query = "SELECT id FROM settings WHERE id = 1";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute();

            if ($check_stmt->rowCount() > 0) {
                // Update existing settings
                $query = "UPDATE settings SET 
                         company_name = :company_name,
                         company_address = :company_address,
                         company_phone = :company_phone,
                         company_email = :company_email,
                         tax_rate = :tax_rate,
                         currency_symbol = :currency_symbol,
                         updated_at = NOW()
                         WHERE id = 1";
            } else {
                // Insert new settings
                $query = "INSERT INTO settings (
                         company_name, company_address, company_phone, 
                         company_email, tax_rate, currency_symbol, 
                         created_at, updated_at
                         ) VALUES (
                         :company_name, :company_address, :company_phone,
                         :company_email, :tax_rate, :currency_symbol,
                         NOW(), NOW()
                         )";
            }

            $stmt = $db->prepare($query);
            $stmt->bindParam(':company_name', $_POST['company_name']);
            $stmt->bindParam(':company_address', $_POST['company_address']);
            $stmt->bindParam(':company_phone', $_POST['company_phone']);
            $stmt->bindParam(':company_email', $_POST['company_email']);
            $stmt->bindParam(':tax_rate', $_POST['tax_rate']);
            $stmt->bindParam(':currency_symbol', $_POST['currency_symbol']);

            if ($stmt->execute()) {
                $success = "Settings updated successfully";
            } else {
                throw new Exception("Failed to update settings");
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    // Fetch current settings
    $query = "SELECT * FROM settings WHERE id = 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    // Set default values if no settings exist
    if (!$settings) {
        $settings = [
            'company_name' => '',
            'company_address' => '',
            'company_phone' => '',
            'company_email' => '',
            'tax_rate' => 0,
            'currency_symbol' => 'Rp'
        ];
    }

} catch (PDOException $e) {
    error_log("Database Error in settings.php: " . $e->getMessage());
    $error = "Database error occurred";
} catch (Exception $e) {
    error_log("Error in settings.php: " . $e->getMessage());
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Shop Inventory System</title>
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="assets/css/sidebar.css" rel="stylesheet">
    <link href="assets/css/mobile-responsive.css" rel="stylesheet">
    
    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="assets/js/sidebar.js"></script>
    <script src="assets/js/datatables-config.js"></script>
</head>
<body>
    <!-- Mobile Menu Toggle Button -->
    <button class="mobile-menu-toggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay"></div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="sidebar-brand">POS System</a>
        </div>
        <div class="sidebar-menu">
            <div class="sidebar-item">
                <a href="index.php" class="sidebar-link">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="sidebar-item">
                <a href="products.php" class="sidebar-link">
                    <i class="fas fa-box"></i>
                    <span>Products</span>
                </a>
            </div>
            <div class="sidebar-item">
                <a href="sales.php" class="sidebar-link">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Sales</span>
                </a>
            </div>
            <div class="sidebar-item">
                <a href="customers.php" class="sidebar-link">
                    <i class="fas fa-users"></i>
                    <span>Customers</span>
                </a>
            </div>
            <div class="sidebar-item">
                <a href="reports.php" class="sidebar-link">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </div>
            <div class="sidebar-item">
                <a href="settings.php" class="sidebar-link active">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </div>
            <div class="sidebar-item mt-auto">
                <a href="logout.php" class="sidebar-link text-danger">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <h2>Settings</h2>
                </div>
            </div>

            <!-- Error and Success Messages -->
            <?php if ($error): ?>
                <div class="toast-container">
                    <div class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="toast-container">
                    <div class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo htmlspecialchars($success); ?>
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Store Settings -->
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Store Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="company_name" class="form-label">Company Name</label>
                                        <input type="text" class="form-control" id="company_name" name="company_name" 
                                               value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="company_phone" class="form-label">Company Phone</label>
                                        <input type="text" class="form-control" id="company_phone" name="company_phone" 
                                               value="<?php echo htmlspecialchars($settings['company_phone'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="company_address" class="form-label">Company Address</label>
                                    <textarea class="form-control" id="company_address" name="company_address" 
                                              rows="2"><?php echo htmlspecialchars($settings['company_address'] ?? ''); ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="company_email" class="form-label">Company Email</label>
                                        <input type="email" class="form-control" id="company_email" name="company_email" 
                                               value="<?php echo htmlspecialchars($settings['company_email'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="tax_rate" class="form-label">Tax Rate (%)</label>
                                        <input type="number" class="form-control" id="tax_rate" name="tax_rate" 
                                               value="<?php echo htmlspecialchars($settings['tax_rate'] ?? '0'); ?>" step="0.01" min="0" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="currency_symbol" class="form-label">Currency Symbol</label>
                                    <input type="text" class="form-control" id="currency_symbol" name="currency_symbol" 
                                           value="<?php echo htmlspecialchars($settings['currency_symbol'] ?? 'Rp'); ?>" required>
                                </div>

                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/sidebar.js"></script>
</body>
</html> 