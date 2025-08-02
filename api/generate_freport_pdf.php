<?php
session_start();
require_once dirname(__DIR__) . '/config/db_connect.php'; 
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Helper function to format confidence interval strings.
 */
function format_interval($value) {
    if (is_null($value) || trim($value) === '') { return ''; }
    return htmlspecialchars(preg_replace('/\s+/', ' <=> ', trim($value)));
}

/**
 * Helper function to format Pass/Fail table cells.
 */
function format_status_cell($status) {
    if (is_null($status) || trim($status) === '') { return '<td>N/A</td>'; }
    $clean_status = htmlspecialchars(trim($status));
    $class = strtolower($clean_status);
    return '<td class="' . $class . '">' . $clean_status . '</td>';
}

$reportId = filter_var($_GET['report_id'] ?? null, FILTER_VALIDATE_INT);
if (!$reportId) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid Report ID provided.']);
    exit;
}

try {
    $company_id = $_SESSION['company_id'];
    
    // --- 1. Fetch Data ---
    $stmt_details = $link->prepare("
        SELECT 
            fr.*, 
            t.scheduled,
            p.job_name, p.street_1, p.street_2, p.city, p.state, p.zip, 
            cust.customer_name, 
            c1.first_name AS contact_first_name, c1.last_name AS contact_last_name, c1.title AS contact_title, 
            uploader.first_name AS uploader_first_name, uploader.last_name AS uploader_last_name 
        FROM uploaded_freports fr 
        JOIN projects p ON fr.project_id = p.id 
        JOIN customers cust ON p.customer_id = cust.id 
        LEFT JOIN contacts c1 ON p.contact_id_1 = c1.id 
        LEFT JOIN users uploader ON fr.user_id = uploader.id
        LEFT JOIN tasks t ON fr.task_id = t.id
        WHERE fr.id = ? AND fr.company_id = ?
    ");
    $stmt_details->bind_param("ii", $reportId, $company_id);
    $stmt_details->execute();
    $reportData = $stmt_details->get_result()->fetch_assoc();

    if (!$reportData) { throw new Exception("Report not found or permission denied."); }

    $stmt_composite = $link->prepare("SELECT * FROM freport_composite WHERE report_id = ?");
    $stmt_composite->bind_param("i", $reportId);
    $stmt_composite->execute();
    $compositeFNumbers = $stmt_composite->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt_sample = $link->prepare("SELECT * FROM freport_sample WHERE report_id = ? ORDER BY source_html_table_index, id");
    $stmt_sample->bind_param("i", $reportId);
    $stmt_sample->execute();
    $sampleFNumbers = $stmt_sample->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt_company = $link->prepare("SELECT company_name, logo FROM companies WHERE id = ?");
    $stmt_company->bind_param("i", $company_id);
    $stmt_company->execute();
    $companyData = $stmt_company->get_result()->fetch_assoc();

    $selectedUserId = filter_var($_GET['selected_user_id'] ?? null, FILTER_VALIDATE_INT);
    $userIdToQuery = $selectedUserId ?: $reportData['user_id'];
    $stmt_user = $link->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt_user->bind_param("i", $userIdToQuery);
    $stmt_user->execute();
    $user = $stmt_user->get_result()->fetch_assoc();
    $preparer_first_name = $user['first_name'] ?? 'N/A';
    $preparer_last_name = $user['last_name'] ?? '';

    // --- 2. Fetch Images ---
    $imageDataUri = null;
    $logoDataUri = null;
    $wasabiConfig = include(dirname(__DIR__) . '/config/wasabi_config.php');
    $s3Client = new S3Client(['version' => 'latest', 'region' => $wasabiConfig['region'], 'endpoint' => $wasabiConfig['endpoint'], 'credentials' => ['key' => $wasabiConfig['key'], 'secret' => $wasabiConfig['secret']], 'http' => ['verify' => dirname(__DIR__) . '/config/cacert.pem']]);
    if (!empty($reportData['report_image_key'])) {
        $resultImg = $s3Client->getObject(['Bucket' => $wasabiConfig['bucket'], 'Key' => $reportData['report_image_key']]);
        $imageDataUri = 'data:' . $resultImg['ContentType'] . ';base64,' . base64_encode($resultImg['Body']->getContents());
    }
    if (!empty($companyData['logo'])) {
        $resultLogo = $s3Client->getObject(['Bucket' => $wasabiConfig['bucket'], 'Key' => $companyData['logo']]);
        $contentType = $resultLogo['ContentType'];
        if ($contentType === 'application/octet-stream') {
            $fileExtension = strtolower(pathinfo($companyData['logo'], PATHINFO_EXTENSION));
            $mimeTypes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif'];
            $contentType = $mimeTypes[$fileExtension] ?? 'application/octet-stream';
        }
        $logoDataUri = 'data:' . $contentType . ';base64,' . base64_encode($resultLogo['Body']->getContents());
    }

    // --- 3. Prepare Variables for Templates ---
    foreach (['contact_first_name', 'contact_last_name', 'contact_title', 'customer_name', 'job_name', 'street_1', 'street_2', 'city', 'state', 'zip', 'report_name', 'upload_timestamp', 'scheduled', 'spec_overall_ff', 'spec_overall_fl', 'uploader_first_name', 'uploader_last_name'] as $key) {
        $$key = $reportData[$key] ?? null;
    }

    // --- 4. Build HTML Document ---
    $cssContent = file_get_contents(dirname(__DIR__) . '/css/pdf_report.css');
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>F-Number Report: <?php echo htmlspecialchars($reportData['report_name']); ?></title>
        <style><?php echo $cssContent; ?></style>
    </head>
    <body>
        <header>
            <table class="header-table"><tr>
                <td class="header-left"><p><strong>Project:</strong> <?php echo htmlspecialchars($reportData['job_name']); ?></p><p><strong>Report:</strong> <?php echo htmlspecialchars($reportData['report_name']); ?></p></td>
                <td class="header-right"><?php echo $logoDataUri ? '<img src="' . $logoDataUri . '" class="company-logo">' : ''; ?></td>
            </tr></table>
        </header>
        <footer>
            <table class="footer-table"><tr>
                <td class="footer-left"><?php echo htmlspecialchars($companyData['company_name']); ?></td>
                <td class="footer-center page-number"></td>
                <td class="footer-right">Revolutionizing Concrete</td>
            </tr></table>
        </footer>
        <main>
            <?php include dirname(__DIR__) . '/components/report_text_blocks/freport_intro.phtml'; ?>
            
            <h4>Contract Specifications</h4>
            <table class="spec-table-container">
                <tr>
                    <td>
                        <table class="spec-table">
                            <thead><tr><th class="table-title" colspan="2">Specified FF Value</th></tr></thead>
                            <tbody>
                                <tr><td>Overall</td><td><?php echo htmlspecialchars($reportData['spec_overall_ff']); ?></td></tr>
                                <tr><td>Minimum Local</td><td><?php echo htmlspecialchars($reportData['spec_min_local_ff']); ?></td></tr>
                            </tbody>
                        </table>
                    </td>
                    <td>
                        <table class="spec-table">
                            <thead><tr><th class="table-title" colspan="2">Specified FL Values</th></tr></thead>
                            <tbody>
                                <tr><td>Overall</td><td><?php echo htmlspecialchars($reportData['spec_overall_fl']); ?></td></tr>
                                <tr><td>Minimum Local</td><td><?php echo htmlspecialchars($reportData['spec_min_local_fl']); ?></td></tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </table>

            <table class="spec-table">
                <thead><tr><th class="table-title" colspan="2">Test Section Detail</th></tr></thead>
                <tbody>
                    <tr><td>Surface Area</td><td><?php echo htmlspecialchars($reportData['surface_area']); ?></td></tr>
                    <tr><td>Minimum Readings Required</td><td><?php echo htmlspecialchars($reportData['min_readings_required']); ?></td></tr>
                    <tr><td>Total Number of Readings</td><td><?php echo htmlspecialchars($reportData['total_readings_taken']); ?></td></tr>
                </tbody>
            </table>
            
            <h4>Composite F-Numbers</h4>
            <table>
                <thead><tr><th>Metric</th><th>Overall</th><th>90% Conf.</th><th>SOV P/F</th><th>Min</th><th>90% Conf.</th><th>MLV P/F</th></tr></thead>
                <tbody>
                    <?php foreach ($compositeFNumbers as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['metric']); ?></td>
                        <td><?php echo htmlspecialchars($row['overall_value']); ?></td>
                        <td><?php echo format_interval($row['conf_interval_90']); ?></td>
                        <?php echo format_status_cell($row['sov_pass_fail']); ?>
                        <td><?php echo htmlspecialchars($row['min_value']); ?></td>
                        <td><?php echo format_interval($row['min_conf_interval_90']); ?></td>
                        <?php echo format_status_cell($row['mlv_pass_fail']); ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($imageDataUri): ?>
                <div class="image-container">
                    <h4>Testing Map</h4>
                    <div class="text-center"><img src="<?php echo $imageDataUri; ?>" class="report-image"></div>
                </div>
            <?php endif; ?>

            <?php
            $samplesGrouped = [];
            foreach ($sampleFNumbers as $row) { $samplesGrouped[$row['sample_name']][] = $row; }
            if (!empty($samplesGrouped)):
            ?>
                <h4>Sample F-Numbers</h4>
                <?php foreach ($samplesGrouped as $name => $samples): ?>
                    <div class="sample-section">
                        <h5><?php echo htmlspecialchars(preg_replace('/Sample \(HTML Table (\d+)\)/', 'Sample $1', $name)); ?></h5>
                        <table class="sample-table">
                            <colgroup><col class="metric"><col class="value"><col class="conf"><col class="passfail"></colgroup>
                            <thead><tr><th>Metric</th><th>Overall Value</th><th>90% Conf.</th><th>MLV P/F</th></tr></thead>
                            <tbody>
                                <?php foreach ($samples as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['metric']); ?></td>
                                    <td><?php echo htmlspecialchars($row['overall_value']); ?></td>
                                    <td><?php echo format_interval($row['conf_interval_90']); ?></td>
                                    <?php echo format_status_cell($row['mlv_pass_fail']); ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    // --- 5. Render and Output PDF ---
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('Letter', 'portrait');
    $dompdf->render();
    $pdfOutput = $dompdf->output();

    // MODIFIED: Added 'FF_' prefix to the filename.
    $pdfFilename = 'FF_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $reportData['report_name']) . '_' . date('Ymd') . '.pdf';
    
    // MODIFIED: Changed the file path structure to match the new rules.
    $projectId = $reportData['project_id'];
    $taskId = $reportData['task_id'];
    $pdfObjectKey = "company_{$company_id}/project_{$projectId}/task_{$taskId}/docs/{$pdfFilename}";

    if (isset($s3Client)) {
        $s3Client->putObject(['Bucket' => $wasabiConfig['bucket'], 'Key' => $pdfObjectKey, 'Body' => $pdfOutput, 'ContentType' => 'application/pdf']);
        $stmt_update = $link->prepare("UPDATE uploaded_freports SET generated_pdf_key = ? WHERE id = ?");
        $stmt_update->bind_param("si", $pdfObjectKey, $reportId);
        $stmt_update->execute();
    }
    
    header_remove();
    header('Access-Control-Expose-Headers: Content-Disposition');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $pdfFilename . '"');
    header('Content-Length: ' . strlen($pdfOutput));
    echo $pdfOutput;
    exit;

} catch (Exception $e) {
    header_remove();
    http_response_code(500);
    header('Content-Type: application/json');
    error_log("PDF Generation Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred during PDF generation: ' . $e->getMessage()]);
    exit;
}