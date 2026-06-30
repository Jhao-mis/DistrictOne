<?php
session_start();
require '../vendor/autoload.php';
include '../db.php';
require 'login_verification.php';

$username = $_SESSION['username'];

// ✅ Fetch logged-in SuperAdmin info
$query = $conn->prepare("SELECT id, firstname, lastname, department FROM users WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->bind_result($user_id, $firstname, $lastname, $department);
$query->fetch();
$query->close();

if (!$user_id) {
  die("❌ Error: User not found.");
}

$performed_by = "$firstname $lastname";

// ✅ APPROVE Ticket (keep status as 'Pending')
if (isset($_POST['approve_ticket_id'])) {
  $ticket_id = intval($_POST['approve_ticket_id']);

  $update_sql = "UPDATE tickets 
                   SET admin_approved = 1 
                   WHERE id = ? AND status = 'Pending'";
  $stmt = $conn->prepare($update_sql);
  $stmt->bind_param("i", $ticket_id);

  if ($stmt->execute()) {
    // Log history
    $action = "Approved by $performed_by";
    $history_sql = "INSERT INTO ticket_history (ticket_id, action, action_by) VALUES (?, ?, ?)";
    $hstmt = $conn->prepare($history_sql);
    $hstmt->bind_param("isi", $ticket_id, $action, $user_id);
    $hstmt->execute();
    $hstmt->close();

    $msg = "✅ Ticket Approved Successfully";
  } else {
    $error = "❌ Error approving ticket: " . $conn->error;
  }

  $stmt->close();
}

// ✅ REJECT Ticket (change status to 'Rejected')
if (isset($_POST['reject_ticket_id'])) {
  $ticket_id = intval($_POST['reject_ticket_id']);

  $update_sql = "UPDATE tickets 
                   SET status = 'Rejected', admin_approved = 0 
                   WHERE id = ? AND status = 'Pending'";
  $stmt = $conn->prepare($update_sql);
  $stmt->bind_param("i", $ticket_id);

  if ($stmt->execute()) {
    // Log history
    $action = "Rejected by $performed_by";
    $hstmt = $conn->prepare("INSERT INTO ticket_history (ticket_id, action, action_by) VALUES (?, ?, ?)");
    $hstmt->bind_param("isi", $ticket_id, $action, $user_id);
    $hstmt->execute();
    $hstmt->close();

    $msg = "❌ Ticket Rejected Successfully";
  } else {
    $error = "❌ Error rejecting ticket: " . $conn->error;
  }

  $stmt->close();
}

// ✅ Fetch only pending tickets not yet approved
$sql = "SELECT t.id, t.subject, t.description, t.status, t.created_at,
               u.firstname, u.lastname, u.department
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        WHERE t.status = 'Pending' AND t.admin_approved = 0
        ORDER BY t.created_at DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/"
  data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>IT Service Request</title>
  <link rel="icon" type="image/x-icon" href="../assets/img/favicon/districtone.png" />
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
  <link rel="stylesheet" href="../assets/vendor/css/core.css" />
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />
  <link rel="stylesheet" href="../css/admin.css" />
  <link rel="stylesheet" href="./css/profile.css">
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <style>
    .btn-icon {
      border: none;
      background: transparent;
      cursor: pointer;
      font-size: 1.2rem;
      padding: 4px;
    }

    .action-form {
      display: inline-block;
      margin: 0 4px;
    }

    /* Ensure sidebar stays below modals and SweetAlerts */
    .sidebar,
    .layout-menu,
    #layout-menu {
      z-index: 1030 !important;
      /* Bootstrap modals start at 1050 */
    }

    /* Optional: make SweetAlert overlay always on top */
    .swal2-container {
      z-index: 20000 !important;
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
    <div class="container-xxl flex-grow-1 container-p-y">
      <div class="card p-3">
        <h4 class="card-header text-center mb-3">Tickets Pending Approval</h4>

        <?php if (isset($_GET['msg'])): ?>
          <div class="alert alert-success text-center">
            <?= $_GET['msg'] === 'approved' ? '✅ Ticket Approved Successfully' : ($_GET['msg'] === 'rejected' ? '❌ Ticket Rejected Successfully' : htmlspecialchars($_GET['msg'])); ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
          <div class="alert alert-danger text-center"><?= htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="table-responsive text-nowrap">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th>ID</th>
                <th>Submitted By</th>
                <th>Department</th>
                <th>Subject</th>
                <th>Status</th>
                <th>Created At</th>
                <th class="text-center">Action</th>
              </tr>
            </thead>

            <tbody>
              <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                  <tr data-ticket-id="<?= $row['id']; ?>" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Click to view details" data-ticket-subject="<?= htmlspecialchars($row['subject']); ?>"
                    data-ticket-description="<?= htmlspecialchars($row['description']); ?>"
                    data-ticket-status="<?= htmlspecialchars($row['status']); ?>"
                    data-ticket-department="<?= htmlspecialchars($row['department']); ?>"
                    data-ticket-user="<?= htmlspecialchars(trim($row['firstname'] . ' ' . $row['lastname'])); ?>"
                    data-ticket-date="<?= htmlspecialchars(date("M d, Y h:i A", strtotime($row['created_at']))); ?>">
                    <td><?= htmlspecialchars($row['id']); ?></td>
                    <td><?= htmlspecialchars(trim($row['firstname'] . ' ' . $row['lastname'])); ?></td>
                    <td><?= htmlspecialchars($row['department']); ?></td>
                    <td>
                      <?= htmlspecialchars(strlen($row['subject']) > 30 ? substr($row['subject'], 0, 30) . '…' : $row['subject']); ?>
                    </td>
                    <td><span class="badge bg-label-warning"><?= htmlspecialchars($row['status']); ?></span></td>
                    <td><?= htmlspecialchars(date("M d, Y h:i A", strtotime($row['created_at']))); ?></td>
                    <td class="text-center">

                      <!-- SWEET ALERT IMPLEMENTATION -->
                      <form class="action-form" method="POST" data-action="approve">
                        <input type="hidden" name="approve_ticket_id" value="<?= htmlspecialchars($row['id']); ?>">
                        <button type="submit" class="btn-icon text-success" title="Approve">
                          <i class="fa fa-check-circle"></i>
                        </button>
                      </form>

                      <form class="action-form" method="POST" data-action="reject">
                        <input type="hidden" name="reject_ticket_id" value="<?= htmlspecialchars($row['id']); ?>">
                        <button type="submit" class="btn-icon text-danger" title="Reject">
                          <i class="fa fa-times-circle"></i>
                        </button>
                      </form>

                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" class="text-center">✅ No pending tickets found.</td>
                </tr>
              <?php endif; ?>
            </tbody>

          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Ticket Details Modal -->
  <div class="modal fade" id="ticketModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" style="color: white">Ticket Details</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p><strong>Ticket Number:</strong> <span id="modalTicketId"></span></p>
          <p><strong>Subject:</strong> <span id="modalTicketSubject"></span></p>
          <p><strong>Status:</strong> <span id="modalTicketStatus"></span></p>
          <p><strong>Department:</strong> <span id="modalTicketDept"></span></p>
          <p><strong>Submitted By:</strong> <span id="modalTicketUser"></span></p>
          <p><strong>Created At:</strong> <span id="modalTicketDate"></span></p>
          <hr>
          <p><strong>Description:</strong></p>
          <p id="modalTicketDescription" class="border rounded p-2 bg-light"></p>
        </div>
      </div>
    </div>
  </div>

  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- Core JS -->
  <script src="../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../assets/vendor/js/bootstrap.js"></script>
  <script src="../assets/vendor/js/menu.js"></script>
  <script src="../assets/js/main.js"></script>

  <!-- SWEET ALERT SCRIPT HANDLER -->
  <script>
    $(document).ready(function () {
      // Prevent row click when pressing buttons inside it
      $('.action-form button').on('click', function (e) {
        e.stopPropagation();
      });

      // Handle SweetAlert confirmations
      $('.action-form').on('submit', function (e) {
        e.preventDefault(); // Prevent immediate submission
        e.stopPropagation(); // Prevent triggering modal

        const form = this;
        const actionType = $(this).data('action');
        const ticketId = $(this).find('input[type="hidden"]').val();
        const isApprove = actionType === 'approve';
        const title = isApprove ? 'Approve Ticket?' : 'Reject Ticket?';
        const text = isApprove
          ? 'This will mark the ticket as APPROVED.'
          : 'This will mark the ticket as REJECTED.';
        const icon = isApprove ? 'success' : 'warning';
        const confirmButton = isApprove ? 'Yes, Approve' : 'Yes, Reject';
        const confirmColor = isApprove ? '#28a745' : '#dc3545';

        Swal.fire({
          title: title,
          text: text,
          icon: icon,
          showCancelButton: true,
          confirmButtonText: confirmButton,
          confirmButtonColor: confirmColor,
          cancelButtonText: 'Cancel',
          reverseButtons: true,
        }).then((result) => {
          if (result.isConfirmed) {
            form.submit(); // Proceed with the PHP form
          }
        });
      });
    });
  </script>

  <script>
    $(function () {
      // Enable Bootstrap tooltips
      var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
      });
    });
  </script>

  <!-- SCRIPT FOR MODAL VIEW TICKET -->
  <script>
    $(document).ready(function () {
      $('tr[data-ticket-id]').click(function () {
        const row = $(this);

        $('#modalTicketId').text(row.data('ticket-id'));
        $('#modalTicketSubject').text(row.data('ticket-subject'));
        $('#modalTicketDescription').text(row.data('ticket-description'));
        $('#modalTicketStatus').text(row.data('ticket-status'));
        $('#modalTicketDept').text(row.data('ticket-department'));
        $('#modalTicketUser').text(row.data('ticket-user'));
        $('#modalTicketDate').text(row.data('ticket-date'));

        $('#ticketModal').modal('show');
      });
    });
  </script>


</body>

</html>