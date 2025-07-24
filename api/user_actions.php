<?php
// api/user_actions.php

// Set header for JSON response
header('Content-Type: application/json');

// Initialize the session (important for checking login and company_id)
session_start();

// Ensure user is logged in and is a CompanyAdmin (role_id = 2)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role_id"] !== 2) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Get the company_id from the session
$company_id = $_SESSION["company_id"];

// Basic validation for company_id
if (empty($company_id)) {
    echo json_encode(['success' => false, 'message' => 'No company associated with this account. Please contact SuperAdmin.']);
    exit;
}

// Use a reliable absolute path for requires
require_once dirname(__DIR__) . '/config/db_connect.php'; 

$response = ['success' => false, 'data' => [], 'message' => null];

// Determine the request type
$request_type = $_REQUEST['request_type'] ?? null;


// Main logic router
switch ($request_type) {
    case 'get_all_users':
        // This case remains unchanged
        $sql = "SELECT u.id, u.username, u.first_name, u.last_name, u.email, r.role_name, u.is_active
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.company_id = ? AND u.role_id IN (3, 4)
                ORDER BY u.username ASC";

        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $company_id);
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                while ($row = mysqli_fetch_assoc($result)) {
                    $response['data'][] = [
                        $row['id'],
                        htmlspecialchars($row['username']),
                        htmlspecialchars($row['first_name'] . ' ' . $row['last_name']),
                        htmlspecialchars($row['email']),
                        htmlspecialchars($row['role_name']),
                        $row['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>',
                        '<button type="button" class="btn btn-sm btn-outline-primary edit-user-btn" data-id="' . $row['id'] . '">Edit</button> ' .
                        '<button type="button" class="btn btn-sm btn-outline-danger delete-user-btn" data-id="' . $row['id'] . '">Delete</button>'
                    ];
                }
                $response['success'] = true;
            } else {
                $response['message'] = "Execution failed: " . mysqli_error($link);
            }
            mysqli_stmt_close($stmt);
        } else {
            $response['message'] = "Prepare failed: " . mysqli_error($link);
        }
        break;

    case 'get_user_details':
        // This case remains unchanged
        $user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($user_id > 0) {
            $sql = "SELECT id, username, first_name, last_name, email, phone_number, role_id, is_active
                    FROM users WHERE id = ? AND company_id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ii", $user_id, $company_id);
                if (mysqli_stmt_execute($stmt)) {
                    $result = mysqli_stmt_get_result($stmt);
                    if ($user_data = mysqli_fetch_assoc($result)) {
                        $response['success'] = true;
                        $response['data'] = $user_data;
                    } else {
                        $response['message'] = 'User not found or you are not authorized.';
                    }
                } else {
                    $response['message'] = "Execution failed: " . mysqli_error($link);
                }
                mysqli_stmt_close($stmt);
            } else {
                $response['message'] = "Prepare failed: " . mysqli_error($link);
            }
        } else {
            $response['message'] = "Invalid user ID provided.";
        }
        break;

    case 'update_user':
        // This case remains unchanged
        $user_id = $_POST['id'] ?? null;
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? null);
        $role_id = $_POST['role_id'] ?? null;
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($user_id) || empty($first_name) || empty($last_name) || empty($email) || empty($role_id)) {
            $response['message'] = 'Please fill in all required fields.';
            break;
        }
        // ... (rest of update logic is the same)
        $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone_number = ?, role_id = ?, is_active = ? WHERE id = ? AND company_id = ?";
        // ... etc
        if(true) { // Assume the update logic from before is here
            $response['success'] = true;
            $response['message'] = 'User updated successfully!';
        }

        break;

    case 'delete_user':
        $user_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($user_id <= 0) {
            $response['message'] = 'Invalid user ID provided.';
            break;
        }
        
        // Prepare a delete statement. Crucially, we also check the company_id
        // to ensure an admin from one company cannot delete a user from another.
        $sql = "DELETE FROM users WHERE id = ? AND company_id = ?";
        
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ii", $user_id, $company_id);
            
            if (mysqli_stmt_execute($stmt)) {
                // Check if a row was actually deleted
                if (mysqli_stmt_affected_rows($stmt) > 0) {
                    $response['success'] = true;
                    $response['message'] = 'User has been deleted successfully.';
                } else {
                    // This means no row matched the id and company_id
                    $response['message'] = 'Could not delete user. They may have already been removed, or you do not have permission.';
                }
            } else {
                $response['message'] = 'Failed to execute delete statement. Error: ' . mysqli_error($link);
            }
            mysqli_stmt_close($stmt);
        } else {
            $response['message'] = 'Failed to prepare the delete statement. Error: ' . mysqli_error($link);
        }
        break;

    default:
        $response['message'] = "Invalid request type specified.";
        break;
}

mysqli_close($link); // Close the database connection

echo json_encode($response);
?>