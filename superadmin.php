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
        $mail->Username   = 'codetechflutter@gmail.com'; // Replace with your email
        $mail->Password   = 'fkae anzg opbh jjka'; // Replace with app-specific password
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

// Function to handle approve/reject via AJAX
function handleApproval($id, $action, $conn) {
    if ($action == 'approve') {
        $sql = "INSERT INTO users (team_name, username, email, password, role, created_at) 
                SELECT team_name, username, email, password, role, created_at FROM pending_users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            // Fetch email for sending confirmation
            $get_email_sql = "SELECT email, username FROM pending_users WHERE id = ?";
            $email_stmt = $conn->prepare($get_email_sql);
            $email_stmt->bind_param("i", $id);
            $email_stmt->execute();
            $email_stmt->bind_result($email, $username);
            $email_stmt->fetch();
            $email_stmt->close();

            // Send confirmation email
            if (sendMail($email, "Approval Confirmation", "Hello $username, your registration has been approved!")) {
                // Delete the user from pending_users after successful transfer to users
                $delete_sql = "DELETE FROM pending_users WHERE id = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("i", $id);
                $delete_stmt->execute();

                echo json_encode(['status' => 'success', 'message' => 'User approved and email sent!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Approval successful, but email failed!']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error approving user!']);
        }
    } elseif ($action == 'reject') {
        $sql = "DELETE FROM pending_users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'User rejected successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error rejecting user!']);
        }
    }
}

// Handle AJAX requests for approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT); // Validates the ID input
    $action = $_POST['action'];

    if ($id && in_array($action, ['approve', 'reject'])) {
        handleApproval($id, $action, $conn);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid input data!']);
    }
    exit;
}

// Fetch pending registrations
$result = $conn->query("SELECT * FROM pending_users");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Registrations</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        #spinner {
            display: none;
            text-align: center;
        }
        .modal-confirm {        
            color: #434e65;
            width: 325px;
            font-size: 14px;
        }
        .modal-confirm .modal-content {
            padding: 20px;
            border-radius: 5px;
            border: none;
        }
        .btn-top-right {
            position: absolute;
            top: 20px;
            right: 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <!-- Button to Manage Users page -->
        <a href="manage_users.php" class="btn btn-primary btn-top-right">Manage Users</a>

        <h2 class="text-center">Pending Registrations</h2>

        <!-- Loading Spinner -->
        <div id="spinner" class="mt-4">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <p>Processing...</p>
        </div>

        <!-- Pending Users Table -->
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Team Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="pending-users">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['team_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><span class="badge badge-info"><?php echo htmlspecialchars($row['role']); ?></span></td>
                        <td>
                            <button class="btn btn-success approve-btn" data-id="<?php echo $row['id']; ?>">Approve</button>
                            <button class="btn btn-danger reject-btn" data-id="<?php echo $row['id']; ?>">Reject</button>
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

        // Approve Button Click Handler
        $('.approve-btn').on('click', function() {
            currentId = $(this).data('id');
            if (confirm("Are you sure you want to approve this user?")) {
                handleAction('approve', currentId);
            }
        });

        // Reject Button Click Handler
        $('.reject-btn').on('click', function() {
            currentId = $(this).data('id');
            if (confirm("Are you sure you want to reject this user?")) {
                handleAction('reject', currentId);
            }
        });

        // Function to handle approve/reject action
        function handleAction(action, id) {
            $('#spinner').show();
            $.ajax({
                type: 'POST',
                url: 'superadmin.php',
                data: {action: action, id: id},
                dataType: 'json',
                success: function(response) {
                    $('#response').html('<div class="alert alert-' + (response.status == 'success' ? 'success' : 'danger') + '">' + response.message + '</div>');
                    $('button[data-id="' + id + '"]').closest('tr').remove(); // Remove row
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
