<?php
// File: api/photo_handler.php

session_start();
header('Content-Type: application/json');

// --- Dependency and Config Loading ---
require_once '../vendor/autoload.php';
require_once '../config/db_connect.php';
$wasabiConfig = require '../config/wasabi_config.php';

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

// --- Security Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["role_id"], [2, 3])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_REQUEST['action'] ?? null;
$response = ['success' => false, 'message' => 'Invalid action specified.'];
$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['id'];

// --- S3 Client Initialization ---
$s3Client = new S3Client([
    'version'     => 'latest',
    'region'      => $wasabiConfig['region'],
    'endpoint'    => $wasabiConfig['endpoint'],
    'credentials' => [
        'key'    => $wasabiConfig['key'],
        'secret' => $wasabiConfig['secret'],
    ],
]);

// --- Action Routing ---
switch ($action) {
    case 'get_tasks_for_project':
        $projectId = filter_input(INPUT_GET, 'project_id', FILTER_VALIDATE_INT);
        if ($projectId) {
            $sql = "SELECT id, title FROM tasks WHERE project_id = ? AND company_id = ? ORDER BY title ASC";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ii", $projectId, $company_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $response['data'] = mysqli_fetch_all($result, MYSQLI_ASSOC);
                $response['success'] = true;
                mysqli_stmt_close($stmt);
            }
        }
        break;

    case 'get_activity_types':
        $sql = "SELECT id, name FROM activity_types WHERE is_active = 1 ORDER BY name ASC";
        $result = mysqli_query($link, $sql);
        $response['data'] = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $response['success'] = true;
        break;

    case 'get_photos_for_task':
        $taskId = filter_input(INPUT_GET, 'task_id', FILTER_VALIDATE_INT);
        if ($taskId) {
            $sql = "SELECT p.id, p.image_path, p.comments, p.uploaded_at, a.name as activity_name, a.id as activity_id
                    FROM task_photos p
                    JOIN activity_types a ON p.activity_category_id = a.id
                    WHERE p.task_id = ?
                    ORDER BY p.uploaded_at DESC";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "i", $taskId);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $photos = mysqli_fetch_all($result, MYSQLI_ASSOC);
                
                foreach ($photos as &$photo) {
                    try {
                        $cmd = $s3Client->getCommand('GetObject', [
                            'Bucket' => $wasabiConfig['bucket'],
                            'Key'    => $photo['image_path']
                        ]);
                        $request = $s3Client->createPresignedRequest($cmd, '+20 minutes');
                        $photo['presigned_url'] = (string) $request->getUri();
                    } catch (S3Exception $e) {
                        $photo['presigned_url'] = null;
                        error_log("Wasabi URL generation failed for {$photo['image_path']}: " . $e->getMessage());
                    }
                }
                
                $response['data'] = $photos;
                $response['success'] = true;
                mysqli_stmt_close($stmt);
            }
        }
        break;

    case 'upload_photo':
        $taskId = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);
        $activityId = filter_input(INPUT_POST, 'activity_category_id', FILTER_VALIDATE_INT);
        $comments = trim($_POST['comments'] ?? '');

        if (!$taskId || !$activityId || !isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $response['message'] = 'Missing required data or file upload error.';
            break;
        }

        // Fetch project_id for path
        $projectId = null;
        $sql_proj = "SELECT project_id FROM tasks WHERE id = ? AND company_id = ?";
        if ($stmt_proj = mysqli_prepare($link, $sql_proj)) {
            mysqli_stmt_bind_param($stmt_proj, "ii", $taskId, $company_id);
            mysqli_stmt_execute($stmt_proj);
            mysqli_stmt_bind_result($stmt_proj, $projectId);
            mysqli_stmt_fetch($stmt_proj);
            mysqli_stmt_close($stmt_proj);
        }

        if (!$projectId) {
            $response['message'] = "Could not find the associated project for this task.";
            break;
        }

        $file = $_FILES['photo'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $uniqueId = bin2hex(random_bytes(8));

        // --- CORRECTED: File path structure now uses only the IDs, without text prefixes. ---
        $newFileName = "{$projectId}/{$taskId}/images/photo_{$activityId}_{$uniqueId}.{$fileExtension}";

        try {
            $result = $s3Client->putObject([
                'Bucket' => $wasabiConfig['bucket'],
                'Key'    => $newFileName,
                'SourceFile' => $file['tmp_name'],
                'ACL'    => 'private',
            ]);

            $sql = "INSERT INTO task_photos (task_id, user_id, image_path, activity_category_id, comments) VALUES (?, ?, ?, ?, ?)";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "iisis", $taskId, $user_id, $newFileName, $activityId, $comments);
                mysqli_stmt_execute($stmt);
                $response['success'] = true;
                $response['message'] = 'Photo uploaded successfully!';
                mysqli_stmt_close($stmt);
            } else {
                 $response['message'] = 'Database insert failed.';
            }

        } catch (S3Exception $e) {
            $response['message'] = 'Failed to upload file to storage: ' . $e->getMessage();
        } catch (Exception $e) {
            $response['message'] = 'An unexpected error occurred: ' . $e->getMessage();
        }
        break;
}

mysqli_close($link);
echo json_encode($response);