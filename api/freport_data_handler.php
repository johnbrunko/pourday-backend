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

// --- Wasabi Client Initialization ---
try {
    $endpoint = $wasabiConfig['endpoint'];
    if (strpos($endpoint, 'http') !== 0) {
        $endpoint = 'https://' . $endpoint;
    }
    $s3Client = new S3Client([
        'version' => 'latest',
        'region' => $wasabiConfig['region'],
        'endpoint' => $endpoint,
        'credentials' => ['key' => $wasabiConfig['key'], 'secret' => $wasabiConfig['secret']],
        'http' => ['verify' => dirname(__DIR__) . '/config/cacert.pem']
    ]);
} catch (Exception $e) {
    $response['message'] = 'Wasabi S3 client initialization failed: ' . $e->getMessage();
    echo json_encode($response);
    exit;
}

// --- Image Upload to Wasabi (if provided) ---
$imageObjectKey = null;
if (isset($_FILES['imageFileToUpload']) && $_FILES['imageFileToUpload']['error'] === UPLOAD_ERR_OK) {
    $imageFile = $_FILES['imageFileToUpload'];
    $sanitizedImageName = preg_replace("/[^a-zA-Z0-9\._-]/", "_", $imageFile['name']);
    
    // MODIFICATION START: Constructing the new path for images
    $imageObjectKey = "company_{$company_id}/project_{$projectId}/task_{$taskId}/reports/" . $sanitizedImageName;

    try {
        $s3Client->putObject([
            'Bucket' => $wasabiConfig['bucket'],
            'Key' => $imageObjectKey,
            'SourceFile' => $imageFile['tmp_name'],
            'ACL' => 'public-read'
        ]);
    } catch (AwsException $e) {
        // Log the full AWS exception message for debugging
        error_log("Wasabi Image Upload Error: " . $e->getAwsErrorMessage() . " - " . $e->getAwsErrorCode());
        $response['message'] = 'Image upload to Wasabi failed: ' . $e->getAwsErrorMessage();
        echo json_encode($response);
        exit;
    } catch (Exception $e) {
        $response['message'] = 'An unexpected error occurred during image upload: ' . $e->getMessage();
        echo json_encode($response);
        exit;
    }
}

// --- HTML Report File Upload to Wasabi ---
// Assuming htmlFile is the actual HTML report that needs to be stored.
$htmlReportObjectKey = null;
if (isset($_FILES['htmlFileToUpload']) && $_FILES['htmlFileToUpload']['error'] === UPLOAD_ERR_OK) {
    $htmlFile = $_FILES['htmlFileToUpload'];
    $sanitizedHtmlFileName = preg_replace("/[^a-zA-Z0-9\._-]/", "_", $htmlFile['name']);

    // MODIFICATION START: Constructing the new path for HTML reports
    $htmlReportObjectKey = "company_{$company_id}/project_{$projectId}/task_{$taskId}/reports/" . $sanitizedHtmlFileName;

    try {
        $s3Client->putObject([
            'Bucket' => $wasabiConfig['bucket'],
            'Key' => $htmlReportObjectKey,
            'SourceFile' => $htmlFile['tmp_name'],
            'ACL' => 'public-read' // Assuming public-read is desired for reports
        ]);
        // Update originalHtmlFilename to be the Wasabi key to store in DB, if needed,
        // or ensure 'unique_filename' in 'files' table can store this key.
        // For 'uploaded_freports', we'll store this in a new 'report_html_key' field.
        // The 'original_filename' in 'uploaded_freports' should store the client-provided name.
    } catch (AwsException $e) {
        error_log("Wasabi HTML Report Upload Error: " . $e->getAwsErrorMessage() . " - " . $e->getAwsErrorCode());
        $response['message'] = 'HTML report upload to Wasabi failed: ' . $e->getAwsErrorMessage();
        echo json_encode($response);
        exit;
    } catch (Exception $e) {
        $response['message'] = 'An unexpected error occurred during HTML report upload: ' . $e->getMessage();
        echo json_encode($response);
        exit;
    }
}
// MODIFICATION END

// --- Database Transaction ---
mysqli_begin_transaction($link);
try {
    // Helper function to extract a numeric value
    function extract_numeric($value) {
        // Ensure $value is treated as a string before regex
        preg_match('/-?[\d\.]+/', (string)$value, $matches);
        return isset($matches[0]) ? (float)$matches[0] : null;
    }

    // 1. Extract data by targeting the specific tables and rows
    $sov_data = $allTablesData[0]['data'] ?? [];
    $spec_overall_ff = extract_numeric($sov_data[0]['column_2'] ?? null);
    $spec_overall_fl = extract_numeric($sov_data[1]['column_2'] ?? null);

    $mlv_data = $allTablesData[1]['data'] ?? [];
    $spec_min_local_ff = extract_numeric($mlv_data[0]['column_2'] ?? null);
    $spec_min_local_fl = extract_numeric($mlv_data[1]['column_2'] ?? null);

    $test_section_data = $allTablesData[2]['data'] ?? [];
    $surface_area = $test_section_data[0]['column_2'] ?? null;
    $min_readings_required = extract_numeric($test_section_data[1]['column_2'] ?? null);
    $total_readings_taken = extract_numeric($test_section_data[2]['column_2'] ?? null);

    // MODIFICATION START: Add report_html_key to the INSERT statement and parameters
    // 2. Prepare the new, comprehensive INSERT statement for 'uploaded_freports'
    $sql_report = "INSERT INTO uploaded_freports (
        company_id, project_id, task_id, report_name, original_filename, user_id,
        spec_overall_ff, spec_overall_fl, spec_min_local_ff, spec_min_local_fl,
        surface_area, min_readings_required, total_readings_taken, report_image_key, generated_pdf_key
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"; // One more '?' for generated_pdf_key

    $stmt_report = mysqli_prepare($link, $sql_report);

    // 3. Bind all 15 parameters and execute (one more 's' for generated_pdf_key)
    // We are using $htmlReportObjectKey for 'generated_pdf_key' as per the schema,
    // assuming the HTML file itself is the 'generated PDF' equivalent for now,
    // or if a PDF is generated later, this column will hold its key.
    mysqli_stmt_bind_param(
        $stmt_report,
        "iiissiddddsiiss", // Added an 's' for $htmlReportObjectKey
        $company_id, $projectId, $taskId, $reportName, $originalHtmlFilename, $user_id,
        $spec_overall_ff, $spec_overall_fl, $spec_min_local_ff, $spec_min_local_fl,
        $surface_area, $min_readings_required, $total_readings_taken, $imageObjectKey, $htmlReportObjectKey // Pass the HTML report key here
    );
    mysqli_stmt_execute($stmt_report);
    $reportId = mysqli_insert_id($link);
    mysqli_stmt_close($stmt_report);

    if (!$reportId) {
        throw new Exception("Failed to insert main report record.");
    }

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
    // MODIFICATION END

    // Commit the transaction
    mysqli_commit($link);
    $response['success'] = true;
    $response['message'] = 'Report data saved successfully!';
    $response['report_id'] = $reportId;
    $response['image_saved_as'] = $imageObjectKey; // Confirm the path if image was uploaded
    $response['html_report_saved_as'] = $htmlReportObjectKey; // Confirm the path for HTML report

} catch (Exception $e) {
    mysqli_rollback($link);
    $response['message'] = 'Database error: ' . $e->getMessage();
}

mysqli_close($link);
echo json_encode($response);