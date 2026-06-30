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
        $user_id,
        $leave_type,
        $others,
        $leave_philippines,
        $leave_abroad,
        $sick_hospital,
        $sick_outpatient,
        $specialleave_women,
        $study_leave_options,
        $start_date,
        $end_date,
        $no_of_working_days,
        $commutation,
        $date_of_filing
    );

    if ($insert->execute()) {
        echo "<script>
        Swal.fire({
            icon: 'success',
            title: 'Leave Request Submitted!',
            text: 'Your leave request has been filed successfully.',
            confirmButtonColor: '#007bff'
        }).then(() => {
            window.location.href = 'leaveRequest.php';
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
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/"
    data-template="vertical-menu-template-free">

<head>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Leave Request</title>

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
    <link rel="stylesheet" href="./css/leaveRequest.css">

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <!-- Page CSS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
                            <form method="POST" id="requestForm" action="leaveRequest.php">
                                <input type="hidden" name="save" value="1">
                                <div class="row">
                                    <!-- Personal Information -->
                                    <h4>Application for Leave</h4>
                                    <div class="mb-3 col-md-6">
                                        <label for="firstName" class="form-label">First Name</label>

                                        <input class="form-control" type="text" id="firstName" name="firstName"
                                            autofocus value="<?php echo htmlspecialchars($firstname); ?>" readonly />


                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label for="lastName" class="form-label">Middle Name</label>
                                        <input class="form-control" type="text" name="middleName" id="middleName"
                                            autofocus value="<?php echo htmlspecialchars($middlename); ?>" readonly />
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="lastName" class="form-label">Last Name</label>
                                        <input class="form-control" type="text" name="lastName" id="lastName" autofocus
                                            value="<?php echo htmlspecialchars($lastname); ?>" readonly />
                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label for="department" class="form-label">Department</label>
                                        <input class="form-control" type="text" name="department"
                                            value="<?php echo htmlspecialchars($department); ?>" readonly>
                                    </div>



                                    <div class="mb-3 col-md-6">
                                        <label for="date_of_filing" class="form-label">Date of Filing</label>
                                        <input class="form-control" type="text" id="date_of_filing"
                                            name="date_of_filing" value="<?= date('Y-m-d') ?>" readonly>
                                    </div>


                                    <div class="mb-3 col-md-6">
                                        <label for="position" class="form-label">Position</label>
                                        <input class="form-control" type="text" name="position"
                                            placeholder="Input your Position"
                                            value="<?= htmlspecialchars($user_data['position'] ?? '') ?>" readonly>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="salary" class="form-label">Salary</label>
                                        <input class="form-control" type="text" name="salary"
                                            placeholder="Input your Salary"
                                            value="<?= htmlspecialchars($user_data['salary'] ?? '') ?>" readonly>


                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="leave_type" class="form-label">Leave Type</label>
                                        <select class="select2 form-select" id="leave_type" name="leave_type"
                                            onchange="toggleLeaveFields()">
                                            <option value="" disabled <?= empty($leave_type) ? 'selected' : ''; ?>>Select
                                                Leave Type</option>
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
                                        <input class="form-control" type="text" id="others" name="others"
                                            placeholder="Specify"
                                            value="<?= htmlspecialchars($leave_data['others'] ?? '') ?>">
                                    </div>



                                    <!-- Within the Philippines -->
                                    <div class="mb-3 col-md-6" id="LeavePhilippines" style="display: none;">
                                        <label for="leave_philippines" class="form-label">Within the
                                            Philippines:</label>
                                        <input class="form-control" type="text" id="leave_philippines"
                                            name="leave_philippines" placeholder="Specify"
                                            value="<?= htmlspecialchars($leave_data['leave_philippines'] ?? '') ?>">
                                    </div>

                                    <!-- Abroad -->
                                    <div class="mb-3 col-md-6" id="LeaveAbroad" style="display: none;">
                                        <label for="leave_abroad" class="form-label">Abroad:</label>
                                        <input class="form-control" type="text" id="leave_abroad" name="leave_abroad"
                                            placeholder="Specify"
                                            value="<?= htmlspecialchars($leave_data['leave_abroad'] ?? '') ?>">
                                    </div>

                                    <!-- Sick Leave: In Hospital -->
                                    <div class="mb-3 col-md-6" id="SickLeaveHospital" style="display: none;">
                                        <label for="sick_hospital" class="form-label">In Hospital:</label>
                                        <input class="form-control" type="text" id="sick_hospital" name="sick_hospital"
                                            placeholder="Specify Illness"
                                            value="<?= htmlspecialchars($leave_data['sick_hospital'] ?? '') ?>">
                                    </div>

                                    <!-- Sick Leave: Out Patient -->
                                    <div class="mb-3 col-md-6" id="SickLeaveOutPatient" style="display: none;">
                                        <label for="sick_outpatient" class="form-label">Out Patient:</label>
                                        <input class="form-control" type="text" id="sick_outpatient"
                                            name="sick_outpatient" placeholder="Specify Illness"
                                            value="<?= htmlspecialchars($leave_data['sick_outpatient'] ?? '') ?>">
                                    </div>

                                    <!-- Special Leave Benefits for Women -->
                                    <div class="mb-3 col-md-6" id="SpecialLeaveWomen" style="display: none;">
                                        <label for="specialleave_women" class="form-label">In case of Special Leave
                                            Benefits for Women:</label>
                                        <input class="form-control" type="text" id="specialleave_women"
                                            name="specialleave_women" placeholder="Specify Illness"
                                            value="<?= htmlspecialchars($leave_data['specialleave_women'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6" id="StudyLeaveOptions" style="display: none;">
                                        <label for="study_leave_options" class="form-label">Study Leave Purpose:</label>
                                        <select class="select2 form-select" id="study_leave_options"
                                            name="study_leave_options">
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

                                    <!-- Start Date -->
                                    <div class="mb-3 col-md-6">
                                        <label for="start_date" class="form-label">Inclusive Start Date</label>
                                        <input class="form-control" type="date" id="start_date" name="start_date"
                                            value="<?= htmlspecialchars($leave_data['start_date'] ?? '') ?>">
                                    </div>

                                    <!-- End Date -->
                                    <div class="mb-3 col-md-6">
                                        <label for="end_date" class="form-label">Inclusive End Date</label>
                                        <input class="form-control" type="date" id="end_date" name="end_date"
                                            value="<?= htmlspecialchars($leave_data['end_date'] ?? '') ?>">
                                    </div>


                                    <!-- Auto Calculated Working Days -->
                                    <div class="mb-3 col-md-6">
                                        <label for="no_of_working_days" class="form-label">Number of Working Days
                                            Applied For</label>
                                        <input class="form-control" type="text" id="no_of_working_days"
                                            name="no_of_working_days"
                                            value="<?= htmlspecialchars($leave_data['no_of_working_days'] ?? '') ?>">
                                    </div>


                                    <div class="mb-3 col-md-6">
                                        <label for="commutation" class="form-label">Commutation</label>
                                        <select class="select2 form-select" id="commutation" name="commutation">
                                            <option value="" selected disabled>Select Commutation</option>
                                            <option value="Not Requested">Not Requested</option>
                                            <option value="Requested">Requested</option>
                                        </select>
                                    </div>
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-primary me-2" name="save" id="save"
                                            style="background-color: #007bff; border-color: #007bff; position:relative; top:10px; left:-1px;">Save</button>

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
                        require 'db_leave.php'; // include the DB & fetch logic
                        
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
                                            <button type="submit" name="download_excel" id="download_excel"
                                                class="btn btn-outline-secondary"
                                                style="background-color: #3CB371; border-color: #3CB371; color: white; position: relative; left: 10px; top: 10px;">
                                                Download as Excel
                                            </button>

                                            <!-- #region -->
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


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
        document.addEventListener("DOMContentLoaded", function () {
            toggleLeaveFields();
        });
    </script>

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

</body>

</html>