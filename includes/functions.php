<?php
// includes/functions.php

// This file will contain reusable functions for the application.

// Use the PHPMailer classes from the vendor directory
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Require Composer's autoloader to make the PHPMailer classes available
require_once dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Sends an email using the PHPMailer library and configured SMTP settings.
 *
 * @param string $to The recipient's email address.
 * @param string $subject The subject of the email.
 * @param string $htmlBody The HTML content of the email.
 * @param string $altBody (Optional) The plain text version of the email for non-HTML clients.
 * @return bool Returns true if the email was sent successfully, false otherwise.
 */
function send_email($to, $subject, $htmlBody, $altBody = '') {
    
    $mail = new PHPMailer(true); // Passing `true` enables exceptions

    try {
        // --- 1. Load Server Settings ---
        require dirname(__DIR__) . '/config/email_config.php';

        // --- 2. Configure PHPMailer with SMTP details ---
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;

        // --- 3. Set Sender and Recipient ---
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($to);

        // --- 4. Set Email Content ---
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = !empty($altBody) ? $altBody : strip_tags($htmlBody);

        // --- 5. Send the Email ---
        $mail->send();
        return true;

    } catch (Exception $e) {
        // In a production environment, you would log this error to a file instead of echoing it.
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// You can add other global functions here in the future.

?>