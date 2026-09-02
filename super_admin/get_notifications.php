<?php
require 'login_verification.php'; // restricts this endpoint to logged-in Super Admins
include '../db.php';

$notifications = [];

// --------------------
// Fetch pending tickets with requestor info
// --------------------
$ticketSql = "
SELECT 
    t.id, 
    t.subject, 
    t.created_at,
    u.firstname, 
    u.lastname
FROM tickets t
JOIN users u ON t.user_id = u.id
WHERE t.status = 'Pending' 
  AND t.admin_approved = 0
";

$ticketResult = $conn->query($ticketSql);

if ($ticketResult) {
    while ($row = $ticketResult->fetch_assoc()) {
        $notifications[] = [
            'id' => 'ticket-' . $row['id'],
            'type' => 'Ticket Request',
            'subject' => $row['subject'],
            'time' => $row['created_at'],
            'link' => '../super_admin/approveTicket.php?id=' . $row['id'],
            'firstname' => $row['firstname'],
            'lastname' => $row['lastname'],
            'created_at' => $row['created_at']
        ];
    }
}

// --------------------
// Fetch pending room reservations with requestor info
// --------------------
$reservationSql = "
SELECT 
    r.id,
    r.room AS subject,  -- room name as subject
    r.date_requested AS created_at,
    u.firstname,
    u.lastname
FROM room_reservations r
JOIN users u ON r.user_id = u.id
WHERE r.status = 'Pending'
";

$reservationResult = $conn->query($reservationSql);

if ($reservationResult) {
    while ($row = $reservationResult->fetch_assoc()) {
        $notifications[] = [
            'id' => 'reservation-' . $row['id'],
            'type' => 'Room Reservation',
            'subject' => $row['subject'],
            'time' => $row['created_at'],
            'link' => '../super_admin/approveRoom.php?id=' . $row['id'],
            'firstname' => $row['firstname'],
            'lastname' => $row['lastname'],
            'created_at' => $row['created_at']
        ];
    }
}

// --------------------
// Fetch users pending account verification/approval
// (kept in sync with the $unverifiedCount badge in sidebar.php)
// --------------------
// Some installs may not have a created_at column on users, so this
// query is attempted defensively — if it fails, we fall back to a
// version without it rather than breaking the whole endpoint.
$accountSql = "
SELECT 
    id,
    firstname,
    lastname,
    created_at
FROM users
WHERE isVerified = 0
";

try {
    $accountResult = $conn->query($accountSql);
    $accountHasCreatedAt = true;
} catch (mysqli_sql_exception $e) {
    // users table has no created_at column — retry without it
    $accountSqlFallback = "
    SELECT 
        id,
        firstname,
        lastname
    FROM users
    WHERE isVerified = 0
    ";
    $accountResult = $conn->query($accountSqlFallback);
    $accountHasCreatedAt = false;
}

if ($accountResult) {
    while ($row = $accountResult->fetch_assoc()) {
        $createdAt = $accountHasCreatedAt ? $row['created_at'] : date('Y-m-d H:i:s');
        $notifications[] = [
            'id' => 'account-' . $row['id'],
            'type' => 'Account Management',
            'subject' => 'Pending account verification',
            'time' => $createdAt,
            'link' => '../super_admin/accountManagement.php?id=' . $row['id'],
            'firstname' => $row['firstname'],
            'lastname' => $row['lastname'],
            'created_at' => $createdAt
        ];
    }
}

// --------------------
// Sort notifications by newest first
// --------------------
usort($notifications, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Total pending count across ALL categories (drives the sidebar badge)
$totalCount = count($notifications);

// Limit what's actually listed in the dropdown/toasts to the latest 10,
// but keep reporting the true total in "count" so the badge stays accurate.
$notifications = array_slice($notifications, 0, 10);

// Return JSON
header('Content-Type: application/json');
echo json_encode([
    "count" => $totalCount,
    "items" => $notifications
]);