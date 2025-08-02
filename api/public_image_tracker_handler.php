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
try {
    $s3Client = new S3Client([
        'version'     => 'latest',
        'region'      => $wasabiConfig['region'],
        'endpoint'    => $wasabiConfig['endpoint'],
        'credentials' => [ 'key' => $wasabiConfig['key'], 'secret' => $wasabiConfig['secret'] ],
        'http' => ['verify' => dirname(__DIR__) . '/config/cacert.pem']
    ]);
} catch (Exception $e) {
    $response['message'] = 'Wasabi S3 client initialization failed: ' . $e->getMessage();
    echo json_encode($response);
    exit;
}

// --- Action Routing ---
switch ($action) {
    case 'validate_code':
        $uploadCode = trim($_POST['upload_code'] ?? '');
        if (strlen($uploadCode) === 5) {
            $sql = "SELECT t.id, t.project_id, t.company_id, t.title FROM tasks t WHERE t.upload_code = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "s", $uploadCode);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                if ($task = mysqli_fetch_assoc($result)) {
                    // Store validated IDs in the session for security
                    $_SESSION['public_task_id'] = $task['id'];
                    $_SESSION['public_project_id'] = $task['project_id'];
                    $_SESSION['public_company_id'] = $task['company_id']; // Store company_id

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
            } else {
                $response['message'] = 'Database query failed for code validation.';
            }
        } else {
            $response['message'] = 'Code must be 5 characters long.';
        }
        break;

    // The following actions require a validated session
    case 'get_activity_types':
    case 'get_photos_for_task':
    case 'upload_photo':
        // Check for all required public session variables
        if (!isset($_SESSION['public_task_id']) || !isset($_SESSION['public_project_id']) || !isset($_SESSION['public_company_id'])) {
            $response['message'] = 'Session invalid or expired. Please re-enter your code.';
            break;
        }
        
        $taskId = $_SESSION['public_task_id'];
        $projectId = $_SESSION['public_project_id'];
        $companyId = $_SESSION['public_company_id']; // Retrieve company_id from session

        if ($action === 'get_activity_types') {
            $sql = "SELECT id, name FROM activity_types WHERE is_active = 1 ORDER BY name ASC";
            $result = mysqli_query($link, $sql);
            if ($result) {
                $response['data'] = mysqli_fetch_all($result, MYSQLI_ASSOC);
                $response['success'] = true;
            } else {
                $response['message'] = 'Failed to fetch activity types.';
            }
        } 
        elseif ($action === 'get_photos_for_task') {
            $sql = "SELECT tp.id, f.object_key AS image_path, tp.comments, tp.uploaded_at, at.name as activity_name, at.id as activity_id
                            FROM task_photos tp
                            JOIN activity_types at ON tp.activity_category_id = at.id
                            JOIN files f ON tp.file_id = f.id
                            WHERE tp.task_id = ? AND f.project_id = ? AND f.task_id = ? AND f.upload_type = 'img'
                            ORDER BY tp.uploaded_at DESC";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "iii", $taskId, $projectId, $taskId);
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
                        error_log("Wasabi URL generation failed for {$photo['image_path']}: " . $e->getMessage());
                    }
                }
                $response['data'] = $photos;
                $response['success'] = true;
                mysqli_stmt_close($stmt);
            } else {
                $response['message'] = 'Failed to prepare photos query.';
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
            $originalFileName = $file['name'];
            $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
            $uniqueId = bin2hex(random_bytes(8));
            $uniqueFileName = "photo_{$activityId}_{$uniqueId}.{$fileExtension}";

            $objectKey = "company_{$companyId}/project_{$projectId}/task_{$taskId}/img/{$uniqueFileName}";

            mysqli_begin_transaction($link);

            try {
                // 1. Upload file to Wasabi
                $s3Client->putObject([
                    'Bucket' => $wasabiConfig['bucket'],
                    'Key'    => $objectKey,
                    'SourceFile' => $file['tmp_name'],
                    'ACL'    => 'private',
                ]);

                // 2. Insert into 'files' table
                $sql_insert_file = "INSERT INTO files (project_id, task_id, user_id, original_filename, unique_filename, object_key, upload_type) VALUES (?, ?, ?, ?, ?, ?, ?)";
                if ($stmt_insert_file = mysqli_prepare($link, $sql_insert_file)) {
                    $uploadType = 'img';
                    $null_user_id = NULL; // Explicitly set to NULL for binding

                    // Corrected bind_param for files table:
                    mysqli_stmt_bind_param($stmt_insert_file, "iiissss", $projectId, $taskId, $null_user_id, $originalFileName, $uniqueFileName, $objectKey, $uploadType);
                    
                    mysqli_stmt_execute($stmt_insert_file);
                    $fileId = mysqli_insert_id($link);
                    mysqli_stmt_close($stmt_insert_file);

                    if (!$fileId) {
                        throw new Exception("Failed to insert file record.");
                    }
                } else {
                    throw new Exception("Failed to prepare file insert statement: " . mysqli_error($link));
                }

                // 3. Insert into 'task_photos' table, linking to 'files.id'
                $sql_insert_photo = "INSERT INTO task_photos (task_id, user_id, file_id, activity_category_id, comments) VALUES (?, NULL, ?, ?, ?)";
                if ($stmt_insert_photo = mysqli_prepare($link, $sql_insert_photo)) {
                    // MODIFICATION START: Corrected bind_param string to 'iiis'
                    mysqli_stmt_bind_param($stmt_insert_photo, "iiis", $taskId, $fileId, $activityId, $comments);
                    // MODIFICATION END

                    mysqli_stmt_execute($stmt_insert_photo);
                    mysqli_stmt_close($stmt_insert_photo);
                } else {
                    throw new Exception("Failed to prepare task_photos insert statement: " . mysqli_error($link));
                }

                mysqli_commit($link);
                $response['success'] = true;
                $response['message'] = 'Photo uploaded successfully!';
                $response['file_id_stored'] = $fileId;

            } catch (S3Exception $e) {
                mysqli_rollback($link);
                error_log("Wasabi Public Photo Upload Error: " . $e->getAwsErrorMessage() . " - " . $e->getAwsErrorCode());
                $response['message'] = 'Failed to upload file to storage: ' . $e->getAwsErrorMessage();
            } catch (Exception $e) {
                mysqli_rollback($link);
                $response['message'] = 'An unexpected error occurred during upload: ' . $e->getMessage();
            }
        }
        break;

    default:
        $response['message'] = 'Unknown action.';
        break;
}

mysqli_close($link);
echo json_encode($response);