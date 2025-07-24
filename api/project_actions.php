<?php
header('Content-Type: application/json');
session_start();

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
                    if ($row['status'] === 'Hold') $status_badge = '<span class="badge bg-warning">Hold</span>';
                    
                    $actions = '<button type="button" class="btn btn-sm btn-outline-primary edit-project-btn" data-id="' . $row['id'] . '">Edit</button> ' .
                               '<button type="button" class="btn btn-sm btn-outline-danger delete-project-btn" data-id="' . $row['id'] . '">Delete</button>';
                    $response['data'][] = [$row['id'], htmlspecialchars($row['job_name']), htmlspecialchars($row['job_number']), htmlspecialchars($row['customer_name']), $status_badge, $actions];
                }
                $response['success'] = true;
            } else { $response['message'] = "API Error: Execute failed."; }
            mysqli_stmt_close($stmt);
        } else { $response['message'] = "API Error: Prepare failed."; }
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
                } else { $response['message'] = 'Execute failed.'; }
                mysqli_stmt_close($stmt);
            } else { $response['message'] = 'Prepare failed.'; }
        } else { $response['message'] = 'Invalid Project ID.'; }
        break;

    case 'update_project':
        // Simplified validation for brevity
        if(empty($_POST['id']) || empty($_POST['job_name'])) {
            $response['message'] = 'Project ID and Name are required.';
            break;
        }
        $sql = "UPDATE projects SET job_name=?, job_number=?, street_1=?, street_2=?, city=?, state=?, zip=?, customer_id=?, contact_person=?, notes=?, status=? WHERE id=? AND company_id=?";
        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, "sssssssisssii", 
                $_POST['job_name'], $_POST['job_number'], $_POST['street_1'], $_POST['street_2'], $_POST['city'], $_POST['state'], $_POST['zip'], 
                $_POST['customer_id'], $_POST['contact_person'], $_POST['notes'], $_POST['status'], $_POST['id'], $company_id);
            
            if(mysqli_stmt_execute($stmt)){
                $response['success'] = true;
                $response['message'] = 'Project updated successfully!';
            } else { $response['message'] = 'Failed to update project.'; }
            mysqli_stmt_close($stmt);
        } else { $response['message'] = 'Failed to prepare update statement.'; }
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
                } else { $response['message'] = 'Could not delete project or permission denied.'; }
                mysqli_stmt_close($stmt);
            } else { $response['message'] = 'Failed to prepare delete statement.'; }
        } else { $response['message'] = 'Invalid Project ID.'; }
        break;
        
    default:
        $response['message'] = "Invalid request type.";
        break;
}

mysqli_close($link);
echo json_encode($response);