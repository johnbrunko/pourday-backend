<?php
// Set the content type to JSON and include necessary files
header('Content-Type: application/json');
require_once '../config/db_connect.php';   // For the database connection
$wasabiConfig = require_once '../config/wasabi_config.php'; // Include Wasabi config
require_once '../vendor/autoload.php';   // For the AWS SDK

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// --- Get the API token from the request headers ---
$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

if (!$token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authorization token not found.']);
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
    echo json_encode(['success' => false, 'message' => 'Invalid or expired token.']);
    exit();
}

// --- Get the folder prefix from the JSON input ---
$json_data = file_get_contents('php://input');
$data = json_decode($json_data);
$folderPrefix = isset($data->folderPrefix) ? $data->folderPrefix : null;

if (!$folderPrefix) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Folder prefix not provided.']);
    exit();
}

// --- Use the AWS SDK to list files in Wasabi ---
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
        // *** NEW: ADD THIS FOR LOCAL MAMP DEVELOPMENT ***
        'http'    => [
            'verify' => false
        ]
    ]);

    $result = $s3Client->listObjectsV2([
        'Bucket' => $wasabiConfig['bucket'],
        'Prefix' => $folderPrefix,
    ]);

    $files = [];
    if (isset($result['Contents'])) {
        foreach ($result['Contents'] as $object) {
            $files[] = $object['Key'];
        }
    }

    echo json_encode(['success' => true, 'files' => $files]);

} catch (AwsException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error communicating with storage: ' . $e->getMessage()]);
}
?>
