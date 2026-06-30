<?php
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

$conn->close();
?>

<!DOCTYPE html>

<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/"
  data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

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

  <?php
  // 🟦 Count active (Pending + In Progress) assigned tickets
  $activeAssignedCount = 0;
  if (isset($result) && $result->num_rows > 0) {
    mysqli_data_seek($result, 0); // reset pointer if already fetched
    while ($rowTmp = $result->fetch_assoc()) {
      if (in_array($rowTmp['status'], ['Pending', 'In Progress'])) {
        $activeAssignedCount++;
      }
    }
    mysqli_data_seek($result, 0); // reset pointer for table display
  }
  ?>

  <div class="content-wrapper">
    <div class="container-xxl flex-grow-1 container-p-y">
      <div class="card app-calendar-wrapper p-3 shadow-sm border-0">

        <!-- 🔹 Tabs Header -->
        <ul class="nav nav-tabs mb-3" id="ticketTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="requests-tab" data-bs-toggle="tab" data-bs-target="#requests"
              type="button" role="tab">
              Ticket Requests
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="assigned-tab" data-bs-toggle="tab" data-bs-target="#assigned" type="button"
              role="tab">
              Assigned Tickets
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
            <div class="table-controls">
              <input type="text" id="searchRequests" class="form-control form-control-sm w-auto"
                placeholder="Search...">
              <select id="filterRequests" class="form-select form-select-sm w-auto">
                <option value="all">All</option>
                <option value="Pending">Pending</option>
                <option value="In Progress">In Progress</option>
                <option value="Resolved">Resolved</option>
              </select>
            </div>

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
                      <tr>
                        <td><?= $row_all['id']; ?></td>
                        <td><?= htmlspecialchars($row_all['user_name']); ?></td>
                        <td class="subject-col" title="<?= htmlspecialchars($row_all['subject']); ?>">
                          <?= htmlspecialchars($row_all['subject']); ?>
                        </td>
                        <td>
                          <?php
                          $status = htmlspecialchars($row_all['status']);
                          $badge = match ($status) {
                            'Pending' => 'bg-label-warning',
                            'In Progress' => 'bg-label-info',
                            'Resolved' => 'bg-label-success',
                            default => 'bg-label-secondary'
                          };
                          ?>
                          <span class="badge <?= $badge ?> me-1"><?= $status ?></span>
                        </td>
                        <td><?= $row_all['created_at'] ? date("M d, Y h:i A", strtotime($row_all['created_at'])) : 'N/A' ?>
                        </td>
                        <td><a href="viewTicket.php?ticket_id=<?= $row_all['id'] ?>" class="btn btn-sm btn-primary">View</a>
                        </td>
                      </tr>
                    <?php endwhile; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="px-4">No ticket requests found.</p>
            <?php endif; ?>
          </div>

          <!-- 🟩 Assigned Tickets Tab -->
          <div class="tab-pane fade" id="assigned" role="tabpanel" aria-labelledby="assigned-tab">
            <div class="table-controls">
              <input type="text" id="searchAssigned" class="form-control form-control-sm w-auto"
                placeholder="Search...">
              <select id="filterAssigned" class="form-select form-select-sm w-auto">
                <option value="all">All</option>
                <option value="Pending">Pending</option>
                <option value="In Progress">In Progress</option>
                <option value="Resolved">Resolved</option>
              </select>
            </div>

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
                      <tr>
                        <td><?= $row['id']; ?></td>
                        <td><?= htmlspecialchars($row['user_name']); ?></td>
                        <td class="subject-col" title="<?= htmlspecialchars($row['subject']); ?>">
                          <?= htmlspecialchars($row['subject']); ?>
                        </td>
                        <td>
                          <?php
                          $status = htmlspecialchars($row['status']);
                          $badge = match ($status) {
                            'Pending' => 'bg-label-warning',
                            'In Progress' => 'bg-label-info',
                            'Resolved' => 'bg-label-success',
                            default => 'bg-label-secondary'
                          };
                          ?>
                          <span class="badge <?= $badge ?> me-1"><?= $status ?></span>
                        </td>
                        <td><?= $row['created_at'] ? date("M d, Y h:i A", strtotime($row['created_at'])) : 'N/A' ?></td>
                        <td><a href="viewTicket.php?ticket_id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">View</a>
                        </td>
                      </tr>
                    <?php endwhile; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="px-4">No assigned tickets found.</p>
            <?php endif; ?>
          </div>
        </div> <!-- end tab content -->
      </div>
    </div>
  </div>
  </div>
  <div class="content-backdrop fade"></div>
  </div>
  <div class="content-backdrop fade"></div>
  </div>
  </div>
  </div>
  <div class="layout-overlay layout-menu-toggle"></div>
  </div>

  <script src="../assets/vendor/js/bootstrap.js"></script>
  <script src="../assets/vendor/js/menu.js"></script>
  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/dashboards-analytics.js"></script>
  <script async defer src="https://buttons.github.io/buttons.js"></script>

  <!-- ✅ JS for Filter + Search + Sorting -->
  <script>
    function initTableControls(tableId, searchId, filterId) {
      const table = document.getElementById(tableId);
      const rows = Array.from(table.querySelectorAll('tbody tr'));
      const search = document.getElementById(searchId);
      const filter = document.getElementById(filterId);
      const headers = table.querySelectorAll('th.sortable');
      let sortDir = {};

      // Search + Filter logic
      function updateDisplay() {
        const query = search.value.toLowerCase();
        const status = filter.value.toLowerCase();

        rows.forEach(row => {
          const text = row.innerText.toLowerCase();
          const stat = row.cells[3].innerText.toLowerCase();
          const matchSearch = text.includes(query);
          const matchStatus = status === 'all' || stat.includes(status);
          row.style.display = matchSearch && matchStatus ? '' : 'none';
        });
      }

      search.addEventListener('input', updateDisplay);
      filter.addEventListener('change', updateDisplay);

      // Sorting logic
      headers.forEach((th, i) => {
        th.addEventListener('click', () => {
          const asc = !sortDir[i];
          sortDir[i] = asc;
          const sorted = [...rows].sort((a, b) => {
            const aText = a.cells[i].innerText.trim().toLowerCase();
            const bText = b.cells[i].innerText.trim().toLowerCase();
            if (!isNaN(aText) && !isNaN(bText)) return asc ? aText - bText : bText - aText;
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
    initTableControls('tableRequests', 'searchRequests', 'filterRequests');
    initTableControls('tableAssigned', 'searchAssigned', 'filterAssigned');
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
    });
  </script>
</body>

</html>