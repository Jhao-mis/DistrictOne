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

  <title>IT Service Request - District One</title>

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
  <link rel="stylesheet" href="../css/admin.css" />

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
    .app-brand-text {
      font-size: 20px !important;
      font-weight: bold;
      margin-left: 5px;
      margin-top: 10px;
    }

    .logo {
      margin-left: -30px;
      margin-top: 5px;
    }

    body {
      font-family: Arial, sans-serif;
      max-width: 100%;
      overflow-x: hidden;
    }

    .profile-container {
      position: relative;
      height: 130px;
      left: 50px;
      bottom: 170px;
      display: flex;
      align-items: center;
      /* Align profile picture vertically */
      justify-content: flex-start;
      /* Place profile picture near the cover photo */
    }

    /* Scrollable table wrapper when rows exceed 5 */
    .table-scroll {
      max-height: 300px;
      /* adjust height for scrolling */
      overflow-y: auto;
      display: block;
    }

    /* Compact table style */
    .table-smaller th,
    .table-smaller td {
      padding: 0.1rem 0.1rem;
      /* reduce padding */
      font-size: 0.85rem;
      /* smaller font */
      white-space: nowrap;
      /* prevent wrapping */
      vertical-align: middle;
    }

    /* Truncate long text */
    .table-smaller td.subject-col {
      max-width: 100px;
      /* adjust as needed */
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .table-scroll {
      max-height: 525px;
      overflow-y: auto;
    }

    .table-smaller th,
    .table-smaller td {
      font-size: 0.9rem;
      white-space: nowrap;
    }

    .subject-col {
      max-width: 300px;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .sortable {
      cursor: pointer;
      user-select: none;
      position: relative;
    }

    .sortable::after {
      content: '⇅';
      font-size: 0.7rem;
      margin-left: 5px;
      color: #999;
    }

    .sortable.asc::after {
      content: '▲';
      color: #333;
    }

    .sortable.desc::after {
      content: '▼';
      color: #333;
    }
  </style>
</head>

<body>

  <?php include 'sidebar.php' ?>
  <div class="content-wrapper">
    <!-- Content -->
    <div class="container-xxl flex-grow-1 container-p-y">
      <div class="content-wrapper">
        <div class="container-xxl flex-grow-1 container-p-y">
          <div class="card app-calendar-wrapper">
            <div class="container-xxl flex-grow-1 container-p-y">
              <div class="card mb-5">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                  <h5 class="mb-0">All Tickets</h5>
                  <div class="d-flex align-items-center gap-2">
                    <!-- 🔍 Search Bar -->
                    <input type="text" id="searchInput" class="form-control form-control-sm"
                      placeholder="Search ticket..." style="max-width: 200px;">

                    <!-- 🔽 Status Filter -->
                    <select class="form-select form-select-sm w-auto" id="statusFilter">
                      <option value="all">All</option>
                      <option value="Pending">Pending</option>
                      <option value="In Progress">In Progress</option>
                      <option value="Resolved">Resolved</option>
                      <!-- <option value="Rejected">Rejected</option> -->
                    </select>
                  </div>
                </div>

                <?php
                // Combine all ticket results into one dataset
                $all_tickets = [];

                while ($row = $result_new->fetch_assoc())
                  $all_tickets[] = $row;
                while ($row = $result_active->fetch_assoc())
                  $all_tickets[] = $row;
                while ($row = $result_resolved->fetch_assoc())
                  $all_tickets[] = $row;
                ?>

                <?php if (count($all_tickets) > 0): ?>
                  <div
                    class="table-responsive text-nowrap <?php echo (count($all_tickets) > 5) ? 'table-scroll' : ''; ?>">
                    <table class="table table-smaller" id="ticketTable">
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
                        <?php foreach ($all_tickets as $row): ?>
                          <tr class="clickable-row" data-id="<?= htmlspecialchars($row['id']); ?>"
                            data-name="<?= htmlspecialchars($row['user_name']); ?>"
                            data-subject="<?= htmlspecialchars($row['subject']); ?>"
                            data-status="<?= htmlspecialchars($row['status']); ?>"
                            data-assigned="<?= htmlspecialchars($row['assigned_to'] ?: 'Pending Assignment'); ?>"
                            data-created="<?= htmlspecialchars(date('Y-m-d h:i A', strtotime($row['created_at']))); ?>"
                            data-updated="<?= htmlspecialchars($row['last_update'] ? date('Y-m-d h:i A', strtotime($row['last_update'])) : '—'); ?>"
                            data-description="<?= htmlspecialchars($row['description']); ?>"
                            data-link="view_ticket_superadmin.php?ticket_id=<?= $row['id']; ?>" style="cursor: pointer;"
                            title="Click to view details">

                            <td><?= $row['id']; ?></td>
                            <td><?= htmlspecialchars($row['user_name']); ?></td>
                            <td class="subject-col"><?= htmlspecialchars($row['subject']); ?></td>
                            <td>
                              <?php
                              $status = strtolower($row['status']);
                              $badgeClass = match ($status) {
                                'pending' => 'bg-label-warning',
                                'in progress' => 'bg-label-info',
                                'resolved' => 'bg-label-success',
                                'rejected' => 'bg-label-danger',
                                default => 'bg-label-secondary'
                              };
                              ?>
                              <span class="badge <?= $badgeClass; ?> me-1"><?= htmlspecialchars($row['status']); ?></span>
                            </td>
                            <td><?= htmlspecialchars($row['assigned_to'] ?: 'Pending Assignment'); ?></td>
                            <td><?= date('Y-m-d h:i A', strtotime($row['created_at'])); ?></td>
                            <td><?= $row['last_update'] ? date('Y-m-d h:i A', strtotime($row['last_update'])) : '—'; ?></td>
                            <td><a href="view_ticket_superadmin.php?ticket_id=<?= $row['id']; ?>"
                                class="btn btn-sm btn-primary">View</a></td>
                          </tr>

                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php else: ?>
                  <p class="px-4 py-3">No tickets found.</p>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>


      <!-- 📋 Ticket Details Modal -->
      <div class="modal fade" id="ticketModal" tabindex="-1" aria-labelledby="ticketModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
          <div class="modal-content">
            <div class="modal-header bg-primary text-white">
              <h5 class="modal-title" id="ticketModalLabel">Ticket Details</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                aria-label="Close"></button>
            </div>

            <div class="modal-body">
              <div class="row mb-3">
                <div class="col-md-6">
                  <strong>Ticket #:</strong> <span id="modalTicketId"></span>
                </div>
                <div class="col-md-6">
                  <strong>Status:</strong> <span id="modalTicketStatus" class="badge"></span>
                </div>
              </div>

              <div class="mb-2">
                <strong>Submitted By:</strong> <span id="modalTicketName"></span>
              </div>

              <div class="mb-2">
                <strong>Subject:</strong> <span id="modalTicketSubject"></span>
              </div>

              <div class="mb-2">
                <strong>Assigned To:</strong> <span id="modalTicketAssigned"></span>
              </div>

              <div class="mb-2">
                <strong>Created At:</strong> <span id="modalTicketCreated"></span>
              </div>

              <div class="mb-3">
                <strong>Last Updated:</strong> <span id="modalTicketUpdated"></span>
              </div>

              <!-- 📝 Description Section -->
              <div class="mt-3">
                <strong>Description:</strong>
                <div id="modalTicketDescription" class="border rounded p-2 bg-light"
                  style="white-space: pre-wrap; min-height: 80px;"></div>
              </div>
            </div>

            <div class="modal-footer">
              <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <a id="viewTicketLink" href="#" class="btn btn-primary">View</a>
            </div>
          </div>
        </div>
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
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const rows = document.querySelectorAll(".clickable-row");
      const modal = new bootstrap.Modal(document.getElementById("ticketModal"));

      rows.forEach(row => {
        row.addEventListener("click", () => {
          // Populate modal fields
          document.getElementById("modalTicketId").textContent = row.dataset.id;
          document.getElementById("modalTicketName").textContent = row.dataset.name;
          document.getElementById("modalTicketSubject").textContent = row.dataset.subject;
          document.getElementById("modalTicketAssigned").textContent = row.dataset.assigned;
          document.getElementById("modalTicketCreated").textContent = row.dataset.created;
          document.getElementById("modalTicketUpdated").textContent = row.dataset.updated;
          document.getElementById("modalTicketDescription").textContent = row.dataset.description || "No description provided.";
          document.getElementById("viewTicketLink").href = row.dataset.link;

          // Set status badge
          const status = row.dataset.status;
          const badge = document.getElementById("modalTicketStatus");
          badge.textContent = status;
          badge.className = "badge";
          switch (status.toLowerCase()) {
            case "pending":
              badge.classList.add("bg-label-warning"); break;
            case "in progress":
              badge.classList.add("bg-label-info"); break;
            case "resolved":
              badge.classList.add("bg-label-success"); break;
            case "rejected":
              badge.classList.add("bg-label-danger"); break;
            default:
              badge.classList.add("bg-label-secondary");
          }

          modal.show();
        });
      });
    });
  </script>

  <!-- ✅ Filter + Search + Sort Script -->
  <script>
    const statusFilter = document.getElementById('statusFilter');
    const searchInput = document.getElementById('searchInput');
    const table = document.getElementById('ticketTable');
    const rows = Array.from(table.querySelectorAll('tbody tr'));
    let sortDirections = {}; // store ASC/DESC per column

    function filterAndSearch() {
      const filterValue = statusFilter.value.toLowerCase();
      const searchValue = searchInput.value.toLowerCase();

      rows.forEach(row => {
        const statusText = row.querySelector('.status-cell').innerText.toLowerCase();
        const rowText = row.innerText.toLowerCase();

        const matchesStatus = (filterValue === 'all' || statusText.includes(filterValue));
        const matchesSearch = rowText.includes(searchValue);

        row.style.display = (matchesStatus && matchesSearch) ? '' : 'none';
      });
    }

    statusFilter.addEventListener('change', filterAndSearch);
    searchInput.addEventListener('keyup', filterAndSearch);

    // 🔽 Sorting
    document.querySelectorAll('#ticketTable th.sortable').forEach(th => {
      th.addEventListener('click', () => {
        const columnIndex = th.dataset.column;
        const isAsc = !sortDirections[columnIndex];
        sortDirections[columnIndex] = isAsc;

        const sortedRows = [...rows].sort((a, b) => {
          const aText = a.cells[columnIndex].innerText.trim().toLowerCase();
          const bText = b.cells[columnIndex].innerText.trim().toLowerCase();

          if (!isNaN(aText) && !isNaN(bText)) {
            return isAsc ? aText - bText : bText - aText;
          }
          return isAsc
            ? aText.localeCompare(bText)
            : bText.localeCompare(aText);
        });

        const tbody = table.querySelector('tbody');
        tbody.innerHTML = '';
        sortedRows.forEach(row => tbody.appendChild(row));

        // Update visual indicator
        document.querySelectorAll('.sortable').forEach(el => el.classList.remove('asc', 'desc'));
        th.classList.add(isAsc ? 'asc' : 'desc');
      });
    });
  </script>


  <script>
    $(document).ready(function () {
      const $rows = $("#ticketTable tbody tr");

      // 🔍 SEARCH FUNCTION
      $("#searchInput").on("keyup", function () {
        const value = $(this).val().toLowerCase();

        $rows.filter(function () {
          const text = $(this).text().toLowerCase();
          $(this).toggle(text.indexOf(value) > -1);
        });
      });

      // 🔽 STATUS FILTER FUNCTION
      $("#statusFilter").on("change", function () {
        const selectedStatus = $(this).val();

        $rows.each(function () {
          const rowStatus = $(this).find("td:eq(3)").text().trim(); // status is in 4th column
          if (selectedStatus === "all" || rowStatus === selectedStatus) {
            $(this).show();
          } else {
            $(this).hide();
          }
        });
      });

      // 🧠 Combine Search + Filter
      function applySearchAndFilter() {
        const value = $("#searchInput").val().toLowerCase();
        const selectedStatus = $("#statusFilter").val();

        $rows.each(function () {
          const text = $(this).text().toLowerCase();
          const rowStatus = $(this).find("td:eq(3)").text().trim();

          const matchesSearch = text.indexOf(value) > -1;
          const matchesStatus = selectedStatus === "all" || rowStatus === selectedStatus;

          $(this).toggle(matchesSearch && matchesStatus);
        });
      }

      // 🔁 Reapply when both inputs change
      $("#searchInput, #statusFilter").on("keyup change", applySearchAndFilter);
    });
  </script>
</body>

</html>