<?php
session_start();
header('Content-Type: application/json');

// --- Database Connection ---
// Adjust the path as needed to correctly point to your connection script.
require_once '../config/db_connect.php';

// --- Security Gate ---
// This is the most critical part. Ensure only Super Admins (role_id = 1) can execute anything in this file.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["role_id"]) || $_SESSION["role_id"] !== 1) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access']);
    exit;
}

// --- Request Router ---
// We use $_REQUEST to handle both GET and POST variables.
$request_type = $_REQUEST['request_type'] ?? '';

switch ($request_type) {
    // --- Company Actions ---
    case 'get_companies':
        get_companies($link);
        break;
    case 'get_company_details':
        get_company_details($link);
        break;
    case 'update_company':
        update_company($link);
        break;
    case 'delete_company':
        delete_record($link, 'companies');
        break;

    // --- User Actions ---
    case 'get_users':
        get_users($link);
        break;
    case 'get_user_details':
        get_user_details($link);
        break;
    case 'update_user':
        update_user($link);
        break;
    case 'delete_user':
        delete_record($link, 'users');
        break;
    
    // --- Data for Modals ---
    case 'get_all_companies_list':
        get_all_companies_list($link);
        break;
    case 'get_all_roles':
        // Assuming you have a 'roles' table like: CREATE TABLE roles (id INT, role_name VARCHAR(50));
        get_all_roles($link);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid request type.']);
        break;
}

mysqli_close($link);

// =================================================================================
// --- Function Definitions ---
// =================================================================================

/**
 * Fetches all companies for the Companies DataTable.
 */
function get_companies($link) {
    $sql = "SELECT id, company_name, contact_person_name, contact_email, is_active FROM companies";
    $result = mysqli_query($link, $sql);
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $actions = '<button class="btn btn-sm btn-primary me-1 edit-company-btn" data-id="' . $row['id'] . '">Edit</button>';
        $actions .= '<button class="btn btn-sm btn-danger delete-company-btn" data-id="' . $row['id'] . '">Delete</button>';
        
        $status = $row['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>';

        $data[] = [
            $row['id'],
            htmlspecialchars($row['company_name']),
            htmlspecialchars($row['contact_person_name']),
            htmlspecialchars($row['contact_email']),
            $status,
            $actions
        ];
    }
    echo json_encode(['data' => $data]);
}

/**
 * Fetches all users for the Users DataTable.
 * Assumes a 'roles' table exists.
 */
function get_users($link) {
    $sql = "SELECT u.id, u.username, u.email, u.first_name, u.last_name, 
                   c.company_name, r.role_name, u.is_active 
            FROM users u
            LEFT JOIN companies c ON u.company_id = c.id
            LEFT JOIN roles r ON u.role_id = r.id
            ORDER BY u.id";
    $result = mysqli_query($link, $sql);
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $actions = '<button class="btn btn-sm btn-primary me-1 edit-user-btn" data-id="' . $row['id'] . '">Edit</button>';
        $actions .= '<button class="btn btn-sm btn-danger delete-user-btn" data-id="' . $row['id'] . '">Delete</button>';
        
        $status = $row['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>';
        $fullName = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);

        $data[] = [
            $row['id'],
            htmlspecialchars($row['username']),
            htmlspecialchars($row['email']),
            $fullName,
            htmlspecialchars($row['company_name'] ?? 'N/A'),
            htmlspecialchars($row['role_name'] ?? 'N/A'),
            $status,
            $actions
        ];
    }
    echo json_encode(['data' => $data]);
}

/**
 * Fetches full details for a single company to populate the edit modal.
 */
function get_company_details($link) {
    $id = $_GET['id'] ?? 0;
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'No ID provided.']);
        return;
    }
    $stmt = mysqli_prepare($link, "SELECT * FROM companies WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    if ($data) {
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Company not found.']);
    }
}

/**
 * Fetches details for a single user to populate the edit modal.
 */
function get_user_details($link) {
    $id = $_GET['id'] ?? 0;
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'No ID provided.']);
        return;
    }
    $stmt = mysqli_prepare($link, "SELECT id, username, email, first_name, last_name, phone_number, company_id, role_id, is_active FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    if ($data) {
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
    }
}

/**
 * Updates a company's record based on data from the edit modal.
 */
function update_company($link) {
    $sql = "UPDATE companies SET company_name=?, contact_person_name=?, contact_email=?, contact_phone=?, address=?, city=?, state=?, zip_code=?, is_active=? WHERE id=?";
    $stmt = mysqli_prepare($link, $sql);
    
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    mysqli_stmt_bind_param($stmt, "ssssssssii", 
        $_POST['company_name'], $_POST['contact_person_name'], $_POST['contact_email'], $_POST['contact_phone'], 
        $_POST['address'], $_POST['city'], $_POST['state'], $_POST['zip_code'], 
        $is_active, $_POST['id']
    );

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Company updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update company.']);
    }
}

/**
 * Updates a user's record. Handles optional password update.
 */
function update_user($link) {
    $id = $_POST['id'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (!empty($_POST['password'])) {
        // If password is provided, hash it and update it
        $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql = "UPDATE users SET username=?, email=?, first_name=?, last_name=?, company_id=?, role_id=?, is_active=?, password=? WHERE id=?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "ssssiiisi", 
            $_POST['username'], $_POST['email'], $_POST['first_name'], $_POST['last_name'], 
            $_POST['company_id'], $_POST['role_id'], $is_active, $password_hash, $id
        );
    } else {
        // If password is not provided, update everything else
        $sql = "UPDATE users SET username=?, email=?, first_name=?, last_name=?, company_id=?, role_id=?, is_active=? WHERE id=?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "ssssiiii", 
            $_POST['username'], $_POST['email'], $_POST['first_name'], $_POST['last_name'], 
            $_POST['company_id'], $_POST['role_id'], $is_active, $id
        );
    }

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'User updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update user. ' . mysqli_error($link)]);
    }
}

/**
 * Deletes a record from a specified table.
 */
function delete_record($link, $table_name) {
    $id = $_POST['id'] ?? 0;
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'No ID provided for deletion.']);
        return;
    }
    
    // Sanitize table name to prevent SQL injection, although it's controlled internally here.
    if (!in_array($table_name, ['companies', 'users'])) {
         echo json_encode(['success' => false, 'message' => 'Invalid table specified.']);
        return;
    }

    $sql = "DELETE FROM $table_name WHERE id = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => ucfirst(rtrim($table_name, 's')) . ' deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete record.']);
    }
}

/**
 * Fetches all companies (id, name) for dropdown lists.
 */
function get_all_companies_list($link) {
    $sql = "SELECT id, company_name FROM companies ORDER BY company_name";
    $result = mysqli_query($link, $sql);
    $companies = mysqli_fetch_all($result, MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'data' => $companies]);
}

/**
 * Fetches all roles (id, name) for dropdown lists.
 */
function get_all_roles($link) {
    // This assumes you have a 'roles' table. If not, you might need to hard-code this array.
    $sql = "SELECT id, role_name FROM roles ORDER BY id";
    $result = mysqli_query($link, $sql);
    $roles = mysqli_fetch_all($result, MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'data' => $roles]);
}