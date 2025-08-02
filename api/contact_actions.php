<?php
// api/contact_actions.php

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
    case 'get_all_contacts':
        // Join with customers table to get the customer name
        $sql = "SELECT c.id, c.first_name, c.last_name, cust.customer_name, c.email, c.phone 
                FROM contacts c 
                LEFT JOIN customers cust ON c.customer_id = cust.id
                WHERE cust.company_id = ? 
                ORDER BY c.last_name, c.first_name ASC";
        
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $company_id);
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                while ($row = mysqli_fetch_assoc($result)) {
                    $actions = '<button type="button" class="btn btn-sm btn-outline-primary edit-contact-btn" data-id="' . $row['id'] . '">Edit</button> ' .
                               '<button type="button" class="btn btn-sm btn-outline-danger delete-contact-btn" data-id="' . $row['id'] . '">Delete</button>';
                    
                    $response['data'][] = [
                        $row['id'], 
                        htmlspecialchars(trim($row['first_name'] . ' ' . $row['last_name'])),
                        htmlspecialchars($row['customer_name'] ?? 'N/A'),
                        htmlspecialchars($row['email']), 
                        htmlspecialchars($row['phone']), 
                        $actions
                    ];
                }
                $response['success'] = true;
            } else { $response['message'] = "API Error: Failed to execute statement."; }
            mysqli_stmt_close($stmt);
        } else { $response['message'] = "API Error: Failed to prepare statement."; }
        break;

    case 'get_contact_details':
        $contact_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($contact_id > 0) {
            // We need to ensure the contact belongs to a customer of the logged-in user's company
            $sql = "SELECT c.* FROM contacts c LEFT JOIN customers cust ON c.customer_id = cust.id WHERE c.id = ? AND cust.company_id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ii", $contact_id, $company_id);
                if (mysqli_stmt_execute($stmt)) {
                    $result = mysqli_stmt_get_result($stmt);
                    if ($contact_data = mysqli_fetch_assoc($result)) {
                        $response['success'] = true;
                        $response['data'] = $contact_data;
                    } else { $response['message'] = 'Contact not found or you are not authorized.'; }
                } else { $response['message'] = "Execution failed."; }
                mysqli_stmt_close($stmt);
            } else { $response['message'] = "Prepare failed."; }
        } else { $response['message'] = "Invalid contact ID."; }
        break;

    case 'add_contact':
        if(empty($_POST['first_name']) || empty($_POST['last_name'])) {
            $response['message'] = 'First and Last Name are required.';
            break;
        }
        $sql = "INSERT INTO contacts (first_name, last_name, email, phone, title, customer_id, notes) VALUES (?, ?, ?, ?, ?, ?, ?)";
        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, "sssssis", 
                $_POST['first_name'], $_POST['last_name'], $_POST['email'], $_POST['phone'], 
                $_POST['title'], $_POST['customer_id'], $_POST['notes']);
            if(mysqli_stmt_execute($stmt)){
                $response['success'] = true;
                $response['message'] = 'Contact added successfully!';
            } else { $response['message'] = 'Failed to add contact.'; }
            mysqli_stmt_close($stmt);
        } else { $response['message'] = 'Failed to prepare insert statement.'; }
        break;

    case 'update_contact':
        if(empty($_POST['id']) || empty($_POST['first_name']) || empty($_POST['last_name'])) {
            $response['message'] = 'Contact ID, First Name, and Last Name are required.';
            break;
        }
        $sql = "UPDATE contacts SET first_name=?, last_name=?, email=?, phone=?, title=?, customer_id=?, notes=? WHERE id=?";
        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, "sssssisi", 
                $_POST['first_name'], $_POST['last_name'], $_POST['email'], $_POST['phone'], 
                $_POST['title'], $_POST['customer_id'], $_POST['notes'], $_POST['id']);
            if(mysqli_stmt_execute($stmt)){
                $response['success'] = true;
                $response['message'] = 'Contact updated successfully!';
            } else { $response['message'] = 'Failed to update contact.'; }
            mysqli_stmt_close($stmt);
        } else { $response['message'] = 'Failed to prepare update statement.'; }
        break;

    case 'delete_contact':
        $contact_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($contact_id > 0) {
            $sql = "DELETE FROM contacts WHERE id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "i", $contact_id);
                if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
                    $response['success'] = true;
                    $response['message'] = 'Contact deleted successfully.';
                } else { $response['message'] = 'Could not delete contact.'; }
                mysqli_stmt_close($stmt);
            } else { $response['message'] = 'Failed to prepare delete statement.'; }
        } else { $response['message'] = 'Invalid contact ID provided.'; }
        break;
    
    default:
        $response['message'] = "Invalid request type specified.";
        break;
}

mysqli_close($link);
echo json_encode($response);
?>