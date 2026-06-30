<?php
include '../db.php';

// --------------------
// Fetch pending tickets with requestor's department only
// --------------------
$ticketSql = "
SELECT 
    t.id, 
    t.subject, 
    t.created_at,
    u.department
FROM tickets t
JOIN users u ON t.user_id = u.id
WHERE t.status = 'Pending' 
  AND t.admin_approved = 0
";

$ticketResult = $conn->query($ticketSql);

$notifications = [];
while ($row = $ticketResult->fetch_assoc()) {
    $notifications[] = [
        'type' => 'Ticket Request',
        'subject' => $row['subject'],
        'time' => $row['created_at'],
        'link' => '../super_admin/approveTicket.php?id=' . $row['id'],
        'department' => $row['department'], // only department now
        'created_at' => $row['created_at']
    ];
}

// --------------------
// Sort notifications by newest first
// --------------------
usort($notifications, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Limit to latest 10 notifications
$notifications = array_slice($notifications, 0, 10);

// Return JSON
header('Content-Type: application/json');
echo json_encode([
    "count" => count($notifications),
    "items" => $notifications
]);
?>
