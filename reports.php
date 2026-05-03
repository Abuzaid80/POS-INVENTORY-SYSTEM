<?php
session_start();
require_once 'config/database.php';
require_once 'includes/check_permission.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug session variables
echo "<!-- Debug Info:
User ID: " . ($_SESSION['user_id'] ?? 'not set') . "
Username: " . ($_SESSION['username'] ?? 'not set') . "
Role Name: " . ($_SESSION['role_name'] ?? 'not set') . "
Permissions: " . (isset($_SESSION['permissions']) ? implode(', ', $_SESSION['permissions']) : 'not set') . "
-->";

// Ensure user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login.php');
    exit();
}

// Block limited_admin from accessing reports
if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'limited_admin') {
    header('Location: access_denied.php');
    exit();
}

// Allow report_viewer role or check for manage_reports permission
if (!isReportViewer() && !in_array('manage_reports', $_SESSION['permissions'])) {
    header('Location: access_denied.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get date range from request or use default (last 30 days)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

try {
    // Debug query parameters
    echo '<div class="alert alert-info" style="display: none;">
        Query Parameters:<br>
        Start Date: ' . $start_date . '<br>
        End Date: ' . $end_date . '
    </div>';

    // Fetch sales summary
    $query = "SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_sales,
                COALESCE(AVG(total_amount), 0) as average_order,
                COALESCE(MAX(total_amount), 0) as highest_sale,
                COALESCE(MIN(NULLIF(total_amount, 0)), 0) as lowest_sale
              FROM sales 
              WHERE DATE(created_at) BETWEEN :start_date AND :end_date";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    
    // Debug query results
    $sales_summary = $stmt->fetch(PDO::FETCH_ASSOC);
    echo '<div class="alert alert-info" style="display: none;">
        Sales Summary Results:<br>
        ' . print_r($sales_summary, true) . '
    </div>';

    // Fetch daily sales data for chart
    $query = "SELECT 
                DATE(created_at) as date,
                COUNT(*) as order_count,
                COALESCE(SUM(total_amount), 0) as daily_sales,
                COALESCE(AVG(total_amount), 0) as average_sale
              FROM sales 
              WHERE DATE(created_at) BETWEEN :start_date AND :end_date
              GROUP BY DATE(created_at)
              ORDER BY date";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $daily_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch top products with detailed metrics
    $query = "SELECT 
                p.name,
                p.quantity as current_stock,
                COUNT(DISTINCT s.id) as order_count,
                COALESCE(SUM(si.quantity), 0) as total_quantity,
                COALESCE(SUM(si.quantity * si.price), 0) as total_revenue,
                COALESCE(AVG(si.price), 0) as average_price
              FROM products p
              LEFT JOIN sale_items si ON p.id = si.product_id
              LEFT JOIN sales s ON si.sale_id = s.id 
                AND DATE(s.created_at) BETWEEN :start_date AND :end_date
              GROUP BY p.id, p.name, p.quantity
              HAVING total_revenue > 0
              ORDER BY total_revenue DESC
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch category performance
    $query = "SELECT 
                c.name as category_name,
                COUNT(DISTINCT s.id) as order_count,
                COALESCE(SUM(si.quantity), 0) as total_quantity,
                COALESCE(SUM(si.quantity * si.price), 0) as total_revenue,
                COUNT(DISTINCT p.id) as product_count
              FROM categories c
              LEFT JOIN products p ON c.id = p.category_id
              LEFT JOIN sale_items si ON p.id = si.product_id
              LEFT JOIN sales s ON si.sale_id = s.id 
                AND DATE(s.created_at) BETWEEN :start_date AND :end_date
              GROUP BY c.id, c.name
              ORDER BY total_revenue DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $category_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch low stock products
    $query = "SELECT 
                p.name,
                COALESCE(p.quantity, 0) as current_stock,
                p.low_stock_threshold,
                c.name as category_name
              FROM products p
              LEFT JOIN categories c ON p.category_id = c.id
              WHERE COALESCE(p.quantity, 0) <= COALESCE(p.low_stock_threshold, 0)
              ORDER BY p.quantity ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $low_stock_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare chart data
    $chart_labels = array_map(function($sale) {
        return date('M d', strtotime($sale['date']));
    }, $daily_sales);

    $chart_sales = array_map(function($sale) {
        return floatval($sale['daily_sales']);
    }, $daily_sales);

    $chart_orders = array_map(function($sale) {
        return intval($sale['order_count']);
    }, $daily_sales);

    $chart_averages = array_map(function($sale) {
        return floatval($sale['average_sale']);
    }, $daily_sales);

} catch (PDOException $e) {
    error_log("Error in reports.php: " . $e->getMessage());
    $error_message = "An error occurred while generating the reports. Please try again later.";
    echo '<div class="alert alert-danger">' . $error_message . '</div>';
}

// Add JavaScript error handling for charts
echo '<script>
window.onerror = function(msg, url, lineNo, columnNo, error) {
    console.error("Error: " + msg + "\nURL: " + url + "\nLine: " + lineNo + "\nColumn: " + columnNo + "\nError object: " + JSON.stringify(error));
    return false;
};
</script>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Shop Inventory System</title>
    
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <a href="<?php echo isReportViewer() ? 'reports.php' : 'index.php'; ?>" class="sidebar-brand">
                <i class="fas fa-cash-register"></i>SHOP System
            </a>
        </div>
        <div class="sidebar-menu">
            <?php if (!isReportViewer()): ?>
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
            <?php endif; ?>
            <div class="sidebar-item">
                <a href="reports.php" class="sidebar-link active">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </div>
            <?php if (!isReportViewer()): ?>
            <div class="sidebar-item">
                <a href="settings.php" class="sidebar-link">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </div>
            <?php endif; ?>
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
            <!-- Notification Container -->
            <div class="notification-container"></div>

            <div class="row mb-4">
                <div class="col">
                    <h2>Reports</h2>
                </div>
            </div>

            <!-- Date Range Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form id="dateRangeForm" class="row g-3 align-items-center">
                        <div class="col-md-4">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary d-block">Apply Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sales Trend Chart -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Sales Trend</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height:400px;">
                        <canvas id="salesTrendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Sales Table -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Sales Details</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="salesTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Products</th>
                                    <th>Total Amount</th>
                                    <th>Payment Method</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Fetch detailed sales data
                                $query = "SELECT s.*, c.name as customer_name,
                                         GROUP_CONCAT(CONCAT(p.name, ' (', si.quantity, ')') SEPARATOR ', ') as products
                                         FROM sales s
                                         LEFT JOIN customers c ON s.customer_id = c.id
                                         LEFT JOIN sale_items si ON s.id = si.sale_id
                                         LEFT JOIN products p ON si.product_id = p.id
                                         WHERE DATE(s.created_at) BETWEEN :start_date AND :end_date
                                         GROUP BY s.id
                                         ORDER BY s.created_at DESC";
                                $stmt = $db->prepare($query);
                                $stmt->bindParam(':start_date', $start_date);
                                $stmt->bindParam(':end_date', $end_date);
                                $stmt->execute();
                                $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                foreach ($sales as $sale): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y H:i', strtotime($sale['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer'); ?></td>
                                        <td><?php echo htmlspecialchars($sale['products'] ?? ''); ?></td>
                                        <td>Rp <?php echo number_format($sale['total_amount'] ?? 0, 2); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($sale['payment_method']) {
                                                    'cash' => 'success',
                                                    'credit_card' => 'info',
                                                    'bank_transfer' => 'primary',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $sale['payment_method'])); ?>
                                            </span>
                                        </td>
                                        <td><span class="badge bg-success">Completed</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card stats-card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-2">Total Orders</h6>
                                    <h3 class="mb-0 fw-bold"><?php echo number_format($sales_summary['total_orders'] ?? 0); ?></h3>
                                </div>
                                <i class="fas fa-shopping-cart stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-2">Total Sales</h6>
                                    <h3 class="mb-0 fw-bold">Rp <?php echo number_format($sales_summary['total_sales'] ?? 0, 2); ?></h3>
                                </div>
                                <i class="fas fa-money-bill-wave stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-2">Average Order</h6>
                                    <h3 class="mb-0 fw-bold">Rp <?php echo number_format($sales_summary['average_order'] ?? 0, 2); ?></h3>
                                </div>
                                <i class="fas fa-chart-line stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="row">
                <!-- Top Products Chart -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Top Products</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="topProductsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Mobile menu toggle functionality
            const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const sidebarOverlay = document.querySelector('.sidebar-overlay');

            function toggleSidebar() {
                sidebar.classList.toggle('active');
                mainContent.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
            }

            mobileMenuToggle.addEventListener('click', toggleSidebar);
            sidebarOverlay.addEventListener('click', toggleSidebar);

            // Close sidebar when clicking a link on mobile
            document.querySelectorAll('.sidebar-link').forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        toggleSidebar();
                    }
                });
            });

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('active');
                    mainContent.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                }
            });

            // Initialize Sales Trend Chart
            console.log('Initializing Sales Trend Chart...');
            console.log('Chart Data:', {
                labels: <?php echo json_encode($chart_labels); ?>,
                sales: <?php echo json_encode($chart_sales); ?>,
                orders: <?php echo json_encode($chart_orders); ?>,
                averages: <?php echo json_encode($chart_averages); ?>
            });

            const salesTrendCtx = document.getElementById('salesTrendChart');
            if (!salesTrendCtx) {
                console.error('Sales Trend Chart canvas not found!');
            } else {
                try {
                    const salesTrendChart = new Chart(salesTrendCtx, {
                        type: 'line',
                        data: {
                            labels: <?php echo json_encode($chart_labels); ?>,
                            datasets: [
                                {
                                    label: 'Daily Sales (Rp)',
                                    data: <?php echo json_encode($chart_sales); ?>,
                                    borderColor: '#3498db',
                                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                                    borderWidth: 2,
                                    fill: true,
                                    tension: 0.4
                                },
                                {
                                    label: 'Number of Orders',
                                    data: <?php echo json_encode($chart_orders); ?>,
                                    borderColor: '#2ecc71',
                                    backgroundColor: 'rgba(46, 204, 113, 0.1)',
                                    borderWidth: 2,
                                    fill: true,
                                    tension: 0.4,
                                    yAxisID: 'y1'
                                },
                                {
                                    label: 'Average Order Value (Rp)',
                                    data: <?php echo json_encode($chart_averages); ?>,
                                    borderColor: '#e74c3c',
                                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                                    borderWidth: 2,
                                    fill: true,
                                    tension: 0.4,
                                    yAxisID: 'y2'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                intersect: false,
                                mode: 'index'
                            },
                            plugins: {
                                legend: {
                                    position: 'top',
                                    labels: {
                                        usePointStyle: true,
                                        padding: 20
                                    }
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
                                            if (context.parsed.y !== null) {
                                                if (label.includes('Rp')) {
                                                    label += new Intl.NumberFormat('id-ID', {
                                                        style: 'currency',
                                                        currency: 'IDR'
                                                    }).format(context.parsed.y);
                                                } else {
                                                    label += context.parsed.y;
                                                }
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
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    title: {
                                        display: true,
                                        text: 'Daily Sales (Rp)'
                                    },
                                    ticks: {
                                        callback: function(value) {
                                            return new Intl.NumberFormat('id-ID', {
                                                style: 'currency',
                                                currency: 'IDR',
                                                maximumFractionDigits: 0
                                            }).format(value);
                                        }
                                    }
                                },
                                y1: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    title: {
                                        display: true,
                                        text: 'Number of Orders'
                                    },
                                    grid: {
                                        drawOnChartArea: false
                                    }
                                },
                                y2: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    title: {
                                        display: true,
                                        text: 'Average Order Value (Rp)'
                                    },
                                    grid: {
                                        drawOnChartArea: false
                                    },
                                    ticks: {
                                        callback: function(value) {
                                            return new Intl.NumberFormat('id-ID', {
                                                style: 'currency',
                                                currency: 'IDR',
                                                maximumFractionDigits: 0
                                            }).format(value);
                                        }
                                    }
                                }
                            }
                        }
                    });
                    console.log('Sales Trend Chart initialized successfully');
                } catch (error) {
                    console.error('Error initializing Sales Trend Chart:', error);
                }
            }

            // Initialize Top Products Chart
            console.log('Initializing Top Products Chart...');
            console.log('Top Products Data:', {
                labels: <?php echo json_encode(array_column($top_products, 'name')); ?>,
                revenue: <?php echo json_encode(array_column($top_products, 'total_revenue')); ?>
            });

            const topProductsCtx = document.getElementById('topProductsChart');
            if (!topProductsCtx) {
                console.error('Top Products Chart canvas not found!');
            } else {
                try {
                    const topProductsChart = new Chart(topProductsCtx, {
                        type: 'doughnut',
                        data: {
                            labels: <?php echo json_encode(array_column($top_products, 'name')); ?>,
                            datasets: [{
                                data: <?php echo json_encode(array_column($top_products, 'total_revenue')); ?>,
                                backgroundColor: [
                                    '#3498db',
                                    '#2ecc71',
                                    '#f1c40f',
                                    '#e74c3c',
                                    '#9b59b6',
                                    '#1abc9c',
                                    '#34495e',
                                    '#e67e22',
                                    '#95a5a6',
                                    '#16a085'
                                ]
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
                                        padding: 10
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.label || '';
                                            let value = context.parsed || 0;
                                            let percentage = ((value / context.dataset.data.reduce((a, b) => a + b, 0)) * 100).toFixed(1);
                                            return `${label}: Rp ${value.toLocaleString()} (${percentage}%)`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                    console.log('Top Products Chart initialized successfully');
                } catch (error) {
                    console.error('Error initializing Top Products Chart:', error);
                }
            }

            // Handle chart view toggles
            $('[data-chart-view]').click(function() {
                const view = $(this).data('chart-view');
                $(this).addClass('active').siblings().removeClass('active');
                
                salesTrendChart.data.datasets.forEach(dataset => {
                    if (view === 'amount' && dataset.label.includes('Sales')) {
                        dataset.hidden = false;
                    } else if (view === 'transactions' && dataset.label.includes('Orders')) {
                        dataset.hidden = false;
                    } else if (view === 'average' && dataset.label.includes('Average')) {
                        dataset.hidden = false;
                    } else {
                        dataset.hidden = true;
                    }
                });
                salesTrendChart.update();
            });

            // Handle product view toggles
            $('[data-product-view]').click(function() {
                const view = $(this).data('product-view');
                $(this).addClass('active').siblings().removeClass('active');
                
                const data = view === 'quantity' ? 
                    <?php echo json_encode(array_column($top_products, 'total_quantity')); ?> :
                    <?php echo json_encode(array_column($top_products, 'total_revenue')); ?>;
                
                topProductsChart.data.datasets[0].data = data;
                topProductsChart.update();
            });

            // Handle Date Range Form Submit
            $('#dateRangeForm').on('submit', function(e) {
                e.preventDefault();
                const startDate = $('input[name="start_date"]').val();
                const endDate = $('input[name="end_date"]').val();
                window.location.href = `reports.php?start_date=${startDate}&end_date=${endDate}`;
            });

            // Initialize Sales Table
            $('#salesTable').DataTable({
                dom: '<"row"<"col-sm-12 col-md-6"B><"col-sm-12 col-md-6"f>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                buttons: [
                    {
                        extend: 'collection',
                        text: '<i class="fas fa-download me-2"></i>Export',
                        buttons: [
                            {
                                extend: 'excel',
                                text: '<i class="fas fa-file-excel me-2"></i>Excel',
                                className: 'btn btn-success btn-sm',
                                exportOptions: {
                                    columns: [0, 1, 2, 3, 4, 5]
                                }
                            },
                            {
                                extend: 'pdf',
                                text: '<i class="fas fa-file-pdf me-2"></i>PDF',
                                className: 'btn btn-danger btn-sm',
                                exportOptions: {
                                    columns: [0, 1, 2, 3, 4, 5]
                                }
                            },
                            {
                                extend: 'print',
                                text: '<i class="fas fa-print me-2"></i>Print',
                                className: 'btn btn-info btn-sm',
                                exportOptions: {
                                    columns: [0, 1, 2, 3, 4, 5]
                                }
                            }
                        ]
                    }
                ],
                order: [[0, 'desc']],
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                responsive: true,
                language: {
                    search: "",
                    searchPlaceholder: "Search sales...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ sales",
                    infoEmpty: "No sales available",
                    infoFiltered: "(filtered from _MAX_ total sales)",
                    zeroRecords: "No matching sales found",
                    paginate: {
                        first: '<i class="fas fa-angle-double-left"></i>',
                        previous: '<i class="fas fa-angle-left"></i>',
                        next: '<i class="fas fa-angle-right"></i>',
                        last: '<i class="fas fa-angle-double-right"></i>'
                    }
                }
            });
        });
    </script>
</body>
</html> 