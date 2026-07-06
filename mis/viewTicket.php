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

/* =========================================================
   Display-only helpers (no schema/query changes below this line)
   ========================================================= */
$badgeClass = match ($ticket['status']) {
  'Resolved' => 'badge-resolved',
  'In Progress' => 'badge-in-progress',
  'Pending' => 'badge-pending',
  default => 'badge-cancelled'
};

$statusIcon = match ($ticket['status']) {
  'Resolved' => 'bx-check-circle',
  'In Progress' => 'bx-loader-circle',
  'Pending' => 'bx-time-five',
  default => 'bx-x-circle'
};

function ticketEventType($action)
{
  if (stripos($action, 'taken') !== false) return 'taken';
  if (stripos($action, 'Status changed') !== false) return 'status';
  if (stripos($action, 'Feedback') !== false) return 'feedback';
  return 'other';
}

$eventMeta = [
  'taken'    => ['icon' => 'bx-hand',            'class' => 'ev-taken'],
  'status'   => ['icon' => 'bx-refresh',         'class' => 'ev-status'],
  'feedback' => ['icon' => 'bx-message-detail',  'class' => 'ev-feedback'],
  'other'    => ['icon' => 'bx-history',         'class' => 'ev-other'],
];

function timeAgo($datetime)
{
  $diff = time() - strtotime($datetime);
  if ($diff < 60) return 'just now';
  if ($diff < 3600) return floor($diff / 60) . 'm ago';
  if ($diff < 86400) return floor($diff / 3600) . 'h ago';
  if ($diff < 604800) return floor($diff / 86400) . 'd ago';
  return date('M j, Y', strtotime($datetime));
}

// Split "Feedback by X: "..."" into a label + quoted message so it renders like a note, not a run-on sentence
function splitFeedbackAction($action)
{
  if (preg_match('/^(.*?):\s*"(.*)"$/s', $action, $m)) {
    return [$m[1], $m[2]];
  }
  return [$action, null];
}
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

  <style>
    /* =========================================================
       Design tokens
       ========================================================= */
    :root {
      --tk-primary: #4338ca;
      --tk-primary-light: #6366f1;
      --tk-primary-soft: #eef0fd;
      --tk-ink: #1a1d29;
      --tk-muted: #6b7085;
      --tk-faint: #9195a8;
      --tk-border: #e6e7f0;
      --tk-surface: #ffffff;
      --tk-canvas: #f5f6fb;

      --tk-pending: #d97706;
      --tk-pending-soft: #fef3e2;
      --tk-progress: #0284c7;
      --tk-progress-soft: #e5f4fd;
      --tk-resolved: #15803d;
      --tk-resolved-soft: #e8f8ee;
      --tk-cancelled: #b91c1c;
      --tk-cancelled-soft: #fdecec;

      --tk-radius-sm: 8px;
      --tk-radius-md: 12px;
      --tk-radius-lg: 16px;
      --tk-shadow: 0 1px 2px rgba(20, 21, 41, .04), 0 6px 20px rgba(20, 21, 41, .06);
    }

    * {
      box-sizing: border-box;
    }

    body {
      font-family: 'Public Sans', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: var(--tk-canvas);
      color: var(--tk-ink);
    }

    .vt-page-wrap {
      max-width: 1320px;
      margin: 0 auto;
    }

    /* =========================================================
       Ticket header banner
       ========================================================= */
    .vt-banner {
      background: var(--tk-surface);
      border: 1px solid var(--tk-border);
      border-radius: var(--tk-radius-lg);
      box-shadow: var(--tk-shadow);
      padding: 1.35rem 1.5rem;
      margin-bottom: 1.25rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .vt-banner-id {
      font-size: .78rem;
      font-weight: 700;
      letter-spacing: .04em;
      color: var(--tk-primary);
      text-transform: uppercase;
      margin-bottom: .3rem;
      display: block;
    }

    .vt-banner h2 {
      font-size: 1.4rem;
      font-weight: 700;
      margin: 0 0 .5rem;
      color: var(--tk-ink);
      letter-spacing: -.01em;
    }

    .vt-banner-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 1.25rem;
      font-size: .82rem;
      color: var(--tk-muted);
    }

    .vt-banner-meta span {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
    }

    .vt-banner-meta i {
      font-size: 1rem;
      color: var(--tk-faint);
    }

    .vt-banner-right {
      display: flex;
      align-items: center;
      gap: .6rem;
      flex-wrap: wrap;
    }

    /* =========================================================
       Alert banner
       ========================================================= */
    .vt-alert {
      display: flex;
      align-items: center;
      gap: .6rem;
      padding: .8rem 1.1rem;
      border-radius: var(--tk-radius-md);
      background: var(--tk-resolved-soft);
      color: var(--tk-resolved);
      font-weight: 600;
      font-size: .87rem;
      margin-bottom: 1.25rem;
      border: 1px solid rgba(21, 128, 61, .2);
    }

    .vt-alert i {
      font-size: 1.15rem;
    }

    /* =========================================================
       Layout
       ========================================================= */
    .main-container {
      display: grid;
      grid-template-columns: 1.15fr .85fr 1fr;
      gap: 1.25rem;
      align-items: start;
    }

    .column {
      background: var(--tk-surface);
      border: 1px solid var(--tk-border);
      border-radius: var(--tk-radius-lg);
      padding: 1.4rem;
      box-shadow: var(--tk-shadow);
    }

    .column h3 {
      font-size: .8rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: var(--tk-muted);
      margin: 0 0 1.1rem;
      padding-bottom: .8rem;
      border-bottom: 1px solid var(--tk-border);
      display: flex;
      align-items: center;
      gap: .45rem;
    }

    .column h3 i {
      font-size: 1rem;
      color: var(--tk-primary);
    }

    /* =========================================================
       Column 1 — Ticket info
       ========================================================= */
    .vt-field {
      margin-bottom: 1rem;
    }

    .vt-field:last-child {
      margin-bottom: 0;
    }

    .vt-label {
      display: block;
      font-size: .7rem;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: var(--tk-faint);
      font-weight: 700;
      margin-bottom: .3rem;
    }

    .vt-value {
      color: var(--tk-ink);
      font-weight: 600;
      font-size: .92rem;
    }

    .vt-meta-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
      margin-bottom: 1.1rem;
      padding-bottom: 1.1rem;
      border-bottom: 1px dashed var(--tk-border);
    }

    #description {
      display: block;
      width: 100%;
      height: 190px;
      resize: none;
      overflow-y: auto;
      border: 1px solid var(--tk-border);
      border-radius: var(--tk-radius-sm);
      background: var(--tk-canvas);
      color: var(--tk-ink);
      padding: .85rem .95rem;
      font-size: .87rem;
      font-family: inherit;
      line-height: 1.6;
    }

    .vt-status-row {
      display: flex;
      gap: 1.5rem;
      flex-wrap: wrap;
      margin-top: 1.15rem;
    }

    /* Badges */
    .badge {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      padding: .32rem .7rem;
      border-radius: 999px;
      font-size: .78rem;
      font-weight: 700;
      text-transform: none;
      letter-spacing: 0;
    }

    .badge-pending { background: var(--tk-pending-soft); color: var(--tk-pending); }
    .badge-in-progress { background: var(--tk-progress-soft); color: var(--tk-progress); }
    .badge-resolved { background: var(--tk-resolved-soft); color: var(--tk-resolved); }
    .badge-cancelled { background: var(--tk-cancelled-soft); color: var(--tk-cancelled); }
    .badge-mis { background: var(--tk-primary-soft); color: var(--tk-primary); }

    /* =========================================================
       Column 2 — Actions
       ========================================================= */
    label {
      display: block;
      font-size: .76rem;
      font-weight: 700;
      color: var(--tk-muted);
      text-transform: uppercase;
      letter-spacing: .03em;
      margin: 0 0 .4rem;
    }

    select, textarea {
      width: 100%;
      border: 1px solid var(--tk-border);
      border-radius: var(--tk-radius-sm);
      padding: .6rem .75rem;
      font-size: .88rem;
      font-family: inherit;
      color: var(--tk-ink);
      background: var(--tk-surface);
      margin-bottom: 1.1rem;
    }

    textarea {
      height: 130px;
      resize: none;
      line-height: 1.55;
    }

    select:focus, textarea:focus {
      outline: none;
      border-color: var(--tk-primary-light);
      box-shadow: 0 0 0 3px var(--tk-primary-soft);
    }

    .btn-submit {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: .45rem;
      width: 100%;
      border: none;
      border-radius: var(--tk-radius-sm);
      background: var(--tk-primary);
      color: #fff;
      font-weight: 700;
      font-size: .9rem;
      padding: .7rem 1rem;
      cursor: pointer;
      transition: background .12s ease, transform .06s ease;
    }

    .btn-submit:hover { background: #372f9e; }
    .btn-submit:active { transform: translateY(1px); }

    .btn-back {
      margin-top: 1.25rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: .4rem;
      padding: .65rem 1rem;
      border-radius: var(--tk-radius-sm);
      border: 1px solid var(--tk-border);
      background: var(--tk-surface);
      color: var(--tk-ink);
      font-weight: 600;
      font-size: .87rem;
      text-decoration: none;
      width: 100%;
      transition: border-color .12s ease, color .12s ease, background .12s ease;
    }

    .btn-back:hover {
      border-color: var(--tk-primary-light);
      color: var(--tk-primary);
      background: var(--tk-primary-soft);
    }

    .vt-locked, .vt-get-ticket {
      text-align: center;
      padding: 1.6rem 1rem;
      background: var(--tk-canvas);
      border: 1px dashed var(--tk-border);
      border-radius: var(--tk-radius-md);
      color: var(--tk-muted);
    }

    .vt-locked i, .vt-get-ticket i {
      font-size: 1.7rem;
      color: var(--tk-faint);
      display: block;
      margin-bottom: .5rem;
    }

    .vt-locked strong {
      display: block;
      color: var(--tk-ink);
      font-size: .92rem;
      margin-bottom: .25rem;
    }

    .vt-locked span, .vt-get-ticket p {
      font-size: .82rem;
      line-height: 1.5;
    }

    .vt-get-ticket p { margin: 0 0 1.1rem; }

    /* =========================================================
       Column 3 — Activity timeline (ticketing-system style)
       ========================================================= */
    .timeline {
      list-style: none;
      margin: 0;
      padding: 0;
      max-height: 560px;
      overflow-y: auto;
    }

    .timeline li {
      position: relative;
      padding: 0 0 1.35rem 2.4rem;
    }

    .timeline li::before {
      content: '';
      position: absolute;
      left: .95rem;
      top: 2rem;
      bottom: -.15rem;
      width: 2px;
      background: var(--tk-border);
    }

    .timeline li:last-child {
      padding-bottom: 0;
    }

    .timeline li:last-child::before {
      display: none;
    }

    .tl-icon {
      position: absolute;
      left: 0;
      top: 0;
      width: 1.9rem;
      height: 1.9rem;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .9rem;
      border: 3px solid var(--tk-surface);
      box-shadow: 0 0 0 1px var(--tk-border);
    }

    .ev-taken { background: var(--tk-resolved-soft); color: var(--tk-resolved); }
    .ev-status { background: var(--tk-progress-soft); color: var(--tk-progress); }
    .ev-feedback { background: var(--tk-primary-soft); color: var(--tk-primary); }
    .ev-other { background: var(--tk-canvas); color: var(--tk-muted); }

    .tl-card {
      background: var(--tk-canvas);
      border: 1px solid var(--tk-border);
      border-radius: var(--tk-radius-md);
      padding: .75rem .9rem;
    }

    .tl-title {
      font-size: .85rem;
      font-weight: 600;
      color: var(--tk-ink);
      line-height: 1.4;
      margin: 0 0 .25rem;
    }

    .tl-note {
      font-size: .82rem;
      color: var(--tk-muted);
      background: var(--tk-surface);
      border-left: 3px solid var(--tk-primary-light);
      padding: .5rem .65rem;
      border-radius: 6px;
      margin: .4rem 0 .35rem;
      line-height: 1.5;
      font-style: italic;
    }

    .tl-time {
      font-size: .72rem;
      color: var(--tk-faint);
      font-weight: 600;
    }

    .tl-time-full {
      font-size: .72rem;
      color: var(--tk-faint);
    }

    .vt-empty {
      text-align: center;
      color: var(--tk-muted);
      font-size: .85rem;
      padding: 2.5rem 1rem;
    }

    .vt-empty i {
      display: block;
      font-size: 1.9rem;
      margin-bottom: .55rem;
      color: var(--tk-border);
    }

    /* =========================================================
       Responsive
       ========================================================= */
    @media (max-width: 1100px) {
      .main-container {
        grid-template-columns: 1fr 1fr;
      }

      .column:first-child {
        grid-column: 1 / -1;
      }
    }

    @media (max-width: 700px) {
      .main-container {
        grid-template-columns: 1fr;
      }

      .vt-banner {
        flex-direction: column;
        align-items: flex-start;
      }

      .vt-meta-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      .btn-submit, .btn-back {
        transition: none !important;
      }
    }
  </style>
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
      <div class="vt-page-wrap">

        <?php if (isset($_GET['success'])): ?>
          <div class="vt-alert"><i class='bx bx-check-circle'></i> Ticket updated successfully.</div>
        <?php elseif (isset($_GET['assigned'])): ?>
          <div class="vt-alert"><i class='bx bx-check-circle'></i> Ticket assigned to you.</div>
        <?php endif; ?>

        <div class="vt-banner">
          <div>
            <span class="vt-banner-id">Ticket #<?php echo htmlspecialchars($ticket['id']); ?></span>
            <h2><?php echo htmlspecialchars($ticket['subject']); ?></h2>
            <div class="vt-banner-meta">
              <span><i class='bx bx-user'></i> <?php echo htmlspecialchars($ticket['user_name']); ?></span>
              <span><i class='bx bx-buildings'></i> <?php echo htmlspecialchars($ticket['user_department']); ?></span>
              <?php if (!empty($ticket['created_at'])): ?>
                <span><i class='bx bx-calendar'></i> Opened <?php echo date('M j, Y', strtotime($ticket['created_at'])); ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="vt-banner-right">
            <span class="badge <?php echo $badgeClass; ?>">
              <i class='bx <?php echo $statusIcon; ?>'></i>
              <?php echo htmlspecialchars($ticket['status']); ?>
            </span>
            <span class="badge badge-mis">
              <i class='bx bx-user-check'></i>
              <?php echo $ticket['mis_name'] ? htmlspecialchars($ticket['mis_name']) : 'Unassigned'; ?>
            </span>
          </div>
        </div>

        <div class="main-container">

          <!-- Column 1: Ticket Info -->
          <div class="column">
            <h3><i class='bx bx-detail'></i> Ticket Information</h3>

            <div class="vt-meta-grid">
              <div class="vt-field" style="margin-bottom:0;">
                <span class="vt-label">Submitted By</span>
                <span class="vt-value"><?php echo htmlspecialchars($ticket['user_name']); ?></span>
              </div>
              <div class="vt-field" style="margin-bottom:0;">
                <span class="vt-label">Department</span>
                <span class="vt-value"><?php echo htmlspecialchars($ticket['user_department']); ?></span>
              </div>
            </div>

            <div class="vt-field">
              <span class="vt-label">Subject</span>
              <span class="vt-value"><?php echo htmlspecialchars($ticket['subject']); ?></span>
            </div>

            <div class="vt-field">
              <label for="description">Description</label>
              <textarea id="description" readonly><?php echo htmlspecialchars($ticket['description']); ?></textarea>
            </div>

            <div class="vt-status-row">
              <div class="vt-field" style="margin-bottom:0;">
                <span class="vt-label">Status</span>
                <span class="badge <?php echo $badgeClass; ?>">
                  <i class='bx <?php echo $statusIcon; ?>'></i>
                  <?php echo htmlspecialchars($ticket['status']); ?>
                </span>
              </div>
              <div class="vt-field" style="margin-bottom:0;">
                <span class="vt-label">Assigned To</span>
                <span class="badge badge-mis">
                  <i class='bx bx-user'></i>
                  <?php echo $ticket['mis_name'] ? htmlspecialchars($ticket['mis_name']) : 'Unassigned'; ?>
                </span>
              </div>
            </div>
          </div>

          <!-- Column 2: MIS Actions -->
          <div class="column">
            <h3><i class='bx bx-cog'></i> Actions</h3>

            <?php if (empty($ticket['assigned_to']) || $ticket['assigned_to'] == 0): ?>
              <div class="vt-get-ticket">
                <i class='bx bx-inbox'></i>
                <p>This ticket hasn't been claimed yet. Take it to start working on it.</p>
                <form action="viewTicket.php?ticket_id=<?php echo $ticket['id']; ?>" method="POST">
                  <input type="hidden" name="get_ticket" value="1">
                  <button type="submit" class="btn-submit" style="background-color:#15803d;">
                    <i class='bx bx-hand'></i> Get Ticket
                  </button>
                </form>
              </div>

            <?php elseif ($ticket['assigned_to'] == $user_id): ?>
              <form action="viewTicket.php?ticket_id=<?php echo $ticket['id']; ?>" method="POST">
                <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>" />

                <label for="status">Update Status</label>
                <select name="status" id="status">
                  <option value="Pending" <?php echo ($ticket['status'] == 'Pending' ? 'selected' : ''); ?>>Pending</option>
                  <option value="In Progress" <?php echo ($ticket['status'] == 'In Progress' ? 'selected' : ''); ?>>In
                    Progress</option>
                  <option value="Resolved" <?php echo ($ticket['status'] == 'Resolved' ? 'selected' : ''); ?>>Resolved
                  </option>
                </select>

                <label for="feedback">Feedback</label>
                <textarea name="feedback" placeholder="Write your feedback..."
                  required><?php echo htmlspecialchars($ticket['feedback']); ?></textarea>

                <button type="submit" class="btn-submit"><i class='bx bx-save'></i> Update Ticket</button>
              </form>

            <?php else: ?>
              <div class="vt-locked">
                <i class='bx bx-lock-alt'></i>
                <strong>Assigned to another MIS staff</strong>
                <span>This ticket is with <?php echo htmlspecialchars($ticket['mis_name']); ?>. You cannot update it.</span>
              </div>
            <?php endif; ?>

            <a href="ticketAssign.php" class="btn-back"><i class='bx bx-arrow-back'></i> Back to Dashboard</a>
          </div>

          <!-- Column 3: Activity / History -->
          <div class="column">
            <h3><i class='bx bx-history'></i> Activity</h3>

            <?php if (!empty($history_logs)): ?>
              <ul class="timeline">
                <?php foreach ($history_logs as $log):
                  $type = ticketEventType($log['action']);
                  $meta = $eventMeta[$type];
                  [$mainText, $note] = splitFeedbackAction($log['action']);
                ?>
                  <li>
                    <span class="tl-icon <?php echo $meta['class']; ?>"><i class='bx <?php echo $meta['icon']; ?>'></i></span>
                    <div class="tl-card">
                      <p class="tl-title"><?php echo htmlspecialchars($mainText); ?></p>
                      <?php if ($note): ?>
                        <p class="tl-note">"<?php echo htmlspecialchars($note); ?>"</p>
                      <?php endif; ?>
                      <span class="tl-time" title="<?php echo htmlspecialchars(date('F j, Y, g:i A', strtotime($log['timestamp']))); ?>">
                        <?php echo timeAgo($log['timestamp']); ?>
                      </span>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="vt-empty">
                <i class='bx bx-history'></i>
                No activity yet on this ticket.
              </div>
            <?php endif; ?>
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
        });
      </script>
</body>

</html>