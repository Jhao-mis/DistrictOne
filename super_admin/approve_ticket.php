<?php
session_start();
include '../db.php';
require 'login_verification.php';

if (!isset($_GET['ticket_id'])) {
    die("❌ Ticket ID missing");
}

$ticket_id = intval($_GET['ticket_id']);
$admin_id = $_SESSION['user_id'];

// ✅ Fetch admin's name
$stmt = $conn->prepare("SELECT firstname, lastname FROM users WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$stmt->bind_result($firstname, $lastname);
$stmt->fetch();
$stmt->close();

$admin_name = $firstname . " " . $lastname;

// ✅ Approve ticket
$stmt = $conn->prepare("UPDATE tickets SET admin_approved = 1 WHERE id = ?");
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$stmt->close();

// ✅ Log history with name instead of "Admin"
$action_text = "Approved by " . $admin_name;
$stmt = $conn->prepare("INSERT INTO ticket_history (ticket_id, action, action_by) 
                        VALUES (?, ?, ?)");
$stmt->bind_param("ssi", $ticket_id, $action_text, $admin_id);
$stmt->execute();
$stmt->close();

header("Location: approveTicket.php?msg=approved");
exit;
