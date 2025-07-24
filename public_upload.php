<?php
// No session start or login check needed for a public page
require_once 'config/db_connect.php'; // We still need the DB connection for one query
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Upload - PourDay App</title>
    <link href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-bootstrap@2.0.12/dist/css/modus-bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700&display=fallback"/>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="container mt-5" id="uploaderContainer" style="display:none;">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h3 class="mb-0">File Uploader</h3>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <strong>Project:</strong> <span id="projectName"></span><br>
                <strong>Task:</strong> <span id="taskName"></span>
            </div>
            <form id="uploadForm" enctype="multipart/form-data">
                <input type="hidden" id="project_id" name="project_id">
                <input type="hidden" id="task_id" name="task_id">
                <input type="hidden" id="upload_code" name="upload_code">
                
                <h5 class="mb-3 mt-4">1. Choose Upload Type</h5>
                <div>
                    <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="upload_type" id="typeImage" value="img" checked><label class="form-check-label" for="typeImage">Image(s)</label></div>
                    <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="upload_type" id="typeDocument" value="docs"><label class="form-check-label" for="typeDocument">Document(s)</label></div>
                    <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="upload_type" id="typeFolder" value="folders"><label class="form-check-label" for="typeFolder">Folder</label></div>
                </div>

                <h5 class="mb-3 mt-4">2. Select File(s) to Upload</h5>
                <div class="mb-3"><input type="file" class="form-control" id="file_input" name="files[]" multiple></div>
                <div class="d-grid mt-4"><button type="submit" class="btn btn-primary">Upload Files</button></div>
            </form>

            <div id="progressWrapper" class="mt-4" style="display: none;"><div class="progress" style="height: 25px; font-size: .8rem;"><div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;"></div></div><div class="text-end mt-1"><small id="progressCount" class="text-muted"></small></div></div>
            <div id="uploadStatus" class="mt-3"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="codeEntryModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Enter Upload Code</h5>
            </div>
            <div class="modal-body">
                <p>Please enter the 5-digit upload code you were provided.</p>
                <form id="codeEntryForm">
                    <input type="text" class="form-control form-control-lg text-center" id="codeInput" maxlength="5" required>
                    <div id="codeError" class="text-danger mt-2" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="submitCodeBtn">Submit</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/public_upload_logic.js"></script>
</body>
</html>