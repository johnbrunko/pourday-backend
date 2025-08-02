<?php
// contacts.php
session_start();

// Security Check: Allow CompanyAdmins (2) and ProjectManagers (3)
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

// Fetch the list of customers for the dropdown menu
$customers = [];
$sql_customers = "SELECT id, customer_name FROM customers WHERE company_id = ? AND status = 'active' ORDER BY customer_name ASC";
if ($stmt_customers = mysqli_prepare($link, $sql_customers)) {
    mysqli_stmt_bind_param($stmt_customers, "i", $company_id);
    if (mysqli_stmt_execute($stmt_customers)) {
        $result = mysqli_stmt_get_result($stmt_customers);
        while ($row = mysqli_fetch_assoc($result)) {
            $customers[] = $row;
        }
    }
    mysqli_stmt_close($stmt_customers);
}
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Management - PourDay App</title>

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
        <div class="container mt-5">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Contact Management</h3>
                    <a href="dashboard.php" class="btn btn-light btn-sm">Back to Dashboard</a>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2 mb-4">
                        <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addContactCollapse" aria-expanded="false" aria-controls="addContactCollapse">
                            Add New Contact
                        </button>
                    </div>

                    <div class="collapse" id="addContactCollapse">
                        <div class="card card-body mb-4" style="border-top: 3px solid #0d6efd;">
                            <h4 class="mt-2">Add New Contact</h4>
                            <form id="addContactForm" onsubmit="return false;" class="mb-2">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="addFirstName" class="form-label">First Name</label>
                                        <input type="text" class="form-control" id="addFirstName" name="first_name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="addLastName" class="form-label">Last Name</label>
                                        <input type="text" class="form-control" id="addLastName" name="last_name" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="addEmail" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="addEmail" name="email">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="addPhone" class="form-label">Phone</label>
                                        <input type="tel" class="form-control" id="addPhone" name="phone">
                                    </div>
                                </div>
                                <div class="row">
                                     <div class="col-md-6 mb-3">
                                        <label for="addTitle" class="form-label">Title</label>
                                        <input type="text" class="form-control" id="addTitle" name="title">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="addCustomerId" class="form-label">Associated Customer</label>
                                        <select class="form-select" id="addCustomerId" name="customer_id">
                                            <option value="">None</option>
                                            <?php foreach ($customers as $customer): ?>
                                                <option value="<?php echo $customer['id']; ?>"><?php echo htmlspecialchars($customer['customer_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="addNotes" class="form-label">Notes</label>
                                    <textarea class="form-control" id="addNotes" name="notes" rows="3"></textarea>
                                </div>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary" id="saveNewContactBtn">Add Contact</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <h4 class="mt-5">Your Contacts</h4>
                    <div class="table-responsive">
                        <table id="contactsTable" class="table table-hover table-bordered w-100">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Customer</th>
                                    <th>Email</th>
                                    <th>Phone</th>
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

    <!-- Edit Contact Modal -->
    <div class="modal fade" id="editContactModal" tabindex="-1" aria-labelledby="editContactModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editContactModalLabel">Edit Contact</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editContactForm" onsubmit="return false;">
                        <input type="hidden" id="editContactId" name="id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editFirstName" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="editFirstName" name="first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editLastName" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="editLastName" name="last_name" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editEmail" class="form-label">Email</label>
                                <input type="email" class="form-control" id="editEmail" name="email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editPhone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="editPhone" name="phone">
                            </div>
                        </div>
                        <div class="row">
                             <div class="col-md-6 mb-3">
                                <label for="editTitle" class="form-label">Title</label>
                                <input type="text" class="form-control" id="editTitle" name="title">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editCustomerId" class="form-label">Associated Customer</label>
                                <select class="form-select" id="editCustomerId" name="customer_id">
                                    <option value="">None</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?php echo $customer['id']; ?>"><?php echo htmlspecialchars($customer['customer_name']); ?></option>
                                    <?php endforeach; ?>
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
                    <button type="button" class="btn btn-primary" id="saveContactChangesBtn">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteConfirmModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete Contact</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Toast container for notifications -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100">
        <div id="appToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true"><div class="toast-header"><strong class="me-auto" id="toastTitle"></strong><button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button></div><div class="toast-body" id="toastBody"></div></div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="js/main.js"></script>
    <script src="js/contacts.js"></script>

    <?php
    mysqli_close($link);
    ?>
</body>
</html>