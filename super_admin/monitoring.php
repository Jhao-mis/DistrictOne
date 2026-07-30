<?php
session_start();

require '../vendor/autoload.php';
require '../db.php';
require 'login_verification.php';

/* =================================
   USER SESSION DATA
================================= */
$username = $_SESSION['username'];

date_default_timezone_set('Asia/Manila');
$now = date('Y-m-d H:i:s');

$update = $conn->prepare(
  "UPDATE users SET last_activity=? WHERE username=?"
);
$update->bind_param("ss", $now, $username);
$update->execute();
$update->close();

$query = $conn->prepare(
  "SELECT id, firstname, lastname, role, department 
     FROM users WHERE username=?"
);
$query->bind_param("s", $username);
$query->execute();
$query->bind_result($user_id, $firstname, $lastname, $role, $department);
$query->fetch();
$query->close();

$_SESSION['user_id'] = $user_id;
$_SESSION['firstname'] = $firstname;
$_SESSION['lastname'] = $lastname;
$_SESSION['role'] = $role;

$normalizedRole = trim(strtolower($role));

if (!in_array($normalizedRole, ['admin', 'super admin', 'mis'])) {
  header("Location: ../login.php");
  exit;
}

/* =================================
   FETCH COUNTS (REAL-TIME)
================================= */
if (isset($_GET['realtime']) && $_GET['realtime'] == '1') {

  // New Tickets
  $newTicketsCount = $conn->query(
    "SELECT COUNT(*) AS total FROM tickets WHERE status='Pending' AND admin_approved=0"
  )->fetch_assoc()['total'] ?? 0;

  // Active Tickets
  $activeTicketsCount = $conn->query(
    "SELECT COUNT(*) AS total FROM tickets WHERE status='In Progress' AND admin_approved=1"
  )->fetch_assoc()['total'] ?? 0;

  // Resolved Tickets
  $resolvedTicketsCount = $conn->query(
    "SELECT COUNT(*) AS total FROM tickets WHERE status='Resolved'"
  )->fetch_assoc()['total'] ?? 0;

  // Pending Room Reservations
  $pendingRoomsCount = $conn->query(
    "SELECT COUNT(*) AS total FROM room_reservations WHERE status='Pending'"
  )->fetch_assoc()['total'] ?? 0;

  // Total Users
  $totalUsersCount = $conn->query(
    "SELECT COUNT(*) AS total FROM users"
  )->fetch_assoc()['total'] ?? 0;

  // Not Approved Users
  $notApprovedUsersCount = $conn->query(
    "SELECT COUNT(*) AS total FROM users WHERE isVerified = 0"
  )->fetch_assoc()['total'] ?? 0;


  $data = [
    'ticket_requests' => $newTicketsCount,
    'active_tickets' => $activeTicketsCount,
    'resolved_tickets' => $resolvedTicketsCount,
    'room_requests' => $pendingRoomsCount,
    'total_users' => $totalUsersCount,
    'not_approved_users' => $notApprovedUsersCount
  ];

  header('Content-Type: application/json');
  echo json_encode($data);
  exit;
}

/* =================================
   REALTIME TABLES API
================================= */
if (isset($_GET['tickets']) && $_GET['tickets'] == '1') {

  // Fetch Pending Tickets
  $pendingTickets = $conn->query(
    "SELECT t.*, u.department
         FROM tickets t
         JOIN users u ON t.user_id=u.id
         WHERE t.status='Pending' AND t.admin_approved=0
         ORDER BY t.created_at DESC"
  );
  $pending = [];
  while ($t = $pendingTickets->fetch_assoc()) {
    $pending[] = [
      'id' => $t['id'],
      'fullname' => htmlspecialchars($t['fullname']),
      'subject' => htmlspecialchars($t['subject']),
      'department' => htmlspecialchars($t['department']),
      'created_at' => date('M d, Y h:i A', strtotime($t['created_at'])),
      'status' => 'Pending'
    ];
  }

  // Fetch In-Progress Tickets
  $inProgressTickets = $conn->query(
    "SELECT t.*, u.department
         FROM tickets t
         JOIN users u ON t.user_id=u.id
         WHERE t.status='In Progress' AND t.admin_approved=1
         ORDER BY t.created_at DESC"
  );
  $inprogress = [];
  while ($t = $inProgressTickets->fetch_assoc()) {
    $inprogress[] = [
      'id' => $t['id'],
      'fullname' => htmlspecialchars($t['fullname']),
      'subject' => htmlspecialchars($t['subject']),
      'department' => htmlspecialchars($t['department']),
      'created_at' => date('M d, Y h:i A', strtotime($t['created_at'])),
      'status' => 'In Progress'
    ];
  }

  header('Content-Type: application/json');
  echo json_encode([
    'pending' => $pending,
    'inprogress' => $inprogress
  ]);
  exit;
}

?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed">

<head>
  <meta charset="UTF-8">
  <title>Live Report</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="icon" href="../assets/img/favicon/districtone.png">
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css">
  <link rel="stylesheet" href="../assets/vendor/css/core.css">
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css">
  <link rel="stylesheet" href="../assets/css/demo.css">
  <link rel="stylesheet" href="../css/admin.css">
  <link rel="stylesheet" href="../css/reservation_dashboard.css">
  <link rel="stylesheet" href="./css/reservation.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

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
      --tk-resolved: #1f9d55;
      --tk-resolved-bg: #e4f7ea;
      --tk-rejected: #c0392b;
      --tk-rejected-bg: #fbe9e7;
      --tk-radius: 12px;
      --tk-shadow: 0 1px 2px rgba(20,20,43,.04), 0 8px 24px -12px rgba(20,20,43,.10);
    }

    .tk-wrap { font-family: inherit; color: var(--tk-text); }

    /* ── Page header ───────────────────────────────────────────────── */
    .tk-monitor-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 18px;
    }
    .tk-monitor-title { font-size: 19px; font-weight: 700; color: var(--tk-text); }
    .tk-monitor-sub { font-size: 13px; color: var(--tk-text-muted); margin-top: 2px; }
    .tk-live-pill {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      background: var(--tk-resolved-bg);
      color: var(--tk-resolved);
      font-size: 12px;
      font-weight: 700;
      padding: 6px 13px;
      border-radius: 999px;
    }
    .tk-live-dot {
      width: 7px; height: 7px; border-radius: 50%;
      background: var(--tk-resolved);
      animation: tk-pulse 1.6s ease-in-out infinite;
    }
    @keyframes tk-pulse {
      0% { box-shadow: 0 0 0 0 rgba(31,157,85,.5); }
      70% { box-shadow: 0 0 0 6px rgba(31,157,85,0); }
      100% { box-shadow: 0 0 0 0 rgba(31,157,85,0); }
    }

    /* ── Stat cards ────────────────────────────────────────────────── */
    .tk-stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 14px;
      margin-bottom: 22px;
    }
    .tk-stat-link { text-decoration: none; color: inherit; display: block; }
    .tk-stat {
      background: var(--tk-surface);
      border: 1px solid var(--tk-border);
      border-radius: var(--tk-radius);
      padding: 16px 18px;
      box-shadow: var(--tk-shadow);
      position: relative;
      overflow: hidden;
      transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
    }
    .tk-stat-link:hover .tk-stat {
      transform: translateY(-2px);
      box-shadow: 0 4px 16px -6px rgba(20,20,43,.18);
      border-color: var(--tk-primary);
    }
    .tk-stat::before {
      content: "";
      position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
      background: var(--bar, var(--tk-primary));
    }
    .tk-stat-top { display: flex; align-items: center; justify-content: space-between; }
    .tk-stat-icon {
      width: 34px; height: 34px; border-radius: 9px;
      display: flex; align-items: center; justify-content: center;
      background: var(--icon-bg, var(--tk-primary-soft));
      color: var(--icon-color, var(--tk-primary));
      flex-shrink: 0;
    }
    .tk-stat-icon svg { width: 17px; height: 17px; }
    .tk-stat .tk-stat-num { font-size: 26px; font-weight: 700; line-height: 1.1; margin-top: 12px; }
    .tk-stat .tk-stat-label { font-size: 12px; color: var(--tk-text-muted); margin-top: 3px; font-weight: 500; letter-spacing: .2px; }

    .tk-stat.is-new       { --bar: var(--tk-pending);  --icon-bg: var(--tk-pending-bg);  --icon-color: var(--tk-pending); }
    .tk-stat.is-active    { --bar: var(--tk-progress); --icon-bg: var(--tk-progress-bg); --icon-color: var(--tk-progress); }
    .tk-stat.is-resolved  { --bar: var(--tk-resolved); --icon-bg: var(--tk-resolved-bg); --icon-color: var(--tk-resolved); }
    .tk-stat.is-rooms     { --bar: var(--tk-primary);  --icon-bg: var(--tk-primary-soft); --icon-color: #2563a8; }
    .tk-stat.is-users     { --bar: #6b7280;            --icon-bg: #eef0f2; --icon-color: #6b7280; }
    .tk-stat.is-unverified{ --bar: var(--tk-rejected); --icon-bg: var(--tk-rejected-bg); --icon-color: var(--tk-rejected); }

    /* ── Table cards ───────────────────────────────────────────────── */
    .tk-card {
      background: var(--tk-surface);
      border: 1px solid var(--tk-border);
      border-radius: var(--tk-radius);
      box-shadow: var(--tk-shadow);
      overflow: hidden;
      margin-bottom: 18px;
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
    .tk-card-head .tk-table-count {
      margin-left: auto;
      font-size: 12px;
      font-weight: 600;
      color: var(--tk-text-muted);
      background: var(--tk-bg);
      border: 1px solid var(--tk-border);
      padding: 2px 9px;
      border-radius: 999px;
    }

    .adjustable-table {
      max-height: 280px;
      overflow-y: auto;
    }

    table.tk-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    table.tk-table thead th {
      position: sticky;
      top: 0;
      z-index: 2;
      background: var(--tk-bg);
      text-align: left;
      font-size: 11.5px;
      text-transform: uppercase;
      letter-spacing: .5px;
      color: var(--tk-text-muted);
      font-weight: 700;
      padding: 11px 20px;
      border-bottom: 1px solid var(--tk-border);
    }
    table.tk-table tbody td {
      padding: 12px 20px;
      border-bottom: 1px solid var(--tk-border);
      vertical-align: middle;
      color: var(--tk-text);
    }
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
    .tk-badge.st-pending  { background: var(--tk-pending-bg);  color: var(--tk-pending); }
    .tk-badge.st-progress { background: var(--tk-progress-bg); color: var(--tk-progress); }

    .tk-time { color: var(--tk-text-muted); font-size: 12.5px; }

    .tk-table-empty, .tk-table-loading {
      text-align: center;
      padding: 28px 12px;
      color: var(--tk-text-muted);
      font-size: 13px;
    }
  </style>
</head>

<body>

  <?php
  /* ROLE BASED SIDEBAR */
  switch ($normalizedRole) {
    case 'user':
      include '../user/sidebar.php';
      break;
    case 'mis':
      include '../mis/sidebar.php';
      break;
    case 'admin':
      include '../admin/sidebar.php';
      break;
    case 'super admin':
      include '../super_admin/sidebar.php';
      break;
    default:
      echo "Unauthorized";
      exit;
  }
  ?>

  <div class="content-wrapper">
    <div class="container-xxl flex-grow-1 container-p-y">
      <div class="tk-wrap">

        <div class="tk-monitor-header">
          <div>
            <div class="tk-monitor-title">Live Report</div>
            <div class="tk-monitor-sub">Real-time overview of tickets, reservations, and user accounts.</div>
          </div>
          <span class="tk-live-pill"><span class="tk-live-dot"></span>Live</span>
        </div>

        <!-- REAL-TIME DASHBOARD -->
        <div class="tk-stats">

          <!-- New Tickets -->
          <a href="approveTicket.php" class="tk-stat-link">
            <div class="tk-stat is-new">
              <div class="tk-stat-top">
                <div class="tk-stat-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6M9 16h6M9 8h6M5 4h14v16l-3-2-3 2-3-2-3 2V4z"/></svg>
                </div>
              </div>
              <div class="tk-stat-num" id="ticketRequests">0</div>
              <div class="tk-stat-label">New tickets</div>
            </div>
          </a>

          <!-- Active Tickets -->
          <a href="ticketRequest.php" class="tk-stat-link">
            <div class="tk-stat is-active">
              <div class="tk-stat-top">
                <div class="tk-stat-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                </div>
              </div>
              <div class="tk-stat-num" id="activeTickets">0</div>
              <div class="tk-stat-label">Active tickets</div>
            </div>
          </a>

          <!-- Resolved Tickets -->
          <a href="ticketRequest.php" class="tk-stat-link">
            <div class="tk-stat is-resolved">
              <div class="tk-stat-top">
                <div class="tk-stat-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                </div>
              </div>
              <div class="tk-stat-num" id="resolvedTickets">0</div>
              <div class="tk-stat-label">Resolved tickets</div>
            </div>
          </a>

          <!-- Room Reservations -->
          <a href="approveRoom.php" class="tk-stat-link">
            <div class="tk-stat is-rooms">
              <div class="tk-stat-top">
                <div class="tk-stat-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/></svg>
                </div>
              </div>
              <div class="tk-stat-num" id="roomRequests">0</div>
              <div class="tk-stat-label">Room reservations</div>
            </div>
          </a>

          <!-- Total Users -->
          <a href="accountManagement.php" class="tk-stat-link">
            <div class="tk-stat is-users">
              <div class="tk-stat-top">
                <div class="tk-stat-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
              </div>
              <div class="tk-stat-num" id="totalUsers">0</div>
              <div class="tk-stat-label">Total users</div>
            </div>
          </a>

          <!-- Not Approved Users -->
          <a href="accountManagement.php" class="tk-stat-link">
            <div class="tk-stat is-unverified">
              <div class="tk-stat-top">
                <div class="tk-stat-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a8 8 0 0 1 12.5-6.65M18 21l3-3M21 21l-3-3"/></svg>
                </div>
              </div>
              <div class="tk-stat-num" id="notApprovedUsers">0</div>
              <div class="tk-stat-label">Not verified users</div>
            </div>
          </a>
        </div>


        <!-- PENDING TICKETS TABLE -->
        <div class="tk-card">
          <div class="tk-card-head">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6M9 16h6M9 8h6M5 4h14v16l-3-2-3 2-3-2-3 2V4z"/></svg>
            New tickets
            <span class="tk-table-count" id="pendingCount">–</span>
          </div>
          <div class="table-responsive adjustable-table">
            <table class="tk-table">
              <thead>
                <tr>
                  <th>Ticket Number</th>
                  <th>Full Name</th>
                  <th>Subject</th>
                  <th>Department</th>
                  <th>Date Created</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody id="pendingTicketsBody">
                <tr>
                  <td colspan="6" class="tk-table-loading">Loading…</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- IN-PROGRESS TICKETS TABLE -->
        <div class="tk-card">
          <div class="tk-card-head">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
            Ongoing tickets
            <span class="tk-table-count" id="inProgressCount">–</span>
          </div>
          <div class="table-responsive adjustable-table">
            <table class="tk-table">
              <thead>
                <tr>
                  <th>Ticket Number</th>
                  <th>Full Name</th>
                  <th>Subject</th>
                  <th>Department</th>
                  <th>Date Created</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody id="inProgressTicketsBody">
                <tr>
                  <td colspan="6" class="tk-table-loading">Loading…</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </div>
  </div>

      <script src="../assets/vendor/js/bootstrap.js"></script>
      <script src="../assets/vendor/js/menu.js"></script>
      <script src="../assets/js/main.js"></script>

      <script>
        function loadRealtime() {
          fetch('monitoring.php?realtime=1', {
            cache: 'no-store'
          })
            .then(res => {
              if (!res.ok) throw new Error('Network response was not ok');
              return res.json();
            })
            .then(data => {
              document.getElementById('ticketRequests').innerText = data.ticket_requests ?? 0;
              document.getElementById('activeTickets').innerText = data.active_tickets ?? 0;
              document.getElementById('resolvedTickets').innerText = data.resolved_tickets ?? 0;
              document.getElementById('roomRequests').innerText = data.room_requests ?? 0;
              document.getElementById('totalUsers').innerText = data.total_users ?? 0;
              document.getElementById('notApprovedUsers').innerText = data.not_approved_users ?? 0;
            })
            .catch(err => console.error('Realtime fetch failed:', err));
        }

        loadRealtime();
        setInterval(loadRealtime, 5000);
      </script>

      <script>
        // --- Notification sound setup ---
        const notificationSound = new Audio('../assets/sounds/notification.wav');
        notificationSound.volume = 0.6; // adjust 0.0–1.0 as needed
        let previousPendingCount = null; // null = "not loaded yet", so we don't beep on first page load

        function playNotificationSound() {
          try {
            notificationSound.currentTime = 0; // rewind in case it's still playing from a rapid update
            notificationSound.play().catch(e => {
              // Autoplay blocked until user interacts with the page — this is expected on first load
              console.warn('Notification sound blocked until user interacts with page:', e);
            });
          } catch (e) {
            console.warn('Notification sound failed:', e);
          }
        }

        // Real-time tickets loader
        function loadTicketsRealtime() {
          fetch('monitoring.php?tickets=1', { cache: 'no-store' })
            .then(res => {
              if (!res.ok) throw new Error('Network response not ok');
              return res.json();
            })
            .then(data => {
              // 🔔 Play sound if new pending tickets came in since last check
              if (previousPendingCount !== null && data.pending.length > previousPendingCount) {
                playNotificationSound();
              }
              previousPendingCount = data.pending.length;

              // Pending tickets
              const pendingBody = document.getElementById('pendingTicketsBody');
              pendingBody.innerHTML = '';
              document.getElementById('pendingCount').textContent =
                data.pending.length + ' ticket' + (data.pending.length === 1 ? '' : 's');

              if (data.pending.length > 0) {
                data.pending.forEach(t => {
                  const tr = document.createElement('tr');
                  tr.innerHTML = `
            <td class="tk-id">#${t.id}</td>
            <td>${t.fullname}</td>
            <td class="tk-subject">${t.subject}</td>
            <td>${t.department}</td>
            <td class="tk-time">${t.created_at}</td>
            <td><span class="tk-badge st-pending"><span class="dot"></span>Pending</span></td>
          `;
                  pendingBody.appendChild(tr);
                });
              } else {
                pendingBody.innerHTML = '<tr><td colspan="6" class="tk-table-empty">✅ No pending tickets</td></tr>';
              }

              // In-Progress tickets
              const inProgressBody = document.getElementById('inProgressTicketsBody');
              inProgressBody.innerHTML = '';
              document.getElementById('inProgressCount').textContent =
                data.inprogress.length + ' ticket' + (data.inprogress.length === 1 ? '' : 's');

              if (data.inprogress.length > 0) {
                data.inprogress.forEach(t => {
                  const tr = document.createElement('tr');
                  tr.innerHTML = `
            <td class="tk-id">#${t.id}</td>
            <td>${t.fullname}</td>
            <td class="tk-subject">${t.subject}</td>
            <td>${t.department}</td>
            <td class="tk-time">${t.created_at}</td>
            <td><span class="tk-badge st-progress"><span class="dot"></span>Ongoing</span></td>
          `;
                  inProgressBody.appendChild(tr);
                });
              } else {
                inProgressBody.innerHTML = '<tr><td colspan="6" class="tk-table-empty">✅ No in-progress tickets</td></tr>';
              }
            })
            .catch(err => console.error('Realtime tickets error:', err));
        }

        // Initial load + repeat every 5 seconds
        loadTicketsRealtime();
        setInterval(loadTicketsRealtime, 5000);
      </script>


</body>

</html>