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

// Get the company_id from the session
$company_id = $_SESSION['company_id'] ?? null;
$project_id = filter_input(INPUT_GET, 'project_id', FILTER_SANITIZE_NUMBER_INT);
$task_id = filter_input(INPUT_GET, 'task_id', FILTER_SANITIZE_NUMBER_INT);

if (!$task_id || !$project_id || !$company_id) {
    echo json_encode(['success' => false, 'message' => 'Project, Task, and Company ID are required.']);
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

// The database query is scoped by company_id for security
$sql = "SELECT f.id, f.original_filename, f.unique_filename, f.object_key, f.upload_type 
        FROM files f
        JOIN tasks t ON f.task_id = t.id
        WHERE f.task_id = ? AND t.company_id = ? 
        ORDER BY f.original_filename ASC";

$files_to_process = [];
if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $task_id, $company_id);
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
        ],
        'http' => [
            'verify' => dirname(__DIR__) . '/config/cacert.pem'
        ]
    ]);

    $processed_folders = [];

    foreach ($files_to_process as $file) {
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
        elseif ($file['upload_type'] === 'folders') {
            $path_parts = explode('/', $file['unique_filename']);
            $folder_name = $path_parts[0];

            if (!isset($processed_folders[$folder_name])) {
                // --- FIXED: The key_prefix now includes the correct "company_" format ---
                $processed_folders[$folder_name] = [
                    'name' => htmlspecialchars($folder_name),
                    'key_prefix' => "company_{$company_id}/project_{$project_id}/task_{$task_id}/folders/{$folder_name}"
                ];
            }
        }
    }

    $response['data']['folders'] = array_values($processed_folders);

} catch (Exception $e) {
    error_log("Wasabi SDK Error getting file list: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Could not retrieve file links.']);
    exit;
}

mysqli_close($link);
echo json_encode($response);
