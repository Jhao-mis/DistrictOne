<?php
include '../../db.php'; // your database connection file
include '../login_verification.php'; // your session file

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    $query = "UPDATE users SET isVerified = 1 WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        echo "<script>alert('Account activated successfully.'); window.location.href='../accountManagement.php';</script>";
    } else {
        echo "<script>alert('Error activating account.'); window.location.href='../accountManagement.php';</script>";
    }
} else {
    echo "<script>alert('No ID specified.'); window.location.href='../accountManagement.php';</script>";
}
?>
