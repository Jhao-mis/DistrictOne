<?php
include '../../db.php';
require '../login_verification.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["ticket_id"])) {
    $ticket_id   = intval($_POST["ticket_id"]);
    $assigned_to = isset($_POST["assigned_to"]) ? intval($_POST["assigned_to"]) : null;
    $status      = $_POST["status"] ?? null;
    $feedback    = trim($_POST["feedback"]);
    $user_id     = $_SESSION["user_id"]; // ✅ safer than relying only on role

    // ✅ Fetch updater's name & role
    $stmt = $conn->prepare("SELECT firstname, lastname, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($firstname, $lastname, $role);
    $stmt->fetch();
    $stmt->close();

    $user_name = $firstname . " " . $lastname;

    // Fetch current ticket details
    $query = "SELECT assigned_to, status, feedback FROM tickets WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $ticket = $result->fetch_assoc();
    $stmt->close();

    if (!$ticket) {
        die("❌ Error: Ticket not found.");
    }

    $current_status   = $ticket["status"];
    $current_feedback = $ticket["feedback"];
    $current_assignee = $ticket["assigned_to"];

    // Track changes in ticket history
    $history_msgs = [];

    // ✅ Handle assignment change
    if (!empty($assigned_to) && $assigned_to != $current_assignee) {
        $mis_query = "SELECT CONCAT(firstname, ' ', lastname) AS name FROM users WHERE id = ?";
        $stmt = $conn->prepare($mis_query);
        $stmt->bind_param("i", $assigned_to);
        $stmt->execute();
        $mis_result = $stmt->get_result();
        $mis = $mis_result->fetch_assoc();
        $stmt->close();

        $mis_name = $mis["name"] ?? "Unknown";
        $history_msgs[] = "Ticket assigned to {$mis_name} by {$user_name}";

        // Auto-change status to "In Progress" if still pending
        if ($current_status === "Pending") {
            $status = "In Progress";
        }
    } else {
        $assigned_to = $current_assignee; // keep existing if unchanged
    }

    // ✅ Handle status change
    if (!empty($status) && $status != $current_status) {
        $history_msgs[] = "Status changed to '{$status}' by {$user_name}";
    } else {
        $status = $current_status; // keep existing if unchanged
    }

    // ✅ Handle feedback change (only if new or modified)
    if (!empty($feedback) && $feedback != $current_feedback) {
        $history_msgs[] = "Feedback updated by {$user_name}: \"{$feedback}\"";
    } else {
        $feedback = $current_feedback; // keep existing if unchanged
    }

    // Record only actual changes
    foreach ($history_msgs as $msg) {
        $history_stmt = $conn->prepare("INSERT INTO ticket_history (ticket_id, action) VALUES (?, ?)");
        $history_stmt->bind_param("is", $ticket_id, $msg);
        $history_stmt->execute();
        $history_stmt->close();
    }

    // ✅ Update ticket with latest values
    $update_query = "UPDATE tickets SET assigned_to = ?, status = ?, feedback = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("issi", $assigned_to, $status, $feedback, $ticket_id);

    if ($update_stmt->execute()) {
        $update_stmt->close();

        // Redirect to the appropriate dashboard
        if ($role == "Admin" || $role == "Super Admin") {
            header("Location: ../../super_admin/viewticketSuperadmin.php?ticket_id=" . $ticket_id);
        } elseif ($role == "MIS") {
            header("Location: ../mis/dashboard.php");
        } else {
            header("Location: ../department/dashboard.php");
        }
        exit();
    } else {
        echo "❌ Error updating ticket: " . $update_stmt->error;
    }
}
?>
