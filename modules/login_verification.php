<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../lib/access_control.php';

// Check if user is logged in and has a role
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    // Redirect to session expired page
    header("Location: ../sessionExpired.php");
    exit();
}

if (!access_has_valid_role()) {
    header("Location: ../sessionExpired.php");
    exit();
}

access_require_current_request('../sessionExpired.php');
?>
