<?php
// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <a href="index.php" class="sidebar-brand">
            <i class="fas fa-store"></i>
            <span>POS System</span>
        </a>
    </div>
    <ul class="sidebar-menu">
        <!-- Dashboard -->
        <li class="sidebar-item">
            <a href="index.php" class="sidebar-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <!-- Products -->
        <li class="sidebar-item">
            <a href="products.php" class="sidebar-link <?php echo $current_page === 'products.php' ? 'active' : ''; ?>">
                <i class="fas fa-box"></i>
                <span>Products</span>
            </a>
        </li>
        <!-- Categories -->
        <li class="sidebar-item">
            <a href="categories.php" class="sidebar-link <?php echo $current_page === 'categories.php' ? 'active' : ''; ?>">
                <i class="fas fa-tags"></i>
                <span>Categories</span>
            </a>
        </li>
        <!-- Doctors -->
        <li class="sidebar-item">
            <a href="doctors.php" class="sidebar-link <?php echo $current_page === 'doctors.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-md"></i>
                <span>Doctors</span>
            </a>
        </li>
        <!-- Business Visits -->
        <li class="sidebar-item">
            <a href="business_visit.php" class="sidebar-link <?php echo $current_page === 'business_visit.php' ? 'active' : ''; ?>">
                <i class="fas fa-building"></i>
                <span>Business Visits</span>
            </a>
        </li>
        <!-- Customers -->
        <li class="sidebar-item">
            <a href="customers.php" class="sidebar-link <?php echo $current_page === 'customers.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Customers</span>
            </a>
        </li>
        <!-- Sales -->
        <li class="sidebar-item">
            <a href="sales.php" class="sidebar-link <?php echo $current_page === 'sales.php' ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>Sales</span>
            </a>
        </li>
        <!-- Reports -->
        <li class="sidebar-item">
            <a href="reports.php" class="sidebar-link <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
        </li>
        <!-- Logout -->
        <li class="sidebar-item">
            <a href="logout.php" class="sidebar-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</div> 