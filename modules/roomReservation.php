<?php

require '../vendor/autoload.php';
include '../db.php';
require 'login_verification.php';

$availableRooms = [
    'Training Room, 3rd Floor, CWD Main Building',
    'Conference Room, 2nd Floor, CWD Warehouse',
    'Conference Room, Operations Building, BPS Upper',
    'Roof Deck, Operations Building, BPS Upper',
];

// ==============================
// 🔍 AJAX ROOM AVAILABILITY CHECK
// ==============================
if (isset($_POST['check_availability'])) {

    header('Content-Type: application/json');

    $date = $_POST['date'];
    $start = $_POST['start'];
    $end = $_POST['end'];

    $reservedRooms = [];

    $stmt = $conn->prepare("
        SELECT room FROM room_reservations 
        WHERE reservation_date = ?
        AND status IN ('Pending', 'Approved')
        AND (start_time < ? AND end_time > ?)
    ");

    $stmt->bind_param("sss", $date, $end, $start);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $reservedRooms[] = $row['room'];
    }

    echo json_encode($reservedRooms);

    $stmt->close();
    $conn->close();
    exit();
}

// ==============================
// 🗓️ ROOM CALENDAR AVAILABILITY
// ==============================
if (isset($_GET['availability_calendar'])) {
    header('Content-Type: application/json; charset=utf-8');

    $month = (string) ($_GET['month'] ?? '');
    $room = (string) ($_GET['room'] ?? '');
    $validMonthFormat = preg_match('/^\d{4}-\d{2}$/', $month) === 1;
    $monthStart = $validMonthFormat ? DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01') : false;
    $dateErrors = DateTimeImmutable::getLastErrors();
    $validMonth = $monthStart !== false
        && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
        && $monthStart->format('Y-m') === $month;

    if (!$validMonth || ($room !== 'all' && !in_array($room, $availableRooms, true))) {
        http_response_code(400);
        echo json_encode(['error' => 'Choose a valid month and room.']);
        exit();
    }

    $monthEnd = $monthStart->modify('first day of next month');
    $startDate = $monthStart->format('Y-m-d');
    $endDate = $monthEnd->format('Y-m-d');
    if ($room === 'all') {
        $stmt = $conn->prepare("
            SELECT room, reservation_date, start_time, end_time, status, purpose
            FROM room_reservations
            WHERE reservation_date >= ?
              AND reservation_date < ?
              AND status IN ('Pending', 'Approved')
            ORDER BY reservation_date, room, start_time, end_time
        ");
        $stmt->bind_param('ss', $startDate, $endDate);
    } else {
        $stmt = $conn->prepare("
            SELECT room, reservation_date, start_time, end_time, status, purpose
            FROM room_reservations
            WHERE room = ?
              AND reservation_date >= ?
              AND reservation_date < ?
              AND status IN ('Pending', 'Approved')
            ORDER BY reservation_date, start_time, end_time
        ");
        $stmt->bind_param('sss', $room, $startDate, $endDate);
    }
    $stmt->execute();
    $reservationsByDate = [];
    $reservationResult = $stmt->get_result();

    while ($reservation = $reservationResult->fetch_assoc()) {
        $reservationsByDate[$reservation['reservation_date']][] = [
            'room' => $reservation['room'],
            'start' => substr($reservation['start_time'], 0, 5),
            'end' => substr($reservation['end_time'], 0, 5),
            'status' => $reservation['status'],
            'purpose' => $reservation['purpose'] ?? '',
        ];
    }

    echo json_encode($reservationsByDate);
    $stmt->close();
    $conn->close();
    exit();
}

// ==============================
// 🔔 ALERT HANDLING
// ==============================
$alert = null;

if (isset($_SESSION['alert'])) {
    $alert = $_SESSION['alert'];
    unset($_SESSION['alert']);
}

$username = $_SESSION['username'];

// 🔍 Fetch user details
$query = $conn->prepare("SELECT id, profile_picture, cover_photo, department, firstname, middlename, lastname, email, role 
                       FROM users WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->store_result();
$query->bind_result($user_id, $profile_picture, $cover_photo, $department, $firstname, $middlename, $lastname, $email, $user_role);
$query->fetch();
$query->close();

// 🔧 Pre-fill
$user_name = trim(implode(' ', array_filter([
    trim((string) $firstname),
    trim((string) $middlename),
    trim((string) $lastname),
], static function ($part) {
    return $part !== '';
})));
if ($user_name === '') {
    $user_name = $username;
}
$date_requested = date('Y-m-d');
$calendar_month = date('Y-m');
$calendar_date = date('Y-m-d');

// ==============================
// 🚀 FORM SUBMISSION
// ==============================
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $department = $_POST["department"];
    $department_other = trim($_POST["department_other"]);

    if ($department === "Other" && !empty($department_other)) {
        $department = $department_other;
    }

    $reservation_date = $_POST["reservation_date"];
    $start_time = $_POST["start_time"];
    $end_time = $_POST["end_time"];
    $num_participants = $_POST["participants"];
    $purpose = trim($_POST["purpose"]);
    $room = $_POST["room"];

    if (empty($purpose)) {
        die("❌ Error: Purpose field is empty.");
    }

    // ==============================
    // 🚫 CONFLICT CHECK (VERY IMPORTANT)
    // ==============================
    $check = $conn->prepare("
        SELECT id FROM room_reservations 
        WHERE room = ?
        AND reservation_date = ?
        AND status IN ('Pending', 'Approved')
        AND (start_time < ? AND end_time > ?)
    ");

    $check->bind_param("ssss", $room, $reservation_date, $end_time, $start_time);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => '❌ Room already booked for that schedule.'
        ];
        header("Location: roomReservation.php");
        exit();
    }

    // ==============================
    // ✅ INSERT
    // ==============================
    $stmt = $conn->prepare("INSERT INTO room_reservations 
        (user_id, department, reservation_date, start_time, end_time, participants, purpose, room, status, date_requested) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)");

    $stmt->bind_param(
        "issssisss",
        $user_id,
        $department,
        $reservation_date,
        $start_time,
        $end_time,
        $num_participants,
        $purpose,
        $room,
        $date_requested
    );

    if ($stmt->execute()) {
        $_SESSION['alert'] = [
            'type' => 'success',
            'message' => 'Room reservation submitted successfully!',
        ];
    } else {
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => 'Error: ' . $stmt->error,
        ];
    }

    $stmt->close();
    $conn->close();

    header("Location: roomReservation.php");
    exit();
}

// ==============================
// 📋 FETCH USER RESERVATIONS
// ==============================
$sql = "SELECT * FROM room_reservations WHERE user_id = ? ORDER BY date_requested DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$reservations = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();

?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Room Reservation</title>

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
    <link rel="stylesheet" href="../css/user.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <!-- Helpers -->
    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>

    <!-- SweetAlert2 (used for submission feedback) -->
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
            --tk-shadow: 0 1px 2px rgba(20,20,43,.04), 0 8px 24px -12px rgba(20,20,43,.10);
            --tk-shadow-lg: 0 20px 50px -18px rgba(20,20,43,.22);
            --tk-ease: cubic-bezier(.4,0,.2,1);
        }

        * { box-sizing: border-box; }

        .tk-page { animation: tk-fade-in .35s var(--tk-ease); }
        @keyframes tk-fade-in { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
        @media (prefers-reduced-motion: reduce) { .tk-page, .tk-card, .tk-btn, .tk-status { animation: none !important; transition: none !important; } }

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

        /* ── Buttons ────────────────────────────────────────────── */
        .tk-btn {
            display: inline-flex; align-items: center; gap: 7px;
            font-weight: 700; font-size: 13.5px;
            padding: 10px 18px;
            border-radius: 10px;
            border: 1.5px solid transparent;
            cursor: pointer;
            transition: all .15s var(--tk-ease);
            line-height: 1;
        }
        .tk-btn svg { width: 15px; height: 15px; flex-shrink: 0; }
        .tk-btn-primary { background: var(--tk-primary-dark); color: #fff; box-shadow: 0 6px 16px -8px rgba(78,150,240,.6); }
        .tk-btn-primary:hover { background: var(--tk-primary-deep); }
        .tk-btn-secondary { background: var(--tk-surface); border-color: var(--tk-border); color: var(--tk-text); }
        .tk-btn-secondary:hover { border-color: var(--tk-text-faint); color: var(--tk-text); }
        .tk-btn:focus-visible { outline: 2px solid var(--tk-primary); outline-offset: 2px; }

        .tk-btn-pill {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 12px; font-weight: 700;
            padding: 6px 12px;
            border-radius: 999px;
            border: 1.5px solid transparent;
            transition: all .15s var(--tk-ease);
            white-space: nowrap;
        }
        .tk-btn-pill svg { width: 12px; height: 12px; }
        .tk-btn-pill.is-action { background: var(--tk-primary-soft); color: var(--tk-primary-deep); border-color: transparent; }
        .tk-btn-pill.is-action:hover { background: var(--tk-primary-dark); color: #fff; }
        .tk-btn-pill.is-locked { background: var(--tk-bg); color: var(--tk-text-faint); cursor: not-allowed; border: 1.5px dashed var(--tk-border); }

        /* ── Card / table ───────────────────────────────────────── */
        .tk-card {
            background: var(--tk-surface);
            border: 1px solid var(--tk-border);
            border-radius: var(--tk-radius);
            box-shadow: var(--tk-shadow);
            overflow: hidden;
        }
        .tk-card-head {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid var(--tk-border);
        }
        .tk-card-head h6 { margin: 0; font-size: 14px; font-weight: 800; color: var(--tk-text); }
        .tk-count-pill { font-size: 11px; font-weight: 800; color: var(--tk-primary-deep); background: var(--tk-primary-soft); border-radius: 999px; padding: 3px 10px; }

        .tk-table { width: 100%; border-collapse: collapse; }
        .tk-table thead th {
            background: var(--tk-bg);
            font-size: 11px; text-transform: uppercase; letter-spacing: .5px; font-weight: 800;
            color: var(--tk-text-muted);
            padding: 12px 18px;
            text-align: left;
            border-bottom: 1px solid var(--tk-border);
            white-space: nowrap;
        }
        .tk-table tbody td {
            padding: 13px 18px;
            font-size: 13.5px;
            color: var(--tk-text);
            border-bottom: 1px solid var(--tk-border-soft, var(--tk-border));
            white-space: nowrap;
        }
        .tk-table tbody tr { cursor: pointer; transition: background .12s var(--tk-ease); }
        .tk-table tbody tr:hover { background: var(--tk-bg); }
        .tk-table tbody tr:last-child td { border-bottom: none; }

        .tk-status {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 11px; font-weight: 800; letter-spacing: .2px;
            padding: 4px 10px;
            border-radius: 999px;
        }
        .tk-status.approved { background: var(--tk-success-soft); color: var(--tk-success-deep); }
        .tk-status.pending { background: var(--tk-warning-soft); color: var(--tk-warning-deep); }
        .tk-status.rejected { background: var(--tk-room-soft); color: var(--tk-room-deep); }

        .tk-empty {
            text-align: center; padding: 50px 16px;
            color: var(--tk-text-faint); font-size: 13px;
        }
        .tk-empty svg { width: 34px; height: 34px; opacity: .4; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto; color: var(--tk-text-muted); }
        .tk-empty strong { display: block; color: var(--tk-text-muted); font-weight: 700; font-size: 13.5px; margin-bottom: 3px; }

        /* ── Offcanvas form ─────────────────────────────────────── */
        .event-sidebar { width: 420px; border-left: 1px solid var(--tk-border); }
        .event-sidebar .offcanvas-header {
            padding: 18px 22px;
            background: var(--tk-surface);
        }
        .event-sidebar .offcanvas-title { font-size: 16px; font-weight: 800; color: var(--tk-text); }
        .event-sidebar .offcanvas-body { padding: 20px 22px 26px; background: var(--tk-bg); }

        .reservation-form label {
            display: block;
            font-size: 12px; font-weight: 700;
            color: var(--tk-text-muted);
            margin: 16px 0 6px;
            text-transform: uppercase;
            letter-spacing: .3px;
        }
        .reservation-form label:first-of-type { margin-top: 0; }
        .reservation-form .form-control,
        .reservation-form .form-select,
        .reservation-form textarea {
            width: 100%;
            border: 1.5px solid var(--tk-border);
            border-radius: var(--tk-radius-sm);
            padding: 10px 12px;
            font-size: 13.5px;
            color: var(--tk-text);
            background: var(--tk-surface);
            transition: border-color .15s var(--tk-ease), box-shadow .15s var(--tk-ease);
        }
        .reservation-form .form-control:focus,
        .reservation-form .form-select:focus,
        .reservation-form textarea:focus {
            outline: none;
            border-color: var(--tk-primary);
            box-shadow: 0 0 0 3px var(--tk-primary-soft);
        }
        .reservation-form .form-control[readonly] { background: var(--tk-bg); color: var(--tk-text-muted); }
        .reservation-form textarea { resize: vertical; min-height: 80px; }

        /* ── Modal ──────────────────────────────────────────────── */
        #reservationModal .modal-content { border: none; border-radius: 18px; overflow: hidden; box-shadow: var(--tk-shadow-lg); }
        #reservationModal .modal-header {
            background: linear-gradient(135deg, var(--tk-primary) 0%, var(--tk-primary-dark) 100%);
            padding: 22px 26px;
            border-bottom: none;
        }
        #reservationModal .modal-title { color: #fff; font-size: 17px; font-weight: 800; letter-spacing: -.2px; }
        #reservationModal .btn-close { filter: brightness(0) invert(1); opacity: .85; }
        #reservationModal .btn-close:hover { opacity: 1; }
        #reservationModal .modal-body { padding: 24px 26px; }
        #reservationModal .modal-footer { border-top: 1px solid var(--tk-border); background: var(--tk-bg); padding: 14px 26px; }

        .ev-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px,1fr)); gap: 12px; margin-bottom: 18px; }
        .ev-item { background: var(--tk-bg); border: 1px solid var(--tk-border); border-radius: 11px; padding: 11px 13px; }
        .ev-label { font-size: 10.5px; text-transform: uppercase; letter-spacing: .6px; font-weight: 800; color: var(--tk-text-muted); margin-bottom: 4px; }
        .ev-value { font-size: 13.5px; font-weight: 700; color: var(--tk-text); word-break: break-word; }
        .ev-desc-label { font-size: 13px; font-weight: 800; color: var(--tk-text); margin-bottom: 9px; }
        .ev-desc {
            background: var(--tk-bg); border: 1px solid var(--tk-border); border-radius: 11px;
            padding: 13px 14px; font-size: 13.5px; line-height: 1.65; color: var(--tk-text);
            white-space: pre-wrap; word-wrap: break-word;
            overflow-y: auto; max-height: 150px; min-height: 60px;
        }

        .tk-modal-close {
            background: var(--tk-surface); border: 1.5px solid var(--tk-border); color: var(--tk-text);
            font-weight: 700; font-size: 13.5px; padding: 9px 18px; border-radius: 10px; cursor: pointer;
            transition: border-color .15s var(--tk-ease), color .15s var(--tk-ease);
        }
        .tk-modal-close:hover { border-color: var(--tk-primary); color: var(--tk-primary-deep); }

        /* ── Room availability calendar ─────────────────────────── */
        .availability-card { margin-bottom: 20px; }
        .availability-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding: 15px 20px;
            border-bottom: 1px solid var(--tk-border);
        }
        .availability-heading { display: flex; align-items: center; gap: 10px; }
        .availability-heading h6 { margin: 0; color: var(--tk-text); font-size: 14px; font-weight: 800; }
        .availability-heading p { margin: 3px 0 0; color: var(--tk-text-muted); font-size: 12px; }
        .availability-room {
            width: min(100%, 340px);
            min-height: 38px;
            border: 1px solid var(--tk-border);
            border-radius: 9px;
            padding: 7px 10px;
            color: var(--tk-text);
            background: var(--tk-surface);
            font-size: 12px;
        }
        .availability-body {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(260px, .85fr);
            gap: 18px;
            padding: 18px 20px 20px;
        }
        .availability-monthbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }
        .availability-monthbar h6 { margin: 0; color: var(--tk-text); font-size: 14px; font-weight: 800; }
        .availability-monthnav { display: flex; gap: 6px; }
        .availability-navbtn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border: 1px solid var(--tk-border);
            border-radius: 8px;
            color: var(--tk-text-muted);
            background: #fff;
            cursor: pointer;
        }
        .availability-navbtn:hover { color: var(--tk-primary-deep); border-color: var(--tk-primary); }
        .availability-weekdays,
        .availability-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
        }
        .availability-weekdays { margin-bottom: 5px; }
        .availability-weekdays span {
            padding: 6px 0;
            color: var(--tk-text-faint);
            text-align: center;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .availability-day {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            min-height: 48px;
            margin: 2px;
            border: 1px solid transparent;
            border-radius: 9px;
            color: var(--tk-text);
            background: transparent;
            cursor: pointer;
            font-size: 12px;
        }
        .availability-day:hover { background: var(--tk-primary-soft); }
        .availability-day.is-outside { color: #b6bdc7; }
        .availability-day.is-today { border-color: #b7d7ff; font-weight: 800; }
        .availability-day.is-selected { color: #fff; background: var(--tk-primary-dark); font-weight: 800; }
        .availability-day.is-selected:hover { background: var(--tk-primary-deep); }
        .availability-day[aria-disabled="true"] { cursor: default; }
        .availability-dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: var(--tk-room);
        }
        .availability-day.is-selected .availability-dot { background: #fff; }
        .availability-legend {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-top: 9px;
            color: var(--tk-text-muted);
            font-size: 11px;
        }
        .availability-detail {
            min-height: 250px;
            padding: 15px;
            border: 1px solid var(--tk-border);
            border-radius: 11px;
            background: #fbfcfe;
        }
        .availability-detail h6 { margin: 0; color: var(--tk-text); font-size: 13px; font-weight: 800; }
        .availability-detail-subtitle { margin: 4px 0 12px; color: var(--tk-text-muted); font-size: 11px; }
        .availability-list { display: grid; gap: 8px; }
        .availability-room-group { margin: 8px 0 0; color: var(--tk-text-muted); font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .availability-slot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 9px 10px;
            border: 1px solid var(--tk-border);
            border-radius: 8px;
            background: #fff;
            color: var(--tk-text);
            font-size: 11px;
        }
        button.availability-slot {
            width: 100%;
            text-align: left;
            cursor: pointer;
            font: inherit;
        }
        button.availability-slot:focus-visible {
            outline: 2px solid var(--tk-primary-dark);
            outline-offset: 2px;
        }
        .availability-slot.is-open { border-color: #cbeed6; background: var(--tk-success-soft); color: var(--tk-success-deep); }
        .availability-slot.is-busy { border-color: #f7d8d8; background: var(--tk-room-soft); color: var(--tk-room-deep); }
        .availability-slot-label { font-weight: 800; }
        .availability-event-detail {
            margin-top: 12px;
            padding: 12px;
            border: 1px solid #d6e7ff;
            border-radius: 9px;
            background: #f2f7ff;
        }
        .availability-event-detail[hidden] { display: none; }
        .availability-event-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            color: var(--tk-text);
            font-size: 11px;
            font-weight: 800;
        }
        .availability-event-status {
            padding: 3px 7px;
            border-radius: 999px;
            background: var(--tk-warning-soft);
            color: var(--tk-warning-deep);
            font-size: 9px;
            text-transform: uppercase;
        }
        .availability-event-status.is-approved { background: var(--tk-success-soft); color: var(--tk-success-deep); }
        .availability-event-room { margin-top: 8px; color: var(--tk-text); font-size: 11px; font-weight: 700; }
        .availability-event-time { margin-top: 8px; color: var(--tk-primary-deep); font-size: 11px; font-weight: 800; }
        .availability-event-purpose-label { margin-top: 10px; color: var(--tk-text-muted); font-size: 9px; font-weight: 800; text-transform: uppercase; }
        .availability-event-purpose { margin: 6px 0 0; color: var(--tk-text-muted); font-size: 12px; line-height: 1.5; white-space: pre-wrap; overflow-wrap: anywhere; }
        .availability-empty { padding: 18px 4px; color: var(--tk-text-muted); font-size: 12px; line-height: 1.5; }
        .availability-loading { opacity: .58; pointer-events: none; }
        .availability-error { color: var(--tk-room-deep); }
        @media (max-width: 767.98px) {
            .availability-body { grid-template-columns: 1fr; padding: 14px; }
            .availability-toolbar { align-items: stretch; padding: 14px; }
            .availability-room { width: 100%; }
        }
    </style>
</head>

<body>
    <?php
    $role = $_SESSION['role'];
    switch ($role) {
        case 'User':        include '../user/sidebar.php'; break;
        case 'mis':          include '../mis/sidebar.php'; break;
        case 'Admin':        include '../admin/sidebar.php'; break;
        case 'Super Admin':  include '../super_admin/sidebar.php'; break;
        default: echo "<p>Unauthorized role.</p>"; exit;
    }
    ?>

    <div class="content-wrapper">
        <div class="container-xxl flex-grow-1 container-p-y tk-page">

            <div class="tk-header">
                <div>
                    <h2>Room Reservation Dashboard</h2>
                    <p>Check room schedules and available times before submitting a reservation.</p>
                </div>
                <button class="tk-btn tk-btn-primary" id="app-calendar-sidebar" data-bs-toggle="offcanvas"
                    data-bs-target="#roomReservationSidebar" aria-controls="roomReservationSidebar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    Create Reservation
                </button>
            </div>

            <section class="tk-card availability-card" aria-labelledby="availabilityTitle">
                <div class="availability-toolbar">
                    <div class="availability-heading">
                        <i class="bx bx-calendar" style="font-size:20px;color:var(--tk-primary-deep)" aria-hidden="true"></i>
                        <div>
                            <h6 id="availabilityTitle">Room Availability</h6>
                            <p>Choose a room and date to review reservations and available times.</p>
                        </div>
                    </div>
                    <label class="visually-hidden" for="availabilityRoom">Room to check</label>
                    <select class="availability-room" id="availabilityRoom">
                        <option value="all" selected>All Rooms</option>
                        <?php foreach ($availableRooms as $index => $availableRoom): ?>
                            <option value="<?= htmlspecialchars($availableRoom, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($availableRoom, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="availability-body" id="availabilityCalendar" aria-busy="true">
                    <div>
                        <div class="availability-monthbar">
                            <h6 id="availabilityMonthLabel"><?= htmlspecialchars(date('F Y'), ENT_QUOTES, 'UTF-8') ?></h6>
                            <div class="availability-monthnav">
                                <button type="button" class="availability-navbtn" id="availabilityPrev" aria-label="Previous month"><i class="bx bx-chevron-left"></i></button>
                                <button type="button" class="availability-navbtn" id="availabilityNext" aria-label="Next month"><i class="bx bx-chevron-right"></i></button>
                            </div>
                        </div>
                        <div class="availability-weekdays" aria-hidden="true">
                            <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
                        </div>
                        <div class="availability-grid" id="availabilityDays" role="grid" aria-label="Room availability calendar"></div>
                        <div class="availability-legend"><span class="availability-dot" aria-hidden="true"></span> Reservations on this date</div>
                    </div>
                    <aside class="availability-detail" aria-live="polite">
                        <h6 id="availabilityDateLabel">Select a date</h6>
                        <p class="availability-detail-subtitle" id="availabilityRoomLabel"></p>
                        <div class="availability-list" id="availabilitySlots"></div>
                        <div class="availability-event-detail" id="availabilityEventDetail" hidden>
                            <div class="availability-event-heading">
                                <span>Reservation details</span>
                                <span class="availability-event-status" id="availabilityEventStatus"></span>
                            </div>
                            <div class="availability-event-room" id="availabilityEventRoom"></div>
                            <div class="availability-event-time" id="availabilityEventTime"></div>
                            <div class="availability-event-purpose-label">Event / purpose</div>
                            <p class="availability-event-purpose" id="availabilityEventPurpose"></p>
                        </div>
                    </aside>
                </div>
            </section>

            <!-- Room Reservation Offcanvas -->
            <div class="offcanvas offcanvas-end event-sidebar" tabindex="-1" id="roomReservationSidebar"
                aria-labelledby="roomReservationSidebarLabel">
                <div class="offcanvas-header border-bottom">
                    <h5 class="offcanvas-title" id="roomReservationSidebarLabel">Room Reservation Request</h5>
                    <!-- <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button> -->
                </div>
                <div class="offcanvas-body">
                    <form method="POST" class="reservation-form">
                        <label>Date Requested</label>
                        <input type="text" value="<?php echo htmlspecialchars($date_requested); ?>" readonly class="form-control">

                        <label>Requested By</label>
                        <input type="text" value="<?php echo htmlspecialchars($user_name); ?>" readonly class="form-control">

                        <label>Department / Committee Requesting</label>
                        <select name="department" class="form-select" required>
                            <option value="Office of the General Manager">Office of the General Manager</option>
                            <option value="Administrative Department">Administrative Department</option>
                            <option value="Finance Department">Finance Department</option>
                            <option value="Commercial Department">Commercial Department</option>
                            <option value="Technical Services Department">Technical Services Department</option>
                            <option value="Operations Department">Operations Department</option>
                            <option value="Other">Other (Input Below)</option>
                        </select>

                        <div id="other-department-container" style="display: none;">
                            <label>Specify Other Department / Committee</label>
                            <input type="text" name="department_other" class="form-control" placeholder="Enter other department">
                        </div>

                        <label>Reservation Date</label>
                        <input type="date" name="reservation_date" class="form-control" required>

                        <label>Start Time</label>
                        <input type="time" name="start_time" class="form-control" required>

                        <label>End Time</label>
                        <input type="time" name="end_time" class="form-control" required>

                        <label>Number of Participants</label>
                        <input type="number" name="participants" min="1" class="form-control" required>

                        <label>Purpose of Meeting</label>
                        <textarea name="purpose" class="form-control" placeholder="Enter meeting purpose" required></textarea>

                        <label>Select Room</label>
                        <select name="room" class="form-select" required>
                            <option value="" disabled selected>Select Room</option>
                            <?php foreach ($availableRooms as $availableRoom): ?>
                                <option value="<?= htmlspecialchars($availableRoom, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($availableRoom, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>

                        <div class="d-flex justify-content-sm-between justify-content-start mt-4 gap-2">
                            <button type="submit" name="submit_reservation" class="tk-btn tk-btn-primary">Submit Request</button>
                            <button type="reset" class="tk-btn tk-btn-secondary">Reset</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="tk-card mt-4">
                <div class="tk-card-head">
                    <h6>My Reservations</h6>
                    <span class="tk-count-pill"><?= count($reservations); ?></span>
                </div>
                <div class="table-responsive">
                    <?php if (count($reservations) > 0): ?>
                        <table class="tk-table">
                            <thead>
                                <tr>
                                    <th>Date Requested</th>
                                    <th>Reservation Date</th>
                                    <th>Time</th>
                                    <th>Room</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations as $reservation): ?>
                                    <?php $statusLower = strtolower($reservation['status']); ?>
                                    <tr data-reservation='<?= json_encode($reservation, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                                        <td><?= htmlspecialchars(date('Y-m-d', strtotime($reservation['date_requested']))); ?></td>
                                        <td><?= htmlspecialchars($reservation['reservation_date']); ?></td>
                                        <td><?= htmlspecialchars($reservation['start_time'] . " – " . $reservation['end_time']); ?></td>
                                        <td><?= htmlspecialchars($reservation['room']); ?></td>
                                        <td>
                                            <?php if ($statusLower === 'approved'): ?>
                                                <span class="tk-status approved">Approved</span>
                                            <?php elseif ($statusLower === 'pending'): ?>
                                                <span class="tk-status pending">Pending</span>
                                            <?php else: ?>
                                                <span class="tk-status rejected">Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($statusLower === 'approved'): ?>
                                                <a href="../modules/room/reservation_pdf.php?id=<?= $reservation['id']; ?>"
                                                   target="_blank" class="tk-btn-pill is-action">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 19h16"/></svg>
                                                    PDF
                                                </a>
                                            <?php else: ?>
                                                <span class="tk-btn-pill is-locked">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                                                    Locked
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="tk-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <strong>No reservations yet</strong>
                            Create your first room reservation to see it listed here.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <!-- 📋 Reservation Details Modal -->
    <div class="modal fade" id="reservationModal" tabindex="-1" aria-labelledby="reservationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reservationModalLabel">Reservation Details</h5>
                    <!-- <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button> -->
                </div>

                <div class="modal-body">
                    <div class="ev-grid">
                        <div class="ev-item">
                            <div class="ev-label">Request #</div>
                            <div class="ev-value" id="modalReqId"></div>
                        </div>
                        <div class="ev-item">
                            <div class="ev-label">Status</div>
                            <div class="ev-value"><span id="modalStatus" class="tk-status"></span></div>
                        </div>
                        <div class="ev-item">
                            <div class="ev-label">Requested By</div>
                            <div class="ev-value" id="modalRequested"></div>
                        </div>
                        <div class="ev-item">
                            <div class="ev-label">Department</div>
                            <div class="ev-value" id="modalDept"></div>
                        </div>
                        <div class="ev-item">
                            <div class="ev-label">Date Requested</div>
                            <div class="ev-value" id="modalDateRequested"></div>
                        </div>
                        <div class="ev-item">
                            <div class="ev-label">Reservation Date</div>
                            <div class="ev-value" id="modalReservationDate"></div>
                        </div>
                        <div class="ev-item">
                            <div class="ev-label">Time</div>
                            <div class="ev-value" id="modalTime"></div>
                        </div>
                        <div class="ev-item">
                            <div class="ev-label">Room</div>
                            <div class="ev-value" id="modalRoom"></div>
                        </div>
                        <div class="ev-item">
                            <div class="ev-label">Participants</div>
                            <div class="ev-value" id="modalParticipants"></div>
                        </div>
                    </div>

                    <div class="ev-desc-label">Purpose</div>
                    <div class="ev-desc" id="modalPurpose"></div>
                </div>

                <div class="modal-footer">
                    <button class="tk-modal-close" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/js/main.js"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {

            // ── Show/hide "Other department" field ─────────────────
            const departmentSelect = document.querySelector("select[name='department']");
            const otherContainer = document.getElementById("other-department-container");
            departmentSelect.addEventListener("change", function () {
                otherContainer.style.display = this.value === "Other" ? "block" : "none";
            });

            // ── Live room availability check ────────────────────────
            const dateInput = document.querySelector("input[name='reservation_date']");
            const startTimeInput = document.querySelector("input[name='start_time']");
            const endTimeInput = document.querySelector("input[name='end_time']");
            const roomSelect = document.querySelector("select[name='room']");

            function checkAvailability() {
                const date = dateInput.value;
                const start = startTimeInput.value;
                const end = endTimeInput.value;

                if (!date || !start || !end) return;
                if (end <= start) return;

                fetch('roomReservation.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `check_availability=1&date=${encodeURIComponent(date)}&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`
                })
                    .then(response => response.json())
                    .then(reservedRooms => {
                        Array.from(roomSelect.options).forEach(option => {
                            if (!option.value) return;

                            const roomName = option.value;
                            const isReserved = reservedRooms.includes(roomName);

                            option.disabled = isReserved;
                            option.textContent = isReserved ? roomName + ' (Not Available)' : roomName;
                            option.style.color = isReserved ? '#c0392b' : '';
                        });
                    })
                    .catch(err => console.error("Availability check error:", err));
            }

            dateInput.addEventListener('change', checkAvailability);
            startTimeInput.addEventListener('change', checkAvailability);
            endTimeInput.addEventListener('change', checkAvailability);

            // ── Room availability calendar ─────────────────────────
            const calendar = document.getElementById('availabilityCalendar');
            const calendarRoom = document.getElementById('availabilityRoom');
            const calendarDays = document.getElementById('availabilityDays');
            const calendarMonthLabel = document.getElementById('availabilityMonthLabel');
            const availabilityDateLabel = document.getElementById('availabilityDateLabel');
            const availabilityRoomLabel = document.getElementById('availabilityRoomLabel');
            const availabilitySlots = document.getElementById('availabilitySlots');
            const availabilityEventDetail = document.getElementById('availabilityEventDetail');
            const availabilityEventStatus = document.getElementById('availabilityEventStatus');
            const availabilityEventRoom = document.getElementById('availabilityEventRoom');
            const availabilityEventTime = document.getElementById('availabilityEventTime');
            const availabilityEventPurpose = document.getElementById('availabilityEventPurpose');
            const today = new Date();
            let visibleMonth = new Date(today.getFullYear(), today.getMonth(), 1);
            let selectedDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
            let reservationsByDate = {};
            let calendarRequest;

            function formatCalendarDate(dateString, options = { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }) {
                const [year, month, day] = dateString.split('-').map(Number);
                return new Date(year, month - 1, day).toLocaleDateString(undefined, options);
            }

            function appendAvailabilitySlot(label, time, type, reservation = null) {
                const slot = document.createElement(reservation ? 'button' : 'div');
                if (reservation) {
                    slot.type = 'button';
                    slot.setAttribute('aria-label', `${reservation.room}, ${reservation.start} to ${reservation.end}, ${reservation.status}. Select to view reservation details.`);
                    slot.addEventListener('click', () => {
                        availabilityEventDetail.hidden = false;
                        availabilityEventStatus.textContent = reservation.status;
                        availabilityEventStatus.classList.toggle('is-approved', reservation.status === 'Approved');
                        availabilityEventRoom.textContent = reservation.room;
                        availabilityEventTime.textContent = `${reservation.start} – ${reservation.end}`;
                        availabilityEventPurpose.textContent = reservation.purpose || 'No purpose provided.';
                    });
                }
                slot.className = `availability-slot ${type}`;
                const labelElement = document.createElement('span');
                labelElement.className = 'availability-slot-label';
                labelElement.textContent = label;
                const timeElement = document.createElement('span');
                timeElement.textContent = time;
                slot.append(labelElement, timeElement);
                availabilitySlots.appendChild(slot);
            }

            function renderSelectedDate() {
                const dateReservations = reservationsByDate[selectedDate] || [];
                availabilityDateLabel.textContent = formatCalendarDate(selectedDate);
                availabilityRoomLabel.textContent = calendarRoom.value === 'all' ? 'All Rooms' : calendarRoom.value;
                availabilitySlots.replaceChildren();
                availabilityEventDetail.hidden = true;
                availabilityEventStatus.classList.remove('is-approved');

                if (!dateReservations.length) {
                    const empty = document.createElement('div');
                    empty.className = 'availability-empty';
                    empty.textContent = calendarRoom.value === 'all'
                        ? 'No pending or approved reservations are recorded for any room on this date.'
                        : 'No pending or approved reservations are recorded for this room on this date.';
                    availabilitySlots.appendChild(empty);
                    return;
                }

                const reservationsByRoom = dateReservations.reduce((groups, reservation) => {
                    if (!groups[reservation.room]) groups[reservation.room] = [];
                    groups[reservation.room].push(reservation);
                    return groups;
                }, {});

                Object.entries(reservationsByRoom).forEach(([roomName, roomReservations]) => {
                    if (calendarRoom.value === 'all') {
                        const roomHeading = document.createElement('div');
                        roomHeading.className = 'availability-room-group';
                        roomHeading.textContent = roomName;
                        availabilitySlots.appendChild(roomHeading);
                    }

                    let previousEnd = null;
                    roomReservations.forEach(reservation => {
                        const [startHour, startMinute] = reservation.start.split(':').map(Number);
                        const start = startHour * 60 + startMinute;
                        const [endHour, endMinute] = reservation.end.split(':').map(Number);
                        const end = endHour * 60 + endMinute;
                        if (previousEnd !== null && start > previousEnd) {
                            const endTime = `${String(Math.floor(previousEnd / 60)).padStart(2, '0')}:${String(previousEnd % 60).padStart(2, '0')}`;
                            appendAvailabilitySlot('Available', `${endTime} – ${reservation.start}`, 'is-open');
                        }

                        appendAvailabilitySlot(
                            'Reserved · ' + reservation.status,
                            `${reservation.start} – ${reservation.end}`,
                            'is-busy',
                            reservation
                        );
                        previousEnd = Math.max(previousEnd ?? end, end);
                    });
                });

            }

            function renderCalendar() {
                const year = visibleMonth.getFullYear();
                const month = visibleMonth.getMonth();
                calendarMonthLabel.textContent = visibleMonth.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
                calendarDays.replaceChildren();

                const firstDay = new Date(year, month, 1).getDay();
                const daysInMonth = new Date(year, month + 1, 0).getDate();
                const cellCount = Math.ceil((firstDay + daysInMonth) / 7) * 7;

                for (let index = 0; index < cellCount; index++) {
                    const date = new Date(year, month, index - firstDay + 1);
                    const dateString = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
                    const dayReservations = reservationsByDate[dateString] || [];
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'availability-day';
                    button.setAttribute('role', 'gridcell');
                    button.setAttribute('aria-label', `${formatCalendarDate(dateString)}${dayReservations.length ? `, ${dayReservations.length} reservation${dayReservations.length === 1 ? '' : 's'}` : ', no reservations'}`);
                    if (date.getMonth() !== month) button.classList.add('is-outside');
                    if (dateString === selectedDate) button.classList.add('is-selected');
                    if (dateString === `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`) button.classList.add('is-today');
                    button.appendChild(document.createTextNode(String(date.getDate())));
                    if (dayReservations.length) {
                        const dot = document.createElement('span');
                        dot.className = 'availability-dot';
                        dot.setAttribute('aria-hidden', 'true');
                        button.appendChild(dot);
                    }
                    button.addEventListener('click', () => {
                        selectedDate = dateString;
                        dateInput.value = selectedDate;
                        if (date.getMonth() !== month || date.getFullYear() !== year) {
                            visibleMonth = new Date(date.getFullYear(), date.getMonth(), 1);
                            loadCalendarAvailability();
                            return;
                        }
                        renderCalendar();
                        renderSelectedDate();
                        checkAvailability();
                    });
                    calendarDays.appendChild(button);
                }
                renderSelectedDate();
            }

            async function loadCalendarAvailability() {
                if (calendarRequest) calendarRequest.abort();
                const request = new AbortController();
                calendarRequest = request;
                calendar.setAttribute('aria-busy', 'true');
                calendar.classList.add('availability-loading');
                availabilitySlots.replaceChildren();
                const loading = document.createElement('div');
                loading.className = 'availability-empty';
                loading.textContent = 'Loading room schedule…';
                availabilitySlots.appendChild(loading);

                const month = `${visibleMonth.getFullYear()}-${String(visibleMonth.getMonth() + 1).padStart(2, '0')}`;
                const params = new URLSearchParams({
                    availability_calendar: '1',
                    month,
                    room: calendarRoom.value
                });

                try {
                    const response = await fetch(`roomReservation.php?${params.toString()}`, {
                        headers: { 'Accept': 'application/json' },
                        signal: request.signal
                    });
                    if (!response.ok) throw new Error(`Calendar request failed (${response.status}).`);
                    reservationsByDate = await response.json();
                    renderCalendar();
                } catch (error) {
                    if (error.name === 'AbortError') return;
                    availabilitySlots.replaceChildren();
                    const errorMessage = document.createElement('div');
                    errorMessage.className = 'availability-empty availability-error';
                    errorMessage.textContent = 'Unable to load availability. Please try again.';
                    availabilitySlots.appendChild(errorMessage);
                    console.error('Room availability calendar error:', error);
                } finally {
                    if (calendarRequest === request) {
                        calendar.setAttribute('aria-busy', 'false');
                        calendar.classList.remove('availability-loading');
                    }
                }
            }

            document.getElementById('availabilityPrev').addEventListener('click', () => {
                visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() - 1, 1);
                selectedDate = `${visibleMonth.getFullYear()}-${String(visibleMonth.getMonth() + 1).padStart(2, '0')}-01`;
                dateInput.value = selectedDate;
                loadCalendarAvailability();
                checkAvailability();
            });
            document.getElementById('availabilityNext').addEventListener('click', () => {
                visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() + 1, 1);
                selectedDate = `${visibleMonth.getFullYear()}-${String(visibleMonth.getMonth() + 1).padStart(2, '0')}-01`;
                dateInput.value = selectedDate;
                loadCalendarAvailability();
                checkAvailability();
            });
            calendarRoom.addEventListener('change', () => {
                if (calendarRoom.value !== 'all') roomSelect.value = calendarRoom.value;
                checkAvailability();
                loadCalendarAvailability();
            });
            dateInput.addEventListener('change', () => {
                if (!dateInput.value) return;
                const [year, month] = dateInput.value.split('-').map(Number);
                selectedDate = dateInput.value;
                if (visibleMonth.getFullYear() !== year || visibleMonth.getMonth() !== month - 1) {
                    visibleMonth = new Date(year, month - 1, 1);
                    loadCalendarAvailability();
                } else {
                    renderCalendar();
                }
            });

            roomSelect.addEventListener('change', () => {
                if (roomSelect.value) {
                    calendarRoom.value = roomSelect.value;
                    loadCalendarAvailability();
                }
            });
            loadCalendarAvailability();

            // ── Reservation details modal ───────────────────────────
            const statusClass = { Approved: 'approved', Pending: 'pending', Rejected: 'rejected' };

            document.querySelectorAll('.tk-table tbody tr').forEach(row => {
                row.addEventListener('click', function (e) {
                    if (e.target.closest('a') || e.target.closest('button')) return;

                    const reservation = JSON.parse(this.dataset.reservation);

                    document.getElementById('modalReqId').textContent = reservation.id;
                    document.getElementById('modalRequested').textContent = reservation.requested_by ?? user_name_fallback();
                    document.getElementById('modalDept').textContent = reservation.department;
                    document.getElementById('modalPurpose').textContent = reservation.purpose;
                    document.getElementById('modalDateRequested').textContent = reservation.date_requested;
                    document.getElementById('modalReservationDate').textContent = reservation.reservation_date;
                    document.getElementById('modalTime').textContent = `${reservation.start_time} – ${reservation.end_time}`;
                    document.getElementById('modalRoom').textContent = reservation.room;
                    document.getElementById('modalParticipants').textContent = reservation.participants;

                    const statusElem = document.getElementById('modalStatus');
                    statusElem.textContent = reservation.status;
                    statusElem.className = 'tk-status ' + (statusClass[reservation.status] || 'rejected');

                    new bootstrap.Modal(document.getElementById('reservationModal')).show();
                });
            });

            function user_name_fallback() {
                return document.querySelector("input[readonly]")?.value || '';
            }
        });
    </script>

    <?php if ($alert): ?>
    <script>
        Swal.fire({
            icon: <?= json_encode($alert['type']) ?>,
            title: <?= json_encode($alert['type'] === 'success' ? 'Success!' : 'Oops...') ?>,
            text: <?= json_encode($alert['message']) ?>,
            confirmButtonColor: '#4e96f0'
        });
    </script>
    <?php endif; ?>
</body>

</html>