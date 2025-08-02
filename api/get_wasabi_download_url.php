<?php
// Set the content type to JSON and include necessary files
header('Content-Type: application/json');
require_once '../config/db_connect.php';
$wasabiConfig = require_once '../config/wasabi_config.php'; // Include Wasabi config
require_once '../vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// --- Get the API token from the request headers ---
$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

if (!$token) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authorization token not found.']);
    exit();
}

// --- Validate the token ---
$user_id = null;
$sql = "SELECT id FROM users WHERE api_token = ?";
if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) == 1) {
        mysqli_stmt_bind_result($stmt, $user_id);
        mysqli_stmt_fetch($stmt);
    }
    mysqli_stmt_close($stmt);
}

if (!$user_id) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or expired token.']);
    exit();
}

// --- Get the file key from the JSON input ---
$json_data = file_get_contents('php://input');
$data = json_decode($json_data);
$fileKey = isset($data->fileKey) ? $data->fileKey : null;

if (!$fileKey) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'File key not provided.']);
    exit();
}

// --- Create a pre-signed URL for downloading ---
try {
    // Use the configuration from your wasabi_config.php file
    $s3Client = new S3Client([
        'version'     => 'latest',
        'region'      => $wasabiConfig['region'],
        'endpoint'    => $wasabiConfig['endpoint'],
        'credentials' => [
            'key'    => $wasabiConfig['key'],
            'secret' => $wasabiConfig['secret'],
        ],
        // *** ADD THIS FOR LOCAL MAMP DEVELOPMENT TO FIX SSL ERROR ***
        'http'    => [
            'verify' => false
        ]
    ]);

    $cmd = $s3Client->getCommand('GetObject', [
        'Bucket' => $wasabiConfig['bucket'],
        'Key'    => $fileKey
    ]);

    // Create a pre-signed request valid for 15 minutes
    $request = $s3Client->createPresignedRequest($cmd, '+15 minutes');

    // Get the actual URL
    $presignedUrl = (string)$request->getUri();

    echo json_encode(['status' => 'success', 'downloadUrl' => $presignedUrl]);

} catch (AwsException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error creating download link: ' . $e->getMessage()]);
}
?>