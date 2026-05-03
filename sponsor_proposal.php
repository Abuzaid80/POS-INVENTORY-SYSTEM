<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'config/database.php';

// Initialize messages
$error = '';
$success = '';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        try {
            // Validate input
            if (empty($_POST['doctor_name'])) {
                throw new Exception("Doctor's name is required");
            }
            if (empty($_POST['specialization'])) {
                throw new Exception("Specialization is required");
            }
            if (empty($_POST['hospital'])) {
                throw new Exception("Hospital name is required");
            }
            if (empty($_POST['contact_number'])) {
                throw new Exception("Contact number is required");
            }
            if (empty($_POST['email'])) {
                throw new Exception("Email is required");
            }
            if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format");
            }
            if (empty($_POST['proposal_details'])) {
                throw new Exception("Proposal details are required");
            }
            if (empty($_POST['sponsorship_amount'])) {
                throw new Exception("Sponsorship amount is required");
            }
            if (!is_numeric($_POST['sponsorship_amount']) || $_POST['sponsorship_amount'] <= 0) {
                throw new Exception("Sponsorship amount must be a positive number");
            }

            // Insert proposal into database
            $query = "INSERT INTO doctor_sponsorships (
                        doctor_name, specialization, hospital, contact_number, 
                        email, proposal_details, sponsorship_amount, status, 
                        created_by, created_at, years_of_experience, alternate_contact,
                        license_number, qualifications, sponsorship_duration,
                        expected_benefits, previous_sponsorships
                    ) VALUES (
                        :doctor_name, :specialization, :hospital, :contact_number,
                        :email, :proposal_details, :sponsorship_amount, 'pending',
                        :created_by, NOW(), :years_of_experience, :alternate_contact,
                        :license_number, :qualifications, :sponsorship_duration,
                        :expected_benefits, :previous_sponsorships
                    )";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':doctor_name', $_POST['doctor_name']);
            $stmt->bindParam(':specialization', $_POST['specialization']);
            $stmt->bindParam(':hospital', $_POST['hospital']);
            $stmt->bindParam(':contact_number', $_POST['contact_number']);
            $stmt->bindParam(':email', $_POST['email']);
            $stmt->bindParam(':proposal_details', $_POST['proposal_details']);
            $stmt->bindParam(':sponsorship_amount', $_POST['sponsorship_amount']);
            $stmt->bindParam(':created_by', $_SESSION['user_id']);
            $stmt->bindParam(':years_of_experience', $_POST['years_of_experience']);
            $stmt->bindParam(':alternate_contact', $_POST['alternate_contact']);
            $stmt->bindParam(':license_number', $_POST['license_number']);
            $stmt->bindParam(':qualifications', $_POST['qualifications']);
            $stmt->bindParam(':sponsorship_duration', $_POST['sponsorship_duration']);
            $stmt->bindParam(':expected_benefits', $_POST['expected_benefits']);
            $stmt->bindParam(':previous_sponsorships', $_POST['previous_sponsorships']);

            if ($stmt->execute()) {
                $success = "Sponsorship proposal submitted successfully";
            } else {
                throw new Exception("Failed to submit proposal");
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    // Fetch existing proposals
    $query = "SELECT ds.*, u.username as created_by_name 
              FROM doctor_sponsorships ds 
              LEFT JOIN users u ON ds.created_by = u.id 
              ORDER BY ds.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $proposals = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Sponsorship Proposals - POS Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/navigation.css" rel="stylesheet">
    <style>
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }
        .toast {
            min-width: 300px;
        }
        .proposal-card {
            transition: transform 0.2s;
        }
        .proposal-card:hover {
            transform: translateY(-5px);
        }
        .status-badge {
            font-size: 0.875rem;
            padding: 0.35em 0.65em;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php 
    $current_page = 'sponsor_proposal';
    include 'includes/navigation.php'; 
    ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <h2>Doctor Sponsorship Proposals</h2>
                </div>
            </div>

            <!-- Error and Success Messages -->
            <?php if ($error): ?>
                <div class="toast-container">
                    <div class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="toast-container">
                    <div class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo htmlspecialchars($success); ?>
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- New Proposal Button -->
            <div class="row mb-4">
                <div class="col-12">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newProposalModal">
                        <i class="fas fa-plus"></i> New Proposal
                    </button>
                </div>
            </div>

            <!-- Proposals List -->
            <div class="row">
                <?php foreach ($proposals as $proposal): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card proposal-card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><?php echo htmlspecialchars($proposal['doctor_name']); ?></h5>
                            </div>
                            <div class="card-body">
                                <p class="card-text">
                                    <strong>Specialization:</strong> <?php echo htmlspecialchars($proposal['specialization']); ?><br>
                                    <strong>Hospital:</strong> <?php echo htmlspecialchars($proposal['hospital']); ?><br>
                                    <strong>Contact:</strong> <?php echo htmlspecialchars($proposal['contact_number']); ?><br>
                                    <strong>Email:</strong> <?php echo htmlspecialchars($proposal['email']); ?><br>
                                    <strong>Amount:</strong> Rp <?php echo number_format($proposal['sponsorship_amount'], 2); ?>
                                </p>
                                <p class="card-text">
                                    <strong>Proposal Details:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($proposal['proposal_details'])); ?>
                                </p>
                            </div>
                            <div class="card-footer">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge status-badge bg-<?php 
                                        echo match($proposal['status']) {
                                            'approved' => 'success',
                                            'rejected' => 'danger',
                                            default => 'warning'
                                        };
                                    ?>">
                                        <?php echo ucfirst($proposal['status']); ?>
                                    </span>
                                    <small class="text-muted">
                                        Submitted by <?php echo htmlspecialchars($proposal['created_by_name']); ?>
                                        on <?php echo date('M d, Y', strtotime($proposal['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- New Proposal Modal -->
            <div class="modal fade" id="newProposalModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">New Sponsorship Proposal</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="doctor_name" class="form-label">Doctor's Name</label>
                                        <input type="text" class="form-control" id="doctor_name" name="doctor_name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="specialization" class="form-label">Specialization</label>
                                        <input type="text" class="form-control" id="specialization" name="specialization" required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="hospital" class="form-label">Hospital</label>
                                        <input type="text" class="form-control" id="hospital" name="hospital" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="years_of_experience" class="form-label">Years of Experience</label>
                                        <input type="number" class="form-control" id="years_of_experience" name="years_of_experience" min="0" required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="contact_number" class="form-label">Contact Number</label>
                                        <input type="text" class="form-control" id="contact_number" name="contact_number" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="alternate_contact" class="form-label">Alternate Contact Number</label>
                                        <input type="text" class="form-control" id="alternate_contact" name="alternate_contact">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="license_number" class="form-label">Medical License Number</label>
                                        <input type="text" class="form-control" id="license_number" name="license_number" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="qualifications" class="form-label">Qualifications</label>
                                    <textarea class="form-control" id="qualifications" name="qualifications" rows="2" required></textarea>
                                    <small class="text-muted">List all medical degrees and certifications</small>
                                </div>

                                <div class="mb-3">
                                    <label for="proposal_details" class="form-label">Proposal Details</label>
                                    <textarea class="form-control" id="proposal_details" name="proposal_details" rows="4" required></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="sponsorship_amount" class="form-label">Sponsorship Amount</label>
                                        <div class="input-group">
                                            <span class="input-group-text">Rp</span>
                                            <input type="number" class="form-control" id="sponsorship_amount" 
                                                   name="sponsorship_amount" step="0.01" min="0" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="sponsorship_duration" class="form-label">Sponsorship Duration (Months)</label>
                                        <input type="number" class="form-control" id="sponsorship_duration" 
                                               name="sponsorship_duration" min="1" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="expected_benefits" class="form-label">Expected Benefits</label>
                                    <textarea class="form-control" id="expected_benefits" name="expected_benefits" rows="3" required></textarea>
                                    <small class="text-muted">Describe how this sponsorship will benefit both parties</small>
                                </div>

                                <div class="mb-3">
                                    <label for="previous_sponsorships" class="form-label">Previous Sponsorships (if any)</label>
                                    <textarea class="form-control" id="previous_sponsorships" name="previous_sponsorships" rows="2"></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="supporting_documents" class="form-label">Supporting Documents</label>
                                    <input type="file" class="form-control" id="supporting_documents" name="supporting_documents[]" multiple>
                                    <small class="text-muted">Upload relevant documents (CV, certificates, etc.)</small>
                                </div>

                                <div class="text-end">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Submit Proposal</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/navigation.js"></script>
    <script>
        // Initialize all toasts
        document.addEventListener('DOMContentLoaded', function() {
            var toastElList = [].slice.call(document.querySelectorAll('.toast'));
            var toastList = toastElList.map(function(toastEl) {
                return new bootstrap.Toast(toastEl, {
                    autohide: true,
                    delay: 5000
                });
            });
            toastList.forEach(toast => toast.show());
        });
    </script>
</body>
</html> 