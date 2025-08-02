<?php
// File: api/photo_selector_handler.php - REFINED for NULL handling and clarity

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
    'version'       => 'latest',
    'region'        => $wasabiConfig['region'],
    'endpoint'      => $wasabiConfig['endpoint'],
    'credentials'   => [ 'key' => $wasabiConfig['key'], 'secret' => $wasabiConfig['secret'] ]
]);

// --- Action Routing ---
switch ($action) {
    case 'get_all_data':
        $projectId = filter_input(INPUT_GET, 'project_id', FILTER_VALIDATE_INT);
        $taskId = filter_input(INPUT_GET, 'task_id', FILTER_VALIDATE_INT);

        if (!$projectId || !$taskId) {
            $response['message'] = 'Project and Task IDs are required.';
            break;
        }

        try {
            $dbPhotos = [];
            // Join files table with task_photos to get all relevant image data for the task
            // LEFT JOIN ensures we get all images in the task's folder from 'files'
            // even if they don't yet have an entry in 'task_photos'.
            $sql = "SELECT f.id AS file_id, f.object_key, tp.comments, tp.activity_category_id
                    FROM files f
                    LEFT JOIN task_photos tp ON f.id = tp.file_id AND tp.task_id = ?
                    WHERE f.task_id = ? AND f.project_id = ? AND f.upload_type = 'img'";
            
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "iii", $taskId, $taskId, $projectId);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                while ($row = mysqli_fetch_assoc($result)) {
                    $dbPhotos[] = $row; // Store as an array of associative arrays
                }
                mysqli_stmt_close($stmt);
            }

            $allPhotosData = [];
            foreach ($dbPhotos as $photo) {
                // Generate presigned URL
                $cmd = $s3Client->getCommand('GetObject', ['Bucket' => $wasabiConfig['bucket'], 'Key' => $photo['object_key']]);
                $presignedUrl = (string) $s3Client->createPresignedRequest($cmd, '+60 minutes')->getUri();

                $allPhotosData[] = [
                    'file_id' => $photo['file_id'],
                    'object_key' => $photo['object_key'],
                    'presigned_url' => $presignedUrl,
                    'comments' => $photo['comments'], // NULL will be handled correctly
                    'activity_category_id' => $photo['activity_category_id'] // NULL will be handled correctly
                ];
            }

            // Get all activity types
            $activityTypes = [];
            $sql_types = "SELECT id, name FROM activity_types WHERE is_active = 1 ORDER BY name ASC";
            $result_types = mysqli_query($link, $sql_types);
            $activityTypes = mysqli_fetch_all($result_types, MYSQLI_ASSOC);

            $response['success'] = true;
            $response['data'] = [
                'allPhotos' => $allPhotosData,
                'activityTypes' => $activityTypes
            ];

        } catch (S3Exception $e) {
            $response['message'] = 'Could not generate presigned URLs from storage: ' . $e->getMessage();
        } catch (Exception $e) {
            $response['message'] = 'An unexpected error occurred: ' . $e->getMessage();
        }
        break;

    case 'update_photo_category':
        $taskId = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);
        $fileId = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT); // This is now the key!
        $activityId = filter_input(INPUT_POST, 'activity_category_id', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        $comments = trim($_POST['comments'] ?? '');

        if (!$taskId || !$fileId) {
            $response['message'] = 'Missing required data: Task ID or File ID.';
            break;
        }

        // Check if a record for this file_id and task_id already exists in task_photos
        $existingTaskPhotoId = null;
        $sql_check = "SELECT id FROM task_photos WHERE file_id = ? AND task_id = ?";
        if ($stmt_check = mysqli_prepare($link, $sql_check)) {
            mysqli_stmt_bind_param($stmt_check, "ii", $fileId, $taskId);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_bind_result($stmt_check, $existingTaskPhotoId);
            mysqli_stmt_fetch($stmt_check);
            mysqli_stmt_close($stmt_check);
        }

        if ($existingTaskPhotoId) {
            // If activityId is NULL (meaning unassign), we should DELETE the record
            // instead of updating it to NULL, assuming 'unassigned' means no record exists.
            // If you want to keep records for 'unassigned' state, change this logic.
            if ($activityId === null) {
                $sql = "DELETE FROM task_photos WHERE id = ?";
                if ($stmt = mysqli_prepare($link, $sql)) {
                    mysqli_stmt_bind_param($stmt, "i", $existingTaskPhotoId);
                    mysqli_stmt_execute($stmt);
                    $response['success'] = true;
                    $response['message'] = 'Photo unassigned successfully!';
                    mysqli_stmt_close($stmt);
                } else {
                    $response['message'] = 'Failed to prepare delete statement: ' . mysqli_error($link);
                }
            } else {
                // UPDATE existing record in task_photos
                $sql = "UPDATE task_photos SET activity_category_id = ?, comments = ?, user_id = ? WHERE id = ?";
                if ($stmt = mysqli_prepare($link, $sql)) {
                    // Use a temporary variable for the activity ID to handle potential NULL correctly
                    $activityIdForBind = $activityId;
                    mysqli_stmt_bind_param($stmt, "isii", $activityIdForBind, $comments, $user_id, $existingTaskPhotoId);
                    mysqli_stmt_execute($stmt);
                    $response['success'] = true;
                    $response['message'] = 'Photo category updated successfully!';
                    mysqli_stmt_close($stmt);
                } else {
                    $response['message'] = 'Failed to prepare update statement: ' . mysqli_error($link);
                }
            }
        } else {
            // INSERT new record into task_photos
            // Only insert if an activityId is being set (not unassigned initially)
            if ($activityId !== null) {
                $sql = "INSERT INTO task_photos (task_id, user_id, file_id, activity_category_id, comments) VALUES (?, ?, ?, ?, ?)";
                if ($stmt = mysqli_prepare($link, $sql)) {
                    mysqli_stmt_bind_param($stmt, "iiisi", $taskId, $user_id, $fileId, $activityId, $comments);
                    mysqli_stmt_execute($stmt);
                    $response['success'] = true;
                    $response['message'] = 'Photo categorized successfully!';
                    mysqli_stmt_close($stmt);
                } else {
                    $response['message'] = 'Failed to prepare insert statement: ' . mysqli_error($link);
                }
            } else {
                $response['success'] = true; // Nothing to do if unassigning a non-existent record
                $response['message'] = 'Photo was not previously assigned, no action needed.';
            }
        }
        break;
}

mysqli_close($link);
echo json_encode($response);