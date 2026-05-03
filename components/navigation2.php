<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<button class="navbar-toggler" type="button" id="sidebarToggle">
    <i class="fas fa-bars"></i>
</button>

<div class="d-flex">
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="p-3">
            <h4 class="text-white">POS Inventory</h4>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" href="index.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'products.php' ? 'active' : ''; ?>" href="products.php">
                    <i class="fas fa-box"></i>
                    <span>Products</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'sales.php' ? 'active' : ''; ?>" href="sales.php">
                    <i class="fas fa-shopping-cart"></i> Sales
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'customers.php' ? 'active' : ''; ?>" href="customers.php">
                    <i class="fas fa-users"></i> Customers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'inventory.php' ? 'active' : ''; ?>" href="inventory.php">
                    <i class="fas fa-warehouse"></i> Inventory
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid"> 