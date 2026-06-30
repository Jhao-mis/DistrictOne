<?php
session_start();
include '../includes/db.php';

// Ensure only Admin or MIS Supervisor can access
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["role"], ['admin', 'mis'])) {
    header("Location: ../login.php");
    exit();
}

// ✅ Handle Approve/Reject actions
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'], $_POST['reservation_id'])) {
    $reservation_id = $_POST['reservation_id'];
    $action = $_POST['action'];

    // Determine status based on the action
    $new_status = ($action === 'approve') ? 'Approved' : 'Rejected';

    // Track admin approval
    if ($new_status === 'Approved') {
        $approved_by = $_SESSION['name']; // Capture admin's name from session
        $stmt = $conn->prepare("UPDATE room_reservations SET status = ?, approved_by = ? WHERE id = ?");
        $stmt->bind_param("ssi", $new_status, $approved_by, $reservation_id);
    } else {
        // For rejection, don't touch `approved_by`
        $stmt = $conn->prepare("UPDATE room_reservations SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $reservation_id);
    }

    if ($stmt->execute()) {
        $message = "✅ Reservation has been " . strtolower($new_status) . "!";
    } else {
        $message = "❌ Error updating reservation: " . $conn->error;
    }

    $stmt->close();
}

// ✅ Fetch all reservations
$sql = "SELECT room_reservations.*, users.name AS requested_name
        FROM room_reservations
        JOIN users ON room_reservations.user_id = users.id
        ORDER BY date_requested DESC";
$result = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Reservation Approvals</title>
    <link rel="stylesheet" href="../css/reservation_approval.css">
</head>
<body>

<div class="container">
    <h2>Room Reservation Approval Panel</h2>

    <!-- ✅ Check if there are any reservations -->
    <?php if ($result && $result->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Request Number</th>
                    <th>Requested By</th>
                    <th>Department</th>
                    <th>Purpose</th>
                    <th>Date Requested</th>
                    <th>Reservation Date</th>
                    <th>Time</th>
                    <th>Room</th>
                    <th>Participants</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <!-- ✅ Loop through reservations -->
                <?php while ($reservation = $result->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo htmlspecialchars($reservation['id']); ?></td>
                        <td><?php echo htmlspecialchars($reservation['requested_name']); ?></td>
                        <td><?php echo htmlspecialchars($reservation['department']); ?></td>
                        <td><?php echo htmlspecialchars($reservation['purpose']); ?></td>
                        <td><?php echo htmlspecialchars($reservation['date_requested']); ?></td>
                        <td><?php echo htmlspecialchars($reservation['reservation_date']); ?></td>
                        <td><?php echo htmlspecialchars($reservation['start_time'] . " - " . $reservation['end_time']); ?></td>
                        <td><?php echo htmlspecialchars($reservation['room']); ?></td>
                        <td><?php echo htmlspecialchars($reservation['participants']); ?></td>
                        <td class="status-<?php echo strtolower($reservation['status']); ?>">
                            <?php echo htmlspecialchars($reservation['status']); ?>
                        </td>
                        <td>
                            <!-- ✅ Buttons (always visible) -->
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                                <button type="submit" name="action" value="approve" class="btn btn-approve">Approve</button>
                                <button type="submit" name="action" value="reject" class="btn btn-reject">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No reservations found.</p>
    <?php endif; ?>

    <a href="../admin/dashboard.php" class="btn btn-back">Back to Dashboard</a>
</div>

</body>
</html>

<?php
$conn->close();
?>
