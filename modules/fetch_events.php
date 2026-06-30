<?php

require '../db.php';
require 'login_verification.php';

$username = $_SESSION['username'];

// Fetch user details
$query = $conn->prepare("SELECT department FROM users WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->store_result();
$query->bind_result($department);
$query->fetch();
$query->close();

// Fetch activities from the database
$stmt = $pdo->query("SELECT * FROM activities ORDER BY start_datetime");
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch approved room reservations
$stmt_reservations = $pdo->query("
    SELECT rr.*, u.firstname, u.middlename, u.lastname 
    FROM room_reservations rr
    JOIN users u ON rr.user_id = u.id
    WHERE rr.status = 'Approved'
    ORDER BY rr.reservation_date
");
$reservations = $stmt_reservations->fetchAll(PDO::FETCH_ASSOC);

$events = [];
$blueColor = '#2196f3'; // Blue color for all events

// Add activities
foreach ($activities as $activity) {
    $eventDepartment = $activity['department'] ?? "All Departments";

    if ($eventDepartment == "All Departments" || $eventDepartment == $department) {
        $events[] = [
            'id' => $activity['id'],
            'title' => $activity['title'],
            'start' => $activity['start_datetime'],
            'end' => $activity['end_datetime'],
            'description' => $activity['description'],
            'color' => $blueColor,
            'textColor' => '#ffffff', // white text for contrast
            'department' => $eventDepartment,
            'event_url' => $activity['event_url'],
            'event_location' => $activity['event_location']
        ];
    }
}

// Add room reservations
foreach ($reservations as $reservation) {
    $events[] = [
        'id' => 'res-' . $reservation['id'],
        'title' => 'Room Reserved: ' . $reservation['room'],
        'start' => $reservation['reservation_date'] . ' ' . $reservation['start_time'],
        'end' => $reservation['reservation_date'] . ' ' . $reservation['end_time'],
        'description' => $reservation['purpose'],
        'department' => $reservation['department'] ?? 'N/A',
        'event_location' => $reservation['room'],
        'event_url' => '',
        'color' => $blueColor,
        'textColor' => '#ffffff'
    ];
}

echo json_encode($events);
?>
