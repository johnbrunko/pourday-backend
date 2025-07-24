<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/db_connect.php';

$response = ['success' => false, 'message' => 'Invalid request.'];
$request_type = $_POST['request_type'] ?? '';

if ($request_type === 'validate_code') {
    $upload_code = $_POST['upload_code'] ?? '';

    if (empty($upload_code) || strlen($upload_code) !== 5) {
        $response['message'] = 'Invalid code format.';
        echo json_encode($response);
        exit;
    }

    $sql = "SELECT t.id, t.title, t.project_id, p.job_name 
            FROM tasks t 
            JOIN projects p ON t.project_id = p.id
            WHERE t.upload_code = ? AND t.completed_at IS NULL";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $upload_code);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($task_data = mysqli_fetch_assoc($result)) {
            $response['success'] = true;
            $response['message'] = 'Code validated.';
            $response['task_id'] = $task_data['id'];
            $response['project_id'] = $task_data['project_id'];
            $response['task_name'] = $task_data['title'];
            $response['project_name'] = $task_data['job_name'];
        } else {
            $response['message'] = 'This upload code is either invalid or has expired.';
        }
        mysqli_stmt_close($stmt);
    } else {
        $response['message'] = 'Database query failed.';
    }
}

mysqli_close($link);
echo json_encode($response);