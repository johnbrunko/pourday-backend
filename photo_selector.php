<?php
// File: photo_selector.php

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
    <title>Photo Selector - PourDay</title>
    <link href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-bootstrap@2.0.12/dist/css/modus-bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-icons@1.16.0/dist/modus-solid/fonts/modus-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700&display=fallback"/>
    <link rel="stylesheet" href="css/style.css">
    <style>
        #mainContent { display: none; }
        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 1rem;
        }
        .photo-thumbnail {
            position: relative;
            cursor: pointer;
            border-radius: 0.25rem;
            overflow: hidden;
        }
        .photo-thumbnail img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            transition: transform 0.2s ease;
        }
        .photo-thumbnail:hover img {
            transform: scale(1.05);
        }
        .photo-thumbnail .category-badge {
            position: absolute;
            bottom: 5px;
            left: 5px;
            font-size: 0.75rem;
        }
        #editImagePreview {
            max-height: 400px;
            width: 100%;
            object-fit: contain;
        }
        .icon-menu { z-index: 1060 !important; }
    </style>
</head>
<body>
    <!-- FIXED: Added missing mobile top bar and sidebar -->
    <?php include 'topbar_mobile.html'; ?>
    <?php include 'sidebar.html'; ?>

    <!-- FIXED: Added main content wrapper for correct layout -->
    <main class="page-content-wrapper">
        <!-- Main Content Area -->
        <div class="container-fluid p-4" id="mainContent">
            <h3 id="selectionHeader">Photo Selection for: Task Name</h3>
            <p id="selectionSubHeader">Project: Project Name</p>
            <div class="row">
                <!-- Assigned Photos Column -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">Assigned Photos</div>
                        <div class="card-body">
                            <div id="assignedPhotos" class="photo-grid">
                                <!-- Assigned photos will be dynamically inserted here -->
                            </div>
                        </div>
                    </div>
                </div>
                <!-- All Photos Column -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">All Photos in Folder</div>
                        <div class="card-body">
                             <div id="allPhotos" class="photo-grid">
                                 <!-- All photos from the folder will be dynamically inserted here -->
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
                <div class="modal-header"><h5 class="modal-title">Select Task</h5></div>
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
                    <button type="button" class="btn btn-primary" id="startSelectionBtn" disabled>Start Selecting</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Photo Edit/Assign Modal -->
    <div class="modal fade" id="photoEditModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Categorize Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <img src="" id="editImagePreview" class="img-fluid rounded mb-3" alt="Image preview">
                    <form id="photoEditForm">
                        <div class="mb-3">
                            <label for="editActivityType" class="form-label">Image Type</label>
                            <select class="form-select" id="editActivityType">
                                <!-- Options will be loaded via JS -->
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="editPhotoComments" class="form-label">Notes</label>
                            <textarea class="form-control" id="editPhotoComments" rows="3"></textarea>
                            <div class="invalid-feedback">Notes are required for 'Issue' type photos.</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveCategoryBtn">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
    <script src="js/photo_selector.js"></script>
</body>
</html>