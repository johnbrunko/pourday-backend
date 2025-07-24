<?php
session_start();
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/db_connect.php';
$wasabiConfig = include(dirname(__DIR__) . '/config/wasabi_config.php');

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An error occurred processing the report.'];

// --- Security Check: Ensure user is logged in ---
if (!isset($_SESSION['id']) || !isset($_SESSION['company_id'])) {
    $response['message'] = 'Unauthorized: User not logged in.';
    echo json_encode($response);
    exit();
}

$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['id'];

// --- Input Validation and Data Extraction ---
$taskId = filter_input(INPUT_POST, 'taskId', FILTER_VALIDATE_INT);
$projectId = filter_input(INPUT_POST, 'projectId', FILTER_VALIDATE_INT);
$allTablesDataJson = $_POST['allTablesData'] ?? null;
$reportName = trim($_POST['reportName'] ?? '');
$originalHtmlFilename = $_FILES['htmlFile']['name'] ?? 'N/A';

if (!$taskId || !$projectId || $allTablesDataJson === null || empty($reportName)) {
    $response['message'] = 'Required fields are missing (Task, Project, Parsed Data, or Report Name).';
    echo json_encode($response);
    exit;
}

$allTablesData = json_decode($allTablesDataJson, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $response['message'] = 'Invalid JSON data for tables: ' . json_last_error_msg();
    echo json_encode($response);
    exit;
}

// --- Image Upload to Wasabi (if provided) ---
$imageObjectKey = null;
if (isset($_FILES['imageFileToUpload']) && $_FILES['imageFileToUpload']['error'] === UPLOAD_ERR_OK) {
    $imageFile = $_FILES['imageFileToUpload'];
    $sanitizedImageName = preg_replace("/[^a-zA-Z0-9\._-]/", "_", $imageFile['name']);
    // The key now points to the correct 'report_images' path as requested
    $imageObjectKey = "report_images/{$projectId}/{$taskId}/{$sanitizedImageName}";

    try {
        $endpoint = $wasabiConfig['endpoint'];
        if (strpos($endpoint, 'http') !== 0) {
            $endpoint = 'https://' . $endpoint;
        }
        $s3Client = new S3Client([
            'version' => 'latest', 'region' => $wasabiConfig['region'], 'endpoint' => $endpoint,
            'credentials' => ['key' => $wasabiConfig['key'], 'secret' => $wasabiConfig['secret']]
        ]);
        $s3Client->putObject([
            'Bucket' => $wasabiConfig['bucket'], 'Key' => $imageObjectKey,
            'SourceFile' => $imageFile['tmp_name'], 'ACL' => 'public-read'
        ]);
    } catch (Exception $e) {
        $response['message'] = 'Image upload to Wasabi failed: ' . $e->getMessage();
        echo json_encode($response); exit;
    }
}

// --- Database Transaction ---
mysqli_begin_transaction($link);
try {
    // Helper function to extract a numeric value
    function extract_numeric($value) {
        // This regex will handle numbers, decimals, and negative signs
        preg_match('/-?[\d\.]+/', (string)$value, $matches);
        return isset($matches[0]) ? (float)$matches[0] : null;
    }

    // 1. Extract data by targeting the specific tables and rows
    // Note: The JS parser uses 'column_2' as the key for the value cell since there are no <th> headers.

    // Table 0: Specified Overall Values
    $sov_data = $allTablesData[0]['data'] ?? [];
    $spec_overall_ff = extract_numeric($sov_data[0]['column_2'] ?? null); // Row 0 is FF
    $spec_overall_fl = extract_numeric($sov_data[1]['column_2'] ?? null); // Row 1 is FL

    // Table 1: Minimum Local Values
    $mlv_data = $allTablesData[1]['data'] ?? [];
    $spec_min_local_ff = extract_numeric($mlv_data[0]['column_2'] ?? null); // Row 0 is FF
    $spec_min_local_fl = extract_numeric($mlv_data[1]['column_2'] ?? null); // Row 1 is FL

    // Table 2: Test Section Details
    $test_section_data = $allTablesData[2]['data'] ?? [];
    $surface_area = $test_section_data[0]['column_2'] ?? null; // Row 0 is Surface Area
    $min_readings_required = extract_numeric($test_section_data[1]['column_2'] ?? null); // Row 1 is Min Readings
    $total_readings_taken = extract_numeric($test_section_data[2]['column_2'] ?? null); // Row 2 is Total Readings

    // 2. Prepare the new, comprehensive INSERT statement for 'uploaded_freports'
    $sql_report = "INSERT INTO uploaded_freports (
        company_id, project_id, task_id, report_name, original_filename, user_id,
        spec_overall_ff, spec_overall_fl, spec_min_local_ff, spec_min_local_fl,
        surface_area, min_readings_required, total_readings_taken, report_image_key
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt_report = mysqli_prepare($link, $sql_report);

    // 3. Bind all 14 parameters and execute
    mysqli_stmt_bind_param(
        $stmt_report,
        "iiissiddddsiis", // Corrected string with 14 characters
        $company_id, $projectId, $taskId, $reportName, $originalHtmlFilename, $user_id,
        $spec_overall_ff, $spec_overall_fl, $spec_min_local_ff, $spec_min_local_fl,
        $surface_area, $min_readings_required, $total_readings_taken, $imageObjectKey
    );
    mysqli_stmt_execute($stmt_report);
    $reportId = mysqli_insert_id($link);
    mysqli_stmt_close($stmt_report);

    if (!$reportId) {
        throw new Exception("Failed to insert main report record.");
    }

    // --- The rest of the original code for other tables remains the same ---

    // 4. Insert into freport_composite (from Table 3)
    $compositeData = $allTablesData[3]['data'] ?? [];
    if (!empty($compositeData)) {
        $sql_composite = "INSERT INTO freport_composite (report_id, metric, overall_value, conf_interval_90, sov_pass_fail, min_value, min_conf_interval_90, mlv_pass_fail) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_composite = mysqli_prepare($link, $sql_composite);
        foreach ($compositeData as $row) {
            $ov = extract_numeric($row['overall_value'] ?? null);
            $mv = extract_numeric($row['min_value'] ?? null);
            mysqli_stmt_bind_param($stmt_composite, "isdssdss", $reportId, $row['metric'], $ov, $row['conf_interval_90'], $row['sov_pass_fail'], $mv, $row['min_conf_interval_90'], $row['mlv_pass_fail']);
            mysqli_stmt_execute($stmt_composite);
        }
        mysqli_stmt_close($stmt_composite);
    }

    // 5. Insert into freport_sample (from Table 4 onwards)
    if (count($allTablesData) > 4) {
        $sql_sample = "INSERT INTO freport_sample (report_id, source_html_table_index, sample_name, metric, overall_value, conf_interval_90, mlv_pass_fail) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_sample = mysqli_prepare($link, $sql_sample);
        for ($i = 4; $i < count($allTablesData); $i++) {
            $sampleTable = $allTablesData[$i];
            foreach ($sampleTable['data'] as $row) {
                $headers = $sampleTable['headers'];
                $ov_sample = extract_numeric($row[$headers[1]] ?? null);
                mysqli_stmt_bind_param($stmt_sample, "iisssss", $reportId, $sampleTable['tableIndex'], $sampleTable['sampleName'], $row[$headers[0]], $ov_sample, $row[$headers[2]], $row[$headers[3]]);
                mysqli_stmt_execute($stmt_sample);
            }
        }
        mysqli_stmt_close($stmt_sample);
    }

    // Commit the transaction
    mysqli_commit($link);
    $response['success'] = true;
    $response['message'] = 'Report data saved successfully!';
    $response['report_id'] = $reportId;

} catch (Exception $e) {
    mysqli_rollback($link);
    $response['message'] = 'Database error: ' . $e->getMessage();
}

mysqli_close($link);
echo json_encode($response);