<?php
session_start();

// Security check, allowing any logged-in user to see the dashboard.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once 'config/db_connect.php';

$company_id = $_SESSION["company_id"];
$user_id = $_SESSION["id"]; // Use the user's ID from the session

// --- MODIFIED: Fetch user's name directly from the database ---
$user_full_name = "User"; // Default value
$sql_user = "SELECT first_name, last_name FROM users WHERE id = ?";
if($stmt_user = mysqli_prepare($link, $sql_user)) {
    mysqli_stmt_bind_param($stmt_user, "i", $user_id);
    if(mysqli_stmt_execute($stmt_user)){
        mysqli_stmt_bind_result($stmt_user, $first_name, $last_name);
        if(mysqli_stmt_fetch($stmt_user)){
            $user_full_name = $first_name . ' ' . $last_name;
        }
    }
    mysqli_stmt_close($stmt_user);
}


if (empty($company_id)) {
    echo "Error: Your account is not associated with a company.";
    exit;
}

// --- Fetch Ongoing Projects ---
$ongoing_projects = [];
$sql_projects = "SELECT id, job_name, city, state FROM projects WHERE company_id = ? AND status = 'Ongoing' ORDER BY created DESC LIMIT 10";
if ($stmt_projects = mysqli_prepare($link, $sql_projects)) {
    mysqli_stmt_bind_param($stmt_projects, "i", $company_id);
    mysqli_stmt_execute($stmt_projects);
    $result_projects = mysqli_stmt_get_result($stmt_projects);
    while ($row = mysqli_fetch_assoc($result_projects)) {
        $ongoing_projects[] = $row;
    }
    mysqli_stmt_close($stmt_projects);
}

// --- Fetch Active (Not Completed) Tasks ---
$active_tasks = [];
$sql_tasks = "SELECT t.id, t.title, t.scheduled, p.job_name 
              FROM tasks t
              JOIN projects p ON t.project_id = p.id
              WHERE t.company_id = ? AND t.completed_at IS NULL 
              ORDER BY t.scheduled ASC, t.created_at ASC LIMIT 10";
if ($stmt_tasks = mysqli_prepare($link, $sql_tasks)) {
    mysqli_stmt_bind_param($stmt_tasks, "i", $company_id);
    mysqli_stmt_execute($stmt_tasks);
    $result_tasks = mysqli_stmt_get_result($stmt_tasks);
    while ($row = mysqli_fetch_assoc($result_tasks)) {
        $active_tasks[] = $row;
    }
    mysqli_stmt_close($stmt_tasks);
}

mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PourDay App</title>
    
    <link href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-bootstrap@2.0.12/dist/css/modus-bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-icons@1.16.0/dist/modus-solid/fonts/modus-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700&display=fallback"/>
    <link rel="stylesheet" href="css/style.css">
    
    </head>
<body>

    <?php include 'topbar_mobile.html'; ?>
    <?php include 'sidebar.html'; ?>

    <main class="page-content-wrapper">
        <div class="container-fluid p-4">
            <h1 class="mb-4">Welcome, <?php echo htmlspecialchars($user_full_name); ?>!</h1>

            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Current Projects</h5>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php if (empty($ongoing_projects)): ?>
                                <div class="list-group-item">No ongoing projects found.</div>
                            <?php else: ?>
                                <?php foreach ($ongoing_projects as $project): ?>
                                    <a href="projects.php" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($project['job_name']); ?></h6>
                                        </div>
                                        <small class="text-muted"><?php echo htmlspecialchars($project['city']); ?>, <?php echo htmlspecialchars($project['state']); ?></small>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Upcoming Tasks</h5>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php if (empty($active_tasks)): ?>
                                <div class="list-group-item">No active tasks.</div>
                            <?php else: ?>
                                <?php foreach ($active_tasks as $task): ?>
                                    <a href="tasks.php" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($task['title']); ?></h6>
                                            <small><?php echo $task['scheduled'] ? date('M j, Y', strtotime($task['scheduled'])) : 'Not Scheduled'; ?></small>
                                        </div>
                                        <small class="text-muted">Project: <?php echo htmlspecialchars($task['job_name']); ?></small>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>

</body>
</html>