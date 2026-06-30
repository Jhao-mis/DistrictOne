<?php

include '../includes/db.php';
require '../login_verification.php';


// Ensure user_name is set
if (!isset($_SESSION['user_name'])) {
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT name FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && isset($user['name'])) {
        $_SESSION['user_name'] = $user['name'];
    } else {
        $_SESSION['user_name'] = "Unknown User";
    }

    $stmt->close();
}

// Fetch user’s reservations
$user_id = $_SESSION["user_id"];
$sql = "SELECT * FROM room_reservations WHERE user_id = ? ORDER BY date_requested DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$reservations = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Reservation Dashboard</title>
    <link rel="stylesheet" href="../css/reservation_dashboard.css">
    <script>
        // Auto-popup print for PDF generation
        function openPrintWindow(url) {
            const printWindow = window.open(url, '_blank');
            printWindow.onload = function() {
                printWindow.print();
                printWindow.onafterprint = () => {
                    printWindow.close();
                    window.location.href = 'reservation_dashboard.php'; // Return to dashboard after printing
                };
            };
        }
    </script>
</head>

<body>

<div class="container">
    <h2>Room Reservation Dashboard</h2>
    <h3>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h3>

    <?php if (count($reservations) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Date Requested</th>
                    <th>Reservation Date</th>
                    <th>Time</th>
                    <th>Room</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reservations as $reservation): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($reservation['date_requested']); ?></td>
                        <td><?php echo htmlspecialchars($reservation['reservation_date']); ?></td>
                        <td><?php echo htmlspecialchars($reservation['start_time'] . " - " . $reservation['end_time']); ?></td>
                        <td><?php echo htmlspecialchars($reservation['room']); ?></td>
                        <td class="status-<?php echo strtolower($reservation['status']); ?>">
                            <?php echo htmlspecialchars($reservation['status']); ?>
                        </td>
                        <td>
                            <?php if (strtolower($reservation['status']) === 'approved'): ?>
                                <button 
                                    class="btn btn-generate"
                                    onclick="openPrintWindow('reservation_pdf.php?id=<?php echo $reservation['id']; ?>')"
                                >
                                    📄 Generate PDF
                                </button>
                            <?php else: ?>
                                <button class="btn btn-disabled" disabled>🚫 Locked</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No reservations found.</p>
    <?php endif; ?>

    <a href="../super_admin/superAdmin_dashboard.php" class="btn btn-back">Back to Dashboard</a>
</div>

</body>
</html>
