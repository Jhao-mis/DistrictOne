<?php
require '../vendor/autoload.php';
include '../db.php';
require 'login_verification.php';

$username = $_SESSION['username'];

// ✅ Update last activity
date_default_timezone_set('Asia/Manila');
$now = date('Y-m-d H:i:s');
$updateActivity = $conn->prepare("UPDATE users SET last_activity = ? WHERE username = ?");
$updateActivity->bind_param("ss", $now, $username);
$updateActivity->execute();
$updateActivity->close();

// ✅ Fetch admin details
$query = $conn->prepare("SELECT id, firstname, lastname, role, department FROM users WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->bind_result($user_id, $firstname, $lastname, $role, $department);
$query->fetch();
$query->close();

$_SESSION['user_id'] = $user_id;
$_SESSION['role'] = $role;

// ✅ Restrict access
if (!in_array($role, ['Admin', 'Super Admin', 'mis'])) {
  $_SESSION['error'] = "Access Denied.";
  header("Location: ../login.php");
  exit();
}

// ✅ DEFINE SIDEBAR PATH HERE (Fixes line 107 error)
$sidebarPath = match ($role) {
  'User' => '../user/sidebar.php',
  'mis' => '../mis/sidebar.php',
  'Admin' => '../admin/sidebar.php',
  'Super Admin' => '../super_admin/sidebar.php',
  default => null
};

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'], $_POST['reservation_id'])) {
    $reservation_id = intval($_POST['reservation_id']);
    $action = $_POST['action'];
    $new_status = ($action === 'approve') ? 'Approved' : 'Rejected';
    $admin_name = trim("$firstname $lastname");

    $stmt = $conn->prepare("UPDATE room_reservations SET status = ?, approved_by = ? WHERE id = ?");
    $stmt->bind_param("ssi", $new_status, $admin_name, $reservation_id);

    if ($stmt->execute()) {
        // Check if request is AJAX
        if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo "success";
            exit(); // This prevents the redirect
        }
    }
}

// ✅ Fetch all reservations
$sql = "SELECT rr.*, CONCAT(u.firstname, ' ', u.lastname) AS requested_by 
        FROM room_reservations rr 
        LEFT JOIN users u ON rr.user_id = u.id 
        ORDER BY rr.id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Room Reservation Approval</title>

  <link rel="icon" type="image/x-icon" href="../assets/img/favicon/districtone.png" />
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
  <link rel="stylesheet" href="../assets/vendor/css/core.css" />
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

  <style>
    .card-stats {
      border-left: 5px solid #696cff;
    }

    .table-hover tbody tr {
      cursor: pointer;
      transition: 0.2s;
    }

    .table-hover tbody tr:hover {
      background-color: rgba(105, 108, 255, 0.08) !important;
    }

    .status-badge {
      font-weight: 700;
      text-transform: uppercase;
      font-size: 0.7rem;
      letter-spacing: 0.5px;
    }

    .btn-label-success {
      background-color: #e8fadf;
      color: #71dd37;
      border: none;
    }

    .btn-label-danger {
      background-color: #ffe5e5;
      color: #ff3e1d;
      border: none;
    }

    .btn-label-success:hover {
      background-color: #71dd37;
      color: #fff;
    }

    .btn-label-danger:hover {
      background-color: #ff3e1d;
      color: #fff;
    }

    .border-dashed {
      border-style: dashed !important;
      border-width: 2px !important;
    }

    #modalPurpose {
      line-height: 1.6;
      color: #566a7f;
      background-color: #f9fafb;
    }

    .swal2-container {
      z-index: 9999 !important;
    }

    .swal2-top-end {
      margin-top: 20px;
    }
  </style>
</head>

<body>
  <?php
  // Now $sidebarPath is guaranteed to be set
  if ($sidebarPath)
    include $sidebarPath;
  ?>

  <div class="content-wrapper">
    <div class="container-xxl flex-grow-1 container-p-y">

      <div class="row mb-4">
        <div class="col-md-4">
          <div class="card card-stats shadow-sm">
            <div class="card-body d-flex align-items-center">
              <div class="avatar flex-shrink-0 me-3">
                <span class="avatar-initial rounded bg-label-warning"><i class="bx bx-time"></i></span>
              </div>
              <div>
                <small class="d-block mb-1 text-muted">Awaiting Action</small>
                <h4 class="card-title mb-0" id="countPending">--</h4>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center border-bottom mb-3">
          <h5 class="mb-0 fw-bold">Room Reservation Approval Panel</h5>
        </div>

        <div class="card-body">
          <div class="table-responsive text-nowrap">
            <table class="table table-hover" id="reservationTable">
              <thead>
                <tr>
                  <th>Ticket #</th>
                  <th>Requester</th>
                  <th>Room</th>
                  <th>Schedule</th>
                  <th>Status</th>
                  <th class="text-center">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                  <?php while ($r = $result->fetch_assoc()): ?>
                    <tr class="clickable-row" data-id="<?= $r['id']; ?>"
                      data-json='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8'); ?>'>
                      <td><span class="fw-bold text-primary">#<?= $r['id']; ?></span></td>
                      <td>
                        <div class="d-flex flex-column">
                          <span class="fw-bold text-dark"><?= htmlspecialchars($r['requested_by'] ?? 'Unknown'); ?></span>
                          <small class="text-muted"><?= htmlspecialchars($r['department'] ?? 'N/A'); ?></small>
                        </div>
                      </td>
                      <td><span
                          class="badge bg-label-secondary text-primary border-primary"><?= htmlspecialchars($r['room']); ?></span>
                      </td>
                      <td>
                        <div class="small">
                          <i class="bx bx-calendar me-1"></i><?= date("M d, Y", strtotime($r['reservation_date'])); ?><br>
                          <i
                            class="bx bx-time me-1 text-warning"></i><?= date("h:i A", strtotime($r['start_time'])) . " - " . date("h:i A", strtotime($r['end_time'])); ?>
                        </div>
                      </td>
                      <td>
                        <?php $sCls = match ($r['status']) { 'Approved' => 'bg-label-success', 'Pending' => 'bg-label-warning', default => 'bg-label-danger'}; ?>
                        <span class="badge <?= $sCls ?> status-badge"><?= $r['status']; ?></span>
                      </td>
                      <td class="text-center">
                        <form method="POST" class="action-form">
                          <input type="hidden" name="reservation_id" value="<?= $r['id']; ?>">
                          <div class="d-flex justify-content-center gap-2">
                            <button type="button" class="btn btn-icon btn-label-success approve-btn"><i
                                class="bx bx-check"></i></button>
                            <button type="button" class="btn btn-icon btn-label-danger reject-btn"><i
                                class="bx bx-x"></i></button>
                          </div>
                        </form>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="reservationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content shadow-lg border-0">
        <div class="modal-header bg-label-primary p-4">
          <div class="d-flex align-items-center">
            <div class="avatar flex-shrink-0 me-3">
              <span class="avatar-initial rounded bg-primary"><i class="bx bx-receipt"></i></span>
            </div>
            <div>
              <h5 class="modal-title fw-bold mb-0">Reservation Details</h5>
              <small class="text-muted">Ticket <span id="modalReqId" class="fw-bold"></span></small>
            </div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body p-4">
          <div class="text-center mb-4">
            <span id="modalStatus" class="badge status-badge px-3 py-2 rounded-pill"></span>
          </div>

          <div class="row g-3">
            <div class="col-12">
              <label class="form-label text-uppercase fw-semibold small text-muted">Requested By</label>
              <div class="d-flex align-items-center p-3 border rounded bg-light">
                <i class="bx bx-user-circle fs-3 me-2 text-primary"></i>
                <div>
                  <h6 class="mb-0 fw-bold" id="modalRequested"></h6>
                  <small class="text-muted" id="modalDept"></small>
                </div>
              </div>
            </div>

            <div class="col-6">
              <label class="form-label text-uppercase fw-semibold small text-muted">Room</label>
              <div class="p-2 border rounded"><i class="bx bx-building-house me-1 text-info"></i> <span id="modalRoom"
                  class="fw-medium"></span></div>
            </div>
            <div class="col-6">
              <label class="form-label text-uppercase fw-semibold small text-muted">Participants</label>
              <div class="p-2 border rounded"><i class="bx bx-group me-1 text-info"></i> <span id="modalParticipants"
                  class="fw-medium"></span></div>
            </div>

            <div class="col-12">
              <label class="form-label text-uppercase fw-semibold small text-muted">Schedule</label>
              <div class="p-3 border rounded border-dashed bg-label-secondary">
                <div class="mb-1"><i class="bx bx-calendar me-2"></i><span id="modalDate" class="fw-bold"></span></div>
                <div><i class="bx bx-time-five me-2"></i><span id="modalTime"></span></div>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label text-uppercase fw-semibold small text-muted">Purpose</label>
              <div id="modalPurpose" class="p-3 border rounded text-dark italic"
                style="min-height: 80px; font-style: italic;"></div>
            </div>
          </div>
        </div>

        <div class="modal-footer bg-light d-flex justify-content-between p-3" id="modalActionFooter">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
          <!-- <div class="gap-2 d-flex">
                        <button type="button" class="btn btn-danger modal-reject-btn"><i class="bx bx-x me-1"></i> Reject</button>
                        <button type="button" class="btn btn-success modal-approve-btn"><i class="bx bx-check me-1"></i> Approve</button>
                    </div> -->
        </div>
      </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="../assets/vendor/js/bootstrap.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        // 1. Initialize DataTable (Changed to 'let' to allow re-initialization)
        let table = $('#reservationTable').DataTable({
            "order": [[0, "desc"]],
            "columnDefs": [{ "type": "num-fmt", "targets": 0 }],
            "pageLength": 10
        });

        // 2. Real-time Polling Logic
        let currentPendingCount = -1;

        function checkNewRequests() {
            $.ajax({
                url: 'fetchCount.php',
                method: 'GET',
                success: function(newCount) {
                    newCount = parseInt(newCount);
                    if (currentPendingCount === -1) { 
                        currentPendingCount = newCount; 
                        $('#countPending').text(newCount); 
                        return; 
                    }

                    if (newCount > currentPendingCount) {
                        // Notify the admin
                        Swal.fire({
                            toast: true, position: 'top-end', icon: 'info', title: 'New Request!',
                            text: 'Updating list...', showConfirmButton: false, timer: 2000
                        });

                        // ✅ SILENT TABLE REFRESH
                        $.get(location.href, function(data) {
                            // 1. Grab the current page/state
                            let pageInfo = table.page.info();

                            // 2. Extract the new table body HTML from the fetched data
                            let newTbody = $(data).find('#reservationTable tbody').html();

                            // 3. Destroy old table and inject new rows
                            table.destroy();
                            $('#reservationTable tbody').html(newTbody);

                            // 4. Re-initialize and return to the previous page
                            table = $('#reservationTable').DataTable({
                                "order": [[0, "desc"]],
                                "columnDefs": [{ "type": "num-fmt", "targets": 0 }],
                                "pageLength": 10,
                                "displayStart": pageInfo.start // Stays on the same page!
                            });
                        });
                    }
                    currentPendingCount = newCount;
                    $('#countPending').text(newCount);
                }
            });
        }
        
        // Shortened interval for snappier updates (3 seconds)
        setInterval(checkNewRequests, 3000);
        checkNewRequests();

        // 3. Optimized Action Handler (AJAX)
        function handleAction(btn, type) {
            const row = $(btn).closest('tr');
            const tId = row.find('input[name="reservation_id"]').val();
            const actionVerb = type === 'approve' ? 'Approved' : 'Rejected';

            Swal.fire({
                title: `Confirm ${type}?`,
                text: `Update Ticket #${tId} to ${actionVerb}?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: type === 'approve' ? '#71dd37' : '#ff3e1d',
                confirmButtonText: 'Yes, proceed'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'Updating...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

                    $.ajax({
                        url: 'approveRoom.php',
                        method: 'POST',
                        data: { reservation_id: tId, action: type },
                        success: function() {
                            Swal.fire({ icon: 'success', title: 'Updated!', text: `Ticket #${tId} is now ${actionVerb}`, timer: 1500, showConfirmButton: false });

                            // Update UI status badge
                            const statusBadge = row.find('.status-badge');
                            const newClass = type === 'approve' ? 'bg-label-success' : 'bg-label-danger';
                            statusBadge.text(actionVerb).attr('class', 'badge status-badge ' + newClass);
                            
                            // Immediately sync the count
                            checkNewRequests(); 
                            
                            // Close modal if open
                            bootstrap.Modal.getInstance(document.getElementById('reservationModal'))?.hide();
                        }
                    });
                }
            });
        }

        // 4. Event Delegation (Works on all pagination pages & newly added rows)
        $('#reservationTable tbody').on('click', '.approve-btn', function(e) {
            e.stopPropagation();
            handleAction(this, 'approve');
        });

        $('#reservationTable tbody').on('click', '.reject-btn', function(e) {
            e.stopPropagation();
            handleAction(this, 'reject');
        });

        $('#reservationTable tbody').on('click', 'tr.clickable-row', function(e) {
            if ($(e.target).closest('button, form').length) return;
            const data = $(this).data('json');
            if (!data) return;

            $('#modalReqId').text('#' + data.id);
            $('#modalRequested').text(data.requested_by);
            $('#modalDept').text(data.department || 'N/A');
            $('#modalRoom').text(data.room);
            $('#modalParticipants').text(data.participants + ' Persons');
            $('#modalDate').text(data.reservation_date);
            $('#modalTime').text(data.start_time + ' - ' + data.end_time);
            $('#modalPurpose').text(data.purpose || 'No details provided.');
            
            const sBadge = $('#modalStatus');
            sBadge.text(data.status).attr('class', 'badge px-3 py-2 rounded-pill ' + 
                (data.status === 'Approved' ? 'bg-label-success' : 
                 data.status === 'Pending' ? 'bg-label-warning' : 'bg-label-danger'));

            $('#modalActionFooter .gap-2').show();
            
            // Link modal buttons to the table row buttons
            $('.modal-approve-btn').off().on('click', () => { 
                handleAction($(`tr[data-id="${data.id}"] .approve-btn`), 'approve'); 
            });
            $('.modal-reject-btn').off().on('click', () => { 
                handleAction($(`tr[data-id="${data.id}"] .reject-btn`), 'reject'); 
            });

            new bootstrap.Modal('#reservationModal').show();
        });
    });
</script>
</body>

</html>