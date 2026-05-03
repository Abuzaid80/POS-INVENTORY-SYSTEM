<?php
$page_title = "About";
$page_content = '
<div class="row">
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">About POS Inventory System</h6>
            </div>
            <div class="card-body">
                <p>Welcome to our POS Inventory System, a comprehensive solution designed to streamline your business operations. Our system provides powerful tools for managing inventory, processing sales, and analyzing business performance.</p>
                
                <h5 class="mt-4">Key Features</h5>
                <ul>
                    <li>Real-time inventory tracking</li>
                    <li>Sales management and reporting</li>
                    <li>Customer relationship management</li>
                    <li>Multi-user access with role-based permissions</li>
                    <li>Mobile-friendly interface</li>
                    <li>Data backup and security</li>
                </ul>

                <h5 class="mt-4">System Requirements</h5>
                <ul>
                    <li>Web browser (Chrome, Firefox, Safari, or Edge)</li>
                    <li>Internet connection</li>
                    <li>Modern device (computer, tablet, or smartphone)</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Contact Support</h6>
            </div>
            <div class="card-body">
                <p>Need help or have questions? Our support team is here to assist you.</p>
                <ul class="list-unstyled">
                    <li><i class="fas fa-envelope"></i> support@posinventory.com</li>
                    <li><i class="fas fa-phone"></i> +1 (555) 123-4567</li>
                    <li><i class="fas fa-clock"></i> Monday - Friday, 9:00 AM - 5:00 PM</li>
                </ul>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">System Information</h6>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li><strong>Version:</strong> <span id="systemVersion">Loading...</span></li>
                    <li><strong>Last Updated:</strong> <span id="lastUpdate">Loading...</span></li>
                    <li><strong>Database Size:</strong> <span id="dbSize">Loading...</span></li>
                </ul>
            </div>
        </div>
    </div>
</div>';

$page_scripts = '
<script>
$(document).ready(function() {
    // Load system information
    $.ajax({
        url: "handlers/get_system_info.php",
        method: "GET",
        success: function(response) {
            if (response.success) {
                $("#systemVersion").text(response.version);
                $("#lastUpdate").text(response.last_update);
                $("#dbSize").text(response.db_size);
            }
        }
    });
});
</script>';

require_once 'layout.php';
?> 