<?php
require '../db.php';
require 'login_verification.php';

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

$user_data = [
    'salary' => $salary,
    'position' => $position
];

// ==============================
// 🔔 ALERT HANDLING (Post/Redirect/Get)
// ==============================
$alert = null;
if (isset($_SESSION['alert'])) {
    $alert = $_SESSION['alert'];
    unset($_SESSION['alert']);
}

// ==============================
// 🚀 FORM SUBMISSION
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {

    $leave_type = $_POST['leave_type'] ?? '';
    $others = $_POST['others'] ?? '';
    $leave_philippines = $_POST['leave_philippines'] ?? '';
    $leave_abroad = $_POST['leave_abroad'] ?? '';
    $sick_hospital = $_POST['sick_hospital'] ?? '';
    $sick_outpatient = $_POST['sick_outpatient'] ?? '';
    $specialleave_women = $_POST['specialleave_women'] ?? '';
    $study_leave_purpose = $_POST['study_leave_options'] ?? '';
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
        $user_id,
        $leave_type,
        $others,
        $leave_philippines,
        $leave_abroad,
        $sick_hospital,
        $sick_outpatient,
        $specialleave_women,
        $study_leave_purpose,
        $start_date,
        $end_date,
        $no_of_working_days,
        $commutation,
        $date_of_filing
    );

    if ($insert->execute()) {
        $_SESSION['alert'] = [
            'type' => 'success',
            'message' => 'Your leave request has been filed successfully.'
        ];
    } else {
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => 'Error submitting leave request: ' . $conn->error
        ];
    }

    $insert->close();
    header('Location: leaveRequest.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Leave Request</title>

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
    <link rel="stylesheet" href="./css/leaveRequest.css">

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <!-- Helpers -->
    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>

    <!-- SweetAlert2 (used for submission feedback) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --tk-bg: #f7f8fa;
            --tk-surface: #ffffff;
            --tk-border: #e8eaee;
            --tk-text: #1f2430;
            --tk-text-muted: #767e8c;
            --tk-text-faint: #a2a8b3;
            --tk-primary: #7cb9ff;
            --tk-primary-soft: #eaf3ff;
            --tk-primary-dark: #4e96f0;
            --tk-primary-deep: #2563a8;
            --tk-room: #f87171;
            --tk-room-soft: #fef2f2;
            --tk-room-deep: #c0392b;
            --tk-success: #34c759;
            --tk-success-soft: #eafaf0;
            --tk-success-deep: #1e8a44;
            --tk-warning: #f5b942;
            --tk-warning-soft: #fef7e8;
            --tk-warning-deep: #9a6b0a;
            --tk-radius: 14px;
            --tk-radius-sm: 9px;
            --tk-shadow: 0 1px 2px rgba(20,20,43,.04), 0 8px 24px -12px rgba(20,20,43,.10);
            --tk-ease: cubic-bezier(.4,0,.2,1);
        }

        * { box-sizing: border-box; }

        .tk-page { animation: tk-fade-in .35s var(--tk-ease); }
        @keyframes tk-fade-in { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
        @media (prefers-reduced-motion: reduce) { .tk-page, .tk-card, .tk-btn { animation: none !important; transition: none !important; } }

        .tk-page h4.tk-title { font-size: 21px; font-weight: 800; letter-spacing: -.2px; color: var(--tk-text); margin: 0 0 4px; }
        .tk-page p.tk-subtitle { font-size: 13.5px; color: var(--tk-text-muted); margin: 0 0 20px; }

        /* ── Card ───────────────────────────────────────────────── */
        .tk-card {
            background: var(--tk-surface);
            border: 1px solid var(--tk-border);
            border-radius: var(--tk-radius);
            box-shadow: var(--tk-shadow);
            overflow: hidden;
            margin-bottom: 22px;
        }
        .tk-card-head { padding: 18px 22px; border-bottom: 1px solid var(--tk-border); display: flex; align-items: center; justify-content: space-between; }
        .tk-card-head h5, .tk-card-head h6 { margin: 0; font-size: 15px; font-weight: 800; color: var(--tk-text); }
        .tk-card-body { padding: 22px; }
        .tk-count-pill { font-size: 11px; font-weight: 800; color: var(--tk-primary-deep); background: var(--tk-primary-soft); border-radius: 999px; padding: 3px 10px; }

        .tk-section-label {
            grid-column: 1 / -1;
            font-size: 11.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .6px;
            color: var(--tk-primary-deep);
            padding-bottom: 6px;
            margin-top: 6px;
            border-bottom: 1px dashed var(--tk-border);
        }
        .tk-section-label:first-child { margin-top: 0; }

        /* ── Form ───────────────────────────────────────────────── */
        .tk-form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 4px 20px; }
        @media (max-width: 700px) { .tk-form-grid { grid-template-columns: 1fr; } }

        .tk-field { margin-bottom: 16px; }
        .tk-field.full { grid-column: 1 / -1; }
        .tk-field label {
            display: block; font-size: 12px; font-weight: 700; color: var(--tk-text-muted);
            text-transform: uppercase; letter-spacing: .3px; margin-bottom: 6px;
        }
        .tk-field .form-control,
        .tk-field .form-select {
            width: 100%;
            border: 1.5px solid var(--tk-border);
            border-radius: var(--tk-radius-sm);
            padding: 10px 12px;
            font-size: 13.5px;
            color: var(--tk-text);
            background: var(--tk-surface);
            transition: border-color .15s var(--tk-ease), box-shadow .15s var(--tk-ease);
        }
        .tk-field .form-control:focus,
        .tk-field .form-select:focus {
            outline: none;
            border-color: var(--tk-primary);
            box-shadow: 0 0 0 3px var(--tk-primary-soft);
        }
        .tk-field .form-control[readonly] { background: var(--tk-bg); color: var(--tk-text-muted); }

        /* ── Buttons ────────────────────────────────────────────── */
        .tk-btn {
            display: inline-flex; align-items: center; gap: 7px;
            font-weight: 700; font-size: 13.5px;
            padding: 10px 20px;
            border-radius: 10px;
            border: 1.5px solid transparent;
            cursor: pointer;
            transition: all .15s var(--tk-ease);
            line-height: 1;
        }
        .tk-btn svg { width: 15px; height: 15px; flex-shrink: 0; }
        .tk-btn-primary { background: var(--tk-primary-dark); color: #fff; box-shadow: 0 6px 16px -8px rgba(78,150,240,.6); }
        .tk-btn-primary:hover { background: var(--tk-primary-deep); }
        .tk-btn:focus-visible { outline: 2px solid var(--tk-primary); outline-offset: 2px; }
        .tk-btn:disabled,
        .tk-btn:disabled:hover {
            background: var(--tk-bg);
            color: var(--tk-text-faint);
            box-shadow: none;
            border-color: var(--tk-border);
            cursor: not-allowed;
        }

        .tk-form-hint {
            font-size: 12px;
            color: var(--tk-text-faint);
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .tk-form-hint svg { width: 13px; height: 13px; flex-shrink: 0; }
        .tk-form-hint.is-ready { color: var(--tk-success-deep); }

        .tk-btn-pill {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 12px; font-weight: 700;
            padding: 6px 12px;
            border-radius: 999px;
            border: none;
            background: var(--tk-success-soft); color: var(--tk-success-deep);
            transition: all .15s var(--tk-ease);
            white-space: nowrap;
        }
        .tk-btn-pill svg { width: 12px; height: 12px; }
        .tk-btn-pill:hover { background: var(--tk-success); color: #fff; }

        /* ── History table ──────────────────────────────────────── */
        .tk-scroll-body { max-height: 420px; overflow-y: auto; }
        .tk-scroll-body::-webkit-scrollbar { width: 6px; }
        .tk-scroll-body::-webkit-scrollbar-thumb { background: var(--tk-border); border-radius: 4px; }
        .tk-scroll-body::-webkit-scrollbar-thumb:hover { background: #d3d7dd; }

        .tk-table { width: 100%; border-collapse: collapse; }
        .tk-table thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: var(--tk-bg);
            font-size: 11px; text-transform: uppercase; letter-spacing: .5px; font-weight: 800;
            color: var(--tk-text-muted);
            padding: 12px 18px;
            text-align: left;
            border-bottom: 1px solid var(--tk-border);
            white-space: nowrap;
        }
        .tk-table tbody td {
            padding: 13px 18px;
            font-size: 13.5px;
            color: var(--tk-text);
            border-bottom: 1px solid var(--tk-border);
            white-space: nowrap;
        }
        .tk-table tbody tr:last-child td { border-bottom: none; }
        .tk-table tbody tr:hover { background: var(--tk-bg); }

        .tk-chip {
            display: inline-flex; align-items: center;
            font-size: 11.5px; font-weight: 700;
            padding: 4px 10px;
            border-radius: 999px;
            background: var(--tk-primary-soft); color: var(--tk-primary-deep);
        }
        .tk-chip.is-muted { background: var(--tk-bg); color: var(--tk-text-muted); }
        .tk-chip.is-requested { background: var(--tk-warning-soft); color: var(--tk-warning-deep); }

        .tk-empty {
            text-align: center; padding: 46px 16px;
            color: var(--tk-text-faint); font-size: 13px;
        }
        .tk-empty svg { width: 32px; height: 32px; opacity: .4; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto; color: var(--tk-text-muted); }
        .tk-empty strong { display: block; color: var(--tk-text-muted); font-weight: 700; font-size: 13.5px; margin-bottom: 3px; }
    </style>
</head>

<body>
    <?php
    $role = $_SESSION['role'];
    switch ($role) {
        case 'User':        include '../user/sidebar.php'; break;
        case 'mis':          include '../mis/sidebar.php'; break;
        case 'Admin':        include '../admin/sidebar.php'; break;
        case 'Super Admin':  include '../super_admin/sidebar.php'; break;
        default: echo "<p>Unauthorized role.</p>"; exit;
    }
    ?>

    <div class="content-wrapper">
        <div class="container-xxl flex-grow-1 container-p-y tk-page">

            <h4 class="tk-title">Leave Request</h4>
            <p class="tk-subtitle">File a new leave application and track the status of your past requests.</p>

            <div class="tk-card">
                <div class="tk-card-head">
                    <h5>Application for Leave</h5>
                </div>
                <div class="tk-card-body">
                    <form method="POST" id="requestForm" action="leaveRequest.php">
                        <input type="hidden" name="save" value="1">

                        <div class="tk-form-grid">
                            <div class="tk-section-label">Personal Information</div>

                            <div class="tk-field">
                                <label for="firstName">First Name</label>
                                <input class="form-control" type="text" id="firstName" name="firstName"
                                    value="<?php echo htmlspecialchars($firstname); ?>" readonly />
                            </div>
                            <div class="tk-field">
                                <label for="middleName">Middle Name</label>
                                <input class="form-control" type="text" name="middleName" id="middleName"
                                    value="<?php echo htmlspecialchars($middlename); ?>" readonly />
                            </div>
                            <div class="tk-field">
                                <label for="lastName">Last Name</label>
                                <input class="form-control" type="text" name="lastName" id="lastName"
                                    value="<?php echo htmlspecialchars($lastname); ?>" readonly />
                            </div>
                            <div class="tk-field">
                                <label for="department">Department</label>
                                <input class="form-control" type="text" name="department"
                                    value="<?php echo htmlspecialchars($department); ?>" readonly>
                            </div>
                            <div class="tk-field">
                                <label for="date_of_filing">Date of Filing</label>
                                <input class="form-control" type="text" id="date_of_filing" name="date_of_filing"
                                    value="<?= date('Y-m-d') ?>" readonly>
                            </div>
                            <div class="tk-field">
                                <label for="position">Position</label>
                                <input class="form-control" type="text" name="position" placeholder="Not on file"
                                    value="<?= htmlspecialchars($user_data['position'] ?? '') ?>" readonly>
                            </div>
                            <div class="tk-field">
                                <label for="salary">Salary</label>
                                <input class="form-control" type="text" name="salary" placeholder="Not on file"
                                    value="<?= htmlspecialchars($user_data['salary'] ?? '') ?>" readonly>
                            </div>

                            <div class="tk-section-label">Leave Details</div>

                            <div class="tk-field">
                                <label for="leave_type">Leave Type</label>
                                <select class="form-select" id="leave_type" name="leave_type" onchange="toggleLeaveFields()" required>
                                    <option value="" disabled selected>Select Leave Type</option>
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
                                        echo "<option value=\"" . htmlspecialchars($option) . "\">" . htmlspecialchars($option) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="tk-field" id="othersField" style="display: none;">
                                <label for="others">Specify:</label>
                                <input class="form-control" type="text" id="others" name="others" placeholder="Specify">
                            </div>

                            <div class="tk-field" id="LeavePhilippines" style="display: none;">
                                <label for="leave_philippines">Within the Philippines:</label>
                                <input class="form-control" type="text" id="leave_philippines" name="leave_philippines" placeholder="Specify">
                            </div>

                            <div class="tk-field" id="LeaveAbroad" style="display: none;">
                                <label for="leave_abroad">Abroad:</label>
                                <input class="form-control" type="text" id="leave_abroad" name="leave_abroad" placeholder="Specify">
                            </div>

                            <div class="tk-field" id="SickLeaveHospital" style="display: none;">
                                <label for="sick_hospital">In Hospital:</label>
                                <input class="form-control" type="text" id="sick_hospital" name="sick_hospital" placeholder="Specify Illness">
                            </div>

                            <div class="tk-field" id="SickLeaveOutPatient" style="display: none;">
                                <label for="sick_outpatient">Out Patient:</label>
                                <input class="form-control" type="text" id="sick_outpatient" name="sick_outpatient" placeholder="Specify Illness">
                            </div>

                            <div class="tk-field" id="SpecialLeaveWomen" style="display: none;">
                                <label for="specialleave_women">In case of Special Leave Benefits for Women:</label>
                                <input class="form-control" type="text" id="specialleave_women" name="specialleave_women" placeholder="Specify Illness">
                            </div>

                            <div class="tk-field" id="StudyLeaveOptions" style="display: none;">
                                <label for="study_leave_options">Study Leave Purpose:</label>
                                <select class="form-select" id="study_leave_options" name="study_leave_options">
                                    <option value="" disabled selected>Select Purpose</option>
                                    <?php
                                    $study_leave_purpose_options = [
                                        "Completion of Master`s Degree",
                                        "BAR/Board Examination Review",
                                        "Monetization of Leave Credits",
                                        "Terminal Leave"
                                    ];
                                    foreach ($study_leave_purpose_options as $option) {
                                        echo "<option value=\"" . htmlspecialchars($option) . "\">" . htmlspecialchars($option) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="tk-field">
                                <label for="start_date">Inclusive Start Date</label>
                                <input class="form-control" type="date" id="start_date" name="start_date" required>
                            </div>
                            <div class="tk-field">
                                <label for="end_date">Inclusive End Date</label>
                                <input class="form-control" type="date" id="end_date" name="end_date" required>
                            </div>
                            <div class="tk-field">
                                <label for="no_of_working_days">Number of Working Days Applied For</label>
                                <input class="form-control" type="text" id="no_of_working_days" name="no_of_working_days" readonly>
                            </div>
                            <div class="tk-field">
                                <label for="commutation">Commutation</label>
                                <select class="form-select" id="commutation" name="commutation" required>
                                    <option value="" selected disabled>Select Commutation</option>
                                    <option value="Not Requested">Not Requested</option>
                                    <option value="Requested">Requested</option>
                                </select>
                            </div>
                        </div>

                        <button type="button" class="tk-btn tk-btn-primary" id="save" disabled>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                            Submit Request
                        </button>
                        <div class="tk-form-hint" id="saveHint">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                            <span id="saveHintText">Fill in the required leave details to enable submission.</span>
                        </div>
                    </form>
                </div>
            </div>

            <div class="tk-card">
                <div class="tk-card-head">
                    <h5>Leave Requests History</h5>
                    <?php
                    require 'db_leave.php'; // include the DB & fetch logic
                    ?>
                    <span class="tk-count-pill"><?= isset($user_leaves) ? count($user_leaves) : 0 ?></span>
                </div>
                <div class="table-responsive tk-scroll-body">
                    <?php if (!empty($user_leaves)): ?>
                        <table class="tk-table">
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
                                <?php foreach ($user_leaves as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['date_of_filing']) ?></td>
                                        <td><span class="tk-chip"><?= htmlspecialchars($row['leave_type']) ?></span></td>
                                        <td><?= htmlspecialchars($row['start_date']) ?></td>
                                        <td><?= htmlspecialchars($row['end_date']) ?></td>
                                        <td><?= htmlspecialchars($row['no_of_working_days']) ?></td>
                                        <td>
                                            <?php if (strtolower($row['commutation']) === 'requested'): ?>
                                                <span class="tk-chip is-requested">Requested</span>
                                            <?php else: ?>
                                                <span class="tk-chip is-muted">Not Requested</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" action="download_leave_excel.php" target="_blank" style="display:inline;">
                                                <input type="hidden" name="leave_id" value="<?= $row['id'] ?>">
                                                <button type="submit" name="download_excel" class="tk-btn-pill">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 19h16"/></svg>
                                                    Excel
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="tk-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                            <strong>No leave requests found</strong>
                            Requests you file will show up here.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
        <div class="content-backdrop fade"></div>
    </div>
    <div class="layout-overlay"></div>

    <script src="../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../assets/vendor/libs/popper/popper.js"></script>
    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pages-account-settings-account.js"></script>

    <script>
        document.getElementById('save').addEventListener('click', function (e) {
            e.preventDefault();

            const form = document.getElementById('requestForm');
            if (!isCoreFieldsValid()) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing details',
                    text: 'Please fill in Leave Type, the Philippines/Abroad location (if applicable), Start Date, End Date, and Commutation before submitting.',
                    confirmButtonColor: '#4e96f0'
                });
                return;
            }

            Swal.fire({
                title: 'File a Leave Request',
                text: 'Are you sure you want to submit?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, submit',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#4e96f0',
                cancelButtonColor: '#c0392b',
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    </script>

    <script>
        function calculateWorkingDays(start, end) {
            let count = 0;
            const startDate = new Date(start);
            const endDate = new Date(end);

            while (startDate <= endDate) {
                const day = startDate.getDay();
                if (day !== 0 && day !== 6) {
                    count++;
                }
                startDate.setDate(startDate.getDate() + 1);
            }

            return count;
        }

        function updateWorkingDays() {
            const start = document.getElementById('start_date').value;
            const end = document.getElementById('end_date').value;

            if (start && end && new Date(start) <= new Date(end)) {
                document.getElementById('no_of_working_days').value = calculateWorkingDays(start, end);
            } else {
                document.getElementById('no_of_working_days').value = '';
            }
        }

        document.getElementById('start_date').addEventListener('change', updateWorkingDays);
        document.getElementById('end_date').addEventListener('change', updateWorkingDays);
    </script>

    <script>
        function toggleLeaveFields() {
            const leaveType = document.getElementById("leave_type").value;
            const fieldsByType = {
                "Vacation Leave": ["LeavePhilippines", "LeaveAbroad"],
                "Special Privilege Leave": ["LeavePhilippines", "LeaveAbroad"],
                "Sick Leave": ["SickLeaveHospital", "SickLeaveOutPatient"],
                "Special Leave Benefits for Women": ["SpecialLeaveWomen"],
                "Study Leave": ["StudyLeaveOptions"],
                "Others": ["othersField"],
            };
            const allConditional = ["LeavePhilippines", "LeaveAbroad", "SickLeaveHospital", "SickLeaveOutPatient", "SpecialLeaveWomen", "StudyLeaveOptions", "othersField"];
            const toShow = fieldsByType[leaveType] || [];

            allConditional.forEach(id => {
                document.getElementById(id).style.display = toShow.includes(id) ? "block" : "none";
            });

            updateSaveButtonState();
        }

        // The submit button only cares about the fields the user actually fills in by hand:
        // Leave Type, Within the Philippines / Abroad (either/or, when relevant), Start Date,
        // End Date, and Commutation. Everything else (readonly DB fields, optional detail
        // fields) does not gate the button.
        function isCoreFieldsValid() {
            const leaveType = document.getElementById('leave_type').value;
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const commutation = document.getElementById('commutation').value;

            if (!leaveType || !startDate || !endDate || !commutation) return false;

            const needsLocation = leaveType === 'Vacation Leave' || leaveType === 'Special Privilege Leave';
            if (needsLocation) {
                const phil = document.getElementById('leave_philippines').value.trim();
                const abroad = document.getElementById('leave_abroad').value.trim();
                if (!phil && !abroad) return false;
            }

            return true;
        }

        function updateSaveButtonState() {
            const save = document.getElementById('save');
            const hint = document.getElementById('saveHint');
            const isValid = isCoreFieldsValid();

            save.disabled = !isValid;
            hint.classList.toggle('is-ready', isValid);
            document.getElementById('saveHintText').textContent = isValid
                ? 'All set — ready to submit.'
                : 'Fill in the required leave details to enable submission.';
        }

        document.getElementById("leave_type").addEventListener("change", toggleLeaveFields);
        document.getElementById('requestForm').addEventListener('input', updateSaveButtonState);
        document.getElementById('requestForm').addEventListener('change', updateSaveButtonState);
        document.addEventListener("DOMContentLoaded", toggleLeaveFields);
    </script>



    <script>
        // Guard against browsers accepting a partial/invalid year in date inputs
        document.querySelectorAll('input[type="date"]').forEach(input => {
            input.addEventListener('blur', () => {
                const year = input.value.split('-')[0];
                if (input.value && year.length !== 4) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Invalid date',
                        text: 'Please enter a valid date with a 4-digit year.',
                        confirmButtonColor: '#4e96f0'
                    });
                    input.value = '';
                }
            });
        });
    </script>

    <?php if ($alert): ?>
    <script>
        Swal.fire({
            icon: <?= json_encode($alert['type']) ?>,
            title: <?= json_encode($alert['type'] === 'success' ? 'Leave Request Submitted!' : 'Submission Failed') ?>,
            text: <?= json_encode($alert['message']) ?>,
            confirmButtonColor: '#4e96f0'
        });
    </script>
    <?php endif; ?>
</body>

</html>