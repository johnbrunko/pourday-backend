<?php
// Database credentials for MySQL
define('DB_SERVER', 'localhost'); // Your MySQL host (e.g., 'localhost' or '127.0.0.1')
define('DB_USERNAME', 'u615152953_developer'); // Your MySQL database username
define('DB_PASSWORD', 'e+kq#t+F3n'); // Your MySQL database password
define('DB_NAME', 'u615152953_pourday');   // The name of your database

/* Attempt to connect to MySQL database */
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($link === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}
?>