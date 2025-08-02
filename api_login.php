<?php
// Set the content type to JSON for API responses
header('Content-Type: application/json');

// Include database connection
require_once 'config/db_connect.php';

// --- Read JSON data from the desktop client ---
$json_data = file_get_contents('php://input');
$data = json_decode($json_data);

// Check if data is valid
if (!$data || !isset($data->username) || !isset($data->password)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit();
}

$username = $data->username;
$password = $data->password;

// --- Validate Credentials (Reused from your index.php) ---
$sql = "SELECT id, username, password, role_id, is_active FROM users WHERE username = ?";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "s", $param_username);
    $param_username = $username;

    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) == 1) {
            mysqli_stmt_bind_result($stmt, $id, $username, $hashed_password, $role_id, $is_active);
            if (mysqli_stmt_fetch($stmt)) {
                if ($is_active) {
                    if (password_verify($password, $hashed_password)) {
                        // --- SUCCESS! GENERATE AND SAVE TOKEN ---

                        // Generate a secure random token
                        $token = bin2hex(random_bytes(32));

                        // Save the token to the database for this user
                        $update_sql = "UPDATE users SET api_token = ? WHERE id = ?";
                        if ($update_stmt = mysqli_prepare($link, $update_sql)) {
                            mysqli_stmt_bind_param($update_stmt, "si", $token, $id);
                            mysqli_stmt_execute($update_stmt);
                            mysqli_stmt_close($update_stmt);
                        }

                        // --- Return a success response with the token ---
                        echo json_encode([
                            'success' => true,
                            'token' => $token,
                            'user' => [
                                'id' => $id,
                                'username' => $username,
                                'role_id' => $role_id
                            ]
                        ]);
                        exit();

                    }
                } else {
                    // Account is inactive
                    echo json_encode(['success' => false, 'message' => 'Your account is currently inactive.']);
                    exit();
                }
            }
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database query failed.']);
        exit();
    }
}

// --- FAILED LOGIN ---
// If we reach here, the login failed (invalid user/pass)
echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
?>