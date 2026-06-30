<?php

require '../vendor/autoload.php';
require 'login_verification.php';
include '../db.php';

$username = $_SESSION['username'];

// Fetch user details
$query = $conn->prepare("SELECT id, profile_picture, cover_photo, department, firstname, middlename, lastname, email FROM users WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->store_result();
$query->bind_result($user_id, $profile_picture, $cover_photo, $department, $firstname, $middlename, $lastname, $email);
$query->fetch();
$query->close();

// Fetch all activities
$stmt = $pdo->query("SELECT * FROM activities ORDER BY start_datetime");
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all approved room reservations
$stmt_reservations = $pdo->query("SELECT * FROM room_reservations WHERE status = 'Approved' ORDER BY reservation_date");
$reservations = $stmt_reservations->fetchAll(PDO::FETCH_ASSOC);

// Department color mapping
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
    $department = $activity['department'] ?? "All Departments"; // Default to "All Departments" if not set

    // Assign colors based on department
    $bgcolor = $departmentColors[$department]['bgcolor'] ?? "#b0f5f5"; // Default color
    $textcolor = $departmentColors[$department]['textcolor'] ?? "#679e9e";

    $events[] = [
        'id' => $activity['id'], // Include the database id
        'title' => $activity['title'],
        'start' => $activity['start_datetime'],
        'end' => $activity['end_datetime'],
        'description' => $activity['description'],
        'color' => $bgcolor, // Background color
        'textColor' => $textcolor, // Text color
        'department' => $department, // Include department info
        'event_url' => $activity['event_url'], // Include event URL
        'event_location' => $activity['event_location'] // Include event location

    ];
}

// Process room reservations
foreach ($reservations as $reservation) {
    $events[] = [
        'id' => 'res-' . $reservation['id'], // Unique ID for reservation events
        'title' => 'Room Reserved: ' . $reservation['room'],
        'department' => $reservation['department'], // ✅ Fixed variable and added comma
        'start' => $reservation['reservation_date'] . ' ' . $reservation['start_time'],
        'end' => $reservation['reservation_date'] . ' ' . $reservation['end_time'],
        'description' => $reservation['purpose'],
        'color' => "#4caf50", // Green for reserved rooms
        'textColor' => "#ffffff",
        'event_location' => $reservation['room'],
        'editable' => false, // 🔥 Important: Mark reservation as NOT editable
        'deletable' => false, // 🔥 Optional: If you manually control delete button
        'type' => 'Room Reservation' // 🔥 Add type to detect in JS easily
    ];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $activity_date = $_POST['activity_date'];
    $activity_end_date = $_POST['activity_end_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $department = $_POST['department'];
    $event_url = $_POST['event_url']; // Get event URL
    $event_location = $_POST['event_location']; // Get event location

    $start_datetime = $activity_date . ' ' . $start_time;
    $end_datetime = $activity_end_date . ' ' . $end_time;

    $stmt = $pdo->prepare("INSERT INTO activities (title, description, start_datetime, end_datetime, department, event_url, event_location) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title, $description, $start_datetime, $end_datetime, $department, $event_url, $event_location]);

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

    <!-- Icons. Uncomment required icon fonts -->
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
        <!-- Content -->
        <div class="container-xxl flex-grow-1 container-p-y">
            <div class="card app-calendar-wrapper">
                <div class="row g-0">
                    <!-- Calendar Sidebar -->
                    <div class="col border-end" id="app-calendar-sidebar">
                        <div class="border-bottom p-6 my-sm-0 mb-4">
                            <button class="btn btn-primary btn-toggle-sidebar w-100" data-bs-toggle="offcanvas"
                                data-bs-target="#addEventSidebar" aria-controls="addEventSidebar"
                                style="background-color:#007bff">
                                <i class="icon-base bx bx-plus icon-16px me-2"></i>
                                <span class="align-middle">Add Event</span>
                            </button>
                        </div>
                        <div class="px-3 pt-2">


                            <!-- Filter -->
                            <div>
                                <h5>Department Filters</h5>
                            </div>

                            <div class="form-check form-check-secondary mb-5 ms-2">
                                <input class="form-check-input select-all" type="checkbox" id="Alldepartment"
                                    data-value="All departments" checked="">
                                <label class="form-check-label" for="Alldepartment">All Departments</label>
                            </div>

                            <div class="app-calendar-events-filter text-heading">
                                <div class="form-check form-check-danger mb-5 ms-2">
                                    <input class="form-check-input input-filter" type="checkbox" id="GMdepartment"
                                        data-value="Office of the General Manager" checked="">
                                    <label class="form-check-label" for="GMdepartment">Office of the General
                                        Manager</label>
                                </div>
                                <div class="form-check mb-5 ms-2">
                                    <input class="form-check-input input-filter" type="checkbox" id="MISdepartment"
                                        data-value="Management Information Services Section" checked="">
                                    <label class="form-check-label" for="MISdepartment">Management Information Services
                                        Section</label>
                                </div>
                                <div class="form-check form-check-warning mb-5 ms-2">
                                    <input class="form-check-input input-filter" type="checkbox" id="ADdepartment"
                                        data-value="Administrative Department" checked="">
                                    <label class="form-check-label" for="ADdepartment">Administrative Department</label>
                                </div>
                                <div class="form-check form-check-success mb-5 ms-2">
                                    <input class="form-check-input input-filter" type="checkbox" id="FDdepartment"
                                        data-value="Finance Department" checked="">
                                    <label class="form-check-label" for="FDdepartment">Finance Department</label>
                                </div>
                                <div class="form-check form-check-info ms-2">
                                    <input class="form-check-input input-filter" type="checkbox" id="COMdepartment"
                                        data-value="Commercial Department" checked="">
                                    <label class="form-check-label" for="COMdepartment">Commercial Department</label>
                                </div>
                                <div class="form-check form-check-info ms-2">
                                    <input class="form-check-input input-filter" type="checkbox" id="TSdepartment"
                                        data-value="Technical Services Department" checked="">
                                    <label class="form-check-label" for="TSdepartment">Technical Services
                                        Department</label>
                                </div>
                                <div class="form-check form-check-info ms-2">
                                    <input class="form-check-input input-filter" type="checkbox" id="OPdepartment"
                                        data-value="Operations Department" checked="">
                                    <label class="form-check-label" for="OPdepartment">Operations Department</label>
                                </div>
                                <div class="form-check form-check-primary mb-5 ms-2">
                                    <input class="form-check-input input-filter" type="checkbox" id="Reservation"
                                        data-value="Reservation" checked="">
                                    <label class="form-check-label" for="Reservation">Reservation</label>
                                </div>

                            </div>
                        </div>
                    </div>
                    <!-- /Calendar Sidebar -->

                    <!-- Bootstrap Modal for Event Details -->
                    <div class="modal fade" id="eventModal" tabindex="-1" role="dialog"
                        aria-labelledby="eventModalLabel" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="eventTitle"></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
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
                                    <button type="button" class="btn btn-danger" id="deleteEventBtn"
                                        data-id="<?= $activity['id'] ?>">Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Room Reservation Modal -->
                    <div class="modal fade" id="roomReservationModal" tabindex="-1" role="dialog"
                        aria-labelledby="roomReservationModalLabel" aria-hidden="false">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="roomReservationModalLabel">Room Reservation Details</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Close"></button>

                                </div>
                                <div class="modal-body">
                                    <p><strong>Title:</strong> <span id="roomReservationTitle"></span></p>
                                    <p><strong>Department:</strong> <span id="roomReservationDepartment"></span></p>
                                    <p><strong>Date:</strong> <span id="roomReservationDate"></span></p>
                                    <p><strong>Time:</strong> <span id="roomReservationTime"></span></p>
                                    <p><strong>Location:</strong> <span id="roomReservationLocation"></span></p>
                                    <p><strong>Description:</strong> <span id="roomReservationDescription"></span></p>
                                </div>
                                <div class="modal-footer">
                                </div>
                            </div>
                        </div>
                    </div>

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

                    <!-- Calendar & Modal -->
                    <div class="col app-calendar-content" style="width:1200px; height:auto; max-width: 80%;">
                        <div class="card shadow-none border-0" style="width:900px; height:auto; max-width: 80%;">
                            <div class="card-body pb-0">

                                <!-- FullCalendar -->
                                <div id="calendar" class="fc fc-media-screen fc-direction-ltr fc-theme-standard"
                                    style="width:850px;">

                                </div>
                                <div class="app-overlay"></div>

                                <!-- FullCalendar Offcanvas -->
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

                                                <div
                                                    class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                                </div>
                                            </div>
                                            <div class="mb-6">
                                                <label class="form-label" for="department">Department</label>
                                                <div class="position-relative">
                                                    <select class="select2 select-event-label form-select"
                                                        id="department" name="department">
                                                        <option value="All Departments" selected>All Departments
                                                        </option>
                                                        <option value="Office of the General Manager">Office of the
                                                            General Manager</option>
                                                        <option value="Management Information Services Section">
                                                            Management Information Services Section</option>
                                                        <option value="Administrative Department">Administrative
                                                            Department</option>
                                                        <option value="Finance Department">Finance Department</option>
                                                        <option value="Commercial Department">Commercial Department
                                                        </option>
                                                        <option value="Technical Services Department">Technical Services
                                                            Department</option>
                                                        <option value="Operations Department">Operations Department
                                                        </option>
                                                    </select>
                                                </div>


                                                <div class="mb-6 form-control-validation fv-plugins-icon-container">
                                                    <label class="form-label" for="activity_date">Start Date</label>
                                                    <input type="date" class="form-control flatpickr-input"
                                                        id="activity_date" name="activity_date" placeholder="Date">

                                                    <label class="form-label" for="activity_end_date">End Date</label>
                                                    <input type="date" class="form-control flatpickr-input"
                                                        id="activity_end_date" name="activity_end_date"
                                                        placeholder="Date">


                                                    <label class="form-label" for="start_time">Start Time</label>
                                                    <input type="time" class="form-control flatpickr-input"
                                                        name="start_time" id="start_time" required>


                                                    <label class="form-label" for="end_time">End Time:</label>
                                                    <input type="time" class="form-control flatpickr-input"
                                                        name="end_time" id="end_time" required>


                                                    <div class="mb-6">
                                                        <label class="form-label" for="event_url">Event URL</label>
                                                        <input type="text" class="form-control" id="event_url"
                                                            name="event_url" placeholder="https://www.google.com">
                                                    </div>
                                                    <div class="mb-6">
                                                        <label class="form-label" for="event_location">Location</label>
                                                        <input type="text" class="form-control" id="event_location"
                                                            name="event_location" placeholder="Enter Location">
                                                    </div>
                                                    <div class="mb-6">
                                                        <label class="form-label"
                                                            for="eventDescription">Description</label>
                                                        <textarea class="form-control" name="description"
                                                            id="description"></textarea>
                                                    </div>
                                                    <div
                                                        class="d-flex justify-content-sm-between justify-content-start mt-6 gap-2">
                                                        <div class="d-flex" style="margin-top:20px;">
                                                            <button type="button" id="confirmAdd"
                                                                class="btn btn-primary btn-add-event me-4">Add
                                                                Activity</button>
                                                            <button type="reset"
                                                                class="btn btn-label-secondary btn-cancel me-sm-0 me-1"
                                                                data-bs-dismiss="offcanvas">Cancel</button>
                                                        </div>
                                                        <button
                                                            class="btn btn-label-danger btn-delete-event d-none">Delete</button>
                                                    </div>
                                                    <input type="hidden">
                                        </form>
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

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/dashboards-analytics.js"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>

    <script>
        document.getElementById('confirmAdd').addEventListener('click', function () {
            Swal.fire({
                title: 'Are you sure?',
                text: "Do you want to add this activity?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, add it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Added!',
                        text: 'The activity has been successfully added.',
                        icon: 'success'
                    }).then(() => {
                        // Submit the form manually
                        this.closest('form').submit();
                    });
                }
            });
        });
    </script>

    <script>

        document.getElementById('deleteEventBtn').addEventListener('click', function () {
            var activityId = this.getAttribute('data-id'); // Get the ID from button attribute

            Swal.fire({
                title: 'Are you sure?',
                text: "This event will be deleted permanently!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('deleteEvent.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'id=' + encodeURIComponent(activityId)
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire(
                                    'Deleted!',
                                    data.message,
                                    'success'
                                ).then(() => {
                                    location.reload(); // Reload to refresh the activity list
                                });
                            } else {
                                Swal.fire(
                                    'Error!',
                                    data.message,
                                    'error'
                                );
                            }
                        })
                        .catch(error => {
                            Swal.fire(
                                'Error!',
                                'An error occurred while deleting.',
                                'error'
                            );
                            console.error('Error:', error);
                        });
                }
            });
        });

    </script>

    <script>
        $(document).ready(function () {
            function filterEvents() {
                let selectedDepartments = [];

                $(".input-filter:checked").each(function () {
                    selectedDepartments.push($(this).data("value"));
                });

                let allChecked = $("#Alldepartment").prop("checked");
                if (allChecked) {
                    $('#calendar').fullCalendar('removeEvents');
                    $('#calendar').fullCalendar('addEventSource', <?php echo json_encode($events); ?>);
                } else {
                    $('#calendar').fullCalendar('removeEvents');
                    let filteredEvents = <?php echo json_encode($events); ?>.filter(event =>
                        selectedDepartments.includes(event.department)
                    );
                    $('#calendar').fullCalendar('addEventSource', filteredEvents);
                }
            }


            // Listen to checkbox changes
            $(".input-filter, #Alldepartment").on("change", function () {
                if ($(this).attr("id") === "Alldepartment") {
                    $(".input-filter").prop("checked", $(this).prop("checked"));
                } else {
                    if (!$(this).prop("checked")) {
                        $("#Alldepartment").prop("checked", false);
                    } else if ($(".input-filter:checked").length === $(".input-filter").length) {
                        $("#Alldepartment").prop("checked", true);
                    }
                }
                filterEvents();
            });

            filterEvents(); // Initial filter on page load

            $('#calendar').fullCalendar({
                header: {
                    left: 'prev,next today',
                    right: 'title',
                    center: 'month,agendaWeek,agendaDay'
                },
                buttonIcons: {
                    prev: 'chevron-left', // Single left arrow
                    next: 'chevron-right' // Single right arrow
                },
                events: <?php echo json_encode($events); ?>,
                eventRender: function (event, element) {
                    element.css('background-color', event.color);
                    element.css('color', event.textColor);
                    element.qtip({
                        content: event.description,
                        style: {
                            classes: 'qtip-bootstrap'
                        }
                    });
                },
                height: 'auto',
                aspectRatio: 2,
                contentHeight: 600, // Set a fixed height for scrolling
                scrollTime: '08:00:00', // Default scroll position

                eventClick: function (event) {
                    let currentEventId = null;
                    currentEventId = event.id;

                    if (event.type === 'Room Reservation' || event.category === 'Room Reservation' || event.department === 'Room Reservation') {
                        // Room Reservation - No Edit/Delete
                        $('#roomReservationTitle').text(event.title);
                        $('#roomReservationDepartment').text(event.department);
                        $('#roomReservationDate').text(moment(event.start).format('MMMM D, YYYY'));
                        $('#roomReservationTime').text(moment(event.start).format('h:mm A') + " - " + moment(event.end).format('h:mm A'));
                        $('#roomReservationLocation').text(event.location || 'No location provided');
                        $('#roomReservationDescription').text(event.description || 'No description');

                        // Hide Edit and Delete buttons
                        $('#updateEventBtn').hide();
                        $('#deleteEventBtn').hide();

                        $('#roomReservationModal').modal('show');
                    } else {
                        // Normal Event - Allow Edit/Delete
                        $('#eventTitle').text(event.title);
                        $('#eventDepartment').text(event.department);
                        $('#eventDate').text(moment(event.start).format('MMMM D, YYYY'));
                        $('#eventTime').text(moment(event.start).format('h:mm A') + " - " + moment(event.end).format('h:mm A'));
                        $('#eventDescription').text(event.description);
                        $('#eventLocation').text(event.event_location);
                        $('#eventURL').attr('href', event.event_url).text(event.event_url);

                        $('#editDepartment').val(event.department);
                        $('#editDate').val(moment(event.start).format('YYYY-MM-DD'));
                        $('#editTime').val(moment(event.start).format('HH:mm'));
                        $('#editLocation').val(event.event_location);
                        $('#editURL').val(event.event_url);
                        $('#editDescription').val(event.description);

                        $('#deleteEventBtn').attr('data-id', event.id);

                        // Show Edit and Delete buttons
                        $('#updateEventBtn').show();
                        $('#deleteEventBtn').show();

                        $('#eventModal').modal('show');
                    }
                }

            });


            // Ensure the delete button gets the correct ID when the modal is shown
            $('#eventModal').on('show.bs.modal', function (event) {
                let button = $(event.relatedTarget); // The button that triggered the modal
                let activityId = button.data('id'); // Get ID from the clicked event

                $('#deleteEventBtn').attr('data-id', activityId); // Set the ID for delete button
            });

            // DELETE EVENT FUNCTION
            $('#deleteEventBtn').on('click', function () {
                let activityId = $(this).attr('data-id'); // Get the ID from the delete button

                if (!activityId) {
                    alert("Error: No activity ID found!");
                    return;
                }
            });

            // When update button is clicked, open the modal and set the ID
            $('#updateEventBtn').on('click', function () {
                let activityId = $('#deleteEventBtn').attr('data-id'); // Get ID from the delete button
                if (!activityId) {
                    alert("Error: No activity ID found!");
                    return;
                }

                $('#editEventId').val(activityId); // Store it in the hidden input field
                $('#editEventModal').modal('show');
            });


            // Confirm update and send to backend
            $(document).on('click', '#confirmUpdateBtn', function () {
                Swal.fire({
                    title: 'Are you sure?',
                    text: "Do you want to update this event?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, update it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        let activityId = $('#editEventId').val(); // Get the stored ID

                        if (!activityId) {
                            Swal.fire('Error', 'No activity ID found!', 'error');
                            return;
                        }

                        let updatedData = new URLSearchParams({
                            id: activityId,
                            title: $('#editTitle').val(),
                            event_url: $('#editURL').val(),
                            event_location: $('#editLocation').val(),
                            activity_date: $('#editActivityDate').val(),
                            activity_end_date: $('#editActivityEndDate').val(),
                            start_time: $('#editStartTime').val(),
                            end_time: $('#editEndTime').val(),
                            description: $('#editDescription').val()
                        }).toString();

                        fetch("editEvent.php", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/x-www-form-urlencoded"
                            },
                            body: updatedData
                        })
                            .then(response => response.text())
                            .then(data => {
                                try {
                                    let jsonData = JSON.parse(data);

                                    if (jsonData.status === "success") {
                                        Swal.fire('Updated!', 'The event has been updated.', 'success')
                                            .then(() => {
                                                $('#editEventModal').modal('hide');
                                                location.reload();
                                            });
                                    } else {
                                        Swal.fire('Error', "Error updating event: " + jsonData.message, 'error');
                                    }
                                } catch (error) {
                                    Swal.fire('Error', "Invalid JSON response: " + data, 'error');
                                }
                            })
                            .catch(error => {
                                Swal.fire('Error', "AJAX request failed: " + error, 'error');
                            });
                    }
                });
            });
        });

    </script>
</body>

</html>