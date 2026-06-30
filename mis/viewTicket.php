<?php
require '../vendor/autoload.php';
include '../db.php';
require 'login_verification.php';

// Get the logged-in MIS user
$username = $_SESSION['username'];

// Fetch user details for MIS display
$query = $conn->prepare("SELECT id, profile_picture, department, firstname, middlename, lastname, email FROM users WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->store_result();
$query->bind_result($user_id, $profile_picture, $department, $firstname, $middlename, $lastname, $email);
$query->fetch();
$query->close();

$mis_fullname = trim($firstname . " " . $lastname);

// Get the ticket ID from URL
if (!isset($_GET['ticket_id'])) {
  die("Invalid Ticket ID.");
}
$ticket_id = intval($_GET['ticket_id']);

// Fetch ticket details
$sql_ticket = "SELECT tickets.*, 
                      tickets.assigned_to,
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

// ✅ Update ticket logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
  $new_status = $_POST['status'];
  $new_feedback = $_POST['feedback'];

  $log_actions = [];

  // 1. If ticket was unassigned → assign to current MIS
  if (empty($ticket['assigned_to']) || $ticket['assigned_to'] == 0) {
    $assign_sql = "UPDATE tickets SET assigned_to = ?, status = ?, feedback = ? WHERE id = ?";
    $assign_stmt = $conn->prepare($assign_sql);
    $assign_stmt->bind_param("issi", $user_id, $new_status, $new_feedback, $ticket_id);
    $assign_stmt->execute();
    $assign_stmt->close();

    $log_actions[] = "Ticket was taken by $mis_fullname";

    // Only log status if different
    if ($ticket['status'] !== $new_status) {
      $log_actions[] = "Status changed to '$new_status' by $mis_fullname";
    }

    // Only log feedback if different
    if ($ticket['feedback'] !== $new_feedback) {
      $log_actions[] = "Feedback by $mis_fullname: \"$new_feedback\"";
    }
  } else {
    // 2. Ticket already assigned → just update values
    $update_sql = "UPDATE tickets SET status = ?, feedback = ? WHERE id = ? AND assigned_to = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssii", $new_status, $new_feedback, $ticket_id, $user_id);
    $update_stmt->execute();
    $update_stmt->close();

    // Only log status if changed
    if ($ticket['status'] !== $new_status) {
      $log_actions[] = "Status changed to '$new_status' by $mis_fullname";
    }

    // Only log feedback if changed
    if ($ticket['feedback'] !== $new_feedback) {
      $log_actions[] = "Feedback by $mis_fullname: \"$new_feedback\"";
    }
  }

  // ✅ Insert only the logs for actual changes
  foreach ($log_actions as $action) {
    $history_sql = "INSERT INTO ticket_history (ticket_id, action, action_by, timestamp) VALUES (?, ?, ?, NOW())";
    $history_stmt = $conn->prepare($history_sql);
    $history_stmt->bind_param("isi", $ticket_id, $action, $user_id);
    $history_stmt->execute();
    $history_stmt->close();
  }

  header("Location: viewTicket.php?ticket_id=" . $ticket_id . "&success=1");
  exit;
}

// ✅ Handle Get Ticket
if (isset($_POST['get_ticket'])) {
  $assign_sql = "UPDATE tickets SET assigned_to = ? WHERE id = ? AND (assigned_to IS NULL OR assigned_to = 0)";
  $assign_stmt = $conn->prepare($assign_sql);
  $assign_stmt->bind_param("ii", $user_id, $ticket_id);
  $assign_stmt->execute();
  $assign_stmt->close();

  // Log the action with real name
  $action = "Ticket was taken by " . $mis_fullname;
  $history_sql = "INSERT INTO ticket_history (ticket_id, action, timestamp) VALUES (?, ?, NOW())";
  $history_stmt = $conn->prepare($history_sql);
  $history_stmt->bind_param("is", $ticket_id, $action);
  $history_stmt->execute();
  $history_stmt->close();

  header("Location: viewTicket.php?ticket_id=" . $ticket_id . "&assigned=1");
  exit;
}

// Fetch ticket history
$history_sql = "SELECT * FROM ticket_history WHERE ticket_id = ? ORDER BY timestamp DESC";
$history_stmt = $conn->prepare($history_sql);
$history_stmt->bind_param("i", $ticket_id);
$history_stmt->execute();
$history_logs = $history_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$history_stmt->close();

$conn->close();
?>

<!DOCTYPE html>

<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
  data-assets-path="../../assets/" data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
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
  <link rel="stylesheet" href="./css/viewTicket.css">

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

    case 'super_admin':
      include '../super_admin/sidebar.php';
      break;

    default:
      echo "<p>Unauthorized role.</p>";
      exit;
  }
  ?>

  <div class="content-wrapper">
    <!-- Content -->
    <div class="container-xxl flex-grow-1 container-p-y">
      <div class="main-container">

        <!-- Column 1: Ticket Info -->
        <div class="column">
          <h3>Ticket Information</h3>
          <div class="ticket-detail">
            <p><strong>Ticket Number:</strong> #<?php echo htmlspecialchars($ticket['id']); ?></p>
            <p><strong>Submitted By:</strong> <?php echo htmlspecialchars($ticket['user_name']); ?></p>
            <p><strong>Department:</strong> <strong><?php echo htmlspecialchars($ticket['user_department']); ?></strong>
            </p>

            <p><strong>Subject:</strong> <?php echo htmlspecialchars($ticket['subject']); ?></p>

            <!-- Description field -->
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

        <!-- Column 2: MIS Actions -->
        <div class="column">
          <h3>Actions</h3>

          <?php if (empty($ticket['assigned_to']) || $ticket['assigned_to'] == 0): ?>
            <!-- Show ONLY Get Ticket button if unassigned -->
            <form action="viewTicket.php?ticket_id=<?php echo $ticket['id']; ?>" method="POST">
              <input type="hidden" name="get_ticket" value="1">
              <button type="submit" class="btn-submit" style="background-color: #28a745;">
                Get Ticket
              </button>
            </form>

          <?php elseif ($ticket['assigned_to'] == $user_id): ?>
            <!-- Show Update form ONLY if ticket is assigned to the logged-in MIS -->
            <form action="viewTicket.php?ticket_id=<?php echo $ticket['id']; ?>" method="POST">
              <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>" />

              <label for="status">Update Status:</label>
              <select name="status" id="status">
                <option value="Pending" <?php echo ($ticket['status'] == 'Pending' ? 'selected' : ''); ?>>Pending</option>
                <option value="In Progress" <?php echo ($ticket['status'] == 'In Progress' ? 'selected' : ''); ?>>In
                  Progress</option>
                <option value="Resolved" <?php echo ($ticket['status'] == 'Resolved' ? 'selected' : ''); ?>>Resolved
                </option>
              </select>

              <label for="feedback">Feedback:</label>
              <textarea name="feedback" placeholder="Write your feedback..."
                required><?php echo htmlspecialchars($ticket['feedback']); ?></textarea>

              <button type="submit" class="btn-submit">Update Ticket</button>
            </form>

          <?php else: ?>
            <!-- If assigned to another MIS, lock actions -->
            <p><em>This ticket is assigned to <?php echo htmlspecialchars($ticket['mis_name']); ?>. You cannot update
                it.</em></p>
          <?php endif; ?>

          <a href="ticketAssign.php" class="btn-back">Back to Dashboard</a>
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
      </script>
</body>

</html>