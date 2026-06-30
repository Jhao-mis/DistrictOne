<?php
require '../db.php'; // Ensure correct database connection
require 'login_verification.php';
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(["status" => "error", "message" => "No ID provided."]);
    exit;
}

$activity_id = (int)$_POST['id']; // Sanitize input

$stmt = $pdo->prepare("DELETE FROM activities WHERE id = ?");
$stmt->execute([$activity_id]);

if ($stmt->rowCount()) {
    echo json_encode(["status" => "success", "message" => "Activity deleted successfully."]);
} else {
    echo json_encode(["status" => "error", "message" => "No rows affected."]);
}
?>
