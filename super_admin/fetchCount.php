<?php
include '../db.php';
// Just return the number of pending requests
$res = $conn->query("SELECT COUNT(*) as total FROM room_reservations WHERE status = 'Pending'");
$row = $res->fetch_assoc();
echo $row['total'];
?>