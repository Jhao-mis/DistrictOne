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

// Fetch all activities and approved room reservations
$stmt = $pdo->query("SELECT * FROM activities ORDER BY start_datetime");
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt_reservations = $pdo->query("
  SELECT rr.*, u.department 
  FROM room_reservations rr
  LEFT JOIN users u ON rr.user_id = u.id
  WHERE rr.status = 'Approved'
  ORDER BY rr.reservation_date
");

$reservations = $stmt_reservations->fetchAll(PDO::FETCH_ASSOC);

// Merge activities and reservations
$events = [];
foreach ($activities as $activity) {
    $eventDepartment = $activity['department'] ?? "All Departments";
    $bgcolor = $departmentColors[$eventDepartment]['bgcolor'] ?? "#b0f5f5"; // Default color
    $textcolor = $departmentColors[$eventDepartment]['textcolor'] ?? "#679e9e";
    
    $events[] = [
        'id' => $activity['id'],
        'title' => $activity['title'],
        'start' => $activity['start_datetime'],
        'end' => $activity['end_datetime'],
        'description' => $activity['description'],
        'color' => $bgcolor,
        'textColor' => $textcolor,
        'department' => $eventDepartment,
        'event_url' => $activity['event_url'],
        'event_location' => $activity['event_location']
    ];
}

foreach ($reservations as $reservation) {
  $events[] = [
      'id' => 'res-' . $reservation['id'], // Unique ID for reservations
      'title' => 'Room Reserved: ' . $reservation['room'],
      'start' => $reservation['reservation_date'] . ' ' . $reservation['start_time'],
      'end' => $reservation['reservation_date'] . ' ' . $reservation['end_time'],
      'description' => $reservation['purpose'], // Room reservation description (purpose)
      'color' => "#4caf50", // Green for room reservations
      'textColor' => "#ffffff",
      'department' => $reservation['department'] ?? 'Unknown Department',
      'event_url' => '', // No URL for simple reservations
      'event_location' => $reservation['room'],
      'room_reservation' => $reservation['room'] // Add this so frontend can display easily
  ];
}

// Department color mapping (same as before)
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

$conn->close();
?>



<!DOCTYPE html>

<html
  lang="en"
  class="light-style layout-menu-fixed"
  dir="ltr"
  data-theme="theme-default"
  data-assets-path="../assets/"
  data-template="vertical-menu-template-free"
>
  <head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"
    />

    <title>Calendar - District One</title>

    <meta name="description" content="" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/districtone.png" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
      rel="stylesheet"
    />

    <!-- Icons. Uncomment required icon fonts -->
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="../assets/vendor/libs/apex-charts/apex-charts.css" />

    <!-- Page CSS -->
    <link rel="stylesheet" href="../../assets/vendor/css/pages/app-calendar.css">
    <!-- Helpers -->
    <script src="../assets/vendor/js/helpers.js"></script>

    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
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

    <style>
      .app-brand-text {
            font-size: 20px !important;
            font-weight: bold;
            margin-left: 5px;
            margin-top: 10px;
            }
            .logo {
            margin-left: -30px;
             margin-top: 5px;
            }
        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            margin: 0;
            padding: 20px;
        }
        #calendar {
    max-width: 1000px;
    margin: auto;
    background-color: #fff;
    border-radius: 10px;
    box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
    padding: 15px;
}
.fc-daygrid-day {
    border: 1px solid #e8e8e8; /* Light border */
}

.fc-daygrid-day-top {
    font-size: 14px;
    font-weight: bold;
    color: #666;
}

.fc-event {
    border-radius: 5px;
    font-size: 12px;
    padding: 4px;
    border: none;
}

.fc-event-title {
    font-weight: 600;
}

.fc-toolbar-title {
    font-size: 22px;
    font-weight: bold;
    color: #333;
}

.fc-button {
    background-color: #f5f5f5;
    color: #333;
    border: none;
    font-size: 14px;
    padding: 6px 12px;
    border-radius: 5px;
}

.fc-button-active {
    background-color: #ddd;
}
        .fc-toolbar {
            color: white;
            padding: 10px;
            border-radius: 8px;
        }
        .fc-prev-button:hover,
.fc-next-button:hover,
.fc-today-button:hover,
.fc-month-button:hover,
.fc-agendaWeek-button:hover,
.fc-agendaDay-button:hover {
    background-color: lightgray !important;
    border-color: lightgray !important;
    color: black !important;
}
/* Calendar Sidebar Styling */
.col {
    width: 10px;
    background-color: #fff;
    padding: 20px;
    border-right: 1px solid #ddd;
}

/* Button Styling */
.btn-toggle-sidebar {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 10px;
    font-size: 16px;
    background-color: #7367F0;
    color: #fff;
    border: none;
    border-radius: 6px;
}

.btn-toggle-sidebar i {
    font-size: 18px;
}

/* Calendar Section */
.p-6 {
    padding: 20px !important;
}

/* Event Filters Section */
h5 {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 15px;
}

.form-check {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 16px;
}

.form-check-input {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.form-check-label {
    cursor: pointer;
}

/* Adjust spacing */
.mb-5 {
    margin-bottom: 12px !important;
}

.ms-2 {
    margin-left: 5px !important;
}
.fc-scroller {
    max-height: 600px; /* Adjust this to fit your layout */
    overflow-y: auto; /* Enable scrolling */
}
.fc-prev-button, .fc-next-button {
    font-size: 18px !important;
    background: none !important;
    border: none !important;
    color: #333 !important; /* Dark color for visibility */
}
/* All Departments */
#Alldepartment:checked {
    background-color: #969593 !important;
    border-color: #969593 !important;
}

/* Office of the General Manager */
#GMdepartment:checked {
    background-color: #b0f5f5 !important;
    border-color: #b0f5f5 !important;
}

/* Management Information Services Section */
#MISdepartment:checked {
    background-color: #fcc6c0 !important;
    border-color: #fcc6c0 !important;
}

/* Administrative Department */
#ADdepartment:checked {
    background-color: #cff799 !important;
    border-color: #cff799 !important;
}

/* Finance Department */
#FDdepartment:checked {
    background-color: #3cc74a !important;
    border-color: #3cc74a !important;
}

/* Commercial Department */
#COMdepartment:checked {
    background-color: #f8fc9d !important;
    border-color: #f8fc9d !important;
}

/* Technical Services Department */
#TSdepartment:checked {
    background-color: #cba5f2 !important;
    border-color: #cba5f2 !important;
}

/* Operations Department */
#OPdepartment:checked {
    background-color: #fab07a !important;
    border-color: #fab07a !important;
}

/* General styling for all checked checkboxes */
.form-check-input:checked {
    box-shadow: none !important;
    background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path fill="%23FFF" d="M13.485 1.929a1.5 1.5 0 0 1 0 2.121L6.363 11.172 2.515 7.323a1.5 1.5 0 1 1 2.121-2.122l2.121 2.122 5.657-5.657a1.5 1.5 0 0 1 2.121 0z"/></svg>') !important;
    background-repeat: no-repeat;
    background-position: center;
    background-size: 80%;
}

  /* Scrollable event list */
  #eventListContainer {
        max-height: 600px; /* Adjust height as needed */
        overflow-y: auto;
        padding-right: 10px; /* Avoid content cutting off */
    }

    /* Optional: Customize scrollbar */
    #eventListContainer::-webkit-scrollbar {
        width: 8px;
    }

    #eventListContainer::-webkit-scrollbar-thumb {
        background-color: #888;
        border-radius: 4px;
    }

    #eventListContainer::-webkit-scrollbar-thumb:hover {
        background-color: #555;
    }
      /* Event item styling */
      .event-item {
        padding: 10px 0;
        border-bottom: 1px solid #ddd; /* Line between events */
    }

    /* Color dot for department */
    .event-dot {
        height: 10px;
        width: 10px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 8px;
    }

    .event-title {
        font-weight: bold;
        display: flex;
        align-items: center;
    }

    .event-date {
        font-size: 0.9rem;
        font-weight: bold;
        color: #333;
        margin-bottom: 5px;
    }

    .event-time {
        font-size: 0.9rem;
        color: #666;
    }
    .bg-menu-theme .menu-inner > .menu-item.active > .menu-link {
  color: #fff;
  background-color: rgba(47, 144, 255, 0.63) !important;
}

.btn-outline-primary {
  color: #2793eb;
  border-color: #2793eb;
  background: transparent;
}
.btn-outline-primary:hover {
  color: #fff;
  background-color: #2793eb;
  border-color: #2793eb;
  box-shadow: 0 0.125rem 0.25rem 0 rgba(105, 108, 255, 0.4);
  transform: translateY(-1px);
}

.btn-check:focus + .btn-outline-primary, .btn-outline-primary:focus {
  color: #fff;
  background-color: #2793eb;
  border-color: #2793eb;
  box-shadow: none;
  transform: translateY(0);
}
.btn-check:checked + .btn-outline-primary, .btn-check:active + .btn-outline-primary, .btn-outline-primary:active, .btn-outline-primary.active, .btn-outline-primary.dropdown-toggle.show {
  color: #fff;
  background-color: #2793eb;
  border-color: #2793eb;
}
.btn-check:checked + .btn-outline-primary:focus, .btn-check:active + .btn-outline-primary:focus, .btn-outline-primary:active:focus, .btn-outline-primary.active:focus, .btn-outline-primary.dropdown-toggle.show:focus {
  box-shadow: none;
}
.btn-outline-primary.disabled, .btn-outline-primary:disabled {
  box-shadow: none;
}
.btn-outline-primary .badge {
  background: #2793eb;
  border-color: #2793eb;
  color: #fff;
}

.btn-outline-primary:hover .badge,
.btn-outline-primary:focus:hover .badge,
.btn-outline-primary:active .badge,
.btn-outline-primary.active .badge,
.show > .btn-outline-primary.dropdown-toggle .badge {
  background: #fff;
  border-color: #fff;
  color: #2793eb;
}
.bg-menu-theme .menu-inner > .menu-item.active:before {
  background-color: #2793eb;
}

    </style>

<script>
$(document).ready(function () {
    // Make events global
    let events = <?php echo json_encode($events); ?>;

    function updateMonthDisplay() {
        let currentMonth = $('#calendar').fullCalendar('getDate').format('MMMM YYYY');
        $("#monthButtonText").text(currentMonth);
        $("#eventMonthTitle").text(currentMonth);
        displayEventsForMonth(currentMonth);
    }

    function displayEventsForMonth(selectedMonth) {
        let eventListHTML = "";
        let filteredEvents = events.filter(event =>
            moment(event.start).format('MMMM YYYY') === selectedMonth
        );

        if (filteredEvents.length === 0) {
            eventListHTML = "<p>No events this month.</p>";
        } else {
            filteredEvents.forEach(event => {
                eventListHTML += `
<div class="card mb-2 p-3">

    <!-- Your content here -->

                        <h6 class="fw-bold">${event.title}</h6>
                        <p><strong>Department:</strong> ${event.department}</p>
                        <p><strong>Date:</strong> ${moment(event.start).format('MMMM D, YYYY')} - ${moment(event.end).format('MMMM D, YYYY')}</p>
                        <p><strong>Time:</strong> ${moment(event.start).format('h:mm A')} - ${moment(event.end).format('h:mm A')}</p>
                        <p><strong>Location:</strong> ${event.event_location}</p>
                        <p><strong>URL:</strong> <a href="${event.event_url}" target="_blank">${event.event_url}</a></p>
                        <p><strong>Description:</strong> ${event.description}</p>
                    </div>
                                                <hr style="border: 1px; margin: 0;">
                `;
            });
        }

        $("#eventList").html(eventListHTML);
    }

    $('#calendar').fullCalendar({
        header: {
            left: 'title, today',
            center: '',
            right: ''
        },
        events: events,
        viewRender: function (view) {
            setTimeout(updateMonthDisplay, 100); // Ensures update happens after view changes
        },
        height: 'auto',
        aspectRatio: 2,
        contentHeight: 600,
        scrollTime: '08:00:00'
    });

    // Month Navigation
    $("#prevMonth").on("click", function () {
        $('#calendar').fullCalendar('prev');
    });

    $("#nextMonth").on("click", function () {
        $('#calendar').fullCalendar('next');
    });

    updateMonthDisplay(); // Initial load
});
</script>

  </head>


<body>

<?php include 'sidebar.php' ?>

    <div class="content-wrapper">
        <!-- Content -->
        <div class="container-xxl flex-grow-1 container-p-y">
            
          
  <div class="card app-calendar-wrapper">
    <div class="row g-0">
      <!-- Calendar Sidebar -->
      <div class="col border-end" id="app-calendar-sidebar">
        <div class="border-bottom p-6 my-sm-0 mb-4">
        <div class="d-flex justify-content-center align-items-center">
    <button id="prevMonth" class="btn btn-outline-primary me-5">←</button>
    <span id="monthButtonText" class="fw-bold">Loading...</span>
    <button id="nextMonth" class="btn btn-outline-primary ms-5">→</button>
</div>
</div>
<h5 class="fw-bold mt-3">Events for <span id="eventMonthTitle"></span></h5>
<!-- Scrollable Event List -->
<div id="eventListContainer">
        <div id="eventList"></div>
    </div>
</div>


<script>
$(document).ready(function () {
    let currentMonth = moment();

    function updateMonthDisplay() {
        let formattedMonth = currentMonth.format('MMMM YYYY');
        $("#monthButtonText").text(formattedMonth);
        $("#eventMonthTitle").text(formattedMonth);
        fetchAndDisplayEvents(formattedMonth);
    }

    function fetchAndDisplayEvents(selectedMonth) {
        $.ajax({
            url: '../fetch_events.php',
            type: 'GET',
            dataType: 'json',
            success: function (events) {
                let eventListHTML = "";
                let filteredEvents = events.filter(event =>
                    moment(event.start).format('MMMM YYYY') === selectedMonth
                );

                if (filteredEvents.length === 0) {
                    eventListHTML = "<p>No events this month.</p>";
                } else {
                    filteredEvents.forEach(event => {
                        eventListHTML += `
                            <div class="event-item">
                                <p class="event-date">${moment(event.start).format('MMMM D, YYYY')}</p>
                                <div class="event-title">
                                    <span class="event-dot" style="background-color:${event.color || '#888'};"></span>
                                    ${event.title}
                                </div>
                                <p class="event-time">${moment(event.start).format('h:mm A')} - ${moment(event.end).format('h:mm A')}</p>
                                <p><strong>Location:</strong> ${event.event_location}</p>
                                <p><strong>URL:</strong> <a href="${event.event_url}" target="_blank">${event.event_url}</a></p>
                                <p><strong>Description:</strong> ${event.description}</p>
                            </div>
                        `;
                    });
                }

                $("#eventList").html(eventListHTML);
            },
            error: function () {
                $("#eventList").html("<p>Error loading events.</p>");
            }
        });
    }

    $("#prevMonth").on("click", function () {
        currentMonth.subtract(1, 'months');
        updateMonthDisplay();
    });

    $("#nextMonth").on("click", function () {
        currentMonth.add(1, 'months');
        updateMonthDisplay();
    });

    updateMonthDisplay();
});
</script>



      <!-- /Calendar Sidebar -->

      <!-- Calendar & Modal -->
       
      <div class="col app-calendar-content" style="width:1200px; height:auto; max-width: 80%;" >
        <div class="card shadow-none border-0" style="width:900px; height:auto; max-width: 80%;">
          <div class="card-body pb-0">

            <!-- FullCalendar -->
            <div id="calendar" class="fc fc-media-screen fc-direction-ltr fc-theme-standard" style="width:850px;">
            
        </div>
        <div class="app-overlay"></div>

        <!-- FullCalendar Offcanvas -->
        <div class="offcanvas offcanvas-end event-sidebar" tabindex="-1" id="addEventSidebar" aria-labelledby="addEventSidebarLabel">
          <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title" id="addEventSidebarLabel">Add Event</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
          </div>
          <div class="offcanvas-body">

            <form method="POST" action="">
              <div class="mb-6 form-control-validation fv-plugins-icon-container">
                <label class="form-label" for="eventTitle">Title</label>

                <input type="text" class="form-control" id="title" name="title" placeholder="Event Title">

              <div class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback"></div></div>
              <div class="mb-6">
              <label class="form-label" for="department">Department</label>
<div class="position-relative">
    <select class="select2 select-event-label form-select" id="department" name="department">
        <option value="All Departments" selected>All Departments</option>
        <option value="Office of the General Manager">Office of the General Manager</option>
        <option value="Management Information Services Section">Management Information Services Section</option>
        <option value="Administrative Department">Administrative Department</option>
        <option value="Finance Department">Finance Department</option>
        <option value="Commercial Department">Commercial Department</option>
        <option value="Technical Services Department">Technical Services Department</option>
        <option value="Operations Department">Operations Department</option>
    </select>
</div>

              
              <div class="mb-6 form-control-validation fv-plugins-icon-container">
                <label class="form-label" for="activity_date">Start Date</label>
                <input type="date" class="form-control flatpickr-input" id="activity_date" name="activity_date" placeholder="Date">

                <label class="form-label" for="activity_end_date">End Date</label>
                <input type="date" class="form-control flatpickr-input" id="activity_end_date" name="activity_end_date" placeholder="Date">

                
                <label class="form-label" for="start_time">Start Time</label>
                <input type="time" class="form-control flatpickr-input" name="start_time" id="start_time" required>
 
                
        <label class="form-label" for="end_time">End Time:</label>
        <input type="time" class="form-control flatpickr-input" name="end_time" id="end_time" required>

        
              <div class="mb-6">
                <label class="form-label" for="event_url">Event URL</label>
                <input type="url" class="form-control" id="event_url" name="event_url" placeholder="https://www.google.com">
              </div>
              <div class="mb-6">
                <label class="form-label" for="event_location">Location</label>
                <input type="text" class="form-control" id="event_location" name="event_location" placeholder="Enter Location">
              </div>
              <div class="mb-6">
                <label class="form-label" for="eventDescription">Description</label>
                <textarea class="form-control" name="description" id="description"></textarea>
              </div>
              <div class="d-flex justify-content-sm-between justify-content-start mt-6 gap-2">
                <div class="d-flex" style="margin-top:20px;">
                  <button type="submit" class="btn btn-primary btn-add-event me-4">Add Activity</button>
                  <button type="reset" class="btn btn-label-secondary btn-cancel me-sm-0 me-1" data-bs-dismiss="offcanvas">Cancel</button>
                </div>
                <button class="btn btn-label-danger btn-delete-event d-none">Delete</button>
              </div>
            <input type="hidden">
        </form>
          </div>
        </div>
      </div>
      <!-- /Calendar & Modal -->
    </div>
  </div>

        </div>
        <!-- / Content -->
        
        <div class="content-backdrop fade"></div>
      </div>
      
          <!-- Bootstrap Modal for Event Details -->
<div class="modal fade" id="eventModal" tabindex="-1" role="dialog" aria-labelledby="eventModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="eventTitle"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
      </div>
    </div>
  </div>
</div>

                    <!-- Account -->
                    
                          <!-- Profile Picture Preview -->
                     
                        
                   




            <!-- / Content -->

          

            <div class="content-backdrop fade"></div>
          </div>
          <!-- Content wrapper -->
        </div>
        <!-- / Layout page -->
      </div>

      <!-- Overlay -->
      <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <!-- / Layout wrapper -->



    <!-- Core JS -->
     
    <!-- build:js assets/vendor/js/core.js -->
    <script src="../assets/vendor/js/bootstrap.js"></script>

    <script src="../assets/vendor/js/menu.js"></script>
    <!-- endbuild -->

    <!-- Vendors JS -->

    <!-- Main JS -->
    <script src="../assets/js/main.js"></script>

    <!-- Page JS -->
    <script src="../assets/js/dashboards-analytics.js"></script>

 <!-- Place this tag in your head or just before your close body tag. -->
 <script async defer src="https://buttons.github.io/buttons.js"></script>
  </body>
</html>