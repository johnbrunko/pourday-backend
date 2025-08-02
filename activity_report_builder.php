<?php
session_start();
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
        .photo-thumbnail { width: 100px; height: 100px; object-fit: cover; border-radius: 0.25rem; }
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
                        <!-- Step 2: Observations, Concerns, Recommendations -->
                        <div class="builder-step" id="step2">
                            <h4>Step 2: Add Notes</h4>
                            <div class="accordion" id="notesAccordion">
                                <!-- Observations -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="headingObservations">
                                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseObservations" aria-expanded="true" aria-controls="collapseObservations">
                                            Observations
                                        </button>
                                    </h2>
                                    <div id="collapseObservations" class="accordion-collapse collapse show" aria-labelledby="headingObservations">
                                        <div class="accordion-body" id="observations-body">
                                            <!-- Templates and custom entries will be loaded here -->
                                        </div>
                                    </div>
                                </div>
                                <!-- Concerns -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="headingConcerns">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseConcerns" aria-expanded="false" aria-controls="collapseConcerns">
                                            Concerns
                                        </button>
                                    </h2>
                                    <div id="collapseConcerns" class="accordion-collapse collapse" aria-labelledby="headingConcerns">
                                        <div class="accordion-body" id="concerns-body">
                                            <!-- Templates and custom entries will be loaded here -->
                                        </div>
                                    </div>
                                </div>
                                <!-- Recommendations -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="headingRecommendations">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRecommendations" aria-expanded="false" aria-controls="collapseRecommendations">
                                            Recommendations
                                        </button>
                                    </h2>
                                    <div id="collapseRecommendations" class="accordion-collapse collapse" aria-labelledby="headingRecommendations">
                                        <div class="accordion-body" id="recommendations-body">
                                            <!-- Templates and custom entries will be loaded here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Attach Photos -->
                        <div class="builder-step" id="step3">
                            <h4>Step 3: Attach Photos</h4>
                            <div id="photo-selection-container" class="row g-2">
                                <!-- Photos will be loaded here -->
                                <p class="text-muted">Select a task to see available photos.</p>
                            </div>
                        </div>

                        <!-- REMOVED: Step for attaching documents has been removed. -->

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
    <script src="js/main.js"></script>
    <script src="js/activity_report_builder.js"></script>
</body>
</html>
