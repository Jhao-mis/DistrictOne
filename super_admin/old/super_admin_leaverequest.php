<?php
require '../db.php';
require 'login_verification.php';

// Single connection
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$username = $_SESSION['username'];

// Fetch logged-in user info and their salary/position from personal_data_sheet
$stmt = $conn->prepare("
    SELECT u.id, u.firstname, u.middlename, u.lastname, u.department, p.salary, p.position
    FROM users u
    LEFT JOIN personal_data_sheet p ON u.id = p.user_id
    WHERE u.username = ?
");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($user_id, $firstname, $middlename, $lastname, $department, $salary, $position);
$stmt->fetch();
$stmt->close();

if (!$user_id) {
    die("Error: User does not exist.");
}

// Fetch leave requests
$stmt = $conn->prepare("SELECT * FROM leave_requests WHERE user_id=? ORDER BY date_of_filing ASC");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Prepare an array for form fields
$user_data = [
    'salary' => $salary,
    'position' => $position
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {

    $leave_type = $_POST['leave_type'] ?? '';
    $others = $_POST['others'] ?? '';
    $leave_philippines = $_POST['leave_philippines'] ?? '';
    $leave_abroad = $_POST['leave_abroad'] ?? '';
    $sick_hospital = $_POST['sick_hospital'] ?? '';
    $sick_outpatient = $_POST['sick_outpatient'] ?? '';
    $specialleave_women = $_POST['specialleave_women'] ?? '';
    $study_leave_options = $_POST['study_leave_options'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $no_of_working_days = $_POST['no_of_working_days'] ?? '';
    $commutation = $_POST['commutation'] ?? '';
    $date_of_filing = $_POST['date_of_filing'] ?? date('Y-m-d');

    $insert = $conn->prepare("INSERT INTO leave_requests 
        (user_id, leave_type, others, leave_philippines, leave_abroad, sick_hospital, sick_outpatient, specialleave_women, study_leave_options, start_date, end_date, no_of_working_days, commutation, date_of_filing)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $insert->bind_param(
        "isssssssssssss",
        $user_id, $leave_type, $others, $leave_philippines, $leave_abroad, $sick_hospital, $sick_outpatient, $specialleave_women, $study_leave_options, $start_date, $end_date, $no_of_working_days, $commutation, $date_of_filing
    );

    if ($insert->execute()) {
        echo "<script>
        Swal.fire({
            icon: 'success',
            title: 'Leave Request Submitted!',
            text: 'Your leave request has been filed successfully.',
            confirmButtonColor: '#007bff'
        }).then(() => {
            window.location.href = 'mis_leaverequest.php';
        });
        </script>";
    } else {
        echo "Error submitting leave request: " . $conn->error;
    }

    $insert->close();
}

$conn->close();
?>

<!DOCTYPE html>

<!-- =========================================================
* Sneat - Bootstrap 5 HTML Admin Template - Pro | v1.0.0
==============================================================

* Product Page: https://themeselection.com/products/sneat-bootstrap-html-admin-template/
* Created by: ThemeSelection
* License: You must have a valid license purchased in order to legally use the theme for your project.
* Copyright ThemeSelection (https://themeselection.com)

=========================================================
 -->
<!-- beautify ignore:start -->
<html
  lang="en"
  class="light-style layout-menu-fixed"
  dir="ltr"
  data-theme="theme-default"
  data-assets-path="../assets/"
  data-template="vertical-menu-template-free"
>
  <head>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"
    />

    <title>Leave Request - District One</title>

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

    <!-- Page CSS -->
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Helpers -->
    <script src="../assets/vendor/js/helpers.js"></script>

    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="../assets/js/config.js"></script>
  </head>

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

  <body>
    
<?php include 'sidebar.php'; ?>

          <!-- Content wrapper -->
          <div class="content-wrapper">
            <!-- Content -->

            <div class="container-xxl flex-grow-1 container-p-y">
              <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light"></span> Leave Request</h4>

              <div class="row">
                <div class="col-md-12">
                  
                  <div class="card mb-4">
                    <!-- Account -->
                    
                    <hr class="my-0" />
                    <div class="card-body">
                    <form method="POST" id="requestForm" action="super_admin_leaverequest.php">
                    <input type="hidden" name="save" value="1">                        
                    <div class="row">
                          <!-- Personal Information -->
                          <h4>Application for Leave</h4>
                          <div class="mb-3 col-md-6">
                            <label for="firstName" class="form-label">First Name</label>

                            <input
                              class="form-control"
                              type="text"
                              id="firstName"
                              name="firstName"
                              autofocus
                             value="<?php echo htmlspecialchars($firstname); ?>" readonly/>
                       
                           
                          </div>
                             <div class="mb-3 col-md-6">
                            <label for="lastName" class="form-label">Middle Name</label>
                            <input class="form-control" type="text" name="middleName" id="middleName" autofocus value="<?php echo htmlspecialchars($middlename); ?>" readonly/>
                          </div>

                          <div class="mb-3 col-md-6">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input class="form-control" type="text" name="lastName" id="lastName" autofocus value="<?php echo htmlspecialchars($lastname); ?>" readonly/>
                          </div>
                            <div class="mb-3 col-md-6">
                    <label for="department" class="form-label">Department</label>
                    <input class="form-control" type="text" name="department"
                        value="<?php echo htmlspecialchars($department); ?>" readonly>
                </div>



                   <div class="mb-3 col-md-6">
    <label for="date_of_filing" class="form-label">Date of Filing</label>
    <input 
        class="form-control" 
        type="text" 
        id="date_of_filing" 
        name="date_of_filing" 
        value="<?= date('Y-m-d') ?>" 
        readonly>  
</div>


                  <div class="mb-3 col-md-6">
                    <label for="position" class="form-label">Position</label>
                    <input class="form-control" type="text" name="position" placeholder="Input your Position"                   
                    value="<?= htmlspecialchars($user_data['position'] ?? '') ?>" readonly>
                    </div>

                       <div class="mb-3 col-md-6">
                        <label for="salary" class="form-label">Salary</label>
                        <input class="form-control" type="text" name="salary" placeholder="Input your Salary" 
                         value="<?= htmlspecialchars($user_data['salary'] ?? '') ?>" readonly>  


                        </div>

                             <div class="mb-3 col-md-6">
    <label for="leave_type" class="form-label">Leave Type</label>
    <select class="select2 form-select" id="leave_type" name="leave_type" onchange="toggleLeaveFields()">
        <option value="" disabled <?= empty($leave_type) ? 'selected' : ''; ?>>Select Leave Type</option>
       <?php
       $leave_options = [
           "Vacation Leave",
           "Mandatory/Forced Leave",
           "Sick Leave",
           "Maternity Leave",
           "Paternity Leave",
           "Special Privilege Leave",
           "Solo Parent Leave",
           "Study Leave",
           "10-Day VAWC Leave",
           "Rehabilitation Privilege",
           "Special Leave Benefits for Women",
           "Special Emergency (Calamity) Leave",
           "Adoption Leave",
           "Others"

       ];

       foreach ($leave_options as $option) {
           $selected = ($leave_data['leave_type'] ?? '') == $option || $leave_type == $option ? 'selected' : '';
           echo "<option value=\"$option\" $selected>$option</option>";
       }
       ?>


    </select>
</div>

                       <!-- Others -->
<div class="mb-3 col-md-6" id="othersField" style="display: none;">
    <label for="others" class="form-label">Specify:</label>
    <input class="form-control" type="text" id="others" name="others" placeholder="Specify" 
            value="<?= htmlspecialchars($leave_data['others'] ?? '') ?>"> 
</div>



<!-- Within the Philippines -->
<div class="mb-3 col-md-6" id="LeavePhilippines" style="display: none;">
    <label for="leave_philippines" class="form-label">Within the Philippines:</label>
    <input class="form-control" type="text" id="leave_philippines" name="leave_philippines" placeholder="Specify" 
            value="<?= htmlspecialchars($leave_data['leave_philippines'] ?? '') ?>"> 
</div>

<!-- Abroad -->
<div class="mb-3 col-md-6" id="LeaveAbroad" style="display: none;">
    <label for="leave_abroad" class="form-label">Abroad:</label>
    <input class="form-control" type="text" id="leave_abroad" name="leave_abroad" placeholder="Specify"
            value="<?= htmlspecialchars($leave_data['leave_abroad'] ?? '') ?>"> 
</div>

<!-- Sick Leave: In Hospital -->
<div class="mb-3 col-md-6" id="SickLeaveHospital" style="display: none;">
    <label for="sick_hospital" class="form-label">In Hospital:</label>
    <input class="form-control" type="text" id="sick_hospital" name="sick_hospital" placeholder="Specify Illness"
            value="<?= htmlspecialchars($leave_data['sick_hospital'] ?? '') ?>"> 
</div>

<!-- Sick Leave: Out Patient -->
<div class="mb-3 col-md-6" id="SickLeaveOutPatient" style="display: none;">
    <label for="sick_outpatient" class="form-label">Out Patient:</label>
    <input class="form-control" type="text" id="sick_outpatient" name="sick_outpatient" placeholder="Specify Illness"
            value="<?= htmlspecialchars($leave_data['sick_outpatient'] ?? '') ?>"> 
</div>

<!-- Special Leave Benefits for Women -->
<div class="mb-3 col-md-6" id="SpecialLeaveWomen" style="display: none;">
    <label for="specialleave_women" class="form-label">In case of Special Leave Benefits for Women:</label>
    <input class="form-control" type="text" id="specialleave_women" name="specialleave_women" placeholder="Specify Illness"
            value="<?= htmlspecialchars($leave_data['specialleave_women'] ?? '') ?>"> 
</div>

                            <div class="mb-3 col-md-6" id="StudyLeaveOptions" style="display: none;">
    <label for="study_leave_options" class="form-label">Study Leave Purpose:</label>
    <select class="select2 form-select" id="study_leave_options" name="study_leave_options">
        <option value="" disabled <?= empty($leave_data['study_leave_options'] ?? $study_leave_options) ? 'selected' : ''; ?>>Select Purpose</option>
        
        <?php
        $study_leave_options = [
            "Completion of Master`s Degree",
            "BAR/Board Examination Review",
            "Monetization of Leave Credits",
            "Terminal Leave"
        ];

        foreach ($study_leave_options as $option) {
            $selected = ($leave_data['study_leave_options'] ?? '') == $option || $study_leave_options == $option ? 'selected' : '';
            echo "<option value=\"$option\" $selected>$option</option>";
        }
        ?>
    </select>
</div>


<script>
function toggleLeaveFields() {
    var leaveType = document.getElementById("leave_type").value;
    var leavePhilippines = document.getElementById("LeavePhilippines");
    var leaveAbroad = document.getElementById("LeaveAbroad");
    var sickHospital = document.getElementById("SickLeaveHospital");
    var sickOutPatient = document.getElementById("SickLeaveOutPatient");
    var leaveWomen = document.getElementById("SpecialLeaveWomen");
    var othersField = document.getElementById("othersField");
    var studyLeaveOptions = document.getElementById("StudyLeaveOptions"); // Correct reference

    // Show fields for "Vacation Leave" or "Special Privilege Leave"
    if (leaveType === "Vacation Leave" || leaveType === "Special Privilege Leave") {
        leavePhilippines.style.display = "block";
        leaveAbroad.style.display = "block";
    } else {
        leavePhilippines.style.display = "none";
        leaveAbroad.style.display = "none";
    }

    // Show fields for "Sick Leave"
    if (leaveType === "Sick Leave") {
        sickHospital.style.display = "block";
        sickOutPatient.style.display = "block";
    } else {
        sickHospital.style.display = "none";
        sickOutPatient.style.display = "none";
    }

    // Show field for "Special Leave Benefits for Women"
    if (leaveType === "Special Leave Benefits for Women") {
        leaveWomen.style.display = "block";
    } else {
        leaveWomen.style.display = "none";
    }

    // Show dropdown for "Study Leave"
    if (leaveType === "Study Leave") {
        studyLeaveOptions.style.display = "block";
    } else {
        studyLeaveOptions.style.display = "none";
    }
     if (leaveType === "Others") {
        othersField.style.display = "block";
     } else {
         othersField.style.display = "none";
    }
}

// Run function when dropdown changes
document.getElementById("leave_type").addEventListener("change", toggleLeaveFields);

// Run function on page load to reflect the selected value from the database
document.addEventListener("DOMContentLoaded", function() {
    toggleLeaveFields();
});
</script>

                        <!-- Start Date -->
                        <div class="mb-3 col-md-6">
    <label for="start_date" class="form-label">Inclusive Start Date</label>
    <input 
        class="form-control" 
        type="date" 
        id="start_date" 
        name="start_date"
        value="<?= htmlspecialchars($leave_data['start_date'] ?? '') ?>">
</div>

<!-- End Date -->
<div class="mb-3 col-md-6">
    <label for="end_date" class="form-label">Inclusive End Date</label>
    <input 
        class="form-control" 
        type="date" 
        id="end_date" 
        name="end_date"
        value="<?= htmlspecialchars($leave_data['end_date'] ?? '') ?>">
</div>
<script>
// Optional: Enforce 4-digit year length on input blur
document.querySelectorAll('input[type="date"]').forEach(input => {
    input.addEventListener('blur', () => {
        const value = input.value;
        const year = value.split('-')[0];
        if (year.length !== 4) {
            alert('Please enter a valid date with a 4-digit year (DD-MM-YYYY).');
            input.value = '';
        }
    });
});
</script>

<!-- Auto Calculated Working Days -->
<div class="mb-3 col-md-6">
    <label for="no_of_working_days" class="form-label">Number of Working Days Applied For</label>
    <input 
        class="form-control" 
        type="text" 
        id="no_of_working_days" 
        name="no_of_working_days" 
        value="<?= htmlspecialchars($leave_data['no_of_working_days'] ?? '') ?>"> 
</div>

<script>
function calculateWorkingDays(start, end) {
    let count = 0;
    const startDate = new Date(start);
    const endDate = new Date(end);

    while (startDate <= endDate) {
        const day = startDate.getDay();
        // Skip Saturday (6) and Sunday (0)
        if (day !== 0 && day !== 6) {
            count++;
        }
        startDate.setDate(startDate.getDate() + 1);
    }

    return count;
}

document.getElementById('start_date').addEventListener('change', updateWorkingDays);
document.getElementById('end_date').addEventListener('change', updateWorkingDays);

function updateWorkingDays() {
    const start = document.getElementById('start_date').value;
    const end = document.getElementById('end_date').value;

    if (start && end && new Date(start) <= new Date(end)) {
        const workingDays = calculateWorkingDays(start, end);
        document.getElementById('no_of_working_days').value = workingDays;
    } else {
        document.getElementById('no_of_working_days').value = '';
    }
}
</script>
<div class="mb-3 col-md-6">
    <label for="commutation" class="form-label">Commutation</label>
    <select class="select2 form-select" id="commutation" name="commutation">
        <option value="" selected disabled>Select Commutation</option>
        <option value="Not Requested">Not Requested</option>
        <option value="Requested">Requested</option>
    </select>
</div>



                        
                        <div class="mt-2">
                        <button type="button" class="btn btn-primary me-2" name="save" id="save" style="background-color: #007bff; border-color: #007bff; position:relative; top:10px; left:-1px;">Save</button>


                            
                        </div>
                   </form>
                    </div>
                    <!-- /Account -->
                  </div>
                  
                </div>
              </div>
            </div>
            <!-- / Content -->

<div class="card mt-4">
    <div class="card-header">
        <h5>Leave Requests History</h5>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Date Filed</th>
                    <th>Leave Type</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>No. of Days</th>
                    <th>Commutation</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                require 'db_admin_leave.php'; // include the DB & fetch logic

                if (!empty($user_leaves)) {
                    foreach ($user_leaves as $row):
                ?>
                    <tr>
                        <td><?= htmlspecialchars($row['date_of_filing']) ?></td>
                        <td><?= htmlspecialchars($row['leave_type']) ?></td>
                        <td><?= htmlspecialchars($row['start_date']) ?></td>
                        <td><?= htmlspecialchars($row['end_date']) ?></td>
                        <td><?= htmlspecialchars($row['no_of_working_days']) ?></td>
                        <td><?= htmlspecialchars($row['commutation']) ?></td>
                        <td>
                            <form method="POST" action="download_leave_excel.php" target="_blank">
                                <input type="hidden" name="leave_id" value="<?= $row['id'] ?>">
                                <button type="submit" 
        name="download_excel" 
        id="download_excel" 
        class="btn btn-outline-secondary" 
        style="background-color: #3CB371; border-color: #3CB371; color: white; position: relative; left: 10px; top: 10px;">
    Download as Excel
</button>

                            </form>
                        </td>
                    </tr>
                <?php
                    endforeach;
                } else {
                    echo '<tr><td colspan="7" class="text-center">No leave requests found.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>



               <script>
        function toggleEdit() {
            let formFields = document.querySelectorAll("input");
            let editButton = document.getElementById("update");
            let submitButton = document.getElementById("save");

            formFields.forEach(field => field.disabled = !field.disabled);
            submitButton.style.display = formFields[0].disabled ? "none" : "block";
            editButton.style.display = formFields[0].disabled ? "block" : "none";
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.getElementById('save').addEventListener('click', function (e) {
        e.preventDefault(); // Prevent default form action

        Swal.fire({
            title: 'File a Leave Request',
            text: 'Are you sure you want to submit?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'No',
            confirmButtonColor: '#007bff',
            cancelButtonColor: '#d33',
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: 'Leave Request Submitted!',
                    text: 'Your leave request has been filed successfully.',
                    confirmButtonColor: '#007bff'
                }).then(() => {
                    document.getElementById('requestForm').submit();
                });
            }
        });
    });
</script>


          

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
    <script src="../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../assets/vendor/libs/popper/popper.js"></script>
    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>

    <script src="../assets/vendor/js/menu.js"></script>
    <!-- endbuild -->

    <!-- Vendors JS -->

    <!-- Main JS -->
    <script src="../assets/js/main.js"></script>

    <!-- Page JS -->
    <script src="../assets/js/pages-account-settings-account.js"></script>

    <!-- Place this tag in your head or just before your close body tag. -->
    <script async defer src="https://buttons.github.io/buttons.js"></script>

      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>



  </body>
</html>