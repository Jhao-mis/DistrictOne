<?php

include '../../db.php';
require '../login_verification.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ticket_id'])) {
    $ticket_id = intval($_POST['ticket_id']);
    $new_status = $_POST['status'];
    $feedback = trim($_POST['feedback']);
    $updated_by = $_SESSION["role"] == "admin" ? "Admin" : "User";

    // Fetch current ticket details
    $stmt = $conn->prepare("SELECT status FROM tickets WHERE id = ?");
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $ticket = $result->fetch_assoc();
    $current_status = $ticket['status'];

    // Update status only if it has changed
    if ($new_status !== $current_status) {
        $stmt = $conn->prepare("UPDATE tickets SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $ticket_id);
        $stmt->execute();

        // Log status change in history
        $history_message = "Status changed to $new_status by $updated_by.";
        $stmt = $conn->prepare("INSERT INTO ticket_history (ticket_id, action) VALUES (?, ?)");
        $stmt->bind_param("is", $ticket_id, $history_message);
        $stmt->execute();
    }

    // If feedback is added, log it too
    if (!empty($feedback)) {
        $stmt = $conn->prepare("UPDATE tickets SET feedback = ? WHERE id = ?");
        $stmt->bind_param("si", $feedback, $ticket_id);
        $stmt->execute();

        // Log feedback in history
        $history_message = "Feedback added by $updated_by: \"$feedback\"";
        $stmt = $conn->prepare("INSERT INTO ticket_history (ticket_id, action) VALUES (?, ?)");
        $stmt->bind_param("is", $ticket_id, $history_message);
        $stmt->execute();
    }

    header("Location: view_ticket.php?ticket_id=$ticket_id");
    exit();
}
?>
