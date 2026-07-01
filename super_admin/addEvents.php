<?php
include '../db.php';
require '../vendor/autoload.php';
require 'login_verification.php';


$username = $_SESSION['username'];

// Fetch user details
$query = $conn->prepare("SELECT id, profile_picture, cover_photo, department, firstname, middlename, lastname, email FROM users WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->store_result();
$query->bind_result($user_id, $profile_picture, $cover_photo, $department, $firstname, $middlename, $lastname, $email);
$query->fetch();
$query->close();

// Fetch all activities (still used for any future list or logic)
$stmt = $pdo->query("SELECT * FROM activities ORDER BY start_datetime");
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all approved room reservations (kept in case you use them elsewhere)
$stmt_reservations = $pdo->query("SELECT * FROM room_reservations WHERE status = 'Approved' ORDER BY reservation_date");
$reservations = $stmt_reservations->fetchAll(PDO::FETCH_ASSOC);

// Department color mapping (kept if needed)
$departmentColors = [
    "All Departments" => ["bgcolor" => "#d1d0cf", "textcolor" => "#969593"],
    "Office of the General Manager" => ["bgcolor" => "#b0f5f5", "textcolor" => "#679e9e"],
    "Management Information Services Section" => ["bgcolor" => "#fcc6c0", "textcolor" => "#8f4239"],
    "Administrative Department" => ["bgcolor" => "#cff799", "textcolor" => "#6a8745"],
    "Finance Department" => ["bgcolor" => "#3cc74a", "textcolor" => "#1b6622"],
    "Commercial Department" => ["bgcolor" => "#f8fc9d", "textcolor" => "#b8bf2c"],
    "Technical Services Department" => ["bgcolor" => "#cba5f2", "textcolor" => "#70518f"],
    "Operations Department" => ["bgcolor" => "#fab07a", "textcolor" => "#a8602c"]
];

$events = [];

// Process activities
foreach ($activities as $activity) {
    $dept = $activity['department'] ?? "All Departments"; // avoid overwriting $department (user dept)
    $bgcolor = $departmentColors[$dept]['bgcolor'] ?? "#b0f5f5";
    $textcolor = $departmentColors[$dept]['textcolor'] ?? "#679e9e";

    $events[] = [
        'id' => $activity['id'],
        'title' => $activity['title'],
        'start' => $activity['start_datetime'],
        'end' => $activity['end_datetime'],
        'description' => $activity['description'],
        'color' => $bgcolor,
        'textColor' => $textcolor,
        'department' => $dept,
        'event_url' => $activity['event_url'],
        'event_location' => $activity['event_location']
    ];
}

// Process room reservations (kept for completeness)
foreach ($reservations as $reservation) {
    $events[] = [
        'id' => 'res-' . $reservation['id'],
        'title' => 'Room Reserved: ' . $reservation['room'],
        'department' => $reservation['department'],
        'start' => $reservation['reservation_date'] . ' ' . $reservation['start_time'],
        'end' => $reservation['reservation_date'] . ' ' . $reservation['end_time'],
        'description' => $reservation['purpose'],
        'color' => "#4caf50",
        'textColor' => "#ffffff",
        'event_location' => $reservation['room'],
        'editable' => false,
        'deletable' => false,
        'type' => 'Room Reservation'
    ];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Basic server-side sanitization (you can expand/validate further)
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $activity_date = trim($_POST['activity_date'] ?? '');
    $activity_end_date = trim($_POST['activity_end_date'] ?? $activity_date);
    $start_time = trim($_POST['start_time'] ?? '00:00:00');
    $end_time = trim($_POST['end_time'] ?? '00:00:00');
    $department_input = trim($_POST['department'] ?? 'All Departments');
    $event_url = trim($_POST['event_url'] ?? '');
    $event_location = trim($_POST['event_location'] ?? '');

    // Normalize times if only HH:MM given
    if (strlen($start_time) === 5)
        $start_time .= ':00';
    if (strlen($end_time) === 5)
        $end_time .= ':00';

    $start_datetime = $activity_date . ' ' . $start_time;
    $end_datetime = $activity_end_date . ' ' . $end_time;

    $stmt = $pdo->prepare("INSERT INTO activities (title, description, start_datetime, end_datetime, department, event_url, event_location) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title, $description, $start_datetime, $end_datetime, $department_input, $event_url, $event_location]);

    // Set flash message for SweetAlert
    $_SESSION['event_success'] = "Event added successfully!";

    header("Location: addEvents.php");
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Add Events</title>

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
    <link rel="stylesheet" href="./css/addEvents.css">

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="../assets/vendor/libs/apex-charts/apex-charts.css" />

    <!-- Helpers -->
    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>

    <!-- FullCalendar v5 -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>

    <!-- jQuery + Moment.js (for formatting, optional if you use moment in event display) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>

    <style>
        /* =========================================================
           TOKENS — shared accent with the rest of DistrictOne (#4e96f0)
           ========================================================= */
        :root {
            --ae-accent: #4e96f0;
            --ae-accent-deep: #3d7fd9;
            --ae-accent-soft: rgba(78, 150, 240, .12);
            --ae-bg: #f7f8fa;
            --ae-surface: #ffffff;
            --ae-surface-sunken: #fafbfc;
            --ae-border: #e8eaee;
            --ae-text: #1f2430;
            --ae-text-muted: #767e8c;
            --ae-danger: #e5534b;
            --ae-danger-soft: rgba(229, 83, 75, .1);
            --ae-radius: 12px;
            --ae-radius-sm: 8px;
            --ae-shadow: 0 1px 2px rgba(20, 20, 43, .04), 0 8px 24px -12px rgba(20, 20, 43, .10);
        }

        /* ── Card wrapper ─────────────────────────────────────────── */
        .app-calendar-wrapper {
            border: 1px solid var(--ae-border);
            border-radius: var(--ae-radius);
            box-shadow: var(--ae-shadow);
            overflow: hidden;
        }

        .ae-toolbar {
            padding: 18px 22px;
            border-bottom: 1px solid var(--ae-border);
            background: var(--ae-surface);
        }
        .ae-toolbar h4 {
            font-weight: 700;
            font-size: 1.15rem;
            color: var(--ae-text);
            margin: 0;
        }
        .ae-toolbar .text-muted-sub {
            font-size: 12.5px;
            color: var(--ae-text-muted);
            margin-top: 2px;
        }

        .ae-btn-primary {
            display: inline-flex;
            align-items: center;
            background: var(--ae-accent);
            color: #fff;
            border: none;
            font-weight: 600;
            font-size: 13.5px;
            padding: 9px 16px;
            border-radius: 9px;
            box-shadow: 0 4px 10px -4px rgba(78, 150, 240, .55);
            transition: background .12s ease, transform .12s ease;
        }
        .ae-btn-primary:hover, .ae-btn-primary:focus {
            background: var(--ae-accent-deep);
            color: #fff;
            transform: translateY(-1px);
        }

        /* ── Calendar container ───────────────────────────────────── */
        #calendar-panel {
            padding: 18px 22px 22px;
            background: var(--ae-bg);
        }

        /* Department legend / filter bar */
        .cal-legend {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-bottom: 14px;
            padding: 10px 12px;
            background: var(--ae-surface);
            border: 1px solid var(--ae-border);
            border-radius: var(--ae-radius-sm);
        }
        .cal-legend .legend-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: var(--ae-text-muted);
            margin-right: 4px;
            flex: none;
        }
        .legend-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            padding: 5px 10px 5px 8px;
            border-radius: 999px;
            border: 1.5px solid transparent;
            cursor: pointer;
            user-select: none;
            background: var(--ae-surface-sunken);
            color: var(--ae-text-muted);
            transition: opacity .12s ease, border-color .12s ease, background .12s ease;
        }
        .legend-chip .dot { width: 9px; height: 9px; border-radius: 50%; flex: none; }
        .legend-chip.active {
            background: var(--chip-bg, var(--ae-accent-soft));
            color: var(--chip-text, var(--ae-accent-deep));
        }
        .legend-chip.inactive { opacity: .45; }
        .legend-chip:hover { opacity: .85; }
        .legend-reset {
            margin-left: auto;
            font-size: 11.5px;
            font-weight: 600;
            color: var(--ae-accent-deep);
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px 6px;
        }
        .legend-reset:hover { text-decoration: underline; }

        #calendar {
            max-width: 100%;
            margin: 0 auto;
            padding: 16px;
            border: 1px solid var(--ae-border);
            border-radius: var(--ae-radius);
            background-color: var(--ae-surface);
            position: relative;
        }

        #calendar.is-filtering { opacity: .55; pointer-events: none; transition: opacity .15s ease; }

        /* Allow event titles to wrap */
        .fc .fc-event-title-only {
            white-space: normal !important;
            overflow: visible !important;
            text-overflow: unset !important;
            word-wrap: break-word !important;
        }

        /* ── Event pills ──────────────────────────────────────────── */
        .fc-event {
            border: none !important;
            border-radius: 6px !important;
            box-shadow: 0 1px 0 rgba(20,20,43,.03);
        }
        .fc-daygrid-event {
            padding: 2px 6px !important;
            margin-top: 2px !important;
        }
        .fc-event:hover {
            opacity: .92;
            cursor: pointer;
            filter: brightness(0.97);
            transition: .12s ease;
        }
        .ae-event-inner {
            display: flex;
            align-items: center;
            gap: 5px;
            min-width: 0;
        }
        .ae-event-inner .ae-event-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            flex: none;
            background: currentColor;
            opacity: .65;
        }
        .ae-event-inner .ae-event-time {
            font-weight: 700;
            font-size: 10.5px;
            opacity: .8;
            flex: none;
        }
        .ae-event-inner .ae-event-title {
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .fc-daygrid-block-event .ae-event-title { white-space: normal; }

        /* ── Day grid cells ───────────────────────────────────────── */
        .fc .fc-daygrid-day-frame { padding: 3px; }
        .fc .fc-daygrid-day-top { padding: 4px 6px 0; }
        .fc .fc-daygrid-day-number {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--ae-text);
            padding: 3px 6px;
        }
        .fc .fc-day-sat .fc-daygrid-day-number,
        .fc .fc-day-sun .fc-daygrid-day-number {
            color: var(--ae-text-muted);
        }
        .fc-day-sat:not(.fc-day-today), .fc-day-sun:not(.fc-day-today) {
            background: var(--ae-surface-sunken);
        }
        .fc-day-today .fc-daygrid-day-number {
            background: var(--ae-accent);
            color: #fff !important;
            border-radius: 999px;
            min-width: 22px;
            height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 !important;
        }
        .fc .fc-daygrid-more-link {
            font-size: 11px;
            font-weight: 700;
            color: var(--ae-accent-deep);
        }
        .fc-daygrid-day.fc-day-other .fc-daygrid-day-number { opacity: .35; }

        /* ── Toolbar title + buttons ─────────────────────────────── */
        .fc .fc-toolbar {
            flex-wrap: wrap;
            gap: 10px;
        }
        .fc .fc-toolbar-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--ae-text);
        }
        .fc .fc-toolbar-chunk button {
            background-color: var(--ae-surface);
            border: 1.5px solid var(--ae-border);
            color: var(--ae-text);
            font-weight: 600;
            font-size: 12.5px;
            border-radius: 8px;
            margin: 0 2px;
            text-transform: capitalize;
            box-shadow: none;
            transition: border-color .12s ease, background .12s ease;
        }
        .fc .fc-toolbar-chunk button:hover {
            background-color: var(--ae-accent-soft);
            border-color: var(--ae-accent);
            color: var(--ae-accent-deep);
        }
        .fc .fc-button.fc-button-active,
        .fc .fc-button-primary:not(:disabled):active {
            background-color: var(--ae-accent) !important;
            border-color: var(--ae-accent) !important;
            color: #fff !important;
        }
        .fc .fc-today-button {
            background-color: var(--ae-accent-soft) !important;
            border-color: var(--ae-accent-soft) !important;
            color: var(--ae-accent-deep) !important;
        }
        .fc-theme-standard td, .fc-theme-standard th {
            border-color: var(--ae-border) !important;
        }
        .fc-col-header-cell {
            background: var(--ae-surface-sunken);
            padding: 8px 0;
        }
        .fc-col-header-cell-cushion {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: var(--ae-text-muted);
        }
        .fc-day-today { background: var(--ae-accent-soft) !important; }

        /* ── Week/day (time grid) view ───────────────────────────── */
        .fc-timegrid-slot-label-cushion {
            font-size: 11px;
            color: var(--ae-text-muted);
        }
        .fc-timegrid-now-indicator-line { border-color: var(--ae-danger) !important; }
        .fc-timegrid-now-indicator-arrow { border-color: var(--ae-danger) !important; color: var(--ae-danger) !important; }

        /* ── List view ────────────────────────────────────────────── */
        .fc-list { border-radius: var(--ae-radius-sm); overflow: hidden; }
        .fc-list-day-cushion { background: var(--ae-surface-sunken) !important; }
        .fc-list-event:hover td { background: var(--ae-accent-soft) !important; cursor: pointer; }
        .fc-list-event-dot { border-width: 5px !important; }
        .fc-list-empty { color: var(--ae-text-muted); font-size: 13.5px; }

        /* Empty state when a filter hides everything */
        .cal-empty-state {
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 46px 12px;
            color: var(--ae-text-muted);
            text-align: center;
        }
        .cal-empty-state i { font-size: 30px; color: var(--ae-border); }
        .cal-empty-state.show { display: flex; }

        /* ── Modals (view) ───────────────────────────────────────── */
        .modal-content {
            border-radius: var(--ae-radius);
            border: 1px solid var(--ae-border);
        }
        .modal-header, .modal-footer { border-color: var(--ae-border); }
        .modal-title { font-weight: 700; color: var(--ae-text); }
        .btn-primary {
            background-color: var(--ae-accent);
            border-color: var(--ae-accent);
        }
        .btn-primary:hover, .btn-primary:focus {
            background-color: var(--ae-accent-deep);
            border-color: var(--ae-accent-deep);
        }

        /* View modal detail rows */
        .view-row {
            display: flex;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid var(--ae-border);
        }
        .view-row:last-child { border-bottom: none; }
        .view-row .view-icon {
            width: 30px; height: 30px; flex: none;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            background: var(--ae-accent-soft);
            color: var(--ae-accent-deep);
            font-size: 15px;
        }
        .view-row .view-label {
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: var(--ae-text-muted);
            margin-bottom: 2px;
        }
        .view-row .view-value {
            font-size: 14px;
            color: var(--ae-text);
            word-break: break-word;
        }
        .dept-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12.5px;
            font-weight: 600;
            padding: 3px 10px 3px 8px;
            border-radius: 999px;
        }
        .dept-chip .dot { width: 8px; height: 8px; border-radius: 50%; flex: none; }

        /* ── Offcanvas add-event form ────────────────────────────── */
        .event-sidebar { width: 440px; }
        .event-sidebar .offcanvas-header {
            padding: 18px 22px;
            background: var(--ae-surface);
        }
        .event-sidebar .offcanvas-title { font-weight: 700; }
        .event-sidebar .offcanvas-title small {
            display: block;
            font-weight: 500;
            font-size: 12px;
            color: var(--ae-text-muted);
            margin-top: 2px;
        }
        .event-sidebar .offcanvas-body { padding: 4px 22px 26px; background: var(--ae-surface-sunken); }

        /* Form section grouping */
        .form-section {
            background: var(--ae-surface);
            border: 1px solid var(--ae-border);
            border-radius: var(--ae-radius-sm);
            padding: 16px 16px 4px;
            margin: 18px 0;
        }
        .form-section-title {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: var(--ae-text-muted);
            margin-bottom: 14px;
        }
        .form-section-title i { font-size: 15px; color: var(--ae-accent); }

        .field-group { margin-bottom: 14px; }
        .field-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .event-sidebar .form-label {
            font-weight: 600;
            font-size: 12.5px;
            color: var(--ae-text);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .event-sidebar .form-label i { font-size: 14px; color: var(--ae-text-muted); }
        .event-sidebar .form-label .req { color: var(--ae-danger); font-weight: 700; }

        .event-sidebar .form-control,
        .event-sidebar .form-select {
            border-radius: 8px;
            border: 1.5px solid var(--ae-border);
            font-size: 13.5px;
            padding: 8px 11px;
        }
        .event-sidebar .form-control:focus,
        .event-sidebar .form-select:focus {
            border-color: var(--ae-accent);
            box-shadow: 0 0 0 3px var(--ae-accent-soft);
        }
        .event-sidebar textarea.form-control { resize: vertical; min-height: 74px; }

        .field-hint {
            font-size: 11px;
            color: var(--ae-text-muted);
            margin-top: 4px;
        }
        .char-counter {
            font-size: 11px;
            color: var(--ae-text-muted);
            text-align: right;
            margin-top: 3px;
        }

        /* Department select + live color preview */
        .dept-select-row { display: flex; align-items: center; gap: 8px; }
        .dept-dot {
            width: 14px; height: 14px;
            border-radius: 50%;
            flex: none;
            border: 2px solid #fff;
            box-shadow: 0 0 0 1.5px var(--ae-border);
            transition: background-color .15s ease;
        }

        /* Sticky action footer inside offcanvas */
        .ae-form-actions {
            position: sticky;
            bottom: -26px;
            margin: 20px -22px -26px;
            padding: 14px 22px;
            background: var(--ae-surface);
            border-top: 1px solid var(--ae-border);
            display: flex;
            gap: 10px;
        }
        .btn-add-event {
            background-color: var(--ae-accent);
            border-color: var(--ae-accent);
            font-weight: 600;
            flex: 1;
        }
        .btn-add-event:hover {
            background-color: var(--ae-accent-deep);
            border-color: var(--ae-accent-deep);
        }
        .btn-cancel {
            font-weight: 600;
            border-radius: 8px;
        }

        /* ── Edit modal (styled to match the add form) ───────────── */
        #editEventModal .modal-body { padding: 18px 20px 20px; background: var(--ae-surface-sunken); }
        #editEventModal .form-section { margin-top: 0; }
        #editEventModal .form-label {
            font-weight: 600;
            font-size: 12.5px;
            color: var(--ae-text);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        #editEventModal .form-label i { font-size: 14px; color: var(--ae-text-muted); }
        #editEventModal .form-control {
            border-radius: 8px;
            border: 1.5px solid var(--ae-border);
            font-size: 13.5px;
            padding: 8px 11px;
        }
        #editEventModal .form-control:focus {
            border-color: var(--ae-accent);
            box-shadow: 0 0 0 3px var(--ae-accent-soft);
        }
        #editEventModal .field-group { margin-bottom: 14px; }
        #editEventModal .modal-footer { background: var(--ae-surface); }
    </style>
</head>

<body>
    <?php $role = trim(strtolower($_SESSION['role'] ?? ''));
    switch ($role) {
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
            echo "<p>Unauthorized role.</p>";
            exit;
    }
    ?>

    <div class="content-wrapper">
        <!-- Content -->
        <div class="container-xxl flex-grow-1 container-p-y">
            <div class="card app-calendar-wrapper">
                <div class="row g-0">

                    <!-- Top area with Add Event button -->
                    <div class="col-12">
                        <div class="ae-toolbar d-flex justify-content-between align-items-center">
                            <div>
                                <h4>Add Calendar Activity</h4>
                                <div class="text-muted-sub">Schedule company-wide or department events</div>
                            </div>
                            <button class="ae-btn-primary" data-bs-toggle="offcanvas" data-bs-target="#addEventSidebar"
                                aria-controls="addEventSidebar">
                                <i class="icon-base bx bx-plus icon-16px me-2"></i>
                                <span class="align-middle">Add Event</span>
                            </button>
                        </div>
                    </div>

                    <!-- Calendar -->
                    <div class="col-12" id="calendar-panel">
                        <div class="cal-legend" id="calLegend">
                            <span class="legend-label">Filter</span>
                            <!-- chips injected by JS -->
                            <button type="button" class="legend-reset" id="legendReset">Reset</button>
                        </div>
                        <div style="position:relative;">
                            <div id="calendar"></div>
                            <div class="cal-empty-state" id="calEmptyState">
                                <i class="bx bx-calendar-x"></i>
                                <div><strong>No events match this filter</strong></div>
                                <div style="font-size:12.5px;">Try selecting a different department above.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Event Modal (view) -->
                    <div class="modal fade" id="eventModal" tabindex="-1" role="dialog">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="eventTitle"></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3" id="eventDepartmentChipWrap"></div>

                                    <div class="view-row">
                                        <div class="view-icon"><i class="bx bx-calendar"></i></div>
                                        <div>
                                            <div class="view-label">Date</div>
                                            <div class="view-value" id="eventDate"></div>
                                        </div>
                                    </div>
                                    <div class="view-row">
                                        <div class="view-icon"><i class="bx bx-time-five"></i></div>
                                        <div>
                                            <div class="view-label">Time</div>
                                            <div class="view-value" id="eventTime"></div>
                                        </div>
                                    </div>
                                    <div class="view-row">
                                        <div class="view-icon"><i class="bx bx-map-pin"></i></div>
                                        <div>
                                            <div class="view-label">Location</div>
                                            <div class="view-value" id="eventLocation"></div>
                                        </div>
                                    </div>
                                    <div class="view-row">
                                        <div class="view-icon"><i class="bx bx-link"></i></div>
                                        <div>
                                            <div class="view-label">URL</div>
                                            <div class="view-value"><a id="eventURL" target="_blank"></a></div>
                                        </div>
                                    </div>
                                    <div class="view-row">
                                        <div class="view-icon"><i class="bx bx-align-left"></i></div>
                                        <div>
                                            <div class="view-label">Description</div>
                                            <div class="view-value" id="eventDescription"></div>
                                        </div>
                                    </div>
                                    <span id="eventDepartment" class="d-none"></span>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-primary" id="updateEventBtn"><i class="bx bx-edit-alt me-1"></i>Update</button>
                                    <button type="button" class="btn btn-danger" id="deleteEventBtn"><i class="bx bx-trash me-1"></i>Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Event Modal -->
                    <div class="modal fade" id="editEventModal" tabindex="-1" role="dialog">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"><i class="bx bx-edit-alt me-1"></i>Edit Event</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <form id="editEventForm">
                                        <input type="hidden" name="id" id="editEventId">

                                        <div class="form-section">
                                            <div class="form-section-title"><i class="bx bx-detail"></i>Event details</div>

                                            <div class="field-group">
                                                <label class="form-label" for="editTitle"><i class="bx bx-bookmark"></i>Title <span class="req">*</span></label>
                                                <input type="text" class="form-control" name="title" id="editTitle"
                                                    placeholder="e.g. Quarterly Town Hall" required>
                                            </div>

                                            <div class="field-group">
                                                <label class="form-label" for="editLocation"><i class="bx bx-map-pin"></i>Location</label>
                                                <input type="text" class="form-control" name="event_location" id="editLocation"
                                                    placeholder="Room, building, or venue">
                                            </div>

                                            <div class="field-group">
                                                <label class="form-label" for="editURL"><i class="bx bx-link"></i>Event URL</label>
                                                <input type="text" class="form-control" name="event_url" id="editURL"
                                                    placeholder="https://">
                                            </div>
                                        </div>

                                        <div class="form-section">
                                            <div class="form-section-title"><i class="bx bx-calendar-event"></i>Schedule</div>

                                            <div class="field-grid-2 field-group">
                                                <div>
                                                    <label class="form-label" for="editActivityDate">Start date <span class="req">*</span></label>
                                                    <input type="date" class="form-control" name="activity_date"
                                                        id="editActivityDate" required>
                                                </div>
                                                <div>
                                                    <label class="form-label" for="editActivityEndDate">End date <span class="req">*</span></label>
                                                    <input type="date" class="form-control" name="activity_end_date"
                                                        id="editActivityEndDate" required>
                                                </div>
                                            </div>

                                            <div class="field-grid-2 field-group">
                                                <div>
                                                    <label class="form-label" for="editStartTime">Start time <span class="req">*</span></label>
                                                    <input type="time" class="form-control" name="start_time" id="editStartTime"
                                                        required>
                                                </div>
                                                <div>
                                                    <label class="form-label" for="editEndTime">End time <span class="req">*</span></label>
                                                    <input type="time" class="form-control" name="end_time" id="editEndTime"
                                                        required>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-section">
                                            <div class="form-section-title"><i class="bx bx-align-left"></i>Description</div>
                                            <div class="field-group">
                                                <textarea class="form-control" name="description" rows="3"
                                                    id="editDescription" placeholder="Add any helpful details attendees should know"></textarea>
                                            </div>
                                        </div>

                                        <button type="submit" class="btn btn-primary w-100"><i class="bx bx-save me-1"></i>Save Changes</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Offcanvas Add Event -->
                    <div class="offcanvas offcanvas-end event-sidebar" tabindex="-1" id="addEventSidebar"
                        aria-labelledby="addEventSidebarLabel">
                        <div class="offcanvas-header border-bottom">
                            <div>
                                <h5 class="offcanvas-title" id="addEventSidebarLabel">Add Event</h5>
                                <small>Fields marked <span class="req">*</span> are required</small>
                            </div>
                            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"
                                aria-label="Close"></button>
                        </div>
                        <div class="offcanvas-body">
                            <form method="POST" action="" id="addEventForm">

                                <div class="form-section">
                                    <div class="form-section-title"><i class="bx bx-detail"></i>Event details</div>

                                    <div class="field-group">
                                        <label class="form-label" for="title"><i class="bx bx-bookmark"></i>Title <span class="req">*</span></label>
                                        <input type="text" class="form-control" id="title" name="title"
                                            placeholder="e.g. Quarterly Town Hall" required>
                                    </div>

                                    <div class="field-group">
                                        <label class="form-label" for="department"><i class="bx bx-buildings"></i>Department</label>
                                        <div class="dept-select-row">
                                            <span class="dept-dot" id="deptDot"></span>
                                            <select class="select2 select-event-label form-select" id="department"
                                                name="department">
                                                <option value="All Departments" selected>All Departments</option>
                                                <option value="Office of the General Manager">Office of the General Manager
                                                </option>
                                                <option value="Management Information Services Section">Management
                                                    Information Services Section</option>
                                                <option value="Administrative Department">Administrative Department</option>
                                                <option value="Finance Department">Finance Department</option>
                                                <option value="Commercial Department">Commercial Department</option>
                                                <option value="Technical Services Department">Technical Services Department
                                                </option>
                                                <option value="Operations Department">Operations Department</option>
                                            </select>
                                        </div>
                                        <div class="field-hint">This sets the color shown on the calendar.</div>
                                    </div>

                                    <div class="field-group">
                                        <label class="form-label" for="event_location"><i class="bx bx-map-pin"></i>Location</label>
                                        <input type="text" class="form-control" id="event_location" name="event_location"
                                            placeholder="Room, building, or venue">
                                    </div>

                                    <div class="field-group">
                                        <label class="form-label" for="event_url"><i class="bx bx-link"></i>Event URL</label>
                                        <input type="text" class="form-control" id="event_url" name="event_url"
                                            placeholder="https://www.google.com">
                                    </div>
                                </div>

                                <div class="form-section">
                                    <div class="form-section-title"><i class="bx bx-calendar-event"></i>Schedule</div>

                                    <div class="field-grid-2 field-group">
                                        <div>
                                            <label class="form-label" for="activity_date">Start date <span class="req">*</span></label>
                                            <input type="date" class="form-control flatpickr-input" id="activity_date"
                                                name="activity_date" required>
                                        </div>
                                        <div>
                                            <label class="form-label" for="activity_end_date">End date</label>
                                            <input type="date" class="form-control flatpickr-input" id="activity_end_date"
                                                name="activity_end_date" placeholder="Same as start date">
                                        </div>
                                    </div>

                                    <div class="field-grid-2 field-group">
                                        <div>
                                            <label class="form-label" for="start_time">Start time <span class="req">*</span></label>
                                            <input type="time" class="form-control flatpickr-input" name="start_time"
                                                id="start_time" required>
                                        </div>
                                        <div>
                                            <label class="form-label" for="end_time">End time <span class="req">*</span></label>
                                            <input type="time" class="form-control flatpickr-input" name="end_time"
                                                id="end_time" required>
                                        </div>
                                    </div>
                                    <div class="field-hint" id="timeWarning" style="display:none; color: var(--ae-danger);">
                                        <i class="bx bx-error-circle"></i> End time should be after start time.
                                    </div>
                                </div>

                                <div class="form-section">
                                    <div class="form-section-title"><i class="bx bx-align-left"></i>Description</div>
                                    <div class="field-group">
                                        <textarea class="form-control" name="description" id="description" rows="3"
                                            maxlength="500" placeholder="Add any helpful details attendees should know"></textarea>
                                        <div class="char-counter"><span id="descCount">0</span>/500</div>
                                    </div>
                                </div>

                                <div class="ae-form-actions">
                                    <button type="reset" class="btn btn-label-secondary btn-cancel"
                                        data-bs-dismiss="offcanvas">Cancel</button>
                                    <button type="submit" id="confirmAdd"
                                        class="btn btn-primary btn-add-event"><i class="bx bx-plus me-1"></i>Add Activity</button>
                                </div>
                            </form>
                        </div>
                    </div>

                </div> <!-- .row -->
                <div class="content-backdrop fade"></div>
            </div> <!-- .card -->
        </div> <!-- .container -->
    </div> <!-- .content-wrapper -->

    <div class="layout-overlay layout-menu-toggle"></div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/dashboards-analytics.js"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>

    <script>
        const events = <?= json_encode($events); ?>;
        const departmentColors = <?= json_encode($departmentColors); ?>;
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const calendarEl = document.getElementById('calendar');

            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                height: 'auto',
                aspectRatio: 1.35,
                navLinks: true,
                editable: true,
                selectable: true,
                events: events,

                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },

                eventClick: function (info) {
                    const e = info.event.extendedProps;
                    const dept = e.department || 'All Departments';
                    const palette = departmentColors[dept] || departmentColors['All Departments'];

                    document.getElementById('eventTitle').innerText = info.event.title;
                    document.getElementById('eventDepartment').innerText = dept;

                    document.getElementById('eventDepartmentChipWrap').innerHTML =
                        `<span class="dept-chip" style="background:${palette.bgcolor}; color:${palette.textcolor};">
                            <span class="dot" style="background:${palette.textcolor};"></span>${dept}
                        </span>`;

                    document.getElementById('eventDate').innerText = moment(info.event.start).format('MMMM D, YYYY');
                    document.getElementById('eventTime').innerText = info.event.start
                        ? moment(info.event.start).format('h:mm A') + (info.event.end ? ' - ' + moment(info.event.end).format('h:mm A') : '')
                        : '';
                    document.getElementById('eventLocation').innerText = e.event_location || '—';
                    const urlEl = document.getElementById('eventURL');
                    if (e.event_url) { urlEl.href = e.event_url; urlEl.innerText = e.event_url; }
                    else { urlEl.removeAttribute('href'); urlEl.innerText = 'N/A'; }
                    document.getElementById('eventDescription').innerText = e.description || '—';

                    new bootstrap.Modal(document.getElementById('eventModal')).show();

                    const isRoomReservation = e.type === 'Room Reservation';

                    // Show/hide buttons
                    document.getElementById('updateEventBtn').style.display = isRoomReservation ? 'none' : 'inline-block';
                    document.getElementById('deleteEventBtn').style.display = isRoomReservation ? 'none' : 'inline-block';

                    if (!isRoomReservation) {
                        document.getElementById('updateEventBtn').onclick = function () {
                            // Prefill edit modal
                            $('#editEventId').val(info.event.id);
                            $('#editTitle').val(info.event.title);
                            $('#editActivityDate').val(moment(info.event.start).format('YYYY-MM-DD'));
                            $('#editActivityEndDate').val(info.event.end ? moment(info.event.end).format('YYYY-MM-DD') : moment(info.event.start).format('YYYY-MM-DD'));
                            $('#editStartTime').val(moment(info.event.start).format('HH:mm'));
                            $('#editEndTime').val(info.event.end ? moment(info.event.end).format('HH:mm') : moment(info.event.start).format('HH:mm'));
                            $('#editURL').val(e.event_url || '');
                            $('#editLocation').val(e.event_location || '');
                            $('#editDescription').val(e.description || '');

                            bootstrap.Modal.getInstance(document.getElementById('eventModal')).hide();
                            new bootstrap.Modal(document.getElementById('editEventModal')).show();
                        };

                        document.getElementById('deleteEventBtn').onclick = function () {
                            if (!confirm('Are you sure you want to delete this event?')) return;
                            $.post('deleteEvent.php', { id: info.event.id }, function (response) {
                                let res = JSON.parse(response);
                                if (res.status === 'success') {
                                    info.event.remove();
                                    Swal.fire('Deleted!', res.message, 'success');
                                    bootstrap.Modal.getInstance(document.getElementById('eventModal')).hide();
                                } else {
                                    Swal.fire('Error', res.message, 'error');
                                }
                            });
                        };
                    }
                },

                eventDidMount: function (info) {
                    // Room reservations get a fixed green treatment; regular activities
                    // keep the department color assigned in PHP (info.event.backgroundColor)
                    // instead of being forced to a single flat blue.
                    if (info.event.extendedProps.type === 'Room Reservation') {
                        info.el.style.backgroundColor = '#4caf50';
                        info.el.style.border = '1px solid #388e3c';
                        info.el.style.color = '#ffffff';
                        info.el.classList.add('is-reservation');
                    }
                    info.el.style.whiteSpace = 'normal';

                    // Native tooltip with the full title + time, useful when a pill is truncated
                    const timeStr = info.event.start ? moment(info.event.start).format('h:mm A') : '';
                    info.el.setAttribute('title', `${info.event.title}${timeStr ? ' · ' + timeStr : ''}`);
                },

                eventContent: function (arg) {
                    const showTime = arg.view.type !== 'listWeek' && !arg.event.allDay && arg.event.start;
                    const timeStr = showTime ? moment(arg.event.start).format('h:mm A') : '';
                    return {
                        html: `<div class="ae-event-inner">
                                 <span class="ae-event-dot"></span>
                                 ${timeStr ? `<span class="ae-event-time">${timeStr}</span>` : ''}
                                 <span class="ae-event-title">${arg.event.title}</span>
                               </div>`
                    };
                }
            });

            calendar.render();

            // ── Department legend / filter ─────────────────────────
            (function setupLegend() {
                const legendEl = document.getElementById('calLegend');
                const emptyStateEl = document.getElementById('calEmptyState');
                const calendarWrap = document.getElementById('calendar');
                const resetBtn = document.getElementById('legendReset');

                // Build the legend entries from the department palette, plus room reservations
                const legendEntries = Object.keys(departmentColors)
                    .filter(name => name !== 'All Departments')
                    .map(name => ({ key: name, label: name, ...departmentColors[name] }));
                legendEntries.unshift({ key: 'All Departments', label: 'All Departments', ...departmentColors['All Departments'] });
                legendEntries.push({ key: 'Room Reservation', label: 'Room Reservations', bgcolor: '#c8f7cf', textcolor: '#1b6622' });

                const activeKeys = new Set(legendEntries.map(e => e.key));

                legendEntries.forEach(entry => {
                    const chip = document.createElement('span');
                    chip.className = 'legend-chip active';
                    chip.dataset.key = entry.key;
                    chip.style.setProperty('--chip-bg', entry.bgcolor);
                    chip.style.setProperty('--chip-text', entry.textcolor);
                    chip.innerHTML = `<span class="dot" style="background:${entry.textcolor};"></span>${entry.label}`;
                    chip.addEventListener('click', () => {
                        if (activeKeys.has(entry.key)) {
                            activeKeys.delete(entry.key);
                            chip.classList.remove('active');
                            chip.classList.add('inactive');
                        } else {
                            activeKeys.add(entry.key);
                            chip.classList.add('active');
                            chip.classList.remove('inactive');
                        }
                        applyFilter();
                    });
                    legendEl.insertBefore(chip, resetBtn);
                });

                resetBtn.addEventListener('click', () => {
                    activeKeys.clear();
                    legendEntries.forEach(e => activeKeys.add(e.key));
                    legendEl.querySelectorAll('.legend-chip').forEach(c => {
                        c.classList.add('active');
                        c.classList.remove('inactive');
                    });
                    applyFilter();
                });

                function eventKey(e) {
                    if (e.type === 'Room Reservation') return 'Room Reservation';
                    return e.department || 'All Departments';
                }

                function applyFilter() {
                    calendarWrap.classList.add('is-filtering');
                    const filtered = events.filter(e => activeKeys.has(eventKey(e)));
                    calendar.removeAllEventSources();
                    calendar.addEventSource(filtered);
                    emptyStateEl.classList.toggle('show', filtered.length === 0);
                    setTimeout(() => calendarWrap.classList.remove('is-filtering'), 120);
                }
            })();

            // ── Add-event form UX helpers ─────────────────────────
            const deptSelect = document.getElementById('department');
            const deptDot = document.getElementById('deptDot');

            function syncDeptDot() {
                const palette = departmentColors[deptSelect.value] || departmentColors['All Departments'];
                deptDot.style.backgroundColor = palette.textcolor;
            }
            syncDeptDot();
            deptSelect.addEventListener('change', syncDeptDot);

            // Default end date to start date, and keep it in sync until the user edits it manually
            const startDateEl = document.getElementById('activity_date');
            const endDateEl = document.getElementById('activity_end_date');
            let endDateTouched = false;
            endDateEl.addEventListener('input', () => { endDateTouched = true; });
            startDateEl.addEventListener('change', () => {
                if (!endDateTouched || !endDateEl.value) endDateEl.value = startDateEl.value;
            });

            // Live character counter for description
            const descEl = document.getElementById('description');
            const descCount = document.getElementById('descCount');
            descEl.addEventListener('input', () => { descCount.innerText = descEl.value.length; });

            // Gentle inline warning if end time is before start time on the same day
            const startTimeEl = document.getElementById('start_time');
            const endTimeEl = document.getElementById('end_time');
            const timeWarning = document.getElementById('timeWarning');
            function checkTimeOrder() {
                const sameDay = !endDateEl.value || endDateEl.value === startDateEl.value;
                if (sameDay && startTimeEl.value && endTimeEl.value && endTimeEl.value <= startTimeEl.value) {
                    timeWarning.style.display = 'block';
                } else {
                    timeWarning.style.display = 'none';
                }
            }
            [startTimeEl, endTimeEl, startDateEl, endDateEl].forEach(el => el.addEventListener('change', checkTimeOrder));

            // AJAX submit for edit
            $('#editEventForm').submit(function (e) {
                e.preventDefault();
                const data = $(this).serialize();

                $.post('editEvent.php', data, function (response) {
                    const res = JSON.parse(response);
                    if (res.status === 'success') {
                        Swal.fire('Success', res.message, 'success');

                        // Update event in calendar
                        const id = $('#editEventId').val();
                        const event = calendar.getEventById(id);
                        event.setProp('title', $('#editTitle').val());
                        event.setStart($('#editActivityDate').val() + 'T' + $('#editStartTime').val());
                        event.setEnd($('#editActivityEndDate').val() + 'T' + $('#editEndTime').val());
                        event.setExtendedProp('event_url', $('#editURL').val());
                        event.setExtendedProp('event_location', $('#editLocation').val());
                        event.setExtendedProp('description', $('#editDescription').val());

                        bootstrap.Modal.getInstance(document.getElementById('editEventModal')).hide();
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                });
            });
        });
    </script>

    <!-- SweetAlert: show success after creating event (server-side flash) -->
    <?php if (isset($_SESSION['event_success'])): ?>
        <script>
            // Use json_encode to safely escape the message
            const _msg = <?php echo json_encode($_SESSION['event_success']); ?>;
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: _msg,
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });
        </script>
        <?php unset($_SESSION['event_success']); endif; ?>

</body>

</html>