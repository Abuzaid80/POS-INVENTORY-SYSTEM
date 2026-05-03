<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle business visit actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add_visit':
                    // Validate required fields
                    $required_fields = ['visit_number', 'visit_date', 'place_address', 'visit_type', 
                                     'total_value_advance', 'visitor_name', 'visitor_position', 
                                     'company_name', 'business_type', 'total_realization'];
                    foreach ($required_fields as $field) {
                        if (empty($_POST[$field])) {
                            throw new Exception("Please fill in all required fields.");
                        }
                    }

                    // Validate numeric fields
                    if (!is_numeric($_POST['total_value_advance']) || $_POST['total_value_advance'] < 0) {
                        throw new Exception("Total Value Advance must be a non-negative number.");
                    }
                    if (!is_numeric($_POST['total_realization']) || $_POST['total_realization'] < 0) {
                        throw new Exception("Total Realization must be a non-negative number.");
                    }

                    $query = "INSERT INTO business_visits (visit_number, visit_date, place_address, visit_type, 
                             total_value_advance, visitor_name, visitor_position, company_name, 
                             business_type, remarks, total_realization) 
                             VALUES (:visit_number, :visit_date, :place_address, :visit_type, 
                             :total_value_advance, :visitor_name, :visitor_position, :company_name, 
                             :business_type, :remarks, :total_realization)";
                    $stmt = $db->prepare($query);
                    
                    $stmt->bindParam(':visit_number', $_POST['visit_number'], PDO::PARAM_STR);
                    $stmt->bindParam(':visit_date', $_POST['visit_date'], PDO::PARAM_STR);
                    $stmt->bindParam(':place_address', $_POST['place_address'], PDO::PARAM_STR);
                    $stmt->bindParam(':visit_type', $_POST['visit_type'], PDO::PARAM_STR);
                    $stmt->bindParam(':total_value_advance', $_POST['total_value_advance'], PDO::PARAM_STR);
                    $stmt->bindParam(':visitor_name', $_POST['visitor_name'], PDO::PARAM_STR);
                    $stmt->bindParam(':visitor_position', $_POST['visitor_position'], PDO::PARAM_STR);
                    $stmt->bindParam(':company_name', $_POST['company_name'], PDO::PARAM_STR);
                    $stmt->bindParam(':business_type', $_POST['business_type'], PDO::PARAM_STR);
                    $stmt->bindParam(':remarks', $_POST['remarks'], PDO::PARAM_STR);
                    $stmt->bindParam(':total_realization', $_POST['total_realization'], PDO::PARAM_STR);
                    
                    if ($stmt->execute()) {
                        echo json_encode(['status' => 'success', 'message' => 'Business visit added successfully']);
                    } else {
                        throw new Exception("Failed to add business visit to database.");
                    }
                    exit();
                    break;

                case 'update_visit':
                    $query = "UPDATE business_visits 
                             SET visit_number = :visit_number, visit_date = :visit_date, 
                                 place_address = :place_address, visit_type = :visit_type,
                                 total_value_advance = :total_value_advance, visitor_name = :visitor_name,
                                 visitor_position = :visitor_position, company_name = :company_name,
                                 business_type = :business_type, remarks = :remarks,
                                 total_realization = :total_realization
                             WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $_POST['id']);
                    $stmt->bindParam(':visit_number', $_POST['visit_number']);
                    $stmt->bindParam(':visit_date', $_POST['visit_date']);
                    $stmt->bindParam(':place_address', $_POST['place_address']);
                    $stmt->bindParam(':visit_type', $_POST['visit_type']);
                    $stmt->bindParam(':total_value_advance', $_POST['total_value_advance']);
                    $stmt->bindParam(':visitor_name', $_POST['visitor_name']);
                    $stmt->bindParam(':visitor_position', $_POST['visitor_position']);
                    $stmt->bindParam(':company_name', $_POST['company_name']);
                    $stmt->bindParam(':business_type', $_POST['business_type']);
                    $stmt->bindParam(':remarks', $_POST['remarks']);
                    $stmt->bindParam(':total_realization', $_POST['total_realization']);
                    $stmt->execute();
                    break;

                case 'delete_visit':
                    $query = "DELETE FROM business_visits WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $_POST['id']);
                    $stmt->execute();
                    break;

                case 'get_visit':
                    $query = "SELECT * FROM business_visits WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $_POST['id']);
                    $stmt->execute();
                    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo json_encode($visit);
                    exit();
                    break;
            }
            header("Location: business_visit.php");
            exit();
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit();
        }
    }
}

// Fetch all business visits
$query = "SELECT * FROM business_visits ORDER BY visit_date DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_visits = count($visits);
$total_advance = 0;
$total_realization = 0;
$visit_types = [];

foreach ($visits as $visit) {
    $total_advance += $visit['total_value_advance'];
    $total_realization += $visit['total_realization'];
    if (!in_array($visit['visit_type'], $visit_types)) {
        $visit_types[] = $visit['visit_type'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Visits - POS Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="assets/css/navigation.css" rel="stylesheet">
    <style>
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background-color: #2c3e50;
            color: #fff;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            color: #fff;
            text-decoration: none;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .sidebar-brand i {
            margin-right: 10px;
            font-size: 1.5rem;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-item {
            margin: 5px 0;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #ecf0f1;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .sidebar-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
            transform: translateX(5px);
        }

        .sidebar-link.active {
            background-color: #3498db;
            color: #fff;
        }

        .sidebar-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
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

        .card-body {
            padding: 1.25rem;
        }

        /* Table Styles */
        .table-responsive {
            width: 100%;
            margin-bottom: 1rem;
        }

        .table {
            width: 100%;
            margin-bottom: 1rem;
            background-color: transparent;
        }

        /* Search Box */
        .search-box {
            position: relative;
            width: 100%;
            max-width: 400px;
        }

        /* Stats Card Styles */
        .stats-card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }

        .stats-icon {
            font-size: 2rem;
            opacity: 0.8;
        }

        /* Responsive Styles */
        @media (max-width: 1200px) {
            .container-fluid {
                padding-right: 20px;
                padding-left: 20px;
            }
        }

        @media (max-width: 992px) {
            .main-content {
                padding: 20px;
            }
            .container-fluid {
                padding-right: 15px;
                padding-left: 15px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 15px;
            }

            .main-content.active {
                margin-left: 280px;
                width: calc(100% - 280px);
            }

            .container-fluid {
                padding-right: 10px;
                padding-left: 10px;
            }

            .card {
                margin-bottom: 1rem;
            }

            .search-box {
                max-width: 100%;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 10px;
            }
            .container-fluid {
                padding-right: 5px;
                padding-left: 5px;
            }
            .card-body {
                padding: 1rem;
            }
            .table-responsive {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
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
                <a href="doctors.php" class="sidebar-link">
                    <i class="fas fa-user-md"></i>
                    <span>Doctors</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="categories.php" class="sidebar-link">
                    <i class="fas fa-tags"></i>
                    <span>Categories</span>
                </a>
            </li>
            <li class="sidebar-item active">
                <a href="business_visit.php" class="sidebar-link active">
                    <i class="fas fa-building"></i>
                    <span>Business Visits</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="sales.php" class="sidebar-link">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Sales</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="customers.php" class="sidebar-link">
                    <i class="fas fa-users"></i>
                    <span>Customers</span>
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
            <li class="sidebar-item">
                <a href="logout.php" class="sidebar-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Notification Container -->
            <div class="notification-container"></div>

            <div class="row mb-4">
                <div class="col-12">
                    <h2>Business Visit Management</h2>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stats-card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-2">Total Visits</h6>
                                    <h3 class="mb-0 fw-bold"><?php echo $total_visits; ?></h3>
                                </div>
                                <i class="fas fa-building stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-2">Total Advance</h6>
                                    <h3 class="mb-0 fw-bold">Rp <?php echo number_format($total_advance, 2); ?></h3>
                                </div>
                                <i class="fas fa-money-bill-wave stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-2">Total Realization</h6>
                                    <h3 class="mb-0 fw-bold">Rp <?php echo number_format($total_realization, 2); ?></h3>
                                </div>
                                <i class="fas fa-chart-line stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-2">Visit Types</h6>
                                    <h3 class="mb-0 fw-bold"><?php echo count($visit_types); ?></h3>
                                </div>
                                <i class="fas fa-tags stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Add Visit -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" class="form-control" id="searchInput" placeholder="Search visits...">
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVisitModal">
                        <i class="fas fa-plus me-2"></i> Add New Visit
                    </button>
                </div>
            </div>

            <!-- Visits Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="visitsTable">
                            <thead>
                                <tr>
                                    <th>Visit Number</th>
                                    <th>Date</th>
                                    <th>Company</th>
                                    <th>Visitor</th>
                                    <th>Type</th>
                                    <th>Advance</th>
                                    <th>Realization</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($visits as $visit): ?>
                                <tr>
                                    <td class="fw-medium"><?php echo htmlspecialchars($visit['visit_number']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($visit['visit_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($visit['company_name']); ?></td>
                                    <td><?php echo htmlspecialchars($visit['visitor_name']); ?></td>
                                    <td><?php echo htmlspecialchars($visit['visit_type']); ?></td>
                                    <td class="fw-medium">Rp <?php echo number_format($visit['total_value_advance'], 2); ?></td>
                                    <td class="fw-medium">Rp <?php echo number_format($visit['total_realization'], 2); ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-primary edit-visit" 
                                                data-id="<?php echo $visit['id']; ?>"
                                                data-visit-number="<?php echo htmlspecialchars($visit['visit_number']); ?>"
                                                data-visit-date="<?php echo $visit['visit_date']; ?>"
                                                data-place-address="<?php echo htmlspecialchars($visit['place_address']); ?>"
                                                data-visit-type="<?php echo htmlspecialchars($visit['visit_type']); ?>"
                                                data-total-value-advance="<?php echo $visit['total_value_advance']; ?>"
                                                data-visitor-name="<?php echo htmlspecialchars($visit['visitor_name']); ?>"
                                                data-visitor-position="<?php echo htmlspecialchars($visit['visitor_position']); ?>"
                                                data-company-name="<?php echo htmlspecialchars($visit['company_name']); ?>"
                                                data-business-type="<?php echo htmlspecialchars($visit['business_type']); ?>"
                                                data-remarks="<?php echo htmlspecialchars($visit['remarks']); ?>"
                                                data-total-realization="<?php echo $visit['total_realization']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger delete-visit" 
                                                data-id="<?php echo $visit['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Visit Modal -->
    <div class="modal fade" id="addVisitModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Business Visit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addVisitForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Visit Number</label>
                                <input type="text" class="form-control" name="visit_number" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Visit Date</label>
                                <input type="date" class="form-control" name="visit_date" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Place Address</label>
                            <input type="text" class="form-control" name="place_address" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Visit Type</label>
                                <input type="text" class="form-control" name="visit_type" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Total Value Advance</label>
                                <input type="number" class="form-control" name="total_value_advance" step="0.01" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Visitor Name</label>
                                <input type="text" class="form-control" name="visitor_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Visitor Position</label>
                                <input type="text" class="form-control" name="visitor_position" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Company Name</label>
                                <input type="text" class="form-control" name="company_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Business Type</label>
                                <input type="text" class="form-control" name="business_type" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Total Realization</label>
                            <input type="number" class="form-control" name="total_realization" step="0.01" required>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Visit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Visit Modal -->
    <div class="modal fade" id="editVisitModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Business Visit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editVisitForm">
                        <input type="hidden" id="edit_id" name="id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Visit Number</label>
                                <input type="text" class="form-control" id="edit_visit_number" name="visit_number" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Visit Date</label>
                                <input type="date" class="form-control" id="edit_visit_date" name="visit_date" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Place Address</label>
                            <input type="text" class="form-control" id="edit_place_address" name="place_address" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Visit Type</label>
                                <input type="text" class="form-control" id="edit_visit_type" name="visit_type" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Total Value Advance</label>
                                <input type="number" class="form-control" id="edit_total_value_advance" name="total_value_advance" step="0.01" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Visitor Name</label>
                                <input type="text" class="form-control" id="edit_visitor_name" name="visitor_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Visitor Position</label>
                                <input type="text" class="form-control" id="edit_visitor_position" name="visitor_position" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Company Name</label>
                                <input type="text" class="form-control" id="edit_company_name" name="company_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Business Type</label>
                                <input type="text" class="form-control" id="edit_business_type" name="business_type" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" id="edit_remarks" name="remarks" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Total Realization</label>
                            <input type="number" class="form-control" id="edit_total_realization" name="total_realization" step="0.01" required>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#visitsTable').DataTable({
                "order": [[1, "desc"]],
                "pageLength": 10,
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]]
            });

            // Custom search box
            $('#searchInput').on('keyup', function() {
                $('#visitsTable').DataTable().search(this.value).draw();
            });

            // Handle Add Visit Form Submit
            $('#addVisitForm').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'business_visit.php',
                    type: 'POST',
                    data: $(this).serialize() + '&action=add_visit',
                    success: function(response) {
                        try {
                            const result = JSON.parse(response);
                            if (result.status === 'success') {
                                location.reload();
                            } else {
                                alert('Error: ' + result.message);
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            alert('An error occurred while adding the visit.');
                        }
                    },
                    error: function() {
                        alert('An error occurred while adding the visit.');
                    }
                });
            });

            // Handle Edit Visit Button Click
            $('.edit-visit').on('click', function() {
                const id = $(this).data('id');
                const visitNumber = $(this).data('visit-number');
                const visitDate = $(this).data('visit-date');
                const placeAddress = $(this).data('place-address');
                const visitType = $(this).data('visit-type');
                const totalValueAdvance = $(this).data('total-value-advance');
                const visitorName = $(this).data('visitor-name');
                const visitorPosition = $(this).data('visitor-position');
                const companyName = $(this).data('company-name');
                const businessType = $(this).data('business-type');
                const remarks = $(this).data('remarks');
                const totalRealization = $(this).data('total-realization');

                $('#edit_id').val(id);
                $('#edit_visit_number').val(visitNumber);
                $('#edit_visit_date').val(visitDate);
                $('#edit_place_address').val(placeAddress);
                $('#edit_visit_type').val(visitType);
                $('#edit_total_value_advance').val(totalValueAdvance);
                $('#edit_visitor_name').val(visitorName);
                $('#edit_visitor_position').val(visitorPosition);
                $('#edit_company_name').val(companyName);
                $('#edit_business_type').val(businessType);
                $('#edit_remarks').val(remarks);
                $('#edit_total_realization').val(totalRealization);

                $('#editVisitModal').modal('show');
            });

            // Handle Edit Visit Form Submit
            $('#editVisitForm').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'business_visit.php',
                    type: 'POST',
                    data: $(this).serialize() + '&action=update_visit',
                    success: function(response) {
                        try {
                            const result = JSON.parse(response);
                            if (result.status === 'success') {
                                location.reload();
                            } else {
                                alert('Error: ' + result.message);
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            alert('An error occurred while updating the visit.');
                        }
                    },
                    error: function() {
                        alert('An error occurred while updating the visit.');
                    }
                });
            });

            // Handle Delete Visit Button Click
            $('.delete-visit').on('click', function() {
                if (confirm('Are you sure you want to delete this visit?')) {
                    const id = $(this).data('id');
                    $.ajax({
                        url: 'business_visit.php',
                        type: 'POST',
                        data: {
                            action: 'delete_visit',
                            id: id
                        },
                        success: function(response) {
                            try {
                                const result = JSON.parse(response);
                                if (result.status === 'success') {
                                    location.reload();
                                } else {
                                    alert('Error: ' + result.message);
                                }
                            } catch (e) {
                                console.error('Error parsing response:', e);
                                alert('An error occurred while deleting the visit.');
                            }
                        },
                        error: function() {
                            alert('An error occurred while deleting the visit.');
                        }
                    });
                }
            });
        });
    </script>
</body>
</html> 