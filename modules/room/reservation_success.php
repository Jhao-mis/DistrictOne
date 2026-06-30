<?php
session_start();

// Ensure the user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Successful</title>
    <link rel="stylesheet" href="../../css/reservation_success.css">
</head>
<body>

<div class="container">
    <h2>Room Reservation Submitted!</h2>
    <p>Your room reservation request has been successfully submitted.</p>
    <p>Please wait for approval from the General Manager or MIS Supervisor.</p>

    <a href="../../user/room_reservation.php" class="btn btn-back">Back to Dashboard</a>
</div>

</body>
</html>
