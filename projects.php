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
$job_name = $job_number = $street_1 = $street_2 = $city = $state = $zip = $customer_id = $contact_person = $notes = "";
$form_errors = [];
$success_message = "";
$is_form_collapsed = "collapse";
$is_aria_expanded = "false";

// --- Fetch Customers for Dropdown ---
$customers_list = [];
$sql_customers = "SELECT id, customer_name FROM customers WHERE company_id = ? AND status = 'active' ORDER BY customer_name ASC";
if ($stmt_customers = mysqli_prepare($link, $sql_customers)) {
    mysqli_stmt_bind_param($stmt_customers, "i", $company_id);
    mysqli_stmt_execute($stmt_customers);
    $result_customers = mysqli_stmt_get_result($stmt_customers);
    while ($row = mysqli_fetch_assoc($result_customers)) {
        $customers_list[] = $row;
    }
    mysqli_stmt_close($stmt_customers);
}

// --- Form Processing Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_project'])) {
    $is_form_collapsed = "";
    $is_aria_expanded = "true";

    // --- Retrieve and Sanitize ---
    $job_name = trim($_POST['job_name']);
    $job_number = trim($_POST['job_number']);
    $street_1 = trim($_POST['street_1']);
    $street_2 = trim($_POST['street_2']);
    $city = trim($_POST['city']);
    $state = trim($_POST['state']);
    $zip = trim($_POST['zip']);
    $customer_id = trim($_POST['customer_id']);
    $contact_person = trim($_POST['contact_person']);
    $notes = trim($_POST['notes']);

    // --- Validation ---
    if (empty($job_name)) { $form_errors['job_name'] = "Job name is required."; }
    if (empty($street_1)) { $form_errors['street_1'] = "Street address is required."; }
    if (empty($city)) { $form_errors['city'] = "City is required."; }
    if (empty($state)) { $form_errors['state'] = "State is required."; }
    if (empty($zip)) { $form_errors['zip'] = "ZIP code is required."; }
    if (empty($customer_id)) { $form_errors['customer_id'] = "Please select a customer."; }

    // --- Database Insertion ---
    if (empty($form_errors)) {
        $sql = "INSERT INTO projects (company_id, job_name, job_number, street_1, street_2, city, state, zip, customer_id, contact_person, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($link, $sql)) {
            $default_status = 'Ongoing';
            mysqli_stmt_bind_param($stmt, "isssssssisss", 
                $company_id, $job_name, $job_number, $street_1, $street_2, $city, $state, $zip, $customer_id, $contact_person, $notes, $default_status
            );

            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Project '" . htmlspecialchars($job_name) . "' has been created successfully!";
                $is_form_collapsed = "collapse";
                $is_aria_expanded = "false";
                // Clear form fields
                $job_name = $job_number = $street_1 = $street_2 = $city = $state = $zip = $customer_id = $contact_person = $notes = "";
            } else {
                $form_errors['general'] = "Error: Could not create project. " . mysqli_error($link);
            }
            mysqli_stmt_close($stmt);
        } else {
            $form_errors['general'] = "Error: Database prepare failed. " . mysqli_error($link);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Management - PourDay App</title>
    <!-- Stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-bootstrap@2.0.12/dist/css/modus-bootstrap.min.css" rel="stylesheet">
    <!-- FIXED: Added missing Modus Icons stylesheet -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-icons@1.16.0/dist/modus-solid/fonts/modus-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700&display=fallback"/>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- FIXED: Added missing mobile top bar and sidebar includes -->
<?php include 'topbar_mobile.html'; ?>
<?php include 'sidebar.html'; ?>

<!-- FIXED: Added main content wrapper for correct layout -->
<main class="page-content-wrapper">
    <div class="container-fluid p-4">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h3 class="mb-0">Project Management</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($form_errors['general'])): ?><div class="alert alert-danger"><?php echo $form_errors['general']; ?></div><?php endif; ?>
                <?php if (!empty($success_message)): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>

                <div class="d-grid gap-2 mb-4">
                    <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addProjectCollapse" aria-expanded="<?php echo $is_aria_expanded; ?>" aria-controls="addProjectCollapse">
                        Create New Project
                    </button>
                </div>
                
                <div class="<?php echo $is_form_collapsed; ?>" id="addProjectCollapse">
                    <div class="card card-body mb-4">
                        <h4 class="mt-2">New Project Details</h4>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="mt-3">
                            <input type="hidden" name="add_project" value="1">
                            <!-- Form fields -->
                            <div class="row">
                                <div class="col-md-8 mb-3"><label for="job_name" class="form-label">Project / Job Name</label><input type="text" class="form-control <?php echo isset($form_errors['job_name']) ? 'is-invalid' : ''; ?>" id="job_name" name="job_name" value="<?php echo htmlspecialchars($job_name); ?>" required><div class="invalid-feedback"><?php echo $form_errors['job_name'] ?? ''; ?></div></div>
                                <div class="col-md-4 mb-3"><label for="job_number" class="form-label">Job Number</label><input type="text" class="form-control" id="job_number" name="job_number" value="<?php echo htmlspecialchars($job_number); ?>"></div>
                            </div>
                            <div class="mb-3"><label for="customer_id" class="form-label">Customer</label><select class="form-select <?php echo isset($form_errors['customer_id']) ? 'is-invalid' : ''; ?>" id="customer_id" name="customer_id" required><option value="">-- Select a Customer --</option><?php foreach ($customers_list as $customer) { echo '<option value="' . $customer['id'] . '"' . ($customer_id == $customer['id'] ? ' selected' : '') . '>' . htmlspecialchars($customer['customer_name']) . '</option>'; } ?></select><div class="invalid-feedback"><?php echo $form_errors['customer_id'] ?? ''; ?></div></div>
                            <div class="mb-3"><label for="contact_person" class="form-label">Project Contact Person</label><input type="text" class="form-control" id="contact_person" name="contact_person" value="<?php echo htmlspecialchars($contact_person); ?>"></div>
                            <hr>
                            <div class="mb-3"><label for="street_1" class="form-label">Street Address</label><input type="text" class="form-control <?php echo isset($form_errors['street_1']) ? 'is-invalid' : ''; ?>" id="street_1" name="street_1" value="<?php echo htmlspecialchars($street_1); ?>" required><div class="invalid-feedback"><?php echo $form_errors['street_1'] ?? ''; ?></div></div>
                            <div class="mb-3"><label for="street_2" class="form-label">Address Line 2</label><input type="text" class="form-control" id="street_2" name="street_2" value="<?php echo htmlspecialchars($street_2); ?>"></div>
                            <div class="row">
                                <div class="col-md-6 mb-3"><label for="city" class="form-label">City</label><input type="text" class="form-control <?php echo isset($form_errors['city']) ? 'is-invalid' : ''; ?>" id="city" name="city" value="<?php echo htmlspecialchars($city); ?>" required><div class="invalid-feedback"><?php echo $form_errors['city'] ?? ''; ?></div></div>
                                <div class="col-md-3 mb-3"><label for="state" class="form-label">State</label><input type="text" class="form-control <?php echo isset($form_errors['state']) ? 'is-invalid' : ''; ?>" id="state" name="state" value="<?php echo htmlspecialchars($state); ?>" required maxlength="2"><div class="invalid-feedback"><?php echo $form_errors['state'] ?? ''; ?></div></div>
                                <div class="col-md-3 mb-3"><label for="zip" class="form-label">ZIP Code</label><input type="text" class="form-control <?php echo isset($form_errors['zip']) ? 'is-invalid' : ''; ?>" id="zip" name="zip" value="<?php echo htmlspecialchars($zip); ?>" required><div class="invalid-feedback"><?php echo $form_errors['zip'] ?? ''; ?></div></div>
                            </div>
                            <div class="mb-3"><label for="notes" class="form-label">Notes</label><textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($notes); ?></textarea></div>
                            <div class="d-grid"><button type="submit" class="btn btn-primary">Create Project</button></div>
                        </form>
                    </div>
                </div>

                <h4 class="mt-5">All Projects</h4>
                <div class="table-responsive">
                    <table id="projectsTable" class="table table-hover table-bordered w-100">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th><th>Job Name</th><th>Job Number</th><th>Customer</th><th>Status</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

    <!-- Modals for Edit, Delete, and Toast -->
    <?php include 'includes/project_modals.html'; ?>
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100"><div id="appToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true"><div class="toast-header"><strong class="me-auto" id="toastTitle"></strong><button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button></div><div class="toast-body" id="toastBody"></div></div></div>
    
    <!-- Scripts -->
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.min.js"></script>
        <script src="js/main.js"></script>
        <script src="js/projects.js"></script>
    
        <?php mysqli_close($link); ?>
    </body>
</html>
