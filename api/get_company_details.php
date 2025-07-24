<?php
/**
 * get_company_details.php
 * * This script fetches all details for a single company based on its ID.
 * * It is used to populate the 'Edit Company' modal on the Super Admin Dashboard.
 */

// Set header to return JSON content
header('Content-Type: application/json');

// Start session to verify user authentication
session_start();

// Include the database connection file
require_once '../config/db_connect.php';

// --- Security Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role_id"] != 1) {
    // Return an error response if the user is not a logged-in Super Admin
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// --- Input Validation ---
// Check if the company ID is provided and is a valid integer
if (!isset($_POST['id']) || !filter_var($_POST['id'], FILTER_VALIDATE_INT)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Invalid or missing company ID.']);
    exit;
}

$company_id = intval($_POST['id']);

// --- Fetch Company Data ---
// Prepare a SELECT statement to get all relevant company details
$sql = "
    SELECT 
        id, 
        company_name, 
        contact_person_name, 
        contact_email, 
        contact_phone,
        address,
        city,
        state,
        zip_code,
        country,
        is_active
    FROM companies 
    WHERE id = ?
";

if ($stmt = mysqli_prepare($link, $sql)) {
    // Bind the company ID to the prepared statement
    mysqli_stmt_bind_param($stmt, "i", $company_id);

    // Execute the statement
    if (mysqli_stmt_execute($stmt)) {
        // Get the result
        $result = mysqli_stmt_get_result($stmt);
        
        // Fetch the data as an associative array
        if ($company = mysqli_fetch_assoc($result)) {
            // If data is found, return it as a successful JSON response
            echo json_encode(['success' => true, 'data' => $company]);
        } else {
            // If no company is found with that ID, return an error
            http_response_code(404); // Not Found
            echo json_encode(['success' => false, 'message' => 'Company not found.']);
        }
    } else {
        // Handle execution errors
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => 'Failed to execute database query.']);
    }

    // Close the statement
    mysqli_stmt_close($stmt);
} else {
    // Handle statement preparation errors
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Failed to prepare database statement.']);
}

// Close the database connection
mysqli_close($link);
?>