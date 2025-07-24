<?php
// File: api/csv_weather_handler.php

session_start();
header('Content-Type: application/json');
ini_set('auto_detect_line_endings', TRUE);

// --- CONFIGURATION ---
$rows_to_skip = 5;
$column_map = [
    'datetime'    => 0, 'temperature' => 1, 'humidity'    => 3, 'windspeed'   => 13, 'evap_rate'   => 14
];

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/db_connect.php';

$response = ['success' => false, 'message' => 'An error occurred.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$taskId = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);
$csvFilterStartTimeStr = filter_input(INPUT_POST, 'csv_filter_start_time', FILTER_SANITIZE_STRING);
$csvFilterEndTimeStr = filter_input(INPUT_POST, 'csv_filter_end_time', FILTER_SANITIZE_STRING);

if (!$taskId || !$csvFilterStartTimeStr || !$csvFilterEndTimeStr) {
    $response['message'] = 'Required fields were not sent from the browser.';
    echo json_encode($response);
    exit;
}
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    $response['message'] = 'File upload error.';
    echo json_encode($response);
    exit;
}

function parseDateTime(string $dateTimeStr): ?DateTime {
    $normalizedStr = preg_replace('/\s+/', ' ', trim($dateTimeStr));
    // --- KEY CHANGE IS HERE ---
    // Added 'Y-m-d h:i:s A' to handle the format revealed by the debug output.
    $formats = [
        'Y-m-d h:i:s A',  // e.g., "2025-04-17 12:00:00 AM"
        'n/j/Y G:i',      // e.g., "4/17/2025 13:00"
    ];
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat('!'.$format, $normalizedStr);
        if ($date !== false) return $date;
    }
    return null;
}

$fileTmpPath = $_FILES['csv_file']['tmp_name'];
$handle = fopen($fileTmpPath, "r");
if ($handle === FALSE) {
    echo json_encode(['success' => false, 'message' => 'Could not open the uploaded CSV file.']);
    exit;
}

for ($i = 0; $i < $rows_to_skip; $i++) { fgetcsv($handle); }

if (feof($handle)) {
    echo json_encode(['success' => false, 'message' => 'CSV file appears to contain only header rows and no data.']);
    exit;
}

mysqli_begin_transaction($link);
try {
    $stmtDelete = mysqli_prepare($link, "DELETE FROM historical_weather_data WHERE task_id = ?");
    mysqli_stmt_bind_param($stmtDelete, "i", $taskId);
    mysqli_stmt_execute($stmtDelete);
    mysqli_stmt_close($stmtDelete);

    $sqlInsert = "INSERT INTO historical_weather_data (task_id, record_datetime, temperature, humidity, windspeed, conditions, evap_rate) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmtInsert = mysqli_prepare($link, $sqlInsert);

    $rowCount = 0;
    $processedData = [];

    while (($row = fgetcsv($handle)) !== FALSE) {
        if (count($row) === 1 && empty($row[0])) continue; // Skip empty lines
        if (count($row) < max($column_map) + 1) continue;

        $rawDateTime = trim($row[$column_map['datetime']] ?? '');
        if (empty($rawDateTime)) continue;

        $currentCsvDateTimeObj = parseDateTime($rawDateTime);
        if (!$currentCsvDateTimeObj) {
            error_log("Failed to parse datetime: " . $rawDateTime);
            continue;
        }

        $currentRowTimeStr = $currentCsvDateTimeObj->format('H:i:s');
        if ($currentRowTimeStr < $csvFilterStartTimeStr || $currentRowTimeStr > $csvFilterEndTimeStr) {
            continue;
        }

        $temperature = filter_var($row[$column_map['temperature']] ?? null, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
        $humidity = filter_var($row[$column_map['humidity']] ?? null, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
        $windspeed = filter_var($row[$column_map['windspeed']] ?? null, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
        $evapRate = filter_var($row[$column_map['evap_rate']] ?? null, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
        $formattedDateTime = $currentCsvDateTimeObj->format('Y-m-d H:i:s');
        $conditions = null;

        $rowData = [
            'task_id' => $taskId, 'record_datetime' => $formattedDateTime, 'temperature' => $temperature, 'humidity' => $humidity, 'windspeed' => $windspeed, 'conditions' => $conditions, 'evap_rate' => $evapRate
        ];
        
        mysqli_stmt_bind_param($stmtInsert, "isdddsd", $rowData['task_id'], $rowData['record_datetime'], $rowData['temperature'], $rowData['humidity'], $rowData['windspeed'], $rowData['conditions'], $rowData['evap_rate']);
        mysqli_stmt_execute($stmtInsert);
        
        $processedData[] = $rowData;
        $rowCount++;
    }
    
    fclose($handle);
    mysqli_stmt_close($stmtInsert);
    mysqli_commit($link);

    if ($rowCount > 0) {
        $response = [ 'success' => true, 'message' => "Successfully saved {$rowCount} weather records from CSV.", 'weatherData' => $processedData ];
    } else {
        $response = [ 'success' => true, 'message' => 'CSV processed, but no valid records were found. The file may be empty, the data format may be incorrect, or no records match the time filter.', 'weatherData' => [] ];
    }

} catch (Exception $e) {
    mysqli_rollback($link);
    $response['message'] = 'A critical error occurred: ' . $e->getMessage();
}

echo json_encode($response);
?>