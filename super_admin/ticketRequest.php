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

$pageSizeOptions = [25, 50, 100];
$pageSize = isset($_GET['page_size']) ? (int) $_GET['page_size'] : 25;
if (!in_array($pageSize, $pageSizeOptions, true)) {
  $pageSize = 25;
}

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$search = trim((string) ($_GET['search'] ?? ''));
$search = substr($search, 0, 100);
$requestedStatus = (string) ($_GET['status'] ?? 'all');
$allowedStatuses = ['Pending', 'In Progress', 'Resolved'];
$status = in_array($requestedStatus, $allowedStatuses, true) ? $requestedStatus : 'all';
$sortColumns = [
  'id' => 't.id',
  'name' => 'user_name',
  'subject' => 't.subject',
  'status' => 't.status',
  'assigned' => 'assigned_to',
  'created' => 't.created_at',
  'updated' => 'last_update',
];
$sort = (string) ($_GET['sort'] ?? 'created');
if (!isset($sortColumns[$sort])) {
  $sort = 'created';
}
$direction = strtolower((string) ($_GET['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

$statsResult = $conn->query("
  SELECT status, COUNT(*) AS ticket_count
  FROM tickets
  WHERE admin_approved = 1
    AND status IN ('Pending', 'In Progress', 'Resolved')
  GROUP BY status
");
if (!$statsResult) {
  throw new RuntimeException('Unable to load ticket counts: ' . $conn->error);
}

$ticketCounts = ['Pending' => 0, 'In Progress' => 0, 'Resolved' => 0];
while ($countRow = $statsResult->fetch_assoc()) {
  $ticketCounts[$countRow['status']] = (int) $countRow['ticket_count'];
}
$statsResult->free();
$totalCount = array_sum($ticketCounts);

$where = "t.admin_approved = 1 AND t.status IN ('Pending', 'In Progress', 'Resolved')";
$parameterTypes = '';
$parameters = [];
if ($status !== 'all') {
  $where .= ' AND t.status = ?';
  $parameterTypes .= 's';
  $parameters[] = $status;
}
if ($search !== '') {
  $where .= " AND (
    CAST(t.id AS CHAR) LIKE ?
    OR CONCAT_WS(' ', u.firstname, u.middlename, u.lastname) LIKE ?
    OR t.subject LIKE ?
    OR t.description LIKE ?
    OR t.status LIKE ?
    OR DATE_FORMAT(t.created_at, '%Y-%m-%d %h:%i %p') LIKE ?
    OR DATE_FORMAT((
      SELECT th_search.timestamp
      FROM ticket_history th_search
      WHERE th_search.ticket_id = t.id
      ORDER BY th_search.timestamp DESC
      LIMIT 1
    ), '%Y-%m-%d %h:%i %p') LIKE ?
    OR COALESCE(NULLIF(TRIM(REPLACE((
      SELECT SUBSTRING_INDEX(th_assigned.action, ' by ', 1)
      FROM ticket_history th_assigned
      WHERE th_assigned.ticket_id = t.id
        AND th_assigned.action LIKE 'Ticket assigned to%'
      ORDER BY th_assigned.timestamp DESC
      LIMIT 1
    ), 'Ticket assigned to', '')), ''), 'Pending Assignment') LIKE ?
  )";
  $searchTerm = '%' . $search . '%';
  $parameterTypes .= 'ssssssss';
  array_push($parameters, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
}

$bindParameters = static function (mysqli_stmt $statement, string $types, array &$values): void {
  if ($types === '') {
    return;
  }

  $bindings = [$types];
  foreach ($values as $key => &$value) {
    $bindings[] = &$value;
  }
  call_user_func_array([$statement, 'bind_param'], $bindings);
};

$countStatement = $conn->prepare("
  SELECT COUNT(*) AS matching_count
  FROM tickets t
  JOIN users u ON t.user_id = u.id
  WHERE $where
");
if (!$countStatement) {
  throw new RuntimeException('Unable to prepare ticket count query: ' . $conn->error);
}
$countParameters = $parameters;
$bindParameters($countStatement, $parameterTypes, $countParameters);
if (!$countStatement->execute()) {
  throw new RuntimeException('Unable to count tickets: ' . $countStatement->error);
}
$matchingCount = (int) $countStatement->get_result()->fetch_assoc()['matching_count'];
$countStatement->close();

$totalPages = max(1, (int) ceil($matchingCount / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;

$ticketStatement = $conn->prepare("
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
      (
        SELECT SUBSTRING_INDEX(th2.action, ' by ', 1)
        FROM ticket_history th2
        WHERE th2.ticket_id = t.id
          AND th2.action LIKE 'Ticket assigned to%'
        ORDER BY th2.timestamp DESC
        LIMIT 1
      ), 'Ticket assigned to', '')
    ) AS assigned_to
  FROM tickets t
  JOIN users u ON t.user_id = u.id
  WHERE $where
  ORDER BY {$sortColumns[$sort]} $direction, t.id DESC
  LIMIT ? OFFSET ?
");
if (!$ticketStatement) {
  throw new RuntimeException('Unable to prepare ticket list query: ' . $conn->error);
}
$ticketParameters = $parameters;
$ticketTypes = $parameterTypes . 'ii';
$ticketParameters[] = $pageSize;
$ticketParameters[] = $offset;
$bindParameters($ticketStatement, $ticketTypes, $ticketParameters);
if (!$ticketStatement->execute()) {
  throw new RuntimeException('Unable to load tickets: ' . $ticketStatement->error);
}
$tickets = $ticketStatement->get_result()->fetch_all(MYSQLI_ASSOC);
$ticketStatement->close();
$conn->close();

$pageUrl = static function (array $changes): string {
  return '?' . http_build_query(array_merge($_GET, $changes));
};
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
    .tk-table thead th.sortable a { color: inherit; text-decoration: none; }
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
    .tk-page-btn[aria-disabled="true"] { opacity: .4; pointer-events: none; cursor: not-allowed; }
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
            <span class="num"><?= $ticketCounts['Pending'] ?></span>
            <span class="lbl">Pending</span>
          </div>
          <div class="tk-stat-chip is-info">
            <span class="num"><?= $ticketCounts['In Progress'] ?></span>
            <span class="lbl">In Progress</span>
          </div>
          <div class="tk-stat-chip is-success">
            <span class="num"><?= $ticketCounts['Resolved'] ?></span>
            <span class="lbl">Resolved</span>
          </div>
        </div>
      </div>

      <div class="tk-card">
        <div class="tk-card-body">
          <h5>All Tickets</h5>
          <form class="tk-toolbar" method="get" id="ticketFilters">
            <div class="tk-search">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
              <input type="search" name="search" id="searchInput" placeholder="Search ticket..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <select class="tk-select" name="status" id="statusFilter">
              <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Statuses</option>
              <option value="Pending" <?= $status === 'Pending' ? 'selected' : '' ?>>Pending</option>
              <option value="In Progress" <?= $status === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
              <option value="Resolved" <?= $status === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
            </select>
            <input type="hidden" name="page_size" value="<?= $pageSize ?>">
          </form>
        </div>

        <?php if ($matchingCount > 0): ?>
          <div class="tk-table-wrap">
            <table class="tk-table" id="ticketTable">
              <thead>
                <tr>
                  <?php
                  $sortLabels = [
                    'id' => 'Ticket #',
                    'name' => 'Name',
                    'subject' => 'Subject',
                    'status' => 'Status',
                    'assigned' => 'Assigned To',
                    'created' => 'Created At',
                    'updated' => 'Last Updated',
                  ];
                  foreach ($sortLabels as $sortKey => $label):
                    $nextDirection = $sort === $sortKey && $direction === 'ASC' ? 'desc' : 'asc';
                    ?>
                    <th class="sortable <?= $sort === $sortKey ? strtolower($direction) : '' ?>">
                      <a href="<?= htmlspecialchars($pageUrl(['sort' => $sortKey, 'direction' => $nextDirection, 'page' => 1]), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
                    </th>
                  <?php endforeach; ?>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($tickets as $row):
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
            <span class="tk-pagination-info">
              Showing <?= $offset + 1 ?>–<?= min($offset + count($tickets), $matchingCount) ?> of <?= $matchingCount ?> matching tickets
            </span>
            <div class="tk-pagination-right">
              <div class="tk-pagesize-group">
                <span>Show</span>
                <select class="tk-select" name="page_size" id="pageSizeSelect" form="ticketFilters">
                  <?php foreach ($pageSizeOptions as $option): ?>
                    <option value="<?= $option ?>" <?= $pageSize === $option ? 'selected' : '' ?>><?= $option ?></option>
                  <?php endforeach; ?>
                </select>
                <span>entries</span>
              </div>
              <div class="tk-pagination-controls">
                <a class="tk-page-btn" href="<?= htmlspecialchars($pageUrl(['page' => max(1, $page - 1)]), ENT_QUOTES, 'UTF-8') ?>" aria-label="Previous page" <?= $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>‹</a>
                <span class="tk-page-indicator">Page <?= $page ?> of <?= $totalPages ?></span>
                <a class="tk-page-btn" href="<?= htmlspecialchars($pageUrl(['page' => min($totalPages, $page + 1)]), ENT_QUOTES, 'UTF-8') ?>" aria-label="Next page" <?= $page >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>›</a>
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
    // Ticket modal and server-side ticket filters.
    (function () {
      const table = document.getElementById('ticketTable');
      const modalEl = document.getElementById('ticketModal');
      const statusModifier = {
        'pending': 'pending',
        'in progress': 'in-progress',
        'resolved': 'resolved',
        'rejected': 'rejected'
      };

      if (table) {
        const modal = new bootstrap.Modal(modalEl);
        table.querySelectorAll('tbody tr').forEach(row => {
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
      }

      const filterForm = document.getElementById('ticketFilters');
      const searchInput = document.getElementById('searchInput');
      const statusFilter = document.getElementById('statusFilter');
      const pageSizeSelect = document.getElementById('pageSizeSelect');
      let searchTimeout;

      searchInput.addEventListener('input', () => {
        window.clearTimeout(searchTimeout);
        searchTimeout = window.setTimeout(() => filterForm.requestSubmit(), 350);
      });
      statusFilter.addEventListener('change', () => filterForm.requestSubmit());
      pageSizeSelect.addEventListener('change', () => filterForm.requestSubmit());
    })();
  </script>

</body>

</html>