<?php
// File: weather_upload.php

session_start();

// Security Check for Roles 2 and 3
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["role_id"], [2, 3])) {
    header("location: index.php");
    exit;
}

require_once 'config/db_connect.php';

// Fetch projects for the dropdown
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
    <title>Upload Weather Data - PourDay</title>
    <link href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-bootstrap@2.0.12/dist/css/modus-bootstrap.min.css" rel="stylesheet">
    <!-- FIXED: Added missing Modus Icons stylesheet -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-icons@1.16.0/dist/modus-solid/fonts/modus-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700&display=fallback"/>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- FIXED: Added missing mobile top bar and sidebar -->
    <?php include 'topbar_mobile.html'; ?>
    <?php include 'sidebar.html'; ?>

    <!-- FIXED: Added main content wrapper for correct layout -->
    <main class="page-content-wrapper">
        <div class="container-fluid p-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">Upload Historical Weather Data</h3>
                </div>
                <div class="card-body">
                    <form id="weatherFetchForm" onsubmit="return false;">
                        <div class="mb-4">
                            <label class="form-label fw-bold">1. Choose Data Source:</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="dataSource" id="dataSourceManual" value="manual" checked>
                                    <label class="form-check-label" for="dataSourceManual">Fetch from Weather API</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="dataSource" id="dataSourceCSV" value="csv">
                                    <label class="form-check-label" for="dataSourceCSV">Upload from CSV File</label>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="projectSelection" class="form-label fw-bold">2. Select Project</label>
                                <select class="form-select" id="projectSelection" required>
                                    <option value="">-- Select Project --</option>
                                    <?php foreach ($projects_list as $project): ?>
                                        <option value="<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['job_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="taskSelection" class="form-label fw-bold">3. Select Task</label>
                                <select class="form-select" id="taskSelection" required disabled></select>
                            </div>
                        </div>

                        <div id="manualInputSection">
                            <div class="row mb-3">
                                <div class="col-lg-6">
                                    <label for="startDate" class="form-label">4. Start Date & Time</label>
                                    <div class="input-group">
                                        <input type="date" class="form-control" id="startDate" required>
                                        <select class="form-select" style="max-width: 80px;" id="startHour" required></select>
                                        <select class="form-select" style="max-width: 80px;" id="startAmPm" required><option>AM</option><option>PM</option></select>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <label for="endDate" class="form-label">5. End Date & Time</label>
                                    <div class="input-group">
                                        <input type="date" class="form-control" id="endDate" required>
                                        <select class="form-select" style="max-width: 80px;" id="endHour" required></select>
                                        <select class="form-select" style="max-width: 80px;" id="endAmPm" required><option>AM</option><option>PM</option></select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="zipCode" class="form-label">6. Zip Code</label>
                                    <input type="text" class="form-control" id="zipCode" placeholder="Enter Zip Code" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="concreteTemp" class="form-label">7. Concrete Temp (°F)</label>
                                    <select class="form-select" id="concreteTemp" required>
                                        <option value="">-- Select --</option>
                                        <?php for ($i = 40; $i <= 120; $i += 5): ?>
                                            <option value="<?php echo $i; ?>"><?php echo $i; ?>°F</option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div id="csvUploadSection" style="display: none;">
                            <div class="mb-3">
                                <label for="csvFile" class="form-label">Upload Kestrel CSV File</label>
                                <input type="file" class="form-control" id="csvFile" accept=".csv" required>
                            </div>
                            <div>
                                <label class="form-label">Time Range to Use from CSV</label>
                                <div class="input-group">
                                    <select class="form-select" style="max-width: 80px;" id="csvStartHour" required></select>
                                    <select class="form-select" style="max-width: 80px;" id="csvStartAmPm" required><option>AM</option><option>PM</option></select>
                                    <span class="input-group-text">-</span>
                                    <select class="form-select" style="max-width: 80px;" id="csvEndHour" required></select>
                                    <select class="form-select" style="max-width: 80px;" id="csvEndAmPm" required><option>AM</option><option>PM</option></select>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">
                        <button type="submit" class="btn btn-primary">Process Weather Data</button>
                    </form>

                    <div id="statusMessage" class="mt-3"></div>
                    <div id="weatherResults" class="mt-4 table-responsive"></div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="js/main.js"></script>
    <script src="js/tasks.js"></script>
    <script src="js/weather_upload.js"></script>
</body>
</html>