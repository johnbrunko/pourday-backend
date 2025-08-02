<?php
// api/save_file_record.php
session_start();
header('Content-Type: application/json');
require_once '../config/db_connect.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$user_id = $_SESSION['id'];
$project_id = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);
$task_id = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);
$object_key = filter_input(INPUT_POST, 'object_key', FILTER_SANITIZE_STRING);
$original_filename = filter_input(INPUT_POST, 'original_filename', FILTER_SANITIZE_STRING);
$unique_filename = filter_input(INPUT_POST, 'unique_filename', FILTER_SANITIZE_STRING);
$upload_type = filter_input(INPUT_POST, 'upload_type', FILTER_SANITIZE_STRING);

if (!$project_id || !$task_id || !$object_key || !$original_filename || !$unique_filename || !$upload_type) {
    echo json_encode(['success' => false, 'message' => 'Missing required data to save file record.']);
    exit;
}

$sql = "INSERT INTO files (project_id, task_id, user_id, original_filename, unique_filename, object_key, upload_type) VALUES (?, ?, ?, ?, ?, ?, ?)";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "iiissss", $project_id, $task_id, $user_id, $original_filename, $unique_filename, $object_key, $upload_type);
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'File record saved.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save file record to database.']);
    }
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['success' => false, 'message' => 'Database statement preparation failed.']);
}

mysqli_close($link);
?>