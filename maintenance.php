<?php
$page_title = "Maintenance Mode";
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
                                    <h1 class="h4 text-gray-900 mb-4">Maintenance Mode</h1>
                                    <p class="mb-4">The system is currently undergoing maintenance. We apologize for any inconvenience. Please try again later.</p>
                                    <div class="mb-4">
                                        <i class="fas fa-tools fa-4x text-gray-300"></i>
                                    </div>
                                    <p class="text-muted">Expected completion time: <span id="maintenanceTime">Unknown</span></p>
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
    // Get maintenance end time from server
    $.ajax({
        url: "handlers/get_maintenance_time.php",
        method: "GET",
        success: function(response) {
            if (response.success) {
                $("#maintenanceTime").text(response.end_time);
            }
        }
    });
});
</script>';

require_once 'layout.php';
?> 