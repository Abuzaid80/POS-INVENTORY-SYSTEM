<?php
$page_title = "Profile";
$page_content = '
<div class="row">
    <div class="col-xl-4 col-lg-5">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Profile Picture</h6>
            </div>
            <div class="card-body text-center">
                <img class="img-profile rounded-circle mb-3" id="profileImage" src="assets/img/default-avatar.png" style="width: 150px; height: 150px;">
                <div class="mt-3">
                    <button type="button" class="btn btn-primary" id="changeProfilePicture">
                        <i class="fas fa-camera"></i> Change Picture
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-8 col-lg-7">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Account Details</h6>
            </div>
            <div class="card-body">
                <form id="profileForm">
                    <div class="row mb-3">
                        <div class="col-sm-6">
                            <label for="firstName" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="firstName" required>
                        </div>
                        <div class="col-sm-6">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="lastName" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone">
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Change Password</h6>
            </div>
            <div class="card-body">
                <form id="passwordForm">
                    <div class="mb-3">
                        <label for="currentPassword" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="currentPassword" required>
                    </div>
                    <div class="mb-3">
                        <label for="newPassword" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="newPassword" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirmPassword" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirmPassword" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Change Password</button>
                </form>
            </div>
        </div>
    </div>
</div>';

$page_scripts = '
<script>
$(document).ready(function() {
    // Load profile data
    function loadProfile() {
        $.ajax({
            url: "handlers/get_profile.php",
            method: "GET",
            success: function(response) {
                if (response.success) {
                    $("#firstName").val(response.data.first_name);
                    $("#lastName").val(response.data.last_name);
                    $("#email").val(response.data.email);
                    $("#phone").val(response.data.phone);
                    $("#address").val(response.data.address);
                    if (response.data.profile_image) {
                        $("#profileImage").attr("src", response.data.profile_image);
                    }
                }
            }
        });
    }

    // Save profile changes
    $("#profileForm").submit(function(e) {
        e.preventDefault();
        
        const data = {
            first_name: $("#firstName").val(),
            last_name: $("#lastName").val(),
            email: $("#email").val(),
            phone: $("#phone").val(),
            address: $("#address").val()
        };

        $.ajax({
            url: "handlers/update_profile.php",
            method: "POST",
            data: data,
            success: function(response) {
                if (response.success) {
                    alert("Profile updated successfully!");
                } else {
                    alert("Error updating profile: " + response.message);
                }
            }
        });
    });

    // Change password
    $("#passwordForm").submit(function(e) {
        e.preventDefault();
        
        const currentPassword = $("#currentPassword").val();
        const newPassword = $("#newPassword").val();
        const confirmPassword = $("#confirmPassword").val();

        if (newPassword !== confirmPassword) {
            alert("New passwords do not match!");
            return;
        }

        $.ajax({
            url: "handlers/change_password.php",
            method: "POST",
            data: {
                current_password: currentPassword,
                new_password: newPassword
            },
            success: function(response) {
                if (response.success) {
                    alert("Password changed successfully!");
                    $("#passwordForm")[0].reset();
                } else {
                    alert("Error changing password: " + response.message);
                }
            }
        });
    });

    // Change profile picture
    $("#changeProfilePicture").click(function() {
        const input = document.createElement("input");
        input.type = "file";
        input.accept = "image/*";
        
        input.onchange = function(e) {
            const file = e.target.files[0];
            const formData = new FormData();
            formData.append("profile_image", file);

            $.ajax({
                url: "handlers/update_profile_image.php",
                method: "POST",
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $("#profileImage").attr("src", response.image_url);
                        alert("Profile picture updated successfully!");
                    } else {
                        alert("Error updating profile picture: " + response.message);
                    }
                }
            });
        };
        
        input.click();
    });

    // Initial load
    loadProfile();
});
</script>';

require_once 'layout.php';
?> 