<?php
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
        <li class="sidebar-item">
            <a href="index.php" class="sidebar-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="products.php" class="sidebar-link <?php echo $current_page === 'products.php' ? 'active' : ''; ?>">
                <i class="fas fa-box"></i>
                <span>Products</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="categories.php" class="sidebar-link <?php echo $current_page === 'categories.php' ? 'active' : ''; ?>">
                <i class="fas fa-tags"></i>
                <span>Categories</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="sales.php" class="sidebar-link <?php echo $current_page === 'sales.php' ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>Sales</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="customers.php" class="sidebar-link <?php echo $current_page === 'customers.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Customers</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="reports.php" class="sidebar-link <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="sponsor_proposal.php" class="sidebar-link <?php echo $current_page === 'sponsor_proposal.php' ? 'active' : ''; ?>">
                <i class="fas fa-handshake"></i>
                <span>Sponsor Proposals</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="settings.php" class="sidebar-link <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="logout.php" class="sidebar-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</div> 