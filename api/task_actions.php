<?php
session_start();
require_once '../config/db_connect.php'; // Adjust path if necessary

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid request.'];

// Handle GET requests (fetching data)
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET['request_type'])) {
    $request_type = $_GET['request_type'];
    $company_id = $_SESSION["company_id"] ?? null;

    if (empty($company_id)) {
        echo json_encode(['success' => false, 'message' => 'Company ID not found in session.']);
        exit;
    }

    if ($request_type === 'get_open_tasks') {
        $sql = "SELECT t.id, t.title, p.job_name AS project_name, t.upload_code, t.scheduled,
                         CONCAT(u.first_name, ' ', u.last_name) AS assigned_to_user_name,
                         t.pour, t.bent_plate, t.pre_camber, t.post_camber, t.fffl,
                         t.moisture, t.cut_fill, t.other
                 FROM tasks t
                 LEFT JOIN projects p ON t.project_id = p.id
                 LEFT JOIN users u ON t.assigned_to_user_id = u.id
                 WHERE t.company_id = ? AND t.completed_at IS NULL
                 ORDER BY t.scheduled ASC, t.created_at DESC";

        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $company_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            $data = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $task_types_display = [];
                if ($row['pour']) $task_types_display[] = 'Pour';
                if ($row['bent_plate']) $task_types_display[] = 'Bent Plate';
                if ($row['pre_camber']) $task_types_display[] = 'Pre-Camber';
                if ($row['post_camber']) $task_types_display[] = 'Post-Camber';
                if ($row['fffl']) $task_types_display[] = 'FF/FL';
                if ($row['moisture']) $task_types_display[] = 'Moisture';
                if ($row['cut_fill']) $task_types_display[] = 'Cut/Fill';
                if ($row['other']) $task_types_display[] = 'Other';
                $task_types_display_str = implode(', ', $task_types_display);

                $actions_html = '<button class="btn btn-sm btn-primary me-2 edit-task-btn" data-id="' . $row['id'] . '">Edit</button>';
                $actions_html .= '<button class="btn btn-sm btn-danger delete-task-btn" data-id="' . $row['id'] . '">Delete</button>';
                // Now, prepend the new Email button to the beginning of the actions_html string
                $actions_html = '<button class="btn btn-sm btn-success me-2 email-task-btn" data-id="' . $row['id'] . '">Email</button> ' . $actions_html;

                $data[] = [
                    $row['id'],
                    $row['title'],
                    $row['project_name'],
                    $row['upload_code'],
                    $row['scheduled'],
                    $row['assigned_to_user_name'],
                    $task_types_display_str,
                    $actions_html
                ];
            }
            $response = ['success' => true, 'data' => $data];
            mysqli_stmt_close($stmt);
        } else {
            $response = ['success' => false, 'message' => 'Database prepare failed for get_open_tasks: ' . mysqli_error($link)];
        }
    }
    elseif ($request_type === 'get_completed_tasks') {
        $sql = "SELECT t.id, t.title, p.job_name AS project_name, t.sq_ft, t.billable,
                         CONCAT(u.first_name, ' ', u.last_name) AS assigned_to_user_name,
                         t.pour, t.bent_plate, t.pre_camber, t.post_camber, t.fffl,
                         t.moisture, t.cut_fill, t.other, t.completed_at
                 FROM tasks t
                 LEFT JOIN projects p ON t.project_id = p.id
                 LEFT JOIN users u ON t.assigned_to_user_id = u.id
                 WHERE t.company_id = ? AND t.completed_at IS NOT NULL
                 ORDER BY t.completed_at DESC";

        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $company_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            $data = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $task_types_display = [];
                if ($row['pour']) $task_types_display[] = 'Pour';
                if ($row['bent_plate']) $task_types_display[] = 'Bent Plate';
                if ($row['pre_camber']) $task_types_display[] = 'Pre-Camber';
                if ($row['post_camber']) $task_types_display[] = 'Post-Camber';
                if ($row['fffl']) $task_types_display[] = 'FF/FL';
                if ($row['moisture']) $task_types_display[] = 'Moisture';
                if ($row['cut_fill']) $task_types_display[] = 'Cut/Fill';
                if ($row['other']) $task_types_display[] = 'Other';
                $task_types_display_str = implode(', ', $task_types_display);

                $actions_html = '<button class="btn btn-sm btn-primary me-2 edit-task-btn" data-id="' . $row['id'] . '">Edit</button>';
                $actions_html .= '<button class="btn btn-sm btn-danger delete-task-btn" data-id="' . $row['id'] . '">Delete</button>';

                $data[] = [
                    $row['id'],
                    $row['title'],
                    $row['project_name'],
                    $row['sq_ft'],
                    $row['billable'],
                    $row['completed_at'],
                    $row['assigned_to_user_name'],
                    $task_types_display_str,
                    $actions_html
                ];
            }
            $response = ['success' => true, 'data' => $data];
            mysqli_stmt_close($stmt);
        } else {
            $response = ['success' => false, 'message' => 'Database prepare failed for get_completed_tasks: ' . mysqli_error($link)];
        }
    }
    elseif ($request_type === 'get_task_details') {
        $task_id = $_GET['id'] ?? null;
        if (empty($task_id)) {
            $response = ['success' => false, 'message' => 'Task ID is required.'];
        } else {
            $sql = "SELECT t.*, p.job_name AS project_name, p.street_1, p.street_2, p.city, p.state, p.zip, c.customer_name AS customer_name
                    FROM tasks t
                    JOIN projects p ON t.project_id = p.id
                    JOIN customers c ON t.customer_id = c.id
                    WHERE t.id = ? AND t.company_id = ?";

            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ii", $task_id, $company_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                if ($task = mysqli_fetch_assoc($result)) {
                    $address_parts = [];
                    if (!empty($task['street_1'])) $address_parts[] = $task['street_1'];
                    if (!empty($task['street_2'])) $address_parts[] = $task['street_2'];
                    if (!empty($task['city'])) $address_parts[] = $task['city'];
                    if (!empty($task['state'])) $address_parts[] = $task['state'];
                    if (!empty($task['zip'])) $address_parts[] = $task['zip'];
                    $task['project_full_address'] = implode(', ', $address_parts);

                    $response = ['success' => true, 'data' => $task];
                } else {
                        $response = ['success' => false, 'message' => 'Task not found or not accessible.'];
                }
                mysqli_stmt_close($stmt);
            } else {
                $response = ['success' => false, 'message' => 'Database prepare failed for get_task_details: ' . mysqli_error($link)];
            }
        }
    }
    elseif ($request_type === 'send_task_email') {
        $task_id = $_GET['id'] ?? null;
        $email_type = $_GET['email_type'] ?? 'assigned'; // Default to 'assigned' if not specified

        if (empty($task_id)) {
            $response = ['success' => false, 'message' => 'Task ID is required to send email.'];
        } else {
            // Fetch task details AND the assigned user's email, PLUS project zip for weather
            $sql = "SELECT t.*, p.job_name AS project_name, p.zip,
                           CONCAT(u.first_name, ' ', u.last_name) AS assigned_to_user_name, u.email AS assigned_to_user_email
                    FROM tasks t
                    LEFT JOIN projects p ON t.project_id = p.id
                    LEFT JOIN users u ON t.assigned_to_user_id = u.id
                    WHERE t.id = ? AND t.company_id = ?";

            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ii", $task_id, $company_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                if ($taskDetails = mysqli_fetch_assoc($result)) {
                    // --- START: Weather Forecast Logic ---
                    $weatherData = null; // Initialize weather data
                    $scheduledDate = $taskDetails['scheduled'];

                    // Only attempt to fetch weather if we have a scheduled date and zip code
                    if (!empty($scheduledDate) && !empty($taskDetails['zip'])) { // Condition simplified to only check for zip
                        // Construct the location string for Visual Crossing API using only the zip code
                        $location = urlencode($taskDetails['zip']); // Simplified to use only zip code
                        
                        // Format the scheduled date for the API call (YYYY-MM-DD)
                        $forecastDate = (new DateTime($scheduledDate))->format('Y-m-d'); 

                        // Your API Key from earlier
                        $apiKey = '823BMNTFTHTER5VV3FSE5MWT4'; 
                        
                        // Construct the Visual Crossing API URL
                        $weatherApiUrl = "https://weather.visualcrossing.com/VisualCrossingWebServices/rest/services/timeline/{$location}/{$forecastDate}?unitGroup=us&include=days&key={$apiKey}&contentType=json";

                        // Make the API call
                        // Use @ to suppress warnings, and check for FALSE return
                        $weatherJson = @file_get_contents($weatherApiUrl); 

                        if ($weatherJson === FALSE) {
                            error_log("Visual Crossing API call failed for task ID " . $task_id . ". URL: " . $weatherApiUrl);
                            $taskDetails['weather_error'] = "Could not fetch weather forecast for the scheduled date (connection error).";
                        } else {
                            $weatherResponse = json_decode($weatherJson, true);

                            // Check if JSON decoding was successful and 'days' data is present
                            if (json_last_error() === JSON_ERROR_NONE && isset($weatherResponse['days'][0])) {
                                $dailyForecast = $weatherResponse['days'][0];
                                $forecastHtml = "<p><strong>Weather Forecast (" . (new DateTime($taskDetails['scheduled']))->format('F j, Y') . "):</strong><br>";
                                $forecastHtml .= "High: {$dailyForecast['tempmax']}°F, Low: {$dailyForecast['tempmin']}°F<br>";
                                $forecastHtml .= "Conditions: " . htmlspecialchars($dailyForecast['conditions']);
                                if ($dailyForecast['precipprob'] > 0) {
                                    $forecastHtml .= " (Precipitation Chance: {$dailyForecast['precipprob']}%)";
                                }
                                $forecastHtml .= "</p>";
                                $taskDetails['weather_forecast_html'] = $forecastHtml;
                            } else {
                                error_log("Visual Crossing API returned invalid JSON or missing 'days' data for task ID " . $task_id . ". Response: " . $weatherJson);
                                $taskDetails['weather_error'] = "Weather forecast data malformed or missing.";
                            }
                        }
                    } else {
                        $taskDetails['weather_error'] = "Insufficient information (scheduled date or project zip) to fetch weather forecast.";
                    }

                    if (isset($taskDetails['weather_error'])) {
                        // If there was an error fetching weather, include it in the HTML for the email
                        $taskDetails['weather_forecast_html'] = "<p style='color: #dc3545;'><em>Weather forecast unavailable: " . htmlspecialchars($taskDetails['weather_error']) . "</em></p>";
                        error_log("Weather forecast not included in email for Task ID {$taskDetails['id']} due to error: {$taskDetails['weather_error']}");
                    } else if (!isset($taskDetails['weather_forecast_html'])) {
                        // Ensure it's set even if no weather was fetched AND no error occurred (e.g., no zip)
                        $taskDetails['weather_forecast_html'] = "";
                    }
                    // --- END: Weather Forecast Logic ---


                    // Ensure assigned user has an email before sending
                    if (!empty($taskDetails['assigned_to_user_email'])) {
                        // Include the task_notification.php file here
                        require_once __DIR__ . '/../includes/task_notification.php';

                        // Call the email sending function
                        // Pass $taskDetails which now includes 'weather_forecast_html'
                        $emailResult = sendTaskNotificationEmail($taskDetails, $taskDetails['assigned_to_user_email'], $email_type);
                        $response = $emailResult; // Use the response from the email function
                    } else {
                        $response = ['success' => false, 'message' => 'Assigned user does not have an email address recorded.'];
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Task not found or not accessible.'];
                }
                mysqli_stmt_close($stmt);
            } else {
                $response = ['success' => false, 'message' => 'Database prepare failed for send_task_email: ' . mysqli_error($link)];
            }
        }
    }
}
elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['request_type'])) {
    $request_type = $_POST['request_type'];
    $company_id = $_SESSION["company_id"] ?? null;
    $editor_user_id = $_SESSION["id"] ?? null; // Changed to use $_SESSION["id"] for consistency

    if (empty($company_id)) {
        echo json_encode(['success' => false, 'message' => 'Company ID not found in session.']);
        exit;
    }
    if (empty($editor_user_id)) {
        echo json_encode(['success' => false, 'message' => 'Editor user ID not found in session.']);
        exit;
    }

    if ($request_type === 'update_task') {
        $task_id = $_POST['id'] ?? null;
        $title = trim($_POST['title'] ?? '');
        $project_id = (int)($_POST['project_id'] ?? 0);
        $sq_ft = trim($_POST['sq_ft'] ?? '') === '' ? null : (float)$_POST['sq_ft'];
        $notes = trim($_POST['notes'] ?? '');
        $scheduled = empty($_POST['scheduled']) ? null : trim($_POST['scheduled']);
        $billable = isset($_POST['billable']) ? 1 : 0;
        $assigned_to_user_id = empty($_POST['assigned_to_user_id']) ? null : (int)$_POST['assigned_to_user_id'];

        $is_completed_flag = $_POST['completed_at'] ?? '0';

        $task_type_values = [];
        $all_task_type_keys = ['pour', 'bent_plate', 'pre_camber', 'post_camber', 'fffl', 'moisture', 'cut_fill', 'other'];
        foreach ($all_task_type_keys as $key) {
            $task_type_values[$key] = (isset($_POST['task_types']) && in_array($key, $_POST['task_types'])) ? 1 : 0;
        }

        if (empty($task_id) || empty($title) || empty($project_id)) {
            $response = ['success' => false, 'message' => 'Invalid task ID, title, or project selection.'];
            echo json_encode($response);
            exit;
        }

        $set_completed_at_sql = "";
        if ($is_completed_flag === '1') {
            $set_completed_at_sql = ", completed_at = NOW()";
        } else {
            $set_completed_at_sql = ", completed_at = NULL";
        }

        $sql = "UPDATE tasks SET
                    title = ?,
                    project_id = ?,
                    sq_ft = ?,
                    notes = ?,
                    scheduled = ?,
                    billable = ?,
                    assigned_to_user_id = ?,
                    pour = ?, bent_plate = ?, pre_camber = ?, post_camber = ?,
                    fffl = ?, moisture = ?, cut_fill = ?, other = ?
                    " . $set_completed_at_sql . "
                WHERE id = ? AND company_id = ?";

        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "sidssiiiiiiiiiiii",
                $title,
                $project_id,
                $sq_ft,
                $notes,
                $scheduled,
                $billable,
                $assigned_to_user_id,
                $task_type_values['pour'],
                $task_type_values['bent_plate'],
                $task_type_values['pre_camber'],
                $task_type_values['post_camber'],
                $task_type_values['fffl'],
                $task_type_values['moisture'],
                $task_type_values['cut_fill'],
                $task_type_values['other'],
                $task_id,
                $company_id
            );

            if (mysqli_stmt_execute($stmt)) {
                $response = ['success' => true, 'message' => 'Task updated successfully.'];
                // Email notification logic would go here in the current version.

            } else {
                $response = ['success' => false, 'message' => 'Error updating task: ' . mysqli_error($link)];
            }
            mysqli_stmt_close($stmt);
        } else {
            $response = ['success' => false, 'message' => 'Database prepare failed for update_task: ' . mysqli_error($link)];
        }
    } elseif ($request_type === 'delete_task') {
        $task_id = $_POST['id'] ?? null;
        if (empty($task_id)) {
            $response = ['success' => false, 'message' => 'Task ID is required for deletion.'];
        } else {
            $sql = "DELETE FROM tasks WHERE id = ? AND company_id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ii", $task_id, $company_id);
                if (mysqli_stmt_execute($stmt)) {
                    if (mysqli_stmt_affected_rows($stmt) > 0) {
                        $response = ['success' => true, 'message' => 'Task deleted successfully.'];
                    } else {
                        $response = ['success' => false, 'message' => 'Task not found or not authorized for deletion.'];
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Error deleting task: ' . mysqli_error($link)];
                }
                mysqli_stmt_close($stmt);
            } else {
                $response = ['success' => false, 'message' => 'Database prepare failed for delete_task: ' . mysqli_error($link)];
            }
        }
    }
}

echo json_encode($response);
mysqli_close($link);
?>