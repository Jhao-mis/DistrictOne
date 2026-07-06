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

// Helper: pick an icon + tone for a history log action (purely cosmetic, doesn't touch the data)
function tkt_history_icon($action) {
  $action_lc = strtolower($action);
  if (strpos($action_lc, 'assign') !== false) {
    return ['icon' => 'bx-user-plus', 'tone' => 'blue'];
  } elseif (strpos($action_lc, 'resolv') !== false) {
    return ['icon' => 'bx-check-circle', 'tone' => 'green'];
  } elseif (strpos($action_lc, 'progress') !== false) {
    return ['icon' => 'bx-loader-circle', 'tone' => 'amber'];
  } elseif (strpos($action_lc, 'cancel') !== false) {
    return ['icon' => 'bx-x-circle', 'tone' => 'red'];
  } elseif (strpos($action_lc, 'creat') !== false || strpos($action_lc, 'submit') !== false) {
    return ['icon' => 'bx-plus-circle', 'tone' => 'gray'];
  }
  return ['icon' => 'bx-message-detail', 'tone' => 'gray'];
}

$status_slug = strtolower(str_replace(' ', '-', $ticket['status']));
?>

<!DOCTYPE html>

<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/"
  data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>Ticket #<?= htmlspecialchars($ticket['id']); ?> — View Ticket</title>

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

  <style>
    :root {
      --tkt-bg: #f4f6f9;
      --tkt-surface: #ffffff;
      --tkt-border: #e6e9ef;
      --tkt-text: #1f2430;
      --tkt-muted: #7c8494;
      --tkt-faint: #a6acb9;
      --tkt-primary: #4f7cff;
      --tkt-primary-soft: #edf2ff;
      --tkt-primary-deep: #2f57d6;
      --tkt-radius: 14px;
      --tkt-radius-sm: 10px;
      --tkt-shadow: 0 1px 2px rgba(20,20,43,.04), 0 10px 26px -14px rgba(20,20,43,.14);
      --tkt-amber: #b8860b;
      --tkt-amber-soft: #fdf3dc;
      --tkt-blue: #2563a8;
      --tkt-blue-soft: #e7f1ff;
      --tkt-green: #1b7a3d;
      --tkt-green-soft: #e6f7ec;
      --tkt-red: #b91c1c;
      --tkt-red-soft: #fdecec;
      --tkt-gray: #5b6472;
      --tkt-gray-soft: #eef0f3;
      --tkt-ease: cubic-bezier(.4,0,.2,1);
    }

    * { box-sizing: border-box; }

    body { background: var(--tkt-bg); }

    /* Make sure SweetAlert's dark backdrop sits above the fixed sidebar/menu,
       otherwise the sidebar stays bright while the rest of the page dims. */
    .swal2-container {
      z-index: 99999 !important;
    }
    .swal2-popup {
      z-index: 100000 !important;
    }

    .tkt-page {
      max-width: 1240px;
      margin: 0 auto;
      padding: 24px 24px 60px;
    }

    /* ── Back link (top, always visible) ──────────────────── */
    .tkt-back-top { margin-bottom: 14px; }
    .tkt-back-top .tkt-back-link {
      display: inline-flex;
      width: auto;
    }

    /* ── Header ─────────────────────────────────────────────── */
    .tkt-header-bar {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
      background: var(--tkt-surface);
      border: 1px solid var(--tkt-border);
      border-radius: var(--tkt-radius);
      box-shadow: var(--tkt-shadow);
      padding: 22px 26px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }
    .tkt-id {
      display: inline-block;
      font-size: 12px;
      font-weight: 800;
      letter-spacing: .04em;
      color: var(--tkt-primary-deep);
      background: var(--tkt-primary-soft);
      padding: 3px 10px;
      border-radius: 999px;
      margin-bottom: 8px;
    }
    .tkt-subject {
      font-size: 21px;
      font-weight: 800;
      color: var(--tkt-text);
      margin: 0 0 10px;
      letter-spacing: -.2px;
    }
    .tkt-meta-row { display: flex; flex-wrap: wrap; gap: 16px; }
    .tkt-meta {
      display: inline-flex; align-items: center; gap: 6px;
      font-size: 13px; color: var(--tkt-muted); font-weight: 500;
    }
    .tkt-meta i { font-size: 15px; color: var(--tkt-faint); }
    .tkt-header-right { flex-shrink: 0; }

    .tkt-status-badge {
      display: inline-flex; align-items: center; gap: 6px;
      font-size: 12.5px; font-weight: 800; letter-spacing: .02em;
      padding: 7px 16px; border-radius: 999px;
    }
    .tkt-status-badge::before {
      content: ""; width: 7px; height: 7px; border-radius: 50%; background: currentColor;
    }
    .status-pending { background: var(--tkt-amber-soft); color: var(--tkt-amber); }
    .status-in-progress { background: var(--tkt-blue-soft); color: var(--tkt-blue); }
    .status-resolved { background: var(--tkt-green-soft); color: var(--tkt-green); }
    .status-cancelled { background: var(--tkt-red-soft); color: var(--tkt-red); }

    /* ── Grid ───────────────────────────────────────────────── */
    .tkt-grid {
      display: grid;
      grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr);
      gap: 20px;
      align-items: start;
    }
    @media (max-width: 960px) {
      .tkt-grid { grid-template-columns: 1fr; }
    }

    .tkt-card {
      background: var(--tkt-surface);
      border: 1px solid var(--tkt-border);
      border-radius: var(--tkt-radius);
      box-shadow: var(--tkt-shadow);
      padding: 20px 24px;
      margin-bottom: 20px;
    }
    .tkt-card-title {
      display: flex; align-items: center; gap: 8px;
      font-size: 14.5px; font-weight: 800; color: var(--tkt-text);
      margin: 0 0 16px;
      padding-bottom: 12px;
      border-bottom: 1px solid var(--tkt-border);
    }
    .tkt-card-title i { font-size: 17px; color: var(--tkt-primary); }

    /* Info rows */
    .tkt-info-row {
      display: flex; justify-content: space-between; gap: 12px;
      padding: 9px 0;
      border-bottom: 1px solid var(--tkt-border);
      font-size: 13.5px;
    }
    .tkt-info-row:last-child { border-bottom: none; }
    .tkt-info-label { color: var(--tkt-muted); font-weight: 600; }
    .tkt-info-value { color: var(--tkt-text); font-weight: 700; text-align: right; }

    .tkt-description {
      background: var(--tkt-bg);
      border: 1px solid var(--tkt-border);
      border-radius: var(--tkt-radius-sm);
      padding: 16px 18px;
      font-size: 13.5px;
      line-height: 1.7;
      color: var(--tkt-text);
      white-space: pre-wrap;
      word-break: break-word;
    }

    /* ── Timeline (History Logs) ───────────────────────────── */
    .tkt-timeline { position: relative; padding-left: 6px; }
    .tkt-timeline-item {
      position: relative;
      display: flex;
      gap: 14px;
      padding-bottom: 22px;
    }
    .tkt-timeline-item:last-child { padding-bottom: 0; }
    .tkt-timeline-item::before {
      content: "";
      position: absolute;
      left: 15px;
      top: 32px;
      bottom: 0;
      width: 2px;
      background: var(--tkt-border);
    }
    .tkt-timeline-item:last-child::before { display: none; }

    .tkt-timeline-dot {
      flex-shrink: 0;
      width: 32px; height: 32px;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 16px;
      z-index: 1;
    }
    .tkt-timeline-dot.tone-blue   { background: var(--tkt-blue-soft);  color: var(--tkt-blue); }
    .tkt-timeline-dot.tone-green  { background: var(--tkt-green-soft); color: var(--tkt-green); }
    .tkt-timeline-dot.tone-amber  { background: var(--tkt-amber-soft); color: var(--tkt-amber); }
    .tkt-timeline-dot.tone-red    { background: var(--tkt-red-soft);   color: var(--tkt-red); }
    .tkt-timeline-dot.tone-gray   { background: var(--tkt-gray-soft);  color: var(--tkt-gray); }

    .tkt-timeline-content { padding-top: 4px; }
    .tkt-timeline-action { font-size: 13.5px; font-weight: 700; color: var(--tkt-text); margin-bottom: 3px; }
    .tkt-timeline-time { font-size: 12px; color: var(--tkt-faint); font-weight: 500; }

    .tkt-empty {
      text-align: center; padding: 30px 10px; color: var(--tkt-faint); font-size: 13px;
    }
    .tkt-empty i { font-size: 28px; display: block; margin-bottom: 8px; opacity: .5; }

    /* ── Sidebar / Actions ──────────────────────────────────── */
    .tkt-assigned-badge {
      display: inline-flex; align-items: center; gap: 8px;
      font-size: 13.5px; font-weight: 700; color: var(--tkt-primary-deep);
      background: var(--tkt-primary-soft);
      padding: 8px 14px; border-radius: var(--tkt-radius-sm);
      width: 100%;
    }
    .tkt-assigned-badge i { font-size: 16px; }

    .tkt-field { margin-bottom: 16px; }
    .tkt-field:last-of-type { margin-bottom: 0; }
    .tkt-field label {
      display: block; font-size: 12px; font-weight: 800;
      text-transform: uppercase; letter-spacing: .04em;
      color: var(--tkt-muted); margin-bottom: 6px;
    }
    .tkt-field select,
    .tkt-field textarea {
      width: 100%;
      border: 1.5px solid var(--tkt-border);
      border-radius: var(--tkt-radius-sm);
      padding: 9px 12px;
      font-size: 13.5px;
      font-family: inherit;
      color: var(--tkt-text);
      background: var(--tkt-surface);
      transition: border-color .15s var(--tkt-ease), box-shadow .15s var(--tkt-ease);
    }
    .tkt-field select:focus,
    .tkt-field textarea:focus {
      outline: none;
      border-color: var(--tkt-primary);
      box-shadow: 0 0 0 3px var(--tkt-primary-soft);
    }
    .tkt-field textarea { resize: vertical; min-height: 90px; }

    .tkt-btn-submit {
      width: 100%;
      background: var(--tkt-primary);
      color: #fff;
      border: none;
      font-weight: 700;
      font-size: 13.5px;
      padding: 11px 16px;
      border-radius: var(--tkt-radius-sm);
      cursor: pointer;
      transition: background .15s var(--tkt-ease), transform .12s var(--tkt-ease);
    }
    .tkt-btn-submit:hover { background: var(--tkt-primary-deep); }
    .tkt-btn-submit:active { transform: scale(.98); }
    .tkt-btn-submit:focus-visible { outline: 2px solid var(--tkt-primary-deep); outline-offset: 2px; }

    .tkt-back-link {
      display: flex; align-items: center; justify-content: center; gap: 6px;
      font-size: 13.5px; font-weight: 700; color: var(--tkt-primary-deep);
      background: var(--tkt-primary-soft);
      border: 1.5px solid var(--tkt-border);
      border-radius: var(--tkt-radius-sm);
      padding: 10px 16px;
      text-decoration: none;
      transition: border-color .15s var(--tkt-ease), background .15s var(--tkt-ease);
    }
    .tkt-back-link:hover { border-color: var(--tkt-primary); background: #e3ecff; }
  </style>

</head>


<body>

  <?php
  // ALERTS (shown after redirect back from update_ticket.php)
  if (isset($_SESSION['error'])) {
    echo "<script>document.addEventListener('DOMContentLoaded', () => Swal.fire('Error','" . addslashes($_SESSION['error']) . "','error'));</script>";
    unset($_SESSION['error']);
  }
  if (isset($_SESSION['success'])) {
    echo "<script>document.addEventListener('DOMContentLoaded', () => Swal.fire('Success','" . addslashes($_SESSION['success']) . "','success'));</script>";
    unset($_SESSION['success']);
  }
  ?>

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

  <div class="tkt-page">

    <!-- Back to Dashboard: kept at the top so it's always visible without scrolling -->
    <div class="tkt-back-top">
      <a href="ticketRequest.php" class="tkt-back-link">
        <i class="bx bx-arrow-back"></i> Back to Dashboard
      </a>
    </div>

    <!-- Header -->
    <div class="tkt-header-bar">
      <div>
        <span class="tkt-id">TICKET #<?= htmlspecialchars($ticket['id']); ?></span>
        <h1 class="tkt-subject"><?= htmlspecialchars($ticket['subject']); ?></h1>
        <div class="tkt-meta-row">
          <span class="tkt-meta"><i class="bx bx-user"></i> <?= htmlspecialchars($ticket['user_name']); ?></span>
          <span class="tkt-meta"><i class="bx bx-buildings"></i> <?= htmlspecialchars($ticket['user_department']); ?></span>
          <span class="tkt-meta">
            <i class="bx bx-time-five"></i>
            <?= isset($ticket['created_at']) && !empty($ticket['created_at'])
              ? 'Created ' . date('F d, Y h:i A', strtotime($ticket['created_at']))
              : 'No creation record'; ?>
          </span>
        </div>
      </div>
      <div class="tkt-header-right">
        <span class="tkt-status-badge status-<?= htmlspecialchars($status_slug); ?>">
          <?= htmlspecialchars($ticket['status']); ?>
        </span>
      </div>
    </div>

    <div class="tkt-grid">

      <!-- ── Main column: description + history timeline ── -->
      <div class="tkt-main">

        <div class="tkt-card">
          <h3 class="tkt-card-title"><i class="bx bx-message-square-detail"></i> Description</h3>
          <div class="tkt-description"><?= nl2br(htmlspecialchars($ticket['description'])); ?></div>
        </div>

        <div class="tkt-card">
          <h3 class="tkt-card-title"><i class="bx bx-history"></i> Activity Timeline</h3>
          <?php if (!empty($history_logs)): ?>
            <div class="tkt-timeline">
              <?php foreach ($history_logs as $log):
                $meta = tkt_history_icon($log['action']); ?>
                <div class="tkt-timeline-item">
                  <div class="tkt-timeline-dot tone-<?= $meta['tone']; ?>">
                    <i class="bx <?= $meta['icon']; ?>"></i>
                  </div>
                  <div class="tkt-timeline-content">
                    <div class="tkt-timeline-action"><?= htmlspecialchars($log['action']); ?></div>
                    <div class="tkt-timeline-time"><?= date('F j, Y, g:i A', strtotime($log['timestamp'])); ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="tkt-empty">
              <i class="bx bx-time"></i>
              No history logs available yet.
            </div>
          <?php endif; ?>
        </div>

      </div>

      <!-- ── Sidebar: assignment + status/feedback form ── -->
      <div class="tkt-sidebar">

        <div class="tkt-card">
          <h3 class="tkt-card-title"><i class="bx bx-user-check"></i> Assigned To</h3>
          <div class="tkt-assigned-badge">
            <i class="bx bx-headphone"></i>
            <?= $ticket['mis_name'] ? htmlspecialchars($ticket['mis_name']) : 'Unassigned'; ?>
          </div>
        </div>

        <div class="tkt-card">
          <h3 class="tkt-card-title"><i class="bx bx-cog"></i> Update Ticket</h3>
          <form id="updateTicketForm" action="./ticket/update_ticket.php" method="POST">
            <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>" />

            <div class="tkt-field">
              <label for="assigned_to">Assign MIS Personnel</label>
              <select name="assigned_to" id="assigned_to">
                <option value="">Unassigned</option>
                <?php foreach ($mis_personnel as $mis):
                  $selected = ($ticket['assigned_to'] == $mis['id']) ? 'selected' : ''; ?>
                  <option value="<?php echo $mis['id']; ?>" <?php echo $selected; ?>>
                    <?php echo htmlspecialchars($mis['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="tkt-field">
              <label for="status">Update Status</label>
              <select name="status" id="status">
                <option value="Pending" <?php echo ($ticket['status'] == 'Pending' ? 'selected' : ''); ?>>Pending</option>
                <option value="In Progress" <?php echo ($ticket['status'] == 'In Progress' ? 'selected' : ''); ?>>In
                  Progress</option>
                <option value="Resolved" <?php echo ($ticket['status'] == 'Resolved' ? 'selected' : ''); ?>>Resolved
                </option>
                <!--<option value="Cancelled" <?php echo ($ticket['status'] == 'Cancelled' ? 'selected' : ''); ?>>Cancelled</option> -->
              </select>
            </div>

            <div class="tkt-field">
              <label for="feedback">Feedback</label>
              <textarea name="feedback" id="feedback"
                placeholder="Write your feedback..."><?php echo htmlspecialchars($ticket['feedback']); ?></textarea>
            </div>

            <button type="submit" class="tkt-btn-submit">Update Ticket</button>
          </form>
        </div>

      </div>

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

      // Confirm before saving ticket updates
      const updateForm = document.getElementById('updateTicketForm');
      if (updateForm) {
        updateForm.addEventListener('submit', function (event) {
          event.preventDefault();
          Swal.fire({
            title: 'Update this ticket?',
            text: 'This will save the assignment, status, and feedback changes.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, update it',
            confirmButtonColor: '#4f7cff'
          }).then((result) => {
            if (result.isConfirmed) {
              updateForm.submit();
            }
          });
        });
      }
    });
  </script>

</body>
</html>