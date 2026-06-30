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


    <!-- Removed FullCalendar and related CSS/JS -->
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
                    <!-- NOTE: Calendar sidebar & filters were removed per request -->

                    <!-- Top area with Add Event button -->
                    <div class="col-12">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">Add Calendar Activity</h4>
                            <!-- Add Event button (opens offcanvas) -->
                            <button class="btn btn-primary" data-bs-toggle="offcanvas" data-bs-target="#addEventSidebar"
                                aria-controls="addEventSidebar" style="background-color:#007bff">
                                <i class="icon-base bx bx-plus icon-16px me-2"></i>
                                <span class="align-middle">Add Event</span>
                            </button>
                        </div>
                    </div>

                    <!-- CALENDAR THINGS -->

<style>
/* Allow event titles to wrap */
.fc .fc-event-title-only {
    white-space: normal !important;
    overflow: visible !important;
    text-overflow: unset !important;
    word-wrap: break-word !important;
}

/* Calendar container styling */
#calendar {
    max-width: 100%;
    margin: 0 auto;
    padding: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border-radius: 12px;
    background-color: #f9f9f9;
}

/* Event hover effect */
.fc-event:hover {
    opacity: 0.85;
    cursor: pointer;
    transform: scale(1.02);
    transition: 0.2s;
}

/* Header buttons style */
.fc .fc-toolbar-chunk button {
    background-color: #7CB9FF;
    color: #fff;
    border: none;
    border-radius: 4px;
    margin: 0 2px;
}

.fc .fc-toolbar-chunk button:hover {
    background-color: #5DA0FF;
}

/* Active view button */
.fc .fc-button.fc-button-active {
    background-color: #5DA0FF;
}
</style>

<div id="calendar"></div>

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

        eventClick: function(info) {
            const e = info.event.extendedProps;

            document.getElementById('eventTitle').innerText = info.event.title;
            document.getElementById('eventDepartment').innerText = e.department || '';
            document.getElementById('eventDate').innerText = moment(info.event.start).format('MMMM D, YYYY');
            document.getElementById('eventTime').innerText = info.event.start
                ? moment(info.event.start).format('h:mm A') + (info.event.end ? ' - ' + moment(info.event.end).format('h:mm A') : '')
                : '';
            document.getElementById('eventLocation').innerText = e.event_location || '';
            const urlEl = document.getElementById('eventURL');
            if(e.event_url) { urlEl.href = e.event_url; urlEl.innerText = e.event_url; }
            else { urlEl.innerText = 'N/A'; }
            document.getElementById('eventDescription').innerText = e.description || '';

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
                        if(res.status === 'success') {
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

        eventDidMount: function(info) {
            // Color events
            if(info.event.extendedProps.type === 'Room Reservation') {
                info.el.style.backgroundColor = '#4caf50';
                info.el.style.border = '1px solid #388e3c';
            } else {
                info.el.style.backgroundColor = '#7CB9FF';
                info.el.style.border = '1px solid #5DA0FF';
            }
            info.el.style.color = '#ffffff';
            info.el.style.whiteSpace = 'normal';
        },

        eventContent: function(arg) {
            return { html: `<div class="fc-event-title-only">${arg.event.title}</div>` };
        }
    });

    calendar.render();

    // AJAX submit for edit
    $('#editEventForm').submit(function(e) {
        e.preventDefault();
        const data = $(this).serialize();

        $.post('editEvent.php', data, function(response) {
            const res = JSON.parse(response);
            if(res.status === 'success') {
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

                    <!-- CALENDAR THINGS -->



                    <!-- Event Modal -->
                    <div class="modal fade" id="eventModal" tabindex="-1" role="dialog">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="eventTitle"></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p><strong>Department:</strong> <span id="eventDepartment"></span></p>
                                    <p><strong>Date:</strong> <span id="eventDate"></span></p>
                                    <p><strong>Time:</strong> <span id="eventTime"></span></p>
                                    <p><strong>Location:</strong> <span id="eventLocation"></span></p>
                                    <p><strong>URL:</strong> <a id="eventURL" target="_blank"></a></p>
                                    <p><strong>Description:</strong> <span id="eventDescription"></span></p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-primary" id="updateEventBtn">Update</button>
                                    <button type="button" class="btn btn-danger" id="deleteEventBtn">Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Event Modal -->
                    <div class="modal fade" id="editEventModal" tabindex="-1" role="dialog">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Event</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <form id="editEventForm">
                                        <input type="hidden" name="id" id="editEventId">

                                        <div class="mb-3">
                                            <label>Title</label>
                                            <input type="text" class="form-control" name="title" id="editTitle"
                                                required>
                                        </div>

                                        <div class="mb-3">
                                            <label>Start Date</label>
                                            <input type="date" class="form-control" name="activity_date"
                                                id="editActivityDate" required>
                                        </div>

                                        <div class="mb-3">
                                            <label>End Date</label>
                                            <input type="date" class="form-control" name="activity_end_date"
                                                id="editActivityEndDate" required>
                                        </div>

                                        <div class="mb-3">
                                            <label>Start Time</label>
                                            <input type="time" class="form-control" name="start_time" id="editStartTime"
                                                required>
                                        </div>

                                        <div class="mb-3">
                                            <label>End Time</label>
                                            <input type="time" class="form-control" name="end_time" id="editEndTime"
                                                required>
                                        </div>

                                        <div class="mb-3">
                                            <label>Event URL</label>
                                            <input type="text" class="form-control" name="event_url" id="editURL">
                                        </div>

                                        <div class="mb-3">
                                            <label>Location</label>
                                            <input type="text" class="form-control" name="event_location"
                                                id="editLocation">
                                        </div>

                                        <div class="mb-3">
                                            <label>Description</label>
                                            <textarea class="form-control" name="description"
                                                id="editDescription"></textarea>
                                        </div>

                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>



                    <!-- Edit Event Modal -->
                    <div id="editEventModal" class="modal fade" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Event</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <form id="editEventForm">
                                        <input type="hidden" id="editEventId">

                                        <div class="form-group">
                                            <label for="editTitle">Title:</label>
                                            <input type="text" id="editTitle" class="form-control">
                                        </div>

                                        <div class="form-group">
                                            <label for="editURL">Event URL:</label>
                                            <input type="text" id="editURL" class="form-control">
                                        </div>

                                        <div class="form-group">
                                            <label for="editLocation">Location:</label>
                                            <input type="text" id="editLocation" class="form-control">
                                        </div>

                                        <div class="form-group">
                                            <label for="editActivityDate">Start Date:</label>
                                            <input type="date" id="editActivityDate" class="form-control">
                                        </div>

                                        <div class="form-group">
                                            <label for="editActivityEndDate">End Date:</label>
                                            <input type="date" id="editActivityEndDate" class="form-control">
                                        </div>

                                        <div class="form-group">
                                            <label for="editStartTime">Start Time:</label>
                                            <input type="time" id="editStartTime" class="form-control">
                                        </div>

                                        <div class="form-group">
                                            <label for="editEndTime">End Time:</label>
                                            <input type="time" id="editEndTime" class="form-control">
                                        </div>

                                        <div class="form-group">
                                            <label for="editDescription">Description:</label>
                                            <textarea id="editDescription" class="form-control"></textarea>
                                        </div>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" id="confirmUpdateBtn" class="btn btn-primary">Save
                                        Changes</button>
                                    <button type="button" class="btn btn-secondary"
                                        data-bs-dismiss="modal">Cancel</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Offcanvas Add Event (kept) -->
                    <div class="offcanvas offcanvas-end event-sidebar" tabindex="-1" id="addEventSidebar"
                        aria-labelledby="addEventSidebarLabel">
                        <div class="offcanvas-header border-bottom">
                            <h5 class="offcanvas-title" id="addEventSidebarLabel">Add Event</h5>
                            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"
                                aria-label="Close"></button>
                        </div>
                        <div class="offcanvas-body">
                            <form method="POST" action="">
                                <div class="mb-6 form-control-validation fv-plugins-icon-container">
                                    <label class="form-label" for="eventTitle">Title</label>
                                    <input type="text" class="form-control" id="title" name="title"
                                        placeholder="Event Title">
                                </div>

                                <div class="mb-6">
                                    <label class="form-label" for="department">Department</label>
                                    <div class="position-relative">
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
                                </div>

                                <div class="mb-6 form-control-validation fv-plugins-icon-container">
                                    <label class="form-label" for="activity_date">Start Date</label>
                                    <input type="date" class="form-control flatpickr-input" id="activity_date"
                                        name="activity_date" placeholder="Date">

                                    <label class="form-label" for="activity_end_date">End Date</label>
                                    <input type="date" class="form-control flatpickr-input" id="activity_end_date"
                                        name="activity_end_date" placeholder="Date">

                                    <label class="form-label" for="start_time">Start Time</label>
                                    <input type="time" class="form-control flatpickr-input" name="start_time"
                                        id="start_time" required>

                                    <label class="form-label" for="end_time">End Time:</label>
                                    <input type="time" class="form-control flatpickr-input" name="end_time"
                                        id="end_time" required>
                                </div>

                                <div class="mb-6">
                                    <label class="form-label" for="event_url">Event URL</label>
                                    <input type="text" class="form-control" id="event_url" name="event_url"
                                        placeholder="https://www.google.com">
                                </div>

                                <div class="mb-6">
                                    <label class="form-label" for="event_location">Location</label>
                                    <input type="text" class="form-control" id="event_location" name="event_location"
                                        placeholder="Enter Location">
                                </div>

                                <div class="mb-6">
                                    <label class="form-label" for="eventDescription">Description</label>
                                    <textarea class="form-control" name="description" id="description"></textarea>
                                </div>

                                <div class="d-flex justify-content-sm-between justify-content-start mt-6 gap-2">
                                    <div class="d-flex" style="margin-top:20px;">
                                        <button type="submit" id="confirmAdd"
                                            class="btn btn-primary btn-add-event me-4">Add Activity</button>
                                        <button type="reset" class="btn btn-label-secondary btn-cancel me-sm-0 me-1"
                                            data-bs-dismiss="offcanvas">Cancel</button>
                                    </div>
                                    <button class="btn btn-label-danger btn-delete-event d-none">Delete</button>
                                </div>
                            </form>
                        </div>
                    </div>

                </div> <!-- .row -->
                <div class="content-backdrop fade"></div>
            </div> <!-- .card -->
            <div class="content-backdrop fade"></div>
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

    <script>
        // Example: wire up edit modal save button (you can expand with AJAX)
        document.getElementById('confirmUpdateBtn')?.addEventListener('click', function () {
            // Basic client-side handler placeholder.
            // You should implement AJAX to save edits or submit a hidden form.
            alert('Save changes clicked. Implement update logic (AJAX or form submit).');
            // Close modal (Bootstrap 5)
            const modalEl = document.getElementById('editEventModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        });

        // Example: wire up delete button placeholder
        document.getElementById('deleteEventBtn')?.addEventListener('click', function () {
            if (!confirm('Are you sure you want to delete this event?')) return;
            // Implement deletion (AJAX a POST to a delete endpoint)
            alert('Delete action requested. Implement deletion logic on server.');
        });
    </script>

</body>

</html>