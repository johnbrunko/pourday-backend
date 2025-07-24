<?php
session_start();

// Security Check: Allow CompanyAdmins and ProjectManagers to access.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["role_id"], [2, 3])) {
    header("location: index.php");
    exit;
}

require_once 'config/db_connect.php';

$company_id = $_SESSION["company_id"];
if (empty($company_id)) {
    echo "Error: Your account is not associated with a company.";
    exit;
}

// --- Form Variable Initialization ---
$title = $project_id = $sq_ft = $notes = $type = $scheduled = "";
$assigned_to_user_id = null;
$task_types = ['pour' => 0, 'bent_plate' => 0, 'pre_camber' => 0, 'post_camber' => 0, 'fffl' => 0, 'moisture' => 0, 'cut_fill' => 0, 'other' => 0];
$billable = 0;
$form_errors = [];
$success_message = "";
$is_form_collapsed = "collapse";
$is_aria_expanded = "false";

// --- Check for success message from session after a redirect ---
if (isset($_SESSION['form_success_message'])) {
    $success_message = $_SESSION['form_success_message'];
    unset($_SESSION['form_success_message']);
}

// --- Fetch Projects for Dropdown ---
// Note: 'address' was previously assumed, but now individual address components are selected
$projects_list = [];
$sql_projects = "SELECT id, job_name, customer_id, street_1, street_2, city, state, zip FROM projects WHERE company_id = ? AND status = 'Ongoing' ORDER BY job_name ASC";
if ($stmt_projects = mysqli_prepare($link, $sql_projects)) {
    mysqli_stmt_bind_param($stmt_projects, "i", $company_id);
    mysqli_stmt_execute($stmt_projects);
    $result_projects = mysqli_stmt_get_result($stmt_projects);
    while ($row = mysqli_fetch_assoc($result_projects)) {
        $projects_list[] = $row;
    }
    mysqli_stmt_close($stmt_projects);
}

// --- Fetch Users for Dropdown ---
$users_list = [];
$sql_users = "SELECT id, first_name, last_name FROM users WHERE company_id = ? AND is_active = 1 ORDER BY first_name ASC, last_name ASC";
if ($stmt_users = mysqli_prepare($link, $sql_users)) {
    mysqli_stmt_bind_param($stmt_users, "i", $company_id);
    mysqli_stmt_execute($stmt_users);
    $result_users = mysqli_stmt_get_result($stmt_users);
    while ($row = mysqli_fetch_assoc($result_users)) {
        $users_list[] = $row;
    }
    mysqli_stmt_close($stmt_users);
}


// --- Form Processing Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_task'])) {
    $is_form_collapsed = "";
    $is_aria_expanded = "true";

    // Sanitize and retrieve data
    $title = trim($_POST['title']);
    $project_id = (int)$_POST['project_id'];
    $sq_ft = trim($_POST['sq_ft']) === '' ? null : (float)$_POST['sq_ft'];
    $notes = trim($_POST['notes']);
    $scheduled = empty($_POST['scheduled']) ? null : trim($_POST['scheduled']);
    $billable = isset($_POST['billable']) ? 1 : 0;
    $assigned_to_user_id = empty($_POST['assigned_to_user_id']) ? null : (int)$_POST['assigned_to_user_id'];
    foreach ($task_types as $key => &$value) {
        $value = (isset($_POST['task_types']) && in_array($key, $_POST['task_types'])) ? 1 : 0;
    }

    // Validation
    if (empty($title)) { $form_errors['title'] = "Task title is required."; }
    if (empty($project_id)) { $form_errors['project_id'] = "Please select a project."; }

    if (empty($form_errors)) {
        $customer_id = 0;
        foreach($projects_list as $project) {
            if ($project['id'] == $project_id) {
                $customer_id = $project['customer_id'];
                break;
            }
        }
        
        if ($customer_id == 0) {
            $form_errors['general'] = "Invalid project selected.";
        } else {
            $upload_code = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 5);
            $sql = "INSERT INTO tasks (company_id, project_id, customer_id, title, pour, bent_plate, pre_camber, post_camber, fffl, moisture, cut_fill, other, sq_ft, notes, scheduled, upload_code, billable, assigned_to_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "iiisiiiiiiiidsssii",
                    $company_id, $project_id, $customer_id, $title,
                    $task_types['pour'], $task_types['bent_plate'], $task_types['pre_camber'], $task_types['post_camber'],
                    $task_types['fffl'], $task_types['moisture'], $task_types['cut_fill'], $task_types['other'],
                    $sq_ft, $notes, $scheduled, $upload_code, $billable, $assigned_to_user_id
                );

                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['form_success_message'] = "Task '" . htmlspecialchars($title) . "' created successfully!";
                    header("Location: tasks.php");
                    exit();
                } else {
                    $form_errors['general'] = "Error: Could not create task. " . mysqli_error($link);
                }
                mysqli_stmt_close($stmt);
            } else {
                    $form_errors['general'] = "Database prepare failed: " . mysqli_error($link);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Management - PourDay App</title>
    <link href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-bootstrap@2.0.12/dist/css/modus-bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-icons@1.16.0/dist/modus-solid/fonts/modus-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700&display=fallback"/>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'topbar_mobile.html'; ?>
<?php include 'sidebar.html'; ?>

<main class="page-content-wrapper">
    <div class="container-fluid p-4">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">Task Management</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($form_errors['general'])): ?><div class="alert alert-danger"><?php echo $form_errors['general']; ?></div><?php endif; ?>
                <?php if (!empty($success_message)): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>

                <div class="d-grid gap-2 mb-4">
                    <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addTaskCollapse" aria-expanded="<?php echo $is_aria_expanded; ?>">Create New Task</button>
                </div>
                
                <div class="<?php echo $is_form_collapsed; ?>" id="addTaskCollapse">
                    <div class="card card-body mb-4">
                        <h4 class="mt-2">New Task Details</h4>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="mt-3">
                            <input type="hidden" name="add_task" value="1">
                            <div class="row">
                                <div class="col-md-6 mb-3"><label for="project_id" class="form-label">Project</label><select class="form-select <?php echo isset($form_errors['project_id']) ? 'is-invalid' : ''; ?>" id="project_id" name="project_id" required><option value="">-- Select a Project --</option><?php foreach ($projects_list as $project) { echo '<option value="' . $project['id'] . '" data-customer-id="' . $project['customer_id'] . '"' . ($project_id == $project['id'] ? ' selected' : '') . '>' . htmlspecialchars($project['job_name']) . '</option>'; } ?></select><div class="invalid-feedback"><?php echo $form_errors['project_id'] ?? ''; ?></div></div>
                                <div class="col-md-6 mb-3"><label for="title" class="form-label">Task Title</label><input type="text" class="form-control <?php echo isset($form_errors['title']) ? 'is-invalid' : ''; ?>" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>" required><div class="invalid-feedback"><?php echo $form_errors['title'] ?? ''; ?></div></div>
                            </div>
                            <div class="mb-3"><label class="form-label">Task Types</label><div>
                                <?php $all_task_types = ['pour' => 'Pour', 'bent_plate' => 'Bent Plate', 'pre_camber' => 'Pre-Camber', 'post_camber' => 'Post-Camber', 'fffl' => 'FF/FL', 'moisture' => 'Moisture', 'cut_fill' => 'Cut/Fill', 'other' => 'Other']; ?>
                                <?php 
                                // FIX: Ensure $task_types is properly initialized before this loop
                                if (!isset($task_types) || !is_array($task_types)) {
                                    $task_types = [
                                        'pour' => 0, 'bent_plate' => 0, 'pre_camber' => 0, 'post_camber' => 0,
                                        'fffl' => 0, 'moisture' => 0, 'cut_fill' => 0, 'other' => 0
                                    ];
                                }
                                foreach ($all_task_types as $key => $label): 
                                ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="task_types[]" value="<?php echo $key; ?>" id="task_type_<?php echo $key; ?>" <?php echo (isset($task_types[$key]) && $task_types[$key]) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="task_type_<?php echo $key; ?>"><?php echo $label; ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div></div>
                            <div class="row">
                                <div class="col-md-4 mb-3"><label for="scheduled" class="form-label">Scheduled Date</label><input type="date" class="form-control" id="scheduled" name="scheduled" value="<?php echo htmlspecialchars($scheduled); ?>"></div>
                                <div class="col-md-4 mb-3"><label for="sq_ft" class="form-label">Square Footage</label><input type="number" step="0.01" class="form-control" id="sq_ft" name="sq_ft" value="<?php echo htmlspecialchars($sq_ft); ?>"></div>
                                <div class="col-md-4 mb-3">
                                    <label for="assigned_to_user_id" class="form-label">Assign To</label>
                                    <select class="form-select" id="assigned_to_user_id" name="assigned_to_user_id">
                                        <option value="">-- Unassigned --</option>
                                        <?php foreach ($users_list as $user): ?>
                                            <option value="<?php echo $user['id']; ?>" <?php echo ($assigned_to_user_id == $user['id'] ? 'selected' : ''); ?>>
                                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3"><label for="notes" class="form-label">Notes</label><textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($notes); ?></textarea></div>
                            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="billable" value="1" id="billable" <?php echo $billable ? 'checked' : ''; ?>><label class="form-check-label" for="billable">This task is billable</label></div>
                            <div class="d-grid"><button type="submit" class="btn btn-primary">Create Task</button></div>
                        </form>
                    </div>
                </div>

                ---

                <h4 class="mt-5">Open Tasks</h4>
                <div class="table-responsive">
                    <table id="openTasksTable" class="table table-hover table-bordered w-100">
                        <thead class="table-light"><tr><th>ID</th><th>Task</th><th>Project</th><th>Upload Code</th><th>Scheduled</th><th>Assigned To</th><th>Task Types</th><th>Actions</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>

                ---

                <h4 class="mt-5">Completed Tasks</h4>
                <div class="table-responsive">
                    <table id="completedTasksTable" class="table table-hover table-bordered w-100">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Task</th>
                                <th>Project</th>
                                <th>Sq Footage</th>
                                <th>Billable</th>
                                <th>Completed On</th>
                                <th>Assigned To</th>
                                <th>Task Types</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</main>

<?php include 'includes/task_modals.html'; ?>
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100"><div id="appToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true"><div class="toast-header"><strong class="me-auto" id="toastTitle"></strong><button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button></div><div class="toast-body" id="toastBody"></div></div></div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.min.js"></script>
<script src="js/main.js"></script>
<script src="js/tasks.js"></script>

<?php mysqli_close($link); ?>
</body>
</html>