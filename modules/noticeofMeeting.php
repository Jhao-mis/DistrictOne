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

    <!-- Icons -->
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

    <!-- Helpers -->
    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>

    <style>
        /* =========================================================
           TOKENS — shared accent with the rest of DistrictOne (#4e96f0)
           ========================================================= */
        :root {
            --nom-accent: #4e96f0;
            --nom-accent-deep: #3d7fd9;
            --nom-accent-soft: rgba(78, 150, 240, .12);
            --nom-bg: #f7f8fa;
            --nom-surface: #ffffff;
            --nom-surface-sunken: #fafbfc;
            --nom-border: #e8eaee;
            --nom-text: #1f2430;
            --nom-text-muted: #767e8c;
            --nom-danger: #e5534b;
            --nom-danger-deep: #c9433c;
            --nom-danger-soft: rgba(229, 83, 75, .1);
            --nom-radius: 12px;
            --nom-radius-sm: 8px;
            --nom-shadow: 0 1px 2px rgba(20, 20, 43, .04), 0 8px 24px -12px rgba(20, 20, 43, .10);
        }

        /* ── Page header ──────────────────────────────────────────── */
        .nom-page-title { font-weight: 700; color: var(--nom-text); }
        .nom-page-sub { font-size: 13px; color: var(--nom-text-muted); margin-top: 2px; font-weight: 400; }

        /* ── Card wrapper ─────────────────────────────────────────── */
        .nom-card {
            border: 1px solid var(--nom-border);
            border-radius: var(--nom-radius);
            box-shadow: var(--nom-shadow);
            overflow: hidden;
            background: var(--nom-surface);
        }
        .nom-card .card-body { padding: 24px 26px 26px; background: var(--nom-surface-sunken); }

        /* ── Form sections ────────────────────────────────────────── */
        .form-section {
            background: var(--nom-surface);
            border: 1px solid var(--nom-border);
            border-radius: var(--nom-radius-sm);
            padding: 18px 18px 4px;
            margin-bottom: 18px;
        }
        .form-section-title {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: var(--nom-text-muted);
            margin-bottom: 16px;
        }
        .form-section-title i { font-size: 16px; color: var(--nom-accent); }

        .nom-card .form-label {
            font-weight: 600;
            font-size: 12.5px;
            color: var(--nom-text);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .nom-card .form-label i { font-size: 14px; color: var(--nom-text-muted); }

        .nom-card .form-control {
            border-radius: 8px;
            border: 1.5px solid var(--nom-border);
            font-size: 13.5px;
            padding: 9px 12px;
        }
        .nom-card .form-control:focus {
            border-color: var(--nom-accent);
            box-shadow: 0 0 0 3px var(--nom-accent-soft);
        }

        /* ── Attendees ────────────────────────────────────────────── */
        .attendee-entry {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            margin-bottom: 12px;
        }
        .attendee-entry .attendee-field { flex: 1; position: relative; }
        .attendee-number {
            width: 30px; height: 38px;
            flex: none;
            border-radius: 8px;
            background: var(--nom-accent-soft);
            color: var(--nom-accent-deep);
            font-weight: 700;
            font-size: 12.5px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .attendee-remove-btn {
            flex: none;
            width: 38px; height: 38px;
            border-radius: 8px;
            border: 1.5px solid var(--nom-border);
            background: var(--nom-surface);
            color: var(--nom-text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: border-color .12s ease, color .12s ease, background .12s ease;
        }
        .attendee-remove-btn:hover {
            border-color: var(--nom-danger);
            color: var(--nom-danger);
            background: var(--nom-danger-soft);
        }
        .attendee-remove-btn:disabled {
            opacity: .35;
            cursor: not-allowed;
        }
        .attendee-remove-btn:disabled:hover {
            border-color: var(--nom-border);
            color: var(--nom-text-muted);
            background: var(--nom-surface);
        }

        .attendee-count-hint {
            font-size: 11.5px;
            color: var(--nom-text-muted);
            margin: -6px 0 14px;
        }

        .btn-add-attendee {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--nom-surface);
            border: 1.5px dashed var(--nom-accent);
            color: var(--nom-accent-deep);
            font-weight: 600;
            font-size: 13px;
            padding: 8px 14px;
            border-radius: 8px;
            transition: background .12s ease;
        }
        .btn-add-attendee:hover { background: var(--nom-accent-soft); }
        .btn-add-attendee:disabled {
            opacity: .5;
            cursor: not-allowed;
            border-style: solid;
        }

        /* Autocomplete suggestion strip */
        .suggestion-box {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 1050;
            max-height: 200px;
            overflow-y: auto;
            display: none;
            margin-top: 4px;
            background-color: var(--nom-surface);
            border: 1px solid var(--nom-border);
            border-radius: var(--nom-radius-sm);
            box-shadow: var(--nom-shadow);
        }
        .suggestion-box .list-group-item {
            cursor: pointer;
            border: none;
            font-size: 13px;
            padding: 8px 12px;
        }
        .suggestion-box .list-group-item:hover {
            background-color: var(--nom-accent-soft);
            color: var(--nom-accent-deep);
        }

        /* ── Actions footer ───────────────────────────────────────── */
        .nom-form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding-top: 18px;
            border-top: 1px solid var(--nom-border);
        }
        .btn-nom-primary {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: var(--nom-accent);
            border: none;
            color: #fff;
            font-weight: 600;
            font-size: 13.5px;
            padding: 10px 18px;
            border-radius: 9px;
            box-shadow: 0 4px 10px -4px rgba(78, 150, 240, .55);
            transition: background .12s ease, transform .12s ease;
        }
        .btn-nom-primary:hover { background: var(--nom-accent-deep); color: #fff; transform: translateY(-1px); }

        .btn-nom-pdf {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: var(--nom-danger);
            border: none;
            color: #fff;
            font-weight: 600;
            font-size: 13.5px;
            padding: 10px 18px;
            border-radius: 9px;
            box-shadow: 0 4px 10px -4px rgba(229, 83, 75, .5);
            transition: background .12s ease, transform .12s ease;
        }
        .btn-nom-pdf:hover { background: var(--nom-danger-deep); color: #fff; transform: translateY(-1px); }
    </style>
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
        <div class="container-xxl flex-grow-1 container-p-y">

            <div class="mb-4">
                <h4 class="nom-page-title mb-0">Create Notice of Meeting</h4>
                <div class="nom-page-sub">Fill in the meeting details and attendees, then save or generate a signed PDF notice.</div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="nom-card mb-4">
                        <div class="card-body">
                            <form method="POST" id="meetRequest" action="noticeofMeeting.php">

                                <div class="form-section">
                                    <div class="form-section-title"><i class="bx bx-detail"></i>Meeting details</div>
                                    <div class="row">
                                        <div class="mb-3 col-md-6">
                                            <label for="meetingTitle" class="form-label"><i class="bx bx-bookmark"></i>Meeting Title</label>
                                            <input class="form-control" type="text" id="meetingTitle" name="meetingTitle"
                                                placeholder="Input the title of meeting"
                                                value="<?= htmlspecialchars($meeting_data['meetingTitle'] ?? '') ?>">
                                        </div>

                                        <div class="mb-3 col-md-6">
                                            <label for="meetingObjective" class="form-label"><i class="bx bx-target-lock"></i>Objective of the Meeting</label>
                                            <input class="form-control" type="text" id="meetingObjective" name="meetingObjective"
                                                placeholder="Input the objective of meeting"
                                                value="<?= htmlspecialchars($meeting_data['meetingObjective'] ?? '') ?>">
                                        </div>

                                        <div class="mb-3 col-md-6">
                                            <label for="meetingLocation" class="form-label"><i class="bx bx-map-pin"></i>Location</label>
                                            <input class="form-control" type="text" id="meetingLocation" name="meetingLocation"
                                                placeholder="Input the meeting location"
                                                value="<?= htmlspecialchars($meeting_data['meetingLocation'] ?? '') ?>">
                                        </div>

                                        <div class="mb-3 col-md-6">
                                            <label for="meetingDate" class="form-label"><i class="bx bx-calendar"></i>Date of the Meeting</label>
                                            <input class="form-control" type="date" id="meetingDate" name="meetingDate"
                                                value="<?= htmlspecialchars($meeting_data['meetingDate'] ?? '') ?>">
                                        </div>

                                        <div class="mb-3 col-md-6">
                                            <label for="meetingStart" class="form-label"><i class="bx bx-time-five"></i>Start of the Meeting</label>
                                            <input class="form-control" type="time" id="meetingStart" name="meetingStart"
                                                value="<?= htmlspecialchars($meeting_data['meetingStart'] ?? '') ?>">
                                        </div>

                                        <div class="mb-3 col-md-6">
                                            <label for="meetingEnd" class="form-label"><i class="bx bx-time-five"></i>End of the Meeting</label>
                                            <input class="form-control" type="time" id="meetingEnd" name="meetingEnd"
                                                value="<?= htmlspecialchars($meeting_data['meetingEnd'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-section">
                                    <div class="form-section-title"><i class="bx bx-group"></i>Attendees</div>

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
                                                <div class="attendee-number"><?= $i + 1 ?></div>
                                                <div class="attendee-field">
                                                    <input class="form-control attendee-input" type="text"
                                                        id="attendee<?= $i + 1 ?>FullName" name="attendee[]"
                                                        placeholder="Input Attendee's Full Name" autocomplete="off"
                                                        value="<?= htmlspecialchars($_SESSION['attendees'][$i] ?? '', ENT_QUOTES) ?>" />
                                                    <div class="suggestion-box list-group"></div>
                                                </div>
                                                <button type="button" class="attendee-remove-btn" onclick="removeAttendee(this)" title="Remove attendee">
                                                    <i class="bx bx-x"></i>
                                                </button>
                                            </div>
                                        <?php endfor; ?>
                                    </div>

                                    <div class="attendee-count-hint">Up to 20 attendees. At least one is required.</div>

                                    <button type="button" class="btn-add-attendee" id="addAttendeeBtn" onclick="addAttendee()">
                                        <i class="bx bx-plus"></i> Add Attendee
                                    </button>
                                </div>

                                <div class="nom-form-actions">
                                    <button type="submit" class="btn-nom-primary" name="save" id="save">
                                        <i class="bx bx-save"></i> Save
                                    </button>

                                    <button type="submit" class="btn-nom-pdf" name="download_pdf" id="download_pdf">
                                        <i class="bx bx-file-blank"></i> Print as PDF
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-backdrop fade"></div>
    </div>

    <div class="layout-overlay layout-menu-toggle"></div>

    <script src="../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../assets/vendor/libs/popper/popper.js"></script>
    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>

    <script>
        // Prevent more than 4 digits in the meeting date's year
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

    <script>
        // ── Attendees: add / remove / renumber ─────────────────────
        const maxAttendee = 20;
        const attendeeContainer = document.getElementById('attendeeContainer');
        const addAttendeeBtn = document.getElementById('addAttendeeBtn');

        function renumberAttendees() {
            const entries = attendeeContainer.querySelectorAll('.attendee-entry');
            entries.forEach((entry, index) => {
                entry.querySelector('.attendee-number').innerText = index + 1;
                const input = entry.querySelector('.attendee-input');
                input.id = `attendee${index + 1}FullName`;
                const removeBtn = entry.querySelector('.attendee-remove-btn');
                removeBtn.disabled = entries.length <= 1;
            });
            addAttendeeBtn.disabled = entries.length >= maxAttendee;
        }

        function addAttendee() {
            const entries = attendeeContainer.querySelectorAll('.attendee-entry');
            if (entries.length >= maxAttendee) return;

            const entry = document.createElement('div');
            entry.className = 'attendee-entry';
            entry.innerHTML = `
                <div class="attendee-number"></div>
                <div class="attendee-field">
                    <input class="form-control attendee-input" type="text" name="attendee[]"
                        placeholder="Input Attendee's Full Name" autocomplete="off" />
                    <div class="suggestion-box list-group"></div>
                </div>
                <button type="button" class="attendee-remove-btn" onclick="removeAttendee(this)" title="Remove attendee">
                    <i class="bx bx-x"></i>
                </button>
            `;
            attendeeContainer.appendChild(entry);
            renumberAttendees();
        }

        function removeAttendee(btn) {
            const entries = attendeeContainer.querySelectorAll('.attendee-entry');
            if (entries.length <= 1) return; // always keep at least one row
            btn.closest('.attendee-entry').remove();
            renumberAttendees();
        }

        renumberAttendees();

        // ── Attendee name autocomplete ──────────────────────────────
        document.addEventListener('input', function (e) {
            if (!e.target.classList.contains('attendee-input')) return;

            const input = e.target;
            const query = input.value.trim();
            const suggestionBox = input.parentElement.querySelector('.suggestion-box');

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
                confirmButtonColor: '#4e96f0',
                cancelButtonColor: '#d33',
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Saved!',
                        text: 'Your notice has been saved.',
                        confirmButtonColor: '#4e96f0'
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