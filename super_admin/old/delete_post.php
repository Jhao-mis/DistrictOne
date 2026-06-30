<?php

require '../vendor/autoload.php';
require './login_verification.php';
require '../db.php';

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}



// Check if delete request was sent
if (isset($_POST['delete']) && isset($_POST['id'])) {
    $post_id = $_POST['id'];

    // First, get the file path if there's an attachment
    $stmt = $conn->prepare("SELECT file_path FROM announcements WHERE id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $stmt->bind_result($file_path);
    $stmt->fetch();
    $stmt->close();

    // Delete file if it exists
    if (!empty($file_path) && file_exists($file_path)) {
        unlink($file_path); // Remove the file from the server
    }

    // Now delete the announcement from the database
    $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->bind_param("i", $post_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Announcement deleted successfully.";
    } else {
        $_SESSION['error'] = "Failed to delete the announcement.";
    }
    $stmt->close();
}

// Redirect back to the announcements page
header("Location: super_admin_announcement.php");
exit();
?>
