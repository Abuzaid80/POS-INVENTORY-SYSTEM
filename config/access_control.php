<?php
// Check if user is logged in
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

// Check if user has required role
function checkRole($required_roles) {
    if (!isset($_SESSION['role'])) {
        header('Location: login.php');
        exit();
    }

    if (!is_array($required_roles)) {
        $required_roles = [$required_roles];
    }

    if (!in_array($_SESSION['role'], $required_roles)) {
        header('Location: 403.php');
        exit();
    }
}

// Get role name for display
function getRoleName($role) {
    $role_names = [
        'admin' => 'Administrator',
        'medical_rep' => 'Medical Representative',
        'area_manager' => 'Area Manager',
        'regional_sales_manager' => 'Regional Sales Manager',
        'national_sales_manager' => 'National Sales Manager'
    ];
    return $role_names[$role] ?? $role;
}

// Get role hierarchy
function getRoleHierarchy() {
    return [
        'admin' => 5,
        'national_sales_manager' => 4,
        'regional_sales_manager' => 3,
        'area_manager' => 2,
        'medical_rep' => 1
    ];
}

// Check if user has higher or equal role
function hasHigherOrEqualRole($role) {
    $hierarchy = getRoleHierarchy();
    $user_level = $hierarchy[$_SESSION['role']] ?? 0;
    $required_level = $hierarchy[$role] ?? 0;
    return $user_level >= $required_level;
}

// Get accessible roles for current user
function getAccessibleRoles() {
    $hierarchy = getRoleHierarchy();
    $user_level = $hierarchy[$_SESSION['role']] ?? 0;
    $accessible_roles = [];
    
    foreach ($hierarchy as $role => $level) {
        if ($level <= $user_level) {
            $accessible_roles[] = $role;
        }
    }
    
    return $accessible_roles;
}

// Get role-specific menu items
function getRoleMenuItems() {
    $menu_items = [
        'admin' => [
            ['title' => 'Dashboard', 'url' => 'admin/dashboard.php', 'icon' => 'fas fa-tachometer-alt'],
            ['title' => 'Users', 'url' => 'admin/users.php', 'icon' => 'fas fa-users'],
            ['title' => 'Reports', 'url' => 'admin/reports.php', 'icon' => 'fas fa-chart-bar'],
            ['title' => 'Settings', 'url' => 'admin/settings.php', 'icon' => 'fas fa-cog']
        ],
        'national_sales_manager' => [
            ['title' => 'Dashboard', 'url' => 'national_manager/dashboard.php', 'icon' => 'fas fa-tachometer-alt'],
            ['title' => 'Regional Reports', 'url' => 'national_manager/regional_reports.php', 'icon' => 'fas fa-chart-bar'],
            ['title' => 'Performance', 'url' => 'national_manager/performance.php', 'icon' => 'fas fa-chart-line']
        ],
        'regional_sales_manager' => [
            ['title' => 'Dashboard', 'url' => 'regional_manager/dashboard.php', 'icon' => 'fas fa-tachometer-alt'],
            ['title' => 'Area Reports', 'url' => 'regional_manager/area_reports.php', 'icon' => 'fas fa-chart-bar'],
            ['title' => 'Team Performance', 'url' => 'regional_manager/team_performance.php', 'icon' => 'fas fa-users']
        ],
        'area_manager' => [
            ['title' => 'Dashboard', 'url' => 'area_manager/dashboard.php', 'icon' => 'fas fa-tachometer-alt'],
            ['title' => 'Medical Reps', 'url' => 'area_manager/medical_reps.php', 'icon' => 'fas fa-user-md'],
            ['title' => 'Sales Reports', 'url' => 'area_manager/sales_reports.php', 'icon' => 'fas fa-chart-bar']
        ],
        'medical_rep' => [
            ['title' => 'Dashboard', 'url' => 'medical_rep/dashboard.php', 'icon' => 'fas fa-tachometer-alt'],
            ['title' => 'Visits', 'url' => 'medical_rep/visits.php', 'icon' => 'fas fa-hospital'],
            ['title' => 'Sales', 'url' => 'medical_rep/sales.php', 'icon' => 'fas fa-shopping-cart'],
            ['title' => 'Reports', 'url' => 'medical_rep/reports.php', 'icon' => 'fas fa-chart-bar']
        ]
    ];

    return $menu_items[$_SESSION['role']] ?? [];
}
?> 