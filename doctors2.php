<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Handle Excel file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excel_file'])) {
    try {
        require 'vendor/autoload.php';
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['excel_file']['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        // Skip header row
        array_shift($rows);
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($rows as $row) {
            if (empty($row[0])) continue; // Skip empty rows
            
            try {
                $query = "INSERT INTO doctors (name, specialty, outlet, address, contact_number, email, status) 
                         VALUES (:name, :specialty, :outlet, :address, :contact_number, :email, :status)";
                $stmt = $db->prepare($query);
                
                $stmt->bindParam(':name', $row[0], PDO::PARAM_STR);
                $stmt->bindParam(':specialty', $row[1], PDO::PARAM_STR);
                $stmt->bindParam(':outlet', $row[2], PDO::PARAM_STR);
                $stmt->bindParam(':address', $row[3], PDO::PARAM_STR);
                $stmt->bindParam(':contact_number', $row[4], PDO::STR);
                $stmt->bindParam(':email', $row[5], PDO::PARAM_STR);
                $status = isset($row[6]) ? $row[6] : 'active';
                $stmt->bindParam(':status', $status, PDO::PARAM_STR);
                
                if ($stmt->execute()) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            } catch (Exception $e) {
                $errorCount++;
            }
        }
        
        $_SESSION['message'] = "Excel import completed. Successfully imported: $successCount, Failed: $errorCount";
        $_SESSION['message_type'] = $errorCount == 0 ? 'success' : 'warning';
        
    } catch (Exception $e) {
        $_SESSION['message'] = "Error processing Excel file: " . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
    }
    
    header("Location: doctors.php");
    exit();
}

// Handle doctor actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add_doctor':
                    // Validate required fields
                    $required_fields = ['name', 'specialty', 'outlet', 'address', 'contact_number', 'email', 'status'];
                    foreach ($required_fields as $field) {
                        if (empty($_POST[$field])) {
                            throw new Exception("Please fill in all required fields.");
                        }
                    }

                    $query = "INSERT INTO doctors (name, specialty, outlet, address, contact_number, email, status) 
                             VALUES (:name, :specialty, :outlet, :address, :contact_number, :email, :status)";
                    $stmt = $db->prepare($query);
                    
                    $stmt->bindParam(':name', $_POST['name'], PDO::PARAM_STR);
                    $stmt->bindParam(':specialty', $_POST['specialty'], PDO::PARAM_STR);
                    $stmt->bindParam(':outlet', $_POST['outlet'], PDO::PARAM_STR);
                    $stmt->bindParam(':address', $_POST['address'], PDO::PARAM_STR);
                    $stmt->bindParam(':contact_number', $_POST['contact_number'], PDO::PARAM_STR);
                    $stmt->bindParam(':email', $_POST['email'], PDO::PARAM_STR);
                    $stmt->bindParam(':status', $_POST['status'], PDO::PARAM_STR);
                    
                    if ($stmt->execute()) {
                        echo json_encode(['status' => 'success', 'message' => 'Doctor added successfully']);
                    } else {
                        throw new Exception("Failed to add doctor to database.");
                    }
                    exit();
                    break;

                case 'update_doctor':
                    $query = "UPDATE doctors 
                             SET name = :name, specialty = :specialty, outlet = :outlet, 
                                 address = :address, contact_number = :contact_number, 
                                 email = :email, status = :status
                             WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $_POST['id']);
                    $stmt->bindParam(':name', $_POST['name']);
                    $stmt->bindParam(':specialty', $_POST['specialty']);
                    $stmt->bindParam(':outlet', $_POST['outlet']);
                    $stmt->bindParam(':address', $_POST['address']);
                    $stmt->bindParam(':contact_number', $_POST['contact_number']);
                    $stmt->bindParam(':email', $_POST['email']);
                    $stmt->bindParam(':status', $_POST['status']);
                    $stmt->execute();
                    break;

                case 'delete_doctor':
                    try {
                        $query = "DELETE FROM doctors WHERE id = :id";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':id', $_POST['id']);
                        
                        if ($stmt->execute()) {
                            echo json_encode(['status' => 'success', 'message' => 'Doctor deleted successfully']);
                        } else {
                            throw new Exception("Failed to delete doctor");
                        }
                    } catch (Exception $e) {
                        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                    }
                    exit();
                    break;

                case 'get_doctor':
                    $query = "SELECT * FROM doctors WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $_POST['id']);
                    $stmt->execute();
                    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo json_encode($doctor);
                    exit();
                    break;
            }
            header("Location: doctors.php");
            exit();
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit();
        }
    }
}

// Fetch all doctors
$query = "SELECT * FROM doctors ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate doctor statistics
$total_doctors = count($doctors);
$active_doctors = 0;
$inactive_doctors = 0;
$specialties = [];

foreach ($doctors as $doctor) {
    if ($doctor['status'] == 'active') {
        $active_doctors++;
    } else {
        $inactive_doctors++;
    }
    if (!in_array($doctor['specialty'], $specialties)) {
        $specialties[] = $doctor['specialty'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctors - POS Inventory System</title>
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

        .search-box {
            position: relative;
        }
        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        .search-box input {
            padding-left: 2.5rem;
        }
        .stats-card {
            border-radius: 0.5rem;
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-icon {
            font-size: 2rem;
            opacity: 0.8;
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
            <li class="sidebar-item active">
                <a href="doctors.php" class="sidebar-link">
                    <i class="fas fa-user-md"></i>
                    <span>Doctors</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="business_visit.php" class="sidebar-link">
                    <i class="fas fa-building"></i>
                    <span>Business Visits</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="categories.php" class="sidebar-link">
                    <i class="fas fa-tags"></i>
                    <span>Categories</span>
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
                    <div class="d-flex justify-content-between align-items-center">
                        <h2>Doctors Management</h2>
                        <div class="d-flex gap-2">
                            <form action="doctors.php" method="post" enctype="multipart/form-data" class="d-flex gap-2">
                                <div class="input-group">
                                    <input type="file" class="form-control" name="excel_file" accept=".xlsx,.xls" required>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-file-excel me-2"></i>Import Excel
                                    </button>
                                </div>
                            </form>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDoctorModal">
                                <i class="fas fa-plus me-2"></i>Add Doctor
                            </button>
                        </div>
                    </div>
                </div>
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

            <!-- Quick Stats -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stats-card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-2">Total Doctors</h6>
                                    <h3 class="mb-0 fw-bold"><?php echo $total_doctors; ?></h3>
                                </div>
                                <i class="fas fa-user-md stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-2">Active Doctors</h6>
                                    <h3 class="mb-0 fw-bold"><?php echo $active_doctors; ?></h3>
                                </div>
                                <i class="fas fa-check-circle stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-2">Specialties</h6>
                                    <h3 class="mb-0 fw-bold"><?php echo count($specialties); ?></h3>
                                </div>
                                <i class="fas fa-stethoscope stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card bg-danger text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-2">Inactive Doctors</h6>
                                    <h3 class="mb-0 fw-bold"><?php echo $inactive_doctors; ?></h3>
                                </div>
                                <i class="fas fa-user-slash stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Add Doctor -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" class="form-control" id="searchInput" placeholder="Search doctors...">
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDoctorModal">
                        <i class="fas fa-plus me-2"></i> Add New Doctor
                    </button>
                </div>
            </div>

            <!-- Doctors Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="doctorsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Specialty</th>
                                    <th>Outlet</th>
                                    <th>Address</th>
                                    <th>Contact Number</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                    <th>Updated At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($doctors as $doctor): ?>
                                    <tr>
                                        <td><?php echo $doctor['id']; ?></td>
                                        <td class="fw-medium"><?php echo htmlspecialchars($doctor['name']); ?></td>
                                        <td><?php echo htmlspecialchars($doctor['specialty']); ?></td>
                                        <td><?php echo htmlspecialchars($doctor['outlet']); ?></td>
                                        <td><?php echo htmlspecialchars($doctor['address']); ?></td>
                                        <td><?php echo htmlspecialchars($doctor['contact_number']); ?></td>
                                        <td><?php echo htmlspecialchars($doctor['email']); ?></td>
                                        <td>
                                            <?php if ($doctor['status'] == 'active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($doctor['created_at'])); ?></td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($doctor['updated_at'])); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary edit-doctor" data-id="<?php echo $doctor['id']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger delete-doctor" data-id="<?php echo $doctor['id']; ?>">
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

            <!-- Add Doctor Modal -->
            <div class="modal fade" id="addDoctorModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Add New Doctor</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="addDoctorForm">
                                <input type="hidden" name="action" value="add_doctor">
                                
                                <div class="mb-3">
                                    <label class="form-label">Doctor Name</label>
                                    <input type="text" class="form-control" name="name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Specialty</label>
                                    <input type="text" class="form-control" name="specialty" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Outlet</label>
                                    <input type="text" class="form-control" name="outlet" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" name="address" rows="3" required></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" class="form-control" name="contact_number" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                
                                <div class="text-end">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Add Doctor</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Doctor Modal -->
            <div class="modal fade" id="editDoctorModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Doctor</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="editDoctorForm">
                                <input type="hidden" name="action" value="update_doctor">
                                <input type="hidden" name="id" id="edit_doctor_id">
                                
                                <div class="mb-3">
                                    <label class="form-label">Doctor Name</label>
                                    <input type="text" class="form-control" name="name" id="edit_name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Specialty</label>
                                    <input type="text" class="form-control" name="specialty" id="edit_specialty" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Outlet</label>
                                    <input type="text" class="form-control" name="outlet" id="edit_outlet" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" name="address" id="edit_address" rows="3" required></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" class="form-control" name="contact_number" id="edit_contact_number" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" id="edit_email" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status" id="edit_status" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
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
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#doctorsTable').DataTable({
                "order": [[1, "asc"]],
                "pageLength": 10,
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]]
            });

            // Custom search box
            $('#searchInput').on('keyup', function() {
                $('#doctorsTable').DataTable().search(this.value).draw();
            });

            // Handle form submissions
            $('#addDoctorForm').submit(function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                $.ajax({
                    url: 'doctors.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        try {
                            const result = JSON.parse(response);
                            if (result.status === 'success') {
                                location.reload();
                            } else {
                                alert(result.message || 'An error occurred while adding the doctor.');
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            alert('An error occurred while processing the response.');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                        console.error('Status:', status);
                        console.error('Response:', xhr.responseText);
                        alert('An error occurred while processing your request. Please check the console for details.');
                    }
                });
            });

            // Handle edit doctor button click
            $('.edit-doctor').click(function() {
                const doctorId = $(this).data('id');
                
                // Fetch doctor details
                $.ajax({
                    url: 'doctors.php',
                    type: 'POST',
                    data: {
                        action: 'get_doctor',
                        id: doctorId
                    },
                    success: function(response) {
                        try {
                            const doctor = JSON.parse(response);
                            
                            // Populate form fields
                            $('#edit_doctor_id').val(doctor.id);
                            $('#edit_name').val(doctor.name);
                            $('#edit_specialty').val(doctor.specialty);
                            $('#edit_outlet').val(doctor.outlet);
                            $('#edit_address').val(doctor.address);
                            $('#edit_contact_number').val(doctor.contact_number);
                            $('#edit_email').val(doctor.email);
                            $('#edit_status').val(doctor.status);
                            
                            // Show modal
                            $('#editDoctorModal').modal('show');
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            alert('An error occurred while loading doctor details.');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                        alert('An error occurred while fetching doctor details.');
                    }
                });
            });

            // Handle edit form submission
            $('#editDoctorForm').submit(function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                $.ajax({
                    url: 'doctors.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        try {
                            const result = JSON.parse(response);
                            if (result.status === 'success') {
                                location.reload();
                            } else {
                                alert(result.message || 'An error occurred while updating the doctor.');
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            alert('An error occurred while processing the response.');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                        alert('An error occurred while processing your request.');
                    }
                });
            });

            // Handle delete doctor
            $('.delete-doctor').click(function() {
                const doctorId = $(this).data('id');
                const doctorName = $(this).closest('tr').find('td:first').text();
                
                if (confirm(`Are you sure you want to delete doctor "${doctorName}"?`)) {
                    const deleteButton = $(this);
                    deleteButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
                    
                    $.ajax({
                        url: 'doctors.php',
                        type: 'POST',
                        data: {
                            action: 'delete_doctor',
                            id: doctorId
                        },
                        success: function(response) {
                            try {
                                const result = JSON.parse(response);
                                if (result.status === 'success') {
                                    // Show success toast
                                    const toast = $(
                                        `<div class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                                            <div class="d-flex">
                                                <div class="toast-body">
                                                    ${result.message}
                                                </div>
                                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                                            </div>
                                        </div>`
                                    );
                                    $('.notification-container').append(toast);
                                    const bsToast = new bootstrap.Toast(toast);
                                    bsToast.show();
                                    
                                    // Remove toast and reload after it's hidden
                                    toast.on('hidden.bs.toast', function() {
                                        $(this).remove();
                                        location.reload();
                                    });
                                } else {
                                    alert(result.message || 'Failed to delete doctor');
                                }
                            } catch (e) {
                                console.error('Error parsing response:', e);
                                alert('Error deleting doctor');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Delete error:', error);
                            alert('Error deleting doctor. Please try again.');
                        },
                        complete: function() {
                            deleteButton.prop('disabled', false).html('<i class="fas fa-trash"></i>');
                        }
                    });
                }
            });
        });
    </script>
</body>
</html> 