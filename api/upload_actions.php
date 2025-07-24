<?php
// api/upload_actions.php

header('Content-Type: application/json');
session_start();

// Security check for any logged-in user
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

require_once dirname(__DIR__) . '/config/db_connect.php';
$company_id = $_SESSION["company_id"];
$response = ['success' => false, 'data' => []];

$request_type = $_GET['request_type'] ?? '';
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

if ($request_type === 'get_tasks_for_project' && $project_id > 0) {
    
    // Fetch active tasks for the given project_id and company_id
    $sql = "SELECT id, title FROM tasks WHERE project_id = ? AND company_id = ? AND completed_at IS NULL ORDER BY title ASC";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $project_id, $company_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $response['data'][] = $row;
            }
            $response['success'] = true;
        }
        mysqli_stmt_close($stmt);
    }
}

mysqli_close($link);
echo json_encode($response);
?>