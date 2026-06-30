<?php
include '../../db.php';
require '../login_verification.php';

// Ensure the request is POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["ticket_id"])) {
    $ticket_id = intval($_POST["ticket_id"]);
    $feedback = trim($_POST["feedback"]);
    $user_id = $_SESSION["user_id"]; // always store ID from session

    // ✅ Fetch updater's full name
    $stmt = $conn->prepare("SELECT firstname, lastname FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($firstname, $lastname);
    $stmt->fetch();
    $stmt->close();

    $user_name = $firstname . " " . $lastname;

    // Fetch current ticket status
    $stmt = $conn->prepare("SELECT status FROM tickets WHERE id = ?");
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $ticket = $result->fetch_assoc();

    if (!$ticket) {
        die("Error: Ticket not found.");
    }

    $current_status = $ticket['status'];

    // Auto-change status to "In Progress" only if it's still "Pending"
    $new_status = ($current_status === "Pending") ? "In Progress" : $current_status;

    // ✅ Update feedback and status
    $stmt = $conn->prepare("UPDATE tickets SET feedback = ?, status = ? WHERE id = ?");
    $stmt->bind_param("ssi", $feedback, $new_status, $ticket_id);

    if ($stmt->execute()) {
        // Log feedback in history
        if (!empty($feedback)) {
            $history_log = "Feedback added by {$user_name}: \"{$feedback}\"";
            $stmt = $conn->prepare("INSERT INTO ticket_history (ticket_id, action) VALUES (?, ?)");
            $stmt->bind_param("is", $ticket_id, $history_log);
            $stmt->execute();
        }

        // Log status change if it moved from Pending → In Progress
        if ($current_status === "Pending") {
            $status_log = "Status changed to 'In Progress' by {$user_name}";
            $stmt = $conn->prepare("INSERT INTO ticket_history (ticket_id, action) VALUES (?, ?)");
            $stmt->bind_param("is", $ticket_id, $status_log);
            $stmt->execute();
        }

        // ✅ Redirect back to the right dashboard
        $redirect_page = ($_SESSION["role"] == "Admin" || $_SESSION["role"] == "Super Admin") 
            ? "dashboard.php" 
            : "../department/dashboard.php";

        header("Location: $redirect_page");
        exit();
    } else {
        echo "❌ Error updating feedback and status: " . $stmt->error;
    }
} else {
    // Redirect if accessed incorrectly
    header("Location: ../../super_admin_dashboard.php");
    exit();
}
?>
