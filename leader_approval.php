<?php
session_start();
include 'Mysql.php'; // Include your MySQL connection class

// Check if the user is logged in and if the role is team_leader
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'team_leader') {
    die("Error: Unauthorized access. Please log in as a team leader.");
}

// Ensure the session contains the correct team name
if (!isset($_SESSION['team_name']) || empty($_SESSION['team_name'])) {
    die("Error: Team name not found in session. Please log in again.");
}

// Retrieve the team leader's team name from the session
$team_name = $_SESSION['team_name'];

// Prepare SQL query to retrieve pending members for the team leader's team
$sql = "SELECT * FROM pending_members WHERE team_name = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $team_name);
$stmt->execute();
$pending_members = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leader Approval Page</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="container mt-5">
        <h2 class="text-center">Pending Team Member Approvals</h2>

        <?php if ($pending_members->num_rows > 0): ?>
            <table class="table table-bordered" id="members-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($member = $pending_members->fetch_assoc()): ?>
                        <tr data-member-id="<?php echo $member['id']; ?>">
                            <td><?php echo htmlspecialchars($member['username']); ?></td>
                            <td><?php echo htmlspecialchars($member['email']); ?></td>
                            <td>
                                <button class="btn btn-success approve-btn">Approve</button>
                                <button class="btn btn-danger reject-btn">Reject</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-center">No pending members for your team.</p>
        <?php endif; ?>
    </div>

    <script>
        $(document).ready(function() {
            // Approve button click event
            $(document).on('click', '.approve-btn', function() {
                let memberId = $(this).closest('tr').data('member-id');
                if (confirm("Are you sure you want to approve this member?")) {
                    $.ajax({
                        type: 'POST',
                        url: 'leader_approval.php', 
                        data: { action: 'approve', member_id: memberId },
                        dataType: 'json',
                        success: function(response) {
                            console.log(response);  // Log the response for debugging
                            alert(response.message);
                            if (response.status === 'success') {
                                $('tr[data-member-id="' + memberId + '"]').remove();
                            }
                        }
                    });
                }
            });

            // Reject button click event
            $(document).on('click', '.reject-btn', function() {
                let memberId = $(this).closest('tr').data('member-id');
                if (confirm("Are you sure you want to reject this member?")) {
                    $.ajax({
                        type: 'POST',
                        url: 'leader_approval.php', 
                        data: { action: 'reject', member_id: memberId },
                        dataType: 'json',
                        success: function(response) {
                            console.log(response);  // Log the response for debugging
                            alert(response.message);
                            if (response.status === 'success') {
                                $('tr[data-member-id="' + memberId + '"]').remove();
                            }
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>

<?php
// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json'); // Set the header for JSON response
    $action = $_POST['action'];
    $member_id = $_POST['member_id'];

    // Fetch member details
    $stmt = $conn->prepare("SELECT * FROM pending_members WHERE id = ?");
    $stmt->bind_param("i", $member_id);
    if (!$stmt->execute()) {
        echo json_encode(['status' => 'error', 'message' => 'Error fetching member: ' . $conn->error]);
        exit;
    }
    $member = $stmt->get_result()->fetch_assoc();

    if ($action == 'approve') {
        // Insert member into approved_members table
        $sql = "INSERT INTO approved_members (team_name, username, email, password, role, created_at) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssssss", 
            $member['team_name'], 
            $member['username'], 
            $member['email'], 
            $member['password'], 
            $member['role'], 
            $member['created_at']
        );

        if ($stmt->execute()) {
            // Delete member from pending_members table
            $del_stmt = $conn->prepare("DELETE FROM pending_members WHERE id = ?");
            $del_stmt->bind_param("i", $member_id);
            $del_stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'Member approved successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error approving member: ' . $conn->error]);
        }
    } elseif ($action == 'reject') {
        // Delete member from pending_members table
        $del_stmt = $conn->prepare("DELETE FROM pending_members WHERE id = ?");
        $del_stmt->bind_param("i", $member_id);
        if ($del_stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Member rejected successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error rejecting member: ' . $conn->error]);
        }
    }
    exit;
}
?>
