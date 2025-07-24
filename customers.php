<?php
// Customers.php
session_start();

// Security Check: Allow CompanyAdmins (2) and ProjectManagers (3) to access this page.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["role_id"], [2, 3])) {
    header("location: index.php");
    exit;
}

require_once 'config/db_connect.php'; // Database connection

$company_id = $_SESSION["company_id"]; // Get the Company ID from the session

if (empty($company_id)) {
    // This should ideally not happen but is a safeguard.
    echo "Error: Your account is not associated with a company. Please contact an administrator.";
    exit;
}

// Initialize variables for the form
$customer_name = $contact_person = $contact_email = $contact_phone = "";
$address_line_1 = $address_line_2 = $city = $state_province = $postal_code = "";
$notes = "";
$form_errors = [];
$success_message = "";

// --- This section determines if the collapse is open or closed on page load ---
$is_form_collapsed = "collapse"; // "collapse" is the Bootstrap class to hide it
$is_aria_expanded = "false";


// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_customer'])) {

    // If the form was submitted, we assume the user wants to see it, especially if there are errors.
    $is_form_collapsed = ""; // Empty string removes the 'collapse' class, making it visible
    $is_aria_expanded = "true";

    // --- Retrieve and Sanitize Form Data ---
    $customer_name = trim($_POST['customer_name']);
    $contact_person = trim($_POST['contact_person']);
    $contact_email = trim($_POST['contact_email']);
    $contact_phone = trim($_POST['contact_phone']);
    $address_line_1 = trim($_POST['address_line_1']);
    $address_line_2 = trim($_POST['address_line_2']);
    $city = trim($_POST['city']);
    $state_province = trim($_POST['state_province']);
    $postal_code = trim($_POST['postal_code']);
    $notes = trim($_POST['notes']);

    // --- Server-side Validation ---
    if (empty($customer_name)) { $form_errors['customer_name'] = "Customer name is required."; }
    if (empty($address_line_1)) { $form_errors['address_line_1'] = "Address is required."; }
    if (empty($city)) { $form_errors['city'] = "City is required."; }
    if (empty($state_province)) { $form_errors['state_province'] = "State/Province is required."; }
    if (empty($postal_code)) { $form_errors['postal_code'] = "Postal/ZIP code is required."; }
    if (!empty($contact_email) && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $form_errors['contact_email'] = "Please enter a valid email address.";
    }

    // If there are no validation errors, insert into the database
    if (empty($form_errors)) {
        $sql = "INSERT INTO customers (company_id, customer_name, contact_person, contact_email, contact_phone, address_line_1, address_line_2, city, state_province, postal_code, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "issssssssss",
                $company_id,
                $customer_name,
                $contact_person,
                $contact_email,
                $contact_phone,
                $address_line_1,
                $address_line_2,
                $city,
                $state_province,
                $postal_code,
                $notes
            );

            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Customer '" . htmlspecialchars($customer_name) . "' has been added successfully!";
                // Clear form fields on success
                $customer_name = $contact_person = $contact_email = $contact_phone = "";
                $address_line_1 = $address_line_2 = $city = $state_province = $postal_code = "";
                $notes = "";
                // Collapse the form again on success
                $is_form_collapsed = "collapse";
                $is_aria_expanded = "false";
            } else {
                $form_errors['general'] = "Error: Could not add customer. Please try again. " . mysqli_error($link);
            }
            mysqli_stmt_close($stmt);
        } else {
             $form_errors['general'] = "Error: Could not prepare the statement. " . mysqli_error($link);
        }
    }
}

// Fetch company name for display
$company_name = "Your Company";
$sql_company = "SELECT company_name FROM companies WHERE id = ?";
if ($stmt_company = mysqli_prepare($link, $sql_company)) {
    mysqli_stmt_bind_param($stmt_company, "i", $param_company_id);
    $param_company_id = $company_id;
    if (mysqli_stmt_execute($stmt_company)) {
        mysqli_stmt_bind_result($stmt_company, $db_company_name);
        if (mysqli_stmt_fetch($stmt_company)) {
            $company_name = $db_company_name;
        }
    }
    mysqli_stmt_close($stmt_company);
}
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - PourDay App</title>

    <link href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-bootstrap@2.0.12/dist/css/modus-bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-icons@1.16.0/dist/modus-solid/fonts/modus-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5/css/dataTables.bootstrap5.min.css">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700&display=fallback"/>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'topbar_mobile.html'; ?>
    <?php include 'sidebar.html'; ?>

    <main class="page-content-wrapper">
        <div class="container mt-5">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Customer Management</h3>
                    <a href="dashboard.php" class="btn btn-light btn-sm">Back to Dashboard</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($form_errors['general'])): ?>
                        <div class="alert alert-danger" role="alert"><?php echo $form_errors['general']; ?></div>
                    <?php endif; ?>
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success" role="alert"><?php echo $success_message; ?></div>
                    <?php endif; ?>

                    <div class="d-grid gap-2 mb-4">
                        <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addCustomerCollapse" aria-expanded="<?php echo $is_aria_expanded; ?>" aria-controls="addCustomerCollapse">
                            Add New Customer
                        </button>
                    </div>

                    <div class="<?php echo $is_form_collapsed; ?>" id="addCustomerCollapse">
                        <div class="card card-body mb-4" style="border-top: 3px solid #0d6efd;">
                            <h4 class="mt-2">Add New Customer</h4>
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="mb-2">
                                <input type="hidden" name="add_customer" value="1">
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="customer_name" class="form-label">Customer Name</label>
                                        <input type="text" class="form-control <?php echo isset($form_errors['customer_name']) ? 'is-invalid' : ''; ?>" id="customer_name" name="customer_name" value="<?php echo htmlspecialchars($customer_name); ?>" required>
                                        <div class="invalid-feedback"><?php echo $form_errors['customer_name'] ?? ''; ?></div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="contact_person" class="form-label">Contact Person</label>
                                        <input type="text" class="form-control" id="contact_person" name="contact_person" value="<?php echo htmlspecialchars($contact_person); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="contact_phone" class="form-label">Contact Phone</label>
                                        <input type="tel" class="form-control" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars($contact_phone); ?>">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="contact_email" class="form-label">Contact Email</label>
                                    <input type="email" class="form-control <?php echo isset($form_errors['contact_email']) ? 'is-invalid' : ''; ?>" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars($contact_email); ?>">
                                    <div class="invalid-feedback"><?php echo $form_errors['contact_email'] ?? ''; ?></div>
                                </div>
                                  <hr>
                                <div class="mb-3">
                                    <label for="address_line_1" class="form-label">Address Line 1</label>
                                    <input type="text" class="form-control <?php echo isset($form_errors['address_line_1']) ? 'is-invalid' : ''; ?>" id="address_line_1" name="address_line_1" value="<?php echo htmlspecialchars($address_line_1); ?>" required>
                                    <div class="invalid-feedback"><?php echo $form_errors['address_line_1'] ?? ''; ?></div>
                                </div>
                                <div class="mb-3">
                                    <label for="address_line_2" class="form-label">Address Line 2 (Apt, Suite, etc.)</label>
                                    <input type="text" class="form-control" id="address_line_2" name="address_line_2" value="<?php echo htmlspecialchars($address_line_2); ?>">
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="city" class="form-label">City</label>
                                        <input type="text" class="form-control <?php echo isset($form_errors['city']) ? 'is-invalid' : ''; ?>" id="city" name="city" value="<?php echo htmlspecialchars($city); ?>" required>
                                        <div class="invalid-feedback"><?php echo $form_errors['city'] ?? ''; ?></div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="state_province" class="form-label">State / Province</label>
                                        <input type="text" class="form-control <?php echo isset($form_errors['state_province']) ? 'is-invalid' : ''; ?>" id="state_province" name="state_province" value="<?php echo htmlspecialchars($state_province); ?>" required>
                                        <div class="invalid-feedback"><?php echo $form_errors['state_province'] ?? ''; ?></div>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <label for="postal_code" class="form-label">Postal Code</label>
                                        <input type="text" class="form-control <?php echo isset($form_errors['postal_code']) ? 'is-invalid' : ''; ?>" id="postal_code" name="postal_code" value="<?php echo htmlspecialchars($postal_code); ?>" required>
                                        <div class="invalid-feedback"><?php echo $form_errors['postal_code'] ?? ''; ?></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="notes" class="form-label">Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($notes); ?></textarea>
                                </div>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">Add Customer</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <h4 class="mt-5">Your Customers</h4>
                    <div class="table-responsive">
                        <table id="customersTable" class="table table-hover table-bordered w-100">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Customer Name</th>
                                    <th>Contact Person</th>
                                    <th>Contact Phone</th>
                                    <th>City</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <div class="modal fade" id="editCustomerModal" tabindex="-1" aria-labelledby="editCustomerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editCustomerModalLabel">Edit Customer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editCustomerForm" onsubmit="return false;">
                        <input type="hidden" id="editCustomerId" name="id">

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="editCustomerName" class="form-label">Customer Name</label>
                                <input type="text" class="form-control" id="editCustomerName" name="customer_name" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editContactPerson" class="form-label">Contact Person</label>
                                <input type="text" class="form-control" id="editContactPerson" name="contact_person">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editContactPhone" class="form-label">Contact Phone</label>
                                <input type="tel" class="form-control" id="editContactPhone" name="contact_phone">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editContactEmail" class="form-label">Contact Email</label>
                            <input type="email" class="form-control" id="editContactEmail" name="contact_email">
                        </div>
                        <hr>
                        <div class="mb-3">
                            <label for="editAddressLine1" class="form-label">Address Line 1</label>
                            <input type="text" class="form-control" id="editAddressLine1" name="address_line_1" required>
                        </div>
                        <div class="mb-3">
                            <label for="editAddressLine2" class="form-label">Address Line 2</label>
                            <input type="text" class="form-control" id="editAddressLine2" name="address_line_2">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editCity" class="form-label">City</label>
                                <input type="text" class="form-control" id="editCity" name="city" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="editStateProvince" class="form-label">State / Province</label>
                                <input type="text" class="form-control" id="editStateProvince" name="state_province" required>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label for="editPostalCode" class="form-label">Postal Code</label>
                                <input type="text" class="form-control" id="editPostalCode" name="postal_code" required>
                            </div>
                        </div>
                          <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editStatus" class="form-label">Status</label>
                                <select class="form-select" id="editStatus" name="status" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editNotes" class="form-label">Notes</label>
                            <textarea class="form-control" id="editNotes" name="notes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveCustomerChangesBtn">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteConfirmModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete Customer</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="js/main.js"></script>
    <script src="js/customers.js"></script>

    <?php
    mysqli_close($link);
    ?>
</body>
</html>
