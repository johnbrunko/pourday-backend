<?php
// Enable error reporting for debugging. REMOVE IN PRODUCTION!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
session_start();

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["role_id"], [2, 3])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

require_once dirname(__DIR__) . '/config/db_connect.php';
$company_id = $_SESSION["company_id"];

$response = ['success' => false, 'data' => [], 'message' => null];
$request_type = $_REQUEST['request_type'] ?? null;

switch ($request_type) {
    case 'get_all_projects':
        $sql = "SELECT p.id, p.job_name, p.job_number, p.status, c.customer_name 
                FROM projects p
                JOIN customers c ON p.customer_id = c.id
                WHERE p.company_id = ? 
                ORDER BY p.created DESC";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $company_id);
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                while ($row = mysqli_fetch_assoc($result)) {
                    $status_badge = '<span class="badge bg-secondary">' . htmlspecialchars($row['status']) . '</span>';
                    if ($row['status'] === 'Ongoing') $status_badge = '<span class="badge bg-success">Ongoing</span>';
                    if ($row['status'] === 'Hold') $status_badge = '<span class="badge bg-warning text-dark">Hold</span>';
                    if ($row['status'] === 'Completed') $status_badge = '<span class="badge bg-info">Completed</span>';
                    
                    $actions = '<button type="button" class="btn btn-sm btn-outline-primary edit-project-btn" data-id="' . $row['id'] . '">Edit</button> ' .
                               '<button type="button" class="btn btn-sm btn-outline-danger delete-project-btn" data-id="' . $row['id'] . '">Delete</button>';
                    $response['data'][] = [$row['id'], htmlspecialchars($row['job_name']), htmlspecialchars($row['job_number']), htmlspecialchars($row['customer_name']), $status_badge, $actions];
                }
                $response['success'] = true;
            } else { $response['message'] = "API Error: Execute failed for get_all_projects: " . mysqli_stmt_error($stmt); }
            mysqli_stmt_close($stmt);
        } else { $response['message'] = "API Error: Prepare failed for get_all_projects: " . mysqli_error($link); }
        break;

    case 'get_project_details':
        $project_id = $_GET['id'] ?? 0;
        if ($project_id > 0) {
            $sql = "SELECT * FROM projects WHERE id = ? AND company_id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ii", $project_id, $company_id);
                if (mysqli_stmt_execute($stmt)) {
                    $result = mysqli_stmt_get_result($stmt);
                    if ($data = mysqli_fetch_assoc($result)) {
                        $response['success'] = true;
                        $response['data'] = $data;
                    } else { $response['message'] = 'Project not found or permission denied.'; }
                } else { $response['message'] = 'Execute failed for get_project_details: ' . mysqli_stmt_error($stmt); }
                mysqli_stmt_close($stmt);
            } else { $response['message'] = 'Prepare failed for get_project_details: ' . mysqli_error($link); }
        } else { $response['message'] = 'Invalid Project ID for get_project_details.'; }
        break;

    case 'get_contacts_for_customer':
        $customer_id = $_GET['customer_id'] ?? 0;
        if ($customer_id > 0) {
            // Also check that the customer belongs to the user's company for security
            $sql = "SELECT c.id, c.first_name, c.last_name FROM contacts c JOIN customers cust ON c.customer_id = cust.id WHERE c.customer_id = ? AND cust.company_id = ? ORDER BY c.last_name, c.first_name";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ii", $customer_id, $company_id);
                if (mysqli_stmt_execute($stmt)) {
                    $result = mysqli_stmt_get_result($stmt);
                    while($row = mysqli_fetch_assoc($result)) {
                        $response['data'][] = $row;
                    }
                    $response['success'] = true;
                } else { $response['message'] = 'Could not fetch contacts: ' . mysqli_stmt_error($stmt); }
                mysqli_stmt_close($stmt);
            } else { $response['message'] = 'Prepare failed for get_contacts_for_customer: ' . mysqli_error($link); }
        } else { $response['message'] = 'Invalid Customer ID for contacts.'; }
        break;

    case 'update_project':
        // Basic validation
        if(empty($_POST['id'])) { $response['message'] = 'Project ID is required for update.'; break; }
        if(empty($_POST['job_name'])) { $response['message'] = 'Project Name is required.'; break; }
        if(empty($_POST['customer_id'])) { $response['message'] = 'Customer is required.'; break; }

        // Sanitize and cast all input variables
        $job_name_val = trim($_POST['job_name']);
        $job_number_val = trim($_POST['job_number']);
        $street_1_val = trim($_POST['street_1']);
        $street_2_val = trim($_POST['street_2']);
        $city_val = trim($_POST['city']);
        $state_val = trim($_POST['state']);
        $zip_val = trim($_POST['zip']);
        $customer_id_val = (int)$_POST['customer_id'];
        $notes_val = trim($_POST['notes']);
        $status_val = trim($_POST['status']);
        $project_id_val = (int)$_POST['id'];

        // *** FIXED: Handle contact IDs to be NULL if empty, consistent with add form ***
        $contact1_val = !empty($_POST['contact_id_1']) ? (int)$_POST['contact_id_1'] : null;
        $contact2_val = !empty($_POST['contact_id_2']) ? (int)$_POST['contact_id_2'] : null;
        $contact3_val = !empty($_POST['contact_id_3']) ? (int)$_POST['contact_id_3'] : null;

        $sql = "UPDATE projects SET 
                    job_name=?, job_number=?, street_1=?, street_2=?, city=?, state=?, zip=?, 
                    customer_id=?, contact_id_1=?, contact_id_2=?, contact_id_3=?, notes=?, status=? 
                WHERE id=? AND company_id=?";
        
        if($stmt = mysqli_prepare($link, $sql)){
            // *** FIXED: Corrected the type string and added all 15 variables to bind ***
            // Type string: sssssss-iiiss-s-ii (15 total)
            mysqli_stmt_bind_param($stmt, "sssssssiiisssii",
                $job_name_val, $job_number_val, $street_1_val, $street_2_val, $city_val, $state_val, $zip_val,
                $customer_id_val, $contact1_val, $contact2_val, $contact3_val, 
                $notes_val, $status_val, 
                $project_id_val, $company_id
            );
            
            if(mysqli_stmt_execute($stmt)){
                $response['success'] = true;
                $response['message'] = 'Project updated successfully!';
            } else {
                $response['message'] = 'Failed to update project: ' . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        } else {
            $response['message'] = 'Failed to prepare update statement: ' . mysqli_error($link);
        }
        break;

    case 'delete_project':
        $project_id = $_POST['id'] ?? 0;
        if ($project_id > 0) {
            $sql = "DELETE FROM projects WHERE id = ? AND company_id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ii", $project_id, $company_id);
                if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
                    $response['success'] = true;
                    $response['message'] = 'Project deleted successfully.';
                } else { $response['message'] = 'Could not delete project or permission denied: ' . mysqli_stmt_error($stmt); }
                mysqli_stmt_close($stmt);
            } else { $response['message'] = 'Failed to prepare delete statement: ' . mysqli_error($link); }
        } else { $response['message'] = 'Invalid Project ID for deletion.'; }
        break;
        
    default:
        $response['message'] = "Invalid request type.";
        break;
}

mysqli_close($link);
echo json_encode($response);