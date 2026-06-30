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
        'color' => '#2196f3',    // Blue
        'textColor' => '#ffffff'
    ];
}

// Room reservation events (Red)
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
        'color' => '#f44336', // Red
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
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- Template CSS (keeps your UI) -->
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


</head>

<body>
    <?php
    // keep include sidebars as you requested
    $role = $_SESSION['role'];
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

    <div class="content-wrapper container-xxl flex-grow-1 container-p-y">
        <div class="card app-calendar-wrapper">
            <div class="row g-0">
                <!-- Sidebar -->
                <div class="col border-end" id="app-calendar-sidebar">
                    <div class="border-bottom p-3 d-flex justify-content-center align-items-center">
                        <button id="prevMonth" class="btn btn-outline-primary me-3">←</button>
                        <span id="monthButtonText" class="fw-bold">Loading...</span>
                        <button id="nextMonth" class="btn btn-outline-primary ms-3">→</button>
                    </div>

                    <h5 class="fw-bold mt-3 px-3">Events for <span id="eventMonthTitle"></span></h5>
                    <div id="eventListContainer">
                        <div id="eventList" class="mt-2"></div>
                    </div>
                </div>

                <!-- Calendar -->
                <div class="col app-calendar-content">
                    <div id="calendar"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Event detail modal -->
    <div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="eventTitle" class="modal-title"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Department:</strong> <span id="eventDepartment"></span></p>
                    <p><strong>Date:</strong> <span id="eventDate"></span></p>
                    <p><strong>Time:</strong> <span id="eventTime"></span></p>
                    <p><strong>Location:</strong> <span id="eventLocation"></span></p>
                    <p><strong>URL:</strong> <a id="eventURL" href="#" target="_blank" rel="noopener"></a></p>
                    <p><strong>Description:</strong></p>
                    <div id="eventDescription" style="white-space:pre-wrap;"></div>
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
            // events provided by PHP
            const events = <?= json_encode($events); ?>;

            const calendarEl = document.getElementById('calendar');

            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                height: 770,
                events: events,

                // 🔥 TITLE-ONLY CLEAN EVENT RENDERING
                eventContent: function (arg) {
                    const title = `
                <div class="fc-event-title-only">${escapeHtml(arg.event.title)}</div>
            `;
                    return { html: title };
                },

                eventClick: function (info) {
                    const e = info.event.extendedProps;

                    document.getElementById('eventTitle').innerText = info.event.title || '';
                    document.getElementById('eventDepartment').innerText = e.department || '';

                    const start = info.event.start ? moment(info.event.start).format('MMMM D, YYYY') : '';
                    const end = info.event.end ? moment(info.event.end).format('MMMM D, YYYY') : '';

                    document.getElementById('eventDate').innerText =
                        start && end ? (start === end ? start : `${start} - ${end}`) : (start || '');

                    document.getElementById('eventTime').innerText =
                        info.event.start
                            ? (moment(info.event.start).format('h:mm A') +
                                (info.event.end ? ' - ' + moment(info.event.end).format('h:mm A') : ''))
                            : '';

                    document.getElementById('eventLocation').innerText = e.event_location || '';

                    const urlEl = document.getElementById('eventURL');
                    if (e.event_url) {
                        urlEl.href = e.event_url;
                        urlEl.innerText = e.event_url;
                        urlEl.style.display = 'inline';
                    } else {
                        urlEl.href = '#';
                        urlEl.innerText = 'N/A';
                        urlEl.style.display = 'inline';
                    }

                    document.getElementById('eventDescription').innerText = e.description || 'N/A';

                    new bootstrap.Modal(document.getElementById('eventModal')).show();
                },

                datesSet: function () {
                    updateMonthDisplay();
                }
            });

            calendar.render();

            document.getElementById('prevMonth').addEventListener('click', () => calendar.prev());
            document.getElementById('nextMonth').addEventListener('click', () => calendar.next());

            function updateMonthDisplay() {
                const month = moment(calendar.view.currentStart).format('MMMM YYYY');
                document.getElementById('monthButtonText').innerText = month;
                document.getElementById('eventMonthTitle').innerText = month;
                displayEventsForMonth(month);
            }

            function displayEventsForMonth(month) {
                const container = document.getElementById('eventList');
                const filtered = events.filter(e => moment(e.start).format('MMMM YYYY') === month);

                if (!filtered.length) {
                    container.innerHTML = '<p class="px-3">No events this month.</p>';
                    return;
                }

                filtered.sort((a, b) => new Date(a.start) - new Date(b.start));

                let html = '';

                filtered.forEach(e => {
                    const bg = e.color || '#ffffff';
                    const txt = e.textColor || '#000000';
                    const urlHtml = e.event_url
                        ? `<a href="${escapeAttr(e.event_url)}" target="_blank" rel="noopener" style="color:${txt}; text-decoration:underline;">${escapeHtml(e.event_url)}</a>`
                        : 'N/A';

                    html += `
            <div class="event-card" >
                <h6>${escapeHtml(e.title)}</h6>
                <p><strong>Department:</strong> ${escapeHtml(e.department || '')}</p>
                <p><strong>Date:</strong> ${escapeHtml(moment(e.start).format('MMMM D, YYYY'))}${e.end ? ' - ' + escapeHtml(moment(e.end).format('MMMM D, YYYY')) : ''}</p>
                <p><strong>Time:</strong> ${escapeHtml(moment(e.start).format('h:mm A'))}${e.end ? ' - ' + escapeHtml(moment(e.end).format('h:mm A')) : ''}</p>
                <p><strong>Location:</strong> ${escapeHtml(e.event_location || '')}</p>
                <p><strong>URL:</strong> ${urlHtml}</p>
                <p style="margin-bottom:0;"><strong>Description:</strong> ${escapeHtml(e.description || 'N/A')}</p>
            </div>`;
                });

                container.innerHTML = html;
            }

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

            function escapeAttr(s) {
                return escapeHtml(s).replaceAll('"', '%22');
            }

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