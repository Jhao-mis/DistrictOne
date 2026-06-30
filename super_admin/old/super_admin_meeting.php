<?php
session_start();
include '../db.php';
require '../vendor/autoload.php';
require_once('../tcpdf/tcpdf.php'); // Include TCPDF if manually downloaded
require 'login_verification.php';


$username = $_SESSION['username'];

date_default_timezone_set('Asia/Manila'); // Set timezone to your local
$now = date('Y-m-d H:i:s');
$updateActivity = $conn->prepare("UPDATE users SET last_activity = ? WHERE username = ?");
$updateActivity->bind_param("ss", $now, $username);
$updateActivity->execute();
$updateActivity->close();

// Fetch user details
$query = $conn->prepare("SELECT id, profile_picture, cover_photo, department, firstname, middlename, lastname, email FROM users WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->store_result();
$query->bind_result($user_id, $profile_picture, $cover_photo, $department, $firstname, $middlename, $lastname, $email);
$query->fetch();
$query->close();


$sql = "SELECT * FROM meeting WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$meeting_data = $result->fetch_assoc();
$stmt->close(); // Close statement


$_SESSION['attendees'] = [];

$stmt = $conn->prepare("SELECT attendee FROM attendees WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $_SESSION['attendees'][] = $row['attendee'];
}

$stmt->close();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['download_pdf'])) {

    // Prevent previous output before PDF generation
    ob_clean();

    class MYPDF extends TCPDF
    {
        // Page header
        public function Header()
        {
            // Path to the image
            $image_file = '../uploads/cwd_header.jpg'; // Adjust path as needed

            // Set image as header
            $this->Image($image_file, 10, 5, 190, '', 'JPG'); // X, Y, Width, Height, Format
            $this->Ln(20); // Move cursor down to avoid overlapping text
        }
    }

    // Create new PDF document
    $pdf = new MYPDF();
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor("$firstname $middlename $lastname");
    $pdf->SetTitle('Notice of Meeting');
    $pdf->SetSubject('Meeting Notification');

    // Set default margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 10);

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', '', 12);

    // OLD FORMAT OF NOTICE OF MEETING
    // $pdf->Cell(0, 20, '', 0, 1); // Empty cell for small spacing
    // $pdf->SetFont('helvetica', 'B', 16);
    // $pdf->Cell(0, 10, 'NOTICE OF MEETING', 0, 9, 'C');
    // $pdf->Ln(5);

    // // Meeting Details
    // $pdf->SetFont('helvetica', '', 12);
    // $content = "Please be informed that the following personnel are requested to attend the " . ($meeting_data['meetingTitle'] ?? "N/A") . ".\n\n";
    // $content .= ($meeting_data['meetingObjective'] ?? "No objective specified.") . "";
    // $meetingDate = isset($meeting_data['meetingDate']) ? date("F d, Y", strtotime($meeting_data['meetingDate'])) : "N/A";
    // $content .= " Your presence and participation in this presentation are highly valued. The meeting is on " . $meetingDate . ", from " . ($meeting_data['meetingStart'] ?? "N/A") . " to " . ($meeting_data['meetingEnd'] ?? "N/A") . " at " . ($meeting_data['meetingLocation'] ?? "N/A") . ".\n\n";
    // $pdf->MultiCell(0, 10, $content, 0, 'J');
    // $pdf->Ln(5);


    // FINAL REVISE FORMAT OF THE NOTICE OF MEETING
    $pdf->Cell(0, 20, '', 0, 1); // Small top spacing
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'NOTICE OF MEETING', 0, 1, 'C');
    $pdf->Ln(10); // Add vertical space after title

    // Meeting Details
    $pdf->SetFont('helvetica', '', 12);

    // Extract meeting data
    $meetingTitle = trim($meeting_data['meetingTitle'] ?? "N/A");
    $meetingObjective = trim($meeting_data['meetingObjective'] ?? "No objective specified.");
    $meetingDate = isset($meeting_data['meetingDate']) ? date("F d, Y", strtotime($meeting_data['meetingDate'])) : "N/A";

    // Format time with AM/PM
    $meetingStart = isset($meeting_data['meetingStart']) ? date("h:i A", strtotime($meeting_data['meetingStart'])) : "N/A";
    $meetingEnd = isset($meeting_data['meetingEnd']) ? date("h:i A", strtotime($meeting_data['meetingEnd'])) : "N/A";

    $meetingLocation = trim($meeting_data['meetingLocation'] ?? "N/A");

    // ----- Paragraph 1 (Justified) -----
    $paragraph1 = "Please be informed that the following personnel are requested to attend the {$meetingTitle} ";
    $paragraph1 .= "on {$meetingDate}, from {$meetingStart} to {$meetingEnd} at {$meetingLocation}.";

    // Output first paragraph (justified)
    $pdf->MultiCell(0, 8, $paragraph1, 0, 'J');
    $pdf->Ln(6);

    // ----- Paragraph 2 (Left aligned) -----
    $pdf->SetFont('helvetica', '', 12);
    $pdf->MultiCell(0, 8, "The agenda of the meeting is as follows:", 0, 'L');
    $pdf->MultiCell(0, 8, $meetingObjective, 0, 'L');
    $pdf->Ln(8);



    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetX(150); // Adjust position if needed
    $pdf->Cell(60, 10, "Attendance:", 0, 0); // 0 for no border, 0 to stay on same line
    $pdf->SetX(100); // Adjust position if needed
    $pdf->Cell(60, 10, "Received Notice:", 0, 1); // 1 moves to the next line after this
    $pdf->SetFont('helvetica', '', 12);

    // Retrieve attendees from session
    $attendees = $_SESSION['attendees'] ?? [];

    if (!empty($attendees)) {
        foreach ($attendees as $index => $attendee) {
            $pdf->SetX(20); // Adjust the X position (smaller number moves it left)
            $pdf->Cell(80, 8, ($index + 1) . ".) " . $attendee, 0, 0); // Attendee name
            $pdf->Cell(50, 8, "________________", 0, 0); // Received Notice line
            $pdf->Cell(50, 8, "________________", 0, 1); // Attendance line
        }
    } else {
        $pdf->SetX(20); // Adjust the X position (smaller number moves it left)
        $pdf->Cell(0, 10, "No attendees listed.", 0, 1);
    }


    // Leave some space after attendees
    $pdf->Ln(15);

    // Display User Details
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, "Sincerely,", 0, 1);

    $pdf->MultiCell(0, 5, "$firstname $middlename $lastname\n$department", 0, 'L');

    $pdf->Ln(10);

    $pdf->SetFont('helvetica', '', 12);
    $pdf->MultiCell(0, 5, "Approved by:", 0, 'C');

    $pdf->Ln(5);

    $pdf->MultiCell(0, 5, "Mr. Exequiel Aguilar Jr.\nGeneral Manager", 0, 'C');

    $pdf->Ln(5); // Adjust space between sections if needed

    // Output PDF to download
    $pdf->Output('Meeting_Notice.pdf', 'D'); // 'D' forces download
    exit(); // Ensure no further output is sent
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Retrieve and sanitize input data
    $meetingTitle = trim($_POST['meetingTitle'] ?? '');
    $meetingDate = trim($_POST['meetingDate'] ?? '');
    $meetingStart = trim($_POST['meetingStart'] ?? '');
    $meetingEnd = trim($_POST['meetingEnd'] ?? '');
    $meetingLocation = trim($_POST['meetingLocation'] ?? '');
    $meetingObjective = trim($_POST['meetingObjective'] ?? '');

    if ($meeting_data) {
        //Update Familiy Background

        $stmt = $conn->prepare("UPDATE meeting SET 
      meetingTitle = ?, meetingDate = ?, meetingStart = ?, meetingEnd = ?, meetingLocation = ?, meetingObjective = ?
      WHERE user_id = ?");

        $stmt->bind_param(
            "ssssssi",
            $_POST['meetingTitle'],
            $_POST['meetingDate'],
            $_POST['meetingStart'],
            $_POST['meetingEnd'],
            $_POST['meetingLocation'],
            $_POST['meetingObjective'],
            $user_id
        );

        if (!$stmt->execute()) {
            die("Error updating meeting: " . $stmt->error);
        }

        $stmt->close(); // Close only after execution

    } else {

        // INSERT new record
        $insert_query = $conn->prepare("INSERT INTO meeting
        (meetingTitle, meetingDate, meetingStart, meetingEnd, meetingLocation, meetingObjective, user_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");

        if (!$insert_query) {
            die("Error preparing insert statement: " . $conn->error);
        }


        $insert_query->bind_param(
            "ssssssi",
            $meetingTitle,
            $meetingDate,
            $meetingStart,
            $meetingEnd,
            $meetingLocation,
            $meetingObjective,
            $user_id
        );

        if (!$insert_query->execute()) {
            die("Error inserting into meeting: " . $insert_query->error);
        }

        $insert_query->close(); // Close only after execution
    }

}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (!empty($_POST['attendee'])) {
        $stmt = $conn->prepare("DELETE FROM attendees WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO attendees (user_id, attendee) VALUES (?, ?)");

        foreach ($_POST['attendee'] as $index => $attendee) {
            if (!empty($attendee)) { // Prevent inserting empty rows
                $stmt->bind_param("is", $user_id, $attendee);
                $stmt->execute();
            }
        }
        $stmt->close();
    }
}

?>





<!DOCTYPE html>

<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Notice of Meeting - District One</title>

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
            left: 50px;
            bottom: 170px;
            display: flex;
            align-items: center;
            /* Align profile picture vertically */
            justify-content: flex-start;
            /* Place profile picture near the cover photo */
        }


        .profile-img {
            border: 4px solid #fff;
            /* White border around the profile picture */
            background-color: white;
            border-radius: 50%;
            /* Circular border */
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            /* Optional: add a shadow for better visibility */
            margin-left: 10px;
            /* Adjust this value to position the image properly */
            width: 150px;
            /* Increase size (adjust as needed) */
            height: 150px;
            /* Ensure it remains a circle */
        }

        /* Profile Card Styling */
        .profile-card {
            width: 1370px;
            border-radius: 10px;
            margin-bottom: -50px;
            max-width: 100%;
            margin-top: 30px;
            margin-left: 25px;
            background: #ffffff;
            /* Ensure white background */
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            padding-bottom: 150px;
            /* Ensure padding at the bottom */
            min-height: 200px;
            /* Add height to accommodate profile container */
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
            opacity: 0;
            /* Initially hidden */
            transition: opacity 0.3s ease-in-out;
            /* Fade-in effect */
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
            opacity: 0;
            /* Initially hidden */
            transition: opacity 0.3s ease-in-out;
            /* Fade-in effect */
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
            display: inline-block;
            /* Show when the image is selected */
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
            border: 1px solid #e8e8e8;
            /* Light border */
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
            max-height: 600px;
            /* Adjust this to fit your layout */
            overflow-y: auto;
            /* Enable scrolling */
        }

        .fc-prev-button,
        .fc-next-button {
            font-size: 18px !important;
            background: none !important;
            border: none !important;
            color: #333 !important;
            /* Dark color for visibility */
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
            max-height: 600px;
            /* Adjust height as needed */
            overflow-y: auto;
            padding-right: 10px;
            /* Avoid content cutting off */
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
            border-bottom: 1px solid #ddd;
            /* Line between events */
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
            color: #007bff;
            /* Blue text */
            border: 2px solid #007bff;
            /* Blue border */
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            transition: 0.3s ease-in-out;
            max-width: 200px;
            display: block;
            margin: 0 auto;
            /* Centers button */
            text-align: center;
        }

        .custom-outline-blue:hover {
            background-color: #007bff;
            /* Blue background on hover */
            color: white;
            /* White text on hover */
        }

        .bg-menu-theme .menu-inner>.menu-item.active>.menu-link {
            color: #fff;
            background-color: rgba(47, 144, 255, 0.63) !important;
        }

        .bg-menu-theme .menu-inner>.menu-item.active:before {
            background-color: #2793eb;
        }

        .swal2-container {
            z-index: 99999 !important;
        }
    </style>

</head>


<body>

    <?php include 'sidebar.php' ?>


    <div class="container-xxl flex-grow-1 container-p-y">
        <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light"></span> Create Notice of Meeting</h4>

        <div class="row">
            <div class="col-md-12">

                <div class="card mb-4">
                    <!-- Account -->

                    <hr class="my-0" />
                    <div class="card-body">
                        <form method="POST" id="meetRequest" action="super_admin_meeting.php">
                            <div class="row">

                                <div class="mb-3 col-md-6">
                                    <label for="meetingTitle" class="form-label">Meeting Title</label>
                                    <input class="form-control" type="text" name="meetingTitle"
                                        placeholder="Input the title of meeting"
                                        value="<?= htmlspecialchars($meeting_data['meetingTitle'] ?? '') ?>">
                                </div>

                                <div class="mb-3 col-md-6">
                                    <label for="meetingObjective" class="form-label">Objective of the Meeting</label>
                                    <input class="form-control" type="text" name="meetingObjective"
                                        placeholder="Input the objective of meeting"
                                        value="<?= htmlspecialchars($meeting_data['meetingObjective'] ?? '') ?>">
                                </div>

                                <div class="mb-3 col-md-6">
                                    <label for="meetingLocation" class="form-label">Location</label>
                                    <input class="form-control" type="text" id="meetingLocation" name="meetingLocation"
                                        placeholder="Input the meeting location"
                                        value="<?= htmlspecialchars($meeting_data['meetingLocation'] ?? '') ?>">
                                </div>

                                <div class="mb-3 col-md-6">
                                    <label for="meetingDate" class="form-label">Date of the Meeting</label>
                                    <input class="form-control" type="date" id="meetingDate" name="meetingDate"
                                        value="<?= htmlspecialchars($meeting_data['meetingDate'] ?? '') ?>">
                                </div>
                                <script>
                                    // Optional JavaScript to prevent more than 4 digits in year
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

                                <div class="mb-3 col-md-6">
                                    <label for="meetingStart" class="form-label">Start of the Meeting</label>
                                    <input class="form-control" type="time" name="meetingStart"
                                        value="<?= htmlspecialchars($meeting_data['meetingStart'] ?? '') ?>">
                                </div>

                                <div class="mb-3 col-md-6">
                                    <label for="meetingEnd" class="form-label">End of the Meeting</label>
                                    <input class="form-control" type="time" name="meetingEnd"
                                        value="<?= htmlspecialchars($meeting_data['meetingEnd'] ?? '') ?>">
                                </div>


                                <h5>Attendees</h5>
                                <div id="attendeeContainer">
                                    <?php
                                    if (!isset($_SESSION['attendees'])) {
                                        $_SESSION['attendees'] = [];
                                    }

                                    $totalAttendee = count($_SESSION['attendees']);
                                    if ($totalAttendee === 0) {
                                        $totalAttendee = 1; // Ensure at least one attendee input is displayed
                                    }

                                    for ($i = 0; $i < $totalAttendee; $i++): ?>
                                        <div class="attendee-entry">
                                            <div class="mb-3 col-md-12">
                                                <label for="attendee<?= $i + 1 ?>" class="form-label">Name of Attendee
                                                    <?= $i + 1 ?></label>
                                                <input class="form-control" type="text" id="attendee<?= $i + 1 ?>FullName"
                                                    name="attendee[]" placeholder="Input Attendee's Full Name"
                                                    value="<?= isset($_SESSION['attendees'][$i]) ? htmlspecialchars($_SESSION['attendees'][$i], ENT_QUOTES) : ''; ?>" />
                                            </div>
                                        </div>
                                    <?php endfor; ?>
                                </div>

                                <button type="button" class="custom-outline-blue" onclick="addAttendee()"> + Add
                                    Attendee </button>

                                <script>
                                    let attendeeCount = <?= count($_SESSION['attendees']) ?: 1 ?>;
                                    const maxAttendee = 20;

                                    function addAttendee() {
                                        if (attendeeCount >= maxAttendee) {
                                            alert("You can only add up to 20 attendees.");
                                            return;
                                        }

                                        attendeeCount++;
                                        const container = document.getElementById("attendeeContainer");

                                        const attendeeDiv = document.createElement("div");
                                        attendeeDiv.classList.add("attendee-entry");
                                        attendeeDiv.innerHTML = `
            <div class="mb-3 col-md-12">
                <label for="attendee${attendeeCount}FullName" class="form-label">Name of Attendee ${attendeeCount}</label>
                <input class="form-control" type="text" id="attendee${attendeeCount}FullName" name="attendee[]" 
                    placeholder="Input Attendee ${attendeeCount}'s Full Name"/>
            </div>
        `;

                                        container.appendChild(attendeeDiv);
                                    }
                                </script>



                                <div class="mt-2">
                                    <button type="submit" class="btn btn-primary me-2" name="save" id="save"
                                        style="background-color: #007bff; border-color: #007bff; position:relative; top:10px; left:10px;">Save</button>

                                    <button type="submit" class="btn btn-outline-secondary" name="download_pdf"
                                        id="download_pdf"
                                        style="background-color:rgb(255, 55, 55); border-color:rgb(179, 60, 60); color: white; position:relative; margin-left:20px; top: 10px;">Print
                                        as PDF</button>

                                </div>
                        </form>
                    </div>
                    <!-- /Account -->
                </div>

            </div>
        </div>
    </div>
    <!-- / Content -->

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
    <script>
        document.getElementById('save').addEventListener('click', function (e) {
            e.preventDefault(); // Prevent default form action

            Swal.fire({
                title: 'Create Notice of Meeting',
                text: 'Are you sure you want to save?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes',
                cancelButtonText: 'No',
                confirmButtonColor: '#007bff',
                cancelButtonColor: '#d33',
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show second confirmation before submitting
                    Swal.fire({
                        icon: 'success',
                        title: 'Saved!',
                        text: 'Your notice has been saved.',
                        confirmButtonColor: '#007bff'
                    }).then(() => {
                        // Submit the form after the second alert
                        document.getElementById('meetRequest').submit();
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
</body>

</html>