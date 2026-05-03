<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - POS Inventory System' : 'POS Inventory System'; ?></title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="assets/deskapp/images/favicon.ico" />
    
    <!-- Bootstrap CSS -->
    <link href="assets/deskapp/vendors/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="assets/deskapp/vendors/font-awesome/css/all.min.css" rel="stylesheet">
    
    <!-- Deskapp CSS -->
    <link href="assets/deskapp/css/style.css" rel="stylesheet">
    <link href="assets/deskapp/css/custom.css" rel="stylesheet">
    
    <?php if (isset($page_styles)) echo $page_styles; ?>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include 'components/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main">
            <!-- Top Navigation -->
            <?php include 'components/topnav.php'; ?>
            
            <!-- Main Content -->
            <main class="content">
                <div class="container-fluid">
                    <div class="header">
                        <h1 class="header-title">
                            <?php echo isset($page_title) ? $page_title : ''; ?>
                        </h1>
                    </div>
                    
                    <?php if (isset($page_content)) echo $page_content; ?>
                </div>
            </main>
            
            <!-- Footer -->
            <?php include 'components/footer.php'; ?>
        </div>
    </div>

    <!-- Core JS -->
    <script src="assets/deskapp/vendors/jquery/jquery.min.js"></script>
    <script src="assets/deskapp/vendors/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/deskapp/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    
    <!-- DataTables -->
    <script src="assets/deskapp/vendors/datatables/jquery.dataTables.min.js"></script>
    <script src="assets/deskapp/vendors/datatables/dataTables.bootstrap5.min.js"></script>
    
    <!-- Deskapp JS -->
    <script src="assets/deskapp/js/app.js"></script>
    
    <?php if (isset($page_scripts)) echo $page_scripts; ?>
</body>
</html> 