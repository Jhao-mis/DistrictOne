<?php
session_start();
require '../../vendor/autoload.php';
include '../../db.php';

// ✅ Ensure user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit();
}

$username = $_SESSION['username'];

// 🔍 Fetch user details
$query = $conn->prepare("SELECT id, profile_picture, cover_photo, department, firstname, middlename, lastname, email, role 
                         FROM users WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->store_result();
$query->bind_result($user_id, $profile_picture, $cover_photo, $department, $firstname, $middlename, $lastname, $email, $user_role);
$query->fetch();
$query->close();

// ✅ Ensure user details are properly stored in session
$_SESSION['user_id'] = $user_id;
$_SESSION['name'] = trim("$firstname $middlename $lastname") ?: "Unknown User";
$_SESSION['role'] = $user_role;

// 🔧 Pre-populate fields
$user_name = $_SESSION["name"];
$date_requested = date('Y-m-d');

// 🔥 Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $department = $_POST["department"];
    $department_other = trim($_POST["department_other"]);

    // ✅ Handle "Other" department input
    if ($department === "Other" && !empty($department_other)) {
        $department = $department_other;
    }

    // 🎯 Capture form data
    $reservation_date = $_POST["reservation_date"];
    $start_time = $_POST["start_time"];
    $end_time = $_POST["end_time"];
    $num_participants = $_POST["participants"];
    $purpose = trim($_POST["purpose"]);
    $room = $_POST["room"];

    // 🛑 Error check: Ensure purpose is filled
    if (empty($purpose)) {
        die("❌ Error: Purpose field is empty.");
    }

    // 🚀 Insert data into `room_reservations` table
    $stmt = $conn->prepare("INSERT INTO room_reservations 
        (user_id, department, reservation_date, start_time, end_time, participants, purpose, room, status, date_requested) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)");
    
    $stmt->bind_param(
        "issssisss",
        $user_id,
        $department,
        $reservation_date,
        $start_time,
        $end_time,
        $num_participants,
        $purpose,
        $room,
        $date_requested
    );

    // ✅ Handle execution results
    if ($stmt->execute()) {
        $message = "✅ Room reservation submitted successfully!";
    } else {
        $message = "❌ Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();

    // 🔄 Redirect to a success page
    header("Location: reservation_success.php");
    exit();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <title>Room Reservation</title>
    <link rel="stylesheet" href="../../css/room_reservation.css">
</head>
<body>

<div class="container">
    <h2>Room Reservation Request</h2>

    <?php if (isset($message)): ?>
        <p class="message"><?php echo $message; ?></p>
    <?php endif; ?>

    <form method="POST" class="reservation-form">
        <label>Date Requested</label>
        <input type="text" value="<?php echo $date_requested; ?>" readonly>

        <label>Requested By</label>
        <input type="text" value="<?php echo $user_name; ?>" readonly>

        <label>Department/Committee Requesting</label>
        <select name="department" required>
            <option value="Office of the General Manager">Office of the General Manager</option>
            <option value="Administrative Department">Administrative Department</option>
            <option value="Finance Department">Finance Department</option>
            <option value="Commercial Department">Commercial Department</option>
            <option value="Technical Services Department">Technical Services Department</option>
            <option value="Operations Department">Operations Department</option>
            <option value="Other">Other (Input Below)</option>
        </select>
        

        <label>Reservation Date</label>
        <input type="date" name="reservation_date" required>

        <label>Start Time</label>
        <input type="time" name="start_time" required>

        <label>End Time</label>
        <input type="time" name="end_time" required>

        <label>Number of Participants</label>
        <input type="number" name="participants" min="1" required>

        <label>Purpose of Meeting</label>
        <textarea name="purpose" placeholder="Enter meeting purpose" required></textarea>

        <label>Select Room</label>
        <select name="room" required>
            <option value="Training Room, 3rd Floor, CWD Main Building">Training Room, 3rd Floor, CWD Main Building</option>
            <option value="Multipurpose Hall 5th Floor CWD Main Building">Multipurpose Hall 5th Floor CWD Main Building</option>
            <option value="Conference Room, 2nd Floor, CWD Warehouse">Conference Room, 2nd Floor, CWD Warehouse</option>
            <option value="Conference Room, Operations Building, BPS Upper">Conference Room, Operations Building, BPS Upper</option>
            <option value="Roof Deck, Operations Building, BPS Upper">Roof Deck, Operations Building, BPS Upper</option>
        </select>

        <button type="submit" class="btn-submit">Submit Reservation</button>
    </form>

    <a href="../../user/room_reservation.php" class="btn-back">Back</a>
</div>

</body>
</html>
