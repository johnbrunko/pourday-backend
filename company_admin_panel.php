<?php
//company_admin_panel.php
// Initialize the session
session_start();

// Check if the user is logged in, and if they are a CompanyAdmin (role_id = 2)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role_id"] !== 2) {
    header("location: index.php");
    exit;
}

// Require necessary files
require_once 'includes/functions.php';
require_once 'config/db_connect.php';

$company_id = $_SESSION["company_id"];

if (empty($company_id)) {
    echo "Error: Your account is not associated with a company. Please contact SuperAdmin.";
    exit;
}

// Define variables for new user registration form
$username = $email = $password = $confirm_password = $first_name = $last_name = $phone_number = $role_selection = "";
$username_err = $email_err = $password_err = $confirm_password_err = $role_selection_err = "";
$success_message = "";
$general_err = "";

// --- ADDED: Check for success/error messages from the session after a redirect ---
if (isset($_SESSION['form_success_message'])) {
    $success_message = $_SESSION['form_success_message'];
    unset($_SESSION['form_success_message']); // Clear the message
}
if (isset($_SESSION['form_general_err'])) {
    $general_err = $_SESSION['form_general_err'];
    unset($_SESSION['form_general_err']); // Clear the message
}

// Define variables for Company Settings form
$company_name_val = $contact_person_name_val = $contact_email_val = $contact_phone_val = $address_val = $city_val = $state_val = $zip_code_val = $country_val = $logo_val = "";
$company_name_err = $contact_email_err = $general_company_settings_err = "";
$company_settings_success_message = "";

// --- ADDED: Check for success/error messages for Company Settings from the session after a redirect ---
if (isset($_SESSION['company_settings_success_message'])) {
    $company_settings_success_message = $_SESSION['company_settings_success_message'];
    unset($_SESSION['company_settings_success_message']); // Clear the message
}
if (isset($_SESSION['company_settings_general_err'])) {
    $general_company_settings_err = $_SESSION['company_settings_general_err'];
    unset($_SESSION['company_settings_general_err']); // Clear the message
}

// --- Fetch Company Data for pre-populating the form ---
$company_data = []; // Initialize an empty array
$sql_fetch_company_data = "SELECT company_name, contact_person_name, contact_email, contact_phone, address, city, state, zip_code, country, logo FROM companies WHERE id = ?";
if ($stmt_fetch = mysqli_prepare($link, $sql_fetch_company_data)) {
    mysqli_stmt_bind_param($stmt_fetch, "i", $param_company_id_fetch);
    $param_company_id_fetch = $company_id;
    if (mysqli_stmt_execute($stmt_fetch)) {
        mysqli_stmt_bind_result($stmt_fetch,
            $company_data['company_name'],
            $company_data['contact_person_name'],
            $company_data['contact_email'],
            $company_data['contact_phone'],
            $company_data['address'],
            $company_data['city'],
            $company_data['state'],
            $company_data['zip_code'],
            $company_data['country'],
            $company_data['logo']
        );
        mysqli_stmt_fetch($stmt_fetch); // Fetch the result into the bound variables
    } else {
        $general_err = "Oops! Something went wrong while fetching company data.";
    }
    mysqli_stmt_close($stmt_fetch);
}

// Populate form variables with fetched data (or empty string if not set)
$company_name_val = $company_data['company_name'] ?? '';
$contact_person_name_val = $company_data['contact_person_name'] ?? '';
$contact_email_val = $company_data['contact_email'] ?? '';
$contact_phone_val = $company_data['contact_phone'] ?? '';
$address_val = $company_data['address'] ?? '';
$city_val = $company_data['city'] ?? '';
$state_val = $company_data['state'] ?? '';
$zip_code_val = $company_data['zip_code'] ?? '';
$country_val = $company_data['country'] ?? '';
$logo_val = $company_data['logo'] ?? '';


// --- ADDED: Processing Company Settings form data when form is submitted ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_company_settings'])) {

    // Validate company_name (required)
    if (empty(trim($_POST["company_name"]))) {
        $company_name_err = "Please enter a company name.";
    } else {
        $company_name_val = trim($_POST["company_name"]);
    }

    // Validate contact_email (optional, but if provided, must be valid format)
    if (!empty(trim($_POST["contact_email"]))) {
        if (!filter_var(trim($_POST["contact_email"]), FILTER_VALIDATE_EMAIL)) {
            $contact_email_err = "Please enter a valid email format.";
        } else {
            $contact_email_val = trim($_POST["contact_email"]);
        }
    } else {
        $contact_email_val = ""; // Ensure it's empty if not provided
    }

    // Assign other nullable fields directly, converting empty strings to NULL if needed
    $contact_person_name_val = (!empty(trim($_POST["contact_person_name"]))) ? trim($_POST["contact_person_name"]) : null;
    $contact_phone_val       = (!empty(trim($_POST["contact_phone"]))) ? trim($_POST["contact_phone"]) : null;
    $address_val             = (!empty(trim($_POST["address"]))) ? trim($_POST["address"]) : null;
    $city_val                = (!empty(trim($_POST["city"]))) ? trim($_POST["city"]) : null;
    $state_val               = (!empty(trim($_POST["state"]))) ? trim($_POST["state"]) : null;
    $zip_code_val            = (!empty(trim($_POST["zip_code"]))) ? trim($_POST["zip_code"]) : null;
    $country_val             = (!empty(trim($_POST["country"]))) ? trim($_POST["country"]) : null;
    $logo_val = $company_data['logo'] ?? null; // Start with current logo path from DB

    // --- Logo Upload Handling ---
    if (isset($_FILES["new_logo"]) && $_FILES["new_logo"]["error"] == UPLOAD_ERR_OK) {
        $target_dir = "uploads/logos/"; // Ensure this directory exists and is writable!
        $file_name = basename($_FILES["new_logo"]["name"]);
        $imageFileType = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $new_file_name = uniqid('logo_', true) . "." . $imageFileType; // Generate unique name
        $target_file = $target_dir . $new_file_name;

        // Basic file validation
        $check = getimagesize($_FILES["new_logo"]["tmp_name"]);
        if ($check === false) {
            $general_company_settings_err = "File is not an image.";
        } else if ($_FILES["new_logo"]["size"] > 500000) { // Max 500KB
            $general_company_settings_err = "Sorry, your file is too large (max 500KB).";
        } else if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
            $general_company_settings_err = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        }

        // If no upload errors so far, attempt to move the file
        if (empty($general_company_settings_err)) {
            if (move_uploaded_file($_FILES["new_logo"]["tmp_name"], $target_file)) {
                // Successfully uploaded new logo, update logo_val to new path
                $logo_val = $target_file;

                // OPTIONAL: Delete old logo if it exists and is different
                if (!empty($company_data['logo']) && $company_data['logo'] !== $logo_val && file_exists($company_data['logo'])) {
                    unlink($company_data['logo']); // Delete old file
                }
            } else {
                $general_company_settings_err = "Sorry, there was an error uploading your file.";
            }
        }
    } else if (isset($_POST['remove_logo']) && $_POST['remove_logo'] == '1') {
        // Handle explicit logo removal
        if (!empty($company_data['logo']) && file_exists($company_data['logo'])) {
            unlink($company_data['logo']); // Delete existing file
        }
        $logo_val = null; // Set logo to NULL in DB
    }


    // Check input errors before updating in database
    if (empty($company_name_err) && empty($contact_email_err) && empty($general_company_settings_err)) {

        $sql_update_company = "UPDATE companies SET 
                                company_name = ?, 
                                contact_person_name = ?, 
                                contact_email = ?, 
                                contact_phone = ?, 
                                address = ?, 
                                city = ?, 
                                state = ?, 
                                zip_code = ?, 
                                country = ?, 
                                logo = ? 
                                WHERE id = ?";

        if ($stmt_update = mysqli_prepare($link, $sql_update_company)) {
            // "ssssssssssi" -> 10 string params, 1 integer param
            mysqli_stmt_bind_param($stmt_update, "ssssssssssi",
                $company_name_val,
                $contact_person_name_val,
                $contact_email_val,
                $contact_phone_val,
                $address_val,
                $city_val,
                $state_val,
                $zip_code_val,
                $country_val,
                $logo_val,
                $company_id // The WHERE clause parameter
            );

            if (mysqli_stmt_execute($stmt_update)) {
                $_SESSION['company_settings_success_message'] = "Company settings updated successfully!";
                header("location: " . htmlspecialchars($_SERVER["PHP_SELF"]));
                exit();
            } else {
                $_SESSION['company_settings_general_err'] = "Error: Could not update company settings. " . mysqli_error($link);
                header("location: " . htmlspecialchars($_SERVER["PHP_SELF"])); // Redirect even on error to display message
                exit();
            }
            mysqli_stmt_close($stmt_update);
        } else {
            $_SESSION['company_settings_general_err'] = "Error: Could not prepare update statement.";
            header("location: " . htmlspecialchars($_SERVER["PHP_SELF"])); // Redirect even on error to display message
            exit();
        }
    }
}


// This section determines if the collapse is open or closed on page load
$is_form_collapsed = "collapse"; 
$is_aria_expanded = "false";

// Processing new user form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_user'])) {
    
    // If the form was submitted, expand it to show errors if they exist.
    $is_form_collapsed = ""; 
    $is_aria_expanded = "true";

    // --- Validation logic (unchanged) ---
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } else {
        $sql = "SELECT id FROM users WHERE username = ? AND company_id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "si", $param_username, $param_company_id);
            $param_username = trim($_POST["username"]);
            $param_company_id = $company_id;
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    $username_err = "This username is already taken within your company.";
                } else {
                    $username = trim($_POST["username"]);
                }
            } else {
                $general_err = "Oops! Something went wrong with username check.";
            }
            mysqli_stmt_close($stmt);
        }
    }

    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email.";
    } else {
        $sql = "SELECT id FROM users WHERE email = ? AND company_id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "si", $param_email, $param_company_id);
            $param_email = trim($_POST["email"]);
            $param_company_id = $company_id;
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    $email_err = "This email is already registered within your company.";
                } else {
                    $email = trim($_POST["email"]);
                }
            } else {
                $general_err = "Oops! Something went wrong with email check.";
            }
            mysqli_stmt_close($stmt);
        }
    }

    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }

    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }

    if (empty(trim($_POST["role_selection"]))) {
        $role_selection_err = "Please select a role.";
    } else {
        $role_selection = (int)trim($_POST["role_selection"]);
        if ($role_selection !== 3 && $role_selection !== 4) {
            $role_selection_err = "Invalid role selected.";
        }
    }

    $first_name = trim($_POST["first_name"]);
    $last_name = trim($_POST["last_name"]);
    $phone_number = trim($_POST["phone_number"]);

    // --- Check input errors before inserting in database ---
    if (empty($username_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err) && empty($role_selection_err) && empty($general_err)) {

        $sql = "INSERT INTO users (username, email, password, first_name, last_name, phone_number, company_id, role_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssssssiis",
                $param_username, $param_email, $param_password_hash, $param_first_name, $param_last_name,
                $param_phone_number, $param_company_id, $param_role_id, $param_is_active
            );

            $param_username = $username;
            $param_email = $email;
            $param_password_hash = password_hash($password, PASSWORD_DEFAULT);
            $param_first_name = $first_name;
            $param_last_name = $last_name;
            $param_phone_number = $phone_number;
            $param_company_id = $company_id;
            $param_role_id = $role_selection;
            $param_is_active = 1;

            // --- MODIFIED: Implemented Post/Redirect/Get pattern ---
            if (mysqli_stmt_execute($stmt)) {
                
                $temp_success_message = "User **" . htmlspecialchars($username) . "** registered successfully!";
                
                // --- EMAIL SENDING LOGIC ---
                $email_subject = "Welcome to the PourDay App!";
                $email_body_html = "
                    <html>
                    <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
                        <h2>Welcome, " . htmlspecialchars($first_name) . "!</h2>
                        <p>An account has been created for you on the PourDay application.</p>
                        <p>Here are your login details:</p>
                        <ul>
                            <li><strong>Username:</strong> " . htmlspecialchars($username) . "</li>
                            <li><strong>Password:</strong> " . htmlspecialchars($password) . " <i>(The password you were registered with)</i></li>
                        </ul>
                        <p>You can log in at: <a href='https://pourday.tech/index.php'>https://pourday.tech/index.php</a></p>
                        <p>Thank you!</p>
                    </body>
                    </html>
                ";

                if (send_email($email, $email_subject, $email_body_html)) {
                    $temp_success_message .= " A welcome email has been sent.";
                } else {
                    // Set an error message if email fails
                    $_SESSION['form_general_err'] = "Note: The user was created, but the welcome email could not be sent. Please check email configurations.";
                }
                
                // 1. Set the final success message in the session
                $_SESSION['form_success_message'] = $temp_success_message;

                // 2. Redirect to the same page to clear the POST data
                header("location: " . htmlspecialchars($_SERVER["PHP_SELF"]));
                exit(); // 3. Stop script execution

            } else {
                $general_err = "Error: Could not register user. " . mysqli_error($link);
            }
            mysqli_stmt_close($stmt);
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
    <title>Company Admin Panel - PourDay App</title>

    <!-- Using CDN for stylesheets for reliability -->
    <link href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-bootstrap@2.0.12/dist/css/modus-bootstrap.min.css" rel="stylesheet">
    <!-- FIXED: Added missing Modus Icons stylesheet -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-icons@1.16.0/dist/modus-solid/fonts/modus-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
    
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700&display=fallback"/>
    <link rel="stylesheet" href="css/style.css"> <!-- Your custom styles -->
</head>
<body>
    <!-- FIXED: Added missing mobile top bar and sidebar -->
    <?php include 'topbar_mobile.html'; ?>
    <?php include 'sidebar.html'; ?>

    <!-- FIXED: Added main content wrapper for correct layout -->
    <main class="page-content-wrapper">
        <div class="container-fluid p-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Company Admin Panel: <?php echo htmlspecialchars($company_name); ?></h3>
                    <a href="dashboard.php" class="btn btn-light btn-sm">Back to Dashboard</a>
                </div>
                <div class="card-body">
                    
                    <?php if (!empty($general_err)): ?>
                        <div class="alert alert-danger" role="alert"><?php echo $general_err; ?></div>
                    <?php endif; ?>
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success" role="alert"><?php echo $success_message; ?></div>
                    <?php endif; ?>

       <div class="card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Company Info</h4>
                <button class="btn btn-light btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#companySettingsCollapse" aria-expanded="<?php echo (!empty($company_settings_success_message) || !empty($general_company_settings_err)) ? 'true' : 'false'; ?>" aria-controls="companySettingsCollapse">
                    <span class="mi mi-pencil-solid me-2"></span>Edit/View Company Info </button>
            </div>

            <div class="collapse <?php echo (!empty($company_settings_success_message) || !empty($general_company_settings_err)) ? 'show' : ''; ?>" id="companySettingsCollapse">
                <div class="card-body">
                    <?php if (!empty($general_company_settings_err)): ?>
                        <div class="alert alert-danger" role="alert"><?php echo $general_company_settings_err; ?></div>
                    <?php endif; ?>
                    <?php if (!empty($company_settings_success_message)): ?>
                        <div class="alert alert-success" role="alert"><?php echo $company_settings_success_message; ?></div>
                    <?php endif; ?>

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="update_company_settings" value="1">

                        <div class="mb-3">
                            <label for="company_name" class="form-label">Company Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?php echo (!empty($company_name_err)) ? 'is-invalid' : ''; ?>" id="company_name" name="company_name" value="<?php echo htmlspecialchars($company_name_val); ?>" required>
                            <div class="invalid-feedback"><?php echo $company_name_err; ?></div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="contact_person_name" class="form-label">Contact Person Name</label>
                                <input type="text" class="form-control" id="contact_person_name" name="contact_person_name" value="<?php echo htmlspecialchars($contact_person_name_val); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="contact_email" class="form-label">Contact Email</label>
                                <input type="email" class="form-control <?php echo (!empty($contact_email_err)) ? 'is-invalid' : ''; ?>" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars($contact_email_val); ?>">
                                <div class="invalid-feedback"><?php echo $contact_email_err; ?></div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="contact_phone" class="form-label">Contact Phone</label>
                                <input type="text" class="form-control" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars($contact_phone_val); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Company Logo</label>
                                <?php if (!empty($logo_val)): ?>
                                    <div class="mb-2">
                                        <img src="<?php echo htmlspecialchars($logo_val); ?>" alt="Current Company Logo" style="max-width: 150px; height: auto; border: 1px solid #ddd; padding: 5px; background-color: #fff;">
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="remove_logo" name="remove_logo" value="1">
                                        <label class="form-check-label" for="remove_logo">Remove Current Logo</label>
                                    </div>
                                <?php else: ?>
                                    <div class="mb-2 text-muted">No logo currently set.</div>
                                <?php endif; ?>

                                <label for="new_logo" class="form-label">Upload New Logo (Max 500KB, JPG, PNG, GIF)</label>
                                <input type="file" class="form-control" id="new_logo" name="new_logo" accept="image/jpeg, image/png, image/gif">
                                <small class="form-text text-muted">Leave blank to keep current logo, or check "Remove Current Logo" above.</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($address_val); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($city_val); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="state" class="form-label">State</label>
                                <input type="text" class="form-control" id="state" name="state" value="<?php echo htmlspecialchars($state_val); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="zip_code" class="form-label">Zip Code</label>
                                <input type="text" class="form-control" id="zip_code" name="zip_code" value="<?php echo htmlspecialchars($zip_code_val); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="country" class="form-label">Country</label>
                                <input type="text" class="form-control" id="country" name="country" value="<?php echo htmlspecialchars($country_val); ?>">
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">Save Company Settings</button>
                        </div>
                    </form>
                </div>
            </div> </div>


                    <!-- Button to trigger the collapse -->
                    <div class="d-grid gap-2 mb-4">
                        <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#registerUserCollapse" aria-expanded="<?php echo $is_aria_expanded; ?>" aria-controls="registerUserCollapse">
                            Register New User
                        </button>
                    </div>
                    
                    <!-- Wrapper for the collapsible form -->
                    <div class="<?php echo $is_form_collapsed; ?>" id="registerUserCollapse">
                        <div class="card card-body mb-4" style="border-top: 3px solid #0d6efd;">
                            <h4 class="mt-2">Register New User for <?php echo htmlspecialchars($company_name); ?></h4>
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="mb-2">
                                <input type="hidden" name="register_user" value="1">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="first_name" class="form-label">First Name</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="last_name" class="form-label">Last Name</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="phone_number" class="form-label">Phone Number</label>
                                        <input type="text" class="form-control" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($phone_number); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="role_selection" class="form-label">Assign Role</label>
                                        <select class="form-select <?php echo (!empty($role_selection_err)) ? 'is-invalid' : ''; ?>" id="role_selection" name="role_selection" required>
                                            <option value="">Select a Role</option>
                                            <option value="3" <?php echo ($role_selection == 3) ? 'selected' : ''; ?>>Project Manager</option>
                                            <option value="4" <?php echo ($role_selection == 4) ? 'selected' : ''; ?>>Viewer</option>
                                        </select>
                                        <div class="invalid-feedback"><?php echo $role_selection_err; ?></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                                    <div class="invalid-feedback"><?php echo $username_err; ?></div>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                                    <div class="invalid-feedback"><?php echo $email_err; ?></div>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" id="password" name="password" required>
                                    <div class="invalid-feedback"><?php echo $password_err; ?></div>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password</label>
                                    <input type="password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" id="confirm_password" name="confirm_password" required>
                                    <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                                </div>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">Register User</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- User table section -->
                    <h4 class="mt-5">Users in Your Company (<?php echo htmlspecialchars($company_name); ?>)</h4>
                    <div class="table-responsive">
                        <table id="usersTable" class="table table-hover table-bordered w-100">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Name</th>
                                    <th>Email</th>
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
    </main>

    <!-- Modals -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white"><h5 class="modal-title" id="editUserModalLabel">Edit User</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body"><form id="editUserForm"><input type="hidden" id="editUserId" name="id"><div class="row"><div class="col-md-6 mb-3"><label for="editFirstName" class="form-label">First Name</label><input type="text" class="form-control" id="editFirstName" name="first_name" required></div><div class="col-md-6 mb-3"><label for="editLastName" class="form-label">Last Name</label><input type="text" class="form-control" id="editLastName" name="last_name" required></div></div><div class="row"><div class="col-md-6 mb-3"><label for="editUsername" class="form-label">Username</label><input type="text" class="form-control" id="editUsername" name="username" readonly></div><div class="col-md-6 mb-3"><label for="editEmail" class="form-label">Email</label><input type="email" class="form-control" id="editEmail" name="email" required></div></div><div class="row"><div class="col-md-6 mb-3"><label for="editPhoneNumber" class="form-label">Phone Number</label><input type="text" class="form-control" id="editPhoneNumber" name="phone_number"></div><div class="col-md-6 mb-3"><label for="editRole" class="form-label">Role</label><select class="form-select" id="editRole" name="role_id" required><option value="3">Project Manager</option><option value="4">Viewer</option></select></div></div><div class="mb-3 form-check"><input type="checkbox" class="form-check-input" id="editIsActive" name="is_active" value="1"><label class="form-check-label" for="editIsActive">Account Active</label></div><hr><p class="text-muted small">Leave password fields blank if you don't want to change the password.</p><div class="row"><div class="col-md-6 mb-3"><label for="editPassword" class="form-label">New Password</label><input type="password" class="form-control" id="editPassword" name="password"></div><div class="col-md-6 mb-3"><label for="editConfirmPassword" class="form-label">Confirm New Password</label><input type="password" class="form-control" id="editConfirmPassword" name="confirm_password"></div></div></form></div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="button" class="btn btn-primary" id="saveUserChangesBtn">Save Changes</button></div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white"><h5 class="modal-title" id="deleteConfirmModalLabel">Confirm Deletion</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">Are you sure you want to delete this user? This action cannot be undone.</div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete User</button></div>
            </div>
        </div>
    </div>
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100">
        <div id="appToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true"><div class="toast-header"><strong class="me-auto" id="toastTitle">Notification</strong><button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button></div><div class="toast-body" id="toastBody"></div></div>
    </div>

    <!-- Scripts -->
<!-- Scripts (unchanged) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="js/company_admin.js"></script>
    <script src="js/main.js"></script>
    
    <?php
    // Close the database connection at the end of the script
    mysqli_close($link);
    ?>
</body>
</html>