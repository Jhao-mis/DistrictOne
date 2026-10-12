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

// Fetch IT Personnel by explicit permission or MIS department. This works for either role.
$mis_query = $conn->query("SELECT u.id, CONCAT(u.firstname, ' ', u.lastname) AS name
                           FROM users u
                  LEFT JOIN user_permissions up ON up.user_id = u.id
                  LEFT JOIN permissions p ON p.id = up.permission_id AND p.permission_key = 'mis.personnel'
                  WHERE p.permission_key = 'mis.personnel'
                              OR u.department IN ('Management Information Services Section', 'Manage Information Services Section')
                  GROUP BY u.id, u.firstname, u.lastname
                           ORDER BY u.firstname, u.lastname");
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

// Helper: initials for the requester avatar bubble
function tkt_initials($name) {
  $parts = preg_split('/\s+/', trim((string)$name));
  $initials = '';
  foreach (array_slice($parts, 0, 2) as $p) {
    if ($p !== '') $initials .= strtoupper(substr($p, 0, 1));
  }
  return $initials !== '' ? $initials : '?';
}

// Helper: tone for a priority value. Only used if the tickets table has a
// 'priority' column — degrades gracefully (no badge) if it doesn't.
function tkt_priority_tone($priority) {
  $p = strtolower((string)$priority);
  if (strpos($p, 'urgent') !== false || strpos($p, 'critical') !== false) return 'red';
  if (strpos($p, 'high') !== false) return 'amber';
  if (strpos($p, 'low') !== false) return 'gray';
  return 'blue'; // medium / normal / default
}

$status_slug   = strtolower(str_replace(' ', '-', $ticket['status']));
$has_priority  = isset($ticket['priority']) && trim((string)$ticket['priority']) !== '';
$has_category  = isset($ticket['category']) && trim((string)$ticket['category']) !== '';
$priority_tone = $has_priority ? tkt_priority_tone($ticket['priority']) : '';
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
      --tkt-bg: #f4f7fb;
      --tkt-surface: #ffffff;
      --tkt-border: #e1e8f2;
      --tkt-border-strong: #cddcef;
      --tkt-text: #1c2634;
      --tkt-muted: #64748b;
      --tkt-faint: #9aa8ba;
      --tkt-primary: #3b82f6;
      --tkt-primary-soft: #eaf2ff;
      --tkt-primary-deep: #1d4ed8;
      --tkt-radius: 14px;
      --tkt-radius-sm: 10px;
      --tkt-shadow: 0 1px 2px rgba(20,20,43,.04), 0 10px 26px -16px rgba(30,64,140,.20);
      --tkt-amber: #b45309;
      --tkt-amber-soft: #fef3dc;
      --tkt-blue: #1d4ed8;
      --tkt-blue-soft: #e7f0ff;
      --tkt-green: #15803d;
      --tkt-green-soft: #e5f8ec;
      --tkt-red: #b91c1c;
      --tkt-red-soft: #fdecec;
      --tkt-gray: #52606d;
      --tkt-gray-soft: #eef1f5;
      --tkt-ease: cubic-bezier(.4,0,.2,1);
    }

    * { box-sizing: border-box; }

    body {
      background:
        radial-gradient(circle at top right, rgba(59,130,246,.08), transparent 32rem),
        var(--tkt-bg);
    }

    /* Make sure SweetAlert's dark backdrop sits above the fixed sidebar/menu,
       otherwise the sidebar stays bright while the rest of the page dims. */
    .swal2-container { z-index: 99999 !important; }
    .swal2-popup { z-index: 100000 !important; }

    .tkt-page {
      max-width: 1280px;
      width: 100%;
      min-width: 0;
      margin: 0 auto;
      padding: 16px 24px 32px;
      font-family: "Public Sans", -apple-system, BlinkMacSystemFont, sans-serif;
    }
    /* Use the extra width on large monitors instead of leaving it empty */
    @media (min-width: 1600px) {
      .tkt-page { max-width: 1560px; }
      .tkt-layout { grid-template-columns: minmax(0, 1fr) 380px; }
    }

    /* ── Top bar: breadcrumb + back link ───────────────────── */
    .tkt-topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
      margin-bottom: 10px;
    }
    .tkt-breadcrumb {
      display: flex; align-items: center; gap: 6px;
      font-size: 13px; font-weight: 600; color: var(--tkt-muted);
    }
    .tkt-breadcrumb i { font-size: 15px; color: var(--tkt-faint); }
    .tkt-breadcrumb-current { color: var(--tkt-text); font-weight: 800; }
    .tkt-back-link {
      display: inline-flex; align-items: center; gap: 6px;
      font-size: 13px; font-weight: 700; color: var(--tkt-primary-deep);
      background: var(--tkt-surface);
      border: 1.5px solid var(--tkt-border);
      border-radius: var(--tkt-radius-sm);
      padding: 8px 14px;
      text-decoration: none;
      transition: border-color .15s var(--tkt-ease), background .15s var(--tkt-ease);
    }
    .tkt-back-link:hover { border-color: var(--tkt-primary); background: var(--tkt-primary-soft); }

    /* ── Header card ────────────────────────────────────────── */
    .tkt-header-bar {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
      background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
      border-radius: var(--tkt-radius);
      box-shadow: var(--tkt-shadow);
      padding: 16px 24px;
      margin-bottom: 14px;
      flex-wrap: wrap;
    }
    .tkt-id {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
      font-weight: 800;
      letter-spacing: .04em;
      color: #fff;
      background: rgba(255,255,255,.16);
      padding: 4px 11px;
      border-radius: 999px;
      margin-bottom: 8px;
    }
    .tkt-subject {
      font-size: 20px;
      font-weight: 800;
      color: #fff;
      margin: 0 0 10px;
      letter-spacing: -.2px;
      line-height: 1.3;
      overflow-wrap: anywhere;
    }
    .tkt-meta-row { display: flex; flex-wrap: wrap; gap: 8px; }
    .tkt-meta {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 6px 10px;
      border: 1px solid rgba(255,255,255,.24);
      border-radius: 8px;
      background: rgba(255,255,255,.10);
      font-size: 12.5px; font-weight: 500;
      color: rgba(255,255,255,.92);
    }
    .tkt-meta i { font-size: 15px; color: rgba(255,255,255,.75); }
    .tkt-header-bar > :first-child,
    .tkt-header-right { min-width: 0; }
    .tkt-header-right {
      flex-shrink: 0;
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 8px;
    }

    .tkt-status-badge {
      display: inline-flex; align-items: center; gap: 6px;
      font-size: 12.5px; font-weight: 800; letter-spacing: .02em;
      padding: 7px 16px; border-radius: 999px;
      white-space: nowrap;
    }
    .tkt-status-badge::before {
      content: ""; width: 7px; height: 7px; border-radius: 50%; background: currentColor; flex-shrink: 0;
    }
    /* Standalone (non-header) status colors */
    .status-pending { background: var(--tkt-amber-soft); color: var(--tkt-amber); }
    .status-in-progress { background: var(--tkt-blue-soft); color: var(--tkt-blue); }
    .status-resolved { background: var(--tkt-green-soft); color: var(--tkt-green); }
    .status-cancelled { background: var(--tkt-red-soft); color: var(--tkt-red); }
    /* On the gradient header, use solid white pills so status reads instantly */
    .tkt-header-bar .tkt-status-badge { box-shadow: 0 4px 10px -6px rgba(0,0,0,.35); }
    .tkt-header-bar .tkt-status-badge.status-pending { background: #fff; color: var(--tkt-amber); }
    .tkt-header-bar .tkt-status-badge.status-in-progress { background: #fff; color: var(--tkt-blue); }
    .tkt-header-bar .tkt-status-badge.status-resolved { background: #fff; color: var(--tkt-green); }
    .tkt-header-bar .tkt-status-badge.status-cancelled { background: #fff; color: var(--tkt-red); }

    .tkt-priority-badge {
      display: inline-flex; align-items: center; gap: 5px;
      font-size: 11.5px; font-weight: 800; letter-spacing: .03em;
      text-transform: uppercase;
      padding: 5px 12px; border-radius: 999px;
      border: 1px solid rgba(255,255,255,.35);
      background: rgba(255,255,255,.14);
      color: #fff;
    }
    .tkt-priority-badge i { font-size: 13px; }

    /* ── Two-pane layout: conversation (left) + properties (right) ── */
    .tkt-layout {
      display: grid;
      grid-template-columns: minmax(0, 1fr) 340px;
      gap: 20px;
      align-items: start;
    }
    .tkt-thread { min-width: 0; }
    .tkt-side { min-width: 0; }
    @media (min-width: 1041px) {
      .tkt-side { position: sticky; top: 20px; }
    }
    @media (max-width: 1040px) {
      .tkt-layout { grid-template-columns: 1fr; }
      .tkt-side { order: -1; }
    }

    .tkt-card {
      background: var(--tkt-surface);
      border: 1px solid var(--tkt-border);
      border-radius: var(--tkt-radius);
      box-shadow: var(--tkt-shadow);
      padding: 16px 20px;
      margin-bottom: 14px;
      overflow: hidden;
    }
    .tkt-card:last-child { margin-bottom: 0; }
    .tkt-card-title {
      display: flex; align-items: center; gap: 8px;
      font-size: 13.5px; font-weight: 800; color: var(--tkt-text);
      text-transform: uppercase; letter-spacing: .03em;
      margin: 0 0 12px;
      padding-bottom: 10px;
      border-bottom: 1px solid var(--tkt-border);
    }
    .tkt-card-title i { font-size: 16px; color: var(--tkt-primary); }
    .tkt-panel-copy {
      margin: -4px 0 14px;
      color: var(--tkt-muted);
      font-size: 12px;
      line-height: 1.5;
    }
    .tkt-history-count {
      margin-left: auto;
      padding: 3px 9px;
      border: 1px solid var(--tkt-border);
      border-radius: 999px;
      background: var(--tkt-primary-soft);
      color: var(--tkt-primary-deep);
      font-size: 11px;
      font-weight: 700;
      text-transform: none;
      letter-spacing: 0;
    }

    /* ── Message card (the requester's original description) ─ */
    .tkt-message-card { padding: 0; }
    .tkt-msg-head {
      display: flex; align-items: center; gap: 12px;
      padding: 18px 22px 14px;
    }
    .tkt-avatar {
      flex-shrink: 0;
      width: 42px; height: 42px;
      border-radius: 50%;
      background: var(--tkt-primary-soft);
      color: var(--tkt-primary-deep);
      display: flex; align-items: center; justify-content: center;
      font-size: 14.5px; font-weight: 800;
      border: 2px solid #fff;
      box-shadow: 0 0 0 1px var(--tkt-border);
    }
    .tkt-msg-head-text { min-width: 0; }
    .tkt-msg-name {
      display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
      font-size: 14.5px; font-weight: 800; color: var(--tkt-text);
    }
    .tkt-msg-role-tag {
      font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .03em;
      color: var(--tkt-primary-deep);
      background: var(--tkt-primary-soft);
      padding: 2px 8px; border-radius: 999px;
    }
    .tkt-msg-time {
      display: flex; align-items: center; gap: 5px;
      font-size: 12px; color: var(--tkt-faint); font-weight: 500; margin-top: 3px;
    }
    .tkt-description {
      background: #fbfdff;
      border-top: 1px solid var(--tkt-border);
      padding: 18px 22px 22px;
      font-size: 14px;
      line-height: 1.75;
      color: var(--tkt-text);
      white-space: pre-wrap;
      word-break: break-word;
    }

    /* Info rows (ticket properties list) */
    .tkt-info-row {
      display: flex; justify-content: space-between; align-items: baseline; gap: 12px;
      padding: 10px 0;
      border-bottom: 1px solid var(--tkt-border);
      font-size: 13.5px;
    }
    .tkt-info-row:last-child { border-bottom: none; padding-bottom: 0; }
    .tkt-info-row:first-child { padding-top: 0; }
    .tkt-info-label {
      display: flex; align-items: center; gap: 6px;
      color: var(--tkt-muted); font-weight: 600; flex-shrink: 0;
    }
    .tkt-info-label i { font-size: 14px; color: var(--tkt-faint); }
    .tkt-info-value {
      color: var(--tkt-text);
      font-weight: 700;
      text-align: right;
      overflow-wrap: anywhere;
    }
    .tkt-info-value.is-muted { color: var(--tkt-faint); font-weight: 600; font-style: italic; }

    .tkt-badge {
      display: inline-flex; align-items: center; gap: 5px;
      font-size: 11.5px; font-weight: 800; letter-spacing: .01em;
      padding: 3px 10px; border-radius: 999px;
    }
    .tkt-badge.tone-blue  { background: var(--tkt-blue-soft);  color: var(--tkt-blue); }
    .tkt-badge.tone-green { background: var(--tkt-green-soft); color: var(--tkt-green); }
    .tkt-badge.tone-amber { background: var(--tkt-amber-soft); color: var(--tkt-amber); }
    .tkt-badge.tone-red   { background: var(--tkt-red-soft);   color: var(--tkt-red); }
    .tkt-badge.tone-gray  { background: var(--tkt-gray-soft);  color: var(--tkt-gray); }

    /* ── Timeline (History Logs) ───────────────────────────── */
    .tkt-timeline {
      position: relative;
      max-height: 420px;
      overflow-y: auto;
      padding: 4px 4px 4px 6px;
    }
    .tkt-timeline-item {
      position: relative;
      display: flex;
      gap: 14px;
      padding-bottom: 20px;
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

    .tkt-timeline-content {
      flex: 1;
      min-width: 0;
      padding: 10px 13px;
      border: 1px solid var(--tkt-border);
      border-radius: var(--tkt-radius-sm);
      background: var(--tkt-bg);
    }
    .tkt-timeline-action {
      font-size: 13.5px;
      font-weight: 700;
      color: var(--tkt-text);
      line-height: 1.6;
      margin-bottom: 4px;
      white-space: pre-wrap;
      overflow-wrap: anywhere;
    }
    .tkt-timeline-time { font-size: 12px; color: var(--tkt-faint); font-weight: 500; }

    .tkt-empty {
      text-align: center; padding: 30px 10px; color: var(--tkt-faint); font-size: 13px;
    }
    .tkt-empty i { font-size: 28px; display: block; margin-bottom: 8px; opacity: .5; }

    /* ── Side panel: assignment badge + workspace form ─────── */
    .tkt-assigned-badge {
      display: flex; align-items: center; gap: 10px;
      font-size: 13.5px; font-weight: 700; color: var(--tkt-primary-deep);
      background: var(--tkt-primary-soft);
      padding: 10px 14px; border-radius: var(--tkt-radius-sm);
      width: 100%;
      overflow-wrap: anywhere;
    }
    .tkt-assigned-badge i { font-size: 18px; flex-shrink: 0; }
    .tkt-assigned-badge.is-unassigned {
      background: var(--tkt-gray-soft); color: var(--tkt-gray);
    }

    .tkt-field { margin-bottom: 12px; position: relative; }
    .tkt-field:last-of-type { margin-bottom: 0; }
    .tkt-field label {
      display: block; font-size: 11.5px; font-weight: 800;
      text-transform: uppercase; letter-spacing: .04em;
      color: var(--tkt-muted); margin-bottom: 6px;
    }
    .tkt-field-hint {
      margin: 6px 0 0;
      color: var(--tkt-faint);
      font-size: 11.5px;
      line-height: 1.45;
    }
    .tkt-field-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }
    .tkt-field select,
    .tkt-field textarea {
      width: 100%;
      border: 1.5px solid var(--tkt-border);
      border-radius: var(--tkt-radius-sm);
      padding: 10px 12px;
      font-size: 13.5px;
      font-family: inherit;
      color: var(--tkt-text);
      background: #fbfdff;
      transition: border-color .15s var(--tkt-ease), box-shadow .15s var(--tkt-ease);
    }
    .tkt-field select { min-height: 42px; cursor: pointer; }
    .tkt-field select:focus,
    .tkt-field textarea:focus {
      outline: none;
      border-color: var(--tkt-primary);
      box-shadow: 0 0 0 3px var(--tkt-primary-soft);
      background: var(--tkt-surface);
    }
    .tkt-field textarea {
      display: block;
      height: 120px;
      min-height: 120px;
      max-height: 120px;
      resize: none;
      overflow-y: auto;
      line-height: 1.6;
    }

    .tkt-side .tkt-card.tkt-workspace-card {
      border-color: var(--tkt-border-strong);
      background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }
    .tkt-side .tkt-card.tkt-details-card { background: var(--tkt-surface); }

    .tkt-submit-row { padding-top: 10px; }
    .tkt-btn-submit {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 7px;
      width: 100%;
      background: linear-gradient(135deg, #3b82f6, #2563eb);
      color: #fff;
      border: none;
      font-weight: 700;
      font-size: 13.5px;
      padding: 12px 16px;
      border-radius: var(--tkt-radius-sm);
      cursor: pointer;
      box-shadow: 0 7px 14px -8px rgba(37,99,168,.8);
      transition: background .15s var(--tkt-ease), box-shadow .15s var(--tkt-ease), transform .12s var(--tkt-ease);
    }
    .tkt-btn-submit i { font-size: 17px; }
    .tkt-btn-submit:hover {
      background: linear-gradient(135deg, #4f8ef7, #1d4ed8);
      box-shadow: 0 9px 18px -8px rgba(37,99,168,.75);
    }
    .tkt-btn-submit:active { transform: scale(.98); }
    .tkt-btn-submit:focus-visible { outline: 2px solid var(--tkt-primary-deep); outline-offset: 2px; }

    /* Small screens: preserve the full ticket details without horizontal clipping. */
    @media (max-width: 767.98px) {
      .tkt-page { padding: 16px 16px 40px; }
      .tkt-header-bar { padding: 18px; margin-bottom: 16px; }
      .tkt-card { padding: 18px; margin-bottom: 16px; }
      .tkt-subject { font-size: 19px; }
      .tkt-meta-row { gap: 9px 14px; }
      .tkt-meta { align-items: flex-start; }
      .tkt-layout { gap: 16px; }
      .tkt-field-grid { grid-template-columns: 1fr; gap: 16px; }
    }

    @media (max-width: 479.98px) {
      .tkt-page { padding: 14px 12px 32px; }
      .tkt-back-link { width: 100%; justify-content: center; }
      .tkt-topbar { flex-direction: column; align-items: stretch; gap: 10px; }
      .tkt-header-bar { padding: 16px; gap: 12px; }
      .tkt-header-right { width: 100%; align-items: flex-start; }
      .tkt-card { padding: 16px 14px; border-radius: 12px; }
      .tkt-card-title { font-size: 13px; margin-bottom: 14px; }
      .tkt-subject { font-size: 18px; line-height: 1.3; }
      .tkt-msg-head { padding: 16px 16px 12px; }
      .tkt-description { padding: 14px 16px 16px; font-size: 13.5px; }
      .tkt-info-row { flex-direction: column; gap: 4px; }
      .tkt-info-value { text-align: left; }
      .tkt-timeline { padding-left: 0; }
      .tkt-timeline-item { gap: 10px; padding-bottom: 18px; }
      .tkt-timeline-item::before { left: 13px; top: 28px; }
      .tkt-timeline-dot { width: 28px; height: 28px; font-size: 14px; }
      .tkt-timeline-action { font-size: 13px; }
      .tkt-assigned-badge { align-items: flex-start; padding: 8px 10px; }
      .tkt-field select,
      .tkt-field textarea { font-size: 16px; }
      .tkt-field textarea { height: 160px; min-height: 140px; }
    }
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



    <div class="tkt-layout">

      <!-- ── Thread column: the request itself + activity timeline ── -->
      <div class="tkt-thread">

        <div class="tkt-card tkt-message-card">
          <div class="tkt-msg-head">
            <div class="tkt-avatar"><?= htmlspecialchars(tkt_initials($ticket['user_name'])); ?></div>
            <div class="tkt-msg-head-text">
              <div class="tkt-msg-name">
                <?= htmlspecialchars($ticket['user_name']); ?>
                <span class="tkt-msg-role-tag">Requester</span>
              </div>
              <div class="tkt-msg-time">
                <i class="bx bx-time-five"></i>
                <?= isset($ticket['created_at']) && !empty($ticket['created_at'])
                  ? date('F d, Y \a\t h:i A', strtotime($ticket['created_at']))
                  : 'No creation record'; ?>
              </div>
            </div>
          </div>
          <div class="tkt-description"><?= nl2br(htmlspecialchars($ticket['description'])); ?></div>
        </div>

        <div class="tkt-card">
          <h3 class="tkt-card-title">
            <i class="bx bx-history"></i> Activity Timeline
            <span class="tkt-history-count"><?= count($history_logs) ?> event<?= count($history_logs) === 1 ? '' : 's' ?></span>
          </h3>
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

      <!-- ── Side column: ticket properties + workspace form ── -->
      <div class="tkt-side">

        <div class="tkt-card tkt-details-card">
          <h3 class="tkt-card-title"><i class="bx bx-detail"></i> Ticket Details</h3>

          <div class="tkt-info-row">
            <span class="tkt-info-label"><i class="bx bx-buildings"></i> Department</span>
            <span class="tkt-info-value"><?= htmlspecialchars($ticket['user_department']); ?></span>
          </div>
          <?php if (!empty($ticket['email'])): ?>
            <div class="tkt-info-row">
              <span class="tkt-info-label"><i class="bx bx-envelope"></i> Email</span>
              <span class="tkt-info-value"><?= htmlspecialchars($ticket['email']); ?></span>
            </div>
          <?php endif; ?>
          <?php if ($has_category): ?>
            <div class="tkt-info-row">
              <span class="tkt-info-label"><i class="bx bx-category"></i> Category</span>
              <span class="tkt-info-value"><?= htmlspecialchars($ticket['category']); ?></span>
            </div>
          <?php endif; ?>
          <?php if ($has_priority): ?>
            <div class="tkt-info-row">
              <span class="tkt-info-label"><i class="bx bx-flag"></i> Priority</span>
              <span class="tkt-info-value">
                <span class="tkt-badge tone-<?= $priority_tone; ?>"><?= htmlspecialchars($ticket['priority']); ?></span>
              </span>
            </div>
          <?php endif; ?>
          <div class="tkt-info-row">
            <span class="tkt-info-label"><i class="bx bx-flag-alt"></i> Status</span>
            <span class="tkt-info-value">
              <span class="tkt-status-badge status-<?= htmlspecialchars($status_slug); ?>"><?= htmlspecialchars($ticket['status']); ?></span>
            </span>
          </div>
          <div class="tkt-info-row">
            <span class="tkt-info-label"><i class="bx bx-time-five"></i> Created</span>
            <span class="tkt-info-value">
              <?= isset($ticket['created_at']) && !empty($ticket['created_at'])
                ? date('M d, Y h:i A', strtotime($ticket['created_at']))
                : '—'; ?>
            </span>
          </div>
        </div>

        <div class="tkt-card">
          <h3 class="tkt-card-title"><i class="bx bx-user-check"></i> Assigned To</h3>
          <div class="tkt-assigned-badge <?= $ticket['mis_name'] ? '' : 'is-unassigned'; ?>">
            <i class="bx <?= $ticket['mis_name'] ? 'bx-headphone' : 'bx-user-x'; ?>"></i>
            <?= $ticket['mis_name'] ? htmlspecialchars($ticket['mis_name']) : 'Unassigned'; ?>
          </div>
        </div>

        <div class="tkt-card tkt-workspace-card">
          <h3 class="tkt-card-title"><i class="bx bx-edit-alt"></i> Ticket Workspace</h3>
          
          <form id="updateTicketForm" action="./ticket/update_ticket.php" method="POST">
            <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>" />
            <input type="hidden" name="return_to" value="viewticket_superadmin" />

            <div class="tkt-field-grid">
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
            </div>

            <div class="tkt-field">
              <label for="feedback">Feedback</label>
              <textarea name="feedback" id="feedback"
                placeholder="Write your feedback..."><?php echo htmlspecialchars($ticket['feedback']); ?></textarea>
              
            </div>

            <div class="tkt-submit-row">
              <button type="submit" class="tkt-btn-submit"><i class="bx bx-save"></i> Update Ticket</button>
            </div>
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
            confirmButtonColor: '#4e96f0'
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