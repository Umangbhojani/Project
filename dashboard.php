<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Check if the user is admin
if ($_SESSION["role"] !== 'admin') {
    // Redirect to a request page if not admin
    header("location: request_admin.php");
    exit;
}

// Include database connection file
include 'Mysql.php';

// Retrieve user information from session
$name = $_SESSION["name"];
$role = $_SESSION["role"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2 class="text-center">Welcome, <?php echo htmlspecialchars($name); ?>!</h2>
        <h4 class="text-center">Role: <?php echo htmlspecialchars(ucfirst($role)); ?></h4>
        
        <div class="mt-4">
            <h5>Options:</h5>
            <ul class="list-group">
                <li class="list-group-item"><a href="typing_test.php">Start Typing Test</a></li>
                <li class="list-group-item"><a href="view_results.php">View Results</a></li>
                <?php if ($role == 'team_leader'): ?>
                    <li class="list-group-item"><a href="manage_teams.php">Manage Teams</a></li>
                <?php endif; ?>
                <li class="list-group-item"><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
