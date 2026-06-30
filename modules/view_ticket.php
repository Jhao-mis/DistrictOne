<?php
// Start session & connect to database
session_start();
require_once '../db.php';

$username = $_SESSION['username'];

date_default_timezone_set('Asia/Manila'); // Set timezone to your local
$now = date('Y-m-d H:i:s');
$updateActivity = $conn->prepare("UPDATE users SET last_activity = ? WHERE username = ?");
$updateActivity->bind_param("ss", $now, $username);
$updateActivity->execute();
$updateActivity->close();

// ✅ Fetch user details
$query = $conn->prepare("SELECT id, profile_picture, cover_photo, department, firstname, middlename, lastname, email 
                         FROM users WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->store_result();
$query->bind_result($user_id, $profile_picture, $cover_photo, $department, $firstname, $middlename, $lastname, $email);
$query->fetch();
$query->close();

// ✅ Ensure user_id is set properly
if (!$user_id) {
  die("❌ Error: User details not found.");
}
$_SESSION['user_id'] = $user_id;

// Check if 'ticket_id' is provided
if (!isset($_GET['ticket_id']) || empty($_GET['ticket_id'])) {
  die("<p style='color:red; text-align:center;'>❌ Error: Ticket ID is missing or invalid.</p>");
}

$ticket_id = intval($_GET['ticket_id']); // Ensure ticket_id is an integer

// ✅ Adjusted query: handles tickets without "assigned_to"
$query = "SELECT tickets.*, 
                 CONCAT(users.firstname, ' ', IFNULL(users.middlename, ''), ' ', users.lastname) AS mis_name, 
                 users.department AS mis_department 
          FROM tickets 
          LEFT JOIN users ON tickets.assigned_to = users.id 
          WHERE tickets.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$result = $stmt->get_result();

// 🛑 Check if ticket exists
if ($result->num_rows === 0) {
  die("<p style='color:red; text-align:center;'>❌ Error: No ticket found with ID: $ticket_id</p>");
}

$ticket = $result->fetch_assoc();

// 🕒 Fetch ticket history with timestamps
$history_query = "SELECT ticket_history.*, 
                         CONCAT(users.firstname, ' ', IFNULL(users.middlename, ''), ' ', users.lastname) AS action_by 
                  FROM ticket_history 
                  LEFT JOIN users ON ticket_history.action_by = users.id 
                  WHERE ticket_history.ticket_id = ? 
                  ORDER BY ticket_history.timestamp DESC";
$stmt = $conn->prepare($history_query);
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$history_result = $stmt->get_result();

// ✅ Close everything
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/"
  data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>View Ticket</title>

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="../assets/img/favicon/districtone.png" />

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
    rel="stylesheet" />

  <!-- Icons -->
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />

  <!-- Core CSS -->
  <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />
  <link rel="stylesheet" href="../css/user.css" />
  <link rel="stylesheet" href="./css/ticket.css">

  <!-- ✅ Our merged ticket styles -->
  <link rel="stylesheet" href="../css/ticket.css" />

  <!-- Vendors CSS -->
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <link rel="stylesheet" href="../assets/vendor/libs/apex-charts/apex-charts.css" />
  <link rel="stylesheet" href="../../assets/vendor/css/pages/app-calendar.css">

  <!-- Helpers -->
  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>

  <!-- FullCalendar -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

  <!-- Old FullCalendar + QTip (only if still used) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/qtip2/3.0.3/jquery.qtip.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qtip2/3.0.3/jquery.qtip.min.js"></script>

</head>

<body>
  <?php $role = $_SESSION['role'];

    switch ($role) {
        case 'User':
            include '../user/sidebar.php';
            break;

        case 'mis':
            include '../mis/sidebar.php';
            break;

        case 'Admin':
            include '../admin/sidebar.php';
            break;

        case 'Super Admin':
            include '../super_admin/sidebar.php';
            break;

        default:
            echo "<p>Unauthorized role.</p>";
            exit;
    }
    ?>

  <div class="content-wrapper">
    <div class="container-xxl flex-grow-1 container-p-y">

      <!-- MAIN CONTENT -->
      <div class="main-container">
        <!-- Column 1: Ticket Information -->
        <div class="column">
          <h3>Ticket Information</h3>
          <div class="ticket-detail">
            <p><strong>Ticket Number:</strong> <?= htmlspecialchars($ticket['id']); ?></p>
            <p><strong>Status:</strong>
              <span class="badge 
                <?php
                if ($ticket['status'] == 'Resolved')
                  echo 'badge-resolved';
                elseif ($ticket['status'] == 'In Progress')
                  echo 'badge-in-progress';
                elseif ($ticket['status'] == 'Pending')
                  echo 'badge-pending';
                else
                  echo 'badge-cancelled';
                ?>">
                <?= htmlspecialchars($ticket['status']); ?>
              </span>
            </p>
            <p><strong>Assigned to:</strong> <?= htmlspecialchars($ticket['mis_name'] ?? 'Not Assigned'); ?></p>
            <p><strong>Subject:</strong> <?= htmlspecialchars($ticket['subject']); ?></p>

            <label for="ticket-description"><strong>Description:</strong></label>
            <textarea id="ticket-description" readonly><?= htmlspecialchars($ticket['description']); ?></textarea>
          </div>

          <!-- Back Button -->
          <div class="btn-container">
            <a href="serviceRequest.php" class="btn-back">Back to Dashboard</a>
          </div>
        </div>

        <!-- Column 2: History Logs -->
        <div class="column">
          <h3>History Timeline</h3>
          <?php if ($history_result->num_rows > 0): ?>
            <ul class="history-list">
              <?php while ($row = $history_result->fetch_assoc()): ?>
                <li><strong><?= $row['timestamp']; ?>:</strong> <?= htmlspecialchars($row['action']); ?></li>
              <?php endwhile; ?>
            </ul>
          <?php else: ?>
            <p>No history records found for this ticket.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- JS -->
  <script src="../assets/vendor/js/bootstrap.js"></script>
  <script src="../assets/vendor/js/menu.js"></script>
  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/dashboards-analytics.js"></script>
  <script async defer src="https://buttons.github.io/buttons.js"></script>

  <script>
    document.addEventListener("DOMContentLoaded", function () {
      document.querySelectorAll(".btn-create").forEach(button => {
        button.addEventListener("click", function (event) {
          event.preventDefault();
          var editModal = new bootstrap.Modal(document.getElementById("editModal"));
          editModal.show();
        });
      });
    });
  </script>



</body>

</html>