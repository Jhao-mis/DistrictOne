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

    header("Location: super_add_activity.php");
    exit();
}

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


    <title>Add Calendar Activity - District One</title>

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
        max-width: 100%;
        overflow-x: hidden;
    }

    .profile-container {
        position: relative;
        height: 130px;
        left:50px;
        bottom:170px;
        display: flex;
        align-items: center; /* Align profile picture vertically */
        justify-content: flex-start; /* Place profile picture near the cover photo */
    }


    .profile-img {
        border: 4px solid #fff; /* White border around the profile picture */
        background-color: white;
        border-radius: 50%; /* Circular border */
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); /* Optional: add a shadow for better visibility */
        margin-left: 10px; /* Adjust this value to position the image properly */
        width: 150px; /* Increase size (adjust as needed) */
        height: 150px; /* Ensure it remains a circle */
    }
/* Profile Card Styling */
.profile-card {
    width: 1370px;
    border-radius: 10px;
    margin-bottom: -50px;
    max-width: 100%;
    margin-top: 30px;
    margin-left: 25px;
    background: #ffffff; /* Ensure white background */
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    padding-bottom: 150px; /* Ensure padding at the bottom */
    min-height: 200px; /* Add height to accommodate profile container */
}

/* Cover Photo Styling */
.cover-photo-container {
    position: relative;
    width: 100%;
    height: 300px;
}

.cover-img {
    width: 100%;
    height: 300px;
    object-fit: cover;
}

/* Edit Cover Button */
.edit-cover-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #fff;
    padding: 5px 10px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 16px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
}

/* Profile Picture */


.profile-img {
    width: 150px;
    height: 150px;
    margin-top: 40px;
    border-radius: 50%;
    object-fit: cover;
}

/* User Info */
.user-info {
    margin-left: 20px;
    margin-top: 140px;
}

.user-name {
    font-size: 30px;
    font-weight: bold;
}

.user-details {
    font-size: 20px;
    color: gray;
}
.edit-cover-container {
        position: absolute;
        bottom: 10px;
        left: 87%;
        transform: translateX(-50%);
        background: rgba(0, 0, 0, 0.5);
        color: white;
        padding: 5px 10px;
        border-radius: 5px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px;
        opacity: 0; /* Initially hidden */
        transition: opacity 0.3s ease-in-out; /* Fade-in effect */
        z-index: 1;
    }

    .save-btn {
        position: absolute;
        bottom: 10px;
        left: 95%;
        transform: translateX(-50%);
        background: rgba(0, 0, 0, 0.5);
        color: white;
        padding: 3px 10px;
        border-radius: 5px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px;
        opacity: 0; /* Initially hidden */
        transition: opacity 0.3s ease-in-out; /* Fade-in effect */
        z-index: 1;
    }

    input[type="file"] {
        display: none;
    }
    /* Show buttons when the cover photo is hovered */
    .cover-photo-container:hover .save-btn,
    .cover-photo-container:hover .edit-cover-container {
        display: inline-block;
        opacity: 1;
    }

        .save-btn.show {
            display: inline-block; /* Show when the image is selected */
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
.custom-outline-blue2 {
    background-color: white;
    color: #007bff; /* Blue text */
    border: 2px solid #007bff; /* Blue border */
    padding: 8px 16px;
    border-radius: 20px;
    cursor: pointer;
    transition: 0.3s ease-in-out;
    max-width: 200px;
    display: block;
    margin: 0 auto; /* Centers button */
    margin-top:10px;
    text-align: center;
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
    .custom-outline-blue {
    background-color: white;
    color: #007bff; /* Blue text */
    border: 2px solid #007bff; /* Blue border */
    padding: 8px 16px;
    border-radius: 20px;
    cursor: pointer;
    transition: 0.3s ease-in-out;
    max-width: 200px;
    display: block;
    margin: 0 auto; /* Centers button */
    text-align: center;
}

.custom-outline-blue:hover {
    background-color: #007bff; /* Blue background on hover */
    color: white; /* White text on hover */
}
        .form-check-inline {
            display: inline-flex !important;
            align-items: center;
        }

        .form-check-input {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            width: 18px;
            height: 18px;
            border: 2px solid #007bff;
            border-radius: 50%;
            display: inline-block;
            position: relative;
            cursor: pointer;
        }

            /* Ensure the checkmark is visible */
            .form-check-input:checked {
                background-color: #007bff;
                border: 2px solid #007bff;
            }

                /* Add the inner circle */
                .bg-menu-theme .menu-inner > .menu-item.active > .menu-link {
  color: #fff;
  background-color: rgba(47, 144, 255, 0.63) !important;
}
.bg-menu-theme .menu-sub > .menu-item.active > .menu-link:not(.menu-toggle):before {
  background-color: #2793eb !important;
  border: 3px solid #e7e7ff !important;
}

.bg-menu-theme .menu-inner > .menu-item.active:before {
  background-color: #2793eb;
}

.swal2-container {
        z-index: 99999 !important;
    }

    </style>

<script>
  $(document).ready(function() {
    function filterEvents() {
        let selectedDepartments = [];

        $(".input-filter:checked").each(function() {
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
    $(".input-filter, #Alldepartment").on("change", function() {
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
        eventRender: function(event, element) {
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
        
        eventClick: function(event) {
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
    $('#eventModal').on('show.bs.modal', function(event) {
        let button = $(event.relatedTarget); // The button that triggered the modal
        let activityId = button.data('id'); // Get ID from the clicked event

        $('#deleteEventBtn').attr('data-id', activityId); // Set the ID for delete button
    });

    // DELETE EVENT FUNCTION
$('#deleteEventBtn').on('click', function() {
    let activityId = $(this).attr('data-id'); // Get the ID from the delete button

    if (!activityId) {
        alert("Error: No activity ID found!");
        return;
    }
  });

// When update button is clicked, open the modal and set the ID
  $('#updateEventBtn').on('click', function() { 
    let activityId = $('#deleteEventBtn').attr('data-id'); // Get ID from the delete button
    if (!activityId) {
        alert("Error: No activity ID found!");
        return;
    }
    
    $('#editEventId').val(activityId); // Store it in the hidden input field
    $('#editEventModal').modal('show');
});


// Confirm update and send to backend
$(document).on('click', '#confirmUpdateBtn', function() {
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

            fetch("update_activity.php", {
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
          <button class="btn btn-primary btn-toggle-sidebar w-100" data-bs-toggle="offcanvas" data-bs-target="#addEventSidebar" aria-controls="addEventSidebar" style="background-color:#007bff">
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
            <input class="form-check-input select-all" type="checkbox" id="Alldepartment" data-value="All departments" checked="">
            <label class="form-check-label" for="Alldepartment">All Departments</label>
            </div>

          <div class="app-calendar-events-filter text-heading">
            <div class="form-check form-check-danger mb-5 ms-2">
              <input class="form-check-input input-filter" type="checkbox" id="GMdepartment" data-value="Office of the General Manager" checked="">
              <label class="form-check-label" for="GMdepartment">Office of the General Manager</label>
            </div>
            <div class="form-check mb-5 ms-2">
              <input class="form-check-input input-filter" type="checkbox" id="MISdepartment" data-value="Management Information Services Section" checked="">
              <label class="form-check-label" for="MISdepartment">Management Information Services Section</label>
            </div>
            <div class="form-check form-check-warning mb-5 ms-2">
              <input class="form-check-input input-filter" type="checkbox" id="ADdepartment" data-value="Administrative Department" checked="">
              <label class="form-check-label" for="ADdepartment">Administrative Department</label>
            </div>
            <div class="form-check form-check-success mb-5 ms-2">
              <input class="form-check-input input-filter" type="checkbox" id="FDdepartment" data-value="Finance Department" checked="">
              <label class="form-check-label" for="FDdepartment">Finance Department</label>
            </div>
            <div class="form-check form-check-info ms-2">
              <input class="form-check-input input-filter" type="checkbox" id="COMdepartment" data-value="Commercial Department" checked="">
              <label class="form-check-label" for="COMdepartment">Commercial Department</label>
            </div>
            <div class="form-check form-check-info ms-2">
              <input class="form-check-input input-filter" type="checkbox" id="TSdepartment" data-value="Technical Services Department" checked="">
              <label class="form-check-label" for="TSdepartment">Technical Services Department</label>
            </div>
            <div class="form-check form-check-info ms-2">
              <input class="form-check-input input-filter" type="checkbox" id="OPdepartment" data-value="Operations Department" checked="">
              <label class="form-check-label" for="OPdepartment">Operations Department</label>
            </div>
            <div class="form-check form-check-primary mb-5 ms-2">
  <input class="form-check-input input-filter" type="checkbox" id="Reservation" data-value="Reservation" checked="">
  <label class="form-check-label" for="Reservation">Reservation</label>
</div>

          </div>
        </div>
      </div>
      <!-- /Calendar Sidebar -->

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
        <button type="button" class="btn btn-primary" id="updateEventBtn">Update</button>
        <button type="button" class="btn btn-danger" id="deleteEventBtn" data-id="<?= $activity['id'] ?>">Delete</button>

      </div>
    </div>
  </div>
</div>

<!-- Room Reservation Modal -->
<div class="modal fade" id="roomReservationModal" tabindex="-1" role="dialog" aria-labelledby="roomReservationModalLabel" aria-hidden="false">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title" id="roomReservationModalLabel">Room Reservation Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

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
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
                <button type="button" id="confirmUpdateBtn" class="btn btn-primary">Save Changes</button>
                <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

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
                <input type="text" class="form-control" id="event_url" name="event_url" placeholder="https://www.google.com">
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
                  <button type="button" id="confirmAdd" class="btn btn-primary btn-add-event me-4">Add Activity</button>
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

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.getElementById('confirmAdd').addEventListener('click', function() {
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

document.getElementById('deleteEventBtn').addEventListener('click', function() {
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
            fetch('delete_activity.php', {
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



<!-- Add SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>




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
