<?php
// api/get_files.php

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

session_start();
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/db_connect.php';
$wasabiConfig = include(dirname(__DIR__) . '/config/wasabi_config.php');

header('Content-Type: application/json');

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$project_id = filter_input(INPUT_GET, 'project_id', FILTER_SANITIZE_NUMBER_INT);
$task_id = filter_input(INPUT_GET, 'task_id', FILTER_SANITIZE_NUMBER_INT);

if (!$task_id || !$project_id) {
    echo json_encode(['success' => false, 'message' => 'Project and Task ID are required.']);
    exit;
}

$response = [
    'success' => true,
    'data' => [
        'images' => [],
        'docs' => [],
        'folders' => []
    ]
];

$sql = "SELECT id, original_filename, unique_filename, object_key, upload_type FROM files WHERE task_id = ? ORDER BY original_filename ASC";
$files_to_process = [];
if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $task_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $files_to_process[] = $row;
    }
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['success' => false, 'message' => 'Database query failed.']);
    exit;
}

if (empty($files_to_process)) {
    echo json_encode($response);
    exit;
}

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

    $processed_folders = []; // Array to track unique folders we've processed

    foreach ($files_to_process as $file) {
        // Handle images and docs as individual files
        if ($file['upload_type'] === 'img' || $file['upload_type'] === 'docs') {
            $command = $s3Client->getCommand('GetObject', ['Bucket' => $wasabiConfig['bucket'], 'Key' => $file['object_key']]);
            $presignedUrl = (string)$s3Client->createPresignedRequest($command, '+15 minutes')->getUri();
            $file_data = ['id' => $file['id'], 'name' => htmlspecialchars($file['original_filename']), 'url' => $presignedUrl];
            
            if ($file['upload_type'] === 'img') {
                $response['data']['images'][] = $file_data;
            } else {
                $response['data']['docs'][] = $file_data;
            }
        } 
        // Group all 'folders' type files by their parent directory
        elseif ($file['upload_type'] === 'folders') {
            $path_parts = explode('/', $file['unique_filename']);
            $folder_name = $path_parts[0]; // This is the top-level folder name

            // If we haven't seen this folder yet, create a single entry for it
            if (!isset($processed_folders[$folder_name])) {
                $processed_folders[$folder_name] = [
                    'name' => htmlspecialchars($folder_name),
                    // This prefix is crucial for a future "download all as zip" feature
                    'key_prefix' => "{$project_id}/{$task_id}/folders/{$folder_name}"
                ];
            }
        }
    }

    // Add the unique, processed folders to the final response
    $response['data']['folders'] = array_values($processed_folders);

} catch (Exception $e) {
    error_log("Wasabi SDK Error getting file list: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Could not retrieve file links.']);
    exit;
}

mysqli_close($link);
echo json_encode($response);