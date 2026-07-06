<?php
require '../vendor/autoload.php';
include '../db.php';
require 'login_verification.php';

$username = $_SESSION['username'];

date_default_timezone_set('Asia/Manila');
$now = date('Y-m-d H:i:s');
$updateActivity = $conn->prepare("UPDATE users SET last_activity = ? WHERE username = ?");
$updateActivity->bind_param("ss", $now, $username);
$updateActivity->execute();
$updateActivity->close();

// 🔍 Fetch user details
$query = $conn->prepare("SELECT id, profile_picture, cover_photo, department, firstname, middlename, lastname, email 
                         FROM users WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->store_result();
$query->bind_result($user_id, $profile_picture, $cover_photo, $department, $firstname, $middlename, $lastname, $email);
$query->fetch();
$query->close();

// 📅 Fetch activities
$stmt = $pdo->query("SELECT * FROM activities ORDER BY start_datetime");
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 🎫 Fetch tickets by status (Admin Approved)

// ✅ Pending Tickets
$sql_new = "
SELECT 
    t.id, 
    t.description, 
    CONCAT(u.firstname, ' ', u.middlename, ' ', u.lastname) AS user_name, 
    t.subject, 
    t.status,
    t.created_at,
    (
        SELECT th.timestamp 
        FROM ticket_history th 
        WHERE th.ticket_id = t.id 
        ORDER BY th.timestamp DESC 
        LIMIT 1
    ) AS last_update,
    TRIM(REPLACE(
        (SELECT SUBSTRING_INDEX(th2.action, ' by ', 1) 
         FROM ticket_history th2 
         WHERE th2.ticket_id = t.id 
           AND th2.action LIKE 'Ticket assigned to%'
         ORDER BY th2.timestamp DESC 
         LIMIT 1), 'Ticket assigned to', '')
    ) AS assigned_to
FROM tickets t
JOIN users u ON t.user_id = u.id
WHERE t.status = 'Pending'
  AND t.admin_approved = 1
ORDER BY t.created_at DESC";
$result_new = $conn->query($sql_new);

// ✅ In Progress Tickets
$sql_active = "
SELECT 
    t.id, 
    t.description, 
    CONCAT(u.firstname, ' ', u.middlename, ' ', u.lastname) AS user_name, 
    t.subject, 
    t.status,
    t.created_at,
    (
        SELECT th.timestamp 
        FROM ticket_history th 
        WHERE th.ticket_id = t.id 
        ORDER BY th.timestamp DESC 
        LIMIT 1
    ) AS last_update,
    TRIM(REPLACE(
        (SELECT SUBSTRING_INDEX(th2.action, ' by ', 1) 
         FROM ticket_history th2 
         WHERE th2.ticket_id = t.id 
           AND th2.action LIKE 'Ticket assigned to%'
         ORDER BY th2.timestamp DESC 
         LIMIT 1), 'Ticket assigned to', '')
    ) AS assigned_to
FROM tickets t
JOIN users u ON t.user_id = u.id
WHERE t.status = 'In Progress'
  AND t.admin_approved = 1
ORDER BY t.created_at DESC";
$result_active = $conn->query($sql_active);

// ✅ Resolved Tickets
$sql_resolved = "
SELECT 
    t.id, 
    t.description, 
    CONCAT(u.firstname, ' ', u.middlename, ' ', u.lastname) AS user_name, 
    t.subject, 
    t.status,
    t.created_at,
    (
        SELECT th.timestamp 
        FROM ticket_history th 
        WHERE th.ticket_id = t.id 
        ORDER BY th.timestamp DESC 
        LIMIT 1
    ) AS last_update,
    TRIM(REPLACE(
        (SELECT SUBSTRING_INDEX(th2.action, ' by ', 1) 
         FROM ticket_history th2 
         WHERE th2.ticket_id = t.id 
           AND th2.action LIKE 'Ticket assigned to%'
         ORDER BY th2.timestamp DESC 
         LIMIT 1), 'Ticket assigned to', '')
    ) AS assigned_to
FROM tickets t
JOIN users u ON t.user_id = u.id
WHERE t.status = 'Resolved'
  AND t.admin_approved = 1
ORDER BY t.created_at DESC";
$result_resolved = $conn->query($sql_resolved);


$conn->close();
?>

<!DOCTYPE html>

<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/"
  data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>Ticket Request</title>

  <meta name="description" content="" />

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
  <link rel="stylesheet" href="../css/admin.css" />
  <link rel="stylesheet" href="./css/ticketRequest.css">

  <!-- Vendors CSS (kept: used by the sidebar/menu scroll) -->
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

  <!-- Helpers -->
  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>

  <style>
    :root {
      --tk-bg: #f7f8fa;
      --tk-surface: #ffffff;
      --tk-border: #e8eaee;
      --tk-text: #1f2430;
      --tk-text-muted: #767e8c;
      --tk-text-faint: #a2a8b3;
      --tk-primary: #7cb9ff;
      --tk-primary-soft: #eaf3ff;
      --tk-primary-dark: #4e96f0;
      --tk-primary-deep: #2563a8;
      --tk-room: #f87171;
      --tk-room-soft: #fef2f2;
      --tk-room-deep: #c0392b;
      --tk-success: #34c759;
      --tk-success-soft: #eafaf0;
      --tk-success-deep: #1e8a44;
      --tk-warning: #f5b942;
      --tk-warning-soft: #fef7e8;
      --tk-warning-deep: #9a6b0a;
      --tk-info: #5b8def;
      --tk-info-soft: #eaf1ff;
      --tk-info-deep: #2a5cc4;
      --tk-radius: 14px;
      --tk-radius-sm: 9px;
      --tk-shadow: 0 1px 2px rgba(20, 20, 43, .04), 0 8px 24px -12px rgba(20, 20, 43, .10);
      --tk-shadow-lg: 0 20px 50px -18px rgba(20, 20, 43, .22);
      --tk-ease: cubic-bezier(.4, 0, .2, 1);
    }

    * { box-sizing: border-box; }

    .tk-page { animation: tk-fade-in .35s var(--tk-ease); }
    @keyframes tk-fade-in { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
    @media (prefers-reduced-motion: reduce) { .tk-page, .tk-card, .tk-status { animation: none !important; transition: none !important; } }

    /* ── Header ─────────────────────────────────────────────── */
    .tk-header { display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 20px; }
    .tk-header h2 { font-size: 21px; font-weight: 800; letter-spacing: -.2px; color: var(--tk-text); margin: 0 0 4px; }
    .tk-header p { font-size: 13.5px; color: var(--tk-text-muted); margin: 0; }
    .tk-header-stats { display: flex; gap: 10px; flex-wrap: wrap; }
    .tk-stat-chip { display: flex; flex-direction: column; align-items: flex-start; background: var(--tk-surface); border: 1px solid var(--tk-border); border-radius: var(--tk-radius-sm); padding: 8px 14px; min-width: 92px; }
    .tk-stat-chip .num { font-size: 17px; font-weight: 800; color: var(--tk-text); line-height: 1.2; }
    .tk-stat-chip .lbl { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--tk-text-muted); margin-top: 1px; }
    .tk-stat-chip.is-warn .num { color: var(--tk-warning-deep); }
    .tk-stat-chip.is-info .num { color: var(--tk-info-deep); }
    .tk-stat-chip.is-success .num { color: var(--tk-success-deep); }

    /* ── Card / table ───────────────────────────────────────── */
    .tk-card { background: var(--tk-surface); border: 1px solid var(--tk-border); border-radius: var(--tk-radius); box-shadow: var(--tk-shadow); overflow: hidden; }
    .tk-card-body { padding: 18px 20px 6px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
    .tk-card-body h5 { margin: 0; font-size: 15px; font-weight: 800; color: var(--tk-text); }

    .tk-toolbar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

    .tk-search { position: relative; }
    .tk-search svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 14px; height: 14px; color: var(--tk-text-faint); pointer-events: none; }
    .tk-search input { width: 210px; border: 1.5px solid var(--tk-border); border-radius: var(--tk-radius-sm); padding: 8px 12px 8px 34px; font-size: 13px; color: var(--tk-text); background: var(--tk-surface); transition: border-color .15s var(--tk-ease), box-shadow .15s var(--tk-ease); }
    .tk-search input:focus { outline: none; border-color: var(--tk-primary); box-shadow: 0 0 0 3px var(--tk-primary-soft); }

    .tk-select { border: 1.5px solid var(--tk-border); border-radius: var(--tk-radius-sm); padding: 8px 30px 8px 12px; font-size: 13px; font-weight: 600; color: var(--tk-text); background: var(--tk-surface); cursor: pointer; }
    .tk-select:focus { outline: none; border-color: var(--tk-primary); box-shadow: 0 0 0 3px var(--tk-primary-soft); }

    .tk-table-wrap { max-height: 560px; overflow-y: auto; }
    .tk-table { width: 100%; border-collapse: collapse; }
    .tk-table thead th { position: sticky; top: 0; z-index: 1; background: var(--tk-bg); font-size: 10.5px; text-transform: uppercase; letter-spacing: .5px; font-weight: 800; color: var(--tk-text-muted); padding: 11px 16px; text-align: left; border-bottom: 1px solid var(--tk-border); white-space: nowrap; user-select: none; }
    .tk-table thead th.sortable:hover { color: var(--tk-primary-deep); cursor: pointer; }
    .tk-table thead th.asc::after { content: " ▲"; font-size: 9px; }
    .tk-table thead th.desc::after { content: " ▼"; font-size: 9px; }
    .tk-table tbody td { padding: 11px 16px; font-size: 13px; color: var(--tk-text); border-bottom: 1px solid var(--tk-border); vertical-align: middle; }
    .tk-table tbody tr { cursor: pointer; transition: background .12s var(--tk-ease); }
    .tk-table tbody tr:hover { background: var(--tk-bg); }
    .tk-table tbody tr:last-child td { border-bottom: none; }
    .tk-table .subject-col { max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .tk-status { display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 800; letter-spacing: .2px; padding: 4px 10px; border-radius: 999px; white-space: nowrap; }
    .tk-status.pending { background: var(--tk-warning-soft); color: var(--tk-warning-deep); }
    .tk-status.in-progress { background: var(--tk-info-soft); color: var(--tk-info-deep); }
    .tk-status.resolved { background: var(--tk-success-soft); color: var(--tk-success-deep); }
    .tk-status.rejected { background: var(--tk-room-soft); color: var(--tk-room-deep); }
    .tk-status.other { background: var(--tk-bg); color: var(--tk-text-faint); border: 1px solid var(--tk-border); }

    .tk-btn-pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11.5px; font-weight: 800; padding: 6px 12px; border-radius: 999px; border: 1.5px solid transparent; transition: all .15s var(--tk-ease); white-space: nowrap; text-decoration: none; cursor: pointer; }
    .tk-btn-pill.is-primary { background: var(--tk-primary-soft); color: var(--tk-primary-deep); }
    .tk-btn-pill.is-primary:hover { background: var(--tk-primary-dark); color: #fff; }

    .tk-empty { text-align: center; padding: 50px 16px; color: var(--tk-text-faint); font-size: 13px; }
    .tk-empty svg { width: 34px; height: 34px; opacity: .4; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto; color: var(--tk-text-muted); }
    .tk-empty strong { display: block; color: var(--tk-text-muted); font-weight: 700; font-size: 13.5px; margin-bottom: 3px; }

    /* ── Pagination ─────────────────────────────────────────── */
    .tk-pagination {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 10px;
      padding: 12px 20px;
      border-top: 1px solid var(--tk-border);
      background: var(--tk-surface);
    }
    .tk-pagination-info { font-size: 12.5px; color: var(--tk-text-muted); }
    .tk-pagination-right { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
    .tk-pagesize-group { display: flex; align-items: center; gap: 7px; font-size: 12.5px; color: var(--tk-text-muted); }
    .tk-pagesize-group select.tk-select { padding: 6px 26px 6px 10px; font-size: 12.5px; }
    .tk-pagination-controls { display: flex; align-items: center; gap: 6px; }
    .tk-page-btn {
      display: inline-flex; align-items: center; justify-content: center;
      width: 30px; height: 30px;
      border: 1.5px solid var(--tk-border);
      background: var(--tk-surface);
      border-radius: 8px;
      color: var(--tk-text);
      cursor: pointer;
      font-size: 15px;
      line-height: 1;
      transition: border-color .15s var(--tk-ease), background .15s var(--tk-ease), color .15s var(--tk-ease);
    }
    .tk-page-btn:hover:not(:disabled) { border-color: var(--tk-primary); color: var(--tk-primary-deep); background: var(--tk-primary-soft); }
    .tk-page-btn:disabled { opacity: .4; cursor: not-allowed; }
    .tk-page-indicator { font-size: 12.5px; font-weight: 700; color: var(--tk-text); white-space: nowrap; min-width: 90px; text-align: center; }

    /* ── Modal ──────────────────────────────────────────────── */
    .tk-modal .modal-content { border: none; border-radius: 18px; overflow: hidden; box-shadow: var(--tk-shadow-lg); }
    .tk-modal .modal-header { padding: 20px 24px; border-bottom: none; background: linear-gradient(135deg, var(--tk-primary) 0%, var(--tk-primary-deep) 100%); }
    .tk-modal .modal-title { color: #fff; font-size: 16px; font-weight: 800; letter-spacing: -.2px; }
    .tk-modal .btn-close { filter: brightness(0) invert(1); opacity: .85; }
    .tk-modal .btn-close:hover { opacity: 1; }
    .tk-modal .modal-body { padding: 22px 24px; font-size: 13.5px; color: var(--tk-text); }
    .tk-modal .modal-footer { border-top: 1px solid var(--tk-border); background: var(--tk-bg); padding: 14px 24px; }
    .tk-modal-close { background: var(--tk-surface); border: 1.5px solid var(--tk-border); color: var(--tk-text); font-weight: 700; font-size: 13.5px; padding: 9px 18px; border-radius: 10px; cursor: pointer; transition: border-color .15s var(--tk-ease), color .15s var(--tk-ease); }
    .tk-modal-close:hover { border-color: var(--tk-primary); color: var(--tk-primary-deep); }

    .tk-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 20px; margin-bottom: 16px; }
    .tk-detail-grid .full { grid-column: 1 / -1; }
    .tk-detail-label { font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; color: var(--tk-text-muted); margin-bottom: 3px; }
    .tk-detail-value { font-size: 14px; font-weight: 600; color: var(--tk-text); }
    .tk-detail-desc { white-space: pre-wrap; background: var(--tk-bg); border: 1px solid var(--tk-border); border-radius: var(--tk-radius-sm); padding: 12px 14px; font-size: 13px; color: var(--tk-text); min-height: 70px; }
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

  // Combine all ticket results into one dataset
  $all_tickets = [];
  while ($row = $result_new->fetch_assoc())
    $all_tickets[] = $row;
  while ($row = $result_active->fetch_assoc())
    $all_tickets[] = $row;
  while ($row = $result_resolved->fetch_assoc())
    $all_tickets[] = $row;

  $totalCount = count($all_tickets);
  $pendingCount = 0;
  $inProgressCount = 0;
  $resolvedCount = 0;
  foreach ($all_tickets as $t) {
    switch (strtolower($t['status'])) {
      case 'pending':
        $pendingCount++;
        break;
      case 'in progress':
        $inProgressCount++;
        break;
      case 'resolved':
        $resolvedCount++;
        break;
    }
  }

  // Map a status string to the CSS modifier class used by .tk-status
  function statusClass(string $status): string
  {
    return match (strtolower($status)) {
      'pending' => 'pending',
      'in progress' => 'in-progress',
      'resolved' => 'resolved',
      'rejected' => 'rejected',
      default => 'other',
    };
  }
  ?>

  <div class="content-wrapper">
    <div class="container-xxl flex-grow-1 container-p-y tk-page">

      <div class="tk-header">
        <div>
          <h2>Ticket Requests</h2>
          <p>Track, search, and review all submitted support tickets.</p>
        </div>
        <div class="tk-header-stats">
          <div class="tk-stat-chip">
            <span class="num"><?= $totalCount ?></span>
            <span class="lbl">Total</span>
          </div>
          <div class="tk-stat-chip is-warn">
            <span class="num"><?= $pendingCount ?></span>
            <span class="lbl">Pending</span>
          </div>
          <div class="tk-stat-chip is-info">
            <span class="num"><?= $inProgressCount ?></span>
            <span class="lbl">In Progress</span>
          </div>
          <div class="tk-stat-chip is-success">
            <span class="num"><?= $resolvedCount ?></span>
            <span class="lbl">Resolved</span>
          </div>
        </div>
      </div>

      <div class="tk-card">
        <div class="tk-card-body">
          <h5>All Tickets</h5>
          <div class="tk-toolbar">
            <div class="tk-search">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
              <input type="text" id="searchInput" placeholder="Search ticket...">
            </div>
            <select class="tk-select" id="statusFilter">
              <option value="all">All Statuses</option>
              <option value="Pending">Pending</option>
              <option value="In Progress">In Progress</option>
              <option value="Resolved">Resolved</option>
            </select>
          </div>
        </div>

        <?php if ($totalCount > 0): ?>
          <div class="tk-table-wrap">
            <table class="tk-table" id="ticketTable">
              <thead>
                <tr>
                  <th data-column="0" class="sortable">Ticket #</th>
                  <th data-column="1" class="sortable">Name</th>
                  <th data-column="2" class="sortable">Subject</th>
                  <th data-column="3" class="sortable">Status</th>
                  <th data-column="4" class="sortable">Assigned To</th>
                  <th data-column="5" class="sortable">Created At</th>
                  <th data-column="6" class="sortable">Last Updated</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($all_tickets as $row):
                  $statusCls = statusClass($row['status']);
                  $createdDisplay = htmlspecialchars(date('Y-m-d h:i A', strtotime($row['created_at'])));
                  $updatedDisplay = $row['last_update'] ? htmlspecialchars(date('Y-m-d h:i A', strtotime($row['last_update']))) : '—';
                  $assignedDisplay = htmlspecialchars($row['assigned_to'] ?: 'Pending Assignment');
                  $link = 'viewticketSuperadmin.php?ticket_id=' . $row['id'];
                  ?>
                  <tr class="clickable-row"
                    data-id="<?= htmlspecialchars($row['id']); ?>"
                    data-name="<?= htmlspecialchars($row['user_name']); ?>"
                    data-subject="<?= htmlspecialchars($row['subject']); ?>"
                    data-status="<?= htmlspecialchars($row['status']); ?>"
                    data-assigned="<?= $assignedDisplay; ?>"
                    data-created="<?= $createdDisplay; ?>"
                    data-updated="<?= $updatedDisplay; ?>"
                    data-description="<?= htmlspecialchars($row['description']); ?>"
                    data-link="<?= htmlspecialchars($link); ?>"
                    title="Click to view details">
                    <td><?= $row['id']; ?></td>
                    <td><?= htmlspecialchars($row['user_name']); ?></td>
                    <td class="subject-col"><?= htmlspecialchars($row['subject']); ?></td>
                    <td class="status-cell"><span class="tk-status <?= $statusCls ?>"><?= htmlspecialchars($row['status']); ?></span></td>
                    <td><?= $assignedDisplay; ?></td>
                    <td><?= $createdDisplay; ?></td>
                    <td><?= $updatedDisplay; ?></td>
                    <td><a href="<?= htmlspecialchars($link); ?>" class="tk-btn-pill is-primary" onclick="event.stopPropagation();">View</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="tk-pagination" id="tkPagination">
            <span class="tk-pagination-info" id="paginationInfo"></span>
            <div class="tk-pagination-right">
              <div class="tk-pagesize-group">
                <span>Show</span>
                <select class="tk-select" id="pageSizeSelect">
                  <option value="10">10</option>
                  <option value="25">25</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
                  <option value="all">All</option>
                </select>
                <span>entries</span>
              </div>
              <div class="tk-pagination-controls">
                <button type="button" class="tk-page-btn" id="prevPageBtn" aria-label="Previous page">‹</button>
                <span class="tk-page-indicator" id="pageIndicator"></span>
                <button type="button" class="tk-page-btn" id="nextPageBtn" aria-label="Next page">›</button>
              </div>
            </div>
          </div>
        <?php else: ?>
          <div class="tk-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 12h6m-6 4h6m-9 5h12a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-3.5L13 3h-2L9.5 5H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z" /></svg>
            <strong>No tickets found</strong>
            Submitted tickets will show up here.
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>

  <div class="content-backdrop fade"></div>
  <div class="layout-overlay layout-menu-toggle"></div>

  <!-- 📋 Ticket Details Modal -->
  <div class="modal fade tk-modal" id="ticketModal" tabindex="-1" aria-labelledby="ticketModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="ticketModalLabel">Ticket Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="tk-detail-grid">
            <div>
              <div class="tk-detail-label">Ticket #</div>
              <div class="tk-detail-value" id="modalTicketId"></div>
            </div>
            <div>
              <div class="tk-detail-label">Status</div>
              <span id="modalTicketStatus" class="tk-status"></span>
            </div>
            <div>
              <div class="tk-detail-label">Submitted By</div>
              <div class="tk-detail-value" id="modalTicketName"></div>
            </div>
            <div>
              <div class="tk-detail-label">Assigned To</div>
              <div class="tk-detail-value" id="modalTicketAssigned"></div>
            </div>
            <div>
              <div class="tk-detail-label">Created At</div>
              <div class="tk-detail-value" id="modalTicketCreated"></div>
            </div>
            <div>
              <div class="tk-detail-label">Last Updated</div>
              <div class="tk-detail-value" id="modalTicketUpdated"></div>
            </div>
            <div class="full">
              <div class="tk-detail-label">Subject</div>
              <div class="tk-detail-value" id="modalTicketSubject"></div>
            </div>
          </div>

          <div class="tk-detail-label">Description</div>
          <div id="modalTicketDescription" class="tk-detail-desc"></div>
        </div>

        <div class="modal-footer">
          <button type="button" class="tk-modal-close" data-bs-dismiss="modal">Close</button>
          <a id="viewTicketLink" href="#" class="tk-btn-pill is-primary">View Full Ticket</a>
        </div>
      </div>
    </div>
  </div>

  <!-- CORE JS -->
  <script src="../assets/vendor/js/bootstrap.js"></script>
  <script src="../assets/vendor/js/menu.js"></script>
  <script src="../assets/js/main.js"></script>

  <script>
    // Ticket modal + search/filter/sort/pagination — single consolidated script (vanilla JS, no jQuery needed)
    (function () {
      const table = document.getElementById('ticketTable');
      if (!table) return; // no tickets rendered, nothing to wire up

      const tbody = table.querySelector('tbody');
      const rows = Array.from(tbody.querySelectorAll('tr'));
      const searchInput = document.getElementById('searchInput');
      const statusFilter = document.getElementById('statusFilter');
      const pageSizeSelect = document.getElementById('pageSizeSelect');
      const prevBtn = document.getElementById('prevPageBtn');
      const nextBtn = document.getElementById('nextPageBtn');
      const pageIndicator = document.getElementById('pageIndicator');
      const paginationInfo = document.getElementById('paginationInfo');
      const sortDirections = {};
      let currentPage = 1;

      // ── Ticket details modal ────────────────────────────────
      const modalEl = document.getElementById('ticketModal');
      const modal = new bootstrap.Modal(modalEl);
      const statusModifier = {
        'pending': 'pending',
        'in progress': 'in-progress',
        'resolved': 'resolved',
        'rejected': 'rejected'
      };

      rows.forEach(row => {
        row.addEventListener('click', () => {
          document.getElementById('modalTicketId').textContent = row.dataset.id;
          document.getElementById('modalTicketName').textContent = row.dataset.name;
          document.getElementById('modalTicketSubject').textContent = row.dataset.subject;
          document.getElementById('modalTicketAssigned').textContent = row.dataset.assigned;
          document.getElementById('modalTicketCreated').textContent = row.dataset.created;
          document.getElementById('modalTicketUpdated').textContent = row.dataset.updated;
          document.getElementById('modalTicketDescription').textContent = row.dataset.description || 'No description provided.';
          document.getElementById('viewTicketLink').href = row.dataset.link;

          const status = row.dataset.status;
          const badge = document.getElementById('modalTicketStatus');
          badge.textContent = status;
          badge.className = 'tk-status ' + (statusModifier[status.toLowerCase()] || 'other');

          modal.show();
        });
      });

      // ── Search + status filter (returns the rows that match, in current DOM/sort order) ──
      function getFilteredRows() {
        const query = searchInput.value.trim().toLowerCase();
        const status = statusFilter.value;

        return rows.filter(row => {
          const matchesSearch = row.textContent.toLowerCase().includes(query);
          const matchesStatus = status === 'all' || row.dataset.status === status;
          return matchesSearch && matchesStatus;
        });
      }

      // ── Pagination: shows only the current page's slice of the filtered rows ──
      function renderPage() {
        const filtered = getFilteredRows();
        const totalItems = filtered.length;
        const pageSizeRaw = pageSizeSelect.value;
        const pageSize = pageSizeRaw === 'all' ? Math.max(totalItems, 1) : parseInt(pageSizeRaw, 10);
        const totalPages = Math.max(1, Math.ceil(totalItems / pageSize));

        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const start = (currentPage - 1) * pageSize;
        const end = pageSizeRaw === 'all' ? totalItems : start + pageSize;
        const visibleSet = new Set(filtered.slice(start, end));

        rows.forEach(row => {
          row.style.display = visibleSet.has(row) ? '' : 'none';
        });

        if (totalItems === 0) {
          paginationInfo.textContent = 'No matching tickets';
        } else {
          paginationInfo.textContent = `Showing ${start + 1}–${Math.min(end, totalItems)} of ${totalItems}`;
        }

        pageIndicator.textContent = `Page ${currentPage} of ${totalPages}`;
        prevBtn.disabled = currentPage <= 1;
        nextBtn.disabled = currentPage >= totalPages;
      }

      function applyFilters() {
        currentPage = 1; // reset to page 1 whenever the result set changes
        renderPage();
      }

      searchInput.addEventListener('input', applyFilters);
      statusFilter.addEventListener('change', applyFilters);
      pageSizeSelect.addEventListener('change', applyFilters);

      prevBtn.addEventListener('click', () => {
        currentPage -= 1;
        renderPage();
      });
      nextBtn.addEventListener('click', () => {
        currentPage += 1;
        renderPage();
      });

      // ── Sorting ──────────────────────────────────────────────
      table.querySelectorAll('th.sortable').forEach(th => {
        th.addEventListener('click', () => {
          const columnIndex = Number(th.dataset.column);
          const isAsc = !sortDirections[columnIndex];
          sortDirections[columnIndex] = isAsc;

          rows.sort((a, b) => {
            const aText = a.cells[columnIndex].innerText.trim().toLowerCase();
            const bText = b.cells[columnIndex].innerText.trim().toLowerCase();

            const aNum = parseFloat(aText);
            const bNum = parseFloat(bText);
            const bothNumeric = !isNaN(aNum) && !isNaN(bNum) && aText !== '' && bText !== '';

            if (bothNumeric) return isAsc ? aNum - bNum : bNum - aNum;
            return isAsc ? aText.localeCompare(bText) : bText.localeCompare(aText);
          });

          rows.forEach(row => tbody.appendChild(row));

          table.querySelectorAll('th.sortable').forEach(el => el.classList.remove('asc', 'desc'));
          th.classList.add(isAsc ? 'asc' : 'desc');

          currentPage = 1;
          renderPage();
        });
      });

      // Initial paint
      renderPage();
    })();
  </script>

</body>

</html>