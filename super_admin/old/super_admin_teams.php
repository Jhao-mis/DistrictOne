<?php

include '../db.php';
require '../vendor/autoload.php';
require 'login_verification.php';


$username = $_SESSION['username'];
$query = $conn->prepare("SELECT users.id, users.profile_picture, users.cover_photo, users.department, users.firstname, users.middlename, users.lastname, users.email, personal_data_sheet.position
                         FROM users 
                         LEFT JOIN personal_data_sheet ON users.id = personal_data_sheet.user_id 
                         WHERE users.username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->store_result();
$query->bind_result($user_id, $profile_picture, $cover_photo, $department, $firstname, $middlename, $lastname, $email, $position);
$query->fetch();
$query->close();

$same_dept_query = $conn->prepare("SELECT users.id, users.firstname, users.middlename, users.lastname, users.profile_picture, users.email, users.department, personal_data_sheet.position 
                                   FROM users 
                                   LEFT JOIN personal_data_sheet ON users.id = personal_data_sheet.user_id 
                                   WHERE users.department = ? AND users.username != ?");
$same_dept_query->bind_param("ss", $department, $username);
$same_dept_query->execute();
$same_dept_result = $same_dept_query->get_result();
$same_department_users = $same_dept_result->fetch_all(MYSQLI_ASSOC);
$same_dept_query->close();

$result = $conn->query("SELECT * FROM image_slider");

$images = [];
while ($row = $result->fetch_assoc()) {
    $images[] = $row['image_url'];
}


// If profile picture is empty, set the default profile picture
if (empty($profile_picture)) {
    $profile_picture = '../assets/img/avatars/default_dp.jpg';
}
if (empty($cover_photo)) {
    $cover_photo = '../assets/img/avatars/default_cover.png';
}

$_SESSION['profile_picture'] = $profile_picture;
$_SESSION['cover_photo'] = $cover_photo;

$query = $conn->prepare("SELECT name_extension, personal_date_of_birth, place_of_birth, sex, civil_status, citizenship, telephone_no, mobile_no FROM personal_data_sheet WHERE user_id = ?");
$query->bind_param("i", $user_id);
$query->execute();
$query->store_result();
$query->bind_result($name_extension, $personal_date_of_birth, $place_of_birth, $sex, $civil_status, $citizenship, $telephone_no, $mobile_no);
$query->fetch();
$query->close();

$query = $conn->prepare("SELECT spouse_first_name, spouse_extension_name, spouse_middle_name, spouse_last_name, father_first_name, father_name_extension, father_middle_name, father_last_name, mother_first_name, mother_middle_name, mother_last_name FROM family_background WHERE user_id = ?");
$query->bind_param("i", $user_id);
$query->execute();
$query->store_result();
$query->bind_result($spouse_first_name, $spouse_extension_name, $spouse_middle_name, $spouse_last_name, $father_first_name, $father_name_extension, $father_middle_name, $father_last_name, $mother_first_name, $mother_middle_name, $mother_last_name);
$query->fetch();
$query->close();

$query = $conn->prepare("SELECT elementarySchool, highSchool, college, vocTradeCourse, graduateSchool FROM educational_background WHERE user_id = ?");
$query->bind_param("i", $user_id);
$query->execute();
$query->store_result();
$query->bind_result($elementarySchool, $highSchool, $college, $vocTradeCourse, $graduateSchool);
$query->fetch();
$query->close();

// Ensure variables are set to avoid undefined variable warnings
$name_extension = $name_extension ?? '';
$personal_date_of_birth = $personal_date_of_birth ?? '';
// Handle messages
$successMessage = "";
$errorMessage = "";

// File upload settings
$allowed_types = ["image/jpeg", "image/jpg", "image/png"];
$max_size = 2 * 1024 * 1024; // 2MB limit
$upload_dir = "uploads/";

// Ensure uploads directory exists
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Process Cover Photo Upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['cover_photo'])) {
    $cover_photo = $_FILES['cover_photo'];

    // Debugging: Check if file is uploaded
    if ($cover_photo["error"] !== 0) {
        $errorMessage = "Error: Cover photo upload error. Code: " . $cover_photo["error"];
    } elseif (!in_array($cover_photo["type"], $allowed_types)) {
        $errorMessage = "Error: Only JPG, JPEG, and PNG files are allowed.";
    } elseif ($cover_photo["size"] > $max_size) {
        $errorMessage = "Error: Cover photo size must be less than 2MB.";
    } else {
        // Rename file with timestamp
        $cover_name = time() . "_cover_" . basename($cover_photo["name"]);
        $cover_path = $upload_dir . $cover_name;

        // Move the uploaded file
        if (move_uploaded_file($cover_photo["tmp_name"], $cover_path)) {
            // Update cover photo in the database
            $stmt = $conn->prepare("UPDATE users SET cover_photo = ? WHERE id = ?");
            $stmt->bind_param("si", $cover_path, $user_id);

            if ($stmt->execute()) {
                $_SESSION['cover_photo'] = $cover_photo ?: 'uploads/default_cover.png'; // Update session
                // Fetch the updated cover photo from the database after update
                $query = $conn->prepare("SELECT cover_photo FROM users WHERE id = ?");
                $query->bind_param("i", $user_id);
                $query->execute();
                $query->store_result();
                $query->bind_result($new_cover_photo);
                $query->fetch();
                $query->close();
                // Update session with the new cover photo
                $_SESSION['cover_photo'] = $new_cover_photo;
                header("Location: admin_profile.php?success=1");
                exit();
            } else {
                $errorMessage = "Error: Database update failed.";
            }
            $stmt->close();
        } else {
            $errorMessage = "Error: Failed to upload cover photo.";
        }
    }
}


$stmt = $pdo->query("SELECT * FROM activities ORDER BY start_datetime");
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
foreach ($activities as $activity) {
    // Fetch event department
    $eventDepartment = $activity['department'] ?? "All Departments"; // Default to "All Departments"

    // Only add events that belong to the user's department OR "All Departments"
    if ($eventDepartment == "All Departments" || $eventDepartment == $department) {
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

    header("Location: add_activity.php");
    exit();
}

$sql = "SELECT * FROM announcements ORDER BY created_at DESC";
$result = $conn->query($sql);

$client_department = $department; // This is fetched from the logged-in user

if ($client_department == "All Departments") {
    $sql = "SELECT * FROM announcements ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
} else {
    $sql = "SELECT * FROM announcements WHERE department = ? OR department = 'All Departments' ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $client_department);
}


$stmt->execute();
$result = $stmt->get_result();

?>


<!-- Display messages -->
<?php if (!empty($successMessage)): ?>
    <script>alert("<?php echo $successMessage; ?>");</script>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
    <script>alert("<?php echo $errorMessage; ?>");</script>
<?php endif; ?>




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

    <title>Dashboard - District One</title>

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

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Calendar CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/qtip2/3.0.3/jquery.qtip.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qtip2/3.0.3/jquery.qtip.min.js"></script>
    <!-- Helpers -->
    <script src="../assets/vendor/js/helpers.js"></script>

    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="../assets/js/config.js"></script>

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
        padding: 10px;
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
/* Assuming your cover photo is already positioned and sized */
.cover-upload-container {
    position: absolute;  /* Position it absolutely relative to the parent container */
    top: 50%;  /* Adjust top value to place the form where you want */
    left: 50%;  /* Center horizontally */
    transform: translate(-50%, -50%);  /* Adjust to truly center the form */
    z-index: 10;  /* Ensure this stays above the cover photo */
    padding: 20px;
    background-color: rgba(255, 255, 255, 0.8);  /* Optional background to make the form readable */
    border-radius: 8px;
}

/* For the profile header or cover photo */
.user-profile-header {
    position: relative;  /* Make sure it's a positioned container */
    top: -50px;  /* Adjust top value to place the form where you want */
    left: 20px;  /* Center horizontally */

}

/* Optional: Styling for the cover photo */
.cover-photo {
    width: 100%;
    height: auto;
    position: relative;
    z-index: 1;
}
.user-profile-info{
    top: 20px;  /* Adjust top value to place the form where you want */
}
.bg-menu-theme .menu-inner > .menu-item.active > .menu-link {
  color: #fff;
  background-color: rgba(47, 144, 255, 0.63) !important;
}
.nav-pills .nav-link.active, .nav-pills .nav-link.active:hover, .nav-pills .nav-link.active:focus {
  background-color: #2793eb;
  color: #fff;
  box-shadow: 0 2px 4px 0 rgba(39, 147, 235);
}
.bg-menu-theme .menu-inner > .menu-item.active:before {
  background-color: #2793eb;
}


</style>

    <script>
 $(document).ready(function () {
    function updateMonthDisplay() {
        let currentMonth = $('#calendar').fullCalendar('getDate').format('MMMM YYYY');
        $("#monthButtonText").text(currentMonth);
        $("#eventMonthTitle").text(currentMonth);
        displayEventsForMonth(currentMonth);
    }

    function displayEventsForMonth(selectedMonth) {
        let eventListHTML = "";
        let filteredEvents = events.filter(event => moment(event.start).format('MMMM YYYY') === selectedMonth);

        if (filteredEvents.length === 0) {
            eventListHTML = "<p>No events this month.</p>";
        } else {
            filteredEvents.forEach(event => {
                eventListHTML += `
                    <div class="card mb-2 p-3" style="background-color:${event.color || '#f8f9fa'};">
                        <h6 class="fw-bold">${event.title}</h6>
                        <p><strong>Department:</strong> ${event.department}</p>
                        <p><strong>Date:</strong> ${moment(event.start).format('MMMM D, YYYY')} - ${moment(event.end).format('MMMM D, YYYY')}</p>
                        <p><strong>Time:</strong> ${moment(event.start).format('h:mm A')} - ${moment(event.end).format('h:mm A')}</p>
                        <p><strong>Location:</strong> ${event.event_location}</p>
                        <p><strong>URL:</strong> <a href="${event.event_url}" target="_blank">${event.event_url}</a></p>
                        <p><strong>Description:</strong> ${event.description}</p>
                    </div>
                `;
            });
        }

        $("#eventList").html(eventListHTML);
    }

    $('#calendar').fullCalendar({
        header: {
            left: 'prev,next today',
            center: 'title',
            right: 'month,agendaWeek,agendaDay'
        },
        customButtons: {
        prev: {
            text: '‹', // Unicode left arrow
            click: function() {
                $('#calendar').fullCalendar('prev');
            }
        },
        next: {
            text: '›', // Unicode right arrow
            click: function() {
                $('#calendar').fullCalendar('next');
            }
        }
        },
        events: <?php echo json_encode($events); ?>,
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
       

        <div class="row">
    <div class="col-12">
<div class="card mb-6">

<div class="user-profile-header-banner">
  <div class="cover-photo-container" style="height: 300px; overflow: hidden; position: relative;">

    <!-- Bootstrap Carousel -->
    <div id="coverCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="10000" style="height: 100%;">
      
<!-- Indicators -->
      <div class="carousel-indicators">
        <button type="button" data-bs-target="#coverCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
        <button type="button" data-bs-target="#coverCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
        <button type="button" data-bs-target="#coverCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
        <button type="button" data-bs-target="#coverCarousel" data-bs-slide-to="3" aria-label="Slide 4"></button>
        <button type="button" data-bs-target="#coverCarousel" data-bs-slide-to="4" aria-label="Slide 5"></button>
      </div>

      <!-- Carousel Items -->
      <div class="carousel-inner" style="height: 100%;">
        <div class="carousel-item active" style="height: 100%;">
          <img src="../assets/img/carousel/1.png" class="d-block w-100 h-100" style="object-fit: cover;" alt="Cover 1">
        </div>
        <div class="carousel-item" style="height: 100%;">
          <img src="../assets/img/carousel/2.png" class="d-block w-100 h-100" style="object-fit: cover;" alt="Cover 2">
        </div>
      <div class="carousel-item" style="height: 100%;">
          <img src="../assets/img/carousel/3.png" class="d-block w-100 h-100" style="object-fit: cover;" alt="Cover 3">
        </div>
        <div class="carousel-item" style="height: 100%;">
          <img src="../assets/img/carousel/4.png" class="d-block w-100 h-100" style="object-fit: cover;" alt="Cover 3">
        </div>
        <div class="carousel-item" style="height: 100%;">
          <img src="../assets/img/carousel/5.png" class="d-block w-100 h-100" style="object-fit: cover;" alt="Cover 3">
        </div>
      </div>

      <!-- Carousel Controls -->
      <button class="carousel-control-prev" type="button" data-bs-target="#coverCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
      </button>
      <button class="carousel-control-next" type="button" data-bs-target="#coverCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
      </button>
    </div>

  </div>
</div>

        <!-- Profile Picture -->
         <div class="user-profile-header d-flex flex-column flex-lg-row text-sm-start text-center mb-8">
         <div class="flex-shrink-0 mt-1 mx-sm-0 mx-auto">
            <img src="<?php echo!empty($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : '../assets/img/avatars/default_dp.jpg'; ?>" 
                 alt="user-avatar" class="d-block h-80 ms-0 ms-sm-6 rounded-5 profile-img" id="uploadedAvatar">
        </div>

        <!-- User Info -->
        <div class="flex-grow-1 mt-3 mt-lg-5">
            <div class="d-flex align-items-md-end align-items-sm-start align-items-center justify-content-md-between justify-content-start mx-5 flex-md-row flex-column gap-4 mt-2 ms-3">
              <div class="user-profile-info">
            <h2 class="mb-2 mt-lg-5 user-name"><?php echo htmlspecialchars($firstname . " " . $middlename . " " . $lastname); ?></h2>   
            <p class="user-details">
              <strong> <?php echo htmlspecialchars($department); ?> </strong><br>
              <?php echo htmlspecialchars($position); ?>
            </p>
             </div>

             <div id="success-message" 
             style="display: none; 
             color: green; 
             padding: 10px; 
             background-color: #e7f7e7; 
             border: 1px solid green; 
             position: relative; 
             top: -300px; 
             right: 90px; 
             transition: opacity 0.5s ease-in-out; ">
    The Cover Photo has been successfully changed.
                
    </div>
    </div>
    </div> 
</div>
</div>
</div>
</div>



<div class="row">
    <div class="col-md-12">
      <div class="mb-3 mt-lg-4 mt-3 ms-2 nav-align-top">
        <ul class="nav nav-pills flex-column flex-sm-row mb-6 gap-sm-0 gap-2">
          <li class="nav-item">
            <a class="nav-link" href="super_admin_profile.php"><i class="icon-base bx bx-user icon-sm me-1_5"></i> Profile</a>
          </li>
          <li class="nav-item">
            <a class="nav-link active" href="javascript:void(0);"><i class="icon-base bx bx-group icon-sm me-1_5"></i> Teams</a>
          </li>
        </ul>
      </div>
    </div>
  </div>


  <div class="card mt-3">
    <div class="card-header">
        <h5 class="card-title mb-0">Users from Your Department (<?php echo htmlspecialchars($department); ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($same_department_users)) : ?>
            <div class="row">
                <?php foreach ($same_department_users as $user) : ?>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body d-flex align-items-center">
                                <img src="<?php 
                                    // Check if the profile picture exists and prepend the directory path
                                    echo !empty($user['profile_picture']) && file_exists($user['profile_picture']) 
                                         ? htmlspecialchars($user['profile_picture']) 
                                         : '../assets/img/avatars/default_dp.jpg'; ?>" 
                                     alt="Profile" 
                                     class="rounded-circle me-3" 
                                     style="width: 60px; height: 60px; object-fit: cover;">
                                <div>
                                    <h6 class="mb-0"><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['middlename'] . ' ' . $user['lastname']); ?></h6>
                                    <small><?php echo htmlspecialchars($user['email']); ?></small>
                                    <p class="text-muted"><?php echo htmlspecialchars($user['position']); ?></p> <!-- Display position -->
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p class="text-muted">No other users found in your department.</p>
        <?php endif; ?>
    </div>
</div>
    
            

                    <!-- Account -->
                    
                          <!-- Profile Picture Preview -->
                     
                        
                   




            <!-- / Content -->

            </div>
            </div>

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
    <script src="../assets/vendor/libs/apex-charts/apexcharts.js"></script>

    <!-- Main JS -->
    <script src="../assets/js/main.js"></script>

    <!-- Page JS -->
    <script src="../assets/js/dashboards-analytics.js"></script>

    <!-- Place this tag in your head or just before your close body tag. -->
    <script async defer src="https://buttons.github.io/buttons.js"></script>
<script>
   function previewImage(event) {
    const coverPhoto = document.getElementById("coverPreview");
    const saveBtn = document.querySelector(".save-btn");
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            coverPhoto.src = e.target.result;  // Update preview immediately
            coverPhoto.style.display = "block"; // Make sure it's visible
            coverPhoto.style.left = "0px"; 
            coverPhoto.style.top = "0px";
            coverPhoto.style.width = "100%";
            coverPhoto.style.height = "100%";
            const preview = document.getElementById('coverPreview');
            preview.src = reader.result;


            // Show the save button
            saveBtn.classList.add("show"); // Add show class to display the button
        };
        reader.readAsDataURL(event.target.files[0]);
    }
}

</script>
    <script>
        // Preview the image before uploading
        function previewImage(event) {
            const coverPhoto = document.getElementById("coverPreview");
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    coverPhoto.src = e.target.result;  // Update preview immediately
                    coverPhoto.style.display = "block"; // Make sure it's visible
                };
                reader.readAsDataURL(file);
            }
        }
    </script>
<script>
    let coverPhoto = document.querySelector('.cover-photo');
let coverPhotoContainer = document.querySelector('.cover-photo-container');
let saveBtn = document.querySelector('.save-btn');

// Ensure that the cover photo is visible when the image is uploaded
document.addEventListener("DOMContentLoaded", () => {
    if (coverPhoto) {
        coverPhoto.style.display = 'block';  // Show cover photo
    }
});

// Variables for dragging functionality
let isDragging = false;
let offsetX, offsetY;

coverPhoto.addEventListener('mousedown', (e) => {
    isDragging = true;
    offsetX = e.clientX - coverPhoto.getBoundingClientRect().left;
    offsetY = e.clientY - coverPhoto.getBoundingClientRect().top;
    coverPhoto.classList.add('dragging');  // Add dragging effect
    document.body.style.cursor = 'grabbing';  // Change cursor style
});

document.addEventListener('mousemove', (e) => {
    if (isDragging) {
        let x = e.clientX - offsetX;
        let y = e.clientY - offsetY;

        // Keep the image within the container bounds
        let maxX = coverPhotoContainer.offsetWidth - coverPhoto.offsetWidth;
        let maxY = coverPhotoContainer.offsetHeight - coverPhoto.offsetHeight;

        // Set position of the image within the container
        x = Math.max(0, Math.min(x, maxX));
        y = Math.max(0, Math.min(y, maxY));

        coverPhoto.style.left = `${x}px`;
        coverPhoto.style.top = `${y}px`;
    }
});

document.addEventListener('mouseup', () => {
    isDragging = false;
    coverPhoto.classList.remove('dragging');  // Remove dragging effect
    document.body.style.cursor = 'grab';  // Change cursor back to normal
    saveBtn.style.display = 'block';  // Show save button after drag
});

// Handle save button click
saveBtn.addEventListener('click', () => {
    // You can implement a function to save the new position in the database here
    alert('Cover photo position saved!');
    // Hide the save button after save
    saveBtn.style.display = 'none';
});

</script>
  <script>
    document.getElementById("cover-form").addEventListener("submit", function(event) {
        event.preventDefault(); // Prevent the form from submitting immediately

        // Show success message with fade-in effect
        const successMessage = document.getElementById("success-message");
        successMessage.style.display = "block"; // Make sure it's visible
        setTimeout(function() {
            successMessage.style.opacity = 1; // Fade in the message
        }, 0);

        // Simulate form submission (use AJAX or redirect as needed)
        setTimeout(function() {
            // After a short time, fade out the message and submit the form
            successMessage.style.opacity = 0; // Fade out the message
            setTimeout(function() {
                document.getElementById("cover-form").submit(); // Submit the form
            }, 500); // Wait for the fade-out effect to complete
        }, 2000); // Wait for 2 seconds before fading out and submitting the form
    });
</script>




  </body>
</html>
