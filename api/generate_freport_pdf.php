<?php
session_start();
// This should be your new project's connection file
require_once dirname(__DIR__) . '/config/db_connect.php'; 
// Include the Composer autoloader for Dompdf and AWS SDK
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Dompdf\Dompdf;
use Dompdf\Options;

// --- Initial Setup & Validation ---
$reportId = filter_var($_GET['report_id'] ?? null, FILTER_VALIDATE_INT);

if (!$reportId) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid Report ID provided.']);
    exit;
}

try {
    // --- 1. Fetch All Report Data ---
    $company_id = $_SESSION['company_id'];

    // Main query to get report details and join with project info
    $stmt_details = $link->prepare("
        SELECT fr.*, p.job_name 
        FROM uploaded_freports fr
        JOIN projects p ON fr.project_id = p.id
        WHERE fr.id = ? AND fr.company_id = ?
    ");
    $stmt_details->bind_param("ii", $reportId, $company_id);
    $stmt_details->execute();
    $detailsResult = $stmt_details->get_result();
    $reportData = $detailsResult->fetch_assoc();

    if (!$reportData) {
        throw new Exception("Report not found or permission denied.");
    }
    
    // Fetch Composite F-Numbers
    $stmt_composite = $link->prepare("SELECT * FROM freport_composite WHERE report_id = ?");
    $stmt_composite->bind_param("i", $reportId);
    $stmt_composite->execute();
    $compositeResult = $stmt_composite->get_result();
    $compositeFNumbers = $compositeResult->fetch_all(MYSQLI_ASSOC);

    // Fetch Sample F-Numbers
    $stmt_sample = $link->prepare("SELECT * FROM freport_sample WHERE report_id = ? ORDER BY source_html_table_index, id");
    $stmt_sample->bind_param("i", $reportId);
    $stmt_sample->execute();
    $sampleResult = $stmt_sample->get_result();
    $sampleFNumbers = $sampleResult->fetch_all(MYSQLI_ASSOC);

    // --- 2. Determine Preparer's Name ---
    $selectedUserId = filter_var($_GET['selected_user_id'] ?? null, FILTER_VALIDATE_INT);
    $preparerName = 'N/A';
    $userIdToQuery = $selectedUserId ?: $reportData['user_id'];

    if ($userIdToQuery) {
        $stmt_user = $link->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $stmt_user->bind_param("i", $userIdToQuery);
        $stmt_user->execute();
        $userResult = $stmt_user->get_result();
        if ($user = $userResult->fetch_assoc()) {
            $preparerName = trim($user['first_name'] . ' ' . $user['last_name']);
        }
    }
    
    // --- 3. Fetch Image from Wasabi and Base64 Encode ---
    $imageDataUri = null;
    if (!empty($reportData['report_image_key'])) {
        try {
            $wasabiConfig = include(dirname(__DIR__) . '/config/wasabi_config.php');
            $s3Client = new S3Client([
                'version'     => 'latest',
                'region'      => $wasabiConfig['region'],
                'endpoint'    => $wasabiConfig['endpoint'],
                'credentials' => ['key' => $wasabiConfig['key'], 'secret' => $wasabiConfig['secret']]
            ]);
            $result = $s3Client->getObject([
                'Bucket' => $wasabiConfig['bucket'],
                'Key'    => $reportData['report_image_key'],
            ]);
            $imageDataUri = 'data:' . $result['ContentType'] . ';base64,' . base64_encode($result['Body']->getContents());
        } catch (Exception $e) {
            error_log("Could not fetch image from Wasabi: " . $e->getMessage());
            // Fail gracefully, the PDF will just show a "not found" message.
        }
    }
    
    // --- 4. Build HTML for the PDF ---
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>F-Number Report: <?php echo htmlspecialchars($reportData['report_name']); ?></title>
        <style>
            @page { margin: 100px 50px; }
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 12px; color: #333; }
            header { position: fixed; top: -80px; left: 0; right: 0; height: 60px; text-align: center; border-bottom: 1px solid #ddd; }
            footer { position: fixed; bottom: -60px; left: 0; right: 0; height: 40px; text-align: center; font-size: 10px; color: #777; }
            h1, h2, h3, h4, h5 { margin: 15px 0 5px 0; color: #005f9e; }
            h1 { font-size: 24px; } h2 { font-size: 20px; } h3 { font-size: 16px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .report-image { max-width: 100%; height: auto; margin-top: 10px; border: 1px solid #ddd; }
            .text-center { text-align: center; }
        </style>
    </head>
    <body>
        <header>
            <h1>F-Number Analysis Report</h1>
            <p><strong>Project:</strong> <?php echo htmlspecialchars($reportData['job_name']); ?></p>
        </header>
        <footer>
            Report generated on <?php echo date("Y-m-d H:i:s"); ?> by PourDay App.
        </footer>
        <main>
            <h3><?php echo htmlspecialchars($reportData['report_name']); ?></h3>
            <p><strong>Prepared By:</strong> <?php echo htmlspecialchars($preparerName); ?></p>
            
            <h4>Specifications</h4>
            <table>
                <tr>
                    <th>Overall FF Spec</th><td><?php echo htmlspecialchars($reportData['spec_overall_ff']); ?></td>
                    <th>Overall FL Spec</th><td><?php echo htmlspecialchars($reportData['spec_overall_fl']); ?></td>
                </tr>
                <tr>
                    <th>Minimum Local FF Spec</th><td><?php echo htmlspecialchars($reportData['spec_min_local_ff']); ?></td>
                    <th>Minimum Local FL Spec</th><td><?php echo htmlspecialchars($reportData['spec_min_local_fl']); ?></td>
                </tr>
                 <tr>
                    <th>Surface Area</th><td><?php echo htmlspecialchars($reportData['surface_area']); ?></td>
                    <th>Readings Required / Taken</th><td><?php echo htmlspecialchars($reportData['min_readings_required']); ?> / <?php echo htmlspecialchars($reportData['total_readings_taken']); ?></td>
                </tr>
            </table>

            <h4>Composite F-Numbers</h4>
            <table>
                <thead><tr><th>Metric</th><th>Overall</th><th>90% Conf.</th><th>SOV P/F</th><th>Min</th><th>90% Conf.</th><th>MLV P/F</th></tr></thead>
                <tbody>
                    <?php foreach ($compositeFNumbers as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['metric']); ?></td><td><?php echo htmlspecialchars($row['overall_value']); ?></td><td><?php echo htmlspecialchars($row['conf_interval_90']); ?></td><td><?php echo htmlspecialchars($row['sov_pass_fail']); ?></td>
                        <td><?php echo htmlspecialchars($row['min_value']); ?></td><td><?php echo htmlspecialchars($row['min_conf_interval_90']); ?></td><td><?php echo htmlspecialchars($row['mlv_pass_fail']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($imageDataUri): ?>
                <h4>Testing Map</h4>
                <div class="text-center"><img src="<?php echo $imageDataUri; ?>" class="report-image"></div>
            <?php endif; ?>

            <?php
            $samplesGrouped = [];
            foreach ($sampleFNumbers as $row) { $samplesGrouped[$row['sample_name']][] = $row; }
            if (!empty($samplesGrouped)):
            ?>
                <h4>Sample F-Numbers</h4>
                <?php foreach ($samplesGrouped as $name => $samples): ?>
                    <h5><?php echo htmlspecialchars($name); ?></h5>
                    <table>
                        <thead><tr><th>Metric</th><th>Overall Value</th><th>90% Conf.</th><th>MLV P/F</th></tr></thead>
                        <tbody>
                            <?php foreach ($samples as $row): ?>
                            <tr><td><?php echo htmlspecialchars($row['metric']); ?></td><td><?php echo htmlspecialchars($row['overall_value']); ?></td><td><?php echo htmlspecialchars($row['conf_interval_90']); ?></td><td><?php echo htmlspecialchars($row['mlv_pass_fail']); ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    // --- 5. Render, Upload, Update, and Serve PDF ---
    $options = new Options();
    $options->set('isRemoteEnabled', true); // Important for loading images
    $options->set('chroot', dirname(__DIR__)); // Set chroot to project root for security
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('Letter', 'portrait');
    $dompdf->render();
    $pdfOutput = $dompdf->output();

    // The rest of this block can be uncommented when you want to save the PDF to Wasabi

    $pdfFilename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $reportData['report_name']) . '_' . date('Ymd') . '.pdf';
    $pdfObjectKey = "reports/{$reportData['project_id']}/{$pdfFilename}";

    if (isset($s3Client)) {
        $s3Client->putObject([
            'Bucket'      => $wasabiConfig['bucket'],
            'Key'         => $pdfObjectKey,
            'Body'        => $pdfOutput,
            'ContentType' => 'application/pdf',
        ]);

        // Update the DB with the path to the generated PDF
        $stmt_update = $link->prepare("UPDATE uploaded_freports SET generated_pdf_key = ? WHERE id = ?");
        $stmt_update->bind_param("si", $pdfObjectKey, $reportId);
        $stmt_update->execute();
    }

    
    // --- 6. Serve the file to the browser ---
    $pdfFilename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $reportData['report_name']) . '.pdf';
    header_remove(); // Clear any previous headers
    header('Access-Control-Expose-Headers: Content-Disposition');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $pdfFilename . '"');
    header('Content-Length: ' . strlen($pdfOutput));

    echo $pdfOutput;
    exit;

} catch (Exception $e) {
    // Return a JSON error if anything fails
    header_remove();
    header('Content-Type: application/json');
    http_response_code(500);
    error_log("PDF Generation Error for Report ID $reportId: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred during PDF generation: ' . $e->getMessage()]);
    exit;
}