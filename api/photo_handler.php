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
try {
    $s3Client = new S3Client([
        'version'     => 'latest',
        'region'      => $wasabiConfig['region'],
        'endpoint'    => $wasabiConfig['endpoint'],
        'credentials' => [
            'key'    => $wasabiConfig['key'],
            'secret' => $wasabiConfig['secret'],
        ],
        'http' => ['verify' => dirname(__DIR__) . '/config/cacert.pem']
    ]);
} catch (Exception $e) {
    $response['message'] = 'Wasabi S3 client initialization failed: ' . $e->getMessage();
    echo json_encode($response);
    exit;
}


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
            } else {
                $response['message'] = 'Failed to prepare task query.';
            }
        } else {
            $response['message'] = 'Invalid project ID.';
        }
        break;

    case 'get_activity_types':
        $sql = "SELECT id, name FROM activity_types WHERE is_active = 1 ORDER BY name ASC";
        $result = mysqli_query($link, $sql);
        if ($result) {
            $response['data'] = mysqli_fetch_all($result, MYSQLI_ASSOC);
            $response['success'] = true;
        } else {
            $response['message'] = 'Failed to fetch activity types.';
        }
        break;

    case 'get_photos_for_task':
        $taskId = filter_input(INPUT_GET, 'task_id', FILTER_VALIDATE_INT);
        if ($taskId) {
            // We need to fetch the company_id and project_id to construct the correct URL prefix
            $project_id_for_url = null;
            $company_id_for_url = null;
            $sql_ids = "SELECT t.project_id, t.company_id FROM tasks t WHERE t.id = ? LIMIT 1";
            if ($stmt_ids = mysqli_prepare($link, $sql_ids)) {
                mysqli_stmt_bind_param($stmt_ids, "i", $taskId);
                mysqli_stmt_execute($stmt_ids);
                mysqli_stmt_bind_result($stmt_ids, $project_id_for_url, $company_id_for_url);
                mysqli_stmt_fetch($stmt_ids);
                mysqli_stmt_close($stmt_ids);
            }

            if (!$project_id_for_url || !$company_id_for_url) {
                $response['message'] = "Could not retrieve project or company ID for task photos.";
                break;
            }

            // MODIFICATION START: Join with 'files' table to get the object_key
            $sql = "SELECT tp.id, f.object_key AS image_path, tp.comments, tp.uploaded_at, at.name as activity_name, at.id as activity_id
                            FROM task_photos tp
                            JOIN activity_types at ON tp.activity_category_id = at.id
                            JOIN files f ON tp.file_id = f.id  -- JOIN with files table
                            WHERE tp.task_id = ? AND f.project_id = ? AND f.task_id = ? AND f.upload_type = 'img'
                            ORDER BY tp.uploaded_at DESC";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "iii", $taskId, $project_id_for_url, $taskId); // project_id and task_id for files table
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $photos = mysqli_fetch_all($result, MYSQLI_ASSOC);
                
                foreach ($photos as &$photo) {
                    try {
                        // The image_path column now contains the full object_key from the 'files' table.
                        // We don't need to prepend company_ID here if it's consistently stored in files.object_key.
                        // The logic should be simple: just use photo['image_path'] directly.
                        $cmd = $s3Client->getCommand('GetObject', [
                            'Bucket' => $wasabiConfig['bucket'],
                            'Key'    => $photo['image_path'] // This is now the full object_key from 'files'
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
            } else {
                $response['message'] = 'Failed to prepare photos query.';
            }
        } else {
            $response['message'] = 'Invalid task ID.';
        }
        break;
        // MODIFICATION END

    case 'upload_photo':
        $taskId = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);
        $activityId = filter_input(INPUT_POST, 'activity_category_id', FILTER_VALIDATE_INT);
        $comments = trim($_POST['comments'] ?? '');

        if (!$taskId || !$activityId || !isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $response['message'] = 'Missing required data or file upload error.';
            break;
        }

        // Fetch project_id for path and company_id for the full path
        $projectId = null;
        $company_id_for_upload = null; // Use a different variable name to avoid conflict with session $company_id
        $sql_proj_company = "SELECT project_id, company_id FROM tasks WHERE id = ? AND company_id = ?";
        if ($stmt_proj_company = mysqli_prepare($link, $sql_proj_company)) {
            mysqli_stmt_bind_param($stmt_proj_company, "ii", $taskId, $company_id);
            mysqli_stmt_execute($stmt_proj_company);
            mysqli_stmt_bind_result($stmt_proj_company, $projectId, $company_id_for_upload);
            mysqli_stmt_fetch($stmt_proj_company);
            mysqli_stmt_close($stmt_proj_company);
        }

        if (!$projectId || !$company_id_for_upload) {
            $response['message'] = "Could not find the associated project or company for this task.";
            break;
        }

        $file = $_FILES['photo'];
        $originalFileName = $file['name'];
        $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        $uniqueId = bin2hex(random_bytes(8));
        $uniqueFileName = "photo_{$activityId}_{$uniqueId}.{$fileExtension}";

        // Construct the full Wasabi object key for the 'files' table
        $objectKey = "company_{$company_id_for_upload}/project_{$projectId}/task_{$taskId}/img/{$uniqueFileName}";

        // Start Transaction
        mysqli_begin_transaction($link);

        try {
            // 1. Upload file to Wasabi
            $s3Client->putObject([
                'Bucket' => $wasabiConfig['bucket'],
                'Key'    => $objectKey,
                'SourceFile' => $file['tmp_name'],
                'ACL'    => 'private', // Keep as 'private' as presigned URLs are generated
            ]);

            // 2. Insert into 'files' table
            $sql_insert_file = "INSERT INTO files (project_id, task_id, user_id, original_filename, unique_filename, object_key, upload_type) VALUES (?, ?, ?, ?, ?, ?, ?)";
            if ($stmt_insert_file = mysqli_prepare($link, $sql_insert_file)) {
                $uploadType = 'img';
                mysqli_stmt_bind_param($stmt_insert_file, "iiissss", $projectId, $taskId, $user_id, $originalFileName, $uniqueFileName, $objectKey, $uploadType);
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
            // user_id is from session ($user_id)
            $sql_insert_photo = "INSERT INTO task_photos (task_id, user_id, file_id, activity_category_id, comments) VALUES (?, ?, ?, ?, ?)";
            if ($stmt_insert_photo = mysqli_prepare($link, $sql_insert_photo)) {
                mysqli_stmt_bind_param($stmt_insert_photo, "iiiss", $taskId, $user_id, $fileId, $activityId, $comments);
                mysqli_stmt_execute($stmt_insert_photo);
                mysqli_stmt_close($stmt_insert_photo);
            } else {
                throw new Exception("Failed to prepare task_photos insert statement: " . mysqli_error($link));
            }

            mysqli_commit($link); // Commit transaction on success
            $response['success'] = true;
            $response['message'] = 'Photo uploaded successfully!';
            $response['file_id_stored'] = $fileId; // For debugging/confirmation

        } catch (S3Exception $e) {
            mysqli_rollback($link); // Rollback on S3 error
            error_log("Wasabi Photo Upload Error: " . $e->getAwsErrorMessage() . " - " . $e->getAwsErrorCode());
            $response['message'] = 'Failed to upload file to storage: ' . $e->getAwsErrorMessage();
        } catch (Exception $e) {
            mysqli_rollback($link); // Rollback on any other error
            $response['message'] = 'An unexpected error occurred during upload: ' . $e->getMessage();
        }
        break;

    default:
        $response['message'] = 'Unknown action.';
        break;
}

mysqli_close($link);
echo json_encode($response);