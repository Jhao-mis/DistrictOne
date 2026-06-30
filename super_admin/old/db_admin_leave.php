<?php
require '../db.php';
require 'login_verification.php';

$username = $_SESSION['username'] ?? '';
if (!$username) die("Not logged in.");

// Connect to DB
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("DB connection failed: " . $conn->connect_error);

// Get user with position & salary from personal_data_sheet
$stmt = $conn->prepare("
    SELECT u.id, u.firstname, u.middlename, u.lastname, u.department, 
           p.position, p.salary
    FROM users u
    LEFT JOIN personal_data_sheet p ON u.id = p.user_id
    WHERE u.username = ?
");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($user_id, $firstname, $middlename, $lastname, $department, $position, $salary);
if (!$stmt->fetch()) die("User not found.");
$stmt->close();

// Fetch all leaves of this user
$stmt = $conn->prepare("SELECT * FROM leave_requests WHERE user_id=? ORDER BY date_of_filing DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_leaves = [];
while ($row = $result->fetch_assoc()) {
    $user_leaves[] = $row;
}
$result->free();
$stmt->close();

$conn->close();
?>
