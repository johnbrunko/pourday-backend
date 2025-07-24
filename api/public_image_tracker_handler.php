<?php
// File: api/public_image_tracker_handler.php

session_start();
header('Content-Type: application/json');

// --- Dependency and Config Loading ---
require_once '../vendor/autoload.php';
require_once '../config/db_connect.php';
$wasabiConfig = require '../config/wasabi_config.php';

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

$action = $_REQUEST['action'] ?? null;
$response = ['success' => false, 'message' => 'Invalid action specified.'];

// --- S3 Client Initialization ---
$s3Client = new S3Client([
    'version'     => 'latest',
    'region'      => $wasabiConfig['region'],
    'endpoint'    => $wasabiConfig['endpoint'],
    'credentials' => [ 'key' => $wasabiConfig['key'], 'secret' => $wasabiConfig['secret'] ]
]);

// --- Action Routing ---
switch ($action) {
    case 'validate_code':
        $uploadCode = trim($_POST['upload_code'] ?? '');
        if (strlen($uploadCode) === 5) {
            $sql = "SELECT id, project_id, title FROM tasks WHERE upload_code = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "s", $uploadCode);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                if ($task = mysqli_fetch_assoc($result)) {
                    // Store validated IDs in the session for security
                    $_SESSION['public_task_id'] = $task['id'];
                    $_SESSION['public_project_id'] = $task['project_id'];
                    
                    // Fetch project name to send back to UI
                    $sql_proj = "SELECT job_name FROM projects WHERE id = ?";
                    $stmt_proj = mysqli_prepare($link, $sql_proj);
                    mysqli_stmt_bind_param($stmt_proj, "i", $task['project_id']);
                    mysqli_stmt_execute($stmt_proj);
                    $result_proj = mysqli_stmt_get_result($stmt_proj);
                    $project = mysqli_fetch_assoc($result_proj);

                    $response['success'] = true;
                    $response['data'] = [
                        'task_name' => $task['title'],
                        'project_name' => $project['job_name'] ?? 'N/A'
                    ];
                } else {
                    $response['message'] = 'Invalid upload code.';
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            $response['message'] = 'Code must be 5 characters long.';
        }
        break;

    // The following actions require a validated session
    case 'get_activity_types':
    case 'get_photos_for_task':
    case 'upload_photo':
        if (!isset($_SESSION['public_task_id'])) {
            $response['message'] = 'Session invalid or expired. Please re-enter your code.';
            break;
        }
        
        $taskId = $_SESSION['public_task_id'];
        $projectId = $_SESSION['public_project_id'];

        if ($action === 'get_activity_types') {
            $sql = "SELECT id, name FROM activity_types WHERE is_active = 1 ORDER BY name ASC";
            $result = mysqli_query($link, $sql);
            $response['data'] = mysqli_fetch_all($result, MYSQLI_ASSOC);
            $response['success'] = true;
        } 
        elseif ($action === 'get_photos_for_task') {
            $sql = "SELECT p.id, p.image_path, p.comments, p.uploaded_at, a.name as activity_name, a.id as activity_id
                    FROM task_photos p JOIN activity_types a ON p.activity_category_id = a.id
                    WHERE p.task_id = ? ORDER BY p.uploaded_at DESC";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "i", $taskId);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $photos = mysqli_fetch_all($result, MYSQLI_ASSOC);
                
                foreach ($photos as &$photo) {
                    try {
                        $cmd = $s3Client->getCommand('GetObject', ['Bucket' => $wasabiConfig['bucket'], 'Key' => $photo['image_path']]);
                        $request = $s3Client->createPresignedRequest($cmd, '+20 minutes');
                        $photo['presigned_url'] = (string) $request->getUri();
                    } catch (S3Exception $e) {
                        $photo['presigned_url'] = null;
                    }
                }
                $response['data'] = $photos;
                $response['success'] = true;
                mysqli_stmt_close($stmt);
            }
        }
        elseif ($action === 'upload_photo') {
            $activityId = filter_input(INPUT_POST, 'activity_category_id', FILTER_VALIDATE_INT);
            $comments = trim($_POST['comments'] ?? '');

            if (!$activityId || !isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
                $response['message'] = 'Missing required data or file upload error.';
                break;
            }

            $file = $_FILES['photo'];
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $uniqueId = bin2hex(random_bytes(8));
            $newFileName = "{$projectId}/{$taskId}/images/photo_{$activityId}_{$uniqueId}.{$fileExtension}";

            try {
                $s3Client->putObject([
                    'Bucket' => $wasabiConfig['bucket'],
                    'Key'    => $newFileName,
                    'SourceFile' => $file['tmp_name'],
                    'ACL'    => 'private',
                ]);

                // For public uploads, user_id is NULL
                $sql_insert = "INSERT INTO task_photos (task_id, user_id, image_path, activity_category_id, comments) VALUES (?, NULL, ?, ?, ?)";
                if ($stmt_insert = mysqli_prepare($link, $sql_insert)) {
                    mysqli_stmt_bind_param($stmt_insert, "isis", $taskId, $newFileName, $activityId, $comments);
                    mysqli_stmt_execute($stmt_insert);
                    $response['success'] = true;
                    $response['message'] = 'Photo uploaded successfully!';
                    mysqli_stmt_close($stmt_insert);
                }
            } catch (Exception $e) {
                $response['message'] = 'Upload failed: ' . $e->getMessage();
            }
        }
        break;
}

mysqli_close($link);
echo json_encode($response);