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

// Fetch events from the database
$stmt = $pdo->query("SELECT * FROM activities ORDER BY start_datetime");
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Department color mapping
$departmentColors = [
    "All Departments" => "#d1d0cf",
    "Office of the General Manager" => "#b0f5f5",
    "Management Information Services Section" => "#fcc6c0",
    "Administrative Department" => "#cff799",
    "Finance Department" => "#3cc74a",
    "Commercial Department" => "#f8fc9d",
    "Technical Services Department" => "#cba5f2",
    "Operations Department" => "#fab07a"
];

$events = [];
foreach ($activities as $activity) {
    $eventDepartment = $activity['department'] ?? "All Departments";

    // Only add events that belong to the user's department OR "All Departments"
    if ($eventDepartment == "All Departments" || $eventDepartment == $department) {
        $events[] = [
            'id' => $activity['id'],
            'title' => $activity['title'],
            'start' => $activity['start_datetime'],
            'end' => $activity['end_datetime'],
            'description' => $activity['description'],
            'color' => $departmentColors[$eventDepartment] ?? "#b0f5f5",
            'department' => $eventDepartment,
            'event_url' => $activity['event_url'],
            'event_location' => $activity['event_location']
        ];
    }
}

echo json_encode($events);
?>
