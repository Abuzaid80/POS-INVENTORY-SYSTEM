<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - Shop Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container {
            max-width: 500px;
            width: 90%;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .error-icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 1rem;
        }
        .back-button {
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <i class="fas fa-exclamation-circle error-icon"></i>
        <h2 class="mb-4">Access Denied</h2>
        <p class="text-muted mb-4">Sorry, you don't have permission to access this page.</p>
        
        <?php if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'limited_admin'): ?>
            <a href="products.php" class="btn btn-primary back-button">
                <i class="fas fa-arrow-left me-2"></i>Go to Products
            </a>
        <?php elseif (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'report_viewer'): ?>
            <a href="reports.php" class="btn btn-primary back-button">
                <i class="fas fa-arrow-left me-2"></i>Go to Reports
            </a>
        <?php else: ?>
            <a href="index.php" class="btn btn-primary back-button">
                <i class="fas fa-arrow-left me-2"></i>Go to Dashboard
            </a>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 