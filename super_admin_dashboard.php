<?php
session_start();

// Security Check: Only allow Super Admin (role_id = 1)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role_id"] !== 1) {
    header("location: index.php"); // Redirect to login or a "not authorized" page
    exit;
}

require_once 'config/db_connect.php'; // Ensure you have your DB connection file
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard</title>

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
        <div class="container-fluid mt-5">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Super Admin Dashboard</h3>
                </div>
                <div class="card-body">

                    <ul class="nav nav-tabs" id="adminTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="companies-tab" data-bs-toggle="tab" data-bs-target="#companies-tab-pane" type="button" role="tab" aria-controls="companies-tab-pane" aria-selected="true">Manage Companies</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users-tab-pane" type="button" role="tab" aria-controls="users-tab-pane" aria-selected="false">Manage Users</button>
                        </li>
                    </ul>

                    <div class="tab-content" id="adminTabContent">
                        <div class="tab-pane fade show active" id="companies-tab-pane" role="tabpanel" aria-labelledby="companies-tab" tabindex="0">
                            <div class="p-3">
                                <h4 class="mt-3">All Companies</h4>
                                <div class="table-responsive">
                                    <table id="companiesTable" class="table table-hover table-bordered w-100">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Company Name</th>
                                                <th>Contact Name</th>
                                                <th>Contact Email</th>
                                                <th>Active</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="users-tab-pane" role="tabpanel" aria-labelledby="users-tab" tabindex="0">
                           <div class="p-3">
                                <h4 class="mt-3">All Users</h4>
                                <div class="table-responsive">
                                    <table id="usersTable" class="table table-hover table-bordered w-100">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Username</th>
                                                <th>Email</th>
                                                <th>Full Name</th>
                                                <th>Company</th>
                                                <th>Role</th>
                                                <th>Active</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="editCompanyModal" tabindex="-1" aria-labelledby="editCompanyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editCompanyModalLabel">Edit Company</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editCompanyForm" onsubmit="return false;">
                        <input type="hidden" id="editCompanyId" name="id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_company_name" class="form-label">Company Name</label>
                                <input type="text" class="form-control" id="edit_company_name" name="company_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_contact_person_name" class="form-label">Contact Person</label>
                                <input type="text" class="form-control" id="edit_contact_person_name" name="contact_person_name">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_contact_email" class="form-label">Contact Email</label>
                                <input type="email" class="form-control" id="edit_contact_email" name="contact_email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_contact_phone" class="form-label">Contact Phone</label>
                                <input type="tel" class="form-control" id="edit_contact_phone" name="contact_phone">
                            </div>
                        </div>
                        <div class="mb-3">
                             <label for="edit_address" class="form-label">Address</label>
                             <textarea class="form-control" id="edit_address" name="address" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-5 mb-3">
                                <label for="edit_city" class="form-label">City</label>
                                <input type="text" class="form-control" id="edit_city" name="city">
                            </div>
                             <div class="col-md-4 mb-3">
                                <label for="edit_state" class="form-label">State</label>
                                <input type="text" class="form-control" id="edit_state" name="state">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="edit_zip_code" class="form-label">Zip Code</label>
                                <input type="text" class="form-control" id="edit_zip_code" name="zip_code">
                            </div>
                        </div>
                        <div class="form-check form-switch mb-3">
                          <input class="form-check-input" type="checkbox" role="switch" id="edit_company_is_active" name="is_active">
                          <label class="form-check-label" for="edit_company_is_active">Company is Active</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveCompanyChangesBtn">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editUserForm" onsubmit="return false;">
                        <input type="hidden" id="editUserId" name="id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="edit_username" name="username" required>
                            </div>
                             <div class="col-md-6 mb-3">
                                <label for="edit_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="edit_email" name="email" required>
                            </div>
                        </div>
                         <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="edit_first_name" name="first_name">
                            </div>
                             <div class="col-md-6 mb-3">
                                <label for="edit_last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="edit_last_name" name="last_name">
                            </div>
                        </div>
                        <div class="row">
                             <div class="col-md-6 mb-3">
                                <label for="edit_company_id" class="form-label">Company</label>
                                <select class="form-select" id="edit_company_id" name="company_id"></select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_role_id" class="form-label">Role</label>
                                <select class="form-select" id="edit_role_id" name="role_id" required>
                                    </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">New Password (optional)</label>
                            <input type="password" class="form-control" id="edit_password" name="password" aria-describedby="passwordHelp">
                            <div id="passwordHelp" class="form-text">Leave blank to keep the current password.</div>
                        </div>
                        <div class="form-check form-switch mb-3">
                          <input class="form-check-input" type="checkbox" role="switch" id="edit_user_is_active" name="is_active">
                          <label class="form-check-label" for="edit_user_is_active">User is Active</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveUserChangesBtn">Save Changes</button>
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
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="appToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto" id="toastTitle"></strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="toastBody"></div>
        </div>
    </div>


    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="js/main.js"></script> <script src="js/admin_page.js"></script> </body>
</html>
