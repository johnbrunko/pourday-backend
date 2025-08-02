<?php
session_start();
// Allow only admins to access this page (e.g., role_id 1=SuperAdmin, 2=CompanyAdmin)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["role_id"], [1, 2])) {
    header("location: index.php");
    exit;
}

require_once 'config/db_connect.php';
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Observation Notes Manager - PourDay</title>
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
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Manage Observation Notes</h3>
                    <button id="add-new-btn" class="btn btn-light btn-sm">
                        <i class="modus-icons notranslate">add</i> Add New Template
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Text</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="templates-table-body">
                                <!-- Data will be loaded here by JavaScript -->
                                <tr>
                                    <td colspan="4" class="text-center">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Add/Edit Modal -->
    <div class="modal fade" id="templateModal" tabindex="-1" aria-labelledby="templateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="templateModalLabel">Add New Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="templateForm">
                        <input type="hidden" id="templateId" name="template_id">
                        <div class="mb-3">
                            <label for="templateCategory" class="form-label">Category</label>
                            <select class="form-select" id="templateCategory" name="category" required>
                                <option value="" disabled selected>-- Choose a category --</option>
                                <option value="Observation">Observation</option>
                                <option value="Concern">Concern</option>
                                <option value="Recommendation">Recommendation</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="templateText" class="form-label">Template Text</label>
                            <textarea class="form-control" id="templateText" name="text" rows="4" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" form="templateForm">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
    <!-- We will create this JS file in the next step -->
    <script src="js/observation_notes_manager.js"></script>
</body>
</html>