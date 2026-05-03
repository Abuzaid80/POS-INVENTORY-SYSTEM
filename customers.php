<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Function to check if user is super admin
function isSuperAdmin() {
    return isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'super_admin';
}

// Function to check if user has admin access
function hasAdminAccess() {
    return isset($_SESSION['role_name']) && (
        $_SESSION['role_name'] === 'admin' || 
        $_SESSION['role_name'] === 'super_admin' || 
        $_SESSION['role_name'] === 'limited_admin'
    );
}

// Handle customer actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    try {
        switch ($_POST['action']) {
            case 'add_customer':
                try {
                    // Only admin, super_admin, and limited_admin can add customers
                    if (!hasAdminAccess()) {
                        throw new Exception('You do not have permission to add customers');
                    }

                    // Validate required fields
                    if (empty($_POST['name'])) {
                        throw new Exception("Customer name is required");
                    }

                    // Start transaction
                    $db->beginTransaction();

                    try {
                        // Insert customer
                        $query = "INSERT INTO customers (name, email, phone, address, notes) 
                                 VALUES (:name, :email, :phone, :address, :notes)";
                        $stmt = $db->prepare($query);
                        
                        // Bind parameters
                        $stmt->execute([
                            ':name' => $_POST['name'],
                            ':email' => $_POST['email'] ?? null,
                            ':phone' => $_POST['phone'] ?? null,
                            ':address' => $_POST['address'] ?? null,
                            ':notes' => $_POST['notes'] ?? null
                        ]);
                        
                        $customer_id = $db->lastInsertId();
                        
                        // Add tags if any
                        if (isset($_POST['tags']) && is_array($_POST['tags'])) {
                            $query = "INSERT INTO customer_tag_relations (customer_id, tag_id) VALUES (:customer_id, :tag_id)";
                            $stmt = $db->prepare($query);
                            
                            foreach ($_POST['tags'] as $tag_id) {
                                $stmt->execute([
                                    ':customer_id' => $customer_id,
                                    ':tag_id' => $tag_id
                                ]);
                            }
                        }
                        
                        // Commit transaction
                        $db->commit();
                        
                        echo json_encode([
                            'success' => true,
                            'message' => 'Customer added successfully'
                        ]);
                        exit();
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        $db->rollBack();
                        throw $e;
                    }
                } catch (Exception $e) {
                    error_log("Error in add_customer: " . $e->getMessage());
                    echo json_encode([
                        'success' => false,
                        'message' => $e->getMessage()
                    ]);
                    exit();
                }
                break;

            case 'update_customer':
                // Only admin, super_admin, and limited_admin can update customers
                if (!hasAdminAccess()) {
                    throw new Exception('You do not have permission to update customers');
                }
                if (!isset($_POST['id']) || !isset($_POST['name'])) {
                    throw new Exception('Required fields are missing');
                }
                
                $db->beginTransaction();
                
                // Update customer details
                $query = "UPDATE customers SET 
                         name = :name,
                         email = :email,
                         phone = :phone,
                         address = :address,
                         notes = :notes
                         WHERE id = :id";
                         
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':name' => $_POST['name'],
                    ':email' => $_POST['email'] ?? null,
                    ':phone' => $_POST['phone'] ?? null,
                    ':address' => $_POST['address'] ?? null,
                    ':notes' => $_POST['notes'] ?? null,
                    ':id' => $_POST['id']
                ]);
                
                // Delete existing tag relations
                $query = "DELETE FROM customer_tag_relations WHERE customer_id = :customer_id";
                $stmt = $db->prepare($query);
                $stmt->execute([':customer_id' => $_POST['id']]);
                
                // Add new tag relations if tags are selected
                if (isset($_POST['tags']) && is_array($_POST['tags'])) {
                    $query = "INSERT INTO customer_tag_relations (customer_id, tag_id) VALUES (:customer_id, :tag_id)";
                    $stmt = $db->prepare($query);
                    
                    foreach ($_POST['tags'] as $tagId) {
                        $stmt->execute([
                            ':customer_id' => $_POST['id'],
                            ':tag_id' => $tagId
                        ]);
                    }
                }
                
                $db->commit();
                $response['success'] = true;
                $response['message'] = 'Customer updated successfully';
                break;

            case 'delete_customer':
                try {
                    // Only super_admin can delete customers
                    if (!isSuperAdmin()) {
                        throw new Exception('You do not have permission to delete customers');
                    }

                    // Validate customer ID
                    if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
                        throw new Exception('Invalid customer ID');
                    }

                    // Start transaction
                    $db->beginTransaction();

                    try {
                        // Check if customer exists
                        $query = "SELECT id FROM customers WHERE id = :id";
                        $stmt = $db->prepare($query);
                        $stmt->execute([':id' => $_POST['id']]);
                        
                        if (!$stmt->fetch()) {
                            throw new Exception('Customer not found');
                        }

                        // First delete customer tags
                        $query = "DELETE FROM customer_tag_relations WHERE customer_id = :id";
                        $stmt = $db->prepare($query);
                        $stmt->execute([':id' => $_POST['id']]);

                        // Then delete the customer
                        $query = "DELETE FROM customers WHERE id = :id";
                        $stmt = $db->prepare($query);
                        $stmt->execute([':id' => $_POST['id']]);

                        // Check if customer was actually deleted
                        if ($stmt->rowCount() === 0) {
                            throw new Exception('Failed to delete customer');
                        }

                        // Commit transaction
                        $db->commit();

                        $response['success'] = true;
                        $response['message'] = 'Customer deleted successfully';
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        $db->rollBack();
                        throw $e;
                    }
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    $response['success'] = false;
                    $response['message'] = $e->getMessage();
                }
                break;
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $response['message'] = $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Fetch all customers with their tags
try {
$query = "SELECT c.*, GROUP_CONCAT(ct.name) as tag_names, GROUP_CONCAT(ct.color) as tag_colors
          FROM customers c
          LEFT JOIN customer_tag_relations ctr ON c.id = ctr.customer_id
          LEFT JOIN customer_tags ct ON ctr.tag_id = ct.id
          GROUP BY c.id
          ORDER BY c.name";
$stmt = $db->prepare($query);
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all available tags
$query = "SELECT * FROM customer_tags ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - Shop Inventory System</title>
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="assets/css/sidebar.css" rel="stylesheet">
    <link href="assets/css/mobile-responsive.css" rel="stylesheet">
    
    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="assets/js/sidebar.js"></script>
    <script src="assets/js/datatables-config.js"></script>
</head>
<body>
    <!-- Mobile Menu Toggle Button -->
    <button class="mobile-menu-toggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay"></div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
        
            <a href="index.php" class="sidebar-brand">
                <i class="fas fa-cash-register"></i>SHOP System</a>
        </div>
        <div class="sidebar-menu">
            <div class="sidebar-item">
                <a href="index.php" class="sidebar-link">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="sidebar-item">
                <a href="products.php" class="sidebar-link">
                    <i class="fas fa-box"></i>
                    <span>Products</span>
                </a>
            </div>
            <div class="sidebar-item">
                <a href="sales.php" class="sidebar-link">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Sales</span>
                </a>
            </div>
            <div class="sidebar-item">
                <a href="customers.php" class="sidebar-link active">
                    <i class="fas fa-users"></i>
                    <span>Customers</span>
                </a>
            </div>
            <div class="sidebar-item">
                <a href="reports.php" class="sidebar-link">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </div>
            <div class="sidebar-item">
                <a href="settings.php" class="sidebar-link">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </div>
            <div class="sidebar-item mt-auto">
                <a href="logout.php" class="sidebar-link text-danger">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
    <div class="row mb-4">
        <div class="col-12">
            
            <h2>Customer Management</h2>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" class="form-control" placeholder="Search customers...">
            </div>
        </div>
        <div class="col-md-6">
            <div class="d-flex gap-2">
                <?php if (hasAdminAccess()): ?>
                    <button class="btn btn-primary ms-auto" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                        <i class="fas fa-plus-circle me-2"></i> Add New Customer
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Customer Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Customer List</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="customerTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>Tags</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($customer['name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($customer['email'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($customer['phone'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($customer['address'] ?? ''); ?></td>
                                <td>
                                    <?php if (!empty($customer['tag_names'])): ?>
                                        <?php
                                        $tag_names = explode(',', $customer['tag_names']);
                                        $tag_colors = explode(',', $customer['tag_colors']);
                                        foreach ($tag_names as $index => $tag_name) {
                                            $tag_color = $tag_colors[$index] ?? 'secondary'; // Default to secondary if color is missing
                                            echo '<span class="badge bg-' . htmlspecialchars($tag_color) . ' me-1">' . htmlspecialchars($tag_name) . '</span>';
                                        }
                                        ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No Tags</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if (hasAdminAccess()): ?>
                                        <button class="btn btn-sm btn-warning edit-customer" 
                                                data-id="<?php echo $customer['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($customer['name'] ?? ''); ?>"
                                                data-email="<?php echo htmlspecialchars($customer['email'] ?? ''); ?>"
                                                data-phone="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>"
                                                data-address="<?php echo htmlspecialchars($customer['address'] ?? ''); ?>"
                                                data-notes="<?php echo htmlspecialchars($customer['notes'] ?? ''); ?>"
                                                title="Edit Customer">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if (isSuperAdmin()): ?>
                                        <button class="btn btn-sm btn-danger delete-customer" 
                                                data-id="<?php echo $customer['id']; ?>" 
                                                title="Delete Customer">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($customers)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No customers found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Customer Modal -->
    <?php if (hasAdminAccess()): ?>
        <div class="modal fade" id="addCustomerModal" tabindex="-1" aria-labelledby="addCustomerModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Customer</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addCustomerForm">
                            <div class="mb-3">
                                    <label for="name" class="form-label">Customer Name</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email">
                            </div>
                            <div class="mb-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="tel" class="form-control" id="phone" name="phone">
                            </div>
                            <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                    <label for="notes" class="form-label">Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tags</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($tags as $tag): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="tags[]" 
                                                       value="<?php echo $tag['id']; ?>" id="tag<?php echo $tag['id']; ?>">
                                                <label class="form-check-label" for="tag<?php echo $tag['id']; ?>">
                                                    <span class="badge" style="background-color: <?php echo $tag['color']; ?>">
                                                <?php echo htmlspecialchars($tag['name']); ?>
                                                    </span>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="submitAddCustomer()">Add Customer</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Customer Modal -->
        <div class="modal fade" id="editCustomerModal" tabindex="-1" aria-labelledby="editCustomerModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Customer</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="editCustomerForm">
                            <input type="hidden" id="editCustomerId" name="id">
                            <div class="mb-3">
                                <label for="editName" class="form-label">Customer Name</label>
                                <input type="text" class="form-control" id="editName" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="editEmail" class="form-label">Email</label>
                                <input type="email" class="form-control" id="editEmail" name="email">
                            </div>
                            <div class="mb-3">
                                <label for="editPhone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="editPhone" name="phone">
                            </div>
                            <div class="mb-3">
                                <label for="editAddress" class="form-label">Address</label>
                                <textarea class="form-control" id="editAddress" name="address" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="editNotes" class="form-label">Notes</label>
                                <textarea class="form-control" id="editNotes" name="notes" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tags</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($tags as $tag): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="tags[]" 
                                                   value="<?php echo $tag['id']; ?>" id="editTag<?php echo $tag['id']; ?>">
                                            <label class="form-check-label" for="editTag<?php echo $tag['id']; ?>">
                                                <span class="badge" style="background-color: <?php echo $tag['color']; ?>">
                                                    <?php echo htmlspecialchars($tag['name']); ?>
                                                </span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveEditCustomer">Save Changes</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Import Customer Modal -->
    <div class="modal fade" id="importCustomerModal" tabindex="-1" role="dialog" aria-labelledby="importCustomerModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importCustomerModalLabel">Import Customers</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="importCustomerForm" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="csvFile">Select CSV File</label>
                            <input type="file" class="form-control-file" id="csvFile" name="csvFile" accept=".csv" required>
                            <small class="form-text text-muted">
                                Download the template first to ensure correct format. Required columns: Name, Email, Phone, Address, Notes
                            </small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="importCustomerBtn">Import</button>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container"></div>

    <script>
        $(document).ready(function() {
            // Initialize DataTable with advanced features
            const customerTable = $('#customerTable').DataTable({
                responsive: true,
                dom: '<"row"<"col-sm-12 col-md-6"B><"col-sm-12 col-md-6"f>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                buttons: [
                    {
                        extend: 'collection',
                        text: '<i class="fas fa-download me-2"></i>Export',
                        buttons: [
                            {
                                extend: 'excel',
                                text: '<i class="fas fa-file-excel me-2"></i>Excel',
                                className: 'btn btn-success btn-sm',
                                exportOptions: {
                                    columns: [0, 1, 2, 3, 4]
                                }
                            },
                            {
                                extend: 'pdf',
                                text: '<i class="fas fa-file-pdf me-2"></i>PDF',
                                className: 'btn btn-danger btn-sm',
                                exportOptions: {
                                    columns: [0, 1, 2, 3, 4]
                                }
                            },
                            {
                                extend: 'print',
                                text: '<i class="fas fa-print me-2"></i>Print',
                                className: 'btn btn-info btn-sm',
                                exportOptions: {
                                    columns: [0, 1, 2, 3, 4]
                                }
                            }
                        ]
                    }
                ],
                language: {
                    search: "",
                    searchPlaceholder: "Search customers...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ customers",
                    infoEmpty: "No customers available",
                    infoFiltered: "(filtered from _MAX_ total customers)",
                    zeroRecords: "No matching customers found",
                    paginate: {
                        first: '<i class="fas fa-angle-double-left"></i>',
                        previous: '<i class="fas fa-angle-left"></i>',
                        next: '<i class="fas fa-angle-right"></i>',
                        last: '<i class="fas fa-angle-double-right"></i>'
                    }
                },
                order: [[0, 'asc']], // Sort by name ascending
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                columnDefs: [
                    {
                        targets: -1, // Target the last column (Actions)
                        orderable: false,
                        searchable: false
                    }
                ]
            });

            // Add tooltips to action buttons
            $('.edit-customer, .delete-customer').tooltip({
                placement: 'top'
            });

            // Handle edit customer button click
            $('#customerTable tbody').on('click', '.edit-customer', function() {
                const customerId = $(this).data('id');
                const customerName = $(this).data('name');
                const customerEmail = $(this).data('email');
                const customerPhone = $(this).data('phone');
                const customerAddress = $(this).data('address');
                const customerNotes = $(this).data('notes');

                // Populate the edit form
                $('#editCustomerId').val(customerId);
                $('#editName').val(customerName);
                $('#editEmail').val(customerEmail);
                $('#editPhone').val(customerPhone);
                $('#editAddress').val(customerAddress);
                $('#editNotes').val(customerNotes);

                // Reset all tag checkboxes
                $('input[name="tags[]"]').prop('checked', false);

                // Show the edit modal
                $('#editCustomerModal').modal('show');
            });

            // Handle delete customer button click
            $('#customerTable tbody').on('click', '.delete-customer', function() {
                const customerId = $(this).data('id');
                const customerName = $(this).closest('tr').find('td:first').text().trim();
                const deleteButton = $(this);
                const row = deleteButton.closest('tr');
                
                if (confirm(`Are you sure you want to delete customer "${customerName}"?`)) {
                    // Show loading state
                    deleteButton.prop('disabled', true)
                        .html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
                    
                    $.ajax({
                        url: 'customers.php',
                        type: 'POST',
                        data: {
                            action: 'delete_customer',
                            id: customerId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                // Show success notification
                                showNotification(
                                    'Customer Deleted',
                                    `Customer "${customerName}" has been deleted successfully!`,
                                    'success'
                                );
                                
                                // Remove the row from DataTable
                                const rowToRemove = customerTable.row(row);
                                rowToRemove.remove().draw(false);
                            } else {
                                showNotification(
                                    'Error',
                                    response.message || 'Failed to delete customer',
                                    'danger'
                                );
                            }
                        },
                        error: function(xhr, status, error) {
                            showNotification(
                                'Error',
                                'An error occurred while deleting the customer. Please try again.',
                                'danger'
                            );
                            console.error('Delete customer error:', error);
                        },
                        complete: function() {
                            // Restore button state
                            deleteButton.prop('disabled', false)
                                .html('<i class="fas fa-trash-alt"></i>');
                        }
                    });
                }
            });

            // Function to show notifications
            function showNotification(title, message, type = 'success') {
                const toast = $(`
                    <div class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
                        <div class="toast-header bg-${type} text-white">
                            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} me-2"></i>
                            <strong class="me-auto">${title}</strong>
                            <small>Just now</small>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                        </div>
                        <div class="toast-body">
                            ${message}
                        </div>
                    </div>
                `);
                
                $('.toast-container').append(toast);
                const bsToast = new bootstrap.Toast(toast);
                bsToast.show();
                
                // Remove toast after it's hidden
                toast.on('hidden.bs.toast', function() {
                    toast.remove();
                });
            }

            // Handle add customer form submission
            function submitAddCustomer() {
                const form = $('#addCustomerForm');
                const submitButton = form.closest('.modal').find('button[type="button"].btn-primary');
                const originalText = submitButton.text();
                
                // Show loading state
                submitButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...');
                
                // Get form data
                const formData = new FormData(form[0]);
                formData.append('action', 'add_customer');
                
                // Get selected tags
                const selectedTags = [];
                form.find('input[name="tags[]"]:checked').each(function() {
                    selectedTags.push($(this).val());
                });
                formData.delete('tags[]');
                selectedTags.forEach(tagId => {
                    formData.append('tags[]', tagId);
                });
                
                $.ajax({
                    url: 'customers.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        try {
                            const result = typeof response === 'string' ? JSON.parse(response) : response;
                            
                            if (result.success) {
                                // Show success notification
                                showNotification(
                                    'Customer Added',
                                    `Customer "${formData.get('name')}" has been added successfully!`,
                                    'success'
                                );
                                
                                // Reset form and close modal
                                form[0].reset();
                                $('#addCustomerModal').modal('hide');
                                
                                // Reload page after a short delay
                                setTimeout(() => {
                                    location.reload();
                                }, 2000);
                            } else {
                                throw new Error(result.message || 'Failed to add customer');
                            }
                        } catch (error) {
                            showNotification(
                                'Error',
                                error.message || 'An error occurred while adding the customer',
                                'danger'
                            );
                        }
                    },
                    error: function(xhr, status, error) {
                        showNotification(
                            'Error',
                            'An error occurred while adding the customer. Please try again.',
                            'danger'
                        );
                    },
                    complete: function() {
                        // Restore button state
                        submitButton.prop('disabled', false).text(originalText);
                    }
                });
            }

            // Make submitAddCustomer function globally available
            window.submitAddCustomer = submitAddCustomer;

            // Handle edit customer form submission
            $('#saveEditCustomer').click(function() {
                const form = $('#editCustomerForm');
                const submitButton = $(this);
                const originalText = submitButton.text();
                const customerName = $('#editName').val();
                
                // Show loading state
                submitButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
                
                // Get form data
                const formData = new FormData(form[0]);
                formData.append('action', 'update_customer');
                
                // Get selected tags
                const selectedTags = [];
                form.find('input[name="tags[]"]:checked').each(function() {
                    selectedTags.push($(this).val());
                });
                formData.delete('tags[]');
                selectedTags.forEach(tagId => {
                    formData.append('tags[]', tagId);
                });
                
                $.ajax({
                    url: 'customers.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        try {
                            const result = typeof response === 'string' ? JSON.parse(response) : response;
                            
                            if (result.success) {
                                // Show success notification
                                showNotification(
                                    'Customer Updated',
                                    `Customer "${customerName}" has been updated successfully!`,
                                    'success'
                                );
                                
                                // Close modal
                                $('#editCustomerModal').modal('hide');
                                
                                // Reload page after a short delay
                                setTimeout(() => {
                                    location.reload();
                                }, 2000);
                            } else {
                                throw new Error(result.message || 'Failed to update customer');
                            }
                        } catch (error) {
                            showNotification(
                                'Error',
                                error.message || 'An error occurred while updating the customer',
                                'danger'
                            );
                        }
                    },
                    error: function(xhr, status, error) {
                        showNotification(
                            'Error',
                            'An error occurred while updating the customer. Please try again.',
                            'danger'
                        );
                    },
                    complete: function() {
                        // Restore button state
                        submitButton.prop('disabled', false).text(originalText);
                    }
                });
            });

            // Handle customer import
            $('#importCustomerBtn').click(function() {
                var formData = new FormData($('#importCustomerForm')[0]);
                
                $.ajax({
                    url: 'handlers/customers_import.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.status === 'success') {
                            alert(response.message);
                            if (response.errors && response.errors.length > 0) {
                                alert('Errors occurred:\n' + response.errors.join('\n'));
                            }
                            location.reload();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error occurred while importing customers');
                    }
                });
            });
        });
    </script>
</body>
</html> 