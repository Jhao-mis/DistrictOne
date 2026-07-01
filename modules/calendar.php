<?php
require '../vendor/autoload.php';
include '../db.php';
require 'login_verification.php';

$username = $_SESSION['username'];

// Department Colors
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

// Fetch user details
$query = $conn->prepare("SELECT id, profile_picture, cover_photo, department, firstname, middlename, lastname, email FROM users WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->store_result();
$query->bind_result($user_id, $profile_picture, $cover_photo, $department, $firstname, $middlename, $lastname, $email);
$query->fetch();
$query->close();

// Fetch activities & reservations
$activities = $pdo->query("SELECT * FROM activities ORDER BY start_datetime")->fetchAll(PDO::FETCH_ASSOC);
$reservations = $pdo->query("
    SELECT rr.*, u.firstname, u.middlename, u.lastname 
    FROM room_reservations rr
    JOIN users u ON rr.user_id = u.id
    WHERE rr.status = 'Approved'
    ORDER BY rr.reservation_date
")->fetchAll(PDO::FETCH_ASSOC);

// Merge events
$events = [];

// Activity events (Blue)
foreach ($activities as $activity) {
    $events[] = [
        'id' => $activity['id'],
        'title' => $activity['title'],
        'start' => $activity['start_datetime'],
        'end' => $activity['end_datetime'],
        'description' => $activity['description'],
        'department' => $activity['department'] ?? 'All Departments',
        'event_url' => $activity['event_url'],
        'event_location' => $activity['event_location'],
        'color' => '#7cb9ff',
        'textColor' => '#ffffff'
    ];
}

// Room reservation events (soft red)
foreach ($reservations as $reservation) {
    $events[] = [
        'id' => 'res-' . $reservation['id'],
        'title' => 'Room Reserved',
        'start' => $reservation['reservation_date'] . ' ' . $reservation['start_time'],
        'end' => $reservation['reservation_date'] . ' ' . $reservation['end_time'],
        'description' => $reservation['purpose'],
        'department' => $reservation['department'] ?? 'N/A',
        'event_location' => $reservation['room'],
        'event_url' => '',
        'color' => '#f87171',
        'textColor' => '#ffffff'
    ];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <title>Calendar</title>

    <!-- Favicon & Fonts -->
    <link rel="icon" href="../assets/img/favicon/districtone.png" />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Template CSS -->
    <link rel="stylesheet" href="../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="./css/calendar.css" />
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" />

    <!-- FullCalendar v5 -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>

    <!-- jQuery + Moment (used for sidebar formatting) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>

    <!-- Template helpers -->
    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>

    <style>
        :root {
            --tk-bg: #f7f8fa;
            --tk-surface: #ffffff;
            --tk-border: #e8eaee;
            --tk-border-soft: #f0f1f4;
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
            --tk-radius: 14px;
            --tk-radius-sm: 9px;
            --tk-shadow: 0 1px 2px rgba(20,20,43,.04), 0 8px 24px -12px rgba(20,20,43,.10);
            --tk-shadow-lg: 0 20px 50px -18px rgba(20,20,43,.22);
            --tk-ease: cubic-bezier(.4,0,.2,1);
        }

        * { box-sizing: border-box; }

        /* ── Wrapper ────────────────────────────────────────────── */
        .cal-wrapper {
            background: var(--tk-surface);
            border: 1px solid var(--tk-border);
            border-radius: var(--tk-radius);
            box-shadow: var(--tk-shadow);
            overflow: hidden;
            display: flex;
            height: 720px;
        }

        /* ── Sidebar ────────────────────────────────────────────── */
        .cal-sidebar {
            width: 292px;
            flex-shrink: 0;
            border-right: 1px solid var(--tk-border);
            display: flex;
            flex-direction: column;
            background: var(--tk-bg);
            min-height: 0;
            height: 100%;
        }
        .cal-sidebar-head {
            padding: 18px 20px;
            border-bottom: 1px solid var(--tk-border);
            background: var(--tk-surface);
        }
        .cal-sidebar-head .month-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .cal-sidebar-head .month-label {
            font-size: 14.5px;
            font-weight: 800;
            letter-spacing: -.1px;
            color: var(--tk-text);
            white-space: nowrap;
        }
        .cal-nav-btn {
            width: 30px; height: 30px;
            border: 1.5px solid var(--tk-border);
            background: var(--tk-surface);
            color: var(--tk-text-muted);
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; flex-shrink: 0;
            transition: border-color .15s var(--tk-ease), color .15s var(--tk-ease), background .15s var(--tk-ease), transform .12s var(--tk-ease);
        }
        .cal-nav-btn:hover { border-color: var(--tk-primary); color: var(--tk-primary-deep); background: var(--tk-primary-soft); }
        .cal-nav-btn:active { transform: scale(.92); }
        .cal-nav-btn:focus-visible { outline: 2px solid var(--tk-primary); outline-offset: 2px; }
        .cal-nav-btn svg { width: 14px; height: 14px; }

        .cal-sidebar-month-title {
            font-size: 11.5px;
            font-weight: 800;
            color: var(--tk-text-muted);
            text-transform: uppercase;
            letter-spacing: .6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 7px;
            padding: 16px 20px 10px;
        }
        .cal-sidebar-month-title .label-group { display: flex; align-items: center; gap: 7px; min-width: 0; }
        .cal-sidebar-month-title .label-group span:not(.cal-count-pill) {
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .cal-sidebar-month-title svg { width: 14px; height: 14px; color: var(--tk-primary-dark); flex-shrink: 0; }
        .cal-count-pill {
            font-size: 10.5px;
            font-weight: 800;
            color: var(--tk-primary-deep);
            background: var(--tk-primary-soft);
            border-radius: 999px;
            padding: 2px 8px;
            flex-shrink: 0;
        }

        .cal-event-list { flex: 1; min-height: 0; overflow-y: auto; padding: 4px 12px 16px; }
        .cal-event-list::-webkit-scrollbar { width: 5px; }
        .cal-event-list::-webkit-scrollbar-thumb { background: var(--tk-border); border-radius: 4px; }
        .cal-event-list::-webkit-scrollbar-thumb:hover { background: #d3d7dd; }

        /* Event card in sidebar */
        .cal-event-item {
            background: var(--tk-surface);
            border: 1px solid var(--tk-border);
            border-radius: var(--tk-radius-sm);
            padding: 12px 13px 12px 15px;
            margin-bottom: 9px;
            cursor: pointer;
            transition: border-color .15s var(--tk-ease), transform .15s var(--tk-ease), box-shadow .15s var(--tk-ease);
            position: relative;
            overflow: hidden;
        }
        .cal-event-item::before {
            content: "";
            position: absolute; left: 0; top: 0; bottom: 0; width: 3px;
            background: var(--event-color, var(--tk-primary));
            border-radius: 0 3px 3px 0;
        }
        .cal-event-item:hover {
            border-color: var(--event-color, var(--tk-primary));
            transform: translateX(3px);
            box-shadow: 0 6px 16px -8px rgba(20,20,43,.18);
        }
        .cal-event-item:focus-visible { outline: 2px solid var(--tk-primary); outline-offset: 2px; }
        .cal-event-item h6 {
            font-size: 13px; font-weight: 700;
            color: var(--tk-text); margin-bottom: 6px; line-height: 1.4;
        }
        .cal-event-item-meta { font-size: 11.5px; color: var(--tk-text-muted); display: flex; flex-direction: column; gap: 3px; }
        .cal-event-item-meta span { display: flex; align-items: center; gap: 5px; }
        .cal-event-item-meta svg { width: 11px; height: 11px; flex-shrink: 0; color: var(--tk-text-faint); }
        .cal-event-badge {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 10px; font-weight: 800; letter-spacing: .2px; padding: 3px 8px;
            border-radius: 999px; margin-bottom: 7px;
        }
        .cal-event-badge.is-activity { background: var(--tk-primary-soft); color: var(--tk-primary-deep); }
        .cal-event-badge.is-room { background: var(--tk-room-soft); color: var(--tk-room-deep); }

        .cal-empty {
            text-align: center; padding: 40px 16px;
            color: var(--tk-text-faint); font-size: 13px;
        }
        .cal-empty svg { width: 34px; height: 34px; opacity: .4; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto; color: var(--tk-text-muted); }
        .cal-empty strong { display: block; color: var(--tk-text-muted); font-weight: 700; font-size: 13px; margin-bottom: 3px; }

        /* Legend */
        .cal-legend {
            padding: 14px 20px;
            border-top: 1px solid var(--tk-border);
            display: flex;
            flex-direction: column;
            gap: 8px;
            background: var(--tk-surface);
        }
        .cal-legend-item { display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600; color: var(--tk-text-muted); }
        .cal-legend-dot { width: 9px; height: 9px; border-radius: 3px; flex-shrink: 0; box-shadow: 0 0 0 3px currentColor22; }

        /* ── Calendar area ──────────────────────────────────────── */
        .cal-main { flex: 1; padding: 20px 22px 22px; min-width: 0; height: 100%; overflow-y: auto; }

        /* Toolbar */
        .cal-main .fc-toolbar.fc-header-toolbar { margin-bottom: 18px; flex-wrap: wrap; gap: 8px; }
        .cal-main .fc-toolbar-title { font-size: 18px; font-weight: 800; letter-spacing: -.2px; color: var(--tk-text); }
        .cal-main .fc-button {
            background: var(--tk-surface) !important;
            border: 1.5px solid var(--tk-border) !important;
            color: var(--tk-text) !important;
            font-weight: 700 !important;
            font-size: 12.5px !important;
            padding: 6px 13px !important;
            border-radius: 9px !important;
            box-shadow: none !important;
            transition: all .15s var(--tk-ease);
        }
        .cal-main .fc-button:hover { border-color: var(--tk-primary) !important; background: var(--tk-primary-soft) !important; color: var(--tk-primary-deep) !important; }
        .cal-main .fc-button:focus-visible { outline: 2px solid var(--tk-primary) !important; outline-offset: 2px; }
        .cal-main .fc-button-primary:not(:disabled).fc-button-active,
        .cal-main .fc-button-primary:not(:disabled):active {
            background: var(--tk-primary) !important;
            border-color: var(--tk-primary) !important;
            color: #fff !important;
        }
        .cal-main .fc-today-button { background: var(--tk-primary-soft) !important; border-color: var(--tk-primary-soft) !important; color: var(--tk-primary-deep) !important; }
        .cal-main .fc-today-button:disabled { opacity: .4 !important; }

        /* Grid */
        .cal-main .fc-theme-standard td,
        .cal-main .fc-theme-standard th,
        .cal-main .fc-theme-standard .fc-scrollgrid { border-color: var(--tk-border) !important; }
        .cal-main .fc-scrollgrid { border-radius: 10px; overflow: hidden; }
        .cal-main .fc-col-header-cell { background: var(--tk-bg); padding: 9px 0; }
        .cal-main .fc-col-header-cell-cushion { font-size: 11px; text-transform: uppercase; letter-spacing: .6px; font-weight: 800; color: var(--tk-text-muted); text-decoration: none; }
        .cal-main .fc-daygrid-day-number { font-size: 12.5px; font-weight: 600; color: var(--tk-text); padding: 6px 8px; text-decoration: none; }
        .cal-main .fc-daygrid-day-frame { transition: background .12s var(--tk-ease); }
        .cal-main .fc-daygrid-day:hover .fc-daygrid-day-frame { background: #fbfcfd; }
        .cal-main .fc-day-today { background: var(--tk-primary-soft) !important; }
        .cal-main .fc-day-today .fc-daygrid-day-number {
            background: var(--tk-primary); color: #fff;
            border-radius: 50%; width: 24px; height: 24px;
            display: inline-flex; align-items: center; justify-content: center;
            padding: 0; margin: 4px;
            box-shadow: 0 2px 8px -2px rgba(78,150,240,.55);
        }
        .cal-main .fc-day-other .fc-daygrid-day-number { color: #c3c8d1; }
        .cal-main .fc-daygrid-day.fc-day-sat .fc-daygrid-day-frame,
        .cal-main .fc-daygrid-day.fc-day-sun .fc-daygrid-day-frame { background: #fcfcfd; }

        /* Events */
        .cal-main .fc-event {
            cursor: pointer;
            border: none !important;
            font-size: 11.5px !important;
            font-weight: 700 !important;
            padding: 3px 7px !important;
            border-radius: 6px !important;
            box-shadow: 0 1px 2px rgba(20,20,43,.12);
            transition: transform .12s var(--tk-ease), box-shadow .12s var(--tk-ease);
        }
        .cal-main .fc-event:hover { transform: translateY(-1px); box-shadow: 0 4px 10px -2px rgba(20,20,43,.28); }
        .cal-main .fc-more-link { font-size: 11px; font-weight: 800; color: var(--tk-primary-deep); }
        .fc-event-title-only { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        /* ── Modal ──────────────────────────────────────────────── */
        #eventModal .modal-content { border: none; border-radius: 18px; overflow: hidden; box-shadow: var(--tk-shadow-lg); }
        #eventModal .modal-header {
            background: linear-gradient(135deg, var(--tk-primary) 0%, var(--tk-primary-dark) 100%);
            padding: 22px 26px;
            border-bottom: none;
            position: relative;
        }
        #eventModal .modal-header.is-room { background: linear-gradient(135deg, #fca5a5 0%, var(--tk-room) 100%); }
        #eventModal .modal-header-eyebrow {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 10.5px; font-weight: 800; letter-spacing: .5px; text-transform: uppercase;
            color: rgba(255,255,255,.85); margin-bottom: 5px;
        }
        #eventModal .modal-title { color: #fff; font-size: 18px; font-weight: 800; letter-spacing: -.2px; }
        #eventModal .btn-close { filter: brightness(0) invert(1); opacity: .85; }
        #eventModal .btn-close:hover { opacity: 1; }
        #eventModal .modal-body { padding: 24px 26px; }
        #eventModal .modal-footer { border-top: 1px solid var(--tk-border); background: var(--tk-bg); padding: 14px 26px; }

        .ev-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px,1fr)); gap: 12px; margin-bottom: 20px; }
        .ev-item { background: var(--tk-bg); border: 1px solid var(--tk-border); border-radius: 11px; padding: 11px 13px; transition: border-color .15s var(--tk-ease); }
        .ev-item:hover { border-color: var(--tk-border-soft); }
        .ev-label { font-size: 10.5px; text-transform: uppercase; letter-spacing: .6px; font-weight: 800; color: var(--tk-text-muted); margin-bottom: 4px; display: flex; align-items: center; gap: 4px; }
        .ev-label svg { width: 11px; height: 11px; color: var(--tk-text-faint); }
        .ev-value { font-size: 13.5px; font-weight: 700; color: var(--tk-text); word-break: break-word; }
        .ev-desc-label { font-size: 13px; font-weight: 800; color: var(--tk-text); margin-bottom: 9px; display: flex; align-items: center; gap: 6px; }
        .ev-desc-label svg { width: 14px; height: 14px; color: var(--tk-text-muted); }
        .ev-desc { background: var(--tk-bg); border: 1px solid var(--tk-border); border-radius: 11px; padding: 13px 14px; font-size: 13.5px; line-height: 1.65; color: var(--tk-text); white-space: pre-wrap; min-height: 60px; }

        .tk-btn-close { background: var(--tk-surface); border: 1.5px solid var(--tk-border); color: var(--tk-text); font-weight: 700; font-size: 13.5px; padding: 9px 18px; border-radius: 10px; cursor: pointer; transition: border-color .15s var(--tk-ease), color .15s var(--tk-ease); }
        .tk-btn-close:hover { border-color: var(--tk-primary); color: var(--tk-primary-deep); }
        .tk-btn-close:focus-visible { outline: 2px solid var(--tk-primary); outline-offset: 2px; }

        /* Fade-in for calendar */
        .cal-wrapper { animation: cal-fade-in .35s var(--tk-ease); }
        @keyframes cal-fade-in { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

        @media (prefers-reduced-motion: reduce) {
            .cal-wrapper, .cal-event-item, .cal-nav-btn, .fc-event { animation: none !important; transition: none !important; }
        }

        @media (max-width: 768px) {
            .cal-wrapper { flex-direction: column; height: auto; }
            .cal-sidebar { width: 100%; border-right: none; border-bottom: 1px solid var(--tk-border); height: auto; max-height: 340px; }
            .cal-main { height: auto; overflow-y: visible; padding: 16px; }
        }
    </style>
</head>

<body>
    <?php
    $role = $_SESSION['role'];
    switch ($role) {
        case 'User':      include '../user/sidebar.php'; break;
        case 'mis':       include '../mis/sidebar.php'; break;
        case 'Admin':     include '../admin/sidebar.php'; break;
        case 'Super Admin': include '../super_admin/sidebar.php'; break;
        default: echo "<p>Unauthorized role.</p>"; exit;
    }
    ?>

    <div class="content-wrapper container-xxl flex-grow-1 container-p-y">
        <div class="cal-wrapper">

            <!-- ── Sidebar ──────────────────────────────────────── -->
            <div class="cal-sidebar" id="app-calendar-sidebar">
                <div class="cal-sidebar-head">
                    <div class="month-nav">
                        <button id="prevMonth" class="cal-nav-btn" title="Previous month" aria-label="Previous month">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M15 18l-6-6 6-6"/></svg>
                        </button>
                        <span class="month-label" id="monthButtonText">Loading…</span>
                        <button id="nextMonth" class="cal-nav-btn" title="Next month" aria-label="Next month">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M9 6l6 6-6 6"/></svg>
                        </button>
                    </div>
                </div>

                <div class="cal-sidebar-month-title">
                    <div class="label-group">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        <span>Events for <span id="eventMonthTitle" style="color:var(--tk-text);"></span></span>
                    </div>
                    <span class="cal-count-pill" id="eventCountPill">0</span>
                </div>

                <div class="cal-event-list" id="eventList"></div>

                <div class="cal-legend">
                    <div class="cal-legend-item">
                        <div class="cal-legend-dot" style="background:#7cb9ff;"></div>
                        Activity / Event
                    </div>
                    <div class="cal-legend-item">
                        <div class="cal-legend-dot" style="background:#f87171;"></div>
                        Room Reservation
                    </div>
                </div>
            </div>

            <!-- ── Calendar ─────────────────────────────────────── -->
            <div class="cal-main">
                <div id="calendar"></div>
            </div>
        </div>
    </div>

    <!-- Event detail modal -->
    <div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header" id="eventModalHeader">
                    <div>
                        <div class="modal-header-eyebrow" id="eventModalEyebrow">Activity</div>
                        <h5 id="eventTitle" class="modal-title"></h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="ev-grid">
                        <div class="ev-item">
                            <div class="ev-label">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/></svg>
                                Department
                            </div>
                            <div class="ev-value" id="eventDepartment"></div>
                        </div>
                        <div class="ev-item">
                            <div class="ev-label">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                                Date
                            </div>
                            <div class="ev-value" id="eventDate"></div>
                        </div>
                        <div class="ev-item">
                            <div class="ev-label">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                                Time
                            </div>
                            <div class="ev-value" id="eventTime"></div>
                        </div>
                        <div class="ev-item">
                            <div class="ev-label">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                Location
                            </div>
                            <div class="ev-value" id="eventLocation"></div>
                        </div>
                        <div class="ev-item" id="eventURLWrap">
                            <div class="ev-label">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                URL
                            </div>
                            <div class="ev-value"><a id="eventURL" href="#" target="_blank" rel="noopener" style="color:var(--tk-primary-deep); word-break:break-all;"></a></div>
                        </div>
                    </div>
                    <div class="ev-desc-label">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
                        Description
                    </div>
                    <div class="ev-desc" id="eventDescription"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="tk-btn-close" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="layout-overlay"></div>

    <!-- Template JS -->
    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/js/main.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const events = <?= json_encode($events); ?>;
            const calendarEl = document.getElementById('calendar');

            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                height: 'auto',
                fixedWeekCount: false,
                dayMaxEvents: 3,
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,dayGridWeek,listMonth'
                },
                buttonText: { today: 'Today', month: 'Month', week: 'Week', list: 'List' },
                events: events,

                eventContent: function (arg) {
                    return { html: `<div class="fc-event-title-only">${escapeHtml(arg.event.title)}</div>` };
                },

                eventClick: function (info) {
                    const isRoom = String(info.event.id).startsWith('res-');
                    const e = info.event.extendedProps;

                    setModalHeader(isRoom);
                    document.getElementById('eventTitle').innerText = info.event.title || '';
                    document.getElementById('eventDepartment').innerText = e.department || 'N/A';

                    const start = info.event.start ? moment(info.event.start).format('MMMM D, YYYY') : '';
                    const end = info.event.end ? moment(info.event.end).format('MMMM D, YYYY') : '';
                    document.getElementById('eventDate').innerText =
                        start && end ? (start === end ? start : `${start} – ${end}`) : (start || 'N/A');

                    document.getElementById('eventTime').innerText = info.event.start
                        ? (moment(info.event.start).format('h:mm A') +
                           (info.event.end ? ' – ' + moment(info.event.end).format('h:mm A') : ''))
                        : 'N/A';

                    document.getElementById('eventLocation').innerText = e.event_location || 'N/A';

                    const urlEl = document.getElementById('eventURL');
                    if (e.event_url) {
                        urlEl.href = e.event_url;
                        urlEl.innerText = e.event_url;
                        document.getElementById('eventURLWrap').style.display = '';
                    } else {
                        document.getElementById('eventURLWrap').style.display = 'none';
                    }

                    document.getElementById('eventDescription').innerText = e.description || 'N/A';

                    new bootstrap.Modal(document.getElementById('eventModal')).show();
                },

                datesSet: function () { updateMonthDisplay(); }
            });

            calendar.render();

            document.getElementById('prevMonth').addEventListener('click', () => calendar.prev());
            document.getElementById('nextMonth').addEventListener('click', () => calendar.next());

            function setModalHeader(isRoom) {
                const header = document.getElementById('eventModalHeader');
                const eyebrow = document.getElementById('eventModalEyebrow');
                header.classList.toggle('is-room', isRoom);
                eyebrow.innerText = isRoom ? 'Room Reservation' : 'Activity';
            }

            function updateMonthDisplay() {
                const month = moment(calendar.view.currentStart).format('MMMM YYYY');
                document.getElementById('monthButtonText').innerText = month;
                document.getElementById('eventMonthTitle').innerText = month;
                displayEventsForMonth(month);
            }

            function displayEventsForMonth(month) {
                const container = document.getElementById('eventList');
                const filtered = events.filter(e => moment(e.start).format('MMMM YYYY') === month);

                document.getElementById('eventCountPill').innerText = filtered.length;

                if (!filtered.length) {
                    container.innerHTML = `
                        <div class="cal-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                            <strong>No events this month</strong>
                            Nothing scheduled for ${escapeHtml(month)} yet.
                        </div>`;
                    return;
                }

                filtered.sort((a, b) => new Date(a.start) - new Date(b.start));

                let html = '';
                filtered.forEach((e, idx) => {
                    const isRoom = String(e.id).startsWith('res-');
                    const badgeClass = isRoom ? 'is-room' : 'is-activity';
                    const badgeLabel = isRoom ? 'Room Reservation' : 'Activity';

                    html += `
                        <div class="cal-event-item" tabindex="0" style="--event-color: ${escapeAttr(e.color || '#7cb9ff')};"
                             onclick='openEventFromSidebar(${JSON.stringify({
                                 id: e.id,
                                 title: e.title,
                                 department: e.department,
                                 start: e.start,
                                 end: e.end,
                                 location: e.event_location,
                                 url: e.event_url,
                                 description: e.description
                             })})'
                             onkeydown='if(event.key==="Enter"||event.key===" "){event.preventDefault();this.click();}'>
                            <span class="cal-event-badge ${escapeHtml(badgeClass)}">${escapeHtml(badgeLabel)}</span>
                            <h6>${escapeHtml(e.title)}</h6>
                            <div class="cal-event-item-meta">
                                <span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                                    ${escapeHtml(moment(e.start).format('MMM D, YYYY'))}
                                </span>
                                <span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                                    ${escapeHtml(moment(e.start).format('h:mm A'))}
                                </span>
                                ${e.event_location ? `<span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                    ${escapeHtml(e.event_location)}
                                </span>` : ''}
                            </div>
                        </div>`;
                });

                container.innerHTML = html;
            }

            // Open modal from sidebar click
            window.openEventFromSidebar = function(e) {
                const isRoom = String(e.id).startsWith('res-');
                setModalHeader(isRoom);

                document.getElementById('eventTitle').innerText = e.title || '';
                document.getElementById('eventDepartment').innerText = e.department || 'N/A';

                const start = e.start ? moment(e.start).format('MMMM D, YYYY') : '';
                const end = e.end ? moment(e.end).format('MMMM D, YYYY') : '';
                document.getElementById('eventDate').innerText = start && end ? (start === end ? start : `${start} – ${end}`) : (start || 'N/A');
                document.getElementById('eventTime').innerText = e.start
                    ? (moment(e.start).format('h:mm A') + (e.end ? ' – ' + moment(e.end).format('h:mm A') : ''))
                    : 'N/A';
                document.getElementById('eventLocation').innerText = e.location || 'N/A';

                const urlEl = document.getElementById('eventURL');
                if (e.url) {
                    urlEl.href = e.url; urlEl.innerText = e.url;
                    document.getElementById('eventURLWrap').style.display = '';
                } else {
                    document.getElementById('eventURLWrap').style.display = 'none';
                }

                document.getElementById('eventDescription').innerText = e.description || 'N/A';
                new bootstrap.Modal(document.getElementById('eventModal')).show();
            };

            updateMonthDisplay();

            function escapeHtml(s) {
                if (s === null || s === undefined) return '';
                return String(s)
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#39;');
            }
            function escapeAttr(s) { return escapeHtml(s).replaceAll('"', '%22'); }

            // Mobile sidebar toggle
            const menuToggle = document.querySelector('.layout-menu-toggle');
            const sidebar = document.getElementById('app-calendar-sidebar');
            const overlay = document.querySelector('.layout-overlay');

            if (menuToggle && sidebar && overlay) {
                menuToggle.addEventListener('click', () => {
                    sidebar.classList.toggle('open');
                    overlay.classList.toggle('open');
                });
                overlay.addEventListener('click', () => {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('open');
                });
            }
        });
    </script>

</body>
</html>