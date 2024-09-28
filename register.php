<?php 
include 'Mysql.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Function to send email
function sendMail($to, $subject, $message){
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'codetechflutter@gmail.com';
        $mail->Password   = ''; // Ensure to use a secure way to store passwords
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('codetechflutter@gmail.com', 'Typing Master');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

// Handle AJAX request for registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'register') {
    $team_name = $_POST['team_name'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $created_at = date('Y-m-d H:i:s');

    // Check for existing username or email
    $check_sql = "SELECT * FROM pending_users WHERE username = ? OR email = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Username or email already exists!']);
    } else {
        // Insert into pending_users based on role
        if ($role == 'team_leader') {
            $sql = "INSERT INTO pending_users (team_name, username, email, password, role, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?)"; // Removed name field
        } else {
            $sql = "INSERT INTO pending_members (team_name, username, email, password, role, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?)"; // Removed name field
        }

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssss", $team_name, $username, $email, $password, $role, $created_at);
        
        if ($stmt->execute()) {
            $message = ($role == 'team_leader') 
                ? "Your registration as Team Leader is successful! Awaiting admin approval."
                : "Your registration as Team Member is successful! Awaiting team leader approval.";
            sendMail($email, "Registration Confirmation", $message);
            echo json_encode(['status' => 'success', 'message' => 'Registration successful!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error: ' . $stmt->error]);
        }
    }
    exit; // End script after handling AJAX request
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Team</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* Style for centering the spinner */
        #loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1000;
            display: none; /* Hidden initially */
        }

        /* Style to dim the form when loading */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.7);
            z-index: 999;
            display: none; /* Hidden initially */
        }
    </style>
</head>
<body>
    <div class="container mt-5 position-relative">
        <!-- Loading spinner overlay -->
        <div id="loading" class="loading-overlay">
            <div class="spinner-border" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <p>Please wait...</p>
        </div>

        <h2 class="text-center">Register Team</h2>
        <form id="registrationForm">
            <div class="form-group">
                <label for="team_name">Team Name</label>
                <input type="text" class="form-control" id="team_name" name="team_name" required>
            </div>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="role">Role</label>
                <select class="form-control" id="role" name="role" required>
                    <option value="team_member">Team Member</option>
                    <option value="team_leader">Team Leader</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Register</button>
            <a href="login.php">You can now log in here</a>
        </form>
    </div>

    <script>
        $(document).ready(function() {
            $('#registrationForm').on('submit', function(e) {
                e.preventDefault(); // Prevent the default form submission

                // Show the loading spinner
                $('#loading').show();

                $.ajax({
                    url: 'register.php',
                    type: 'POST',
                    data: $(this).serialize() + '&action=register',
                    dataType: 'json',
                    success: function(response) {
                        // Hide the loading spinner
                        $('#loading').hide();

                        if (response.status === 'success') {
                            alert(response.message);
                            $('#registrationForm')[0].reset(); // Reset form after successful submission
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function() {
                        // Hide the loading spinner
                        $('#loading').hide();

                        alert('An error occurred. Please try again.');
                    }
                });
            });
        });
    </script>
</body>
</html>