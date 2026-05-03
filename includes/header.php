<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Require login for all pages
$auth->requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'POS Inventory System'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/sidebar.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
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
        .table thead {
            background-color: #007bff;
            color: white;
        }
        .table thead th {
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
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }
        .toast {
            background: white;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            margin-bottom: 10px;
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
        }
        .toast.show {
            opacity: 1;
        }
        .toast-header {
            padding: 0.5rem 1rem;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            align-items: center;
        }
        .toast-body {
            padding: 1rem;
        }
        .toast-success {
            border-left: 4px solid #28a745;
        }
        .toast-error {
            border-left: 4px solid #dc3545;
        }
        .toast-icon {
            margin-right: 0.5rem;
        }
    </style>
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
            <a href="index.php" class="sidebar-brand">Dashboard</a>
        </div>
        <div class="sidebar-menu">
            <div class="sidebar-item">
                <a href="index.php" class="sidebar-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <?php if ($auth->hasPermission('manage_products')): ?>
            <div class="sidebar-item">
                <a href="products.php" class="sidebar-link <?php echo $current_page === 'products' ? 'active' : ''; ?>">
                    <i class="fas fa-box"></i>
                    <span>Products</span>
                </a>
            </div>
            <?php endif; ?>
            <?php if ($auth->hasPermission('manage_sales')): ?>
            <div class="sidebar-item">
                <a href="sales.php" class="sidebar-link <?php echo $current_page === 'sales' ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Sales</span>
                </a>
            </div>
            <?php endif; ?>
            <?php if ($auth->hasPermission('manage_customers')): ?>
            <div class="sidebar-item">
                <a href="customers.php" class="sidebar-link <?php echo $current_page === 'customers' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Customers</span>
                </a>
            </div>
            <?php endif; ?>
            <?php if ($auth->hasPermission('manage_reports')): ?>
            <div class="sidebar-item">
                <a href="reports.php" class="sidebar-link <?php echo $current_page === 'reports' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </div>
            <?php endif; ?>
            <?php if ($auth->hasPermission('manage_settings')): ?>
            <div class="sidebar-item">
                <a href="settings.php" class="sidebar-link <?php echo $current_page === 'settings' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </div>
            <?php endif; ?>
            <?php if ($auth->hasPermission('manage_users')): ?>
            <div class="sidebar-item">
                <a href="users.php" class="sidebar-link <?php echo $current_page === 'users' ? 'active' : ''; ?>">
                    <i class="fas fa-user-cog"></i>
                    <span>Users</span>
                </a>
            </div>
            <?php endif; ?>
            <div class="sidebar-item">
                <a href="logout.php" class="sidebar-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Toast Container -->
        <div class="toast-container"></div>

        <div class="wrapper">
            <!-- Top Navbar -->
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <button class="btn btn-link d-lg-none" id="sidebarCollapse">
                        <i class="fas fa-bars"></i>
                    </button>
                    
                    <div class="d-flex align-items-center">
                        <a class="navbar-brand" href="dashboard.php">
                            <i class="fas fa-store"></i>
                        </a>
                    </div>

                    <div class="d-flex align-items-center">
                        <ul class="navbar-nav">
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-user-circle"></i>
                                    <span class="d-none d-md-inline">
                                        <?php 
                                        if (isset($_SESSION['user_name'])) {
                                            echo htmlspecialchars($_SESSION['user_name']);
                                        } else {
                                            echo 'Guest';
                                        }
                                        ?>
                                    </span>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                    <?php if (isset($_SESSION['user_name'])): ?>
                                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                                    <?php else: ?>
                                        <li><a class="dropdown-item" href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                                    <?php endif; ?>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>

            <!-- Main Content Container -->
            <div class="container-fluid">
                <div class="row">
                    <!-- Main Content -->
                    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                            <!-- Removed redundant Dashboard heading -->
                        </div>
                        
                        <?php if (isset($_SESSION['message'])): ?>
                            <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
                                <?php 
                                    echo $_SESSION['message'];
                                    unset($_SESSION['message']);
                                    unset($_SESSION['message_type']);
                                ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h2><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h2>
                                </div>
                            </div>
                        </div>
                    </main>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="assets/js/script.js"></script>
    <script>
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html> 