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

// Fetch all doctors
try {
    $query = "SELECT * FROM doctors ORDER BY name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Error in doctors.php: " . $e->getMessage());
    $doctors = [];
}

// Handle doctor actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add_doctor':
                    // Validate required fields
                    $required_fields = ['name', 'specialization', 'place_of_practice', 'contact', 'email'];
                    foreach ($required_fields as $field) {
                        if (empty($_POST[$field])) {
                            throw new Exception("Please fill in all required fields.");
                        }
                    }

                    // Validate email format
                    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                        throw new Exception("Invalid email format.");
                    }

                    // Start transaction
                    $db->beginTransaction();

                    // Insert new doctor
                    $query = "INSERT INTO doctors (name, specialty, place_of_practice, contact_number, email) 
                             VALUES (:name, :specialty, :place_of_practice, :contact_number, :email)";
                    $stmt = $db->prepare($query);
                    
                    // Bind parameters
                    $stmt->bindParam(':name', $_POST['name'], PDO::PARAM_STR);
                    $stmt->bindParam(':specialty', $_POST['specialization'], PDO::PARAM_STR);
                    $stmt->bindParam(':place_of_practice', $_POST['place_of_practice'], PDO::PARAM_STR);
                    $stmt->bindParam(':contact_number', $_POST['contact'], PDO::PARAM_STR);
                    $stmt->bindParam(':email', $_POST['email'], PDO::PARAM_STR);

                    if (!$stmt->execute()) {
                        throw new Exception("Failed to add doctor to database.");
                    }

                    // Commit transaction
                    $db->commit();

                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Doctor added successfully'
                    ]);
                    exit();
                    break;

                case 'update_doctor':
                    // Validate required fields
                    $required_fields = ['id', 'name', 'specialization', 'place_of_practice', 'contact', 'email'];
                    foreach ($required_fields as $field) {
                        if (empty($_POST[$field])) {
                            throw new Exception("Please fill in all required fields.");
                        }
                    }

                    // Validate email format
                    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                        throw new Exception("Invalid email format.");
                    }

                    // Start transaction
                    $db->beginTransaction();

                    // Check if doctor exists
                    $check_query = "SELECT id FROM doctors WHERE id = :id";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->bindParam(':id', $_POST['id']);
                    $check_stmt->execute();

                    if ($check_stmt->rowCount() === 0) {
                        throw new Exception("Doctor not found");
                    }

                    // Update doctor
                    $query = "UPDATE doctors 
                             SET name = :name, 
                                 specialty = :specialty, 
                                 place_of_practice = :place_of_practice, 
                                 contact_number = :contact_number, 
                                 email = :email 
                             WHERE id = :id";
                    $stmt = $db->prepare($query);
                    
                    // Bind parameters
                    $stmt->bindParam(':id', $_POST['id'], PDO::PARAM_INT);
                    $stmt->bindParam(':name', $_POST['name'], PDO::PARAM_STR);
                    $stmt->bindParam(':specialty', $_POST['specialization'], PDO::PARAM_STR);
                    $stmt->bindParam(':place_of_practice', $_POST['place_of_practice'], PDO::PARAM_STR);
                    $stmt->bindParam(':contact_number', $_POST['contact'], PDO::PARAM_STR);
                    $stmt->bindParam(':email', $_POST['email'], PDO::PARAM_STR);

                    if (!$stmt->execute()) {
                        throw new Exception("Failed to update doctor in database");
                    }

                    // Commit transaction
                    $db->commit();

                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Doctor updated successfully'
                    ]);
                    exit();
                    break;

                case 'delete_doctor':
                    // ... existing delete_doctor case ...
                    break;
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
            exit();
        }
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
                <a href="doctors.php" class="sidebar-link active">
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
            <li class="sidebar-item">
                <a href="business_visit.php" class="sidebar-link">
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
                    <h2>Doctor Management</h2>
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
                                    <th>Name</th>
                                    <th>Specialization</th>
                                    <th>Place of Practice</th>
                                    <th>Contact</th>
                                    <th>Email</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($doctors as $doctor): ?>
                                <tr>
                                    <td class="fw-medium"><?php echo htmlspecialchars($doctor['name']); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['specialty']); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['place_of_practice']); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['contact_number']); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['email']); ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-primary edit-doctor" 
                                                data-id="<?php echo $doctor['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($doctor['name']); ?>"
                                                data-specialty="<?php echo htmlspecialchars($doctor['specialty']); ?>"
                                                data-place-of-practice="<?php echo htmlspecialchars($doctor['place_of_practice']); ?>"
                                                data-contact="<?php echo htmlspecialchars($doctor['contact_number']); ?>"
                                                data-email="<?php echo htmlspecialchars($doctor['email']); ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger delete-doctor" 
                                                data-id="<?php echo $doctor['id']; ?>">
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
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Specialization</label>
                            <input type="text" class="form-control" name="specialization" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Place of Practice</label>
                            <input type="text" class="form-control" name="place_of_practice" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact</label>
                            <input type="text" class="form-control" name="contact" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
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
                        <input type="hidden" id="edit_id" name="id">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Specialization</label>
                            <input type="text" class="form-control" id="edit_specialization" name="specialization" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Place of Practice</label>
                            <input type="text" class="form-control" id="edit_place_of_practice" name="place_of_practice" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact</label>
                            <input type="text" class="form-control" id="edit_contact" name="contact" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
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
            $('#doctorsTable').DataTable({
                "order": [[0, "asc"]],
                "pageLength": 10,
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]]
            });

            // Custom search box
            $('#searchInput').on('keyup', function() {
                $('#doctorsTable').DataTable().search(this.value).draw();
            });

            // Handle Add Doctor Form Submit
            $('#addDoctorForm').on('submit', function(e) {
                e.preventDefault();
                
                // Disable submit button and show loading state
                const submitButton = $(this).find('button[type="submit"]');
                const originalButtonText = submitButton.html();
                submitButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...');
                
                $.ajax({
                    url: 'doctors.php',
                    type: 'POST',
                    data: {
                        action: 'add_doctor',
                        name: $('input[name="name"]').val(),
                        specialization: $('input[name="specialization"]').val(),
                        place_of_practice: $('input[name="place_of_practice"]').val(),
                        contact: $('input[name="contact"]').val(),
                        email: $('input[name="email"]').val()
                    },
                    success: function(response) {
                        try {
                            const result = typeof response === 'object' ? response : JSON.parse(response);
                            
                            if (result.status === 'success') {
                                // Show success toast
                                const toast = $(
                                    `<div class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                                        <div class="d-flex">
                                            <div class="toast-body">
                                                <i class="fas fa-check-circle me-2"></i>
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
                                
                                // Reset form and close modal
                                $('#addDoctorForm')[0].reset();
                                $('#addDoctorModal').modal('hide');
                            } else {
                                // Show error toast
                                const toast = $(
                                    `<div class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                                        <div class="d-flex">
                                            <div class="toast-body">
                                                <i class="fas fa-exclamation-circle me-2"></i>
                                                ${result.message || 'An error occurred while adding the doctor.'}
                                            </div>
                                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                                        </div>
                                    </div>`
                                );
                                $('.notification-container').append(toast);
                                const bsToast = new bootstrap.Toast(toast);
                                bsToast.show();
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            console.error('Raw response:', response);
                            
                            // Show error toast
                            const toast = $(
                                `<div class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                                    <div class="d-flex">
                                        <div class="toast-body">
                                            <i class="fas fa-exclamation-circle me-2"></i>
                                            An error occurred while processing the response. Please try again.
                                        </div>
                                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                                    </div>
                                </div>`
                            );
                            $('.notification-container').append(toast);
                            const bsToast = new bootstrap.Toast(toast);
                            bsToast.show();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                        console.error('Status:', status);
                        console.error('Response:', xhr.responseText);
                        
                        // Show error toast
                        const toast = $(
                            `<div class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                                <div class="d-flex">
                                    <div class="toast-body">
                                        <i class="fas fa-exclamation-circle me-2"></i>
                                        An error occurred while processing your request. Please try again.
                                    </div>
                                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                                </div>
                            </div>`
                        );
                        $('.notification-container').append(toast);
                        const bsToast = new bootstrap.Toast(toast);
                        bsToast.show();
                    },
                    complete: function() {
                        // Re-enable submit button and restore original text
                        submitButton.prop('disabled', false).html(originalButtonText);
                    }
                });
            });

            // Handle Edit Doctor Button Click
            $('.edit-doctor').on('click', function() {
                var id = $(this).data('id');
                var name = $(this).data('name');
                var specialty = $(this).data('specialty');
                var placeOfPractice = $(this).data('place-of-practice');
                var contact = $(this).data('contact');
                var email = $(this).data('email');

                $('#edit_id').val(id);
                $('#edit_name').val(name);
                $('#edit_specialization').val(specialty);
                $('#edit_place_of_practice').val(placeOfPractice);
                $('#edit_contact').val(contact);
                $('#edit_email').val(email);

                $('#editDoctorModal').modal('show');
            });

            // Handle Edit Doctor Form Submit
            $('#editDoctorForm').on('submit', function(e) {
                e.preventDefault();
                
                // Disable submit button and show loading state
                const submitButton = $(this).find('button[type="submit"]');
                const originalButtonText = submitButton.html();
                submitButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
                
                $.ajax({
                    url: 'doctors.php',
                    type: 'POST',
                    data: {
                        action: 'update_doctor',
                        id: $('#edit_id').val(),
                        name: $('#edit_name').val(),
                        specialization: $('#edit_specialization').val(),
                        place_of_practice: $('#edit_place_of_practice').val(),
                        contact: $('#edit_contact').val(),
                        email: $('#edit_email').val()
                    },
                    success: function(response) {
                        try {
                            const result = typeof response === 'object' ? response : JSON.parse(response);
                            
                            if (result.status === 'success') {
                                // Show success toast
                                const toast = $(
                                    `<div class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                                        <div class="d-flex">
                                            <div class="toast-body">
                                                <i class="fas fa-check-circle me-2"></i>
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
                                
                                // Close modal
                                $('#editDoctorModal').modal('hide');
                            } else {
                                // Show error toast
                                const toast = $(
                                    `<div class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                                        <div class="d-flex">
                                            <div class="toast-body">
                                                <i class="fas fa-exclamation-circle me-2"></i>
                                                ${result.message || 'An error occurred while updating the doctor.'}
                                            </div>
                                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                                        </div>
                                    </div>`
                                );
                                $('.notification-container').append(toast);
                                const bsToast = new bootstrap.Toast(toast);
                                bsToast.show();
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            console.error('Raw response:', response);
                            
                            // Show error toast
                            const toast = $(
                                `<div class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                                    <div class="d-flex">
                                        <div class="toast-body">
                                            <i class="fas fa-exclamation-circle me-2"></i>
                                            An error occurred while processing the response. Please try again.
                                        </div>
                                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                                    </div>
                                </div>`
                            );
                            $('.notification-container').append(toast);
                            const bsToast = new bootstrap.Toast(toast);
                            bsToast.show();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                        console.error('Status:', status);
                        console.error('Response:', xhr.responseText);
                        
                        // Show error toast
                        const toast = $(
                            `<div class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                                <div class="d-flex">
                                    <div class="toast-body">
                                        <i class="fas fa-exclamation-circle me-2"></i>
                                        An error occurred while processing your request. Please try again.
                                    </div>
                                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                                </div>
                            </div>`
                        );
                        $('.notification-container').append(toast);
                        const bsToast = new bootstrap.Toast(toast);
                        bsToast.show();
                    },
                    complete: function() {
                        // Re-enable submit button and restore original text
                        submitButton.prop('disabled', false).html(originalButtonText);
                    }
                });
            });

            // Handle Delete Doctor Button Click
            $('.delete-doctor').on('click', function() {
                if (confirm('Are you sure you want to delete this doctor?')) {
                    var id = $(this).data('id');
                    $.ajax({
                        url: 'api/doctors.php',
                        type: 'DELETE',
                        data: { id: id },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('Error: ' + response.message);
                            }
                        },
                        error: function() {
                            alert('An error occurred while deleting the doctor.');
                        }
                    });
                }
            });
        });
    </script>
</body>
</html> 