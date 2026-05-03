<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sidebar-content">
        <a class="sidebar-brand" href="index.php">
            <span class="align-middle">POS System</span>
        </a>

        <ul class="sidebar-nav">
            <li class="sidebar-item <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                <a class="sidebar-link" href="index.php">
                    <i class="align-middle" data-feather="home"></i>
                    <span class="align-middle">Dashboard</span>
                </a>
            </li>

            <li class="sidebar-item <?php echo $current_page == 'products.php' ? 'active' : ''; ?>">
                <a class="sidebar-link" href="products.php">
                    <i class="align-middle" data-feather="package"></i>
                    <span class="align-middle">Products</span>
                </a>
            </li>

            <li class="sidebar-item <?php echo $current_page == 'inventory.php' ? 'active' : ''; ?>">
                <a class="sidebar-link" href="inventory.php">
                    <i class="align-middle" data-feather="box"></i>
                    <span class="align-middle">Inventory</span>
                </a>
            </li>

            <li class="sidebar-item <?php echo $current_page == 'sales.php' ? 'active' : ''; ?>">
                <a class="sidebar-link" href="sales.php">
                    <i class="align-middle" data-feather="shopping-cart"></i>
                    <span class="align-middle">Sales</span>
                </a>
            </li>

            <li class="sidebar-item <?php echo $current_page == 'customers.php' ? 'active' : ''; ?>">
                <a class="sidebar-link" href="customers.php">
                    <i class="align-middle" data-feather="users"></i>
                    <span class="align-middle">Customers</span>
                </a>
            </li>

            <li class="sidebar-item <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                <a class="sidebar-link" href="reports.php">
                    <i class="align-middle" data-feather="bar-chart-2"></i>
                    <span class="align-middle">Reports</span>
                </a>
            </li>

            <li class="sidebar-item <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
                <a class="sidebar-link" href="settings.php">
                    <i class="align-middle" data-feather="settings"></i>
                    <span class="align-middle">Settings</span>
                </a>
            </li>
        </ul>
    </div>
</div> 