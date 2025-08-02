<?php
// File: weather_report.php
session_start();

// Security Check: Allow appropriate roles to access this page.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["role_id"], [2, 3])) {
    header("location: index.php");
    exit;
}

require_once 'config/db_connect.php';

// Fetch all 'Ongoing' projects for the user's company
$projects_list = [];
$company_id = $_SESSION["company_id"] ?? 0;

if ($company_id) {
    $sql_projects = "SELECT id, job_name 
                      FROM projects 
                      WHERE company_id = ? AND status = 'Ongoing'
                      ORDER BY job_name ASC";
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
    <title>Weather Report - PourDay</title>
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
                    <h3 class="mb-0">Generate Weather Report</h3>
                    <a href="weather_upload.php" class="btn btn-light btn-sm">Back to Upload</a>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="projectSelection" class="form-label fw-bold">1. Select Project</label>
                            <select class="form-select" id="projectSelection" required>
                                <option value="">-- Select a Project --</option>
                                <?php if (empty($projects_list)): ?>
                                    <option disabled>No ongoing projects found.</option>
                                <?php else: ?>
                                    <?php foreach ($projects_list as $project): ?>
                                        <option value="<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['job_name']); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="taskSelection" class="form-label fw-bold">2. Select Task</label>
                            <select class="form-select" id="taskSelection" required disabled>
                                <option value="">-- Select a Project First --</option>
                            </select>
                        </div>
                    </div>
                    <button id="generateReportBtn" class="btn btn-primary" disabled>Generate Report</button>
                    <hr class="my-4">

                    <div id="report-content" style="display: none;">
                        <div id="report-info" class="mb-4"></div>

                        <button id="downloadChartsBtn" class="btn btn-success mb-4" style="display: none;">
                            <i class="modus-icon mi-download" aria-hidden="true"></i> Download Charts
                        </button>

                        <div id="charts-container" class="row g-4">
                             <div class="col-md-6">
                                 <div class="card">
                                     <div class="card-body text-center">
                                         <canvas id="tempChartCanvas"></canvas>
                                     </div>
                                 </div>
                             </div>
                             <div class="col-md-6">
                                 <div class="card">
                                     <div class="card-body text-center">
                                         <canvas id="humidityChartCanvas"></canvas>
                                     </div>
                                 </div>
                             </div>
                             <div class="col-md-6">
                                 <div class="card">
                                     <div class="card-body text-center">
                                         <canvas id="windChartCanvas"></canvas>
                                     </div>
                                 </div>
                             </div>
                             <div class="col-md-6">
                                 <div class="card">
                                     <div class="card-body text-center">
                                         <canvas id="evapChartCanvas"></canvas>
                                     </div>
                                 </div>
                             </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
    
    <script src="js/main.js"></script>
    <script src="js/weather_report.js"></script>
</body>
</html>