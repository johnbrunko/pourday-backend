<?php
// includes/task_notification.php

// Load PHPMailer classes
// You'll need to ensure PHPMailer is installed and accessible.
// If you installed via Composer, it would be 'vendor/autoload.php'.
// If manually, adjust paths to PHPMailerAutoload.php or individual classes.
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure this path is correct for your PHPMailer installation
// This path assumes your 'vendor' folder is directly in your app root,
// and 'includes' is also directly in your app root.
require_once __DIR__ . '/../vendor/autoload.php';

// Load email configuration
require_once __DIR__ . '/../config/email_config.php';

/**
 * Sends an email notification for a task.
 *
 * @param array $taskDetails An associative array containing task, project, and assigned user details.
 * Expected keys: id, title, project_name, scheduled, assigned_to_user_name,
 * assigned_to_user_email (MUST be present for recipient). Also expects pour, bent_plate, etc., sq_ft, notes, and upload_code.
 * It now also expects 'weather_forecast_html' if weather data was fetched successfully in the API.
 * @param string $recipientEmail The email address to send the notification to.
 * @param string $emailType 'assigned' or 'reminder' or 'update'
 * @return array An associative array with 'success' (bool) and 'message' (string).
 */
function sendTaskNotificationEmail(array $taskDetails, string $recipientEmail, string $emailType): array
{
    $mail = new PHPMailer(true); // Enable exceptions

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        // Recipients
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($recipientEmail); // Add recipient

        // Content
        $mail->isHTML(true); // Set email format to HTML

        $subject = "";
        $body = "";

        // Common task types string generation
        $taskTypesDisplay = [];
        if (!empty($taskDetails['pour'])) $taskTypesDisplay[] = 'Pour';
        if (!empty($taskDetails['bent_plate'])) $taskTypesDisplay[] = 'Bent Plate';
        if (!empty($taskDetails['pre_camber'])) $taskTypesDisplay[] = 'Pre-Camber';
        if (!empty($taskDetails['post_camber'])) $taskTypesDisplay[] = 'Post-Camber';
        if (!empty($taskDetails['fffl'])) $taskTypesDisplay[] = 'FF/FL';
        if (!empty($taskDetails['moisture'])) $taskTypesDisplay[] = 'Moisture';
        if (!empty($taskDetails['cut_fill'])) $taskTypesDisplay[] = 'Cut/Fill';
        if (!empty($taskDetails['other'])) $taskTypesDisplay[] = 'Other';
        $taskTypesHtml = '';
        if (!empty($taskTypesDisplay)) {
            $taskTypesHtml = "<p><strong>Required Tasks:</strong> " . htmlspecialchars(implode(', ', $taskTypesDisplay)) . "</p>";
        }

        // Common square footage string generation
        $sqFtHtml = '';
        if (!empty($taskDetails['sq_ft'])) {
            $sqFtHtml = "<p><strong>Square Footage:</strong> " . htmlspecialchars($taskDetails['sq_ft']) . "</p>";
        }

        // Common notes string generation
        $notesHtml = '';
        if (!empty($taskDetails['notes'])) {
            $notesHtml = "<p><strong>Notes:</strong> " . nl2br(htmlspecialchars($taskDetails['notes'])) . "</p>";
        }

        // Common upload code and link generation
        $uploadCodeHtml = '';
        if (!empty($taskDetails['upload_code'])) {
            // Using the base URL provided earlier
            $uploadLink = "https://pourday.tech/public_upload.php?code=" . htmlspecialchars($taskDetails['upload_code']);
            $uploadCodeHtml = "<p><strong>Upload Code:</strong> " . htmlspecialchars($taskDetails['upload_code']) . " <a href=\"" . $uploadLink . "\">" . htmlspecialchars($uploadLink) . "</a></p>";
        }

        // Weather forecast HTML (passed from API response in $taskDetails)
        $weatherForecastHtml = $taskDetails['weather_forecast_html'] ?? ''; // This should be populated by api/task_actions.php


        // Prepare email content based on type
        switch ($emailType) {
            case 'assigned':
                $scheduledDateForSubject = '';
                if (!empty($taskDetails['scheduled'])) {
                    $scheduledDateTime = new DateTime($taskDetails['scheduled']);
                    $scheduledDateForSubject = ' on ' . $scheduledDateTime->format('F j, Y'); // Formats as "July 22, 2025"
                }
                $subject = "You have been assigned " . htmlspecialchars($taskDetails['title']) . " for " . htmlspecialchars($taskDetails['project_name']) . $scheduledDateForSubject;
                $body = "<h2>New Task Assignment</h2>";
                $body .= "<p>Dear " . htmlspecialchars($taskDetails['assigned_to_user_name']) . ",</p>";
                $body .= "<p>You have been assigned a new task:</p>";
                $body .= "<p><strong>Task:</strong> " . htmlspecialchars($taskDetails['title']) . "</p>";
                $body .= "<p><strong>Project:</strong> " . htmlspecialchars($taskDetails['project_name']) . "</p>";
                if (!empty($taskDetails['scheduled'])) {
                    $scheduledDate = (new DateTime($taskDetails['scheduled']))->format('F j, Y');
                    $body .= "<p><strong>Scheduled Date:</strong> " . $scheduledDate . "</p>";
                }
                $body .= $weatherForecastHtml; // Add weather forecast after scheduled date
                $body .= $taskTypesHtml; // Add required tasks
                $body .= $sqFtHtml;      // Add square footage
                $body .= $uploadCodeHtml; // Add upload code and link
                $body .= $notesHtml;     // Add notes
                $body .= "<p>Please log in to the PourDay App for more details.</p>";
                break;

            case 'reminder':
                $subject = "Task Reminder: {$taskDetails['title']} for Project {$taskDetails['project_name']}";
                $body = "<h2>Task Reminder</h2>";
                $body .= "<p>Dear " . htmlspecialchars($taskDetails['assigned_to_user_name']) . ",</p>";
                $body .= "<p>This is a reminder for your upcoming task:</p>";
                $body .= "<p><strong>Task:</strong> " . htmlspecialchars($taskDetails['title']) . "</p>";
                $body .= "<p><strong>Project:</strong> " . htmlspecialchars($taskDetails['project_name']) . "</p>";
                if (!empty($taskDetails['scheduled'])) {
                    $scheduledDate = (new DateTime($taskDetails['scheduled']))->format('F j, Y');
                    $body .= "<p><strong>Scheduled Date:</strong> " . $scheduledDate . "</p>";
                }
                $body .= $weatherForecastHtml; // Add weather forecast after scheduled date
                $body .= $taskTypesHtml; // Add required tasks
                $body .= $sqFtHtml;      // Add square footage
                $body .= $uploadCodeHtml; // Add upload code and link
                $body .= $notesHtml;     // Add notes
                $body .= "<p>Please ensure you are prepared for this task. Log in to the PourDay App for more details.</p>";
                break;

            case 'update':
                $subject = "Task Update: {$taskDetails['title']} for Project {$taskDetails['project_name']}";
                $body = "<h2>Task Update</h2>";
                $body .= "<p>Dear " . htmlspecialchars($taskDetails['assigned_to_user_name']) . ",</p>";
                $body .= "<p>A task you are assigned to has been updated:</p>";
                $body .= "<p><strong>Task:</strong> " . htmlspecialchars($taskDetails['title']) . "</p>";
                $body .= "<p><strong>Project:</strong> " . htmlspecialchars($taskDetails['project_name']) . "</p>";
                if (!empty($taskDetails['scheduled'])) {
                    $scheduledDate = (new DateTime($taskDetails['scheduled']))->format('F j, Y');
                    $body .= "<p><strong>Scheduled Date:</strong> " . $scheduledDate . "</p>";
                }
                $body .= $weatherForecastHtml; // Add weather forecast after scheduled date
                $body .= $taskTypesHtml; // Add required tasks
                $body .= $sqFtHtml;      // Add square footage
                $body .= $uploadCodeHtml; // Add upload code and link
                $body .= $notesHtml;     // Add notes
                $body .= "<p>Please review the changes in the PourDay App.</p>";
                break;

            default:
                return ['success' => false, 'message' => 'Invalid email type specified.'];
        }

        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body); // Plain text for non-HTML clients

        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully.'];

    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo . " (Task ID: " . ($taskDetails['id'] ?? 'N/A') . ", Recipient: $recipientEmail)");
        return ['success' => false, 'message' => "Email could not be sent. Mailer Error: {$mail->ErrorInfo}"];
    }
}