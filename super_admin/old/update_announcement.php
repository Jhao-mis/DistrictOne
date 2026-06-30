<?php

require '../db.php';

if (isset($_POST['id'])) {
    $id = $_POST['id'];
    $title = $_POST['title'];
    $message = $_POST['message'];
    $department = $_POST['department'];

    $stmt = $conn->prepare("UPDATE announcements SET title = ?, message = ?, department = ? WHERE id = ?");
    $stmt->bind_param("sssi", $title, $message, $department, $id);

    if ($stmt->execute()) {
        echo "Announcement updated successfully!";
    } else {
        echo "Error updating announcement.";
    }
}


?>