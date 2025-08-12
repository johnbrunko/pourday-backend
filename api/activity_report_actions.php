<?php
session_start();
header('Content-Type: application/json');
// api/activity_report_actions.php
// --- Security and Initialization ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["role_id"], [1, 2, 3])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

require_once dirname(__DIR__) . '/config/db_connect.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

$action = $_GET['action'] ?? '';
$company_id = $_SESSION['company_id'];
$response = ['success' => false, 'message' => 'Invalid action.'];

// --- S3 Client Initialization for generating presigned URLs ---
$s3Client = null;
try {
    $wasabiConfig = include(dirname(__DIR__) . '/config/wasabi_config.php');
    $s3Client = new S3Client([
        'version' => 'latest',
        'region' => $wasabiConfig['region'],
        'endpoint' => $wasabiConfig['endpoint'],
        'credentials' => [
            'key' => $wasabiConfig['key'],
            'secret' => $wasabiConfig['secret']
        ],
        'http' => [
            'verify' => dirname(__DIR__) . '/config/cacert.pem'
        ]
    ]);
    $bucketName = $wasabiConfig['bucket'];
} catch (Exception $e) {
    // S3 client failed to initialize, but we can continue for actions that don't need it.
    $s3Client = null;
}


// --- Main Action Router ---
try {
    switch ($action) {
        case 'get_tasks_for_project':
            $project_id = filter_input(INPUT_GET, 'project_id', FILTER_VALIDATE_INT);
            if (!$project_id) throw new Exception("Invalid Project ID.");

            $stmt = $link->prepare("SELECT id, title FROM tasks WHERE project_id = ? AND company_id = ? ORDER BY title ASC");
            $stmt->bind_param("ii", $project_id, $company_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_all(MYSQLI_ASSOC);
            $response = ['success' => true, 'data' => $data];
            break;

        case 'get_all_note_templates':
            $stmt = $link->prepare("SELECT id, text, category FROM field_report_templates WHERE company_id = ? AND is_active = 1 ORDER BY category, text ASC");
            $stmt->bind_param("i", $company_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $grouped_templates = [
                'Observation' => [],
                'Concern' => [],
                'Recommendation' => []
            ];

            while ($row = $result->fetch_assoc()) {
                if (array_key_exists($row['category'], $grouped_templates)) {
                    $grouped_templates[$row['category']][] = ['id' => $row['id'], 'text' => $row['text']];
                }
            }
            
            $response = ['success' => true, 'data' => $grouped_templates];
            break;

        case 'get_task_photos':
            if (!$s3Client) throw new Exception("S3 client is not configured.");
            $task_id = filter_input(INPUT_GET, 'task_id', FILTER_VALIDATE_INT);
            if (!$task_id) throw new Exception("Invalid Task ID.");

            // MODIFIED: Changed JOIN to LEFT JOIN to include photos even if they have no category
            $stmt = $link->prepare("
                SELECT f.id as file_id, f.object_key, tp.comments, at.name as activity_name
                FROM task_photos tp
                JOIN files f ON tp.file_id = f.id
                LEFT JOIN activity_types at ON tp.activity_category_id = at.id
                WHERE tp.task_id = ?
            ");
            $stmt->bind_param("i", $task_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $photos = $result->fetch_all(MYSQLI_ASSOC);

            foreach ($photos as &$photo) {
                $cmd = $s3Client->getCommand('GetObject', [
                    'Bucket' => $bucketName,
                    'Key'    => $photo['object_key']
                ]);
                $request = $s3Client->createPresignedRequest($cmd, '+10 minutes');
                $photo['thumbnail_url'] = (string)$request->getUri();
            }

            $response = ['success' => true, 'data' => $photos];
            break;

        default:
             http_response_code(400);
             $response = ['success' => false, 'message' => 'Invalid action specified.'];
             break;
    }
} catch (Exception $e) {
    http_response_code(500);
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response);
exit;
