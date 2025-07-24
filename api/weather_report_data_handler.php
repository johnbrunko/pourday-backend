<?php
// File: api/report_data_handler.php

session_start();
header('Content-Type: application/json');

// Security Check: User must be logged in and associated with a company.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["company_id"])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/db_connect.php';

$task_id = filter_input(INPUT_GET, 'task_id', FILTER_VALIDATE_INT);
$company_id = $_SESSION['company_id'];
$response = ['success' => false, 'message' => 'Could not retrieve report data.'];

if (!$task_id) {
    $response['message'] = 'Invalid Task ID provided.';
    echo json_encode($response);
    exit;
}

// This single, powerful query joins the three tables to get all necessary data.
// It also verifies that the requested task belongs to the user's company for security.
$sql = "SELECT 
            p.job_name,
            p.job_number,
            p.city,
            p.state,
            t.title AS task_title,
            t.scheduled AS task_scheduled_date,
            h.record_datetime,
            h.temperature,
            h.humidity,
            h.windspeed,
            h.evap_rate
        FROM 
            historical_weather_data h
        JOIN 
            tasks t ON h.task_id = t.id
        JOIN 
            projects p ON t.project_id = p.id
        WHERE 
            h.task_id = ? AND t.company_id = ?
        ORDER BY 
            h.record_datetime ASC";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $task_id, $company_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $report_data = [];
    $project_info = null;
    $task_info = null;

    while ($row = mysqli_fetch_assoc($result)) {
        // Set the project and task info on the first iteration
        if ($project_info === null) {
            $project_info = [
                'job_name' => $row['job_name'],
                'job_number' => $row['job_number'],
                'location' => $row['city'] . ', ' . $row['state']
            ];
            $task_info = [
                'title' => $row['task_title'],
                'scheduled_date' => date('m/d/Y', strtotime($row['task_scheduled_date']))
            ];
        }

        // Add the hourly weather data to our array
        $report_data[] = [
            'record_datetime' => $row['record_datetime'],
            'temperature' => $row['temperature'],
            'humidity' => $row['humidity'],
            'windspeed' => $row['windspeed'],
            'evap_rate' => $row['evap_rate']
        ];
    }
    mysqli_stmt_close($stmt);

    if (!empty($report_data)) {
        $response = [
            'success' => true,
            'project' => $project_info,
            'task' => $task_info,
            'weatherData' => $report_data
        ];
    } else {
        // This can happen if the task exists but has no weather data, or if the user doesn't have permission.
        $response['message'] = 'No weather data found for this task, or you do not have permission to view it.';
    }

} else {
    $response['message'] = 'Database query failed.';
}

echo json_encode($response);
?>