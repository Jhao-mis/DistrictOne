<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has a role
if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    // Redirect to session expired page
    header("Location: sessionExpired.php");
    exit();
}

// Define allowed roles (if not defined by the page)
if (!isset($allowed_roles)) {
    // By default, all roles can access unless restricted
    $allowed_roles = ['User', 'MIS', 'Admin', 'Super Admin'];
}

// Normalize role name (to avoid case sensitivity issues)
$userRole = strtolower(trim($_SESSION['role']));
$allowedRolesLower = array_map('strtolower', $allowed_roles);

// Check if user’s role is allowed
if (!in_array($userRole, $allowedRolesLower)) {
    // Redirect to sessionExpired.php for unauthorized access
    header("Location: sessionExpired.php");
    exit();
}
?>
