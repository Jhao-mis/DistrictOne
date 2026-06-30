<?php
session_start();
include '../db.php';

// Fetch all tickets for the client
$tickets = $pdo->query("SELECT id, status FROM tickets")->fetchAll(PDO::FETCH_ASSOC);

// Return the data as JSON
header('Content-Type: application/json');
echo json_encode($tickets);
?>