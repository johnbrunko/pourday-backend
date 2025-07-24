<?php
// Initialize the session
session_start();

// Check if the user is already logged in, if yes then redirect them based on their role
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    // Role-based redirect for already logged-in users
    $role_id = $_SESSION["role_id"];
    switch ($role_id) {
        case 1: // SuperAdmin
            header("location: super_admin_dashboard.php");
            break;
        case 2: // CompanyAdmin
            header("location: admin_dashboard.php");
            break;
        case 3: // ProjectManager
        case 4: // Viewer
            header("location: dashboard.php");
            break;
        default:
            // Default redirect if role is not set or recognized
            header("location: dashboard.php");
            break;
    }
    exit;
}

// Include database connection
require_once 'config/db_connect.php';

// Define variables and initialize with empty values
$username = $password = "";
$username_err = $password_err = $login_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Check if username is empty
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter username.";
    } else {
        $username = trim($_POST["username"]);
    }

    // Check if password is empty
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate credentials
    if (empty($username_err) && empty($password_err)) {
        // Prepare a select statement
        $sql = "SELECT id, username, email, password, role_id, company_id, is_active FROM users WHERE username = ?";

        if ($stmt = mysqli_prepare($link, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "s", $param_username);

            // Set parameters
            $param_username = $username;

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                // Store result
                mysqli_stmt_store_result($stmt);

                // Check if username exists, if yes then verify password
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    // Bind result variables
                    mysqli_stmt_bind_result($stmt, $id, $username, $email, $hashed_password, $role_id, $company_id, $is_active);
                    if (mysqli_stmt_fetch($stmt)) {
                        if ($is_active) { // Check if account is active
                            if (password_verify($password, $hashed_password)) {
                                // Password is correct, so start a new session
                                // session_start(); // Already started at the top

                                // Store data in session variables
                                $_SESSION["loggedin"] = true;
                                $_SESSION["id"] = $id;
                                $_SESSION["username"] = $username;
                                $_SESSION["email"] = $email;
                                $_SESSION["role_id"] = $role_id;
                                $_SESSION["company_id"] = $company_id;

                                // *** NEW: ROLE-BASED REDIRECT LOGIC ***
                                // Redirect user based on their role_id
                                switch ($role_id) {
                                    case 1: // SuperAdmin
                                        header("location: super_admin_dashboard.php");
                                        break;
                                    case 2: // CompanyAdmin
                                        header("location: admin_dashboard.php");
                                        break;
                                    case 3: // ProjectManager
                                        header("location: dashboard.php");
                                        break;
                                    case 4: // Viewer
                                        header("location: dashboard.php");
                                        break;
                                    default:
                                        // Fallback to a default dashboard if role is not recognized
                                        header("location: dashboard.php");
                                        break;
                                }
                                exit(); // Crucial to stop script execution after redirect

                            } else {
                                // Password is not valid
                                $login_err = "Invalid username or password.";
                            }
                        } else {
                            // Account is inactive
                            $login_err = "Your account is currently inactive. Please contact support.";
                        }
                    }
                } else {
                    // Username doesn't exist
                    $login_err = "Invalid username or password.";
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }

    // Close connection
    mysqli_close($link);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Concrete Pour App - Login</title>

    <link rel="stylesheet" href="node_modules/@trimble-oss/modus-bootstrap/dist/css/modus-bootstrap.min.css">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700&display=fallback"/>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Concrete Pour App Login</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        if (!empty($login_err)) {
                            echo '<div class="alert alert-danger">' . $login_err . '</div>';
                        }
                        ?>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                                <div class="invalid-feedback"><?php echo $username_err; ?></div>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" id="password" name="password" required>
                                <div class="invalid-feedback"><?php echo $password_err; ?></div>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Login</button>
                            </div>
                            <p class="text-center mt-3">
                                Don't have an account? <a href="register.php">Register here</a>
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="node_modules/@trimble-oss/modus-bootstrap/dist/js/modus-bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>

</body>
</html>