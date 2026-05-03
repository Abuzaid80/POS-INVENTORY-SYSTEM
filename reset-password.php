<?php
$page_title = "Reset Password";
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
                                    <h1 class="h4 text-gray-900 mb-2">Reset Your Password</h1>
                                    <p class="mb-4">Please enter your new password below.</p>
                                </div>
                                <form class="user" id="resetPasswordForm">
                                    <input type="hidden" id="token" value="' . $_GET['token'] . '">
                                    <div class="form-group">
                                        <input type="password" class="form-control form-control-user" id="newPassword" placeholder="New Password" required>
                                    </div>
                                    <div class="form-group">
                                        <input type="password" class="form-control form-control-user" id="confirmPassword" placeholder="Confirm New Password" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-user btn-block">
                                        Reset Password
                                    </button>
                                </form>
                                <hr>
                                <div class="text-center">
                                    <a class="small" href="login.php">Back to Login</a>
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
    $("#resetPasswordForm").submit(function(e) {
        e.preventDefault();
        
        const token = $("#token").val();
        const newPassword = $("#newPassword").val();
        const confirmPassword = $("#confirmPassword").val();

        if (newPassword !== confirmPassword) {
            alert("Passwords do not match!");
            return;
        }

        $.ajax({
            url: "handlers/reset_password.php",
            method: "POST",
            data: {
                token: token,
                new_password: newPassword
            },
            success: function(response) {
                if (response.success) {
                    alert("Password has been reset successfully!");
                    window.location.href = "login.php";
                } else {
                    alert("Error: " + response.message);
                }
            }
        });
    });
});
</script>';

require_once 'layout.php';
?> 