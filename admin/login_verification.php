<?php
// Start session only if it's not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and role is set
if (!isset($_SESSION["username"]) || !isset($_SESSION['role'])) {
    // Redirect to session expired page
    header("Location: ../sessionExpired.php");
    exit();
}

// Define allowed roles (optional, for flexibility)
$allowed_roles = isset($allowed_roles) ? $allowed_roles : ['Admin'];

// Normalize role names to avoid case issues
$userRole = strtolower(trim($_SESSION['role']));
$allowedRolesLower = array_map('strtolower', $allowed_roles);

// Check if the user's role is allowed
if (!in_array($userRole, $allowedRolesLower)) {
    // Redirect to session expired page for unauthorized access
    header("Location: ../sessionExpired.php");
    exit();
}
?>
