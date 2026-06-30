<?php
include '../../db.php'; // your database connection file
include '../login_verification.php'; // your session file

if (isset($_GET['id']) && isset($_GET['status'])) {
    $id = intval($_GET['id']);
    $status = intval($_GET['status']); // 0 or 1

    $query = "UPDATE users SET isVerified = $status WHERE id = $id";

    if (mysqli_query($conn, $query)) {
        // Redirect to acc.php with success action and status
        header("Location: ../accountManagement.php?status_changed=$status");
        exit();
    } else {
        // Redirect with error flag
        header("Location: ../accountManagement.php?action=error");
        exit();
    }
} else {
    // Redirect with invalid flag
    header("Location: ../accountManagement.php?action=invalid");
    exit();
}
?>
