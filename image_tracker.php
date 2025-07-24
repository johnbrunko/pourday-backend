<?php
// File: image_tracker.php

session_start();

// Security Check for authorized roles
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["role_id"], [2, 3])) {
    header("location: index.php");
    exit;
}

require_once 'config/db_connect.php';

// Fetch projects for the initial modal
$projects_list = [];
$company_id = $_SESSION["company_id"] ?? 0;

if ($company_id) {
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
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Tracker - PourDay</title>
    <link href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-bootstrap@2.0.12/dist/css/modus-bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-icons@1.16.0/dist/modus-solid/fonts/modus-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700&display=fallback"/>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Custom styles for the image tracker page */
        #mainContent { display: none; }
        #cameraIconContainer {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 60vh;
            cursor: pointer;
            border: 2px dashed var(--bs-border-color);
            border-radius: 0.5rem;
            transition: background-color 0.3s ease;
        }
        #cameraIconContainer:hover {
            background-color: var(--bs-secondary-bg);
        }
        #cameraIcon {
            font-size: 8rem;
            color: var(--bs-secondary-color);
        }
        #imagePreview {
            max-height: 300px;
            width: 100%;
            object-fit: contain;
        }
        .activity-item.completed {
            text-decoration: line-through;
            color: var(--bs-secondary-color);
        }
        .activity-item.pending {
            font-weight: 600; /* semibold */
        }
        /* Style for consistent image height in the gallery */
        #photoGallery .card-img-top {
            height: 200px;
            object-fit: cover; /* Ensures image covers the area without distortion */
        }
        /* --- FIX: Increase z-index to appear above modals --- */
        .icon-menu {
            z-index: 1060 !important;
        }
    </style>
</head>
<body>
    <!-- FIXED: Added missing mobile top bar and sidebar -->
    <?php include 'topbar_mobile.html'; ?>
    <?php include 'sidebar.html'; ?>

    <!-- FIXED: Added main content wrapper for correct layout -->
    <main class="page-content-wrapper">
        <div class="container-fluid p-4">
            <!-- Main Content Area (initially hidden) -->
            <div id="mainContent">
                <h3 id="trackingHeader">Image Tracking for: Task Name</h3>
                <p id="trackingSubHeader">Project: Project Name</p>

                <ul class="nav nav-tabs" id="imageTrackerTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload-tab-pane" type="button" role="tab">Upload Photo</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="view-tab" data-bs-toggle="tab" data-bs-target="#view-tab-pane" type="button" role="tab">View Photos</button>
                    </li>
                </ul>
                <div class="tab-content" id="imageTrackerTabsContent">
                    <!-- Upload Tab -->
                    <div class="tab-pane fade show active" id="upload-tab-pane" role="tabpanel">
                        <div class="card card-body mt-3">
                            <div id="cameraIconContainer" title="Click to upload a photo">
                                <i id="cameraIcon" class="modus-icons notranslate">camera</i>
                            </div>
                            <input type="file" id="photoInput" accept="image/*,.heic,.heif" capture="environment" class="d-none">
                        </div>
                    </div>
                    <!-- View Tab -->
                    <div class="tab-pane fade" id="view-tab-pane" role="tabpanel">
                        <div class="card card-body mt-3">
                            <h4>Photo Checklist</h4>
                            <p>Items with a strikethrough have at least one photo uploaded.</p>
                            <div id="photoChecklist" class="list-group mb-4"></div>
                            <hr>
                            <h4>Uploaded Photos</h4>
                            <div id="photoGallery" class="row g-3">
                                <!-- Photos will be dynamically inserted here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Task Selection Modal -->
    <div class="modal fade" id="taskSelectionModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Select Task</h5>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="projectSelection" class="form-label">1. Select Project</label>
                        <select class="form-select" id="projectSelection">
                            <option value="">-- Select Project --</option>
                            <?php foreach ($projects_list as $project): ?>
                                <option value="<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['job_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="taskSelection" class="form-label">2. Select Task</label>
                        <select class="form-select" id="taskSelection" disabled></select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="startTrackingBtn" disabled>Start Tracking</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Photo Upload Modal -->
    <div class="modal fade" id="photoUploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Save Photo Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="photoUploadForm">
                        <img src="" id="imagePreview" class="img-fluid rounded mb-3" alt="Image preview">
                        <div class="mb-3">
                            <label for="activityType" class="form-label">Image Type</label>
                            <select class="form-select" id="activityType" required></select>
                        </div>
                        <div class="mb-3">
                            <label for="photoComments" class="form-label">Notes</label>
                            <textarea class="form-control" id="photoComments" rows="3"></textarea>
                            <div class="invalid-feedback">Notes are required for 'Issue' type photos.</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="savePhotoBtn">Save Photo</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- NEW: HEIC conversion library -->
    <script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js"></script>
    <script src="js/main.js"></script>
    <script src="js/image_tracker.js"></script>
</body>
</html>