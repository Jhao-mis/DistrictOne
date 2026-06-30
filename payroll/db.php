<?php
// Database credentials
$host = "localhost";       // Usually localhost
$db_name = "districtone"; // Replace with your DB name
$username = "root"; // Replace with your DB username
$password = "Cwdh2o@2025"; // Replace with your DB password

// Create connection
$conn = new mysqli($host, $username, $password, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Optional: set character set to UTF-8
$conn->set_charset("utf8");

?>
