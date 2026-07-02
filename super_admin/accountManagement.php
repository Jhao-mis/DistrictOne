<?php
require '../vendor/autoload.php';
require '../db.php';
require 'login_verification.php';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Update current user activity
$username = $_SESSION['username'];
date_default_timezone_set('Asia/Manila');
$now = date('Y-m-d H:i:s');
$updateActivity = $conn->prepare("UPDATE users SET last_activity = ? WHERE username = ?");
$updateActivity->bind_param("ss", $now, $username);
$updateActivity->execute();
$updateActivity->close();

// Fetch logged-in user details
$query = $conn->prepare("
  SELECT id, profile_picture, cover_photo, department, firstname, middlename, lastname, email, role 
  FROM users 
  WHERE username = ?
");
$query->bind_param("s", $username);
$query->execute();
$query->store_result();
$query->bind_result($user_id, $profile_picture, $cover_photo, $department, $firstname, $middlename, $lastname, $email, $user_role);
$query->fetch();
$query->close();


// Fetch all users 
$result = $conn->query("
  SELECT id, firstname, middlename, lastname, username, email, department, role, profile_picture, isVerified, last_activity 
  FROM users
  ORDER BY firstname ASC, lastname ASC, middlename ASC
");

// Prepare arrays and counters
$activeUsers = [];
$unverifiedUsers = [];
$activeCount = 0;
$unverifiedCount = 0;

while ($row = mysqli_fetch_assoc($result)) {
  if ($row['isVerified']) {
    $activeUsers[] = $row;
    $activeCount++;
  } else {
    $unverifiedUsers[] = $row;
    $unverifiedCount++;
  }
}
?>

<!DOCTYPE html>

<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/"
  data-template="vertical-menu-template-free">

<head>

  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>Account Management</title>

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
  <link rel="stylesheet" href="./css/acc.css">

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

  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/qtip2/3.0.3/jquery.qtip.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qtip2/3.0.3/jquery.qtip.min.js"></script>

  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
      --tk-radius: 14px;
      --tk-radius-sm: 9px;
      --tk-shadow: 0 1px 2px rgba(20, 20, 43, .04), 0 8px 24px -12px rgba(20, 20, 43, .10);
      --tk-shadow-lg: 0 20px 50px -18px rgba(20, 20, 43, .22);
      --tk-ease: cubic-bezier(.4, 0, .2, 1);
    }

    * {
      box-sizing: border-box;
    }

    .tk-page {
      animation: tk-fade-in .35s var(--tk-ease);
    }

    @keyframes tk-fade-in {
      from {
        opacity: 0;
        transform: translateY(6px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @media (prefers-reduced-motion: reduce) {

      .tk-page,
      .tk-card,
      .tk-btn,
      .tk-status {
        animation: none !important;
        transition: none !important;
      }
    }

    /* ── Header ─────────────────────────────────────────────── */
    .tk-header {
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 14px;
      margin-bottom: 20px;
    }

    .tk-header h2 {
      font-size: 21px;
      font-weight: 800;
      letter-spacing: -.2px;
      color: var(--tk-text);
      margin: 0 0 4px;
    }

    .tk-header p {
      font-size: 13.5px;
      color: var(--tk-text-muted);
      margin: 0;
    }

    .tk-header-stats {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .tk-stat-chip {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      background: var(--tk-surface);
      border: 1px solid var(--tk-border);
      border-radius: var(--tk-radius-sm);
      padding: 8px 14px;
      min-width: 96px;
    }

    .tk-stat-chip .num {
      font-size: 17px;
      font-weight: 800;
      color: var(--tk-text);
      line-height: 1.2;
    }

    .tk-stat-chip .lbl {
      font-size: 10.5px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .4px;
      color: var(--tk-text-muted);
      margin-top: 1px;
    }

    .tk-stat-chip.is-warn .num {
      color: var(--tk-room-deep);
    }

    /* ── Buttons ────────────────────────────────────────────── */
    .tk-btn {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      font-weight: 700;
      font-size: 13.5px;
      padding: 10px 18px;
      border-radius: 10px;
      border: 1.5px solid transparent;
      cursor: pointer;
      transition: all .15s var(--tk-ease);
      line-height: 1;
    }

    .tk-btn svg {
      width: 15px;
      height: 15px;
      flex-shrink: 0;
    }

    .tk-btn-primary {
      background: var(--tk-primary-dark);
      color: #fff;
      box-shadow: 0 6px 16px -8px rgba(78, 150, 240, .6);
    }

    .tk-btn-primary:hover {
      background: var(--tk-primary-deep);
      color: #fff;
    }

    .tk-btn-secondary {
      background: var(--tk-surface);
      border-color: var(--tk-border);
      color: var(--tk-text);
    }

    .tk-btn-secondary:hover {
      border-color: var(--tk-text-faint);
      color: var(--tk-text);
    }

    .tk-btn:focus-visible {
      outline: 2px solid var(--tk-primary);
      outline-offset: 2px;
    }

    .tk-btn-pill {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      font-size: 11.5px;
      font-weight: 800;
      padding: 6px 12px;
      border-radius: 999px;
      border: 1.5px solid transparent;
      transition: all .15s var(--tk-ease);
      white-space: nowrap;
      text-decoration: none;
      cursor: pointer;
    }

    .tk-btn-pill.is-primary {
      background: var(--tk-primary-soft);
      color: var(--tk-primary-deep);
    }

    .tk-btn-pill.is-primary:hover {
      background: var(--tk-primary-dark);
      color: #fff;
    }

    .tk-btn-pill.is-warn {
      background: var(--tk-warning-soft);
      color: var(--tk-warning-deep);
    }

    .tk-btn-pill.is-warn:hover {
      background: #f5a623;
      color: #fff;
    }

    .tk-btn-pill.is-success {
      background: var(--tk-success-soft);
      color: var(--tk-success-deep);
    }

    .tk-btn-pill.is-success:hover {
      background: var(--tk-success);
      color: #fff;
    }

    /* ── Card / table ───────────────────────────────────────── */
    .tk-card {
      background: var(--tk-surface);
      border: 1px solid var(--tk-border);
      border-radius: var(--tk-radius);
      box-shadow: var(--tk-shadow);
      overflow: hidden;
    }

    .tk-card-body {
      padding: 18px 20px 6px;
    }

    /* Tabs */
    .tk-tabs {
      display: flex;
      gap: 4px;
      background: var(--tk-bg);
      border: 1px solid var(--tk-border);
      border-radius: 11px;
      padding: 4px;
      margin-bottom: 16px;
      width: fit-content;
    }

    .tk-tabs .nav-link {
      border: none !important;
      background: transparent !important;
      color: var(--tk-text-muted) !important;
      font-size: 13px;
      font-weight: 700;
      padding: 8px 16px !important;
      border-radius: 8px !important;
      transition: all .15s var(--tk-ease);
    }

    .tk-tabs .nav-link.active {
      background: var(--tk-surface) !important;
      color: var(--tk-primary-deep) !important;
      box-shadow: var(--tk-shadow);
    }

    /* Search */
    .tk-search {
      position: relative;
      max-width: 340px;
      margin-bottom: 18px;
    }

    .tk-search svg {
      position: absolute;
      left: 13px;
      top: 50%;
      transform: translateY(-50%);
      width: 15px;
      height: 15px;
      color: var(--tk-text-faint);
      pointer-events: none;
    }

    .tk-search input {
      width: 100%;
      border: 1.5px solid var(--tk-border);
      border-radius: var(--tk-radius-sm);
      padding: 9px 14px 9px 36px;
      font-size: 13.5px;
      color: var(--tk-text);
      background: var(--tk-surface);
      transition: border-color .15s var(--tk-ease), box-shadow .15s var(--tk-ease);
    }

    .tk-search input:focus {
      outline: none;
      border-color: var(--tk-primary);
      box-shadow: 0 0 0 3px var(--tk-primary-soft);
    }

    .tk-table {
      width: 100%;
      border-collapse: collapse;
    }

    .tk-table thead th {
      background: var(--tk-bg);
      font-size: 10.5px;
      text-transform: uppercase;
      letter-spacing: .5px;
      font-weight: 800;
      color: var(--tk-text-muted);
      padding: 11px 16px;
      text-align: left;
      border-bottom: 1px solid var(--tk-border);
      white-space: nowrap;
      user-select: none;
    }

    .tk-table thead th.sortable:hover {
      color: var(--tk-primary-deep);
      cursor: pointer;
    }

    .tk-table thead th.asc::after {
      content: " ▲";
      font-size: 9px;
    }

    .tk-table thead th.desc::after {
      content: " ▼";
      font-size: 9px;
    }

    .tk-table tbody td {
      padding: 11px 16px;
      font-size: 13px;
      color: var(--tk-text);
      border-bottom: 1px solid var(--tk-border);
      vertical-align: middle;
    }

    .tk-table tbody tr:hover {
      background: var(--tk-bg);
    }

    .tk-table tbody tr:last-child td {
      border-bottom: none;
    }

    .tk-user-cell {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .tk-user-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid var(--tk-border);
      flex-shrink: 0;
    }

    .tk-user-name {
      font-weight: 700;
      font-size: 13px;
      color: var(--tk-text);
      text-transform: capitalize;
    }

    .tk-user-handle {
      font-size: 11.5px;
      color: var(--tk-text-muted);
    }

    .tk-status {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      font-size: 10.5px;
      font-weight: 800;
      letter-spacing: .2px;
      padding: 4px 10px;
      border-radius: 999px;
      white-space: nowrap;
    }

    .tk-status.verified {
      background: var(--tk-success-soft);
      color: var(--tk-success-deep);
    }

    .tk-status.unverified {
      background: var(--tk-room-soft);
      color: var(--tk-room-deep);
    }

    .tk-status.online {
      background: var(--tk-success-soft);
      color: var(--tk-success-deep);
    }

    .tk-status.offline {
      background: var(--tk-bg);
      color: var(--tk-text-faint);
      border: 1px solid var(--tk-border);
    }

    .tk-status.online::before {
      content: "";
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: var(--tk-success-deep);
    }

    .tk-empty {
      text-align: center;
      padding: 50px 16px;
      color: var(--tk-text-faint);
      font-size: 13px;
    }

    .tk-empty svg {
      width: 34px;
      height: 34px;
      opacity: .4;
      margin-bottom: 10px;
      display: block;
      margin-left: auto;
      margin-right: auto;
      color: var(--tk-text-muted);
    }

    .tk-empty strong {
      display: block;
      color: var(--tk-text-muted);
      font-weight: 700;
      font-size: 13.5px;
      margin-bottom: 3px;
    }

    /* ── Modal ──────────────────────────────────────────────── */
    .tk-modal .modal-content {
      border: none;
      border-radius: 18px;
      overflow: hidden;
      box-shadow: var(--tk-shadow-lg);
    }

    .tk-modal .modal-header {
      padding: 20px 24px;
      border-bottom: none;
    }

    .tk-modal .modal-header.is-success {
      background: linear-gradient(135deg, var(--tk-success) 0%, var(--tk-success-deep) 100%);
    }

    .tk-modal .modal-header.is-error {
      background: linear-gradient(135deg, var(--tk-room) 0%, var(--tk-room-deep) 100%);
    }

    .tk-modal .modal-header.is-edit {
      background: linear-gradient(135deg, var(--tk-primary) 0%, var(--tk-primary-deep) 100%);
    }

    .tk-modal .modal-title {
      color: #fff;
      font-size: 16px;
      font-weight: 800;
      letter-spacing: -.2px;
    }

    .tk-modal .btn-close {
      filter: brightness(0) invert(1);
      opacity: .85;
    }

    .tk-modal .btn-close:hover {
      opacity: 1;
    }

    .tk-modal .modal-body {
      padding: 22px 24px;
      font-size: 13.5px;
      color: var(--tk-text);
    }

    .tk-modal .modal-footer {
      border-top: 1px solid var(--tk-border);
      background: var(--tk-bg);
      padding: 14px 24px;
    }

    .tk-modal-close {
      background: var(--tk-surface);
      border: 1.5px solid var(--tk-border);
      color: var(--tk-text);
      font-weight: 700;
      font-size: 13.5px;
      padding: 9px 18px;
      border-radius: 10px;
      cursor: pointer;
      transition: border-color .15s var(--tk-ease), color .15s var(--tk-ease);
    }

    .tk-modal-close:hover {
      border-color: var(--tk-primary);
      color: var(--tk-primary-deep);
    }

    .swal2-container {
      z-index: 2000 !important;
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

    case 'Super Admin':
      include '../super_admin/sidebar.php';
      break;

    default:
      echo "<p>Unauthorized role.</p>";
      exit;
  }
  ?>

  <div class="content-wrapper">
    <div class="container-xxl flex-grow-1 container-p-y tk-page">

      <div class="tk-header">
        <div>
          <h2>Account Management</h2>
          <p>Review user accounts, verify new signups, and control access.</p>
        </div>
        <div class="tk-header-stats">
          <div class="tk-stat-chip">
            <span class="num"><?= $activeCount ?></span>
            <span class="lbl">Active</span>
          </div>
          <div class="tk-stat-chip <?= $unverifiedCount > 0 ? 'is-warn' : '' ?>">
            <span class="num"><?= $unverifiedCount ?></span>
            <span class="lbl">Unverified</span>
          </div>
        </div>
      </div>

      <div class="tk-card">
        <div class="tk-card-body">

          <!-- Tabs -->
          <ul class="nav tk-tabs" id="userTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active"
                type="button" role="tab">
                Active Users (<?= $activeCount ?>)
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="unverified-tab" data-bs-toggle="tab" data-bs-target="#unverified"
                type="button" role="tab">
                Unverified (<?= $unverifiedCount ?>)
              </button>
            </li>
          </ul>

          <!-- Search Bar -->
          <div class="tk-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"
              stroke-linejoin="round">
              <circle cx="11" cy="11" r="7" />
              <path d="m21 21-4.3-4.3" />
            </svg>
            <input type="text" placeholder="Search accounts..." id="searchInput">
          </div>

        </div>

        <div class="tab-content" id="userTabContent">

          <!-- Active Users Tab -->
          <div class="tab-pane fade show active" id="active" role="tabpanel">
            <?php if (!empty($activeUsers)): ?>
              <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                <table class="tk-table">
                  <thead>
                    <tr>
                      <th>Profile</th>
                      <th>Email</th>
                      <th>Department</th>
                      <th>Role</th>
                      <th>Status</th>
                      <th>Availability</th>
                      <th class="text-center">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($activeUsers as $row): ?>
                      <tr>
                        <td>
                          <div class="tk-user-cell">
                            <?php
                            $baseDir = '../uploads/dp/';
                            $default = $baseDir . 'default_dp.jpg';
                            $profileFilename = !empty($row['profile_picture']) ? basename($row['profile_picture']) : '';
                            $relativePath = $baseDir . $profileFilename;
                            $absolutePath = __DIR__ . '/' . $relativePath;
                            $finalPath = (file_exists($absolutePath) && !is_dir($absolutePath) && !empty($profileFilename))
                              ? $relativePath : $default;
                            ?>
                            <img src="<?= htmlspecialchars($finalPath) ?>" class="tk-user-avatar" alt="Profile">
                            <div>
                              <span class="tk-user-name d-block">
                                <?= htmlspecialchars($row['firstname'] . ' ' . $row['middlename'] . ' ' . $row['lastname']) ?>
                              </span>
                              <span class="tk-user-handle"><?= htmlspecialchars($row['username']) ?></span>
                            </div>
                          </div>
                        </td>
                        <td class="text-muted"><?= htmlspecialchars($row['email']) ?></td>
                        <td class="text-muted"><?= htmlspecialchars($row['department']) ?></td>
                        <td><?= htmlspecialchars($row['role']) ?></td>
                        <td><span class="tk-status verified">Verified</span></td>
                        <td>
                          <?php
                          date_default_timezone_set('Asia/Manila');
                          $last_activity = strtotime($row['last_activity']);
                          $current_time = time();
                          $threshold = 10;
                          if ($current_time - $last_activity <= $threshold) {
                            echo '<span class="tk-status online">Online</span>';
                          } else {
                            echo '<span class="tk-status offline">Offline</span>';
                          }
                          ?>
                        </td>
                        <td class="text-center">
                          <a href="#" class="tk-btn-pill is-primary"
                            onclick="openEditModal(<?= $row['id'] ?>); return false;">Edit</a>
                          <a href="#" class="tk-btn-pill is-warn"
                            onclick="confirmActivateDeactivate(<?= $row['id'] ?>, 0)">Deactivate</a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="tk-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                  <circle cx="12" cy="7" r="4" />
                </svg>
                <strong>No active accounts</strong>
                Verified accounts will show up here.
              </div>
            <?php endif; ?>
          </div>

          <!-- Unverified Users Tab -->
          <div class="tab-pane fade" id="unverified" role="tabpanel">
            <?php if (!empty($unverifiedUsers)): ?>
              <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                <table class="tk-table">
                  <thead>
                    <tr>
                      <th>Profile</th>
                      <th>Email</th>
                      <th>Department</th>
                      <th>Role</th>
                      <th>Status</th>
                      <th>Availability</th>
                      <th class="text-center">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($unverifiedUsers as $row): ?>
                      <tr>
                        <td>
                          <div class="tk-user-cell">
                            <?php
                            $baseDir = '../uploads/dp/';
                            $default = $baseDir . 'default_dp.jpg';
                            $profileFilename = !empty($row['profile_picture']) ? basename($row['profile_picture']) : '';
                            $relativePath = $baseDir . $profileFilename;
                            $absolutePath = __DIR__ . '/' . $relativePath;
                            $finalPath = (file_exists($absolutePath) && !is_dir($absolutePath) && !empty($profileFilename))
                              ? $relativePath : $default;
                            ?>
                            <img src="<?= htmlspecialchars($finalPath) ?>" class="tk-user-avatar" alt="Profile">
                            <div>
                              <span class="tk-user-name d-block">
                                <?= htmlspecialchars($row['firstname'] . ' ' . $row['middlename'] . ' ' . $row['lastname']) ?>
                              </span>
                              <span class="tk-user-handle"><?= htmlspecialchars($row['username']) ?></span>
                            </div>
                          </div>
                        </td>
                        <td class="text-muted"><?= htmlspecialchars($row['email']) ?></td>
                        <td class="text-muted"><?= htmlspecialchars($row['department']) ?></td>
                        <td><?= htmlspecialchars($row['role']) ?></td>
                        <td><span class="tk-status unverified">Unverified</span></td>
                        <td><span class="tk-status offline">Offline</span></td>
                        <td class="text-center">
                          <a href="#" class="tk-btn-pill is-primary"
                            onclick="openEditModal(<?= $row['id'] ?>); return false;">Edit</a>
                          <a href="#" class="tk-btn-pill is-success"
                            onclick="confirmActivateDeactivate(<?= $row['id'] ?>, 1)">Activate</a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="tk-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                  <circle cx="12" cy="7" r="4" />
                </svg>
                <strong>No unverified accounts</strong>
                New sign-ups awaiting verification will show up here.
              </div>
            <?php endif; ?>
          </div>

        </div>
      </div>
    </div>
  </div>

  <div class="content-backdrop fade"></div>

  <!-- Success Modal -->
  <div class="modal fade tk-modal" id="successModal" tabindex="-1" aria-labelledby="successModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header is-success">
          <h5 class="modal-title" id="successModalLabel">Success</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <?php if (isset($_SESSION['success'])) {
            echo $_SESSION['success'];
            unset($_SESSION['success']);
          } ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="tk-modal-close" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Error Modal -->
  <div class="modal fade tk-modal" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header is-error">
          <h5 class="modal-title" id="errorModalLabel">Error</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <?php if (isset($_SESSION['error'])) {
            echo $_SESSION['error'];
            unset($_SESSION['error']);
          } ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="tk-modal-close" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit Account Modal -->
  <div class="modal fade tk-modal" id="editAccountModal" tabindex="-1" aria-labelledby="editAccountModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header is-edit">
          <h5 class="modal-title" id="editAccountModalLabel">Edit Account</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <form id="editAccountForm">
          <div class="modal-body">
            <input type="hidden" name="id" id="edit_id">

            <h6 class="text-muted mb-3 fw-bold"
              style="font-size:12px; text-transform:uppercase; letter-spacing:.4px;">Personal Information</h6>
            <div class="row g-3 mb-3">
              <div class="col-md-4">
                <label class="form-label">First Name</label>
                <input type="text" name="firstname" id="edit_firstname" class="form-control" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Middle Name</label>
                <input type="text" name="middlename" id="edit_middlename" class="form-control">
              </div>
              <div class="col-md-4">
                <label class="form-label">Last Name</label>
                <input type="text" name="lastname" id="edit_lastname" class="form-control" required>
              </div>
            </div>

            <hr class="my-3">

            <h6 class="text-muted mb-3 fw-bold"
              style="font-size:12px; text-transform:uppercase; letter-spacing:.4px;">Account Information</h6>
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">Username</label>
                <input type="text" name="username" id="edit_username" class="form-control bg-light" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Employee No.</label>
                <input type="text" name="emp_id" id="edit_emp_id" class="form-control" required>
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" id="edit_email" class="form-control bg-light" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Department</label>
                <select name="department" id="edit_department" class="form-select" required>
                  <option value="Office of the General Manager">Office of the General Manager</option>
                  <option value="Management Information Services Section">Management Information Services Section
                  </option>
                  <option value="Administrative Department">Administrative Department</option>
                  <option value="Finance Department">Finance Department</option>
                  <option value="Commercial Department">Commercial Department</option>
                  <option value="Technical Services Department">Technical Services Department</option>
                  <option value="Operations Department">Operations Department</option>
                </select>
              </div>
            </div>

            <div class="mb-2">
              <label class="form-label">Role</label>
              <select name="role" id="edit_role" class="form-select" required>
                <option value="User">User</option>
                <option value="mis">MIS</option>
                <option value="Admin">Admin</option>
                <option value="Super Admin">Super Admin</option>
              </select>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="tk-modal-close" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="tk-btn tk-btn-primary" id="editSaveBtn">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="layout-overlay layout-menu-toggle"></div>

  <!-- CORE JS -->
  <script src="../assets/vendor/js/bootstrap.js"></script>
  <script src="../assets/vendor/js/menu.js"></script>
  <script src="../assets/js/main.js"></script>

  <script>
    // Show bootstrap modals if PHP set success/error (keeps existing modal markup)
    document.addEventListener("DOMContentLoaded", function () {
      <?php if (isset($_SESSION['success'])): ?>
        var successModal = new bootstrap.Modal(document.getElementById('successModal'));
        successModal.show();
      <?php endif; ?>

      <?php if (isset($_SESSION['error'])): ?>
        var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
        errorModal.show();
      <?php endif; ?>
    });
  </script>

  <script>
    // Confirm Activate/Deactivate using SweetAlert
    function confirmActivateDeactivate(id, status) {
      const action = status === 1 ? 'Activate' : 'Deactivate';
      Swal.fire({
        title: `Are you sure you want to ${action} this account?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: `Yes, ${action} it!`,
        cancelButtonText: 'Cancel',
        confirmButtonColor: status === 1 ? '#1e8a44' : '#c0392b',
        cancelButtonColor: '#767e8c'
      }).then((result) => {
        if (result.isConfirmed) {
          // redirect to server action
          window.location.href = `acc_management/deactivate_account.php?id=${id}&status=${status}&redirect=1`;
        }
      });
    }

    // Show success alert after status change via URL param
    window.addEventListener('DOMContentLoaded', () => {
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('status_changed')) {
        const changedStatus = urlParams.get('status_changed') === '1' ? 'activated' : 'deactivated';
        Swal.fire({
          title: 'Success!',
          text: `The account has been ${changedStatus}.`,
          icon: 'success',
          confirmButtonColor: '#4e96f0'
        });
      }
    });
  </script>

  <script>
    // Unified search input for both Active and Unverified tables
    (function () {
      const searchInput = document.getElementById("searchInput");
      if (!searchInput) return;

      searchInput.addEventListener("input", function () {
        const value = this.value.trim().toLowerCase();
        document.querySelectorAll(".tk-card table tbody").forEach(tbody => {
          tbody.querySelectorAll("tr").forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(value) ? "" : "none";
          });
        });
      });
    })();
  </script>

  <script>
    // Sortable table headers (numeric-aware). Skips columns named "Action" / "Actions".
    (function () {
      document.querySelectorAll(".tk-card table").forEach(table => {
        const ths = table.querySelectorAll("thead th");
        ths.forEach((th, index) => {
          const headerText = th.textContent.trim().toLowerCase();
          if (headerText === "" || headerText.includes("action")) return;

          th.classList.add("sortable");

          let asc = true;
          th.addEventListener("click", () => {
            const tbody = table.tBodies[0];
            if (!tbody) return;
            const rows = Array.from(tbody.querySelectorAll("tr"));

            const dir = asc ? 1 : -1;
            rows.sort((a, b) => {
              const aCell = (a.cells[index] && a.cells[index].innerText) ? a.cells[index].innerText.trim() : "";
              const bCell = (b.cells[index] && b.cells[index].innerText) ? b.cells[index].innerText.trim() : "";

              const aNum = parseFloat(aCell.replace(/[^0-9.\-]+/g, ""));
              const bNum = parseFloat(bCell.replace(/[^0-9.\-]+/g, ""));
              const aIsNum = !isNaN(aNum) && aCell !== "";
              const bIsNum = !isNaN(bNum) && bCell !== "";

              if (aIsNum && bIsNum) {
                return (aNum - bNum) * dir;
              }

              return aCell.toLowerCase().localeCompare(bCell.toLowerCase()) * dir;
            });

            rows.forEach(r => tbody.appendChild(r));

            asc = !asc;
            ths.forEach(h => h.classList.remove("asc", "desc"));
            th.classList.add(asc ? "asc" : "desc");
          });
        });
      });
    })();
  </script>

  <script>
    // Edit Account modal logic
    (function () {
      const editModalEl = document.getElementById('editAccountModal');
      const editModal = new bootstrap.Modal(editModalEl);
      const editForm = document.getElementById('editAccountForm');
      const editSaveBtn = document.getElementById('editSaveBtn');

      window.openEditModal = function (id) {
        fetch(`acc_management/edit_account.php?id=${id}`, { credentials: 'same-origin' })
          .then(res => res.text())
          .then(text => {
            let data;
            try {
              data = JSON.parse(text);
            } catch (err) {
              console.error('Non-JSON response from edit_account.php (GET):', text);
              Swal.fire({ icon: 'error', title: 'Error', text: 'Unexpected server response. Check console for details.' });
              return;
            }

            if (!data.success) {
              Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Could not load account.' });
              return;
            }

            const u = data.user;
            document.getElementById('edit_id').value = u.id;
            document.getElementById('edit_firstname').value = u.firstname || '';
            document.getElementById('edit_middlename').value = u.middlename || '';
            document.getElementById('edit_lastname').value = u.lastname || '';
            document.getElementById('edit_username').value = u.username || '';
            document.getElementById('edit_emp_id').value = u.emp_id || '';
            document.getElementById('edit_email').value = u.email || '';
            document.getElementById('edit_department').value = u.department || '';
            document.getElementById('edit_role').value = u.role || '';
            editModal.show();
          })
          .catch(err => {
            console.error('Fetch failed:', err);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to reach the server.' });
          });
      };

      editForm.addEventListener('submit', function (e) {
        e.preventDefault();
        editSaveBtn.disabled = true;
        editSaveBtn.textContent = 'Saving...';

        fetch('acc_management/edit_account.php', {
          method: 'POST',
          credentials: 'same-origin',
          body: new FormData(editForm)
        })
          .then(res => res.text())
          .then(text => {
            editSaveBtn.disabled = false;
            editSaveBtn.textContent = 'Save Changes';

            let data;
            try {
              data = JSON.parse(text);
            } catch (err) {
              console.error('Non-JSON response from edit_account.php (POST):', text);
              Swal.fire({ icon: 'error', title: 'Error', text: 'Unexpected server response. Check console for details.' });
              return;
            }

            if (!data.success) {
              Swal.fire({
                icon: 'error',
                title: data.duplicate_emp ? 'Duplicate Emp No.' : 'Error',
                text: data.message
              });
              return;
            }

            editModal.hide();
            Swal.fire({
              icon: 'success',
              title: 'Account Updated',
              text: data.message,
              confirmButtonColor: '#4e96f0'
            }).then(() => {
              window.location.reload();
            });
          })
          .catch(err => {
            console.error('Fetch failed:', err);
            editSaveBtn.disabled = false;
            editSaveBtn.textContent = 'Save Changes';
            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to save changes.' });
          });
      });
    })();
  </script>

</body>

</html>