<?php
session_start();

// Security check, allowing any logged-in user with a role to access.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || empty($_SESSION["role_id"])) {
    header("location: index.php");
    exit;
}

require_once 'config/db_connect.php';

$company_id = $_SESSION["company_id"];
if (empty($company_id)) {
    echo "Error: Your account is not associated with a company.";
    exit;
}

// Fetch Ongoing Projects for the dropdown
$projects_list = [];
$sql_projects = "SELECT id, job_name FROM projects WHERE company_id = ? AND status = 'Ongoing' ORDER BY job_name ASC";
if ($stmt_projects = mysqli_prepare($link, $sql_projects)) {
    mysqli_stmt_bind_param($stmt_projects, "i", $company_id);
    mysqli_stmt_execute($stmt_projects);
    $result_projects = mysqli_stmt_get_result($stmt_projects);
    while ($row = mysqli_fetch_assoc($result_projects)) {
        $projects_list[] = $row;
    }
    mysqli_stmt_close($stmt_projects);
}

mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Management - PourDay App</title>
    
    <!-- Stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-bootstrap@2.0.12/dist/css/modus-bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-icons@1.16.0/dist/modus-solid/fonts/modus-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700&display=fallback"/>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php include 'topbar_mobile.html'; ?>
<?php include 'sidebar.html'; ?>

<main class="page-content-wrapper">
    <div class="container-fluid p-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">File Management</h3>
            </div>
            <div class="card-body">
                <!-- Step 1: Project and Task Selection (Moved outside tabs) -->
                <h5 class="mb-3">1. Select Destination</h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="project_id" class="form-label">Project</label>
                        <select class="form-select" id="project_id" name="project_id" required>
                            <option value="">-- Select a Project --</option>
                            <?php foreach ($projects_list as $project): ?>
                                <option value="<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['job_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="task_id" class="form-label">Task</label>
                        <select class="form-select" id="task_id" name="task_id" required disabled>
                            <option value="">-- Select a project first --</option>
                        </select>
                    </div>
                </div>
                <hr>

                <!-- Tab Navigation -->
                <ul class="nav nav-tabs" id="fileManagerTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload-tab-pane" type="button" role="tab" aria-controls="upload-tab-pane" aria-selected="true">
                            <i class="modus-icon modus-icon-upload"></i> Upload Files
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="view-tab" data-bs-toggle="tab" data-bs-target="#view-tab-pane" type="button" role="tab" aria-controls="view-tab-pane" aria-selected="false" disabled>
                           <i class="modus-icon modus-icon-folder-open"></i> View Files
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content pt-4" id="fileManagerTabsContent">
                    <!-- Upload Tab Pane -->
                    <div class="tab-pane fade show active" id="upload-tab-pane" role="tabpanel" aria-labelledby="upload-tab" tabindex="0">
                        <form id="uploadForm" enctype="multipart/form-data">
                            <!-- Step 2: Upload Type Selection -->
                            <h5 class="mb-3">2. Choose Upload Type</h5>
                            <div class="mb-3">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="upload_type" id="typeImage" value="img" checked>
                                    <label class="form-check-label" for="typeImage">Image(s)</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="upload_type" id="typeDocument" value="docs">
                                    <label class="form-check-label" for="typeDocument">Document(s)</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="upload_type" id="typeFolder" value="folders">
                                    <label class="form-check-label" for="typeFolder">Folder</label>
                                </div>
                            </div>

                            <!-- Step 3: File Input -->
                            <h5 class="mb-3 mt-4">3. Select File(s) to Upload</h5>
                            <div class="mb-3">
                                <input type="file" class="form-control" id="file_input" name="files[]" multiple>
                            </div>

                            <!-- Upload Button -->
                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary">Upload Files</button>
                            </div>
                        </form>

                        <!-- Progress Bar & Status -->
                        <div id="progressWrapper" class="mt-4" style="display: none;">
                            <div class="progress" style="height: 25px; font-size: .8rem;">
                                <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                    <span class="progress-text"></span>
                                </div>
                            </div>
                            <div class="text-end mt-1">
                                <small id="progressCount" class="text-muted"></small>
                            </div>
                        </div>
                        <div id="uploadStatus" class="mt-3"></div>
                    </div>

                    <!-- View Files Tab Pane -->
                    <div class="tab-pane fade" id="view-tab-pane" role="tabpanel" aria-labelledby="view-tab" tabindex="0">
                        <div id="file-viewer-content">
                            <p class="text-center text-muted">Select a project and task to view files.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
<!-- IMPORTANT: Use the new JS file -->
<script src="js/file_management.js"></script>

</body>
</html>

