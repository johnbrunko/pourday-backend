<?php
// api/get_wasabi_upload_url.php

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

session_start();
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/db_connect.php';
$wasabiConfig = include(dirname(__DIR__) . '/config/wasabi_config.php');

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Authentication failed.'];
$project_id = 0;
$task_id = 0;
$user_id = null; // Default user_id to null

// --- DUAL AUTHENTICATION LOGIC ---
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    // --- Path 1: Logged-in User ---
    $project_id = filter_input(INPUT_POST, 'project_id', FILTER_SANITIZE_NUMBER_INT);
    $task_id = filter_input(INPUT_POST, 'task_id', FILTER_SANITIZE_NUMBER_INT);
    $company_id = $_SESSION['company_id'];
    $user_id = $_SESSION['id']; // Capture user_id for logged-in users

    $sql_validate = "SELECT COUNT(*) FROM tasks WHERE id = ? AND project_id = ? AND company_id = ?";
    $stmt_validate = mysqli_prepare($link, $sql_validate);
    mysqli_stmt_bind_param($stmt_validate, "iii", $task_id, $project_id, $company_id);
    mysqli_stmt_execute($stmt_validate);
    mysqli_stmt_bind_result($stmt_validate, $count);
    mysqli_stmt_fetch($stmt_validate);
    mysqli_stmt_close($stmt_validate);

    if ($count == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied for this task.']);
        exit();
    }

} else if (!empty($_POST['upload_code'])) {
    // --- Path 2: Public User with Upload Code ---
    $upload_code = filter_input(INPUT_POST, 'upload_code', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    
    $sql_validate = "SELECT project_id, id FROM tasks WHERE upload_code = ? AND completed_at IS NULL";
    $stmt_validate = mysqli_prepare($link, $sql_validate);
    mysqli_stmt_bind_param($stmt_validate, "s", $upload_code);
    mysqli_stmt_execute($stmt_validate);
    mysqli_stmt_bind_result($stmt_validate, $p_id, $t_id);

    if (mysqli_stmt_fetch($stmt_validate)) {
        $project_id = $p_id;
        $task_id = $t_id;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired upload code.']);
        exit();
    }
    mysqli_stmt_close($stmt_validate);

} else {
    // If neither condition is met, exit
    echo json_encode($response);
    exit();
}

// --- COMMON LOGIC (PROCEED IF AUTHENTICATED) ---

$filename = filter_input(INPUT_POST, 'filename', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$contentType = filter_input(INPUT_POST, 'contentType', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$uploadType = filter_input(INPUT_POST, 'upload_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if (!$project_id || !$task_id || !$filename || !$contentType || !$uploadType) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required upload parameters.']);
    exit();
}

// --- FILE RENAMING & PATH LOGIC ---
$path_parts = pathinfo($filename);
$original_filename = $path_parts['basename'];
$filename_only = $path_parts['filename'];
$extension = isset($path_parts['extension']) ? '.' . $path_parts['extension'] : '';

$sanitized_filename = preg_replace("/[^a-zA-Z0-9\._-]/", "_", $filename_only);
$unique_id = bin2hex(random_bytes(4));
$new_unique_filename = $sanitized_filename . '_' . $unique_id . $extension;

// Define Object Key (Path in Wasabi Bucket)
$subfolder = 'other';
if ($uploadType === 'img') $subfolder = 'images';
if ($uploadType === 'docs') $subfolder = 'docs';

// For folder uploads, the full relative path is part of the filename, so we use it directly.
// For other types, we use the new unique filename.
$objectKey = ($uploadType === 'folders') 
    ? "{$project_id}/{$task_id}/folders/{$filename}"
    : "{$project_id}/{$task_id}/{$subfolder}/{$new_unique_filename}";

try {
    $endpoint = $wasabiConfig['endpoint'];
    if (strpos($endpoint, 'http') !== 0) $endpoint = 'https://' . $endpoint;
    
    $s3Client = new S3Client([
        'version'     => 'latest',
        'region'      => $wasabiConfig['region'],
        'endpoint'    => $endpoint,
        'credentials' => [
            'key'    => $wasabiConfig['key'],
            'secret' => $wasabiConfig['secret'],
        ]
    ]);
    $command = $s3Client->getCommand('PutObject', [
        'Bucket'      => $wasabiConfig['bucket'],
        'Key'         => $objectKey,
        'ContentType' => $contentType,
    ]);
    $presignedRequest = $s3Client->createPresignedRequest($command, '+15 minutes');
    
    // *** SAVE FILE METADATA TO DATABASE ***
    $sql_insert = "INSERT INTO files (project_id, task_id, user_id, original_filename, unique_filename, object_key, upload_type) VALUES (?, ?, ?, ?, ?, ?, ?)";
    if ($stmt_insert = mysqli_prepare($link, $sql_insert)) {
        // For folders, the original filename is the full path, and we store that as the 'unique_filename' for simplicity.
        $db_unique_filename = ($uploadType === 'folders') ? $filename : $new_unique_filename;
        mysqli_stmt_bind_param($stmt_insert, "iiissss", $project_id, $task_id, $user_id, $original_filename, $db_unique_filename, $objectKey, $uploadType);
        
        if (!mysqli_stmt_execute($stmt_insert)) {
            // Log error if DB insert fails
            error_log("Database Error: Failed to insert file metadata. " . mysqli_error($link));
            // Optionally, you could decide to fail the whole process here
            // echo json_encode(['status' => 'error', 'message' => 'Could not save file record.']);
            // exit();
        }
        mysqli_stmt_close($stmt_insert);
    } else {
        // Log error if statement preparation fails
        error_log("Database Error: Failed to prepare statement to insert file metadata.");
    }
    // *** END DATABASE SAVE ***

    echo json_encode([
        'status' => 'success',  
        'uploadUrl' => (string)$presignedRequest->getUri()
    ]);

} catch (Exception $e) {
    error_log("Wasabi SDK Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to generate upload URL.']);
}
