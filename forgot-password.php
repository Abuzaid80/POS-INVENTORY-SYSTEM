<?php
$page_title = "Forgot Password";
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
                                    <h1 class="h4 text-gray-900 mb-2">Forgot Your Password?</h1>
                                    <p class="mb-4">We get it, stuff happens. Just enter your email address below and we\'ll send you a link to reset your password!</p>
                                </div>
                                <form class="user" id="forgotPasswordForm">
                                    <div class="form-group">
                                        <input type="email" class="form-control form-control-user" id="email" placeholder="Enter Email Address..." required>
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
    $("#forgotPasswordForm").submit(function(e) {
        e.preventDefault();
        
        const email = $("#email").val();

        $.ajax({
            url: "handlers/forgot_password.php",
            method: "POST",
            data: { email: email },
            success: function(response) {
                if (response.success) {
                    alert("Password reset link has been sent to your email.");
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