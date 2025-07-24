<?php
// Initialize the session
session_start();

// Check if the user is logged in, and if they are a SuperAdmin (role_id = 1)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role_id"] !== 1) {
    header("location: index.php"); // Redirect to login if not SuperAdmin
    exit;
}

require_once 'config/db_connect.php'; // Database connection

$company_name = $contact_person = $contact_email = $contact_phone = $address = $city = $state = $zip_code = $country = "";
$company_name_err = $contact_email_err = $general_err = "";
$success_message = "";

// --- ADDED: Check for a success message from the session after a redirect ---
if (isset($_SESSION['form_success_message'])) {
    $success_message = $_SESSION['form_success_message'];
    // Clear the message from the session so it doesn't show again on the next refresh
    unset($_SESSION['form_success_message']);
}

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate company name
    if (empty(trim($_POST["company_name"]))) {
        $company_name_err = "Please enter a company name.";
    } else {
        // Check if company name already exists
        $sql = "SELECT id FROM companies WHERE company_name = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $param_company_name);
            $param_company_name = trim($_POST["company_name"]);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    $company_name_err = "This company name is already registered.";
                } else {
                    $company_name = trim($_POST["company_name"]);
                }
            } else {
                $general_err = "Oops! Something went wrong. Please try again later.";
            }
            mysqli_stmt_close($stmt);
        }
    }

    // Validate contact email (optional, but good practice for uniqueness)
    if (!empty(trim($_POST["contact_email"]))) {
        $sql = "SELECT id FROM companies WHERE contact_email = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $param_contact_email);
            $param_contact_email = trim($_POST["contact_email"]);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    $contact_email_err = "This contact email is already used by another company.";
                } else {
                    $contact_email = trim($_POST["contact_email"]);
                }
            } else {
                $general_err = "Oops! Something went wrong with email check. Please try again later.";
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        $contact_email = ""; // Allow empty
    }

    // Populate other fields
    $contact_person = trim($_POST["contact_person_name"]);
    $contact_phone = trim($_POST["contact_phone"]);
    $address = trim($_POST["address"]);
    $city = trim($_POST["city"]);
    $state = trim($_POST["state"]);
    $zip_code = trim($_POST["zip_code"]);
    $country = trim($_POST["country"]);


    // Check input errors before inserting in database
    if (empty($company_name_err) && empty($contact_email_err) && empty($general_err)) {
        // Prepare an insert statement
        $sql = "INSERT INTO companies (company_name, contact_person_name, contact_email, contact_phone, address, city, state, zip_code, country, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssssssssi",
                $param_company_name, $param_contact_person, $param_contact_email, $param_contact_phone,
                $param_address, $param_city, $param_state, $param_zip_code, $param_country, $param_is_active
            );

            // Set parameters
            $param_company_name = $company_name;
            $param_contact_person = $contact_person;
            $param_contact_email = $contact_email;
            $param_contact_phone = $contact_phone;
            $param_address = $address;
            $param_city = $city;
            $param_state = $state;
            $param_zip_code = $zip_code;
            $param_country = $country;
            $param_is_active = 1; // New companies are active by default

            // --- MODIFIED: Implemented Post/Redirect/Get pattern ---
            if (mysqli_stmt_execute($stmt)) {
                // 1. Set the success message in the session
                $_SESSION['form_success_message'] = "Company **" . htmlspecialchars($company_name) . "** registered successfully!";
                
                // 2. Redirect to the same page to clear the POST data
                header("location: " . htmlspecialchars($_SERVER["PHP_SELF"]));
                exit(); // 3. Stop script execution
                
            } else {
                $general_err = "Error: Could not register company. " . mysqli_error($link);
            }
            mysqli_stmt_close($stmt);
        }
    }
    // Note: If there are validation errors, the script will continue and display them below,
    // which is the correct behavior. The redirect only happens on success.
}

// Fetch existing companies to display in a table
$companies = [];
$sql_fetch = "SELECT id, company_name, contact_person_name, contact_email, is_active FROM companies ORDER BY company_name ASC";
if ($result = mysqli_query($link, $sql_fetch)) {
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $companies[] = $row;
        }
        mysqli_free_result($result);
    }
} else {
    $general_err = "Error: Could not retrieve companies. " . mysqli_error($link);
}

// Close connection
mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Concrete Pour App - Manage Companies</title>

    <link rel="stylesheet" href="node_modules/@trimble-oss/modus-bootstrap/dist/css/modus-bootstrap.min.css">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700&display=fallback"/>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <?php include 'topbar_mobile.html'; ?>
    <?php include 'sidebar.html'; ?>
    
    <div class="container mt-5">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h3 class="mb-0">Manage Companies (SuperAdmin)</h3>
                <a href="dashboard.php" class="btn btn-light btn-sm">Back to Dashboard</a>
            </div>
            <div class="card-body">
                <?php if (!empty($general_err)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $general_err; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success" role="alert">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <h4>Add New Company</h4>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="mb-4">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="company_name" class="form-label">Company Name</label>
                            <input type="text" class="form-control <?php echo (!empty($company_name_err)) ? 'is-invalid' : ''; ?>" id="company_name" name="company_name" value="<?php echo htmlspecialchars($company_name); ?>" required>
                            <div class="invalid-feedback"><?php echo $company_name_err; ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="contact_person_name" class="form-label">Contact Person</label>
                            <input type="text" class="form-control" id="contact_person_name" name="contact_person_name" value="<?php echo htmlspecialchars($contact_person); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="contact_email" class="form-label">Contact Email</label>
                            <input type="email" class="form-control <?php echo (!empty($contact_email_err)) ? 'is-invalid' : ''; ?>" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars($contact_email); ?>">
                            <div class="invalid-feedback"><?php echo $contact_email_err; ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="contact_phone" class="form-label">Contact Phone</label>
                            <input type="text" class="form-control" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars($contact_phone); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($address); ?>">
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="city" class="form-label">City</label>
                            <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($city); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="state" class="form-label">State/Province</label>
                            <input type="text" class="form-control" id="state" name="state" value="<?php echo htmlspecialchars($state); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="zip_code" class="form-label">Zip/Postal Code</label>
                            <input type="text" class="form-control" id="zip_code" name="zip_code" value="<?php echo htmlspecialchars($zip_code); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="country" class="form-label">Country</label>
                        <input type="text" class="form-control" id="country" name="country" value="<?php echo htmlspecialchars($country); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Add Company</button>
                </form>

                <h4 class="mt-5">Existing Companies</h4>
                <?php if (empty($companies)): ?>
                    <p>No companies registered yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Company Name</th>
                                    <th>Contact Person</th>
                                    <th>Contact Email</th>
                                    <th>Active</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($companies as $company): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($company['id']); ?></td>
                                        <td><?php echo htmlspecialchars($company['company_name']); ?></td>
                                        <td><?php echo htmlspecialchars($company['contact_person_name']); ?></td>
                                        <td><?php echo htmlspecialchars($company['contact_email']); ?></td>
                                        <td>
                                            <?php if ($company['is_active']): ?>
                                                <span class="badge bg-success">Yes</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">No</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="#" class="btn btn-sm btn-info me-1">Edit</a>
                                            <a href="#" class="btn btn-sm btn-warning">Deactivate</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="node_modules/@trimble-oss/modus-bootstrap/dist/js/modus-bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>

</body>
</html>