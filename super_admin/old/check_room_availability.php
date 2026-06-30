<?php
require '../db.php'; 

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $reservation_date = $_POST["date"];
    $start_time = $_POST["start"];
    $end_time = $_POST["end"];

    // Fetch rooms with overlapping approved reservations
    $sql = "SELECT room FROM room_reservations 
            WHERE status = 'Approved' 
            AND reservation_date = ?
            AND (
                (start_time < ? AND end_time > ?) OR
                (start_time < ? AND end_time > ?) OR
                (start_time >= ? AND end_time <= ?)
            )";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssss", 
        $reservation_date, $end_time, $end_time,
        $start_time, $start_time,
        $start_time, $end_time
    );
    $stmt->execute();
    $result = $stmt->get_result();

    $reserved_rooms = [];
    while ($row = $result->fetch_assoc()) {
        $reserved_rooms[] = $row['room'];
    }

    echo json_encode($reserved_rooms);
}
?>
