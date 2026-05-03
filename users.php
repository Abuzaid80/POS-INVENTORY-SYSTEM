<?php
$page_title = 'User Management';
$current_page = 'users';
require_once 'includes/header.php';

// Only super admin can access this page
$auth->requirePermission('manage_users');

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    try {
        switch ($_POST['action']) {
            case 'add_user':
                if (!isset($_POST['username']) || !isset($_POST['password']) || !isset($_POST['role_id'])) {
                    throw new Exception('Required fields are missing');
                }
                
                // Check if username already exists
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$_POST['username']]);
                if ($stmt->fetch()) {
                    throw new Exception('Username already exists');
                }
                
                // Insert new user
                $stmt = $db->prepare("INSERT INTO users (username, password, role_id, status) VALUES (?, ?, ?, 'active')");
                $stmt->execute([
                    $_POST['username'],
                    password_hash($_POST['password'], PASSWORD_DEFAULT),
                    $_POST['role_id']
                ]);
                
                $response['success'] = true;
                $response['message'] = 'User added successfully';
                break;

            case 'update_user':
                if (!isset($_POST['id']) || !isset($_POST['username']) || !isset($_POST['role_id'])) {
                    throw new Exception('Required fields are missing');
                }
                
                // Check if username exists for other users
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $stmt->execute([$_POST['username'], $_POST['id']]);
                if ($stmt->fetch()) {
                    throw new Exception('Username already exists');
                }
                
                // Update user
                $query = "UPDATE users SET username = ?, role_id = ?, status = ?";
                $params = [$_POST['username'], $_POST['role_id'], $_POST['status']];
                
                // Update password if provided
                if (!empty($_POST['password'])) {
                    $query .= ", password = ?";
                    $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                }
                
                $query .= " WHERE id = ?";
                $params[] = $_POST['id'];
                
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                
                $response['success'] = true;
                $response['message'] = 'User updated successfully';
                break;

            case 'delete_user':
                if (!isset($_POST['id'])) {
                    throw new Exception('User ID is required');
                }
                
                // Don't allow deleting the last super admin
                $stmt = $db->prepare("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'super_admin'");
                $stmt->execute();
                $super_admin_count = $stmt->fetchColumn();
                
                $stmt = $db->prepare("SELECT r.name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
                $stmt->execute([$_POST['id']]);
                $user_role = $stmt->fetchColumn();
                
                if ($user_role === 'super_admin' && $super_admin_count <= 1) {
                    throw new Exception('Cannot delete the last super admin');
                }
                
                // Delete user
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                
                $response['success'] = true;
                $response['message'] = 'User deleted successfully';
                break;
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Fetch all users with their roles
try {
    $query = "SELECT u.*, r.name as role_name 
              FROM users u 
              JOIN roles r ON u.role_id = r.id 
              ORDER BY u.username";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all roles
    $query = "SELECT * FROM roles ORDER BY name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2>User Management</h2>
    </div>
</div>

<!-- Add User Button -->
<div class="row mb-4">
    <div class="col-12">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-user-plus me-2"></i>Add New User
        </button>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="usersTable">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['role_name']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning edit-user" 
                                        data-id="<?php echo $user['id']; ?>"
                                        data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                        data-role="<?php echo $user['role_id']; ?>"
                                        data-status="<?php echo $user['status']; ?>"
                                        title="Edit User">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($user['role_name'] !== 'super_admin' || $auth->isSuperAdmin()): ?>
                                    <button class="btn btn-sm btn-danger delete-user" 
                                            data-id="<?php echo $user['id']; ?>"
                                            title="Delete User">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addUserForm">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="role_id" class="form-label">Role</label>
                        <select class="form-select" id="role_id" name="role_id" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>">
                                    <?php echo htmlspecialchars($role['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveAddUser">Add User</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editUserForm">
                    <input type="hidden" id="editUserId" name="id">
                    <div class="mb-3">
                        <label for="editUsername" class="form-label">Username</label>
                        <input type="text" class="form-control" id="editUsername" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="editPassword" class="form-label">Password</label>
                        <input type="password" class="form-control" id="editPassword" name="password" 
                               placeholder="Leave blank to keep current password">
                    </div>
                    <div class="mb-3">
                        <label for="editRoleId" class="form-label">Role</label>
                        <select class="form-select" id="editRoleId" name="role_id" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>">
                                    <?php echo htmlspecialchars($role['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editStatus" class="form-label">Status</label>
                        <select class="form-select" id="editStatus" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveEditUser">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    const usersTable = $('#usersTable').DataTable({
        responsive: true,
        order: [[0, 'asc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        columnDefs: [
            {
                targets: -1,
                orderable: false,
                searchable: false
            }
        ]
    });

    // Handle add user form submission
    $('#saveAddUser').on('click', function() {
        const formData = new FormData($('#addUserForm')[0]);
        formData.append('action', 'add_user');

        $.ajax({
            url: 'users.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showToast('User added successfully!', 'success');
                    $('#addUserModal').modal('hide');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showToast(response.message || 'Error adding user', 'error');
                }
            },
            error: function() {
                showToast('Error adding user. Please try again.', 'error');
            }
        });
    });

    // Handle edit user button click
    $('.edit-user').on('click', function() {
        const userId = $(this).data('id');
        const username = $(this).data('username');
        const roleId = $(this).data('role');
        const status = $(this).data('status');

        $('#editUserId').val(userId);
        $('#editUsername').val(username);
        $('#editRoleId').val(roleId);
        $('#editStatus').val(status);
        $('#editPassword').val('');

        $('#editUserModal').modal('show');
    });

    // Handle save edit button click
    $('#saveEditUser').on('click', function() {
        const formData = new FormData($('#editUserForm')[0]);
        formData.append('action', 'update_user');

        $.ajax({
            url: 'users.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showToast('User updated successfully!', 'success');
                    $('#editUserModal').modal('hide');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showToast(response.message || 'Error updating user', 'error');
                }
            },
            error: function() {
                showToast('Error updating user. Please try again.', 'error');
            }
        });
    });

    // Handle delete user button click
    $('.delete-user').on('click', function() {
        const userId = $(this).data('id');
        if (confirm('Are you sure you want to delete this user?')) {
            const formData = new FormData();
            formData.append('action', 'delete_user');
            formData.append('id', userId);

            $.ajax({
                url: 'users.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showToast('User deleted successfully!', 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showToast(response.message || 'Error deleting user', 'error');
                    }
                },
                error: function() {
                    showToast('Error deleting user. Please try again.', 'error');
                }
            });
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?> 