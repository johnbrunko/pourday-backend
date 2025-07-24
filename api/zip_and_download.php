<?php
// api/zip_and_download.php

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// --- INCREASE SERVER RESOURCES FOR THIS HEAVY TASK ---
@ini_set('max_execution_time', 300); 
@ini_set('memory_limit', '512M');

session_start();
require_once dirname(__DIR__) . '/vendor/autoload.php';
$wasabiConfig = include(dirname(__DIR__) . '/config/wasabi_config.php');

// Security check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(403);
    echo "Error: Unauthorized access.";
    exit;
}

// Get the folder prefix from the URL
$prefix = filter_input(INPUT_GET, 'prefix', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if (empty($prefix)) {
    http_response_code(400);
    echo "Error: Folder path is missing.";
    exit;
}

// Validate Wasabi config
if (empty($wasabiConfig) || !is_array($wasabiConfig)) {
    http_response_code(500);
    error_log("Zip & Download Error: Wasabi configuration is missing or invalid.");
    echo "Server configuration error. Please contact an administrator.";
    exit;
}

$tempZipFile = null; // Initialize variable for cleanup
$tempLocalFile = null; // Initialize for robust cleanup in catch block

try {
    // Initialize Wasabi S3 Client
    $endpoint = $wasabiConfig['endpoint'];
    if (strpos($endpoint, 'http') !== 0) $endpoint = 'https://' . $endpoint;

    $s3Client = new S3Client([
        'version'     => 'latest',
        'region'      => $wasabiConfig['region'],
        'endpoint'    => $endpoint,
        'credentials' => [
            'key'    => $wasabiConfig['key'],
            'secret' => $wasabiConfig['secret'],
        ]
    ]);
    
    $normalizedPrefix = rtrim($prefix, '/') . '/';

    // List all objects in the specified folder
    $objects = $s3Client->listObjectsV2([
        'Bucket' => $wasabiConfig['bucket'],
        'Prefix' => $normalizedPrefix
    ]);

    if (!isset($objects['Contents']) || empty($objects['Contents'])) {
        http_response_code(404);
        echo "Error: No files found in the specified folder.";
        exit;
    }

    $folderBaseName = basename(rtrim($prefix, '/'));
    $safeZipFileName = preg_replace('/[^a-zA-Z0-9\._-]/', '_', $folderBaseName) . '.zip';
    $tempZipFile = tempnam(sys_get_temp_dir(), 'zip');
    
    $zip = new ZipArchive();
    if ($zip->open($tempZipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        throw new Exception("Cannot create zip archive.");
    }

    $filesAddedCount = 0;

    foreach ($objects['Contents'] as $object) {
        if (substr($object['Key'], -1) === '/') continue;

        $tempLocalFile = tempnam(sys_get_temp_dir(), 's3');

        $s3Client->getObject([
            'Bucket' => $wasabiConfig['bucket'],
            'Key'    => $object['Key'],
            'SaveAs' => $tempLocalFile 
        ]);

        // *** FIX: Read content from the temp file first, then add from string ***
        $fileContent = file_get_contents($tempLocalFile);
        
        if ($fileContent === false) {
            error_log("Zip Error: Failed to read content from temp file '{$tempLocalFile}' for S3 key '{$object['Key']}'.");
            unlink($tempLocalFile); // Clean up
            $tempLocalFile = null;
            continue; // Skip this file
        }

        $relativePath = substr($object['Key'], strlen($normalizedPrefix));
        
        if ($relativePath) {
            // Use addFromString, which is more reliable than addFile with temp files.
            if ($zip->addFromString($relativePath, $fileContent)) {
                $filesAddedCount++;
            } else {
                error_log("Zip Error: Failed to add '{$relativePath}' to the archive using addFromString.");
            }
        }
        
        unlink($tempLocalFile);
        $tempLocalFile = null;
    }

    if ($filesAddedCount === 0) {
        $zip->close();
        unlink($tempZipFile);
        throw new Exception("No files were successfully added to the zip archive. Please check logs for 'addFromString' errors.");
    }

    $zip->close();

    // Send the zip file to the browser
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $safeZipFileName . '"');
    header('Content-Length: ' . filesize($tempZipFile));
    header('Connection: close');

    if (ob_get_level()) ob_end_clean();

    readfile($tempZipFile);

    unlink($tempZipFile);
    exit;

} catch (Exception $e) {
    error_log("Zip & Download Error: " . $e->getMessage());
    http_response_code(500);
    // Clean up temp files on error if they exist
    if (isset($tempZipFile) && file_exists($tempZipFile)) unlink($tempZipFile);
    if (isset($tempLocalFile) && file_exists($tempLocalFile)) unlink($tempLocalFile);
    echo "An error occurred while creating the download. Please check server logs.";
}