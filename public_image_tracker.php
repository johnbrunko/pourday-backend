<?php
// File: public_image_tracker.php
session_start();

// Unset any previous session data to ensure a clean start.
unset($_SESSION['public_task_id']);
unset($_SESSION['public_project_id']);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Photo Upload Portal - PourDay</title>
    <link href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-bootstrap@2.0.12/dist/css/modus-bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-icons@1.16.0/dist/modus-solid/fonts/modus-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700&display=fallback"/>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Custom styles for the public upload page */
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
            font-weight: 600;
        }
        #photoGallery .card-img-top {
            height: 200px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <!-- Code Entry Modal -->
    <div class="modal fade" id="codeEntryModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Enter Upload Code</h5>
                </div>
                <div class="modal-body">
                    <p>Please enter the 5-digit alphanumeric code provided for this task.</p>
                    <form id="codeEntryForm">
                        <input type="text" class="form-control form-control-lg text-center" id="uploadCodeInput" maxlength="5" required>
                        <div id="codeError" class="text-danger mt-2 d-none">Invalid code. Please try again.</div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="submitCodeBtn">Submit</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Area (hidden initially) -->
    <div class="container mt-5" id="mainContent">
        <h3 id="trackingHeader">Image Upload for: Task Name</h3>
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
                    <div id="photoGallery" class="row g-3"></div>
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
    <script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js"></script>
    <script src="js/public_image_tracker.js"></script>
</body>
</html>