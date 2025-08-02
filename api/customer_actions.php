<?php
// api/customer_actions.php

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["role_id"], [2, 3])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

require_once dirname(__DIR__) . '/config/db_connect.php';

$company_id = $_SESSION["company_id"];
if (empty($company_id)) {
    echo json_encode(['success' => false, 'message' => 'No company associated with this account.']);
    exit;
}

$response = ['success' => false, 'data' => [], 'message' => null];
$request_type = $_REQUEST['request_type'] ?? null;

switch ($request_type) {
    case 'get_all_customers':
        // SQL query updated: Removed contact_person, contact_phone
        $sql = "SELECT id, customer_name, city, status FROM customers WHERE company_id = ? ORDER BY customer_name ASC";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $company_id);
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                while ($row = mysqli_fetch_assoc($result)) {
                    $status_badge = ($row['status'] === 'active') ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>';
                    $actions = '<button type="button" class="btn btn-sm btn-outline-primary edit-customer-btn" data-id="' . $row['id'] . '">Edit</button> ' .
                               '<button type="button" class="btn btn-sm btn-outline-danger delete-customer-btn" data-id="' . $row['id'] . '">Delete</button>';
                    // Adjusted data array: Removed contact_person (index 2) and contact_phone (index 3)
                    // New order: ID, Customer Name, City, Status, Actions
                    $response['data'][] = [
                        $row['id'],
                        htmlspecialchars($row['customer_name']),
                        htmlspecialchars($row['city']),
                        $status_badge,
                        $actions
                    ];
                }
                $response['success'] = true;
            } else { $response['message'] = "API Error: Failed to execute statement."; }
            mysqli_stmt_close($stmt);
        } else { $response['message'] = "API Error: Failed to prepare statement."; }
        break;

    case 'get_customer_details':
        $customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($customer_id > 0) {
            // SQL query updated: Explicitly selecting columns, removed contact_person, contact_email, contact_phone
            $sql = "SELECT id, customer_name, address_line_1, address_line_2, city, state_province, postal_code, notes, status FROM customers WHERE id = ? AND company_id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ii", $customer_id, $company_id);
                if (mysqli_stmt_execute($stmt)) {
                    $result = mysqli_stmt_get_result($stmt);
                    if ($customer_data = mysqli_fetch_assoc($result)) {
                        $response['success'] = true;
                        $response['data'] = $customer_data;
                    } else { $response['message'] = 'Customer not found or you are not authorized.'; }
                } else { $response['message'] = "Execution failed."; }
                mysqli_stmt_close($stmt);
            } else { $response['message'] = "Prepare failed."; }
        } else { $response['message'] = "Invalid customer ID."; }
        break;

    case 'update_customer':
        // Basic validation
        if(empty($_POST['id']) || empty($_POST['customer_name'])) {
            $response['message'] = 'Customer ID and Name are required.';
            break;
        }
        // SQL query updated: Removed contact_person, contact_email, contact_phone
        $sql = "UPDATE customers SET customer_name=?, address_line_1=?, address_line_2=?, city=?, state_province=?, postal_code=?, status=?, notes=? WHERE id=? AND company_id=?";
        if($stmt = mysqli_prepare($link, $sql)){
            // mysqli_stmt_bind_param updated: Removed 'ssss' for contact_person, contact_email, contact_phone
            mysqli_stmt_bind_param($stmt, "sssssssii",
                $_POST['customer_name'],
                $_POST['address_line_1'],
                $_POST['address_line_2'],
                $_POST['city'],
                $_POST['state_province'],
                $_POST['postal_code'],
                $_POST['status'],
                $_POST['notes'],
                $_POST['id'],
                $company_id
            );
            if(mysqli_stmt_execute($stmt)){
                $response['success'] = true;
                $response['message'] = 'Customer updated successfully!';
            } else { $response['message'] = 'Failed to update customer.'; }
            mysqli_stmt_close($stmt);
        } else { $response['message'] = 'Failed to prepare update statement.'; }
        break;

    case 'delete_customer':
        $customer_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($customer_id > 0) {
            $sql = "DELETE FROM customers WHERE id = ? AND company_id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ii", $customer_id, $company_id);
                if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
                    $response['success'] = true;
                    $response['message'] = 'Customer deleted successfully.';
                } else { $response['message'] = 'Could not delete customer or permission denied.'; }
                mysqli_stmt_close($stmt);
            } else { $response['message'] = 'Failed to prepare delete statement.'; }
        } else { $response['message'] = 'Invalid customer ID provided.'; }
        break;
    
    default:
        $response['message'] = "Invalid request type specified.";
        break;
}

mysqli_close($link);
echo json_encode($response);
?>