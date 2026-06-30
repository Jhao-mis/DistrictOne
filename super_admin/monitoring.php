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
    /* Adjustable table with sticky header */
    .adjustable-table {
      max-height: 240px;
      /* adjust as needed */
      overflow-y: auto;
    }

    .adjustable-table thead th {
      position: sticky;
      top: 0;
      background: #fff;
      z-index: 2;
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

      <style>
        .dashboard-link {
          text-decoration: none;
          color: inherit;
        }

        .dashboard-link:hover .card {
          box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
          cursor: pointer;
        }
      </style>

      <!-- REAL-TIME DASHBOARD -->
      <div class="row g-3 mb-4">

        <!-- New Tickets -->
        <div class="col-md-2">
          <a href="approveTicket.php" class="dashboard-link">
            <div class="card text-center">
              <div class="card-body">
                <h6>New Tickets</h6>
                <h3 id="ticketRequests">0</h3>
              </div>
            </div>
          </a>
        </div>

        <!-- Active Tickets -->
        <div class="col-md-2">
          <a href="ticketRequest.php" class="dashboard-link">
            <div class="card text-center">
              <div class="card-body">
                <h6>Active Tickets</h6>
                <h3 id="activeTickets">0</h3>
              </div>
            </div>
          </a>
        </div>

        <!-- Resolved Tickets -->
        <div class="col-md-2">
          <a href="ticketRequest.php" class="dashboard-link">
            <div class="card text-center">
              <div class="card-body">
                <h6>Resolved Tickets</h6>
                <h3 id="resolvedTickets">0</h3>
              </div>
            </div>
          </a>
        </div>

        <!-- Room Reservations -->
        <div class="col-md-2">
          <a href="approveRoom.php" class="dashboard-link">
            <div class="card text-center">
              <div class="card-body">
                <h6>Room Reservations</h6>
                <h3 id="roomRequests">0</h3>
              </div>
            </div>
          </a>
        </div>

        <!-- Total Users -->
        <div class="col-md-2">
          <a href="accountManagement.php" class="dashboard-link">
            <div class="card text-center">
              <div class="card-body">
                <h6>Total Users</h6>
                <h3 id="totalUsers">0</h3>
              </div>
            </div>
          </a>
        </div>

        <!-- Not Approved Users -->
        <div class="col-md-2">
          <a href="accountManagement.php" class="dashboard-link">
            <div class="card text-center">
              <div class="card-body">
                <h6>Not Verified Users</h6>
                <h3 id="notApprovedUsers">0</h3>
              </div>
            </div>
          </a>
        </div>
      </div>


      <!-- PENDING TICKETS TABLE -->
      <div class="card">
        <h5 class="card-header">New Tickets</h5>
        <div class="table-responsive adjustable-table">
          <table class="table table-hover align-middle">
            <thead class="table-light">
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
                <td colspan="6" class="text-center p-3">Loading...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- IN-PROGRESS TICKETS TABLE -->
      <div class="card mt-4">
        <h5 class="card-header">Ongoing Tickets</h5>
        <div class="table-responsive adjustable-table">
          <table class="table table-hover align-middle">
            <thead class="table-light">
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
                <td colspan="6" class="text-center p-3">Loading...</td>
              </tr>
            </tbody>
          </table>
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
        // Real-time tickets loader
        function loadTicketsRealtime() {
          fetch('monitoring.php?tickets=1', { cache: 'no-store' })
            .then(res => {
              if (!res.ok) throw new Error('Network response not ok');
              return res.json();
            })
            .then(data => {
              // Pending tickets
              const pendingBody = document.getElementById('pendingTicketsBody');
              pendingBody.innerHTML = '';
              if (data.pending.length > 0) {
                data.pending.forEach(t => {
                  const tr = document.createElement('tr');
                  tr.innerHTML = `
            <td>#${t.id}</td>
            <td>${t.fullname}</td>
            <td>${t.subject}</td>
            <td>${t.department}</td>
            <td>${t.created_at}</td>
            <td><span class="badge bg-label-warning text-dark">Pending</span></td>
          `;
                  pendingBody.appendChild(tr);
                });
              } else {
                pendingBody.innerHTML = '<tr><td colspan="6" class="text-center p-3">✅ No pending tickets</td></tr>';
              }

              // In-Progress tickets
              const inProgressBody = document.getElementById('inProgressTicketsBody');
              inProgressBody.innerHTML = '';
              if (data.inprogress.length > 0) {
                data.inprogress.forEach(t => {
                  const tr = document.createElement('tr');
                  tr.innerHTML = `
            <td>#${t.id}</td>
            <td>${t.fullname}</td>
            <td>${t.subject}</td>
            <td>${t.department}</td>
            <td>${t.created_at}</td>
            <td><span class="badge bg-label-primary text-dark">In Progress</span></td>
          `;
                  inProgressBody.appendChild(tr);
                });
              } else {
                inProgressBody.innerHTML = '<tr><td colspan="6" class="text-center p-3">✅ No in-progress tickets</td></tr>';
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