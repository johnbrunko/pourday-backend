<?php
// api/get_wasabi_upload_url.php

session_start();

// Set the content type to JSON and include necessary files
header('Content-Type: application/json');
require_once '../config/db_connect.php';
$wasabiConfig = require_once '../config/wasabi_config.php';
require_once '../vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

// --- FIXED: Restored dual authentication for both desktop and web apps ---
$token = null;
$user_id = null;
$company_id = null;

// 1. Check for the Authorization header (used by the desktop app)
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
} elseif (function_exists('getallheaders')) {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
    }
}

// If a token is found, validate it against the database
if ($token) {
    $sql = "SELECT id, company_id FROM users WHERE api_token = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $token);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) == 1) {
            mysqli_stmt_bind_result($stmt, $user_id, $company_id);
            mysqli_stmt_fetch($stmt);
        }
        mysqli_stmt_close($stmt);
    }
} 
// 2. If no token, check for a valid PHP session (for the web app)
elseif (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $user_id = $_SESSION['id'] ?? null;
    $company_id = $_SESSION['company_id'] ?? null;
}

// Final check for valid credentials
if (!$user_id || !$company_id) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or expired token.']);
    exit();
}
// --- END OF FIX ---

// --- Get data from the POST request ---
$projectId = isset($_POST['project_id']) ? $_POST['project_id'] : null;
$taskId = isset($_POST['task_id']) ? $_POST['task_id'] : null;
$filename = isset($_POST['filename']) ? $_POST['filename'] : null;
$contentType = isset($_POST['contentType']) ? $_POST['contentType'] : 'application/octet-stream';
$uploadType = isset($_POST['upload_type']) ? $_POST['upload_type'] : null;

if (!$projectId || !$taskId || !$filename || !$uploadType) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required upload parameters.']);
    exit();
}

// --- Construct the file key for Wasabi ---
$fileKey = "company_{$company_id}/project_{$projectId}/task_{$taskId}/{$uploadType}/{$filename}";

// --- Create a pre-signed URL for uploading ---
try {
    $s3Client = new S3Client([
        'version'     => 'latest',
        'region'      => $wasabiConfig['region'],
        'endpoint'    => $wasabiConfig['endpoint'],
        'credentials' => [
            'key'    => $wasabiConfig['key'],
            'secret' => $wasabiConfig['secret'],
        ],
        'http' => [
            'verify' => dirname(__DIR__) . '/config/cacert.pem'
        ]
    ]);

    $cmd = $s3Client->getCommand('PutObject', [
        'Bucket'      => $wasabiConfig['bucket'],
        'Key'         => $fileKey,
        'ContentType' => $contentType
    ]);

    // Create a pre-signed request valid for 15 minutes
    $request = $s3Client->createPresignedRequest($cmd, '+15 minutes');
    $presignedUrl = (string)$request->getUri();

    // Return both the URL for uploading and the key for the database record
    echo json_encode([
        'status' => 'success', 
        'uploadUrl' => $presignedUrl,
        'fileKey' => $fileKey 
    ]);

} catch (S3Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error creating upload link: ' . $e->getMessage()]);
}
?>