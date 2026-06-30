<?php
session_start();
include('../../db.php');


// Ensure the user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

// Fetch user details from the users table
$user_id = $_SESSION["user_id"];
$user_query = $conn->prepare("SELECT firstname, middlename, lastname, department FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();

if ($user_result->num_rows > 0) {
    $user_data = $user_result->fetch_assoc();
    $fullname = $user_data['firstname'] . ' ' . $user_data['middlename'] . ' ' . $user_data['lastname'];
    $department = $user_data['department']; // Correct department retrieval
} else {
    $department = "Unknown Department";
    $fullname = "Unknown User";
}

$user_query->close();

// Handle ticket submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $subject = $_POST["subject"];
    $description = $_POST["description"];

    // Insert ticket with department and user details
    $stmt = $conn->prepare("INSERT INTO tickets (user_id, fullname, department, subject, description, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
    $stmt->bind_param("issss", $user_id, $fullname, $department, $subject, $description);

    if ($stmt->execute()) {
        $message = "✅ Ticket submitted successfully!";
    } else {
        $message = "❌ Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();

    header("Location: create_success.php");
    exit();
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
    <h2>Submit a New Ticket</h2>

    <?php if (isset($message)): ?>
        <p class="message"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form method="POST" class="ticket-form">
        <label for="subject">Ticket Subject</label>
        <input type="text" name="subject" id="subject" placeholder="Enter subject..." required>

        <label for="description">Describe your issue</label>
        <textarea name="description" id="description" placeholder="Enter details..." required></textarea>

        <button type="submit" class="btn btn-submit">Submit Ticket</button>
    </form>

    <a href="../../user/client_dashboard.php" class="btn btn-back">Back to Dashboard</a>
</div>

</body>
</html>
