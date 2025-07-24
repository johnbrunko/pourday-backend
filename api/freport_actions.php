<?php

ini_set("log_errors", 1);
ini_set("error_log", dirname(__DIR__) . "/debug_log.txt");
error_reporting(E_ALL);


session_start();
// This should be your new project's connection file
require_once dirname(__DIR__) . '/config/db_connect.php'; 
// Include the AWS SDK autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// --- Security & Setup ---
header('Content-Type: application/json');
$response = ['success' => false, 'data' => [], 'message' => 'Invalid action specified.'];
$action = $_GET['action'] ?? null;
$company_id = $_SESSION['company_id'] ?? 0;

if ($company_id === 0) {
    $response['message'] = 'Authentication Error: Company ID not found.';
    echo json_encode($response);
    exit;
}

// --- Main Logic ---
try {
    if ($action === 'get_projects') {
        // Fetches all projects for the logged-in user's company
        $stmt = $link->prepare("SELECT id, job_name FROM projects WHERE company_id = ? ORDER BY job_name ASC");
        $stmt->bind_param("i", $company_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $response = ['success' => true, 'data' => $result->fetch_all(MYSQLI_ASSOC)];

    } elseif ($action === 'get_users') {
        // Fetches all users for the "Prepared By" dropdown
        $stmt = $link->prepare("SELECT id, first_name, last_name FROM users WHERE company_id = ? ORDER BY last_name, first_name");
        $stmt->bind_param("i", $company_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $response = ['success' => true, 'data' => $result->fetch_all(MYSQLI_ASSOC)];

    } elseif ($action === 'get_reports_for_project' && isset($_GET['project_id'])) {
        // Fetches all uploaded F-Number reports for a given project
        $projectId = filter_var($_GET['project_id'], FILTER_VALIDATE_INT);
        $stmt = $link->prepare("SELECT id, report_name FROM uploaded_freports WHERE project_id = ? AND company_id = ? ORDER BY report_name ASC");
        $stmt->bind_param("ii", $projectId, $company_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $response = ['success' => true, 'data' => $result->fetch_all(MYSQLI_ASSOC)];

    } elseif ($action === 'get_report_details' && isset($_GET['report_id'])) {
        // Fetches all data for a single report, including related tables and a Wasabi image URL
        $reportId = filter_var($_GET['report_id'], FILTER_VALIDATE_INT);
        $data = [];

        // 1. Get main report details from 'uploaded_freports'
        $stmt_details = $link->prepare("SELECT * FROM uploaded_freports WHERE id = ? AND company_id = ?");
        $stmt_details->bind_param("ii", $reportId, $company_id);
        $stmt_details->execute();
        $detailsResult = $stmt_details->get_result();
        $data['report_details'] = $detailsResult->fetch_assoc();

        if ($data['report_details']) {
            // 2. Get composite F-Numbers
            $stmt_composite = $link->prepare("SELECT * FROM freport_composite WHERE report_id = ?");
            $stmt_composite->bind_param("i", $reportId);
            $stmt_composite->execute();
            $compositeResult = $stmt_composite->get_result();
            $data['composite_f_numbers'] = $compositeResult->fetch_all(MYSQLI_ASSOC);

            // 3. Get sample F-Numbers
            $stmt_sample = $link->prepare("SELECT * FROM freport_sample WHERE report_id = ? ORDER BY source_html_table_index, id");
            $stmt_sample->bind_param("i", $reportId);
            $stmt_sample->execute();
            $sampleResult = $stmt_sample->get_result();
            $data['sample_f_numbers'] = $sampleResult->fetch_all(MYSQLI_ASSOC);

            // 4. Generate Pre-signed URL for Wasabi Image
            $imageKey = $data['report_details']['report_image_key'];
            if (!empty($imageKey)) {
                $wasabiConfig = include(dirname(__DIR__) . '/config/wasabi_config.php');
                $s3Client = new S3Client([
                    'version'     => 'latest',
                    'region'      => $wasabiConfig['region'],
                    'endpoint'    => $wasabiConfig['endpoint'],
                    'credentials' => ['key' => $wasabiConfig['key'], 'secret' => $wasabiConfig['secret']]
                ]);
                $command = $s3Client->getCommand('GetObject', [
                    'Bucket' => $wasabiConfig['bucket'],
                    'Key'    => $imageKey,
                ]);
                // Create a temporary URL valid for 15 minutes
                $request = $s3Client->createPresignedRequest($command, '+15 minutes');
                $data['report_details']['image_full_path'] = (string) $request->getUri();
            } else {
                $data['report_details']['image_full_path'] = null;
            }
            $response = ['success' => true, 'data' => $data];
        } else {
            $response['message'] = 'Report not found or permission denied.';
        }
    }

} catch (Exception $e) {
    // Catch any exceptions from database or AWS SDK
    error_log("Error in freport_actions.php: " . $e->getMessage());
    $response['message'] = 'An unexpected server error occurred.';
    http_response_code(500);
}

echo json_encode($response);