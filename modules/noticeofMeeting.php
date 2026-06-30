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



    // OLD FORMAT OF THE NOTICE OF MEETING
    // $pdf->Cell(0, 20, '', 0, 1); // Empty cell for small spacing
    // $pdf->SetFont('helvetica', 'B', 16);
    // $pdf->Cell(0, 10, 'NOTICE OF MEETING', 0, 9, 'C');
    // $pdf->Ln(5);

    // // Meeting Details
    // $pdf->SetFont('helvetica', '', 12);
    // $content = "Please be informed that the following personnel are requested to attend the " . ($meeting_data['meetingTitle'] ?? "N/A") . ".\n\n";
    // $content .= ($meeting_data['meetingObjective'] ?? "No objective specified.") . "";
    // $meetingDate = isset($meeting_data['meetingDate']) ? date("F d, Y", strtotime($meeting_data['meetingDate'])) : "N/A";
    // $content .= " The agenda of the meeting is as follows: " . $meetingDate . ", from " . ($meeting_data['meetingStart'] ?? "N/A") . " to " . ($meeting_data['meetingEnd'] ?? "N/A") . " at " . ($meeting_data['meetingLocation'] ?? "N/A") . ".\n\n";
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
    $pdf->Output('Notice of Meeting.pdf', 'D'); // 'D' forces download
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

    <title>Notice of Meeting</title>

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
    <link rel="stylesheet" href="./css/noticeofMeeting.css">

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
    <script src="../assets/js/config.js"></script>

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
    <div class="container-xxl flex-grow-1 container-p-y">
        <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light"></span> Create Notice of Meeting</h4>
        <div class="row">
            <div class="col-md-12">
                <div class="card mb-4">
                    <!-- Account -->
                    <hr class="my-0" />
                    <div class="card-body">
                        <form method="POST" id="meetRequest" action="noticeofMeeting.php">
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


                            <!-- CSS FOR SUGGESTIVE STRIP DONT TOUCH uwu -->

                                <style>
                                    .suggestion-box {
                                        position: absolute;
                                        top: 100%;
                                        left: 0;
                                        right: 0;
                                        z-index: 1050;
                                        max-height: 200px;
                                        overflow-y: auto;
                                        display: none;

                                        background-color: #ffffff;
                                        border: 1px solid #dee2e6;
                                        border-radius: 0.375rem;
                                        box-shadow: 0 4px 12px rgba(0, 0, 0, .15);
                                    }

                                    .suggestion-box .list-group-item {
                                        cursor: pointer;
                                    }

                                    .suggestion-box .list-group-item:hover {
                                        background-color: #f8f9fa;
                                    }
                                </style>

                                <!-- FETCH ATTENDEES.PHP -->
                                <h5>Attendees</h5>
                                <div id="attendeeContainer">
                                    <?php
                                    if (!isset($_SESSION['attendees'])) {
                                        $_SESSION['attendees'] = [];
                                    }

                                    $totalAttendee = count($_SESSION['attendees']);
                                    if ($totalAttendee === 0) {
                                        $totalAttendee = 1;
                                    }

                                    for ($i = 0; $i < $totalAttendee; $i++): ?>
                                        <div class="attendee-entry">
                                            <div class="mb-3 col-md-12 position-relative">

                                                <label for="attendee<?= $i + 1 ?>" class="form-label">
                                                    Name of Attendee <?= $i + 1 ?>
                                                </label>

                                                <input class="form-control attendee-input" type="text"
                                                    id="attendee<?= $i + 1 ?>FullName" name="attendee[]"
                                                    placeholder="Input Attendee's Full Name" autocomplete="off"
                                                    value="<?= htmlspecialchars($_SESSION['attendees'][$i] ?? '', ENT_QUOTES) ?>" />

                                                <!-- Suggestion Strip -->
                                                <div class="suggestion-box list-group"></div>

                                            </div>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                                <!-- SCRIPT FOR SUGGESTIVE STRIP -->
                                <script>
                                    document.addEventListener('input', function (e) {
                                        if (!e.target.classList.contains('attendee-input')) return;

                                        const input = e.target;
                                        const query = input.value.trim();

                                        let suggestionBox = input.parentElement.querySelector('.suggestion-box');

                                        // Safety net: create suggestion box if missing
                                        if (!suggestionBox) {
                                            suggestionBox = document.createElement('div');
                                            suggestionBox.className = 'suggestion-box list-group';
                                            input.parentElement.appendChild(suggestionBox);
                                        }

                                        if (query.length < 2) {
                                            suggestionBox.innerHTML = '';
                                            suggestionBox.style.display = 'none';
                                            return;
                                        }

                                        fetch(`fetch_attendees.php?q=${encodeURIComponent(query)}`)
                                            .then(res => res.json())
                                            .then(data => {
                                                suggestionBox.innerHTML = '';

                                                if (!data.length) {
                                                    suggestionBox.style.display = 'none';
                                                    return;
                                                }

                                                data.forEach(name => {
                                                    const item = document.createElement('button');
                                                    item.type = 'button';
                                                    item.className = 'list-group-item list-group-item-action';
                                                    item.textContent = name;

                                                    item.onclick = () => {
                                                        input.value = name;
                                                        suggestionBox.innerHTML = '';
                                                        suggestionBox.style.display = 'none';
                                                    };

                                                    suggestionBox.appendChild(item);
                                                });

                                                suggestionBox.style.display = 'block';
                                            });
                                    });

                                    // Hide suggestions when clicking outside
                                    document.addEventListener('click', function (e) {
                                        document.querySelectorAll('.suggestion-box').forEach(box => {
                                            if (!box.parentElement.contains(e.target)) {
                                                box.style.display = 'none';
                                            }
                                        });
                                    });
                                </script>


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
                                        attendeeDiv.className = "attendee-entry";

                                                                            attendeeDiv.innerHTML = `
                                            <div class="mb-3 col-md-12 position-relative">

                                                <label for="attendee${attendeeCount}FullName" class="form-label">
                                                    Name of Attendee ${attendeeCount}
                                                </label>

                                                <input
                                                    class="form-control attendee-input"
                                                    type="text"
                                                    id="attendee${attendeeCount}FullName"
                                                    name="attendee[]"
                                                    placeholder="Input Attendee's Full Name"
                                                    autocomplete="off"
                                                />

                                                <!-- Suggestion Strip -->
                                                <div class="suggestion-box list-group"></div>

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
                </div>
            </div>
        </div>
    </div>
    <div class="content-backdrop fade"></div>
    </div>
    </div>
    </div>
    <div class="layout-overlay layout-menu-toggle"></div>
    </div>

    <script src="../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../assets/vendor/libs/popper/popper.js"></script>
    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pages-account-settings-account.js"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>

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
</body>

</html>