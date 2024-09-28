<?php
include 'Mysql.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Function to send email
function sendMail($to, $subject, $message) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'codetechflutter@gmail.com'; // Your email
        $mail->Password   = 'fkae anzg opbh jjka'; // Your email password
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

// Handle user actions (Update/Delete)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    $action = $_POST['action'];

    if ($id && in_array($action, ['delete', 'update'])) {
        // Fetch user's email and username for notifications
        $get_user_sql = "SELECT email, username, role FROM users WHERE id = ?";
        $user_stmt = $conn->prepare($get_user_sql);
        $user_stmt->bind_param("i", $id);
        $user_stmt->execute();
        $user_stmt->bind_result($email, $username, $currentRole);
        $user_stmt->fetch();
        $user_stmt->close();

        if ($action == 'delete') {
            // Delete user from users table
            $sql = "DELETE FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                sendMail($email, "Account Deletion", "Hello $username, your account has been deleted.");
                echo json_encode(['status' => 'success', 'message' => 'User deleted successfully!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error deleting user!']);
            }
        } elseif ($action == 'update') {
            // Update user details (For example, updating the role)
            $role = $_POST['role'];

            if ($currentRole == 'Leader' && $role == 'Member') {
                // Move from users to approved_members
                $insert_sql = "INSERT INTO approved_members (team_name, username, email, password, created_at, role) 
                               SELECT team_name, username, email, password, created_at, ? FROM users WHERE id = ?";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("si", $role, $id);

                if ($insert_stmt->execute()) {
                    // Delete the user from the users table
                    $delete_sql = "DELETE FROM users WHERE id = ?";
                    $delete_stmt = $conn->prepare($delete_sql);
                    $delete_stmt->bind_param("i", $id);
                    $delete_stmt->execute();

                    sendMail($email, "Role Update Notification", "Hello $username, your role has been updated to Member. You have been moved to the approved members list.");
                    echo json_encode(['status' => 'success', 'message' => 'User role updated and moved to approved members successfully!']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Error moving user to approved members!']);
                }
            } else {
                // Update user role
                $sql = "UPDATE users SET role = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $role, $id);
                if ($stmt->execute()) {
                    sendMail($email, "Role Update Notification", "Hello $username, your role has been updated to $role.");
                    echo json_encode(['status' => 'success', 'message' => 'User role updated successfully!']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Error updating user!']);
                }
            }
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid input data!']);
    }
    exit;
}

// Fetch all users
$result = $conn->query("SELECT * FROM users");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
</head>
<body>
    <div class="container mt-5">
        <!-- Back Button -->
        <a href="superadmin.php" class="btn btn-secondary mb-3">&larr; Back</a>
        
        <h2 class="text-center">Manage Users</h2>

        <!-- Loading Spinner -->
        <div id="spinner" class="mt-4" style="display: none;">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <p>Processing...</p>
        </div>

        <!-- Users Table -->
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Team Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="users-list">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo htmlspecialchars($row['team_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td>
                            <select class="form-control role-select" data-id="<?php echo $row['id']; ?>">
                                <option value="Leader" <?php echo $row['role'] == 'Leader' ? 'selected' : ''; ?>>Leader</option>
                                <option value="Member" <?php echo $row['role'] == 'Member' ? 'selected' : ''; ?>>Member</option>
                            </select>
                        </td>
                        <td>
                            <button class="btn btn-danger delete-btn" data-id="<?php echo $row['id']; ?>">Delete</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- Response Message -->
        <div id="response" class="mt-3"></div>
    </div>

    <script>
    $(document).ready(function() {
        var currentId;

        // Delete Button Click Handler
        $('.delete-btn').on('click', function() {
            currentId = $(this).data('id');
            if (confirm("Are you sure you want to delete this user?")) {
                handleAction('delete', currentId);
            }
        });

        // Role Change Handler
        $('.role-select').on('change', function() {
            var id = $(this).data('id');
            var newRole = $(this).val();
            if (confirm("Are you sure you want to update this user's role?")) {
                handleAction('update', id, newRole);
            }
        });

        // Function to handle delete/update action
        function handleAction(action, id, role = null) {
            $('#spinner').show();
            $.ajax({
                type: 'POST',
                url: 'manage_users.php',
                data: {action: action, id: id, role: role},
                dataType: 'json',
                success: function(response) {
                    $('#response').html('<div class="alert alert-' + (response.status == 'success' ? 'success' : 'danger') + '">' + response.message + '</div>');
                    if (action === 'delete') {
                        $('button[data-id="' + id + '"]').closest('tr').remove(); // Remove the deleted user row
                    }
                    $('#spinner').hide();
                },
                error: function() {
                    $('#response').html('<div class="alert alert-danger">An error occurred. Please try again.</div>');
                    $('#spinner').hide();
                }
            });
        }
    });
    </script>
</body>
</html>
