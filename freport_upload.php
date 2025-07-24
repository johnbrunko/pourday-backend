<?php
session_start();
// Allow admins and project managers to access
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["role_id"], [1, 2, 3])) {
    header("location: index.php");
    exit;
}

require_once 'config/db_connect.php';
$company_id = $_SESSION["company_id"];

// Fetch Projects for the dropdown
$projects_list = [];
$sql_projects = "SELECT id, job_name FROM projects WHERE company_id = ? AND status = 'Ongoing' ORDER BY job_name ASC";
if ($stmt_projects = mysqli_prepare($link, $sql_projects)) {
    mysqli_stmt_bind_param($stmt_projects, "i", $company_id);
    mysqli_stmt_execute($stmt_projects);
    $result = mysqli_stmt_get_result($stmt_projects);
    while ($row = mysqli_fetch_assoc($result)) {
        $projects_list[] = $row;
    }
    mysqli_stmt_close($stmt_projects);
}
?>

<!DOCTYPE html>
<!-- FIXED: Changed theme to dark for consistency -->
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>F-Report Upload - PourDay App</title>
    <link href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-bootstrap@2.0.12/dist/css/modus-bootstrap.min.css" rel="stylesheet">
    <!-- FIXED: Added missing Modus Icons stylesheet -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-icons@1.16.0/dist/modus-solid/fonts/modus-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700&display=fallback"/>
    <link rel="stylesheet" href="css/style.css">
    <style> .error { color: red; font-size: 0.8rem; } </style>
</head>
<body>
<?php include 'topbar_mobile.html'; ?>
<?php include 'sidebar.html'; ?>

<!-- FIXED: Added main content wrapper for correct layout -->
<main class="page-content-wrapper">
    <div class="container-fluid p-4">
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h3 class="mb-0">F-Number Report Management</h3>
                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#uploadFreportModal">
                    <i class="modus-icons notranslate">upload</i> Upload New Report
                </button>
            </div>
            <div class="card-body">
                <p>This page will eventually display a list of uploaded F-Reports. Use the button above to upload and parse a new report.</p>
            </div>
        </div>
    </div>
</main>

<!-- Upload Modal -->
<div class="modal fade" id="uploadFreportModal" tabindex="-1" aria-labelledby="uploadFreportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadFreportModalLabel">Upload & Parse HTML F-Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="uploadFreportForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="projectSelection" class="form-label">1. Select Project</label>
                            <select class="form-select" id="projectSelection" required>
                                <option value="">-- Select Project --</option>
                                <?php foreach ($projects_list as $project): ?>
                                    <option value="<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['job_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="error project-error"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="taskSelection" class="form-label">2. Select Task</label>
                            <select class="form-select" id="taskSelection" required disabled>
                                <option value="">-- Select project first --</option>
                            </select>
                            <div class="error task-error"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="reportName" class="form-label">3. Report Name</label>
                        <input type="text" class="form-control" id="reportName" placeholder="e.g., Level 1 Pour A" required>
                        <div class="error report-name-error"></div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="htmlFile" class="form-label">4. Select HTML Report File</label>
                            <input type="file" class="form-control" id="htmlFile" accept=".html, .htm" required>
                            <div class="error html-file-error"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="imageFile" class="form-label">5. Select Image (Optional)</label>
                            <input type="file" class="form-control" id="imageFile" accept="image/*">
                            <div class="error image-file-error"></div>
                        </div>
                    </div>
                </form>
                <div id="statusArea" class="mt-3"></div>
                
                <!-- ADDED: Image Preview Container -->
                <div id="uploadedImagePreviewContainer" class="mt-2" style="display:none;">
                    <h6>Uploaded Image Preview:</h6>
                    <img id="uploadedImagePreview" src="#" alt="Uploaded Image" style="max-width: 100%; height: auto; border: 1px solid #ddd;"/>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="processAndSaveBtn">Process & Save</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
<script src="js/freport_uploader.js"></script>
</body>
</html>
