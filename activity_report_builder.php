<?php
session_start();
//activity_report_builder.php
// Allow admins and project managers to access this page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["role_id"], [1, 2, 3])) {
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
    <title>Activity Report Builder - PourDay</title>
    <link href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-bootstrap@2.0.12/dist/css/modus-bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-icons@1.16.0/dist/modus-solid/fonts/modus-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700&display=fallback"/>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Custom styles for the builder */
        .builder-step { margin-bottom: 1.5rem; }
        .list-group-item { padding: 0.5rem 1rem; }
        
        /* Styles for the new reordering feature */
        .selected-notes-container {
            border: 1px dashed var(--bs-border-color);
            border-radius: 0.375rem;
            padding: 1rem;
        }
        .sortable-list { min-height: 50px; }

        .selected-note-item { 
            border: 1px solid var(--bs-border-color-translucent);
            padding: 0.5rem 1rem; 
            border-radius: 0.25rem; 
            margin-bottom: 0.5rem; 
            cursor: grab; 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            border-left: 4px solid var(--bs-success);
        }
        .selected-note-item:active, .photo-reorder-item:active { cursor: grabbing; }
        .sortable-ghost { opacity: 0.4; background-color: var(--bs-primary); }
        .drag-handle { margin-right: 0.75rem; color: var(--bs-gray-400); }
        .category-header {
            margin-top: 1rem;
            margin-bottom: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--bs-gray-600);
            color: var(--bs-gray-300);
        }
        .category-header:first-of-type {
            margin-top: 0;
        }

        /* --- MODIFIED: Styles for two-column photo layout --- */
        #reorderable-photos-list {
            border: 1px dashed var(--bs-border-color);
            border-radius: 0.375rem;
            padding: 1rem;
            min-height: 100px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
        }
        .photo-reorder-item {
            display: flex;
            align-items: center;
            border: 1px solid var(--bs-border-color-translucent);
            padding: 0.5rem;
            border-radius: 0.25rem;
            margin-bottom: 1rem;
            cursor: grab;
            width: calc(50% - 0.5rem); /* Two columns with a gap */
            border-left: 4px solid var(--bs-success);
        }
        .photo-thumbnail-reorder {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 0.25rem;
            margin-right: 1rem;
        }
        .photo-caption-reorder {
            font-size: 0.9rem;
            flex-grow: 1; /* Allow caption to take remaining space */
        }
    </style>
</head>
<body>
    <?php include 'topbar_mobile.html'; ?>
    <?php include 'sidebar.html'; ?>

    <main class="page-content-wrapper">
        <div class="container-fluid p-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">Activity Report Builder</h3>
                </div>
                <div class="card-body">
                    <!-- Step 1: Project and Task Selection -->
                    <div class="builder-step" id="step1">
                        <h4>Step 1: Select Project and Task</h4>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="projectSelection" class="form-label">Project</label>
                                <select class="form-select" id="projectSelection" required></select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="taskSelection" class="form-label">Task</label>
                                <select class="form-select" id="taskSelection" required disabled>
                                    <option value="">-- Select a project first --</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Builder sections - initially hidden -->
                    <div id="reportBuilderSections" style="display: none;">
                        
                        <!-- Step 2: Add and Order Notes -->
                        <div class="builder-step" id="step2">
                            <h4>Step 2: Add and Order Notes</h4>
                            <div class="row">
                                <!-- Left Column: Available Notes -->
                                <div class="col-lg-6">
                                    <h5 class="mb-3">Available Notes</h5>
                                    <div class="accordion" id="notesAccordion">
                                        <!-- Accordion Items for Observations, Concerns, Recommendations will be loaded here -->
                                    </div>
                                </div>
                                <!-- Right Column: Selected Notes Staging Area -->
                                <div class="col-lg-6">
                                    <h5 class="mb-3">Selected Notes (Drag to Reorder within a category)</h5>
                                    <div class="selected-notes-container">
                                        <h6 class="category-header">Observations</h6>
                                        <div id="selected-observations-list" class="sortable-list"></div>
                                        
                                        <h6 class="category-header">Concerns</h6>
                                        <div id="selected-concerns-list" class="sortable-list"></div>

                                        <h6 class="category-header">Recommendations</h6>
                                        <div id="selected-recommendations-list" class="sortable-list"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Reorder Photos -->
                        <div class="builder-step" id="step3">
                            <h4>Step 3: Reorder Photos</h4>
                            <div id="reorderable-photos-list">
                                <!-- Photos will be loaded here by JS -->
                                <p class="text-muted p-3" style="width: 100%;">Select a task to see available photos.</p>
                            </div>
                        </div>

                        <!-- Step 4: Generate Report -->
                        <div class="builder-step mt-4" id="step4">
                            <h4>Step 4: Generate Report</h4>
                            <button id="generateReportBtn" class="btn btn-lg btn-success">
                                <i class="modus-icons notranslate">file_text</i> Generate Activity Report
                            </button>
                            <div id="generation-status" class="mt-2"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/sortable.min.js"></script>
    <script src="js/main.js"></script>
    <script src="js/activity_report_builder.js"></script>
</body>
</html>