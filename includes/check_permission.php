<?php
function checkPermission($required_permission) {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }

    // Get the current script name
    $current_script = basename($_SERVER['SCRIPT_NAME']);

    // If user is report_viewer, only allow access to reports.php
    if ($_SESSION['role_name'] === 'report_viewer') {
        if ($current_script !== 'reports.php') {
            header('Location: reports.php');
            exit();
        }
        // Only allow if they have manage_reports permission
        if (!in_array('manage_reports', $_SESSION['permissions'])) {
            header('Location: access_denied.php');
            exit();
        }
        return true;
    }

    // If user is limited_admin, only allow access to products.php, sales.php, and customers.php
    if ($_SESSION['role_name'] === 'limited_admin') {
        $allowed_pages = ['products.php', 'sales.php', 'customers.php'];
        
        // Force redirect from index.php or any other page to products.php
        if (!in_array($current_script, $allowed_pages)) {
            header('Location: products.php');
            exit();
        }
        
        // Check if they have the required permission for the current page
        $page_permissions = [
            'products.php' => 'manage_products',
            'sales.php' => 'manage_sales',
            'customers.php' => 'manage_customers'
        ];
        
        // For limited_admin, automatically grant access to allowed pages
        if (isset($page_permissions[$current_script])) {
            return true;
        }
        
        header('Location: products.php');
        exit();
    }

    // For other users, check if they have the required permission
    if (!in_array($required_permission, $_SESSION['permissions'])) {
        header('Location: index.php');
        exit();
    }
    
    return true;
}

function isReportViewer() {
    return isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'report_viewer';
}
?> 