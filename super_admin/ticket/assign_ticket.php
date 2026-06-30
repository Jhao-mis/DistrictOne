<?php
require_once '../..db.php';
require '../login_verification.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $ticket_id = intval($_POST['ticket_id']);
    $assigned_to = isset($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
    $admin_id = $_SESSION["user_id"]; // Who made the assignment

    // Fetch current ticket data
    $query = "SELECT assigned_to, subject FROM tickets WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $ticket = $result->fetch_assoc();

    if (!$ticket) {
        die("❌ Error: Ticket not found.");
    }

    // Only log if assignment is new/different
    if (!empty($assigned_to) && $assigned_to != $ticket['assigned_to']) {

        // ✅ Update ticket assignment + status
        $update_query = "UPDATE tickets SET assigned_to = ?, status = 'In Progress' WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ii", $assigned_to, $ticket_id);
        $stmt->execute();

        // ✅ Fetch assigned MIS personnel name
        $mis_query = "SELECT firstname, lastname FROM users WHERE id = ?";
        $stmt = $conn->prepare($mis_query);
        $stmt->bind_param("i", $assigned_to);
        $stmt->execute();
        $mis_result = $stmt->get_result();
        $mis = $mis_result->fetch_assoc();
        $mis_name = $mis ? $mis['firstname'] . ' ' . $mis['lastname'] : 'Unknown';

        // ✅ Fetch assigning Admin/Super Admin name
        $admin_query = "SELECT firstname, lastname FROM users WHERE id = ?";
        $stmt = $conn->prepare($admin_query);
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $admin_result = $stmt->get_result();
        $admin = $admin_result->fetch_assoc();
        $admin_name = $admin ? $admin['firstname'] . ' ' . $admin['lastname'] : 'Unknown';

        // ✅ Insert history logs using names instead of role labels
        $history_query = "INSERT INTO ticket_history (ticket_id, action) VALUES (?, ?)";
        $stmt = $conn->prepare($history_query);

        // Assigned log
        $history_msg = "Ticket assigned to {$mis_name} by {$admin_name}";
        $stmt->bind_param("is", $ticket_id, $history_msg);
        $stmt->execute();

        // Status log
        $status_log = "Status changed to In Progress by {$admin_name}";
        $stmt->bind_param("is", $ticket_id, $status_log);
        $stmt->execute();
    }

    // ✅ Redirect back to dashboard
    header("Location: ../super_admin_dashboard.php?ticket_id=" . $ticket_id);
    exit();
} else {
    header("Location: ../super_admin_dashboard.php");
    exit();
}
?>
