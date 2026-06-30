<?php
require '../db.php';




if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ensure all required fields exist
    if (!isset($_POST['id'], $_POST['title'], $_POST['event_url'], $_POST['event_location'], $_POST['activity_date'], $_POST['activity_end_date'], $_POST['start_time'], $_POST['end_time'], $_POST['description'])) {
        echo json_encode(["status" => "error", "message" => "Missing required fields."]);
        exit();
    }

    $id = $_POST['id'];
    $title = $_POST['title'];
    $event_url = $_POST['event_url'];
    $event_location = $_POST['event_location'];
    $activity_date = $_POST['activity_date'];
    $activity_end_date = $_POST['activity_end_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $description = $_POST['description'];

    $start_datetime = $activity_date . ' ' . $start_time;
    $end_datetime = $activity_end_date . ' ' . $end_time;

    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM activities WHERE id = ?");
    $stmt_check->execute([$id]);
    if ($stmt_check->fetchColumn() == 0) {
        echo json_encode(["status" => "error", "message" => "Event ID not found."]);
        exit();
    }

    try {
        $stmt = $pdo->prepare("UPDATE activities SET title = ?, event_url = ?, event_location = ?, activity_end_date = ?, start_datetime = ?, end_datetime = ?, description = ? WHERE id = ?");
        $result = $stmt->execute([$title, $event_url, $event_location, $activity_end_date, $start_datetime, $end_datetime, $description, $id]);
    
        if ($result) {
            echo json_encode(["status" => "success", "message" => "Updated successfully."]);
        } else {
            file_put_contents("log.txt", "SQL Execution failed\n", FILE_APPEND);
            echo json_encode(["status" => "error", "message" => "Database update failed."]);
        }
    } catch (PDOException $e) {
        file_put_contents("log.txt", "SQL Error: " . $e->getMessage() . "\n", FILE_APPEND);
        echo json_encode(["status" => "error", "message" => "SQL Error: " . $e->getMessage()]);
    }
    
}
?>
