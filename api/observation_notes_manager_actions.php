<?php
session_start();
header('Content-Type: application/json');

// --- Security and Initialization ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["role_id"], [1, 2])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

require_once dirname(__DIR__) . '/config/db_connect.php';

$action = $_GET['action'] ?? '';
$company_id = $_SESSION['company_id'];
$response = ['success' => false, 'message' => 'Invalid action.'];

// --- Main Action Router ---
try {
    switch ($action) {
        case 'get_templates':
            $stmt = $link->prepare("SELECT id, category, text, is_active FROM field_report_templates WHERE company_id = ? ORDER BY category, text");
            $stmt->bind_param("i", $company_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_all(MYSQLI_ASSOC);
            $response = ['success' => true, 'data' => $data];
            break;

        case 'get_template':
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) throw new Exception("Invalid ID provided.");
            
            $stmt = $link->prepare("SELECT id, category, text FROM field_report_templates WHERE id = ? AND company_id = ?");
            $stmt->bind_param("ii", $id, $company_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            
            if (!$data) throw new Exception("Template not found or permission denied.");
            $response = ['success' => true, 'data' => $data];
            break;

        case 'add_template':
            // FIXED: Replaced deprecated FILTER_SANITIZE_STRING
            $category = trim($_POST['category'] ?? '');
            $text = trim($_POST['text'] ?? '');

            if (empty($category) || empty($text)) throw new Exception("Category and text cannot be empty.");

            $stmt = $link->prepare("INSERT INTO field_report_templates (company_id, category, text) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $company_id, $category, $text);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Template added successfully.'];
            } else {
                throw new Exception("Failed to add template.");
            }
            break;

        case 'update_template':
            $id = filter_input(INPUT_POST, 'template_id', FILTER_VALIDATE_INT);
            // FIXED: Replaced deprecated FILTER_SANITIZE_STRING
            $category = trim($_POST['category'] ?? '');
            $text = trim($_POST['text'] ?? '');

            if (!$id || empty($category) || empty($text)) throw new Exception("Invalid data provided.");

            $stmt = $link->prepare("UPDATE field_report_templates SET category = ?, text = ? WHERE id = ? AND company_id = ?");
            $stmt->bind_param("ssii", $category, $text, $id, $company_id);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $response = ['success' => true, 'message' => 'Template updated successfully.'];
                } else {
                    // This is not an error, just no change, so we return success.
                    $response = ['success' => true, 'message' => 'No changes were made.'];
                }
            } else {
                throw new Exception("Failed to update template.");
            }
            break;

        case 'toggle_status':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) throw new Exception("Invalid ID provided.");

            // This statement toggles the is_active field between 0 and 1
            $stmt = $link->prepare("UPDATE field_report_templates SET is_active = 1 - is_active WHERE id = ? AND company_id = ?");
            $stmt->bind_param("ii", $id, $company_id);
            if ($stmt->execute()) {
                 if ($stmt->affected_rows > 0) {
                    $response = ['success' => true, 'message' => 'Status updated successfully.'];
                } else {
                    throw new Exception("Template not found or permission denied.");
                }
            } else {
                throw new Exception("Failed to update status.");
            }
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response);
exit;