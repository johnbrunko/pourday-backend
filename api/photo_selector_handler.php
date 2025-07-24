<?php
// File: api/photo_selector_handler.php

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
    'credentials' => [ 'key' => $wasabiConfig['key'], 'secret' => $wasabiConfig['secret'] ]
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
            // 1. Get all photos from the database for this task
            $dbPhotos = [];
            $sql = "SELECT id, image_path, comments, activity_category_id FROM task_photos WHERE task_id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "i", $taskId);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                while ($row = mysqli_fetch_assoc($result)) {
                    $dbPhotos[$row['image_path']] = $row; // Use path as key for easy lookup
                }
                mysqli_stmt_close($stmt);
            }

            // 2. List all objects from the Wasabi folder
            $folderPath = "{$projectId}/{$taskId}/images/";
            $s3Objects = $s3Client->listObjectsV2([
                'Bucket' => $wasabiConfig['bucket'],
                'Prefix' => $folderPath
            ]);

            $allPhotosData = [];
            if (isset($s3Objects['Contents'])) {
                foreach ($s3Objects['Contents'] as $object) {
                    $key = $object['Key'];
                    // Skip the folder itself, only process files
                    if (substr($key, -1) === '/') continue;

                    $cmd = $s3Client->getCommand('GetObject', ['Bucket' => $wasabiConfig['bucket'], 'Key' => $key]);
                    $presignedUrl = (string) $s3Client->createPresignedRequest($cmd, '+60 minutes')->getUri();

                    $allPhotosData[] = [
                        'image_path' => $key,
                        'presigned_url' => $presignedUrl,
                        'comments' => $dbPhotos[$key]['comments'] ?? null,
                        'activity_category_id' => $dbPhotos[$key]['activity_category_id'] ?? null
                    ];
                }
            }

            // 3. Get all activity types
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
            $response['message'] = 'Could not list files from storage: ' . $e->getMessage();
        } catch (Exception $e) {
            $response['message'] = 'An unexpected error occurred: ' . $e->getMessage();
        }
        break;

    case 'update_photo_category':
        $taskId = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);
        $imagePath = $_POST['image_path'] ?? null;
        $activityId = filter_input(INPUT_POST, 'activity_category_id', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        $comments = trim($_POST['comments'] ?? '');

        if (!$taskId || !$imagePath) {
            $response['message'] = 'Missing required data.';
            break;
        }

        // Check if a record for this image path already exists
        $existingId = null;
        $sql_check = "SELECT id FROM task_photos WHERE image_path = ?";
        if ($stmt_check = mysqli_prepare($link, $sql_check)) {
            mysqli_stmt_bind_param($stmt_check, "s", $imagePath);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_bind_result($stmt_check, $existingId);
            mysqli_stmt_fetch($stmt_check);
            mysqli_stmt_close($stmt_check);
        }

        if ($existingId) {
            // UPDATE existing record
            $sql = "UPDATE task_photos SET activity_category_id = ?, comments = ?, user_id = ? WHERE id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "isii", $activityId, $comments, $user_id, $existingId);
                mysqli_stmt_execute($stmt);
                $response['success'] = true;
                $response['message'] = 'Photo category updated successfully!';
                mysqli_stmt_close($stmt);
            }
        } else {
            // INSERT new record
            $sql = "INSERT INTO task_photos (task_id, user_id, image_path, activity_category_id, comments) VALUES (?, ?, ?, ?, ?)";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "iisis", $taskId, $user_id, $imagePath, $activityId, $comments);
                mysqli_stmt_execute($stmt);
                $response['success'] = true;
                $response['message'] = 'Photo categorized successfully!';
                mysqli_stmt_close($stmt);
            }
        }
        break;
}

mysqli_close($link);
echo json_encode($response);