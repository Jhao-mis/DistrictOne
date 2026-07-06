<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require '../vendor/autoload.php';
include '../db.php';
require 'login_verification.php';

$username = $_SESSION['username'];

// Track activity
date_default_timezone_set('Asia/Manila');
$now = date('Y-m-d H:i:s');
$updateActivity = $conn->prepare("UPDATE users SET last_activity = ? WHERE username = ?");
$updateActivity->bind_param("ss", $now, $username);
$updateActivity->execute();
$updateActivity->close();

// Get user details
$query = $conn->prepare("SELECT id, profile_picture, cover_photo, department, firstname, middlename, lastname, email 
                         FROM users WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->store_result();
$query->bind_result($user_id, $profile_picture, $cover_photo, $department, $firstname, $middlename, $lastname, $email);
$query->fetch();
$query->close();

$mis_id = $_SESSION["user_id"]; // Logged-in MIS personnel ID

$sql = "SELECT t.id, 
               CONCAT(u.firstname, ' ', u.middlename, ' ', u.lastname) AS user_name, 
               u.department AS department_name, 
               t.subject, 
               t.description, 
               t.status, 
               t.feedback,
               t.created_at
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        WHERE t.assigned_to = ? 
          AND t.admin_approved = 1
        ORDER BY t.id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $mis_id);
$stmt->execute();
$result = $stmt->get_result();

$sql_all = "SELECT t.id, 
                   CONCAT(u.firstname, ' ', u.middlename, ' ', u.lastname) AS user_name, 
                   u.department AS department_name, 
                   t.subject, 
                   t.description, 
                   t.status, 
                   t.feedback,
                   t.created_at
            FROM tickets t
            JOIN users u ON t.user_id = u.id
            WHERE t.admin_approved = 1
            ORDER BY t.id DESC";


$result_all = $conn->query($sql_all);

// Count active (Pending + In Progress) tickets for the tab badges
$activeAssignedCount = 0;
if ($result && $result->num_rows > 0) {
  mysqli_data_seek($result, 0);
  while ($rowTmp = $result->fetch_assoc()) {
    if (in_array($rowTmp['status'], ['Pending', 'In Progress'])) {
      $activeAssignedCount++;
    }
  }
  mysqli_data_seek($result, 0);
}

$activeRequestsCount = 0;
if ($result_all && $result_all->num_rows > 0) {
  mysqli_data_seek($result_all, 0);
  while ($rowTmp = $result_all->fetch_assoc()) {
    if (in_array($rowTmp['status'], ['Pending', 'In Progress'])) {
      $activeRequestsCount++;
    }
  }
  mysqli_data_seek($result_all, 0);
}

// Full status breakdown for the summary stat cards
function ticketStatusBreakdown($res)
{
  $stats = ['Total' => 0, 'Pending' => 0, 'In Progress' => 0, 'Resolved' => 0];
  if ($res && $res->num_rows > 0) {
    mysqli_data_seek($res, 0);
    while ($r = $res->fetch_assoc()) {
      $stats['Total']++;
      if (isset($stats[$r['status']])) {
        $stats[$r['status']]++;
      }
    }
    mysqli_data_seek($res, 0);
  }
  return $stats;
}

$requestsStats = ticketStatusBreakdown($result_all);
$assignedStats = ticketStatusBreakdown($result);

$conn->close();
?>

<!DOCTYPE html>

<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/"
  data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>Ticket Assignment</title>

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
  <link rel="stylesheet" href="./css/mis_assign.css">

  <!-- Vendors CSS -->
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <link rel="stylesheet" href="../assets/vendor/libs/apex-charts/apex-charts.css" />

  <!-- Helpers -->
  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

  <style>
    /* =========================================================
       Design tokens
       ========================================================= */
    :root {
      --mir-primary: #4f46e5;
      --mir-primary-soft: #eef0fd;
      --mir-primary-dark: #4338ca;
      --mir-ink: #1e2130;
      --mir-muted: #767b91;
      --mir-border: #e7e8f2;
      --mir-surface: #ffffff;
      --mir-canvas: #f6f7fb;

      --mir-pending: #f59e0b;
      --mir-pending-soft: #fef3e2;
      --mir-progress: #0ea5e9;
      --mir-progress-soft: #e6f6fd;
      --mir-resolved: #22c55e;
      --mir-resolved-soft: #e9f9ee;

      --mir-radius-sm: 8px;
      --mir-radius-md: 12px;
      --mir-radius-lg: 16px;
      --mir-shadow-soft: 0 1px 2px rgba(30, 33, 48, .04), 0 4px 12px rgba(30, 33, 48, .05);
      --mir-shadow-raised: 0 8px 24px rgba(30, 33, 48, .08);
    }

    .app-calendar-wrapper {
      border-radius: var(--mir-radius-lg);
      background: var(--mir-surface);
      border: 1px solid var(--mir-border) !important;
    }

    /* =========================================================
       Page header
       ========================================================= */
    .mir-page-head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: 1.25rem;
      flex-wrap: wrap;
    }

    .mir-page-head h4 {
      font-weight: 700;
      color: var(--mir-ink);
      margin-bottom: .2rem;
      letter-spacing: -.01em;
    }

    .mir-page-head p {
      color: var(--mir-muted);
      font-size: .875rem;
      margin: 0;
    }

    /* =========================================================
       Segmented tab bar
       ========================================================= */
    .nav-tabs {
      overflow-x: auto;
      flex-wrap: nowrap;
      white-space: nowrap;
      -ms-overflow-style: none;
      scrollbar-width: none;
      background: var(--mir-canvas);
      border-radius: 10px;
      padding: .3rem;
      border: none;
      gap: .25rem;
      margin-bottom: 1.5rem !important;
    }

    .nav-tabs::-webkit-scrollbar {
      display: none;
    }

    .nav-tabs .nav-link {
      display: flex;
      align-items: center;
      gap: .45rem;
      border: none;
      border-radius: 8px;
      color: var(--mir-muted);
      font-weight: 600;
      font-size: .9rem;
      padding: .55rem 1rem;
      transition: background .15s ease, color .15s ease;
    }

    .nav-tabs .nav-link i {
      font-size: 1.05rem;
    }

    .nav-tabs .nav-link.active {
      background: var(--mir-surface);
      color: var(--mir-primary);
      box-shadow: var(--mir-shadow-soft);
    }

    .nav-tabs .nav-link:hover:not(.active) {
      color: var(--mir-ink);
    }

    .nav-tabs .nav-link .badge {
      font-weight: 600;
      font-size: .7rem;
      padding: .28em .55em;
    }

    .nav-tabs .nav-link:not(.active) .badge.bg-primary {
      background-color: var(--mir-primary) !important;
    }

    /* =========================================================
       Summary stat cards
       ========================================================= */
    .stat-cards {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: .75rem;
      margin-bottom: 1.25rem;
    }

    .stat-card {
      display: flex;
      align-items: center;
      gap: .75rem;
      border: 1px solid var(--mir-border);
      border-radius: var(--mir-radius-md);
      padding: .8rem .9rem;
      background: var(--mir-surface);
      cursor: pointer;
      transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
      text-align: left;
    }

    .stat-card:hover {
      box-shadow: var(--mir-shadow-soft);
      transform: translateY(-2px);
    }

    .stat-card.active {
      border-color: var(--mir-primary);
      background: var(--mir-primary-soft);
      box-shadow: 0 0 0 1px var(--mir-primary) inset;
    }

    .stat-card.active .stat-value,
    .stat-card.active .stat-label {
      color: var(--mir-primary-dark);
    }

    .stat-icon {
      flex: 0 0 auto;
      width: 38px;
      height: 38px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.1rem;
    }

    .stat-icon.total { background: var(--mir-primary-soft); color: var(--mir-primary); }
    .stat-icon.pending { background: var(--mir-pending-soft); color: var(--mir-pending); }
    .stat-icon.inprogress { background: var(--mir-progress-soft); color: var(--mir-progress); }
    .stat-icon.resolved { background: var(--mir-resolved-soft); color: var(--mir-resolved); }

    .stat-card .stat-value {
      font-size: 1.35rem;
      font-weight: 700;
      line-height: 1.1;
      color: var(--mir-ink);
    }

    .stat-card .stat-label {
      font-size: .78rem;
      color: var(--mir-muted);
      margin-top: .1rem;
      font-weight: 500;
    }

    .results-count {
      font-size: .8rem;
      color: var(--mir-muted);
      margin: -.5rem 0 .75rem;
    }

    /* =========================================================
       Table controls
       ========================================================= */
    .table-controls {
      display: flex;
      gap: .6rem;
      margin-bottom: 1rem;
      flex-wrap: wrap;
    }

    .table-controls .mir-search-wrap {
      position: relative;
      flex: 1 1 240px;
    }

    .table-controls .mir-search-wrap i.bx-search {
      position: absolute;
      left: .7rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--mir-muted);
      pointer-events: none;
      font-size: 1rem;
    }

    .table-controls .mir-search-wrap input {
      padding-left: 2.15rem;
      padding-right: 2.15rem;
      width: 100%;
      border-radius: var(--mir-radius-sm);
      border-color: var(--mir-border);
    }

    .table-controls .mir-search-wrap input:focus {
      border-color: var(--mir-primary);
      box-shadow: 0 0 0 3px var(--mir-primary-soft);
    }

    .mir-search-clear {
      position: absolute;
      right: .5rem;
      top: 50%;
      transform: translateY(-50%);
      border: none;
      background: transparent;
      color: var(--mir-muted);
      font-size: 1.1rem;
      line-height: 1;
      padding: .15rem;
      display: none;
      cursor: pointer;
    }

    .mir-search-clear:hover {
      color: var(--mir-ink);
    }

    .table-controls select {
      border-radius: var(--mir-radius-sm);
      border-color: var(--mir-border);
    }

    .table-controls select:focus {
      border-color: var(--mir-primary);
      box-shadow: 0 0 0 3px var(--mir-primary-soft);
    }

    /* =========================================================
       Table
       ========================================================= */
    #tableRequests thead th,
    #tableAssigned thead th {
      font-size: .74rem;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: var(--mir-muted);
      font-weight: 700;
      background: var(--mir-canvas);
      border-bottom: 1px solid var(--mir-border);
    }

    #tableRequests tbody tr,
    #tableAssigned tbody tr {
      transition: background-color .1s ease;
    }

    #tableRequests tbody tr:hover,
    #tableAssigned tbody tr:hover {
      background-color: var(--mir-canvas);
    }

    th.sortable {
      cursor: pointer;
      user-select: none;
      white-space: nowrap;
    }

    th.sortable::after {
      content: '\2195';
      opacity: .35;
      margin-left: .3rem;
      font-size: .8em;
    }

    th.sortable.asc::after {
      content: '\2191';
      opacity: 1;
      color: var(--mir-primary);
    }

    th.sortable.desc::after {
      content: '\2193';
      opacity: 1;
      color: var(--mir-primary);
    }

    .subject-col {
      max-width: 240px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    #tableRequests td i.bx,
    #tableAssigned td i.bx {
      color: var(--mir-muted);
      margin-right: .35rem;
      font-size: .95rem;
    }

    /* Status badges — quieter, tinted rather than solid */
    .badge.bg-label-warning {
      background: var(--mir-pending-soft) !important;
      color: #b45309 !important;
    }

    .badge.bg-label-info {
      background: var(--mir-progress-soft) !important;
      color: #0369a1 !important;
    }

    .badge.bg-label-success {
      background: var(--mir-resolved-soft) !important;
      color: #15803d !important;
    }

    .table-scroll {
      max-height: 60vh;
      overflow-y: auto;
    }

    .table-scroll thead th {
      position: sticky;
      top: 0;
      z-index: 1;
    }

    .action-cell .btn {
      border-radius: 7px;
      font-size: .8rem;
      font-weight: 600;
      padding: .35rem .8rem;
      background: var(--mir-primary);
      border-color: var(--mir-primary);
    }

    .action-cell .btn:hover {
      background: var(--mir-primary-dark);
      border-color: var(--mir-primary-dark);
    }

    /* Status accent on rows (desktop) */
    tr.row-pending td:first-child { box-shadow: inset 3px 0 0 var(--mir-pending); }
    tr.row-inprogress td:first-child { box-shadow: inset 3px 0 0 var(--mir-progress); }
    tr.row-resolved td:first-child { box-shadow: inset 3px 0 0 var(--mir-resolved); }

    /* =========================================================
       Empty state
       ========================================================= */
    .mir-empty {
      text-align: center;
      padding: 3rem 1rem;
      color: var(--mir-muted);
    }

    .mir-empty i {
      font-size: 2.25rem;
      display: block;
      margin-bottom: .6rem;
      color: var(--mir-border);
    }

    .mir-empty strong {
      display: block;
      color: var(--mir-ink);
      font-size: .95rem;
      margin-bottom: .2rem;
    }

    .mir-empty span {
      font-size: .82rem;
    }

    /* =======================
       MOBILE RESPONSIVE FIXES
       ======================= */
    @media (max-width: 767.98px) {
      .container-xxl {
        padding-left: .75rem;
        padding-right: .75rem;
      }

      .app-calendar-wrapper {
        padding: 1rem !important;
      }

      .mir-page-head {
        margin-bottom: 1rem;
      }

      .table-controls {
        flex-direction: column;
      }

      .table-controls select {
        width: 100% !important;
      }

      /* Convert tables into stacked cards on phones */
      .table-scroll {
        max-height: none;
      }

      table.table-smaller,
      table.table-smaller thead,
      table.table-smaller tbody,
      table.table-smaller tr,
      table.table-smaller td {
        display: block;
        width: 100%;
      }

      table.table-smaller thead {
        display: none;
      }

      table.table-smaller tbody tr {
        border: 1px solid var(--mir-border);
        border-radius: var(--mir-radius-md);
        margin-bottom: .75rem;
        padding: .75rem;
        background: var(--mir-surface);
      }

      table.table-smaller td {
        border: none !important;
        padding: .3rem 0 !important;
        white-space: normal !important;
        max-width: 100% !important;
      }

      table.table-smaller td::before {
        content: attr(data-label);
        display: block;
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: var(--mir-muted);
        margin-bottom: .1rem;
      }

      table.table-smaller td.action-cell {
        padding-top: .5rem !important;
        border-top: 1px solid var(--mir-border) !important;
        margin-top: .4rem;
      }

      table.table-smaller td.action-cell::before {
        content: '';
        margin: 0;
      }

      table.table-smaller td.action-cell .btn {
        width: 100%;
      }

      /* Stat cards: 2x2 grid on phones */
      .stat-cards {
        grid-template-columns: repeat(2, 1fr);
      }

      .stat-card {
        padding: .65rem .75rem;
      }

      .stat-icon {
        width: 32px;
        height: 32px;
        font-size: .95rem;
      }

      .stat-card .stat-value {
        font-size: 1.15rem;
      }

      /* Compact app-style ticket card layout */
      table.table-smaller tbody tr {
        display: grid;
        grid-template-columns: 1fr 1fr;
        grid-template-areas:
          "ticket status"
          "subject subject"
          "name date"
          "action action";
        gap: .3rem .5rem;
        align-items: center;
        position: relative;
        padding-left: 1rem;
      }

      table.table-smaller tbody tr.row-pending { border-left: 4px solid var(--mir-pending); }
      table.table-smaller tbody tr.row-inprogress { border-left: 4px solid var(--mir-progress); }
      table.table-smaller tbody tr.row-resolved { border-left: 4px solid var(--mir-resolved); }

      table.table-smaller td::before {
        content: none;
      }

      table.table-smaller td:nth-child(1) {
        grid-area: ticket;
        font-size: .78rem;
        font-weight: 700;
        color: var(--mir-muted);
      }

      table.table-smaller td:nth-child(2) {
        grid-area: name;
        font-size: .82rem;
        color: var(--mir-muted);
      }

      table.table-smaller td:nth-child(3) {
        grid-area: subject;
        font-weight: 600;
        font-size: .95rem;
        color: var(--mir-ink);
      }

      table.table-smaller td:nth-child(4) {
        grid-area: status;
        justify-self: end;
      }

      table.table-smaller td:nth-child(5) {
        grid-area: date;
        font-size: .78rem;
        color: var(--mir-muted);
        justify-self: end;
      }

      table.table-smaller td.action-cell {
        grid-area: action;
      }

      table.table-smaller td i.bx {
        font-size: .9rem;
        margin-right: .2rem;
      }
    }

    /* Respect reduced motion preferences */
    @media (prefers-reduced-motion: reduce) {
      .stat-card,
      .nav-tabs .nav-link,
      #tableRequests tbody tr,
      #tableAssigned tbody tr {
        transition: none !important;
      }

      .stat-card:hover {
        transform: none;
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
    <div class="container-xxl flex-grow-1 container-p-y">
      <div class="card app-calendar-wrapper p-3 shadow-sm border-0">

        <div class="mir-page-head">
          <div>
            <h4>Ticket Assignment</h4>
            <p>Review incoming requests and track the tickets assigned to you.</p>
          </div>
        </div>

        <!-- 🔹 Tabs Header -->
        <ul class="nav nav-tabs mb-3" id="ticketTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="requests-tab" data-bs-toggle="tab" data-bs-target="#requests"
              type="button" role="tab" aria-selected="true">
              <i class='bx bx-list-ul'></i> Ticket Requests
              <span class="badge <?= $activeRequestsCount > 0 ? 'bg-primary' : 'bg-secondary' ?> ms-1">
                <?= $activeRequestsCount ?>
              </span>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="assigned-tab" data-bs-toggle="tab" data-bs-target="#assigned" type="button"
              role="tab" aria-selected="false">
              <i class='bx bx-user-check'></i> Assigned Tickets
              <span class="badge <?= $activeAssignedCount > 0 ? 'bg-primary' : 'bg-secondary' ?> ms-1">
                <?= $activeAssignedCount ?>
              </span>
            </button>
          </li>
        </ul>

        <!-- Tabs Content -->
        <div class="tab-content" id="ticketTabsContent">

          <!--  Ticket Requests Tab -->
          <div class="tab-pane fade show active" id="requests" role="tabpanel" aria-labelledby="requests-tab">

            <div class="stat-cards" data-stat-group="Requests">
              <button type="button" class="stat-card active" data-filter-target="filterRequests" data-value="all">
                <span class="stat-icon total"><i class='bx bx-layer'></i></span>
                <span>
                  <div class="stat-value"><?= $requestsStats['Total'] ?></div>
                  <div class="stat-label">Total</div>
                </span>
              </button>
              <button type="button" class="stat-card" data-filter-target="filterRequests" data-value="Pending">
                <span class="stat-icon pending"><i class='bx bx-time-five'></i></span>
                <span>
                  <div class="stat-value"><?= $requestsStats['Pending'] ?></div>
                  <div class="stat-label">Pending</div>
                </span>
              </button>
              <button type="button" class="stat-card" data-filter-target="filterRequests" data-value="In Progress">
                <span class="stat-icon inprogress"><i class='bx bx-loader-circle'></i></span>
                <span>
                  <div class="stat-value"><?= $requestsStats['In Progress'] ?></div>
                  <div class="stat-label">In Progress</div>
                </span>
              </button>
              <button type="button" class="stat-card" data-filter-target="filterRequests" data-value="Resolved">
                <span class="stat-icon resolved"><i class='bx bx-check-circle'></i></span>
                <span>
                  <div class="stat-value"><?= $requestsStats['Resolved'] ?></div>
                  <div class="stat-label">Resolved</div>
                </span>
              </button>
            </div>

            <div class="table-controls">
              <div class="mir-search-wrap">
                <i class='bx bx-search'></i>
                <input type="text" id="searchRequests" class="form-control form-control-sm"
                  placeholder="Search ticket #, name, subject..." aria-label="Search ticket requests">
                <button type="button" class="mir-search-clear" id="clearRequests" aria-label="Clear search">&times;</button>
              </div>
              <select id="filterRequests" class="form-select form-select-sm w-auto" aria-label="Filter ticket requests by status">
                <option value="all">All Statuses</option>
                <option value="Pending">Pending</option>
                <option value="In Progress">In Progress</option>
                <option value="Resolved">Resolved</option>
              </select>
            </div>

            <div class="results-count" id="countRequests"></div>

            <?php if ($result_all->num_rows > 0): ?>
              <div class="table-responsive text-nowrap <?= ($result_all->num_rows > 5) ? 'table-scroll' : ''; ?>">
                <table class="table table-smaller" id="tableRequests">
                  <thead>
                    <tr>
                      <th class="sortable">Ticket #</th>
                      <th class="sortable">Name</th>
                      <th class="sortable">Subject</th>
                      <th class="sortable">Status</th>
                      <th class="sortable">Created At</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php while ($row_all = $result_all->fetch_assoc()): ?>
                      <?php
                      $status = htmlspecialchars($row_all['status']);
                      $badge = match ($status) {
                        'Pending' => 'bg-label-warning',
                        'In Progress' => 'bg-label-info',
                        'Resolved' => 'bg-label-success',
                        default => 'bg-label-secondary'
                      };
                      $rowClass = match ($status) {
                        'Pending' => 'row-pending',
                        'In Progress' => 'row-inprogress',
                        'Resolved' => 'row-resolved',
                        default => ''
                      };
                      ?>
                      <tr class="<?= $rowClass ?>">
                        <td data-label="Ticket #">#<?= $row_all['id']; ?></td>
                        <td data-label="Name"><i class='bx bx-user'></i><?= htmlspecialchars($row_all['user_name']); ?></td>
                        <td data-label="Subject" class="subject-col" title="<?= htmlspecialchars($row_all['subject']); ?>">
                          <?= htmlspecialchars($row_all['subject']); ?>
                        </td>
                        <td data-label="Status">
                          <span class="badge <?= $badge ?> me-1"><?= $status ?></span>
                        </td>
                        <td data-label="Created At"><i class='bx bx-calendar'></i><?= $row_all['created_at'] ? date("M d, Y h:i A", strtotime($row_all['created_at'])) : 'N/A' ?>
                        </td>
                        <td data-label="Action" class="action-cell">
                          <a href="viewTicket.php?ticket_id=<?= $row_all['id'] ?>" class="btn btn-sm btn-primary">
                            <i class='bx bx-show'></i> View
                          </a>
                        </td>
                      </tr>
                    <?php endwhile; ?>
                  </tbody>
                </table>
              </div>
              <div id="noResultsRequests" class="mir-empty d-none">
                <i class='bx bx-search-alt'></i>
                <strong>No matching tickets</strong>
                <span>Try a different search term or clear the status filter.</span>
              </div>
            <?php else: ?>
              <div class="mir-empty">
                <i class='bx bx-inbox'></i>
                <strong>No ticket requests yet</strong>
                <span>Approved requests will show up here as they come in.</span>
              </div>
            <?php endif; ?>
          </div>

          <!-- 🟩 Assigned Tickets Tab -->
          <div class="tab-pane fade" id="assigned" role="tabpanel" aria-labelledby="assigned-tab">

            <div class="stat-cards" data-stat-group="Assigned">
              <button type="button" class="stat-card active" data-filter-target="filterAssigned" data-value="all">
                <span class="stat-icon total"><i class='bx bx-layer'></i></span>
                <span>
                  <div class="stat-value"><?= $assignedStats['Total'] ?></div>
                  <div class="stat-label">Total</div>
                </span>
              </button>
              <button type="button" class="stat-card" data-filter-target="filterAssigned" data-value="Pending">
                <span class="stat-icon pending"><i class='bx bx-time-five'></i></span>
                <span>
                  <div class="stat-value"><?= $assignedStats['Pending'] ?></div>
                  <div class="stat-label">Pending</div>
                </span>
              </button>
              <button type="button" class="stat-card" data-filter-target="filterAssigned" data-value="In Progress">
                <span class="stat-icon inprogress"><i class='bx bx-loader-circle'></i></span>
                <span>
                  <div class="stat-value"><?= $assignedStats['In Progress'] ?></div>
                  <div class="stat-label">In Progress</div>
                </span>
              </button>
              <button type="button" class="stat-card" data-filter-target="filterAssigned" data-value="Resolved">
                <span class="stat-icon resolved"><i class='bx bx-check-circle'></i></span>
                <span>
                  <div class="stat-value"><?= $assignedStats['Resolved'] ?></div>
                  <div class="stat-label">Resolved</div>
                </span>
              </button>
            </div>

            <div class="table-controls">
              <div class="mir-search-wrap">
                <i class='bx bx-search'></i>
                <input type="text" id="searchAssigned" class="form-control form-control-sm"
                  placeholder="Search ticket #, name, subject..." aria-label="Search assigned tickets">
                <button type="button" class="mir-search-clear" id="clearAssigned" aria-label="Clear search">&times;</button>
              </div>
              <select id="filterAssigned" class="form-select form-select-sm w-auto" aria-label="Filter assigned tickets by status">
                <option value="all">All Statuses</option>
                <option value="Pending">Pending</option>
                <option value="In Progress">In Progress</option>
                <option value="Resolved">Resolved</option>
              </select>
            </div>

            <div class="results-count" id="countAssigned"></div>

            <?php if ($result->num_rows > 0): ?>
              <div class="table-responsive text-nowrap <?= ($result->num_rows > 5) ? 'table-scroll' : ''; ?>">
                <table class="table table-smaller" id="tableAssigned">
                  <thead>
                    <tr>
                      <th class="sortable">Ticket #</th>
                      <th class="sortable">Name</th>
                      <th class="sortable">Subject</th>
                      <th class="sortable">Status</th>
                      <th class="sortable">Created At</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                      <?php
                      $status = htmlspecialchars($row['status']);
                      $badge = match ($status) {
                        'Pending' => 'bg-label-warning',
                        'In Progress' => 'bg-label-info',
                        'Resolved' => 'bg-label-success',
                        default => 'bg-label-secondary'
                      };
                      $rowClass = match ($status) {
                        'Pending' => 'row-pending',
                        'In Progress' => 'row-inprogress',
                        'Resolved' => 'row-resolved',
                        default => ''
                      };
                      ?>
                      <tr class="<?= $rowClass ?>">
                        <td data-label="Ticket #">#<?= $row['id']; ?></td>
                        <td data-label="Name"><i class='bx bx-user'></i><?= htmlspecialchars($row['user_name']); ?></td>
                        <td data-label="Subject" class="subject-col" title="<?= htmlspecialchars($row['subject']); ?>">
                          <?= htmlspecialchars($row['subject']); ?>
                        </td>
                        <td data-label="Status">
                          <span class="badge <?= $badge ?> me-1"><?= $status ?></span>
                        </td>
                        <td data-label="Created At"><i class='bx bx-calendar'></i><?= $row['created_at'] ? date("M d, Y h:i A", strtotime($row['created_at'])) : 'N/A' ?></td>
                        <td data-label="Action" class="action-cell">
                          <a href="viewTicket.php?ticket_id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">
                            <i class='bx bx-show'></i> View
                          </a>
                        </td>
                      </tr>
                    <?php endwhile; ?>
                  </tbody>
                </table>
              </div>
              <div id="noResultsAssigned" class="mir-empty d-none">
                <i class='bx bx-search-alt'></i>
                <strong>No matching tickets</strong>
                <span>Try a different search term or clear the status filter.</span>
              </div>
            <?php else: ?>
              <div class="mir-empty">
                <i class='bx bx-inbox'></i>
                <strong>No tickets assigned to you</strong>
                <span>Once a ticket is assigned, it will appear here.</span>
              </div>
            <?php endif; ?>
          </div>
        </div> <!-- end tab content -->
      </div>
    </div>
  </div>

  <div class="content-backdrop fade"></div>
  <div class="layout-overlay layout-menu-toggle"></div>

  <script src="../assets/vendor/js/bootstrap.js"></script>
  <script src="../assets/vendor/js/menu.js"></script>
  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/dashboards-analytics.js"></script>

  <!-- ✅ JS for Filter + Search + Sorting + Stat Cards -->
  <script>
    function initTableControls(tableId, searchId, filterId, emptyStateId, countId, clearId) {
      const table = document.getElementById(tableId);
      if (!table) return;

      const rows = Array.from(table.querySelectorAll('tbody tr'));
      const search = document.getElementById(searchId);
      const filter = document.getElementById(filterId);
      const emptyState = document.getElementById(emptyStateId);
      const countEl = document.getElementById(countId);
      const clearBtn = document.getElementById(clearId);
      const headers = table.querySelectorAll('th.sortable');
      const total = rows.length;
      let sortDir = {};

      // Search + Filter logic
      function updateDisplay() {
        const query = search.value.toLowerCase();
        const status = filter.value.toLowerCase();
        let visibleCount = 0;

        if (clearBtn) {
          clearBtn.style.display = search.value ? 'block' : 'none';
        }

        rows.forEach(row => {
          const text = row.innerText.toLowerCase();
          const stat = row.cells[3].innerText.toLowerCase();
          const matchSearch = text.includes(query);
          const matchStatus = status === 'all' || stat.includes(status);
          const visible = matchSearch && matchStatus;
          row.style.display = visible ? '' : 'none';
          if (visible) visibleCount++;
        });

        if (emptyState) {
          emptyState.classList.toggle('d-none', visibleCount !== 0);
        }

        if (countEl) {
          countEl.textContent = (query || status !== 'all')
            ? `Showing ${visibleCount} of ${total} tickets`
            : `${total} ticket${total === 1 ? '' : 's'} total`;
        }
      }

      search.addEventListener('input', updateDisplay);
      filter.addEventListener('change', updateDisplay);

      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          search.value = '';
          search.focus();
          updateDisplay();
        });
      }

      updateDisplay();

      // Sorting logic (ticket # has a leading "#", strip it for numeric compare)
      headers.forEach((th, i) => {
        th.addEventListener('click', () => {
          const asc = !sortDir[i];
          sortDir[i] = asc;
          const sorted = [...rows].sort((a, b) => {
            const aText = a.cells[i].innerText.trim().toLowerCase().replace(/^#/, '');
            const bText = b.cells[i].innerText.trim().toLowerCase().replace(/^#/, '');
            if (!isNaN(aText) && !isNaN(bText) && aText !== '' && bText !== '') {
              return asc ? aText - bText : bText - aText;
            }
            return asc ? aText.localeCompare(bText) : bText.localeCompare(aText);
          });

          table.querySelector('tbody').innerHTML = '';
          sorted.forEach(r => table.querySelector('tbody').appendChild(r));
          headers.forEach(h => h.classList.remove('asc', 'desc'));
          th.classList.add(asc ? 'asc' : 'desc');
        });
      });
    }

    // Initialize for both tables
    initTableControls('tableRequests', 'searchRequests', 'filterRequests', 'noResultsRequests', 'countRequests', 'clearRequests');
    initTableControls('tableAssigned', 'searchAssigned', 'filterAssigned', 'noResultsAssigned', 'countAssigned', 'clearAssigned');

    // Stat cards act as quick filters
    document.querySelectorAll('.stat-card').forEach(card => {
      card.addEventListener('click', () => {
        const targetId = card.dataset.filterTarget;
        const value = card.dataset.value;
        const select = document.getElementById(targetId);
        if (!select) return;

        select.value = value;
        select.dispatchEvent(new Event('change'));

        card.parentElement.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active'));
        card.classList.add('active');
      });
    });

    // Keep stat card "active" state in sync if the dropdown is changed manually
    ['filterRequests', 'filterAssigned'].forEach(id => {
      const select = document.getElementById(id);
      if (!select) return;
      select.addEventListener('change', () => {
        const group = document.querySelector(`.stat-card[data-filter-target="${id}"]`)?.parentElement;
        if (!group) return;
        group.querySelectorAll('.stat-card').forEach(c => {
          c.classList.toggle('active', c.dataset.value === select.value);
        });
      });
    });
  </script>

  <script>
    document.addEventListener("DOMContentLoaded", function () {
      document.querySelectorAll(".btn-create").forEach(button => {
        button.addEventListener("click", function (event) {
          event.preventDefault(); // Prevent default link behavior

          const modalEl = document.getElementById("editModal");
          if (modalEl) {
            new bootstrap.Modal(modalEl).show();
          }
        });
      });
    });
  </script>
</body>

</html>