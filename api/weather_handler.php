<?php
// File: api/weather_handler.php

session_start();
header('Content-Type: application/json');

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
$zipCode = filter_input(INPUT_POST, 'zip_code', FILTER_SANITIZE_STRING);
$concreteTempF = filter_input(INPUT_POST, 'concrete_temp', FILTER_VALIDATE_FLOAT);
$startDate = filter_input(INPUT_POST, 'start_date', FILTER_SANITIZE_STRING);
$endDate = filter_input(INPUT_POST, 'end_date', FILTER_SANITIZE_STRING);

if (!$taskId || !$zipCode || !$concreteTempF || !$startDate || !$endDate) {
    $response['message'] = 'Missing required fields.';
    echo json_encode($response);
    exit;
}

/**
 * Calculates saturation vapor pressure at a given temperature.
 * The internal table is in inHg (inches of Mercury).
 * The function performs linear interpolation and converts the final result to psi.
 *
 * @param float $tempF Temperature in Fahrenheit.
 * @return float|null Saturation vapor pressure in psi, or null if out of range.
 */
function calculateSaturationVaporPressure(float $tempF): ?float {
    // This table stores vapor pressure in INCHES OF MERCURY (inHg)
    $vapor_pressure_table_inHg = [
        30 => 0.168, 40 => 0.248, 50 => 0.363, 60 => 0.522, 70 => 0.739, 80 => 1.033, 90 => 1.422, 100 => 1.933, 110 => 2.590, 120 => 3.427, 130 => 4.475, 140 => 5.767
    ];
    
    $inhg_to_psi_factor = 0.491154;

    $keys = array_keys($vapor_pressure_table_inHg);
    sort($keys);

    $roundedTemp = (int)round($tempF);
    if (isset($vapor_pressure_table_inHg[$roundedTemp])) {
        return $vapor_pressure_table_inHg[$roundedTemp] * $inhg_to_psi_factor;
    }

    $lowerTemp = null;
    $upperTemp = null;
    foreach ($keys as $key) {
        if ($key <= $tempF) {
            $lowerTemp = $key;
        } elseif ($key > $tempF) {
            $upperTemp = $key;
            break;
        }
    }

    if ($lowerTemp === null || $upperTemp === null) {
        return null; // Temperature is outside the table's range
    }

    $lowerVp = $vapor_pressure_table_inHg[$lowerTemp];
    $upperVp = $vapor_pressure_table_inHg[$upperTemp];
    $es_inHg = $lowerVp + ($tempF - $lowerTemp) * (($upperVp - $lowerVp) / ($upperTemp - $lowerTemp));
    $es_psi = $es_inHg * $inhg_to_psi_factor;

    return ($es_psi > 0 && is_finite($es_psi)) ? $es_psi : null;
}

$apiKey = '823BMNTFTHTER5VV3FSE5MWT4';
$apiUrl = "https://weather.visualcrossing.com/VisualCrossingWebServices/rest/services/timeline/{$zipCode}/{$startDate}/{$endDate}?unitGroup=us&include=hours&key={$apiKey}&contentType=json";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
// FIXED: Added the CAINFO option to specify the path to the certificate bundle.
curl_setopt($ch, CURLOPT_CAINFO, dirname(__DIR__) . '/config/cacert.pem');
$weatherJson = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch); // Get potential cURL error message
curl_close($ch);

if ($httpcode !== 200 || $weatherJson === false) {
    $response['message'] = 'Failed to connect to the Weather API. Code: ' . $httpcode . ' - Error: ' . $error;
    echo json_encode($response);
    exit;
}

$weatherData = json_decode($weatherJson, true);
if (!$weatherData || !isset($weatherData['days'])) {
    $response['message'] = 'Received invalid data from the Weather API.';
    echo json_encode($response);
    exit;
}

$startTimestamp = strtotime($startDate);
$endTimestamp = strtotime($endDate);

mysqli_begin_transaction($link);
try {
    $stmtDelete = mysqli_prepare($link, "DELETE FROM historical_weather_data WHERE task_id = ?");
    mysqli_stmt_bind_param($stmtDelete, "i", $taskId);
    mysqli_stmt_execute($stmtDelete);
    mysqli_stmt_close($stmtDelete);

    $sqlInsert = "INSERT INTO historical_weather_data (task_id, record_datetime, temperature, humidity, precipitation, windspeed, conditions, evap_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmtInsert = mysqli_prepare($link, $sqlInsert);

    $rowCount = 0;
    $savedData = [];

    $es_Tc = calculateSaturationVaporPressure($concreteTempF);

    foreach ($weatherData['days'] as $day) {
        if (!isset($day['hours'])) continue;
        foreach ($day['hours'] as $hour) {
            $recordDateTime = $day['datetime'] . ' ' . $hour['datetime'];
            $recordTimestamp = strtotime($recordDateTime);

            if ($recordTimestamp >= $startTimestamp && $recordTimestamp <= $endTimestamp) {
                $evap_rate = null;
                
                $Tc_F = $concreteTempF;
                $Ta_F = $hour['temp'] ?? null;
                $Rh_percent = $hour['humidity'] ?? null;
                $V_mph = $hour['windspeed'] ?? null;

                if (is_numeric($Tc_F) && is_numeric($Ta_F) && is_numeric($Rh_percent) && is_numeric($V_mph)) {
                    $Rh_decimal = $Rh_percent / 100;
                    $es_Ta = calculateSaturationVaporPressure($Ta_F);
                    
                    if ($es_Tc !== null && $es_Ta !== null) {
                        $ea = $Rh_decimal * $es_Ta;
                        $evap_rate = 0.44 * ($es_Tc - $ea) * (0.253 + 0.096 * $V_mph);
                        $evap_rate = max(0, $evap_rate);
                    }
                }

                $rowData = [
                    'task_id' => $taskId, 'record_datetime' => $recordDateTime, 'temperature' => $Ta_F, 'humidity' => $Rh_percent, 'precipitation' => $hour['precip'] ?? null, 'windspeed' => $V_mph, 'conditions' => $hour['conditions'] ?? null, 'evap_rate' => $evap_rate
                ];
                
                mysqli_stmt_bind_param($stmtInsert, "isddddsd", $rowData['task_id'], $rowData['record_datetime'], $rowData['temperature'], $rowData['humidity'], $rowData['precipitation'], $rowData['windspeed'], $rowData['conditions'], $rowData['evap_rate']);
                mysqli_stmt_execute($stmtInsert);
                
                $savedData[] = $rowData;
                $rowCount++;
            }
        }
    }
    mysqli_stmt_close($stmtInsert);
    mysqli_commit($link);
    $response = ['success' => true, 'message' => "Successfully saved {$rowCount} hourly weather records.", 'weatherData' => $savedData];
} catch (Exception $e) {
    mysqli_rollback($link);
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
?>