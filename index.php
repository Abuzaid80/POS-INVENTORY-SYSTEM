<?php
session_start();
require_once 'config/database.php';
require_once 'includes/check_permission.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login.php');
    exit();
}

// Block limited_admin from accessing index.php
if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'limited_admin') {
    header('Location: access_denied.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Fetch data for charts
$sales_query = "SELECT 
                    DATE(created_at) as date, 
                    SUM(total_amount) as amount,
                    COUNT(*) as transaction_count,
                    AVG(total_amount) as average_sale
                FROM sales 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date";
$sales_stmt = $db->prepare($sales_query);
$sales_stmt->execute();
$sales_data = $sales_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch top products with more details
$products_query = "SELECT 
                      p.name, 
                      COUNT(DISTINCT CASE WHEN si.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN si.id END) as sale_count,
                      COALESCE(SUM(CASE WHEN si.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN si.quantity ELSE 0 END), 0) as total_quantity,
                      COALESCE(SUM(CASE WHEN si.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN si.quantity * si.price ELSE 0 END), 0) as total_revenue,
                      p.quantity as current_stock,
                      p.price as unit_price
                  FROM products p 
                  LEFT JOIN sale_items si ON p.id = si.product_id 
                  GROUP BY p.id, p.name, p.quantity, p.price 
                  ORDER BY total_revenue DESC 
                  LIMIT 10";
$products_stmt = $db->prepare($products_query);
$products_stmt->execute();
$top_products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch category performance
$category_query = "SELECT 
                      c.name as category_name,
                      COUNT(DISTINCT s.id) as sale_count,
                      SUM(si.quantity) as items_sold,
                      SUM(si.quantity * si.price) as revenue
                  FROM categories c
                  JOIN products p ON c.id = p.category_id
                  JOIN sale_items si ON p.id = si.product_id
                  JOIN sales s ON si.sale_id = s.id
                  WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  GROUP BY c.id
                  ORDER BY revenue DESC
                  LIMIT 5";
$category_stmt = $db->prepare($category_query);
$category_stmt->execute();
$category_data = $category_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch sales summary
$summary_query = "SELECT 
                  COUNT(*) as total_sales,
                  SUM(total_amount) as total_revenue,
                  SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END) as cash_sales,
                  SUM(CASE WHEN payment_method = 'credit_card' THEN total_amount ELSE 0 END) as card_sales,
                  SUM(CASE WHEN payment_method = 'bank_transfer' THEN total_amount ELSE 0 END) as bank_sales,
                  SUM(CASE WHEN payment_method = 'gcash' THEN total_amount ELSE 0 END) as gcash_sales,
                  SUM(CASE WHEN payment_method = 'maya' THEN total_amount ELSE 0 END) as maya_sales
                  FROM sales
                  WHERE DATE(created_at) = CURDATE()";
$summary_stmt = $db->prepare($summary_query);
$summary_stmt->execute();
$sales_summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);

// Prepare data for sales pie chart
$sales_by_method = [
    'Cash' => $sales_summary['cash_sales'] ?? 0,
    'Credit Card' => $sales_summary['card_sales'] ?? 0,
    'Bank Transfer' => $sales_summary['bank_sales'] ?? 0,
    'GCash' => $sales_summary['gcash_sales'] ?? 0,
    'Maya' => $sales_summary['maya_sales'] ?? 0
];

$sales_method_labels = array_keys($sales_by_method);
$sales_method_values = array_values($sales_by_method);

// Calculate total sales and growth
$total_sales = array_sum(array_column($sales_data, 'amount'));
$total_transactions = array_sum(array_column($sales_data, 'transaction_count'));
$average_transaction = $total_transactions > 0 ? $total_sales / $total_transactions : 0;

// Prepare data for Chart.js
$sales_labels = array_map(function($date) {
    return date('M d', strtotime($date));
}, array_column($sales_data, 'date'));

$sales_values = array_column($sales_data, 'amount');
$transaction_counts = array_column($sales_data, 'transaction_count');
$average_sales = array_column($sales_data, 'average_sale');

// Prepare data for product chart
$product_names = array_column($top_products, 'name');
$product_sales = array_column($top_products, 'total_revenue');
$product_quantities = array_column($top_products, 'total_quantity');
$product_stock = array_column($top_products, 'current_stock');
$product_prices = array_column($top_products, 'unit_price');

$category_labels = array_column($category_data, 'category_name');
$category_revenues = array_column($category_data, 'revenue');
$category_items = array_column($category_data, 'items_sold');

// Calculate percentage changes
$days_to_compare = min(count($sales_values), 30);
if ($days_to_compare >= 2) {
    $half_days = (int)($days_to_compare/2);
    $recent_sales = array_slice($sales_values, -$half_days);
    $previous_sales = array_slice($sales_values, 0, $half_days);
    $sales_growth = (array_sum($recent_sales) - array_sum($previous_sales)) / array_sum($previous_sales) * 100;
} else {
    $sales_growth = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Shop Inventory System</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    
    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        /* Sidebar base styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100%;
            background-color: #2c3e50;
            color: #fff;
            z-index: 1050;
            transition: transform 0.3s ease;
        }

        .sidebar-header {
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: #243442;
        }

        .sidebar-brand {
            color: #fff;
            text-decoration: none;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
        }

        .sidebar-brand i {
            margin-right: 0.5rem;
        }

        .sidebar-menu {
            padding: 1rem 0;
            list-style: none;
            margin: 0;
        }

        .sidebar-item {
            margin: 0.25rem 0;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: #fff;
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .sidebar-link i {
            margin-right: 0.75rem;
            width: 1.25rem;
            text-align: center;
        }

        .sidebar-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        .sidebar-link.active {
            background-color: #3498db;
        }

        /* Mobile menu toggle button */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1060;
            background-color: #2c3e50;
            border: none;
            color: #fff;
            padding: 0.75rem;
            border-radius: 0.25rem;
            cursor: pointer;
        }

        /* Close button */
        .btn-close-custom {
            background: none;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            padding: 0.5rem;
            cursor: pointer;
            display: none;
        }

        /* Overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1040;
        }

        /* Mobile styles */
        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
            }

            .btn-close-custom {
                display: block;
            }

            .sidebar {
                transform: translateX(-100%);
                box-shadow: 2px 0 8px rgba(0, 0, 0, 0.15);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .sidebar-overlay.active {
                display: block;
            }

            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
                padding-top: 4rem !important;
            }
        }

        /* Small mobile devices */
        @media (max-width: 576px) {
            .sidebar {
                width: 100%;
                max-width: 280px;
            }
        }

        /* Main Content Styles */
        .main-content {
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
            background-color: #f8f9fa;
            transition: all 0.3s ease;
            width: calc(100% - 280px);
        }

        .container-fluid {
            padding-right: 30px;
            padding-left: 30px;
            width: 100%;
            margin-right: auto;
            margin-left: auto;
        }
        /* DataTables Custom Styles */
        .dataTables_wrapper .dataTables_length select {
            display: inline-block;
            width: auto;
            margin-right: 10px;
        }
        .dataTables_wrapper .dataTables_filter input {
            display: inline-block;
            width: auto;
            margin-left: 10px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.5em 0.75em;
        }
        .dataTables_wrapper .dataTables_info {
            padding-top: 0.85em;
        }
        #activityTable thead {
            background-color: #007bff;
            color: white;
        }
        #activityTable thead th {
            color: white;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.2rem;
        }
        .badge {
            padding: 0.35em 0.65em;
            font-size: 0.75em;
        }
        /* Chart Container */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        /* Card Styles */
        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.25rem;
        }
        .border-left-primary {
            border-left: 4px solid #4e73df !important;
        }
        .border-left-success {
            border-left: 4px solid #1cc88a !important;
        }
        .border-left-info {
            border-left: 4px solid #36b9cc !important;
        }
        .border-left-warning {
            border-left: 4px solid #f6c23e !important;
        }
        .text-xs {
            font-size: .7rem;
        }
        .text-gray-300 {
            color: #dddfeb!important;
        }
        .text-gray-800 {
            color: #5a5c69!important;
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
            }

            .sidebar {
                transform: translateX(-100%);
                width: 100%;
                max-width: 280px;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 15px !important;
            }

            .container-fluid {
                padding-right: 10px !important;
                padding-left: 10px !important;
            }

            .card {
                margin-bottom: 1rem;
            }

            /* Improve table responsiveness */
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            /* Make charts more visible on mobile */
            .chart-container {
                height: 300px !important;
                margin-bottom: 1rem;
            }

            /* Adjust card padding for mobile */
            .card-body {
                padding: 0.75rem;
            }

            /* Make buttons more touch-friendly */
            .btn {
                padding: 0.5rem 1rem;
                margin: 0.2rem;
            }

            .btn-group {
                display: flex;
                flex-wrap: wrap;
                gap: 0.25rem;
            }

            .btn-group .btn {
                flex: 1;
                white-space: nowrap;
            }

            /* Improve form elements on mobile */
            input, select, textarea {
                font-size: 16px !important; /* Prevents zoom on iOS */
            }

            /* Adjust stats cards for mobile */
            .col-xl-3 {
                margin-bottom: 1rem;
            }

            /* Improve DataTables on mobile */
            .dataTables_wrapper {
                padding: 0;
            }

            .dataTables_filter {
                margin-bottom: 1rem;
            }

            .dataTables_length select {
                width: 80px !important;
            }

            /* Add overlay when sidebar is active */
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }

            .sidebar-overlay.active {
                display: block;
            }

            /* Adjust chart legends for mobile */
            .chart-js-legend {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 0.5rem;
                margin-top: 1rem;
            }

            /* Make tables scrollable horizontally */
            .table {
                min-width: 600px;
            }

            /* Adjust font sizes for mobile */
            h2 {
                font-size: 1.5rem;
            }

            h5 {
                font-size: 1.1rem;
            }

            .text-xs {
                font-size: 0.65rem;
            }

            /* Improve chart tooltips on mobile */
            .chartjs-tooltip {
                max-width: 200px;
                white-space: normal;
            }
        }

        /* Additional mobile optimizations */
        @media (max-width: 576px) {
            .main-content {
                padding: 10px !important;
            }

            .container-fluid {
                padding-right: 5px !important;
                padding-left: 5px !important;
            }

            .card-body {
                padding: 0.5rem;
            }

            /* Stack buttons vertically on very small screens */
            .btn-group {
                flex-direction: column;
            }

            .btn-group .btn {
                width: 100%;
                margin: 0.1rem 0;
            }

            /* Adjust chart heights for very small screens */
            .chart-container {
                height: 250px !important;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay"></div>

    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="sidebar-brand">
                <i class="fas fa-store"></i>
                <span>Shop Inventory</span>
            </a>
        </div>
        <ul class="sidebar-menu">
            <li class="sidebar-item">
                <a href="index.php" class="sidebar-link">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="products.php" class="sidebar-link">
                    <i class="fas fa-box"></i>
                    <span>Products</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="categories.php" class="sidebar-link">
                    <i class="fas fa-tags"></i>
                    <span>Categories</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="sales.php" class="sidebar-link">
                    <i class="fas fa-chart-line"></i>
                    <span>Sales</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="reports.php" class="sidebar-link">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="settings.php" class="sidebar-link">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
            <li class="sidebar-item mt-auto">
                <a href="logout.php" class="sidebar-link text-danger">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="row mb-4">
            <div class="col-12">
                <h2>Dashboard Overview</h2>
            </div>
        </div>

        <!-- Quick Stats Cards -->
        <div class="row g-2 mb-4">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-left-primary h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Today's Sales</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">Rp <?php echo number_format(array_sum($sales_values), 2); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-left-success h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Total Products</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($product_names); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-box fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-left-info h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Weekly Sales</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">Rp <?php echo number_format(array_sum($sales_values), 2); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-left-warning h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Top Products</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($top_products); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-star fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-2">
            <!-- Sales Trend Chart -->
            <div class="col-12 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
                        <h5 class="card-title mb-0">Sales Trend</h5>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary active" data-range="week">Week</button>
                            <button type="button" class="btn btn-outline-primary" data-range="month">Month</button>
                            <button type="button" class="btn btn-outline-primary" data-range="year">Year</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-options mb-3 d-flex flex-wrap gap-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="showAmount" checked>
                                <label class="form-check-label" for="showAmount">Amount</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="showTransactions">
                                <label class="form-check-label" for="showTransactions">Transactions</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="showAverage">
                                <label class="form-check-label" for="showAverage">Average</label>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="salesTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category Performance -->
            <div class="col-12 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
                        <h5 class="card-title mb-0">Category Performance</h5>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary active" data-view="revenue">Revenue</button>
                            <button type="button" class="btn btn-outline-primary" data-view="items">Items</button>
                            <button type="button" class="btn btn-outline-primary" data-view="growth">Growth</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales Distribution -->
            <div class="col-12 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Sales Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="salesPieChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product Performance Row -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
                        <h5 class="card-title mb-0">Product Performance</h5>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary active" data-product-view="sales">Sales</button>
                            <button type="button" class="btn btn-outline-primary" data-product-view="quantity">Quantity</button>
                            <button type="button" class="btn btn-outline-primary" data-product-view="stock">Stock</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="productChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Table -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Recent Activity</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover" id="activityTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales_data as $sale): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($sale['date'])); ?></td>
                                <td>Sale</td>
                                <td>Daily Sales</td>
                                <td>Rp <?php echo number_format($sale['amount'], 2); ?></td>
                                <td><span class="badge bg-success">Completed</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sidebar.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Utility function for formatting currency
            function formatCurrency(value) {
                return new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 2
                }).format(value);
            }

            // Initialize Sales Trend Chart
            const salesTrendCtx = document.getElementById('salesTrendChart');
            if (salesTrendCtx) {
                const salesTrendChart = new Chart(salesTrendCtx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($sales_labels); ?>,
                        datasets: [
                            {
                                label: 'Sales Amount',
                                data: <?php echo json_encode($sales_values); ?>,
                                borderColor: '#4e73df',
                                backgroundColor: 'rgba(78, 115, 223, 0.1)',
                                tension: 0.3,
                                fill: true
                            },
                            {
                                label: 'Transactions',
                                data: <?php echo json_encode($transaction_counts); ?>,
                                borderColor: '#1cc88a',
                                backgroundColor: 'rgba(28, 200, 138, 0.1)',
                                tension: 0.3,
                                fill: true,
                                hidden: true
                            },
                            {
                                label: 'Average Sale',
                                data: <?php echo json_encode($average_sales); ?>,
                                borderColor: '#36b9cc',
                                backgroundColor: 'rgba(54, 185, 204, 0.1)',
                                tension: 0.3,
                                fill: true,
                                hidden: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.dataset.label === 'Transactions') {
                                            label += context.parsed.y.toLocaleString();
                                        } else {
                                            label += formatCurrency(context.parsed.y);
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return formatCurrency(value);
                                    }
                                }
                            }
                        }
                    }
                });

                // Handle time range selection
                document.querySelectorAll('[data-range]').forEach(button => {
                    button.addEventListener('click', function() {
                        document.querySelectorAll('[data-range]').forEach(btn => btn.classList.remove('active'));
                        this.classList.add('active');
                        const range = this.dataset.range;
                        updateChartRange(range, salesTrendChart);
                    });
                });

                // Handle dataset toggles
                document.getElementById('showAmount').addEventListener('change', function() {
                    salesTrendChart.data.datasets[0].hidden = !this.checked;
                    salesTrendChart.update();
                });

                document.getElementById('showTransactions').addEventListener('change', function() {
                    salesTrendChart.data.datasets[1].hidden = !this.checked;
                    salesTrendChart.update();
                });

                document.getElementById('showAverage').addEventListener('change', function() {
                    salesTrendChart.data.datasets[2].hidden = !this.checked;
                    salesTrendChart.update();
                });
            }

            // Initialize Category Chart
            const categoryCtx = document.getElementById('categoryChart');
            if (categoryCtx) {
                const categoryChart = new Chart(categoryCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode($category_labels); ?>,
                        datasets: [{
                            data: <?php echo json_encode($category_revenues); ?>,
                            backgroundColor: [
                                '#3498db',
                                '#2ecc71',
                                '#f1c40f',
                                '#e74c3c',
                                '#9b59b6'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    boxWidth: 12,
                                    padding: 20,
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        let value = context.raw || 0;
                                        let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        let percentage = ((value / total) * 100).toFixed(1);
                                        return `${label}: ${formatCurrency(value)} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });

                // Handle category view toggle
                document.querySelectorAll('.btn-group [data-view]').forEach(button => {
                    button.addEventListener('click', function() {
                        document.querySelectorAll('[data-view]').forEach(btn => btn.classList.remove('active'));
                        this.classList.add('active');
                        const view = this.dataset.view;
                        updateCategoryView(view, categoryChart);
                    });
                });
            }

            // Initialize Sales Distribution Chart
            const salesPieCtx = document.getElementById('salesPieChart');
            if (salesPieCtx) {
                const salesPieChart = new Chart(salesPieCtx, {
                    type: 'pie',
                    data: {
                        labels: <?php echo json_encode($sales_method_labels); ?>,
                        datasets: [{
                            data: <?php echo json_encode($sales_method_values); ?>,
                            backgroundColor: [
                                '#2ecc71',  // Cash - Green
                                '#3498db',  // Credit Card - Blue
                                '#9b59b6',  // Bank Transfer - Purple
                                '#e74c3c',  // GCash - Red
                                '#f1c40f'   // Maya - Yellow
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    boxWidth: 12,
                                    padding: 20,
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        let value = context.raw || 0;
                                        let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                        return `${label}: ${formatCurrency(value)} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Initialize Product Chart
            const productCtx = document.getElementById('productChart');
            if (productCtx) {
                const productChart = new Chart(productCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($product_names); ?>,
                        datasets: [{
                            label: 'Sales Revenue',
                            data: <?php echo json_encode($product_sales); ?>,
                            backgroundColor: 'rgba(78, 115, 223, 0.8)',
                            borderColor: 'rgba(78, 115, 223, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.dataset.label === 'Sales Revenue') {
                                            label += formatCurrency(context.raw);
                                        } else {
                                            label += context.raw.toLocaleString();
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        if (productChart.data.datasets[0].label === 'Sales Revenue') {
                                            return formatCurrency(value);
                                        }
                                        return value.toLocaleString();
                                    }
                                }
                            },
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });

                // Handle product view toggle
                document.querySelectorAll('[data-product-view]').forEach(button => {
                    button.addEventListener('click', function() {
                        document.querySelectorAll('[data-product-view]').forEach(btn => btn.classList.remove('active'));
                        this.classList.add('active');
                        const view = this.dataset.productView;
                        
                        let data, label, color;
                        switch(view) {
                            case 'sales':
                                data = <?php echo json_encode($product_sales); ?>;
                                label = 'Sales Revenue';
                                color = 'rgba(78, 115, 223, 0.8)';
                                break;
                            case 'quantity':
                                data = <?php echo json_encode($product_quantities); ?>;
                                label = 'Quantity Sold';
                                color = 'rgba(28, 200, 138, 0.8)';
                                break;
                            case 'stock':
                                data = <?php echo json_encode($product_stock); ?>;
                                label = 'Current Stock';
                                color = 'rgba(246, 194, 62, 0.8)';
                                break;
                        }

                        productChart.data.datasets[0].data = data;
                        productChart.data.datasets[0].label = label;
                        productChart.data.datasets[0].backgroundColor = color;
                        productChart.data.datasets[0].borderColor = color.replace('0.8', '1');
                        
                        productChart.options.scales.x.ticks.callback = function(value) {
                            if (view === 'sales') {
                                return formatCurrency(value);
                            }
                            return value.toLocaleString();
                        };
                        
                        productChart.update();
                    });
                });
            }

            // Function to update chart range
            function updateChartRange(range, chart) {
                let newData;
                const fullData = chart.data.datasets[0].data;
                switch(range) {
                    case 'week':
                        newData = fullData.slice(-7);
                        break;
                    case 'month':
                        newData = fullData.slice(-30);
                        break;
                    case 'year':
                        newData = fullData;
                        break;
                }
                
                chart.data.labels = chart.data.labels.slice(-newData.length);
                chart.data.datasets.forEach((dataset, i) => {
                    dataset.data = chart.data.datasets[i].data.slice(-newData.length);
                });
                
                chart.update();
            }

            // Function to update category view
            function updateCategoryView(view, chart) {
                let data, tooltipCallback;
                switch(view) {
                    case 'revenue':
                        data = <?php echo json_encode($category_revenues); ?>;
                        tooltipCallback = (value) => formatCurrency(value);
                        break;
                    case 'items':
                        data = <?php echo json_encode($category_items); ?>;
                        tooltipCallback = (value) => value.toLocaleString() + ' items';
                        break;
                    case 'growth':
                        data = <?php echo json_encode(array_column($category_data, 'growth', 'category_name')); ?>;
                        tooltipCallback = (value) => value.toFixed(1) + '%';
                        break;
                }
                
                chart.data.datasets[0].data = data;
                chart.options.plugins.tooltip.callbacks.label = function(context) {
                    let label = context.label || '';
                    let value = context.raw || 0;
                    return `${label}: ${tooltipCallback(value)}`;
                };
                
                chart.update();
            }

            const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
            const closeButton = document.querySelector('.btn-close-custom');
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            const mainContent = document.querySelector('.main-content');

            function openSidebar() {
                sidebar.classList.add('active');
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }

            function closeSidebar() {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }

            mobileMenuToggle.addEventListener('click', openSidebar);
            closeButton.addEventListener('click', closeSidebar);
            overlay.addEventListener('click', closeSidebar);

            // Close sidebar when clicking menu items on mobile
            document.querySelectorAll('.sidebar-link').forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        closeSidebar();
                    }
                });
            });

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    closeSidebar();
                }
            });
        });
    </script>
</body>
</html> 