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
      <div class="card app-calendar-wrapper">
        <div class="row g-0">
          <div class="container-xxl flex-grow-1 container-p-y">
            <div class="dashboard-actions mb-4 d-flex justify-content-between align-items-center">
              <a class="btn btn-primary btn-create">Submit a New Ticket</a>
              <div class="d-flex gap-2">
                <input type="text" id="searchInput" class="form-control form-control-sm"
                  placeholder="Search tickets...">
                <select id="statusFilter" class="form-select form-select-sm w-auto">
                  <option value="all">All</option>
                  <option value="Pending">Pending</option>
                  <option value="In Progress">In Progress</option>
                  <option value="Rejected">Rejected</option>
                  <option value="Resolved">Resolved</option>
                </select>
              </div>
            </div>

            <!-- Combined Ticket Table -->
            <div class="card">
              <h5 class="card-header">All Tickets</h5>

              <?php
              // Combine all tickets from your existing queries
              $all_tickets = array_merge(
                iterator_to_array($result_active),
                iterator_to_array($result_resolved),
                iterator_to_array($result_rejected)
              );
              ?>

              <?php if (!empty($all_tickets)): ?>
                <div class="table-responsive text-nowrap <?php echo (count($all_tickets) > 5) ? 'table-scroll' : ''; ?>">
                  <table class="table table-smaller" id="allTicketsTable">
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
                      <?php foreach ($all_tickets as $row): ?>
                        <tr class="ticket-row" data-ticket-id="<?= htmlspecialchars($row['id']) ?>"
                          data-ticket-subject="<?= htmlspecialchars($row['subject']) ?>"
                          data-ticket-status="<?= htmlspecialchars($row['status']) ?>"
                          data-ticket-assigned="<?= $row['assigned_to'] ? htmlspecialchars($row['assigned_to']) : 'Unassigned' ?>"
                          data-ticket-created="<?= date('Y-m-d H:i:s', strtotime($row['created_at'])) ?>"
                          data-ticket-updated="<?= $row['last_update'] ? date('Y-m-d H:i:s', strtotime($row['last_update'])) : 'No updates yet' ?>"
                          data-ticket-description="<?= htmlspecialchars($row['description'] ?? 'No description provided') ?>"
                          data-ticket-name="<?= htmlspecialchars($row['submitted_by'] ?? $username) ?>"
                          title="Click to view" style="cursor: pointer;">
                          <td><?= $row['id'] ?></td>
                          <td class="subject-col"><?= htmlspecialchars($row['subject']) ?></td>
                          <td>
                            <?php
                            $status = htmlspecialchars($row['status']);
                            $badge = match ($status) {
                              'Pending' => 'bg-label-warning',
                              'In Progress' => 'bg-label-info',
                              'Rejected' => 'bg-label-danger',
                              'Resolved' => 'bg-label-success',
                              default => 'bg-label-secondary'
                            };
                            ?>
                            <span class="badge <?= $badge ?> me-1"><?= $status ?></span>
                          </td>
                          <td><?= $row['assigned_to'] ? htmlspecialchars($row['assigned_to']) : 'Unassigned' ?></td>
                          <td><?= date("Y-m-d H:i:s", strtotime($row['created_at'])) ?></td>
                          <td>
                            <?= $row['last_update'] ? date("Y-m-d H:i:s", strtotime($row['last_update'])) : 'No updates yet' ?>
                          </td>
                          <td>
                            <a href="view_ticket.php?ticket_id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">View</a>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <p class="px-4">No tickets found.</p>
              <?php endif; ?>
            </div>
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
          <h5 class="modal-title" style="color: white;" id="ticketModalLabel">Ticket Details</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
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

          <div class="mb-2"><strong>Submitted By:</strong> <span id="modalTicketName"></span></div>
          <div class="mb-2"><strong>Subject:</strong> <span id="modalTicketSubject"></span></div>
          <div class="mb-2"><strong>Assigned To:</strong> <span id="modalTicketAssigned"></span></div>
          <div class="mb-2"><strong>Created At:</strong> <span id="modalTicketCreated"></span></div>
          <div class="mb-3"><strong>Last Updated:</strong> <span id="modalTicketUpdated"></span></div>

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

  <!-- Create Modal -->
  <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editModalLabel">Submit a New Ticket</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <?php if (isset($message)): ?>
            <p class="message"><?php echo htmlspecialchars($message); ?></p>
          <?php endif; ?>

          <form method="POST" class="ticket-form" id="ticketForm" action="serviceRequest.php">

            <label for="subject">Type of Concern </label>
            <select name="subject" id="subject" class="form-control" required>
              <option value="" disabled selected>▼ Select Type of Concern</option>

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

            <label for="description">Describe your issue</label>
            <textarea name="description" id="description" class="form-control" placeholder="Enter details..."
              required></textarea>
            <div class="modal-footer">
              <button type="submit" class="btn btn-primary" style="position:relative; left:-120px; top:20px;">Submit
                Ticket</button>
            </div>
          </form>
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


  <!-- ✅ Filter + Search + Sorting Script -->
  <script>
    const table = document.getElementById('allTicketsTable');
    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const statusFilter = document.getElementById('statusFilter');
    const searchInput = document.getElementById('searchInput');
    let sortDirections = {};

    function filterAndSearch() {
      const status = statusFilter.value.toLowerCase();
      const search = searchInput.value.toLowerCase();

      rows.forEach(row => {
        const statusText = row.cells[2].innerText.toLowerCase();
        const rowText = row.innerText.toLowerCase();
        const matchStatus = status === 'all' || statusText.includes(status);
        const matchSearch = rowText.includes(search);

        row.style.display = matchStatus && matchSearch ? '' : 'none';
      });
    }

    statusFilter.addEventListener('change', filterAndSearch);
    searchInput.addEventListener('input', filterAndSearch);

    // ✅ Sorting by clicking headers
    table.querySelectorAll('th').forEach((th, index) => {
      th.classList.add('sortable');
      th.addEventListener('click', () => {
        const isAsc = !sortDirections[index];
        sortDirections[index] = isAsc;

        const sortedRows = [...rows].sort((a, b) => {
          const aText = a.cells[index].innerText.trim().toLowerCase();
          const bText = b.cells[index].innerText.trim().toLowerCase();
          if (!isNaN(aText) && !isNaN(bText)) {
            return isAsc ? aText - bText : bText - aText;
          }
          return isAsc ? aText.localeCompare(bText) : bText.localeCompare(aText);
        });

        const tbody = table.querySelector('tbody');
        tbody.innerHTML = '';
        sortedRows.forEach(row => tbody.appendChild(row));

        // update arrows
        table.querySelectorAll('th').forEach(h => h.classList.remove('asc', 'desc'));
        th.classList.add(isAsc ? 'asc' : 'desc');
      });
    });
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

        // Dynamic badge color
        const statusBadge = document.getElementById('modalTicketStatus');
        statusBadge.className = 'badge';
        if (status === 'Pending') statusBadge.classList.add('bg-label-warning');
        else if (status === 'In Progress') statusBadge.classList.add('bg-label-info');
        else if (status === 'Rejected') statusBadge.classList.add('bg-label-danger');
        else if (status === 'Resolved') statusBadge.classList.add('bg-label-success');
        else statusBadge.classList.add('bg-label-secondary');

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
    document.getElementById('save').addEventListener('click', function (e) {
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
  </script>

</body>

</html>