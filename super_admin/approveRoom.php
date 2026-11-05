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

$_SESSION['role'] = access_normalize_role($role);
$role = $_SESSION['role'];

// ✅ Restrict access
if (!access_can('special.room_reservation')) {
  $_SESSION['error'] = "Access Denied.";
  header("Location: ../index.php");
  exit();
}

if (isset($_GET['schedule_calendar'])) {
  header('Content-Type: application/json; charset=utf-8');

  $month = (string) ($_GET['month'] ?? '');
  $room = trim((string) ($_GET['room'] ?? 'all'));
  $validMonthFormat = preg_match('/^\d{4}-\d{2}$/', $month) === 1;
  $monthStart = $validMonthFormat ? DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01') : false;
  $dateErrors = DateTimeImmutable::getLastErrors();
  $validMonth = $monthStart !== false
    && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
    && $monthStart->format('Y-m') === $month;

  if (!$validMonth) {
    http_response_code(400);
    echo json_encode(['error' => 'Choose a valid month.']);
    exit();
  }

  $monthEnd = $monthStart->modify('first day of next month');
  $startDate = $monthStart->format('Y-m-d');
  $endDate = $monthEnd->format('Y-m-d');
  if ($room === 'all') {
    $calendarStatement = $conn->prepare("
      SELECT rr.id, rr.room, rr.reservation_date, rr.start_time, rr.end_time,
             rr.status, rr.purpose, rr.department, rr.participants,
             CASE WHEN rr.status = 'Pending' AND EXISTS (
               SELECT 1
               FROM room_reservations overlap
               WHERE overlap.id <> rr.id
                 AND overlap.room = rr.room
                 AND overlap.reservation_date = rr.reservation_date
                 AND overlap.status IN ('Pending', 'Approved')
                 AND overlap.start_time < rr.end_time
                 AND overlap.end_time > rr.start_time
             ) THEN 1 ELSE 0 END AS has_conflict,
             CONCAT(u.firstname, ' ', u.lastname) AS requested_by
      FROM room_reservations rr
      LEFT JOIN users u ON rr.user_id = u.id
      WHERE rr.reservation_date >= ? AND rr.reservation_date < ?
      ORDER BY rr.reservation_date, rr.room, rr.start_time
    ");
    $calendarStatement->bind_param('ss', $startDate, $endDate);
  } else {
    $calendarStatement = $conn->prepare("
      SELECT rr.id, rr.room, rr.reservation_date, rr.start_time, rr.end_time,
             rr.status, rr.purpose, rr.department, rr.participants,
             CASE WHEN rr.status = 'Pending' AND EXISTS (
               SELECT 1
               FROM room_reservations overlap
               WHERE overlap.id <> rr.id
                 AND overlap.room = rr.room
                 AND overlap.reservation_date = rr.reservation_date
                 AND overlap.status IN ('Pending', 'Approved')
                 AND overlap.start_time < rr.end_time
                 AND overlap.end_time > rr.start_time
             ) THEN 1 ELSE 0 END AS has_conflict,
             CONCAT(u.firstname, ' ', u.lastname) AS requested_by
      FROM room_reservations rr
      LEFT JOIN users u ON rr.user_id = u.id
      WHERE rr.room = ? AND rr.reservation_date >= ? AND rr.reservation_date < ?
      ORDER BY rr.reservation_date, rr.start_time
    ");
    $calendarStatement->bind_param('sss', $room, $startDate, $endDate);
  }
  $calendarStatement->execute();
  $calendarResult = $calendarStatement->get_result();
  $calendarReservations = [];
  while ($calendarReservation = $calendarResult->fetch_assoc()) {
    $calendarReservations[$calendarReservation['reservation_date']][] = [
      'id' => (int) $calendarReservation['id'],
      'room' => $calendarReservation['room'],
      'reservation_date' => $calendarReservation['reservation_date'],
      'start_time' => substr($calendarReservation['start_time'], 0, 5),
      'end_time' => substr($calendarReservation['end_time'], 0, 5),
      'status' => $calendarReservation['status'],
      'has_conflict' => (bool) $calendarReservation['has_conflict'],
      'purpose' => $calendarReservation['purpose'] ?? '',
      'department' => $calendarReservation['department'] ?? '',
      'participants' => (int) $calendarReservation['participants'],
      'requested_by' => trim($calendarReservation['requested_by'] ?? '') ?: 'Unknown',
    ];
  }
  echo json_encode($calendarReservations, JSON_THROW_ON_ERROR);
  $calendarStatement->close();
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
$sql = "SELECT rr.*,
        CASE WHEN rr.status = 'Pending' AND EXISTS (
          SELECT 1
          FROM room_reservations overlap
          WHERE overlap.id <> rr.id
            AND overlap.room = rr.room
            AND overlap.reservation_date = rr.reservation_date
            AND overlap.status IN ('Pending', 'Approved')
            AND overlap.start_time < rr.end_time
            AND overlap.end_time > rr.start_time
        ) THEN 1 ELSE 0 END AS has_conflict,
        CONCAT(u.firstname, ' ', u.lastname) AS requested_by
        FROM room_reservations rr 
        LEFT JOIN users u ON rr.user_id = u.id 
        ORDER BY rr.id DESC";
$result = $conn->query($sql);
$calendarRoomsResult = $conn->query("SELECT DISTINCT room FROM room_reservations WHERE room IS NOT NULL AND room <> '' ORDER BY room");
$calendarRooms = $calendarRoomsResult->fetch_all(MYSQLI_ASSOC);
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

    #reservationTable tbody tr.reservation-conflict > td {
      background-color: #fff3cd !important;
    }

    .conflict-badge { margin: .25rem 0 0 .15rem; }

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

    .schedule-card { margin-bottom: 1.5rem; }
    #roomScheduleModal .modal-dialog { max-width: 1120px; }
    #roomScheduleModal .modal-body { overflow-x: hidden; }
    #roomScheduleModal .schedule-card { margin: 0; border: 0; border-radius: 0; box-shadow: none; }
    .schedule-toolbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: .75rem;
      padding: 1rem 1.25rem;
      border-bottom: 1px solid #e4e6ef;
    }
    .schedule-toolbar h5 { margin: 0; color: #344054; font-weight: 700; }
    .schedule-toolbar p { margin: .2rem 0 0; color: #697586; font-size: .82rem; }
    .schedule-controls { display: flex; align-items: center; gap: .5rem; }
    .schedule-room-filter {
      width: min(320px, 52vw);
      min-height: 38px;
      border: 1px solid #dfe3eb;
      border-radius: 7px;
      padding: .4rem .65rem;
      background: #fff;
    }
    .schedule-refresh-state { color: #697586; font-size: .75rem; white-space: nowrap; }
    .schedule-body {
      display: grid;
      grid-template-columns: minmax(0, 1.15fr) minmax(280px, .85fr);
      gap: 1.25rem;
      padding: 1.25rem;
    }
    .schedule-monthbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: .75rem;
    }
    .schedule-monthbar h6 { margin: 0; color: #344054; font-weight: 700; }
    .schedule-month-controls { display: flex; gap: .35rem; }
    .schedule-month-controls button {
      width: 34px;
      height: 34px;
      display: grid;
      place-items: center;
      border: 1px solid #dfe3eb;
      border-radius: 7px;
      background: #fff;
      color: #697586;
    }
    .schedule-month-controls button:hover { border-color: #696cff; color: #696cff; }
    .schedule-weekdays, .schedule-days { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); }
    .schedule-weekdays span {
      padding: .45rem 0;
      color: #697586;
      text-align: center;
      font-size: .68rem;
      font-weight: 700;
      text-transform: uppercase;
    }
    .schedule-day {
      min-height: 54px;
      margin: 2px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 3px;
      border: 1px solid transparent;
      border-radius: 8px;
      background: transparent;
      color: #344054;
      font-size: .82rem;
    }
    .schedule-day:hover { background: #f3f4ff; }
    .schedule-day.is-outside { color: #adb5c0; }
    .schedule-day.is-today { border-color: #b7b9ff; font-weight: 700; }
    .schedule-day.is-selected { color: #fff; background: #696cff; font-weight: 700; }
    .schedule-day.is-selected:hover { background: #5558e8; }
    .schedule-day-dots { display: flex; gap: 3px; min-height: 6px; }
    .schedule-dot { width: 5px; height: 5px; border-radius: 50%; background: #f5b942; }
    .schedule-dot.approved { background: #71dd37; }
    .schedule-dot.rejected { background: #ff3e1d; }
    .schedule-dot.conflict { background: #ff9800; }
    .schedule-legend { display: flex; flex-wrap: wrap; gap: .8rem; margin-top: .75rem; color: #697586; font-size: .72rem; }
    .schedule-legend span { display: inline-flex; align-items: center; gap: .35rem; }
    .schedule-agenda { min-height: 270px; padding: 1rem; border: 1px solid #e4e6ef; border-radius: 9px; background: #fbfcfe; }
    .schedule-agenda h6 { margin: 0; color: #344054; font-weight: 700; }
    .schedule-agenda-date { margin: .2rem 0 .85rem; color: #697586; font-size: .75rem; }
    .schedule-agenda-list { display: grid; gap: .55rem; }
    .schedule-event {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: .6rem;
      width: 100%;
      padding: .7rem;
      border: 1px solid #e4e6ef;
      border-left: 3px solid #f5b942;
      border-radius: 7px;
      background: #fff;
      text-align: left;
    }
    .schedule-event.is-approved { border-left-color: #71dd37; }
    .schedule-event.is-rejected { border-left-color: #ff3e1d; }
    .schedule-event.is-conflict { border-color: #ffb74d; border-left-color: #ff9800; background: #fff8e6; }
    .schedule-event:hover { background: #f8f9ff; }
    .schedule-event-room { display: block; color: #344054; font-size: .78rem; font-weight: 700; }
    .schedule-event-meta { display: block; margin-top: .18rem; color: #697586; font-size: .7rem; }
    .schedule-event-status { flex-shrink: 0; }
    .schedule-empty { padding: 1.5rem .5rem; color: #697586; font-size: .8rem; text-align: center; }
    .schedule-loading { opacity: .6; pointer-events: none; }
    .schedule-error { color: #ff3e1d; }
    @media (max-width: 767.98px) {
      .schedule-body { grid-template-columns: 1fr; padding: .85rem; }
      .schedule-toolbar { align-items: stretch; }
      .schedule-controls { justify-content: space-between; }
      .schedule-room-filter { width: 100%; }
      .schedule-day { min-height: 44px; margin: 1px; }
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

      <div class="modal fade" id="roomScheduleModal" tabindex="-1" aria-labelledby="scheduleTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
          <div class="modal-content">
            <div class="modal-header">
              <div>
                <h5 class="modal-title fw-bold" id="scheduleTitle"><i class="bx bx-calendar me-1 text-primary"></i> Room Schedule</h5>
                <p class="mb-0 mt-1 text-muted small">Monitor room bookings and reservation status for the selected date.</p>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close room schedule"></button>
            </div>
            <div class="modal-body p-0">
      <section class="schedule-card" aria-labelledby="scheduleTitle">
        <div class="schedule-toolbar">
          <div>
            <h5>Schedule Overview</h5>
            <p>Pending, approved, and rejected reservations by room and date.</p>
          </div>
          <div class="schedule-controls">
            <label class="visually-hidden" for="scheduleRoomFilter">Filter calendar by room</label>
            <select class="schedule-room-filter" id="scheduleRoomFilter">
              <option value="all">All Rooms</option>
              <?php foreach ($calendarRooms as $calendarRoom): ?>
                <option value="<?= htmlspecialchars($calendarRoom['room'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($calendarRoom['room'], ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
            <span class="schedule-refresh-state" id="scheduleRefreshState" role="status" aria-live="polite">Live</span>
          </div>
        </div>
        <div class="schedule-body" id="roomSchedule" aria-busy="true">
          <div>
            <div class="schedule-monthbar">
              <h6 id="scheduleMonthLabel"><?= htmlspecialchars(date('F Y'), ENT_QUOTES, 'UTF-8') ?></h6>
              <div class="schedule-month-controls">
                <button type="button" id="schedulePrevMonth" aria-label="Previous month"><i class="bx bx-chevron-left"></i></button>
                <button type="button" id="scheduleNextMonth" aria-label="Next month"><i class="bx bx-chevron-right"></i></button>
              </div>
            </div>
            <div class="schedule-weekdays" aria-hidden="true">
              <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
            </div>
            <div class="schedule-days" id="scheduleDays" role="grid" aria-label="Reservation schedule calendar"></div>
            <div class="schedule-legend">
              <span><i class="schedule-dot"></i> Pending</span>
              <span><i class="schedule-dot approved"></i> Approved</span>
              <span><i class="schedule-dot rejected"></i> Rejected</span>
            </div>
          </div>
          <aside class="schedule-agenda" aria-live="polite">
            <h6 id="scheduleAgendaTitle">Select a date</h6>
            <div class="schedule-agenda-date" id="scheduleAgendaRoom"></div>
            <div class="schedule-agenda-list" id="scheduleAgendaList"></div>
          </aside>
        </div>
      </section>
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
                    <tr class="clickable-row <?= !empty($r['has_conflict']) ? 'table-warning reservation-conflict' : '' ?>" data-id="<?= $r['id']; ?>"
                      title="Select to open the room calendar for this request"
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
                        <?php if (!empty($r['has_conflict'])): ?>
                          <span class="badge bg-label-danger conflict-badge" title="Overlaps another pending or approved reservation">Conflict</span>
                        <?php endif; ?>
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
  <script src="../assets/vendor/js/menu.js"></script>
  <script src="../assets/js/main.js"></script>
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

        function refreshReservationTable() {
            const pageInfo = table.page.info();
            return $.get(location.href).done(function(data) {
                const newTbody = $(data).find('#reservationTable tbody').html();
                if (newTbody === undefined) return;

                table.destroy();
                $('#reservationTable tbody').html(newTbody);
                table = $('#reservationTable').DataTable({
                    "order": [[0, "desc"]],
                    "columnDefs": [{ "type": "num-fmt", "targets": 0 }],
                    "pageLength": 10,
                    "displayStart": pageInfo.start
                });
            });
        }

        const scheduleRoot = document.getElementById('roomSchedule');
        const scheduleModalElement = document.getElementById('roomScheduleModal');
        const scheduleModal = new bootstrap.Modal(scheduleModalElement);
        const scheduleRoomFilter = document.getElementById('scheduleRoomFilter');
        const scheduleDays = document.getElementById('scheduleDays');
        const scheduleMonthLabel = document.getElementById('scheduleMonthLabel');
        const scheduleAgendaTitle = document.getElementById('scheduleAgendaTitle');
        const scheduleAgendaRoom = document.getElementById('scheduleAgendaRoom');
        const scheduleAgendaList = document.getElementById('scheduleAgendaList');
        const scheduleRefreshState = document.getElementById('scheduleRefreshState');
        const today = new Date();
        let scheduleMonth = new Date(today.getFullYear(), today.getMonth(), 1);
        let selectedScheduleDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
        let scheduleReservations = {};
        let scheduleRequest;
        let scheduleRefreshTimer;

        function formatScheduleDate(dateString, options = { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }) {
            const [year, month, day] = dateString.split('-').map(Number);
            return new Date(year, month - 1, day).toLocaleDateString(undefined, options);
        }

        function showReservationDetails(data) {
            $('#modalReqId').text('#' + data.id);
            $('#modalRequested').text(data.requested_by || 'Unknown');
            $('#modalDept').text(data.department || 'N/A');
            $('#modalRoom').text(data.room || 'N/A');
            $('#modalParticipants').text((data.participants ?? 'N/A') + ' Persons');
            $('#modalDate').text(data.reservation_date);
            $('#modalTime').text(data.start_time + ' - ' + data.end_time);
            $('#modalPurpose').text(data.purpose || 'No details provided.');

            const statusClass = data.status === 'Approved'
                ? 'bg-label-success'
                : data.status === 'Pending' ? 'bg-label-warning' : 'bg-label-danger';
            $('#modalStatus').text(data.status).attr('class', 'badge status-badge px-3 py-2 rounded-pill ' + statusClass);
            new bootstrap.Modal(document.getElementById('reservationModal')).show();
        }

        function openScheduleForReservation(data) {
            const [year, month] = data.reservation_date.split('-').map(Number);
            selectedScheduleDate = data.reservation_date;
            scheduleMonth = new Date(year, month - 1, 1);
            scheduleRoomFilter.value = Array.from(scheduleRoomFilter.options)
                .some(option => option.value === data.room) ? data.room : 'all';
            scheduleRefreshState.textContent = 'Loading schedule…';
            scheduleModal.show();
            if (scheduleModalElement.classList.contains('show')) loadSchedule();
        }

        function renderScheduleAgenda() {
            const reservations = scheduleReservations[selectedScheduleDate] || [];
            scheduleAgendaTitle.textContent = formatScheduleDate(selectedScheduleDate);
            scheduleAgendaRoom.textContent = scheduleRoomFilter.value === 'all' ? 'All Rooms' : scheduleRoomFilter.value;
            scheduleAgendaList.replaceChildren();

            if (!reservations.length) {
                const empty = document.createElement('div');
                empty.className = 'schedule-empty';
                empty.textContent = 'No reservations scheduled for this date and room filter.';
                scheduleAgendaList.appendChild(empty);
                return;
            }

            reservations.forEach(reservation => {
                const button = document.createElement('button');
                button.type = 'button';
                const statusType = reservation.status.toLowerCase();
                button.className = `schedule-event is-${statusType}${reservation.has_conflict ? ' is-conflict' : ''}`;
                button.setAttribute('aria-label', `${reservation.room}, ${reservation.start_time} to ${reservation.end_time}, ${reservation.status}. Open reservation details.`);

                const details = document.createElement('span');
                const room = document.createElement('span');
                room.className = 'schedule-event-room';
                room.textContent = reservation.room;
                const meta = document.createElement('span');
                meta.className = 'schedule-event-meta';
                meta.textContent = `${reservation.start_time}–${reservation.end_time} · ${reservation.requested_by}`;
                details.append(room, meta);

                const status = document.createElement('span');
                const badgeType = reservation.has_conflict ? 'danger' : reservation.status === 'Approved' ? 'success'
                    : reservation.status === 'Pending' ? 'warning' : 'danger';
                status.className = `badge bg-label-${badgeType} status-badge schedule-event-status`;
                status.textContent = reservation.has_conflict ? 'Conflict · Pending' : reservation.status;
                button.append(details, status);
                button.addEventListener('click', () => showReservationDetails(reservation));
                scheduleAgendaList.appendChild(button);
            });
        }

        function renderScheduleCalendar() {
            const year = scheduleMonth.getFullYear();
            const month = scheduleMonth.getMonth();
            scheduleMonthLabel.textContent = scheduleMonth.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
            scheduleDays.replaceChildren();
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const cellCount = Math.ceil((firstDay + daysInMonth) / 7) * 7;
            const todayString = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

            for (let index = 0; index < cellCount; index++) {
                const date = new Date(year, month, index - firstDay + 1);
                const dateString = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
                const reservations = scheduleReservations[dateString] || [];
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'schedule-day';
                button.setAttribute('role', 'gridcell');
                button.setAttribute('aria-label', `${formatScheduleDate(dateString)}${reservations.length ? `, ${reservations.length} reservation${reservations.length === 1 ? '' : 's'}` : ', no reservations'}`);
                if (date.getMonth() !== month) button.classList.add('is-outside');
                if (dateString === todayString) button.classList.add('is-today');
                if (dateString === selectedScheduleDate) button.classList.add('is-selected');
                button.appendChild(document.createTextNode(String(date.getDate())));

                const dots = document.createElement('span');
                dots.className = 'schedule-day-dots';
                [...new Set(reservations.map(reservation => reservation.has_conflict ? 'conflict' : reservation.status.toLowerCase()))].slice(0, 3).forEach(status => {
                    const dot = document.createElement('i');
                    dot.className = `schedule-dot ${status}`;
                    dot.setAttribute('aria-hidden', 'true');
                    dots.appendChild(dot);
                });
                button.appendChild(dots);
                button.addEventListener('click', () => {
                    selectedScheduleDate = dateString;
                    if (date.getMonth() !== month || date.getFullYear() !== year) {
                        scheduleMonth = new Date(date.getFullYear(), date.getMonth(), 1);
                        loadSchedule();
                    } else {
                        renderScheduleCalendar();
                    }
                });
                scheduleDays.appendChild(button);
            }
            renderScheduleAgenda();
        }

        async function loadSchedule() {
            if (!scheduleModalElement.classList.contains('show')) return;
            if (scheduleRequest) scheduleRequest.abort();
            const request = new AbortController();
            scheduleRequest = request;
            scheduleRoot.setAttribute('aria-busy', 'true');
            scheduleRoot.classList.add('schedule-loading');
            scheduleRefreshState.textContent = 'Updating…';
            const month = `${scheduleMonth.getFullYear()}-${String(scheduleMonth.getMonth() + 1).padStart(2, '0')}`;
            const params = new URLSearchParams({
                schedule_calendar: '1',
                month,
                room: scheduleRoomFilter.value
            });

            try {
                const response = await fetch(`approveRoom.php?${params.toString()}`, {
                    headers: { 'Accept': 'application/json' },
                    signal: request.signal
                });
                if (!response.ok) throw new Error(`Schedule request failed (${response.status}).`);
                scheduleReservations = await response.json();
                renderScheduleCalendar();
                scheduleRefreshState.textContent = `Updated ${new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}`;
            } catch (error) {
                if (error.name === 'AbortError') return;
                scheduleRefreshState.textContent = 'Schedule unavailable';
                scheduleAgendaList.replaceChildren();
                const errorMessage = document.createElement('div');
                errorMessage.className = 'schedule-empty schedule-error';
                errorMessage.textContent = 'Unable to load the room schedule. Please try again.';
                scheduleAgendaList.appendChild(errorMessage);
                console.error('Room schedule update failed:', error);
            } finally {
                if (scheduleRequest === request) {
                    scheduleRoot.setAttribute('aria-busy', 'false');
                    scheduleRoot.classList.remove('schedule-loading');
                }
            }
        }

        document.getElementById('schedulePrevMonth').addEventListener('click', () => {
            scheduleMonth = new Date(scheduleMonth.getFullYear(), scheduleMonth.getMonth() - 1, 1);
            selectedScheduleDate = `${scheduleMonth.getFullYear()}-${String(scheduleMonth.getMonth() + 1).padStart(2, '0')}-01`;
            loadSchedule();
        });
        document.getElementById('scheduleNextMonth').addEventListener('click', () => {
            scheduleMonth = new Date(scheduleMonth.getFullYear(), scheduleMonth.getMonth() + 1, 1);
            selectedScheduleDate = `${scheduleMonth.getFullYear()}-${String(scheduleMonth.getMonth() + 1).padStart(2, '0')}-01`;
            loadSchedule();
        });
        scheduleRoomFilter.addEventListener('change', loadSchedule);
        scheduleModalElement.addEventListener('shown.bs.modal', () => {
            loadSchedule();
            scheduleRefreshTimer = window.setInterval(loadSchedule, 15000);
        });
        scheduleModalElement.addEventListener('hidden.bs.modal', () => {
            window.clearInterval(scheduleRefreshTimer);
            if (scheduleRequest) scheduleRequest.abort();
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

                        refreshReservationTable();
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
                            const rowData = row.data('json');
                            if (rowData) {
                                rowData.status = actionVerb;
                                row.attr('data-json', JSON.stringify(rowData));
                            }
                            
                            // Immediately sync the count
                            checkNewRequests(); 
                            refreshReservationTable();
                            loadSchedule();
                            
                            // Close modal if open
                            bootstrap.Modal.getInstance(document.getElementById('reservationModal'))?.hide();
                        }
                    }).fail(function() {
                        Swal.fire({ icon: 'error', title: 'Update failed', text: 'The reservation status could not be updated. Please try again.' });
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
            openScheduleForReservation(data);
        });
    });
</script>
</body>

</html>