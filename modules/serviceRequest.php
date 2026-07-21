<?php
require '../vendor/autoload.php';
include '../db.php';
require 'login_verification.php';

$alert = null;

if (isset($_SESSION['alert'])) {
  $alert = $_SESSION['alert'];
  unset($_SESSION['alert']); // Clear after showing once
}

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

// 🎯 Fetch active tickets (Pending, In Progress, Cancelled only)
$query_active = $conn->prepare("
SELECT t.*, 
       (SELECT MAX(th.timestamp) 
        FROM ticket_history th 
        WHERE th.ticket_id = t.id) AS last_update,
       TRIM(REPLACE(
         (SELECT SUBSTRING_INDEX(th.action, ' by ', 1) 
          FROM ticket_history th 
          WHERE th.ticket_id = t.id 
            AND th.action LIKE 'Ticket assigned to%'
          ORDER BY th.timestamp DESC
          LIMIT 1), 'Ticket assigned to', '')
       ) AS assigned_to
FROM tickets t
WHERE t.user_id = ? 
  AND t.status IN ('Pending','In Progress','Cancelled')
ORDER BY t.created_at DESC

");
$query_active->bind_param("i", $user_id);
$query_active->execute();
$result_active = $query_active->get_result();


// 🛠️ Fetch resolved tickets
$query_resolved = $conn->prepare("
SELECT t.*, 
       (SELECT MAX(th.timestamp) 
        FROM ticket_history th 
        WHERE th.ticket_id = t.id) AS last_update,
       TRIM(REPLACE(
         (SELECT SUBSTRING_INDEX(th.action, ' by ', 1) 
          FROM ticket_history th 
          WHERE th.ticket_id = t.id 
            AND th.action LIKE 'Ticket assigned to%'
          ORDER BY th.timestamp DESC
          LIMIT 1), 'Ticket assigned to', '')
       ) AS assigned_to
FROM tickets t
WHERE t.user_id = ? 
  AND t.status = 'Resolved'
ORDER BY t.created_at DESC
");
$query_resolved->bind_param("i", $user_id);
$query_resolved->execute();
$result_resolved = $query_resolved->get_result();


// ❌ Rejected Tickets
$query_rejected = $conn->prepare("
SELECT t.*, 
       (SELECT MAX(th.timestamp) 
        FROM ticket_history th 
        WHERE th.ticket_id = t.id) AS last_update,
       TRIM(REPLACE(
         (SELECT SUBSTRING_INDEX(th.action, ' by ', 1) 
          FROM ticket_history th 
          WHERE th.ticket_id = t.id 
            AND th.action LIKE 'Ticket assigned to%'
          ORDER BY th.timestamp DESC
          LIMIT 1), 'Ticket assigned to', '')
       ) AS assigned_to
FROM tickets t
JOIN users u ON t.user_id = u.id
WHERE t.user_id = ? 
  AND t.status = 'Rejected'
ORDER BY t.created_at DESC
");
$query_rejected->bind_param("i", $user_id);
$query_rejected->execute();
$result_rejected = $query_rejected->get_result();

// ✅ Handle ticket submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $subject = $_POST["subject"];
  $description = $_POST["description"];

  $user_query = $conn->prepare("SELECT firstname, middlename, lastname, department FROM users WHERE id = ?");
  $user_query->bind_param("i", $user_id);
  $user_query->execute();
  $user_result = $user_query->get_result();

  if ($user_result->num_rows > 0) {
    $user_data = $user_result->fetch_assoc();
    $fullname = $user_data['firstname'] . ' ' . $user_data['middlename'] . ' ' . $user_data['lastname'];
    $department = $user_data['department'];

    $stmt = $conn->prepare("INSERT INTO tickets (user_id, fullname, department, subject, description, status) 
                                VALUES (?, ?, ?, ?, ?, 'Pending')");
    $stmt->bind_param("issss", $user_id, $fullname, $department, $subject, $description);

    if ($stmt->execute()) {
      $_SESSION['alert'] = [
        'type' => 'success',
        'message' => 'Ticket submitted successfully!',
        'redirect' => 'admin_dashboard.php'
      ];
    } else {
      $_SESSION['alert'] = [
        'type' => 'error',
        'message' => 'Error: ' . $stmt->error,
      ];
    }

    $stmt->close();
  } else {
    $_SESSION['alert'] = [
      'type' => 'error',
      'message' => 'User not found.',
    ];
  }

  $user_query->close();

  // ✅ REDIRECT TO AVOID FORM RESUBMISSION
  header("Location: " . $_SERVER['PHP_SELF']);
  exit;
}

// Combine all tickets from the existing queries
$all_tickets = array_merge(
  iterator_to_array($result_active),
  iterator_to_array($result_resolved),
  iterator_to_array($result_rejected)
);

// Quick counts for the summary cards (derived from the same dataset, no extra queries)
$counts = ['Pending' => 0, 'In Progress' => 0, 'Rejected' => 0, 'Resolved' => 0, 'Cancelled' => 0];
foreach ($all_tickets as $t) {
  if (isset($counts[$t['status']])) $counts[$t['status']]++;
}
$total = count($all_tickets);

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

  <title>IT Service Request</title>

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
  <link rel="stylesheet" href="./css/ticket_view.css"> <!-- CSS EXTERNAL -->

  <!-- Vendors CSS -->
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <link rel="stylesheet" href="../assets/vendor/libs/apex-charts/apex-charts.css" />

  <!-- Page CSS -->
  <link rel="stylesheet" href="../../assets/vendor/css/pages/app-calendar.css">
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

    .tk-wrap { font-family: inherit; color: var(--tk-text); }

    /* ── Summary cards ─────────────────────────────────────────────── */
    .tk-stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 14px;
      margin-bottom: 22px;
    }
    .tk-stat {
      background: var(--tk-surface);
      border: 1px solid var(--tk-border);
      border-radius: var(--tk-radius);
      padding: 16px 18px;
      box-shadow: var(--tk-shadow);
      position: relative;
      overflow: hidden;
    }
    .tk-stat::before {
      content: "";
      position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
      background: var(--bar, var(--tk-primary));
    }
    .tk-stat .tk-stat-num { font-size: 26px; font-weight: 700; line-height: 1.1; }
    .tk-stat .tk-stat-label { font-size: 12.5px; color: var(--tk-text-muted); margin-top: 4px; font-weight: 500; letter-spacing: .2px; }
    .tk-stat.is-total    { --bar: var(--tk-primary); }
    .tk-stat.is-pending  { --bar: var(--tk-pending); }
    .tk-stat.is-progress { --bar: var(--tk-progress); }
    .tk-stat.is-resolved { --bar: var(--tk-resolved); }
    .tk-stat.is-rejected { --bar: var(--tk-rejected); }

    /* ── Toolbar ────────────────────────────────────────────────────── */
    .tk-toolbar {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 18px;
    }
    .tk-btn-create {
      background: var(--tk-primary);
      color: #fff;
      border: none;
      padding: 10px 18px;
      border-radius: 9px;
      font-weight: 600;
      font-size: 14px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      box-shadow: 0 4px 10px -4px rgba(124,185,255,.55);
      transition: transform .12s ease, box-shadow .12s ease, background .12s ease;
    }
    .tk-btn-create:hover { background: #4e96f0; color:#fff; transform: translateY(-1px); box-shadow: 0 6px 14px -4px rgba(124,185,255,.6); }
    .tk-btn-create svg { width: 16px; height: 16px; }

    .tk-controls { display: flex; gap: 8px; flex-wrap: wrap; }
    .tk-search { position: relative; }
    .tk-search svg {
      position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
      width: 15px; height: 15px; color: var(--tk-text-muted); pointer-events: none;
    }
    .tk-search input {
      padding: 8px 12px 8px 32px;
      border: 1px solid var(--tk-border);
      border-radius: 8px;
      font-size: 13.5px;
      width: 220px;
      background: var(--tk-surface);
      outline: none;
      transition: border-color .12s ease, box-shadow .12s ease;
    }
    .tk-search input:focus { border-color: var(--tk-primary); box-shadow: 0 0 0 3px var(--tk-primary-soft); }

    .tk-pills { display: flex; gap: 6px; flex-wrap: wrap; }
    .tk-pill {
      border: 1px solid var(--tk-border);
      background: var(--tk-surface);
      color: var(--tk-text-muted);
      padding: 7px 13px;
      border-radius: 999px;
      font-size: 12.5px;
      font-weight: 600;
      cursor: pointer;
      transition: all .12s ease;
      white-space: nowrap;
    }
    .tk-pill:hover { border-color: var(--tk-primary); color: var(--tk-primary); }
    .tk-pill.active { background: var(--tk-text); color: #fff; border-color: var(--tk-text); }

    /* ── Table card ─────────────────────────────────────────────────── */
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
      justify-content: space-between;
    }
    .tk-card-head span.tk-count { font-weight: 500; font-size: 12.5px; color: var(--tk-text-muted); }

    .tk-table-scroll { max-height: 560px; overflow-y: auto; }
    table.tk-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    table.tk-table thead th {
      position: sticky; top: 0; z-index: 1;
      background: var(--tk-bg);
      text-align: left;
      font-size: 11.5px;
      text-transform: uppercase;
      letter-spacing: .5px;
      color: var(--tk-text-muted);
      font-weight: 700;
      padding: 11px 20px;
      border-bottom: 1px solid var(--tk-border);
      cursor: pointer;
      user-select: none;
    }
    table.tk-table thead th.asc::after { content: " ▲"; font-size: 9px; }
    table.tk-table thead th.desc::after { content: " ▼"; font-size: 9px; }
    table.tk-table tbody td {
      padding: 13px 20px;
      border-bottom: 1px solid var(--tk-border);
      vertical-align: middle;
      color: var(--tk-text);
    }
    table.tk-table tbody tr { cursor: pointer; transition: background .1s ease; }
    table.tk-table tbody tr:hover { background: #fafbfd; }
    table.tk-table tbody tr:last-child td { border-bottom: none; }

    .tk-id { font-weight: 700; color: var(--tk-text-muted); font-variant-numeric: tabular-nums; }
    .tk-subject { font-weight: 600; }

    .tk-badge {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 4px 10px; border-radius: 999px;
      font-size: 12px; font-weight: 700;
    }
    .tk-badge .dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    .tk-badge.st-pending   { background: var(--tk-pending-bg); color: var(--tk-pending); }
    .tk-badge.st-progress  { background: var(--tk-progress-bg); color: var(--tk-progress); }
    .tk-badge.st-rejected  { background: var(--tk-rejected-bg); color: var(--tk-rejected); }
    .tk-badge.st-resolved  { background: var(--tk-resolved-bg); color: var(--tk-resolved); }
    .tk-badge.st-cancelled { background: var(--tk-cancelled-bg); color: var(--tk-cancelled); }

    .tk-assignee { display: flex; align-items: center; gap: 8px; }
    .tk-avatar {
      width: 26px; height: 26px; border-radius: 50%;
      background: var(--tk-primary-soft); color: var(--tk-primary);
      display: flex; align-items: center; justify-content: center;
      font-size: 11px; font-weight: 700; flex-shrink: 0;
    }
    .tk-avatar.is-unassigned { background: #f0f1f3; color: #9aa0ab; }

    .tk-time { color: var(--tk-text-muted); font-size: 12.5px; }

    .tk-view-link {
      color: var(--tk-primary);
      font-weight: 600;
      text-decoration: none;
      font-size: 13px;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .tk-view-link:hover { text-decoration: underline; color: var(--tk-primary); }

    .tk-empty {
      padding: 60px 20px;
      text-align: center;
      color: var(--tk-text-muted);
    }
    .tk-empty svg { width: 42px; height: 42px; margin-bottom: 12px; opacity: .4; }
    .tk-empty p { margin: 0; font-size: 14px; }
    .tk-empty span { font-size: 12.5px; }

    @media (max-width: 700px) {
      .tk-search input { width: 100%; }
      table.tk-table thead { display: none; }
      table.tk-table, table.tk-table tbody, table.tk-table tr, table.tk-table td { display: block; width: 100%; }
      table.tk-table tbody tr { padding: 12px 16px; border-bottom: 1px solid var(--tk-border); }
      table.tk-table tbody td { padding: 4px 0; border: none; }
      table.tk-table tbody td:first-child { font-weight: 700; color: var(--tk-text-muted); }
    }

    /* ── Submit ticket modal ───────────────────────────────────────── */
    #editModal .modal-content {
      border: none;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 24px 60px -20px rgba(20,20,43,.35);
    }
    #editModal .tk-modal-header {
      background: linear-gradient(135deg, var(--tk-primary) 0%, #4e96f0 100%);
      padding: 22px 26px;
      color: #fff;
      position: relative;
    }
    #editModal .tk-modal-header .tk-modal-eyebrow {
      font-size: 11.5px;
      font-weight: 700;
      letter-spacing: .6px;
      text-transform: uppercase;
      opacity: .8;
      margin-bottom: 3px;
    }
    #editModal .tk-modal-header h5 {
      font-size: 19px;
      font-weight: 700;
      margin: 0;
      color: #fff;
    }
    #editModal .tk-modal-header p {
      margin: 4px 0 0;
      font-size: 13px;
      opacity: .85;
    }
    #editModal .tk-modal-header .btn-close {
      position: absolute;
      top: 18px;
      right: 20px;
      opacity: .9;
    }
    #editModal .modal-body { padding: 26px; background: var(--tk-surface); }

    #editModal .tk-field { margin-bottom: 18px; }
    #editModal .tk-field:last-of-type { margin-bottom: 4px; }
    #editModal .tk-field label {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 13px;
      font-weight: 700;
      color: var(--tk-text);
      margin-bottom: 7px;
    }
    #editModal .tk-field label .tk-req { color: var(--tk-rejected); font-weight: 700; }
    #editModal .tk-field label svg { width: 14px; height: 14px; color: var(--tk-text-muted); flex-shrink: 0; }

    #editModal select.form-control,
    #editModal textarea.form-control {
      border: 1.5px solid var(--tk-border);
      border-radius: 10px;
      padding: 11px 13px;
      font-size: 13.5px;
      color: var(--tk-text);
      background: var(--tk-bg);
      transition: border-color .12s ease, box-shadow .12s ease, background .12s ease;
      width: 100%;
    }
    #editModal select.form-control:focus,
    #editModal textarea.form-control:focus {
      outline: none;
      border-color: var(--tk-primary);
      box-shadow: 0 0 0 3px var(--tk-primary-soft);
      background: var(--tk-surface);
    }
    #editModal textarea.form-control {
      min-height: 120px;
      resize: vertical;
      line-height: 1.5;
    }
    #editModal .tk-hint {
      font-size: 12px;
      color: var(--tk-text-muted);
      margin-top: 6px;
    }
    #editModal .tk-char-count {
      font-size: 11.5px;
      color: var(--tk-text-muted);
      text-align: right;
      margin-top: 4px;
    }

    #editModal .modal-footer {
      border-top: 1px solid var(--tk-border);
      padding: 16px 26px;
      background: var(--tk-bg);
      display: flex;
      justify-content: flex-end;
      gap: 10px;
    }
    #editModal .tk-btn-cancel {
      background: var(--tk-surface);
      border: 1.5px solid var(--tk-border);
      color: var(--tk-text);
      font-weight: 600;
      font-size: 13.5px;
      padding: 10px 18px;
      border-radius: 9px;
      cursor: pointer;
      transition: border-color .12s ease, background .12s ease;
    }
    #editModal .tk-btn-cancel:hover { border-color: #c7cbd4; background: #f5f6f8; }
    #editModal .tk-btn-submit {
      background: var(--tk-primary);
      border: none;
      color: #fff;
      font-weight: 600;
      font-size: 13.5px;
      padding: 10px 20px;
      border-radius: 9px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 7px;
      box-shadow: 0 4px 10px -4px rgba(124,185,255,.55);
      transition: background .12s ease, transform .12s ease;
    }
    #editModal .tk-btn-submit:hover { background: #4e96f0; transform: translateY(-1px); }
    #editModal .tk-btn-submit svg { width: 15px; height: 15px; }

    /* ── Ticket details modal ──────────────────────────────────────── */
    #ticketModal .modal-content {
      border: none;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 24px 60px -20px rgba(20,20,43,.35);
    }
    #ticketModal .tk-view-header {
      background: linear-gradient(135deg, var(--tk-primary) 0%, #4e96f0 100%);
      padding: 22px 26px;
      color: #fff;
      position: relative;
    }
    #ticketModal .tk-view-header .tk-modal-eyebrow {
      font-size: 11.5px;
      font-weight: 700;
      letter-spacing: .6px;
      text-transform: uppercase;
      opacity: .8;
      margin-bottom: 3px;
    }
    #ticketModal .tk-view-header h5 {
      font-size: 19px;
      font-weight: 700;
      margin: 0;
      color: #fff;
    }
    #ticketModal .tk-view-header .btn-close {
      position: absolute;
      top: 18px;
      right: 20px;
      opacity: .9;
    }
    #ticketModal .tk-view-status {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 5px 12px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 700;
      background: rgba(255,255,255,.18);
      margin-top: 10px;
    }
    #ticketModal .tk-view-status .dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

    #ticketModal .modal-body { padding: 24px 26px; background: var(--tk-surface); }

    #ticketModal .tk-view-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 14px;
      margin-bottom: 20px;
    }
    #ticketModal .tk-view-item {
      background: var(--tk-bg);
      border: 1px solid var(--tk-border);
      border-radius: 10px;
      padding: 11px 13px;
    }
    #ticketModal .tk-view-item .tk-view-label {
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
    #ticketModal .tk-view-item .tk-view-label svg { width: 12px; height: 12px; flex-shrink: 0; }
    #ticketModal .tk-view-item .tk-view-value {
      font-size: 13.5px;
      font-weight: 600;
      color: var(--tk-text);
      word-break: break-word;
    }
    #ticketModal .tk-view-item.tk-view-assigned .tk-view-value {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    #ticketModal .tk-view-subject {
      font-size: 16px;
      font-weight: 700;
      color: var(--tk-text);
      margin-bottom: 18px;
      padding-bottom: 16px;
      border-bottom: 1px solid var(--tk-border);
    }
    #ticketModal .tk-view-subject .tk-view-label {
      font-size: 10.5px;
      text-transform: uppercase;
      letter-spacing: .5px;
      font-weight: 700;
      color: var(--tk-text-muted);
      margin-bottom: 5px;
    }

    #ticketModal .tk-view-desc-label {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 13px;
      font-weight: 700;
      color: var(--tk-text);
      margin-bottom: 8px;
    }
    #ticketModal .tk-view-desc-label svg { width: 14px; height: 14px; color: var(--tk-text-muted); }
    #ticketModal .tk-view-desc {
      background: var(--tk-bg);
      border: 1px solid var(--tk-border);
      border-radius: 10px;
      padding: 14px;
      font-size: 13.5px;
      line-height: 1.6;
      color: var(--tk-text);
      white-space: pre-wrap;
      min-height: 80px;
    }

    #ticketModal .modal-footer {
      border-top: 1px solid var(--tk-border);
      padding: 16px 26px;
      background: var(--tk-bg);
      display: flex;
      justify-content: flex-end;
      gap: 10px;
    }
    #ticketModal .tk-btn-cancel {
      background: var(--tk-surface);
      border: 1.5px solid var(--tk-border);
      color: var(--tk-text);
      font-weight: 600;
      font-size: 13.5px;
      padding: 10px 18px;
      border-radius: 9px;
      cursor: pointer;
      transition: border-color .12s ease, background .12s ease;
    }
    #ticketModal .tk-btn-cancel:hover { border-color: #c7cbd4; background: #f5f6f8; }
    #ticketModal .tk-btn-view {
      background: var(--tk-primary);
      border: none;
      color: #fff;
      font-weight: 600;
      font-size: 13.5px;
      padding: 10px 20px;
      border-radius: 9px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 7px;
      box-shadow: 0 4px 10px -4px rgba(124,185,255,.55);
      transition: background .12s ease, transform .12s ease;
    }
    #ticketModal .tk-btn-view:hover { background: #4e96f0; color: #fff; transform: translateY(-1px); }
    #ticketModal .tk-btn-view svg { width: 14px; height: 14px; }
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
      <div class="tk-wrap">

        <!-- Summary -->
        <div class="tk-stats">
          <div class="tk-stat is-total">
            <div class="tk-stat-num"><?= $total ?></div>
            <div class="tk-stat-label">Total tickets</div>
          </div>
          <div class="tk-stat is-pending">
            <div class="tk-stat-num"><?= $counts['Pending'] ?></div>
            <div class="tk-stat-label">Pending</div>
          </div>
          <div class="tk-stat is-progress">
            <div class="tk-stat-num"><?= $counts['In Progress'] ?></div>
            <div class="tk-stat-label">In progress</div>
          </div>
          <div class="tk-stat is-resolved">
            <div class="tk-stat-num"><?= $counts['Resolved'] ?></div>
            <div class="tk-stat-label">Resolved</div>
          </div>
          <div class="tk-stat is-rejected">
            <div class="tk-stat-num"><?= $counts['Rejected'] ?></div>
            <div class="tk-stat-label">Rejected</div>
          </div>
        </div>

        <!-- Toolbar -->
        <div class="tk-toolbar">
          <a class="tk-btn-create btn-create" href="#">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            Submit a new ticket
          </a>

          <div class="tk-controls">
            <div class="tk-search">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
              <input type="text" id="searchInput" placeholder="Search by subject or ticket #">
            </div>
            <div class="tk-pills" id="statusFilterPills">
              <button type="button" class="tk-pill active" data-value="all">All</button>
              <button type="button" class="tk-pill" data-value="Pending">Pending</button>
              <button type="button" class="tk-pill" data-value="In Progress">In Progress</button>
              <button type="button" class="tk-pill" data-value="Resolved">Resolved</button>
              <button type="button" class="tk-pill" data-value="Rejected">Rejected</button>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="tk-card">
          <div class="tk-card-head">
            All tickets
            <span class="tk-count" id="visibleCount"><?= $total ?> ticket<?= $total === 1 ? '' : 's' ?></span>
          </div>

          <?php if (!empty($all_tickets)): ?>
            <div class="tk-table-scroll">
              <table class="tk-table" id="allTicketsTable">
                <thead>
                  <tr>
                    <th>Ticket #</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th>Created At</th>
                    <th>Last Updated</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($all_tickets as $row):
                    $status = htmlspecialchars($row['status']);
                    $statusClass = match ($status) {
                      'Pending' => 'st-pending',
                      'In Progress' => 'st-progress',
                      'Rejected' => 'st-rejected',
                      'Resolved' => 'st-resolved',
                      'Cancelled' => 'st-cancelled',
                      default => 'st-pending'
                    };
                    $assignedRaw = $row['assigned_to'] ? htmlspecialchars($row['assigned_to']) : 'Unassigned';
                    $initials = $row['assigned_to']
                      ? strtoupper(substr($assignedRaw, 0, 1) . (strpos($assignedRaw, ' ') !== false ? substr($assignedRaw, strpos($assignedRaw, ' ') + 1, 1) : ''))
                      : '–';
                  ?>
                    <tr class="ticket-row" data-ticket-id="<?= htmlspecialchars($row['id']) ?>"
                      data-ticket-subject="<?= htmlspecialchars($row['subject']) ?>"
                      data-ticket-status="<?= $status ?>"
                      data-ticket-assigned="<?= $assignedRaw ?>"
                      data-ticket-created="<?= date('Y-m-d H:i:s', strtotime($row['created_at'])) ?>"
                      data-ticket-updated="<?= $row['last_update'] ? date('Y-m-d H:i:s', strtotime($row['last_update'])) : 'No updates yet' ?>"
                      data-ticket-description="<?= htmlspecialchars($row['description'] ?? 'No description provided') ?>"
                      data-ticket-name="<?= htmlspecialchars($row['submitted_by'] ?? $username) ?>"
                      title="Click to view">
                      <td class="tk-id">#<?= $row['id'] ?></td>
                      <td class="tk-subject subject-col"><?= htmlspecialchars($row['subject']) ?></td>
                      <td>
                        <span class="tk-badge <?= $statusClass ?>"><span class="dot"></span><?= $status ?></span>
                      </td>
                      <td>
                        <div class="tk-assignee">
                          <div class="tk-avatar <?= $row['assigned_to'] ? '' : 'is-unassigned' ?>"><?= $initials ?></div>
                          <?= $assignedRaw ?>
                        </div>
                      </td>
                      <td class="tk-time"><?= date("Y-m-d H:i:s", strtotime($row['created_at'])) ?></td>
                      <td class="tk-time">
                        <?= $row['last_update'] ? date("Y-m-d H:i:s", strtotime($row['last_update'])) : 'No updates yet' ?>
                      </td>
                      <td onclick="event.stopPropagation()">
                        <a href="view_ticket.php?ticket_id=<?= $row['id'] ?>" class="tk-view-link">
                          View
                          <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M9 6l6 6-6 6"/></svg>
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="tk-empty" id="noResults" style="display:none;">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
              <p>No tickets match your search</p>
              <span>Try a different keyword or clear the status filter.</span>
            </div>
          <?php else: ?>
            <div class="tk-empty">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 12h6M9 16h6M9 8h6M5 4h14v16l-3-2-3 2-3-2-3 2V4z"/></svg>
              <p>No tickets yet</p>
              <span>Submitted tickets will show up here.</span>
            </div>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </div>

  <!-- 📋 Ticket Details Modal -->
  <div class="modal fade" id="ticketModal" tabindex="-1" aria-labelledby="ticketModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="tk-view-header">
          <!-- <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button> -->
          <div class="tk-modal-eyebrow">Ticket <span id="modalTicketId"></span></div>
          <h5 class="modal-title" id="ticketModalLabel">Ticket details</h5>
          <span class="tk-view-status" id="modalTicketStatusWrap">
            <span class="dot"></span><span id="modalTicketStatus"></span>
          </span>
        </div>

        <div class="modal-body">
          <div class="tk-view-subject">
            <div class="tk-view-label">Subject</div>
            <div id="modalTicketSubject"></div>
          </div>

          <div class="tk-view-grid">
            <div class="tk-view-item">
              <div class="tk-view-label">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Submitted by
              </div>
              <div class="tk-view-value" id="modalTicketName"></div>
            </div>
            <div class="tk-view-item tk-view-assigned">
              <div class="tk-view-label">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6M23 11h-6"/></svg>
                Assigned to
              </div>
              <div class="tk-view-value" id="modalTicketAssigned"></div>
            </div>
            <div class="tk-view-item">
              <div class="tk-view-label">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                Created at
              </div>
              <div class="tk-view-value" id="modalTicketCreated"></div>
            </div>
            <div class="tk-view-item">
              <div class="tk-view-label">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                Last updated
              </div>
              <div class="tk-view-value" id="modalTicketUpdated"></div>
            </div>
          </div>

          <div class="tk-view-desc-label">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
            Description
          </div>
          <div id="modalTicketDescription" class="tk-view-desc"></div>
        </div>

        <div class="modal-footer">
          <button class="tk-btn-cancel" data-bs-dismiss="modal">Close</button>
          <a id="viewTicketLink" href="#" class="tk-btn-view">
            Open full ticket
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Create Modal -->
  <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="tk-modal-header">
          <!-- <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button> -->
          <div class="tk-modal-eyebrow">New request</div>
          <h5 class="modal-title" id="editModalLabel">Submit a new ticket</h5>
          <p>Tell us what's going on and we'll route it to the right team.</p>
        </div>

        <div class="modal-body">
          <?php if (isset($message)): ?>
            <p class="message"><?php echo htmlspecialchars($message); ?></p>
          <?php endif; ?>

          <form method="POST" class="ticket-form" id="ticketForm" action="serviceRequest.php">

            <div class="tk-field">
              <label for="subject">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h7"/></svg>
                Type of concern <span class="tk-req">*</span>
              </label>
              <select name="subject" id="subject" class="form-control" required>
                <option value="" disabled selected>Select the type of concern</option>

                <optgroup label="IT Service Requests">
                  <option>Computer and Electronic Installation, Maintenance and Repairs</option>
                  <option>Borrowing of IT Equipment</option>
                  <option>Hardware Inspection</option>
                  <option>Network Infrastructure</option>
                </optgroup>

                <optgroup label="DistrictOne Concerns">
                  <option>Payslip Concern</option>
                  <option>System Bug / Error</option>
                  <option>Login / Account Issues</option>
                  <option>Data Correction Request</option>
                </optgroup>

                <!-- <optgroup label="🖥️ Software and Applications">
                  <option>Application Development</option>
                  <option>Application Integration and Maintenance</option>
                  <option>Software Development</option>
                </optgroup> -->

                <optgroup label="Communication">
                  <option>Telephony</option>
                  <option>Company Website</option>
                  <option>Communication Applications</option>
                </optgroup>

                <optgroup label="General / Administrative">
                  <option>Internal Announcements</option>
                </optgroup>
              </select>
            </div>

            <div class="tk-field">
              <label for="description">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4v16h16v-7M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Describe your issue <span class="tk-req">*</span>
              </label>
              <textarea name="description" id="description" class="form-control" maxlength="1000"
                placeholder="Include what happened, when it started, and any error messages you saw..."
                required></textarea>
              <div class="tk-char-count"><span id="descCount">0</span>/1000</div>
              <div class="tk-hint">The more detail you give, the faster we can help.</div>
            </div>

            <div class="modal-footer">
              <button type="button" class="tk-btn-cancel" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="tk-btn-submit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                Submit ticket
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="content-backdrop fade"></div>
  <div class="layout-overlay layout-menu-toggle"></div>

  <script src="../assets/vendor/js/bootstrap.js"></script>
  <script src="../assets/vendor/js/menu.js"></script>
  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/dashboards-analytics.js"></script>
  <script async defer src="https://buttons.github.io/buttons.js"></script>

  <!-- ✅ Filter + Search + Sorting Script -->
  <script>
    const table = document.getElementById('allTicketsTable');
    const rows = table ? Array.from(table.querySelectorAll('tbody tr')) : [];
    const pills = document.querySelectorAll('#statusFilterPills .tk-pill');
    const searchInput = document.getElementById('searchInput');
    const visibleCountEl = document.getElementById('visibleCount');
    const noResultsEl = document.getElementById('noResults');
    let sortDirections = {};
    let activeStatus = 'all';

    function filterAndSearch() {
      const search = (searchInput?.value || '').toLowerCase();
      let visible = 0;

      rows.forEach(row => {
        const statusText = (row.dataset.ticketStatus || '').toLowerCase();
        const rowText = row.innerText.toLowerCase();
        const matchStatus = activeStatus === 'all' || statusText === activeStatus.toLowerCase();
        const matchSearch = !search || rowText.includes(search);
        const show = matchStatus && matchSearch;

        row.style.display = show ? '' : 'none';
        if (show) visible++;
      });

      if (visibleCountEl) visibleCountEl.textContent = visible + ' ticket' + (visible === 1 ? '' : 's');
      if (noResultsEl) noResultsEl.style.display = visible === 0 ? 'block' : 'none';
    }

    if (searchInput) searchInput.addEventListener('input', filterAndSearch);

    pills.forEach(pill => {
      pill.addEventListener('click', () => {
        pills.forEach(p => p.classList.remove('active'));
        pill.classList.add('active');
        activeStatus = pill.dataset.value;
        filterAndSearch();
      });
    });

    // ✅ Sorting by clicking headers
    if (table) {
      table.querySelectorAll('th').forEach((th, index) => {
        th.addEventListener('click', () => {
          const isAsc = !sortDirections[index];
          sortDirections[index] = isAsc;

          const sortedRows = [...rows].sort((a, b) => {
            const aText = a.cells[index].innerText.trim().toLowerCase();
            const bText = b.cells[index].innerText.trim().toLowerCase();
            if (!isNaN(aText) && !isNaN(bText) && aText !== '' && bText !== '') {
              return isAsc ? aText - bText : bText - aText;
            }
            return isAsc ? aText.localeCompare(bText) : bText.localeCompare(aText);
          });

          const tbody = table.querySelector('tbody');
          tbody.innerHTML = '';
          sortedRows.forEach(row => tbody.appendChild(row));

          table.querySelectorAll('th').forEach(h => h.classList.remove('asc', 'desc'));
          th.classList.add(isAsc ? 'asc' : 'desc');
        });
      });
    }
  </script>

  <!-- SCRIPT FOR VIEW MODAL TICKET -->
  <script>
    document.querySelectorAll('.ticket-row').forEach(row => {
      row.addEventListener('click', function () {
        const id = this.dataset.ticketId;
        const subject = this.dataset.ticketSubject;
        const status = this.dataset.ticketStatus;
        const assigned = this.dataset.ticketAssigned;
        const created = this.dataset.ticketCreated;
        const updated = this.dataset.ticketUpdated;
        const description = this.dataset.ticketDescription;
        const name = this.dataset.ticketName;

        // Fill modal fields
        document.getElementById('modalTicketId').textContent = id;
        document.getElementById('modalTicketSubject').textContent = subject;
        document.getElementById('modalTicketStatus').textContent = status;
        document.getElementById('modalTicketAssigned').textContent = assigned;
        document.getElementById('modalTicketCreated').textContent = created;
        document.getElementById('modalTicketUpdated').textContent = updated;
        document.getElementById('modalTicketDescription').textContent = description;
        document.getElementById('modalTicketName').textContent = name;

        // Dynamic status pill color
        const statusWrap = document.getElementById('modalTicketStatusWrap');
        statusWrap.style.background = 'rgba(255,255,255,.18)';
        statusWrap.style.color = '#fff';
        if (status === 'Pending') { statusWrap.style.background = '#fdf3da'; statusWrap.style.color = '#b8860b'; }
        else if (status === 'In Progress') { statusWrap.style.background = '#e7eefd'; statusWrap.style.color = '#2563eb'; }
        else if (status === 'Rejected') { statusWrap.style.background = '#fbe9e7'; statusWrap.style.color = '#c0392b'; }
        else if (status === 'Resolved') { statusWrap.style.background = '#e4f7ea'; statusWrap.style.color = '#1f9d55'; }
        else if (status === 'Cancelled') { statusWrap.style.background = '#eef0f2'; statusWrap.style.color = '#6b7280'; }

        // Set view link
        document.getElementById('viewTicketLink').href = 'view_ticket.php?ticket_id=' + id;

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('ticketModal'));
        modal.show();
      });
    });
  </script>

  <script>
    document.addEventListener("DOMContentLoaded", function () {
      document.querySelectorAll(".btn-create").forEach(button => {
        button.addEventListener("click", function (event) {
          event.preventDefault(); // Prevent default link behavior

          var editModal = new bootstrap.Modal(document.getElementById("editModal"));
          editModal.show();
        });
      });

      const descField = document.getElementById('description');
      const descCount = document.getElementById('descCount');
      if (descField && descCount) {
        const updateCount = () => descCount.textContent = descField.value.length;
        descField.addEventListener('input', updateCount);
        updateCount();
      }
    });
  </script>
  <?php if ($alert): ?>
    <script>
      Swal.fire({
        icon: '<?= $alert['type'] ?>',
        title: '<?= $alert['type'] === 'success' ? 'Success' : 'Oops!' ?>',
        text: '<?= $alert['message'] ?>',
        confirmButtonColor: '#007bff'
      });
    </script>
  <?php endif; ?>

  <script>
    const saveBtn = document.getElementById('save');
    if (saveBtn) {
      saveBtn.addEventListener('click', function (e) {
        e.preventDefault(); // Prevent default form action

        Swal.fire({
          title: 'Submit Ticket',
          text: 'Are you sure you want to save?',
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: 'Yes',
          cancelButtonText: 'No',
          confirmButtonColor: '#007bff',
          cancelButtonColor: '#d33',
        }).then((result) => {
          if (result.isConfirmed) {
            Swal.fire({
              icon: 'success',
              title: 'Saved!',
              text: 'Your support ticket has been saved!',
              confirmButtonColor: '#007bff'
            }).then(() => {
              document.getElementById('ticketForm').submit();
            });
          }
        });
      });
    }
  </script>

</body>

</html>