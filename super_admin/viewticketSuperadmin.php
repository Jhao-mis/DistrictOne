<?php

require '../vendor/autoload.php';
include '../db.php';
require 'login_verification.php';



$username = $_SESSION['username'];

// Fetch user details for admin display
$query = $conn->prepare("SELECT id, profile_picture, department, firstname, middlename, lastname, email FROM users WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->store_result();
$query->bind_result($user_id, $profile_picture, $department, $firstname, $middlename, $lastname, $email);
$query->fetch();
$query->close();

// Get the ticket ID from URL
if (!isset($_GET['ticket_id'])) {
  die("Invalid Ticket ID.");
}
$ticket_id = intval($_GET['ticket_id']);

// Fetch ticket details, including user department and assigned MIS personnel
$sql_ticket = "SELECT tickets.*, 
                      CONCAT(users.firstname, ' ', users.lastname) AS user_name,
                      users.department AS user_department,
                      CONCAT(mis_users.firstname, ' ', mis_users.lastname) AS mis_name 
               FROM tickets
               JOIN users ON tickets.user_id = users.id
               LEFT JOIN users AS mis_users ON tickets.assigned_to = mis_users.id
               WHERE tickets.id = ?";
$stmt_ticket = $conn->prepare($sql_ticket);
$stmt_ticket->bind_param("i", $ticket_id);
$stmt_ticket->execute();
$ticket_result = $stmt_ticket->get_result();
$ticket = $ticket_result->fetch_assoc();
$stmt_ticket->close();

if (!$ticket) {
  die("Ticket not found.");
}

// Fetch ticket history logs
$history_sql = "SELECT * FROM ticket_history WHERE ticket_id = ? ORDER BY timestamp DESC";
$history_stmt = $conn->prepare($history_sql);
$history_stmt->bind_param("i", $ticket_id);
$history_stmt->execute();
$history_logs = $history_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$history_stmt->close();

// Fetch MIS personnel list for the dropdown
$mis_query = $conn->query("SELECT id, CONCAT(firstname, ' ', lastname) AS name FROM users WHERE role = 'mis'");
$mis_personnel = $mis_query->fetch_all(MYSQLI_ASSOC);

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

  <meta name="description" content="" />

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="../assets/img/favicon/districtone.png" />

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
    rel="stylesheet" />

  <!-- Icons. Uncomment required icon fonts -->
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />

  <!-- Core CSS -->
  <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />
  <link rel="stylesheet" href="../css/user.css" />
  <link rel="stylesheet" href="./css/viewticketSuperadmin.css">

  <!-- Vendors CSS -->
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <link rel="stylesheet" href="../assets/vendor/libs/apex-charts/apex-charts.css" />

  <!-- Page CSS -->
  <link rel="stylesheet" href="../assets/vendor/css/pages/app-calendar.css">
  <!-- Helpers -->
  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/qtip2/3.0.3/jquery.qtip.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
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

  <!-- / Navbar -->

  <div class="main-container">
    <!-- Column 1: Ticket Information -->
    <div class="column">

      <div class="main-container">

        <!-- Column 1: Ticket Info -->
        <div class="column">
          <h3>Ticket Information</h3>
          <div class="ticket-detail">
            <!-- Rearranged the details -->
            <div class="ticket-header">
              <p class="ticket-number">
                <strong>Ticket Number:</strong> #<?= htmlspecialchars($ticket['id']); ?>
              </p>
              <p class="ticket-date">
                <strong><?= isset($ticket['created_at']) && !empty($ticket['created_at'])
                  ? 'Created at: ' . date('F d, Y h:i A', strtotime($ticket['created_at']))
                  : 'Created at: No record'; ?></strong>
              </p>
            </div>
            <p><strong>Submitted By:</strong> <?php echo htmlspecialchars($ticket['user_name']); ?></p>
            <p><strong>Department:</strong> <strong><?php echo htmlspecialchars($ticket['user_department']); ?></strong>
            </p>

            <p><strong>Subject:</strong> <?php echo htmlspecialchars($ticket['subject']); ?></p>

            <!-- Changed the Description field to a text field -->
            <label for="description">Description:</label>
            <textarea id="description" readonly><?php echo htmlspecialchars($ticket['description']); ?></textarea>

            <p><strong>Status:</strong>
              <span class="badge badge-<?php echo strtolower(str_replace(' ', '-', $ticket['status'])); ?>">
                <?php echo htmlspecialchars($ticket['status']); ?>
              </span>
            </p>
            <p><strong>Assigned To:</strong>
              <span class="badge badge-mis">
                <?php echo $ticket['mis_name'] ? htmlspecialchars($ticket['mis_name']) : 'Unassigned'; ?>
              </span>
            </p>
          </div>
        </div>

        <!-- Column 2: Admin Actions -->
        <div class="column">
          <h3>Actions</h3>
          <form action="./ticket/update_ticket.php" method="POST">
            <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>" />

            <label for="assigned_to">Assign MIS Personnel:</label>
            <select name="assigned_to" id="assigned_to">
              <option value="">Unassigned</option>
              <?php foreach ($mis_personnel as $mis):
                $selected = ($ticket['assigned_to'] == $mis['id']) ? 'selected' : ''; ?>
                <option value="<?php echo $mis['id']; ?>" <?php echo $selected; ?>>
                  <?php echo htmlspecialchars($mis['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>

            <label for="status">Update Status:</label>
            <select name="status" id="status">
              <option value="Pending" <?php echo ($ticket['status'] == 'Pending' ? 'selected' : ''); ?>>Pending</option>
              <option value="In Progress" <?php echo ($ticket['status'] == 'In Progress' ? 'selected' : ''); ?>>In
                Progress</option>
              <option value="Resolved" <?php echo ($ticket['status'] == 'Resolved' ? 'selected' : ''); ?>>Resolved
              </option>
              <!--<option value="Cancelled" <?php echo ($ticket['status'] == 'Cancelled' ? 'selected' : ''); ?>>Cancelled</option> -->
            </select>

            <label for="feedback">Feedback:</label>
            <textarea name="feedback"
              placeholder="Write your feedback..."><?php echo htmlspecialchars($ticket['feedback']); ?></textarea>

            <button type="submit" class="btn-submit">Update Ticket</button>
          </form>
          <a href="ticketRequest.php" class="btn-back">Back to Dashboard</a>
        </div>

        <!-- Column 3: History Logs -->
        <div class="column">
          <h3>History Logs</h3>
          <?php if (!empty($history_logs)): ?>
            <ul class="history-list">
              <?php foreach ($history_logs as $log): ?>
                <li>
                  <strong><?php echo htmlspecialchars($log['action']); ?></strong><br>
                  <em><?php echo date('F j, Y, g:i A', strtotime($log['timestamp'])); ?></em>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p>No history logs available.</p>
          <?php endif; ?>
        </div>

      </div>

      <script src="../assets/vendor/js/bootstrap.js"></script>
      <script src="../assets/vendor/js/menu.js"></script>
      <script src="../assets/js/main.js"></script>

      <script src="../assets/js/dashboards-analytics.js"></script>


      <script async defer src="https://buttons.github.io/buttons.js"></script>

      <script>
        document.addEventListener("DOMContentLoaded", function () {
          document.querySelectorAll(".btn-create").forEach(button => {
            button.addEventListener("click", function (event) {
              event.preventDefault(); // Prevent default link behavior

              var editModal = new bootstrap.Modal(document.getElementById("editModal"));
              editModal.show();
            });
          });
        });

  </body >
</html >