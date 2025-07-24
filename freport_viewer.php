<?php
session_start();
// Allow admins and project managers to access this page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["role_id"], [1, 2, 3])) {
    header("location: index.php");
    exit;
}

require_once 'config/db_connect.php'; // Ensures user has a valid session.
?>

<!DOCTYPE html>
<!-- FIXED: Changed theme to dark for consistency -->
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>F-Report Viewer - PourDay App</title>
    <link href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-bootstrap@2.0.12/dist/css/modus-bootstrap.min.css" rel="stylesheet">
    <!-- FIXED: Added missing Modus Icons stylesheet -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-icons@1.16.0/dist/modus-solid/fonts/modus-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700&display=fallback"/>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'topbar_mobile.html'; ?>
<?php include 'sidebar.html'; ?>

<!-- FIXED: Added main content wrapper for correct layout -->
<main class="page-content-wrapper">
    <div class="container-fluid p-4">
        <div class="card" id="reportSelectionCard">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">View F-Number Report</h3>
            </div>
            <div class="card-body">
                <form id="selectReportForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="projectSelection" class="form-label">1. Select Project</label>
                            <select class="form-select" id="projectSelection" required>
                                <option value="">Loading Projects...</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="reportSelection" class="form-label">2. Select Report</label>
                            <select class="form-select" id="reportSelection" required disabled>
                                <option value="">-- Select a project first --</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="userSelection" class="form-label">3. Prepared By (for PDF)</label>
                            <select class="form-select" id="userSelection" disabled>
                                <option value="">-- Report uploader default --</option>
                            </select>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary" id="viewReportBtn" disabled>
                        <i class="modus-icons notranslate">visibility</i> View Report
                    </button>
                </form>
            </div>
        </div>

        <div class="card mt-4" id="reportDisplayContainer" style="display:none;">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0" id="reportTitle">Report Preview</h5>
                <button type="button" class="btn btn-secondary btn-sm" id="downloadPdfBtn">
                    <i class="modus-icons notranslate">download</i> Download PDF
                </button>
            </div>
            <div class="card-body">
                <iframe id="reportDisplayFrame" style="width: 100%; height: 75vh; border: none;"></iframe>
                <div id="pdfStatus" class="mt-2 text-end"></div>
            </div>
        </div>
    </div>
</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
<script src="js/freport_viewer.js"></script>

</body>
</html>