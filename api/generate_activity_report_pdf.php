<?php
// Set a higher memory limit and execution time for complex PDF generation.
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);

session_start();
// --- Security and Initialization ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["role_id"], [1, 2, 3])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

require_once dirname(__DIR__) . '/config/db_connect.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Aws\S3\S3Client;
use Dompdf\Dompdf;
use Dompdf\Options;

// --- Helper Functions ---

function save_image_locally($s3Client, $bucket, $key, $tempDir) {
    if (empty($key)) return null;
    try {
        $result = $s3Client->getObject(['Bucket' => $bucket, 'Key' => $key]);
        $fileExtension = pathinfo($key, PATHINFO_EXTENSION);
        $tempFilename = uniqid('img_', true) . '.' . $fileExtension;
        $localPath = $tempDir . DIRECTORY_SEPARATOR . $tempFilename;
        file_put_contents($localPath, $result['Body']->getContents());
        return $localPath;
    } catch (Exception $e) {
        error_log("S3 GetObject/Save Error for key {$key}: " . $e->getMessage());
        return null;
    }
}

function get_chart_as_local_file($config, $tempDir) {
    $postData = json_encode(['chart' => $config]);
    $ch = curl_init('https://quickchart.io/chart');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($postData)]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $imageData = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode === 200 && $imageData) {
        $tempFilename = uniqid('chart_', true) . '.png';
        $localPath = $tempDir . DIRECTORY_SEPARATOR . $tempFilename;
        file_put_contents($localPath, $imageData);
        return $localPath;
    }
    error_log("QuickChart API failed with status code: " . $httpcode);
    return null;
}

// --- Main PDF Generation Logic ---

$tempDir = dirname(__DIR__) . '/temp/' . uniqid('report_', true);
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}

try {
    // 1. Get and Decode Input Data
    $postData = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) throw new Exception("Invalid JSON data received.");
    $taskId = filter_var($postData['task_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$taskId) throw new Exception("Task ID is missing or invalid.");

    $company_id = $_SESSION['company_id'];
    $user_id = $_SESSION['id'];

    // 2. Fetch Core Data
    $stmt_main = $link->prepare("
        SELECT 
            t.title AS task_title, t.scheduled,
            p.id AS project_id, p.job_name, p.job_number, p.city, p.state,
            cust.customer_name,
            c.first_name AS contact_first_name, c.last_name AS contact_last_name
        FROM tasks t
        JOIN projects p ON t.project_id = p.id
        JOIN customers cust ON p.customer_id = cust.id
        LEFT JOIN contacts c ON p.contact_id_1 = c.id
        WHERE t.id = ? AND t.company_id = ?
    ");
    $stmt_main->bind_param("ii", $taskId, $company_id);
    $stmt_main->execute();
    $mainDetails = $stmt_main->get_result()->fetch_assoc();
    if (!$mainDetails) throw new Exception("Task not found or permission denied.");

    $stmt_user = $link->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $preparerUser = $stmt_user->get_result()->fetch_assoc();
    $preparerName = trim(($preparerUser['first_name'] ?? '') . ' ' . ($preparerUser['last_name'] ?? ''));

    $stmt_company = $link->prepare("SELECT company_name, logo FROM companies WHERE id = ?");
    $stmt_company->bind_param("i", $company_id);
    $stmt_company->execute();
    $companyData = $stmt_company->get_result()->fetch_assoc();

    // 3. Initialize S3 Client
    $s3Client = null;
    $bucketName = '';
    $wasabiConfig = include(dirname(__DIR__) . '/config/wasabi_config.php');
    $s3Client = new S3Client(['version' => 'latest', 'region' => $wasabiConfig['region'], 'endpoint' => $wasabiConfig['endpoint'], 'credentials' => ['key' => $wasabiConfig['key'], 'secret' => $wasabiConfig['secret']], 'http' => ['verify' => dirname(__DIR__) . '/config/cacert.pem']]);
    $bucketName = $wasabiConfig['bucket'];
    
    // 4. Process Report Entries
    $reportEntries = [];
    foreach (['observations', 'concerns', 'recommendations'] as $category_plural) {
        $category_singular = rtrim($category_plural, 's');
        if (!empty($postData['notes'][$category_plural]['templates'])) {
            $qMarks = str_repeat('?,', count($postData['notes'][$category_plural]['templates']) - 1) . '?';
            $stmt = $link->prepare("SELECT text FROM field_report_templates WHERE id IN ($qMarks)");
            $stmt->bind_param(str_repeat('i', count($postData['notes'][$category_plural]['templates'])), ...$postData['notes'][$category_plural]['templates']);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) { $reportEntries[ucfirst($category_singular)][] = $row['text']; }
        }
        if (!empty(trim($postData['notes'][$category_plural]['custom']))) {
            $reportEntries[ucfirst($category_singular)][] = trim($postData['notes'][$category_plural]['custom']);
        }
    }

    // 5. Fetch FF/FL Data
    $fflReportData = null;
    $compositeFNumbers = [];
    $fflImageLocalPath = null;
    $stmt_ffl = $link->prepare("SELECT * FROM uploaded_freports WHERE task_id = ?");
    $stmt_ffl->bind_param("i", $taskId);
    $stmt_ffl->execute();
    $fflReportData = $stmt_ffl->get_result()->fetch_assoc();
    if ($fflReportData) {
        $reportId = $fflReportData['id'];
        $stmt_composite = $link->prepare("SELECT * FROM freport_composite WHERE report_id = ?");
        $stmt_composite->bind_param("i", $reportId);
        $stmt_composite->execute();
        $compositeFNumbers = $stmt_composite->get_result()->fetch_all(MYSQLI_ASSOC);
        if (!empty($fflReportData['report_image_key'])) {
            $fflImageLocalPath = save_image_locally($s3Client, $bucketName, $fflReportData['report_image_key'], $tempDir);
        }
    }

    // 6. Fetch and Prepare Photos
    $selectedPhotos = [];
    if (!empty($postData['photos'])) {
        $qMarks = str_repeat('?,', count($postData['photos']) - 1) . '?';
        $stmt_photos = $link->prepare("SELECT f.object_key, f.image_width, f.image_height, tp.comments, at.name as activity_name FROM files f JOIN task_photos tp ON f.id = tp.file_id JOIN activity_types at ON tp.activity_category_id = at.id WHERE f.id IN ($qMarks)");
        $stmt_photos->bind_param(str_repeat('i', count($postData['photos'])), ...$postData['photos']);
        $stmt_photos->execute();
        $result = $stmt_photos->get_result();
        while($row = $result->fetch_assoc()) {
            $row['local_path'] = save_image_locally($s3Client, $bucketName, $row['object_key'], $tempDir);
            $selectedPhotos[] = $row;
        }
    }

    // REMOVED: Section for fetching reference documents has been removed.

    // 8. Fetch Weather Data and Generate Charts
    $weatherCharts = [];
    $stmt_weather = $link->prepare("SELECT record_datetime, temperature, humidity, windspeed, evap_rate FROM historical_weather_data WHERE task_id = ? ORDER BY record_datetime ASC");
    $stmt_weather->bind_param("i", $taskId);
    $stmt_weather->execute();
    $weatherData = $stmt_weather->get_result()->fetch_all(MYSQLI_ASSOC);

    if (!empty($weatherData)) {
        $labels = array_map(fn($d) => (new DateTime($d['record_datetime']))->format('g:i A'), $weatherData);
        $chartConfigs = [
            'Temperature' => ['data' => array_map(fn($d) => $d['temperature'], $weatherData), 'color' => '#dc3545', 'title' => 'Temperature (°F)'],
            'Humidity' => ['data' => array_map(fn($d) => $d['humidity'], $weatherData), 'color' => '#0d6efd', 'title' => 'Humidity (%)'],
            'Wind Speed' => ['data' => array_map(fn($d) => $d['windspeed'], $weatherData), 'color' => '#198754', 'title' => 'Wind (mph)'],
            'Evaporation' => ['data' => array_map(fn($d) => $d['evap_rate'], $weatherData), 'color' => '#6f42c1', 'title' => 'Evap. Rate (lb/ft²/h)'],
        ];
        foreach($chartConfigs as $key => $c) {
            $config = [
                'type' => 'line',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => $key,
                            'data' => $c['data'],
                            'borderColor' => $c['color'],
                            'backgroundColor' => 'transparent', // Changed to transparent
                            'fill' => false, // Set to false to remove fill
                            'tension' => 0.1
                        ]
                    ]
                ],
                'options' => [
                    'title' => [
                        'display' => true,
                        'text' => $c['title']
                    ],
                    'legend' => [
                        'display' => false
                    ],
                    'devicePixelRatio' => 2,
                    'scales' => [ 
                        'y' => [
                            'beginAtZero' => false,
                            'title' => [
                                'display' => true,
                                'text' => $c['title'] 
                            ],
                            'grid' => [ // Reverted grid color/width back to default if preferred
                                'color' => 'rgba(0, 0, 0, 0.1)', // Typical Chart.js default light grey
                                'lineWidth' => 1
                            ],
                            'ticks' => [ 
                                'color' => '#666' // Typical Chart.js default tick color
                            ]
                        ],
                        'x' => [
                            'title' => [
                                'display' => true,
                                'text' => 'Time'
                            ],
                            'grid' => [ // Reverted grid color/width back to default if preferred
                                'color' => 'rgba(0, 0, 0, 0.1)', 
                                'lineWidth' => 1
                            ],
                            'ticks' => [ 
                                'color' => '#666'
                            ]
                        ]
                    ]
                ]
            ];
            $weatherCharts[$key] = get_chart_as_local_file($config, $tempDir);
        }
    }

    // 9. Assemble HTML for PDF
    $cssContent = file_get_contents(dirname(__DIR__) . '/css/pdf_report.css');
    $logoLocalPath = save_image_locally($s3Client, $bucketName, $companyData['logo'], $tempDir);
    
    ob_start();
    include(dirname(__DIR__) . '/components/report_templates/activity_report_template.phtml');
    $html = ob_get_clean();

    // 10. Render PDF
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('chroot', dirname(__DIR__));
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('Letter', 'portrait');
    $dompdf->render();
    $pdfOutput = $dompdf->output();

    $pdfFilename = 'Activity_Report_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $mainDetails['job_name']) . '_' . date('Ymd') . '.pdf';
    
    $projectId = $mainDetails['project_id'];
    $pdfObjectKey = "company_{$company_id}/project_{$projectId}/task_{$taskId}/docs/{$pdfFilename}";

    $s3Client->putObject([
        'Bucket' => $bucketName,
        'Key' => $pdfObjectKey,
        'Body' => $pdfOutput,
        'ContentType' => 'application/pdf'
    ]);

    $stmt_file = $link->prepare("
        INSERT INTO files (project_id, task_id, user_id, original_filename, unique_filename, object_key, upload_type) 
        VALUES (?, ?, ?, ?, ?, ?, 'docs')
    ");
    $stmt_file->bind_param("iiisss", $projectId, $taskId, $user_id, $pdfFilename, $pdfFilename, $pdfObjectKey);
    $stmt_file->execute();

    // 11. Stream PDF to user
    header_remove();
    header('Access-Control-Expose-Headers: Content-Disposition');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $pdfFilename . '"');
    header('Content-Length: ' . strlen($pdfOutput));
    echo $pdfOutput;
    exit;

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    error_log("Activity Report PDF Generation Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred during PDF generation: ' . $e->getMessage()]);
    exit;
} finally {
    // 12. Cleanup
    if (is_dir($tempDir)) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }
        rmdir($tempDir);
    }
}