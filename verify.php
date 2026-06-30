<?php
session_start();
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Database credentials from .env
$host = $_ENV['DB_HOST'];
$db = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASS'];

// MySQLi connection
$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die("MySQLi Connection failed: " . mysqli_connect_error());
}

// Optional: PDO connection if you need it elsewhere
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("PDO Connection failed: " . $e->getMessage());
}

// Verification logic
if (isset($_GET['verification'])) {
    $VerificationCode = $_GET['verification'];

    $query = "SELECT * FROM users WHERE VerificationCode = ? AND isVerified = 0";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 's', $VerificationCode);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        $updateQuery = "UPDATE users SET isVerified = 1 WHERE VerificationCode = ?";
        $updateStmt = mysqli_prepare($conn, $updateQuery);
        mysqli_stmt_bind_param($updateStmt, 's', $VerificationCode);
        mysqli_stmt_execute($updateStmt);

        $_SESSION['alertType'] = 'success';
        $_SESSION['alertText'] = 'Verified Account';
        $_SESSION['redirectFrom'] = 'verify';
    } else {
        $_SESSION['alertType'] = 'error';
        $_SESSION['alertText'] = 'Invalid or expired verification link.';
        $_SESSION['redirectFrom'] = 'verify';
    }
    exit();
}
?>