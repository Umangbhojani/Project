<?php
session_start();
include 'Mysql.php'; // Include your MySQL connection class

// Function to sanitize user input
function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $username = sanitize_input($_POST['username']);
    $password = sanitize_input($_POST['password']);
    $role = sanitize_input($_POST['role']);

    if (empty($username) || empty($password) || empty($role)) {
        $error = "Please fill all fields.";
    } else {
        // Determine the table to query based on the role
        if ($role == 'team_member') {
            // Query the approved_members table for team members
            $sql = "SELECT * FROM approved_members WHERE username = ?";
        } else {
            // Query the users table for team leaders and superadmins
            $sql = "SELECT * FROM users WHERE username = ? AND role = ?";
        }

        $stmt = $conn->prepare($sql);
        
        if ($role == 'team_member') {
            $stmt->bind_param("s", $username);
        } else {
            $stmt->bind_param("ss", $username, $role);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        // Verify the user's password and handle the login process
        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $role;
            $_SESSION['team_name'] = $user['team_name']; // assuming team_name is part of both tables

            // Redirect based on role
            if ($role == 'team_leader') {
                echo json_encode(['status' => 'success', 'redirect' => 'leader_approval.php']);
            } elseif ($role == 'team_member') {
                echo json_encode(['status' => 'success', 'redirect' => 'team_member_dashboard.php']);
            } elseif ($role == 'superadmin') {
                echo json_encode(['status' => 'success', 'redirect' => 'superadmin.php']);
            }
            exit;
        } else {
            $error = "Invalid username, password, or role.";
        }
    }
}

// Handle error messages in AJAX requests
if (!empty($error)) {
    echo json_encode(['status' => 'error', 'message' => $error]);
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* Add CSS for loading spinner */
        #loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            z-index: 9999;
            text-align: center;
        }
        #loading .spinner-border {
            position: relative;
            top: 50%;
            transform: translateY(-50%);
        }
    </style>
</head>
<body>
    <div id="loading">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Loading...</span>
        </div>
        <h4>Loading...</h4>
    </div>

    <div class="container mt-5">
        <h2 class="text-center">Login</h2>
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div id="error-message" class="alert alert-danger" style="display:none;"></div>
                <form id="login-form" method="POST" action="login.php">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" name="username" class="form-control" id="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" name="password" class="form-control" id="password" required>
                    </div>
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select name="role" class="form-control" id="role" required>
                            <option value="team_leader">Team Leader</option>
                            <option value="team_member">Team Member</option>
                            <option value="superadmin">Superadmin</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Login</button>
                    <a href="register.php">Your new here than register your self </a> 
                </form>
            </div>
        </div>
    </div>

    <script>
        $('#login-form').on('submit', function(e) {
            e.preventDefault(); // Prevent the default form submission
            $('#loading').show(); // Show loading spinner

            $.ajax({
                type: 'POST',
                url: 'login.php',
                data: $(this).serialize() + '&action=login', // Append action to data
                dataType: 'json',
                success: function(response) {
                    $('#loading').hide(); // Hide loading spinner
                    if (response.status === 'success') {
                        window.location.href = response.redirect; // Redirect on success
                    } else {
                        $('#error-message').text(response.message).show(); // Show error message
                    }
                },
                error: function() {
                    $('#loading').hide(); // Hide loading spinner
                    $('#error-message').text('An unexpected error occurred. Please try again.').show();
                }
            });
        });
    </script>
</body>
</html>
