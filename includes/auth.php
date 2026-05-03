<?php
require_once __DIR__ . '/../config/database.php';

class Auth {
    private $conn;
    private $table_name = "users";
    private $user = null;
    private $permissions = [];

    public function __construct($db) {
        $this->conn = $db;
        $this->loadUser();
    }

    private function loadUser() {
        if (isset($_SESSION['user_id'])) {
            // Get user data
            $stmt = $this->conn->prepare("
                SELECT u.*, r.name as role_name 
                FROM " . $this->table_name . " u 
                JOIN roles r ON u.role_id = r.id 
                WHERE u.id = ? AND u.status = 'active'
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $this->user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($this->user) {
                // Get user permissions
                $stmt = $this->conn->prepare("
                    SELECT p.name 
                    FROM permissions p 
                    JOIN role_permissions rp ON p.id = rp.permission_id 
                    WHERE rp.role_id = ?
                ");
                $stmt->execute([$this->user['role_id']]);
                $this->permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
        }
    }

    public function login($username, $password) {
        try {
            error_log("Auth::login - Starting login process for username: " . $username);
            
            // Prepare query
            $query = "SELECT u.id, u.username, u.password, u.role_id, u.status, r.name as role_name 
                     FROM " . $this->table_name . " u 
                     LEFT JOIN roles r ON u.role_id = r.id 
                     WHERE u.username = :username";
            
            error_log("Auth::login - Executing query: " . $query);
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":username", $username);
            $stmt->execute();

            error_log("Auth::login - Query executed. Row count: " . $stmt->rowCount());

            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                error_log("Auth::login - User found. Status: " . $row['status']);
                
                // Verify password
                $password_verify_result = password_verify($password, $row['password']);
                error_log("Auth::login - Password verification result: " . ($password_verify_result ? "true" : "false"));
                
                if ($password_verify_result) {
                    if ($row['status'] === 'active') {
                        error_log("Auth::login - Login successful. Setting session variables.");
                        // Set session variables
                        $_SESSION['user_id'] = $row['id'];
                        $_SESSION['username'] = $row['username'];
                        $_SESSION['role_id'] = $row['role_id'];
                        $_SESSION['role_name'] = $row['role_name'];
                        $_SESSION['logged_in'] = true;

                        // Reload user data
                        $this->loadUser();
                        return true;
                    } else {
                        error_log("Auth::login - Login failed: User is inactive");
                        return false;
                    }
                } else {
                    error_log("Auth::login - Login failed: Invalid password");
                }
            } else {
                error_log("Auth::login - Login failed: User not found");
            }
            return false;
        } catch (PDOException $e) {
            error_log("Auth::login - Database error: " . $e->getMessage());
            throw new Exception("Database error occurred");
        }
    }

    public function logout() {
        session_unset();
        session_destroy();
        $this->user = null;
        $this->permissions = [];
    }

    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'role_id' => $_SESSION['role_id'],
                'role_name' => $_SESSION['role_name']
            ];
        }
        return null;
    }

    public function hasPermission($permission) {
        if (!$this->isLoggedIn()) {
            return false;
        }

        // Always grant permission to limited_admin for their allowed pages
        if ($this->isLimitedAdmin()) {
            $allowed_permissions = ['manage_products', 'manage_sales', 'manage_customers'];
            if (in_array($permission, $allowed_permissions)) {
                return true;
            }
        }

        try {
            $query = "SELECT p.name 
                     FROM permissions p 
                     JOIN role_permissions rp ON p.id = rp.permission_id 
                     WHERE rp.role_id = :role_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":role_id", $_SESSION['role_id']);
            $stmt->execute();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($row['name'] === $permission) {
                    return true;
                }
            }
            return false;
        } catch (PDOException $e) {
            error_log("Permission check error: " . $e->getMessage());
            return false;
        }
    }

    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }

    public function requirePermission($permission) {
        $this->requireLogin();
        if (!$this->hasPermission($permission)) {
            header('Location: access_denied.php');
            exit;
        }
    }

    public function isSuperAdmin() {
        return $this->user && $this->user['role_name'] === 'super_admin';
    }

    public function isAdmin() {
        return $this->user && ($this->user['role_name'] === 'admin' || $this->user['role_name'] === 'super_admin' || $this->user['role_name'] === 'limited_admin');
    }

    public function isLimitedAdmin() {
        return $this->user && $this->user['role_name'] === 'limited_admin';
    }
} 