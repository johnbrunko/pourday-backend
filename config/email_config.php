<?php
// config/email_config.php

// This file stores the email configuration for PHPMailer.
// Keep this file secure and outside of the public-facing directory if possible.
// For now, we will place it in the 'config' folder.

// --- SMTP Configuration Settings ---
// You will need to get these details from your email provider (e.g., Hostinger, Google Workspace).

define('SMTP_HOST', 'smtp.hostinger.com');      // The SMTP server hostname (e.g., 'smtp.hostinger.com')
define('SMTP_USERNAME', 'aguy@pourday.tech'); // Your full email address
define('SMTP_PASSWORD', 'F700Rprep!');  // The password for your email account
define('SMTP_PORT', 465);                      // The SMTP port (usually 465 for SSL or 587 for TLS)
define('SMTP_SECURE', 'ssl');                  // The encryption protocol ('ssl' or 'tls')

// --- "From" Address Configuration ---
// This is the name and address that will appear as the sender on all outgoing emails.

define('MAIL_FROM_ADDRESS', 'aguy@pourday.tech'); // The "from" email address
define('MAIL_FROM_NAME', 'PourDay App');             // The "from" name (e.g., your application's name)

?>
