<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = "Register";
$page_content = '
<div class="container">
    <div class="row justify-content-center">
        <div class="col-xl-6 col-lg-6 col-md-9">
            <div class="card o-hidden border-0 shadow-lg my-5">
                <div class="card-body p-0">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="p-5">
                                <div class="text-center">
                                    <h1 class="h4 text-gray-900 mb-4">Create an Account!</h1>
                                </div>
                                <form class="user" id="registerForm">
                                    <div class="form-group">
                                        <input type="text" class="form-control form-control-user" id="full_name" name="full_name" placeholder="Full Name" required>
                                    </div>
                                    <div class="form-group">
                                        <input type="email" class="form-control form-control-user" id="email" name="email" placeholder="Email Address" required>
                                    </div>
                                    <div class="form-group">
                                        <input type="password" class="form-control form-control-user" id="password" name="password" placeholder="Password" required>
                                    </div>
                                    <div class="form-group">
                                        <input type="password" class="form-control form-control-user" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-user btn-block">
                                        Register Account
                                    </button>
                                    <div id="registerError" class="alert alert-danger mt-3" style="display: none;"></div>
                                </form>
                                <hr>
                                <div class="text-center">
                                    <a class="small" href="login.php">Already have an account? Login!</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>';

$page_scripts = '
<script>
$(document).ready(function() {
    $("#registerForm").submit(function(e) {
        e.preventDefault();
        
        const full_name = $("#full_name").val();
        const email = $("#email").val();
        const password = $("#password").val();
        const confirm_password = $("#confirm_password").val();
        const $errorDiv = $("#registerError");

        // Clear previous errors
        $errorDiv.hide().text("");

        // Validate inputs
        if (!full_name || !email || !password || !confirm_password) {
            $errorDiv.text("All fields are required").show();
            return;
        }

        if (password !== confirm_password) {
            $errorDiv.text("Passwords do not match").show();
            return;
        }

        if (password.length < 8) {
            $errorDiv.text("Password must be at least 8 characters long").show();
            return;
        }

        // Show loading state
        const $submitBtn = $(this).find("button[type=submit]");
        const originalText = $submitBtn.text();
        $submitBtn.prop("disabled", true).html(\'<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Registering...\');

        $.ajax({
            url: "handlers/register.php",
            method: "POST",
            data: {
                full_name: full_name,
                email: email,
                password: password,
                confirm_password: confirm_password
            },
            success: function(response) {
                if (response.success) {
                    window.location.href = "index.php";
                } else {
                    $errorDiv.text(response.message || "Registration failed. Please try again.").show();
                    $submitBtn.prop("disabled", false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = "An error occurred. Please try again later.";
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                $errorDiv.text(errorMessage).show();
                $submitBtn.prop("disabled", false).text(originalText);
                console.error("Registration error:", error, "Status:", status, "Response:", xhr.responseText);
            }
        });
    });
});
</script>';

require_once 'login-layout.php';
?> 