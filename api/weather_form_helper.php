<?php
// File: api/weather_form_helper.php

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["company_id"])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/db_connect.php';

$action = $_GET['action'] ?? null;
$company_id = $_SESSION["company_id"];
$response = ['success' => false, 'data' => [], 'message' => 'Invalid action'];

if (!isset($_GET['project_id'])) {
    echo json_encode($response);
    exit;
}

$projectId = filter_var($_GET['project_id'], FILTER_VALIDATE_INT);
if (!$projectId) {
    $response['message'] = 'Invalid Project ID.';
    echo json_encode($response);
    exit;
}

// --- ACTION 1: For the Weather Upload page ---
if ($action === 'get_tasks_for_project') {
    // Fetches ALL tasks for a project, plus the project's zip code for auto-fill.
    
    // 1. Fetch Tasks
    $sql_tasks = "SELECT id, title FROM tasks WHERE project_id = ? AND company_id = ? ORDER BY title ASC";
    if ($stmt_tasks = mysqli_prepare($link, $sql_tasks)) {
        mysqli_stmt_bind_param($stmt_tasks, "ii", $projectId, $company_id);
        mysqli_stmt_execute($stmt_tasks);
        $result_tasks = mysqli_stmt_get_result($stmt_tasks);
        $tasks = mysqli_fetch_all($result_tasks, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_tasks);
    } else {
        $tasks = [];
    }

    // 2. Fetch Project Zip Code
    $project_zip = null;
    $sql_project = "SELECT zip FROM projects WHERE id = ? AND company_id = ?";
    if ($stmt_project = mysqli_prepare($link, $sql_project)) {
        mysqli_stmt_bind_param($stmt_project, "ii", $projectId, $company_id);
        mysqli_stmt_execute($stmt_project);
        mysqli_stmt_bind_result($stmt_project, $db_zip);
        if (mysqli_stmt_fetch($stmt_project)) {
            $project_zip = $db_zip;
        }
        mysqli_stmt_close($stmt_project);
    }
    
    $response = ['success' => true, 'data' => $tasks, 'zip' => $project_zip];
}
// --- ACTION 2: For the Weather Report page ---
elseif ($action === 'get_tasks_with_data') {
    // Fetches ONLY tasks that have historical weather data. Does not need the zip code.
    $sql = "SELECT DISTINCT t.id, t.title 
            FROM tasks t
            JOIN historical_weather_data h ON t.id = h.task_id
            WHERE t.project_id = ? AND t.company_id = ? 
            ORDER BY t.title ASC";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $projectId, $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $tasks = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        $response = ['success' => true, 'data' => $tasks];
    }
}

echo json_encode($response);
?>