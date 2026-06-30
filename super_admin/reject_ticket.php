<?php
session_start();
include '../db.php';
require 'login_verification.php';

if (!isset($_GET['ticket_id'])) {
    die("❌ Ticket ID missing");
}

$ticket_id = intval($_GET['ticket_id']);
$admin_id = $_SESSION['user_id'];

// ✅ Reject ticket
$stmt = $conn->prepare("UPDATE tickets SET status = 'Rejected' WHERE id = ?");
$stmt->bind_param("i", $ticket_id);
$stmt->execute();

// ✅ Log history
$stmt = $conn->prepare("INSERT INTO ticket_history (ticket_id, action, action_by) 
                        VALUES (?, 'Rejected by Admin', ?)");
$stmt->bind_param("ii", $ticket_id, $admin_id);
$stmt->execute();

header("Location: approveTicket.php?msg=rejected");
exit;
