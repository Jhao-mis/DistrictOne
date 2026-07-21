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

// ─────────────────────────────────────────────────────────────────────────
// ✅ Handle AJAX "Cancel Ticket" requests (self-contained, no separate file)
// Only allowed while the ticket is still "Pending" — once it's approved
// (moved to In Progress) or resolved/rejected/already cancelled, this
// will refuse the request.
// ─────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_ticket') {
  header('Content-Type: application/json');

  $cancel_ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;

  if ($cancel_ticket_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ticket ID.']);
    exit;
  }

  // Confirm the ticket exists and grab its status + owner + approval flag
  $checkStmt = $conn->prepare("SELECT status, user_id, admin_approved FROM tickets WHERE id = ?");
  if (!$checkStmt) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $conn->error]);
    exit;
  }
  $checkStmt->bind_param("i", $cancel_ticket_id);
  $checkStmt->execute();
  $checkStmt->bind_result($cancel_status, $cancel_owner_id, $cancel_admin_approved);
  $found = $checkStmt->fetch();
  $checkStmt->close();

  if (!$found) {
    echo json_encode(['success' => false, 'message' => 'Ticket not found.']);
    exit;
  }

  if ((int)$cancel_owner_id !== (int)$user_id) {
    echo json_encode(['success' => false, 'message' => 'You are not allowed to cancel this ticket.']);
    exit;
  }

  // ✅ Only "Pending" AND not-yet-approved tickets can be cancelled.
  // Once admin_approved = 1 (or status moves past Pending), cancellation is blocked.
  if ($cancel_status !== 'Pending' || (int)$cancel_admin_approved === 1) {
    echo json_encode(['success' => false, 'message' => 'This ticket has already been approved/processed and can no longer be cancelled.']);
    exit;
  }

  $conn->begin_transaction();
  try {
    $updateStmt = $conn->prepare("UPDATE tickets SET status = 'Cancelled' WHERE id = ? AND status = 'Pending' AND admin_approved = 0");
    $updateStmt->bind_param("i", $cancel_ticket_id);
    $updateStmt->execute();
    $affected = $updateStmt->affected_rows;
    $updateStmt->close();

    if ($affected === 0) {
      // Status/approval changed between the check and the update (race condition)
      $conn->rollback();
      echo json_encode(['success' => false, 'message' => 'This ticket has already been approved/processed and can no longer be cancelled.']);
      exit;
    }

    $cancelNow = date('Y-m-d H:i:s');
    $cancelAction = "Ticket cancelled by requester";
    $historyStmt = $conn->prepare("INSERT INTO ticket_history (ticket_id, action, action_by, timestamp) VALUES (?, ?, ?, ?)");
    $historyStmt->bind_param("isis", $cancel_ticket_id, $cancelAction, $user_id, $cancelNow);
    $historyStmt->execute();
    $historyStmt->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Your ticket has been cancelled.']);
  } catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Failed to cancel the ticket. Please try again.']);
  }

  $conn->close();
  exit;
}

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
$history_rows = $history_result->fetch_all(MYSQLI_ASSOC);
$history_count = count($history_rows);

// ✅ Close everything
$stmt->close();
$conn->close();

// ── Helpers for the redesigned UI ──────────────────────────────────────────
$statusMap = [
  'Resolved'    => ['class' => 'st-resolved',  'icon' => 'check'],
  'In Progress' => ['class' => 'st-progress',  'icon' => 'clock'],
  'Pending'     => ['class' => 'st-pending',   'icon' => 'hourglass'],
  'Rejected'    => ['class' => 'st-rejected',  'icon' => 'x'],
  'Cancelled'   => ['class' => 'st-cancelled', 'icon' => 'x'],
];
$statusInfo = $statusMap[$ticket['status']] ?? ['class' => 'st-pending', 'icon' => 'hourglass'];

// ✅ Determine if the current user is allowed to cancel this ticket.
// Cancellable ONLY while status is "Pending" AND admin_approved = 0 —
// once approved, resolved, rejected, or already cancelled, the button won't show.
$canCancel = $ticket['status'] === 'Pending'
  && (int)$ticket['admin_approved'] === 0
  && (int)$ticket['user_id'] === (int)$user_id;

$assigneeName = $ticket['mis_name'] !== null ? trim(preg_replace('/\s+/', ' ', $ticket['mis_name'])) : '';
$isAssigned = $assigneeName !== '';
$assigneeInitials = $isAssigned
  ? strtoupper(substr($assigneeName, 0, 1) . (strpos($assigneeName, ' ') !== false ? substr($assigneeName, strpos($assigneeName, ' ') + 1, 1) : ''))
  : '–';

function tk_history_icon(string $action): string
{
  $a = strtolower($action);
  if (str_contains($a, 'assigned')) return 'user';
  if (str_contains($a, 'resolved')) return 'check';
  if (str_contains($a, 'reject')) return 'x';
  if (str_contains($a, 'progress')) return 'clock';
  if (str_contains($a, 'created') || str_contains($a, 'submitted')) return 'plus';
  if (str_contains($a, 'comment') || str_contains($a, 'note')) return 'message';
  if (str_contains($a, 'cancel')) return 'x';
  return 'dot';
}
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

  <style>
    :root {
      --tk-bg: #f7f8fa;
      --tk-surface: #ffffff;
      --tk-border: #e8eaee;
      --tk-text: #1f2430;
      --tk-text-muted: #767e8c;
      --tk-primary: #7cb9ff;
      --tk-primary-soft: #eaf3ff;
      --tk-pending: #b8860b;
      --tk-pending-bg: #fdf3da;
      --tk-progress: #2563eb;
      --tk-progress-bg: #e7eefd;
      --tk-rejected: #c0392b;
      --tk-rejected-bg: #fbe9e7;
      --tk-resolved: #1f9d55;
      --tk-resolved-bg: #e4f7ea;
      --tk-cancelled: #6b7280;
      --tk-cancelled-bg: #eef0f2;
      --tk-radius: 12px;
      --tk-shadow: 0 1px 2px rgba(20,20,43,.04), 0 8px 24px -12px rgba(20,20,43,.10);
    }

    .tk-view-wrap { font-family: inherit; color: var(--tk-text); }

    /* ── Top bar ───────────────────────────────────────────────────── */
    .tk-topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 18px;
    }
    .tk-back {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: var(--tk-text-muted);
      text-decoration: none;
      font-size: 13.5px;
      font-weight: 600;
      padding: 8px 14px 8px 10px;
      border-radius: 8px;
      border: 1px solid var(--tk-border);
      background: var(--tk-surface);
      transition: border-color .12s ease, color .12s ease;
    }
    .tk-back:hover { border-color: var(--tk-primary); color: var(--tk-primary); }
    .tk-back svg { width: 15px; height: 15px; }

    /* ── Header card ───────────────────────────────────────────────── */
    .tk-header-card {
      background: linear-gradient(135deg, var(--tk-primary) 0%, #4e96f0 100%);
      border-radius: var(--tk-radius);
      padding: 24px 26px;
      color: #fff;
      margin-bottom: 18px;
      box-shadow: var(--tk-shadow);
    }
    .tk-header-top {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      flex-wrap: wrap;
      gap: 14px;
    }
    .tk-header-eyebrow {
      font-size: 11.5px;
      font-weight: 700;
      letter-spacing: .6px;
      text-transform: uppercase;
      opacity: .8;
      margin-bottom: 5px;
    }
    .tk-header-subject {
      font-size: 21px;
      font-weight: 700;
      line-height: 1.3;
      max-width: 560px;
    }
    .tk-header-actions {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }
    .tk-status-pill {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 7px 15px;
      border-radius: 999px;
      font-size: 13px;
      font-weight: 700;
      background: rgba(255,255,255,.16);
      white-space: nowrap;
    }
    .tk-status-pill svg { width: 14px; height: 14px; }

    .tk-cancel-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 15px;
      border-radius: 999px;
      font-size: 13px;
      font-weight: 700;
      background: rgba(255,255,255,.16);
      color: #fff;
      border: 1px solid rgba(255,255,255,.35);
      cursor: pointer;
      transition: background .12s ease, transform .08s ease;
      white-space: nowrap;
    }
    .tk-cancel-btn:hover { background: rgba(255,255,255,.28); }
    .tk-cancel-btn:active { transform: scale(.97); }
    .tk-cancel-btn svg { width: 14px; height: 14px; }
    .tk-cancel-btn:disabled { opacity: .6; cursor: not-allowed; }

    /* ── Body grid ─────────────────────────────────────────────────── */
    .tk-grid {
      display: grid;
      grid-template-columns: 1.3fr 1fr;
      gap: 18px;
      align-items: start;
    }
    @media (max-width: 900px) {
      .tk-grid { grid-template-columns: 1fr; }
    }

    .tk-card {
      background: var(--tk-surface);
      border: 1px solid var(--tk-border);
      border-radius: var(--tk-radius);
      box-shadow: var(--tk-shadow);
      overflow: hidden;
    }
    .tk-card-head {
      padding: 16px 20px;
      border-bottom: 1px solid var(--tk-border);
      font-weight: 700;
      font-size: 15px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .tk-card-head svg { width: 16px; height: 16px; color: var(--tk-text-muted); }
    .tk-card-body { padding: 20px; }

    /* Ticket info card */
    .tk-meta-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 14px;
      margin-bottom: 20px;
    }
    .tk-meta-item {
      background: var(--tk-bg);
      border: 1px solid var(--tk-border);
      border-radius: 10px;
      padding: 11px 13px;
    }
    .tk-meta-label {
      display: flex;
      align-items: center;
      gap: 5px;
      font-size: 10.5px;
      text-transform: uppercase;
      letter-spacing: .5px;
      font-weight: 700;
      color: var(--tk-text-muted);
      margin-bottom: 4px;
    }
    .tk-meta-label svg { width: 12px; height: 12px; flex-shrink: 0; }
    .tk-meta-value { font-size: 13.5px; font-weight: 600; color: var(--tk-text); word-break: break-word; }
    .tk-meta-value.is-assignee { display: flex; align-items: center; gap: 8px; }

    .tk-avatar {
      width: 24px; height: 24px; border-radius: 50%;
      background: var(--tk-primary-soft); color: var(--tk-primary);
      display: flex; align-items: center; justify-content: center;
      font-size: 10.5px; font-weight: 700; flex-shrink: 0;
    }
    .tk-avatar.is-unassigned { background: #f0f1f3; color: #9aa0ab; }

    .tk-desc-label {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 13px;
      font-weight: 700;
      color: var(--tk-text);
      margin-bottom: 8px;
    }
    .tk-desc-label svg { width: 14px; height: 14px; color: var(--tk-text-muted); }
    .tk-desc-box {
      background: var(--tk-bg);
      border: 1px solid var(--tk-border);
      border-radius: 10px;
      padding: 14px;
      font-size: 13.5px;
      line-height: 1.65;
      color: var(--tk-text);
      white-space: pre-wrap;
      min-height: 100px;
    }

    /* ── History timeline ──────────────────────────────────────────── */
    .tk-history-count {
      margin-left: auto;
      font-size: 12px;
      font-weight: 600;
      color: var(--tk-text-muted);
      background: var(--tk-bg);
      border: 1px solid var(--tk-border);
      padding: 2px 9px;
      border-radius: 999px;
    }
    .tk-timeline {
      list-style: none;
      margin: 0;
      padding: 4px 20px 4px;
      max-height: 560px;
      overflow-y: auto;
      position: relative;
    }
    .tk-timeline-item {
      position: relative;
      padding: 0 0 22px 30px;
    }
    .tk-timeline-item::before {
      content: "";
      position: absolute;
      left: 9px;
      top: 22px;
      bottom: -4px;
      width: 2px;
      background: var(--tk-border);
    }
    .tk-timeline-item:last-child::before { display: none; }
    .tk-timeline-item:last-child { padding-bottom: 2px; }

    .tk-timeline-dot {
      position: absolute;
      left: 0;
      top: 0;
      width: 20px;
      height: 20px;
      border-radius: 50%;
      background: var(--tk-primary-soft);
      color: var(--tk-primary);
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .tk-timeline-dot svg { width: 11px; height: 11px; }
    .tk-timeline-item.is-latest .tk-timeline-dot {
      background: var(--tk-primary);
      color: #fff;
      box-shadow: 0 0 0 4px var(--tk-primary-soft);
    }

    .tk-timeline-content {
      background: var(--tk-bg);
      border: 1px solid var(--tk-border);
      border-radius: 10px;
      padding: 11px 13px;
    }
    .tk-timeline-item.is-latest .tk-timeline-content {
      border-color: var(--tk-primary);
      background: var(--tk-primary-soft);
    }
    .tk-timeline-action {
      font-size: 13px;
      font-weight: 600;
      color: var(--tk-text);
      line-height: 1.45;
    }
    .tk-timeline-meta {
      display: flex;
      align-items: center;
      gap: 6px;
      flex-wrap: wrap;
      margin-top: 6px;
      font-size: 11.5px;
      color: var(--tk-text-muted);
    }
    .tk-timeline-meta .tk-dot-sep { width: 3px; height: 3px; border-radius: 50%; background: var(--tk-text-muted); }
    .tk-timeline-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-weight: 700;
      color: var(--tk-primary);
    }

    .tk-history-empty {
      padding: 50px 20px;
      text-align: center;
      color: var(--tk-text-muted);
    }
    .tk-history-empty svg { width: 36px; height: 36px; margin-bottom: 10px; opacity: .4; }
    .tk-history-empty p { margin: 0; font-size: 13.5px; }

    /* Status colors */
    .st-pending   .tk-status-pill, .tk-status-pill.st-pending   { background: var(--tk-pending-bg);  color: var(--tk-pending); }
    .st-progress  .tk-status-pill, .tk-status-pill.st-progress  { background: var(--tk-progress-bg); color: var(--tk-progress); }
    .st-rejected  .tk-status-pill, .tk-status-pill.st-rejected  { background: var(--tk-rejected-bg); color: var(--tk-rejected); }
    .st-resolved  .tk-status-pill, .tk-status-pill.st-resolved  { background: var(--tk-resolved-bg); color: var(--tk-resolved); }
    .st-cancelled .tk-status-pill, .tk-status-pill.st-cancelled { background: var(--tk-cancelled-bg); color: var(--tk-cancelled); }
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
      <div class="tk-view-wrap">

        <div class="tk-topbar">
          <a href="serviceRequest.php" class="tk-back">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
            Back to dashboard
          </a>
        </div>

        <!-- Header -->
        <div class="tk-header-card">
          <div class="tk-header-top">
            <div>
              <div class="tk-header-eyebrow">Ticket #<?= htmlspecialchars($ticket['id']); ?></div>
              <div class="tk-header-subject"><?= htmlspecialchars($ticket['subject']); ?></div>
            </div>
            <div class="tk-header-actions">
              <span class="tk-status-pill <?= $statusInfo['class'] ?>">
                <?php if ($statusInfo['icon'] === 'check'): ?>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                <?php elseif ($statusInfo['icon'] === 'clock'): ?>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                <?php elseif ($statusInfo['icon'] === 'hourglass'): ?>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 2h14M5 22h14M5 2c0 6 5 7 5 10s-5 4-5 10M19 2c0 6-5 7-5 10s5 4 5 10"/></svg>
                <?php else: ?>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                <?php endif; ?>
                <?= htmlspecialchars($ticket['status']); ?>
              </span>

              <?php if ($canCancel): ?>
                <button type="button" id="btnCancelTicket" class="tk-cancel-btn" data-ticket-id="<?= (int)$ticket['id'] ?>">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                  Cancel Ticket
                </button>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Body -->
        <div class="tk-grid">

          <!-- Ticket info -->
          <div class="tk-card">
            <div class="tk-card-head">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6M9 16h6M9 8h6M5 4h14v16l-3-2-3 2-3-2-3 2V4z"/></svg>
              Ticket information
            </div>
            <div class="tk-card-body">
              <div class="tk-meta-grid">
                <div class="tk-meta-item">
                  <div class="tk-meta-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    Ticket number
                  </div>
                  <div class="tk-meta-value">#<?= htmlspecialchars($ticket['id']); ?></div>
                </div>
                <div class="tk-meta-item">
                  <div class="tk-meta-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/></svg>
                    Status
                  </div>
                  <div class="tk-meta-value"><?= htmlspecialchars($ticket['status']); ?></div>
                </div>
                <div class="tk-meta-item" style="grid-column: 1 / -1;">
                  <div class="tk-meta-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6M23 11h-6"/></svg>
                    Assigned to
                  </div>
                  <div class="tk-meta-value is-assignee">
                    <div class="tk-avatar <?= $isAssigned ? '' : 'is-unassigned' ?>"><?= $assigneeInitials ?></div>
                    <?= $isAssigned ? htmlspecialchars($assigneeName) : 'Not assigned yet' ?>
                  </div>
                </div>
              </div>

              <div class="tk-desc-label">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
                Description
              </div>
              <div class="tk-desc-box"><?= htmlspecialchars($ticket['description']); ?></div>
            </div>
          </div>

          <!-- History timeline -->
          <div class="tk-card">
            <div class="tk-card-head">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
              History timeline
              <span class="tk-history-count"><?= $history_count ?> event<?= $history_count === 1 ? '' : 's' ?></span>
            </div>

            <?php if ($history_count > 0): ?>
              <ul class="tk-timeline">
                <?php foreach ($history_rows as $i => $row):
                  $icon = tk_history_icon($row['action']);
                  $ts = strtotime($row['timestamp']);
                ?>
                  <li class="tk-timeline-item <?= $i === 0 ? 'is-latest' : '' ?>">
                    <span class="tk-timeline-dot">
                      <?php if ($icon === 'user'): ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                      <?php elseif ($icon === 'check'): ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                      <?php elseif ($icon === 'x'): ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                      <?php elseif ($icon === 'clock'): ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                      <?php elseif ($icon === 'plus'): ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                      <?php elseif ($icon === 'message'): ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-8.5 8.4 8.5 8.5 0 0 1-4-1L3 20l1.1-5.5a8.5 8.5 0 1 1 16.9-3z"/></svg>
                      <?php else: ?>
                        <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="4"/></svg>
                      <?php endif; ?>
                    </span>
                    <div class="tk-timeline-content">
                      <div class="tk-timeline-action"><?= htmlspecialchars($row['action']); ?></div>
                      <div class="tk-timeline-meta">
                        <span><?= date('M j, Y · g:i A', $ts); ?></span>
                        <?php if (!empty($row['action_by']) && trim($row['action_by']) !== ''): ?>
                          <span class="tk-dot-sep"></span>
                          <span class="tk-timeline-badge">
                            <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <?= htmlspecialchars(trim($row['action_by'])); ?>
                          </span>
                        <?php endif; ?>
                      </div>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="tk-history-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                <p>No history records yet for this ticket.</p>
              </div>
            <?php endif; ?>
          </div>

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

      // ── Cancel ticket ──────────────────────────────────────────────
      var cancelBtn = document.getElementById("btnCancelTicket");
      if (cancelBtn) {
        cancelBtn.addEventListener("click", function () {
          var ticketId = this.dataset.ticketId;
          var btn = this;

          Swal.fire({
            title: "Cancel this ticket?",
            text: "This action cannot be undone.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Yes, cancel it",
            cancelButtonText: "No, keep it",
            confirmButtonColor: "#c0392b"
          }).then(function (result) {
            if (!result.isConfirmed) return;

            btn.disabled = true;

            fetch(window.location.pathname + window.location.search, {
              method: "POST",
              headers: { "Content-Type": "application/x-www-form-urlencoded" },
              body: "action=cancel_ticket&ticket_id=" + encodeURIComponent(ticketId)
            })
              .then(function (res) { return res.json(); })
              .then(function (data) {
                if (data.success) {
                  Swal.fire("Cancelled!", data.message, "success").then(function () {
                    location.reload();
                  });
                } else {
                  btn.disabled = false;
                  Swal.fire("Error", data.message, "error");
                }
              })
              .catch(function () {
                btn.disabled = false;
                Swal.fire("Error", "Something went wrong. Please try again.", "error");
              });
          });
        });
      }
    });
  </script>

</body>

</html>