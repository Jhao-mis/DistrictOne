<?php
session_start();
include '../../db.php';

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $subject = $_POST["subject"];
    $description = $_POST["description"];

    // Get user details from the updated users table
    $user_query = $conn->prepare("SELECT firstname, middlename, lastname, department FROM users WHERE id = ?");
    $user_query->bind_param("i", $user_id);
    $user_query->execute();
    $user_result = $user_query->get_result();

    if ($user_result->num_rows > 0) {
        $user_data = $user_result->fetch_assoc();
        $fullname = $user_data['firstname'] . ' ' . $user_data['middlename'] . ' ' . $user_data['lastname'];
        $department = $user_data['department'];

        // Insert the ticket with user details
        $stmt = $conn->prepare("INSERT INTO tickets (user_id, fullname, department, subject, description, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
        $stmt->bind_param("issss", $user_id, $fullname, $department, $subject, $description);

        if ($stmt->execute()) {
            $message = "✅ Ticket submitted successfully!";
        } else {
            $message = "❌ Error: " . $stmt->error;
        }
    } else {
        $message = "❌ Error: User details not found.";
    }

    $user_query->close();
    $stmt->close();
    $conn->close();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create a Ticket</title>
    <link rel="stylesheet" href="../../css/create_ticket.css">
</head>
<body>

<div class="container">
    <h2>✅ Ticket submitted successfully!</h2>

    <?php if (isset($message)): ?>
        <p class="message"><?php echo $message; ?></p>
    <?php endif; ?>

    <form method="POST" class="ticket-form">
        <label for="subject">Please wait for the admin to review your ticket</label> <br><br>
        
    </form>

    

    <a href="../../user/client_dashboard.php" class="btn btn-back">Back</a>
</div>

</body>
</html>
