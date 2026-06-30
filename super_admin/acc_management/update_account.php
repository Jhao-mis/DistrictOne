<?php
include '../../db.php'; // your database connection file
include '../login_verification.php'; // your session file

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $firstname = $_POST['firstname'];
    $middlename = $_POST['middlename'];
    $lastname = $_POST['lastname'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $department = $_POST['department'];
    $role = $_POST['role'];

    $query = "UPDATE users SET 
        firstname='$firstname', 
        middlename='$middlename', 
        lastname='$lastname', 
        username='$username', 
        email='$email', 
        department='$department', 
        role='$role' 
        WHERE id=$id";

    if (mysqli_query($conn, $query)) {
        echo "<script>alert('Account updated successfully!'); window.location.href='../acc.php';</script>";
    } else {
        echo "Error updating account: " . mysqli_error($conn);
    }
}

header("Location: edit_account.php?id=$id&updated=1");
exit();

?>
