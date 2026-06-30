<?php
require '../db.php'; // Include database connection
require 'db_datasheet.php';
//require __DIR__ . '/../../vendor/autoload.php';
require 'login_verification.php';

// Establish database connection
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$username = $_SESSION['username']; // Retrieve logged-in user username

date_default_timezone_set('Asia/Manila'); // Set timezone to your local
$now = date('Y-m-d H:i:s');
$updateActivity = $conn->prepare("UPDATE users SET last_activity = ? WHERE username = ?");
$updateActivity->bind_param("ss", $now, $username);
$updateActivity->execute();
$updateActivity->close();

// Verify user exists in `users` table and retrieve their ID
$user_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
$user_check->bind_param("s", $username);
$user_check->execute();
$user_check->bind_result($id);
$user_check->fetch();
$user_check->close();

if (!$id) {
    die("Error: User does not exist in the database.");
}
// Fetch the current user details
$username = $_SESSION['username'];
$query = $conn->prepare("SELECT id, firstname, middlename, lastname, email, department FROM users WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->store_result();

if ($query->num_rows === 0) {
    die("User not found.");
}

$query->bind_result($user_id, $firstname, $middlename, $lastname, $email, $department);
$query->fetch();
$query->close();

// Verify user exists in `users` table and retrieve their ID
$user_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
$user_check->bind_param("s", $username);
$user_check->execute();
$user_check->bind_result($id);
$user_check->fetch();
$user_check->close();


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

    <title>Personal Data Sheet</title>

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
    <link rel="stylesheet" href="./css/personaldataSheet.css">

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
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
            <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Account /</span> Personal Data Sheet</h4>
            <div class="row">
                <div class="col-md-12">
                    <div class="card mb-4">
                        <!-- Account -->
                        <hr class="my-0" />
                        <div class="card-body">
                            <form method="POST" id="dataForm" action="personaldataSheet.php">
                                <div class="row">
                                    <!-- Personal Information -->
                                    <h4>I. Personal Information</h4>
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
                                        <label for="name_extension" class="form-label">Name Extension</label>
                                        <input class="form-control" type="text" name="name_extension"
                                            placeholder="JR/SR"
                                            value="<?= htmlspecialchars($user_data['name_extension'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="department" class="form-label">Department</label>
                                        <input class="form-control" type="texzt" name="department" id="department"
                                            autofocus value="<?php echo htmlspecialchars($department); ?>" readonly />
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="agency_employee_no" class="form-label">AGENCY EMPLOYEE NO.</label>
                                        <input class="form-control" type="text" name="agency_employee_no"
                                            placeholder="Input your Agency Employee number"
                                            value="<?= htmlspecialchars($user_data['agency_employee_no'] ?? '') ?>" >
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="position" class="form-label">Position</label>
                                        <input class="form-control" type="text" name="position" placeholder="Position"
                                            value="<?= htmlspecialchars($user_data['position'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="date_hired" class="form-label">Date Hired</label>
                                        <input class="form-control" type="date" id="date_hired" name="date_hired"
                                            value="<?= htmlspecialchars($user_data['date_hired'] ?? '') ?>">
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


                                    <?php
                                    // Ensure the variables are initialized to avoid undefined errors
                                    $employeeSalaryGrade = $employeeSalaryGrade ?? '';
                                    $sgStep = $sgStep ?? '';
                                    ?>

                                    <div class="mb-3 col-md-6">
                                        <label class="form-label" for="employeeSalaryGrade">Salary Grade</label>
                                        <select id="employeeSalaryGrade" name="employeeSalaryGrade"
                                            class="select2 form-select">
                                            <option value="" disabled <?= empty($employeeSalaryGrade) ? 'selected' : ''; ?>>Select Salary Grade</option>
                                            <?php for ($i = 1; $i <= 33; $i++): ?>
                                                <option value="<?= $i ?>" <?= ($employeeSalaryGrade == (string) $i) ? 'selected' : ''; ?>><?= $i ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label class="form-label" for="sgStep">Step</label>
                                        <select id="sgStep" name="sgStep" class="select2 form-select">
                                            <option value="" disabled <?= empty($sgStep) ? 'selected' : ''; ?>>Select
                                                Step</option>
                                            <?php for ($i = 1; $i <= 8; $i++): ?>
                                                <option value="<?= $i ?>" <?= ($sgStep == (string) $i) ? 'selected' : ''; ?>>
                                                    <?= $i ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="salary" class="form-label">Salary</label>
                                        <input class="form-control" type="number" id="salary" name="salary"
                                            value="<?= htmlspecialchars($user_data['salary'] ?? '') ?>" readonly>


                                    </div>

                                    <!-- JavaScript to handle Salary Auto-fill -->
                                    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                                    <script>
                                        // Salary data: SG => { Step => Salary }
                                        const salaryData = {
                                            1: { 1: 14061, 2: 14164, 3: 14278, 4: 14393, 5: 14509, 6: 14626, 7: 14743, 8: 14862 },
                                            2: { 1: 14925, 2: 15035, 3: 15146, 4: 15258, 5: 15371, 6: 15484, 7: 15599, 8: 15714 },
                                            3: { 1: 15852, 2: 15971, 3: 16088, 4: 16208, 5: 16329, 6: 16448, 7: 16571, 8: 16693 },
                                            4: { 1: 16833, 2: 16958, 3: 17084, 4: 17209, 5: 17337, 6: 17464, 7: 17594, 8: 17724 },
                                            5: { 1: 17866, 2: 18000, 3: 18133, 4: 18267, 5: 18401, 6: 18538, 7: 18676, 8: 18813 },

                                            6: { 1: 18957, 2: 19098, 3: 19239, 4: 19383, 5: 19526, 6: 19670, 7: 19816, 8: 19963 },
                                            7: { 1: 20110, 2: 20258, 3: 20408, 4: 20560, 5: 20711, 6: 20865, 7: 21019, 8: 21175 },
                                            8: { 1: 21448, 2: 21642, 3: 21839, 4: 22035, 5: 22234, 6: 22435, 7: 22638, 8: 22843 },
                                            9: { 1: 23226, 2: 23411, 3: 23599, 4: 23788, 5: 23978, 6: 24170, 7: 24364, 8: 24558 },
                                            10: { 1: 25586, 2: 25790, 3: 25996, 4: 26203, 5: 26412, 6: 26623, 7: 26835, 8: 27050 },

                                            11: { 1: 30024, 2: 30308, 3: 30597, 4: 30889, 5: 31185, 6: 31486, 7: 31790, 8: 32099 },
                                            12: { 1: 32245, 2: 32529, 3: 32817, 4: 33108, 5: 33403, 6: 33702, 7: 34004, 8: 34310 },
                                            13: { 1: 34421, 2: 34733, 3: 35049, 4: 35369, 5: 35694, 6: 36022, 7: 36354, 8: 36691 },
                                            14: { 1: 37024, 2: 37384, 3: 37749, 4: 38118, 5: 38491, 6: 38869, 7: 39252, 8: 39640 },
                                            15: { 1: 40208, 2: 40604, 3: 41006, 4: 41413, 5: 41824, 6: 42241, 7: 42662, 8: 43090 },

                                            16: { 1: 43560, 2: 43996, 3: 44438, 4: 44885, 5: 45338, 6: 45796, 7: 46261, 8: 46730 },
                                            17: { 1: 47247, 2: 47727, 3: 48213, 4: 48705, 5: 49203, 6: 49708, 7: 50218, 8: 50735 },
                                            18: { 1: 51304, 2: 51832, 3: 52367, 4: 52907, 5: 53456, 6: 54010, 7: 54572, 8: 55140 },
                                            19: { 1: 56390, 2: 57165, 3: 57953, 4: 58753, 5: 59567, 6: 60394, 7: 61235, 8: 62089 },
                                            20: { 1: 62967, 2: 63842, 3: 64732, 4: 65637, 5: 66557, 6: 67479, 7: 68409, 8: 69342 },

                                            16: { 1: 43560, 2: 43996, 3: 44438, 4: 44885, 5: 45338, 6: 45796, 7: 46261, 8: 46730 },
                                            17: { 1: 47247, 2: 47727, 3: 48213, 4: 48705, 5: 49203, 6: 49708, 7: 50218, 8: 50735 },
                                            18: { 1: 51304, 2: 51832, 3: 52367, 4: 52907, 5: 53456, 6: 54010, 7: 54572, 8: 55140 },
                                            19: { 1: 56390, 2: 57165, 3: 57953, 4: 58753, 5: 59567, 6: 60394, 7: 61235, 8: 62089 },
                                            20: { 1: 62967, 2: 63842, 3: 64732, 4: 65637, 5: 66557, 6: 67479, 7: 68409, 8: 69342 },

                                            21: { 1: 70013, 2: 71000, 3: 72004, 4: 73024, 5: 74061, 6: 75115, 7: 76151, 8: 77239 },
                                            22: { 1: 78162, 2: 79277, 3: 80411, 4: 81564, 5: 82735, 6: 83887, 7: 85096, 8: 86324 },
                                            23: { 1: 87315, 2: 88574, 3: 89855, 4: 91163, 5: 92592, 6: 94043, 7: 95518, 8: 96955 },
                                            24: { 1: 98185, 2: 99721, 3: 101283, 4: 102871, 5: 104483, 6: 106123, 7: 107739, 8: 109431 },
                                            25: { 1: 111727, 2: 113476, 3: 115254, 4: 117062, 5: 118899, 6: 120766, 7: 122664, 8: 124591 },

                                            26: { 1: 126252, 2: 128228, 3: 130238, 4: 132280, 5: 134356, 6: 136465, 7: 138608, 8: 140788 },
                                            27: { 1: 142663, 2: 144897, 3: 147169, 4: 149407, 5: 151752, 6: 153850, 7: 156267, 8: 158723 },
                                            28: { 1: 160469, 2: 162988, 3: 165548, 4: 167994, 5: 170634, 6: 173320, 7: 175803, 8: 178572 },
                                            29: { 1: 180492, 2: 183332, 3: 186218, 4: 189151, 5: 192131, 6: 194797, 7: 197870, 8: 200993 },
                                            30: { 1: 203200, 2: 206401, 3: 209558, 4: 212766, 5: 216022, 6: 219434, 7: 222797, 8: 226319 },
                                            31: { 1: 293191, 2: 298773, 3: 304464, 4: 310119, 5: 315883, 6: 321846, 7: 327895, 8: 334059 },
                                            32: { 1: 347888, 2: 354743, 3: 361736, 4: 368694, 5: 375969, 6: 383391, 7: 390963, 8: 398686 },
                                            33: { 1: 438844, 2: 451713 },

                                            // Add other salary grades and steps here
                                        };

                                        function updateSalary() {
                                            let grade = $("#employeeSalaryGrade").val();
                                            let step = $("#sgStep").val();

                                            if (grade && step && salaryData[grade] && salaryData[grade][step]) {
                                                $("#salary").val(salaryData[grade][step]);
                                            } else {
                                                $("#salary").val('');
                                            }
                                        }

                                        $("#employeeSalaryGrade, #sgStep").change(updateSalary);
                                    </script>

                                    <div class="mb-3 col-md-6">
                                        <label for="personal_date_of_birth" class="form-label">Date of Birth</label>
                                        <input class="form-control" type="date" id="personal_date_of_birth"
                                            name="personal_date_of_birth" value="<?= $personal_date_of_birth; ?>" />
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

                                    <div class="mb-3 col-md-6">
                                        <label for="place_of_birth" class="form-label">Place of Birth</label>
                                        <input class="form-control" type="text" name="place_of_birth"
                                            placeholder="Input your place of birth"
                                            value="<?= htmlspecialchars($user_data['place_of_birth'] ?? '') ?>">
                                    </div>


                                    <div class="mb-3 col-md-6">
                                        <label class="form-label" for="sex">Sex</label>
                                        <select id="sex" name="sex" class="select2 form-select">
                                            <option value="" disabled <?= empty($sex) ? 'selected' : ''; ?>>Select Sex
                                            </option>
                                            <option value="Male" <?= ($sex == 'Male') ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?= ($sex == 'Female') ? 'selected' : ''; ?>>Female
                                            </option>
                                        </select>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label class="form-label" for="civil_status">Civil Status</label>
                                        <select id="civil_status" name="civil_status" class="select2 form-select">
                                            <option value="" disabled <?= !isset($_SESSION['civil_status']) ? 'selected' : ''; ?>>Select Civil Status</option>
                                            <option value="Single" <?= ($civil_status == 'Single') ? 'selected' : ''; ?>>
                                                Single</option>
                                            <option value="Married" <?= ($civil_status == 'Married') ? 'selected' : ''; ?>>
                                                Married</option>
                                            <option value="Separated/Divorced" <?= ($civil_status == 'Seperated/Divored') ? 'selected' : ''; ?>>Separated/Divorced</option>
                                            <option value="Widowed" <?= ($civil_status == 'Widowed') ? 'selected' : ''; ?>>
                                                Widowed</option>
                                        </select>
                                    </div>


                                    <div class="mb-3 col-md-6">
                                        <label for="height" class="form-label">Height</label>
                                        <input class="form-control" type="text" name="height"
                                            placeholder="Height in meters"
                                            value="<?= htmlspecialchars($user_data['height'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="weight" class="form-label">Weight</label>
                                        <input class="form-control" type="text" name="weight"
                                            placeholder="Weight in Kilograms"
                                            value="<?= htmlspecialchars($user_data['weight'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label class="form-label" for="blood_type">Blood Type</label>
                                        <select id="blood_type" name="blood_type" class="select2 form-select">
                                            <option value="" disabled <?= !isset($_SESSION['blood_type']) ? 'selected' : ''; ?>>Select Blood Type</option>
                                            <option value="A+" <?= ($blood_type == 'A+') ? 'selected' : ''; ?>>A+</option>
                                            <option value="A-" <?= ($blood_type == 'A-') ? 'selected' : ''; ?>>A-</option>
                                            <option value="B+" <?= ($blood_type == 'B+') ? 'selected' : ''; ?>>B+</option>
                                            <option value="B-" <?= ($blood_type == 'B-') ? 'selected' : ''; ?>>B-</option>
                                            <option value="O+" <?= ($blood_type == 'O+') ? 'selected' : ''; ?>>O+</option>
                                            <option value="O-" <?= ($blood_type == 'O-') ? 'selected' : ''; ?>>O-</option>
                                            <option value="AB+" <?= ($blood_type == 'AB+') ? 'selected' : ''; ?>>AB+
                                            </option>
                                            <option value="AB-" <?= ($blood_type == 'AB-') ? 'selected' : ''; ?>>AB-
                                            </option>
                                        </select>
                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label for="gsis_id_no" class="form-label">GSIS ID NO.</label>
                                        <input class="form-control" type="text" name="gsis_id_no"
                                            placeholder="Input your GSIS ID number"
                                            value="<?= htmlspecialchars($user_data['gsis_id_no'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="pagibig_id_no" class="form-label">PAG-IBIG ID NO.</label>
                                        <input class="form-control" type="text" name="pagibig_id_no"
                                            placeholder="Input your PagIbig ID number"
                                            value="<?= htmlspecialchars($user_data['pagibig_id_no'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label for="philhealth_no" class="form-label">PHILHEALTH NO.</label>
                                        <input class="form-control" type="text" name="philhealth_no"
                                            placeholder="Input your PhilHealth number"
                                            value="<?= htmlspecialchars($user_data['philhealth_no'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label for="sss_no" class="form-label">SSS NO.</label>
                                        <input class="form-control" type="text" name="sss_no"
                                            placeholder="Input your SSS number"
                                            value="<?= htmlspecialchars($user_data['sss_no'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label for="tin_no" class="form-label">TIN NO.</label>
                                        <input class="form-control" type="text" name="tin_no"
                                            placeholder="Input your TIN number"
                                            value="<?= htmlspecialchars($user_data['tin_no'] ?? '') ?>">
                                    </div>


                                    <div class="mb-3 col-md-6">
                                        <label for="citizenship" class="form-label">Citizenship</label>
                                        <select class="select2 form-select" id="citizenship" name="citizenship"
                                            onchange="toggleCitizenshipFields()">
                                            <option value="" disabled <?= empty($citizenship) ? 'selected' : ''; ?>>
                                                Citizenship</option>
                                            <option value="Filipino" <?= ($citizenship == 'Filipino') ? 'selected' : ''; ?>>Filipino</option>
                                            <option value="Dual" <?= ($citizenship == 'Dual') ? 'selected' : ''; ?>>Dual
                                            </option>

                                        </select>
                                    </div>


                                    <div class="mb-3 col-md-6" id="dualCitizenshipDetails" style="display: none;">
                                        <label for="dual_holder" class="form-label">If holder of Dual Citizenship,
                                            please indicate details</label>
                                        <input class="form-control" type="text" id="dual_holder" name="dual_holder"
                                            placeholder="Specify your Other Citizenship"
                                            value="<?= htmlspecialchars($user_data['dual_holder'] ?? '') ?>" />
                                    </div>

                                    <script>
                                        function toggleCitizenshipFields() {
                                            var citizenship = document.getElementById("citizenship").value;
                                            var dualCitizenshipDetails = document.getElementById("dualCitizenshipDetails");

                                            dualCitizenshipDetails.style.display = (citizenship === "Dual") ? "block" : "none";
                                        }
                                        // Run function on page load to reflect database data
                                        document.addEventListener("DOMContentLoaded", function () {
                                            toggleCitizenshipFields();
                                        });
                                    </script>


                                    <div class="mb-3 col-md-6">
                                        <label for="resident" class="form-label">Residential Address ||</label>
                                        <label for="RA_house_block_lot_no" class="form-label">House Block Lot
                                            No.</label>
                                        <input class="form-control" type="text" id="RA_house_block_lot_no"
                                            name="RA_house_block_lot_no" placeholder="House Block Lot No."
                                            value="<?= htmlspecialchars($user_data['RA_house_block_lot_no'] ?? '') ?>">

                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label for="RA_subdivision_village" class="form-label">Residential Address ||
                                        </label>
                                        <label for="RA_subdivision_village"
                                            class="form-label">Subdivision/Village</label>
                                        <input class="form-control" type="text" id="RA_subdivision_village"
                                            name="RA_subdivision_village" placeholder="Subdivision/Village"
                                            value="<?= htmlspecialchars($user_data['RA_subdivision_village'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="RA_city_municipality" class="form-label">Residential Address ||
                                            City/Municipality Address</label>
                                        <input class="form-control" type="text" id="RA_city_municipality"
                                            name="RA_city_municipality" placeholder="City/Municipality"
                                            value="<?= htmlspecialchars($user_data['RA_city_municipality'] ?? '') ?>">

                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="RA_street" class="form-label">Residential Address || Street</label>
                                        <input class="form-control" type="text" id="RA_street" name="RA_street"
                                            placeholder="Street"
                                            value="<?= htmlspecialchars($user_data['RA_street'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label for="RA_barangay" class="form-label">Residential Address ||
                                            Barangay</label>
                                        <input class="form-control" type="text" id="RA_barangay" name="RA_barangay"
                                            placeholder="Barangay"
                                            value="<?= htmlspecialchars($user_data['RA_barangay'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label for="RA_province" class="form-label">Residential Address ||
                                            Province</label>
                                        <input class="form-control" type="text" id="RA_province" name="RA_province"
                                            placeholder="Province"
                                            value="<?= htmlspecialchars($user_data['RA_province'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label for="RA_zip_code" class="form-label">Residential Address || ZIP
                                            CODE</label>
                                        <input class="form-control" type="text" id="RA_zip_code" name="RA_zip_code"
                                            placeholder="ZIP CODE"
                                            value="<?= htmlspecialchars($user_data['RA_zip_code'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="PA_house_block_lot_no" class="form-label">Permanent Address ||
                                        </label>
                                        <label for="PA_house_block_lot_no" class="form-label">House Block Lot
                                            No.</label>
                                        <input class="form-control" type="text" id="PA_house_block_lot_no"
                                            name="PA_house_block_lot_no" placeholder="House Block Lot No."
                                            value="<?= htmlspecialchars($user_data['PA_house_block_lot_no'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="PA_subdivision_village" class="form-label">Permanent Address ||
                                        </label>
                                        <label for="PA_subdivision_village"
                                            class="form-label">Subdivision/Village</label>
                                        <input class="form-control" type="text" id="PA_subdivision_village"
                                            name="PA_subdivision_village" placeholder="Subdivision/Village"
                                            value="<?= htmlspecialchars($user_data['PA_subdivision_village'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="PA_city_municipality" class="form-label">Permanent Address ||
                                            City/Municipality Address</label>
                                        <input class="form-control" type="text" id="PA_city_municipality"
                                            name="PA_city_municipality" placeholder="City/Municipality"
                                            value="<?= htmlspecialchars($user_data['PA_city_municipality'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="PA_street" class="form-label">Permanent Address || Street</label>
                                        <input class="form-control" type="text" id="PA_street" name="PA_street"
                                            placeholder="Street"
                                            value="<?= htmlspecialchars($user_data['PA_street'] ?? '') ?>">

                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label for="PA_barangay" class="form-label">Permanent Address ||
                                            Barangay</label>
                                        <input class="form-control" type="text" id="PA_barangay" name="PA_barangay"
                                            placeholder="Barangay"
                                            value="<?= htmlspecialchars($user_data['PA_barangay'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="PA_province" class="form-label">Permanent Address ||
                                            Province</label>
                                        <input class="form-control" type="text" id="PA_province" name="PA_province"
                                            placeholder="Province"
                                            value="<?= htmlspecialchars($user_data['PA_province'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="PA_zip_code" class="form-label">Permanent Address || ZIP
                                            CODE</label>
                                        <input class="form-control" type="text" id="PA_zip_code" name="PA_zip_code"
                                            placeholder="ZIP CODE"
                                            value="<?= htmlspecialchars($user_data['PA_zip_code'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="telephone_no" class="form-label">Telephone No.</label>
                                        <input class="form-control" type="text" id="telephone_no" name="telephone_no"
                                            autofocus placeholder="Input your Telephone Number"
                                            value="<?= htmlspecialchars($user_data['telephone_no'] ?? '') ?>">

                                    </div>


                                    <div class="mb-3 col-md-6">
                                        <label for="mobile_no" class="form-label">Mobile No.</label>
                                        <input class="form-control" type="text" id="mobile_no" name="mobile_no"
                                            placeholder="Input your Mobile Number"
                                            value="<?= htmlspecialchars($user_data['mobile_no'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input class="form-control" type="text" id="email" name="email"
                                            value="<?php echo htmlspecialchars($email); ?>" readonly />
                                    </div>


                                    <!-- END Personal Information -->

                                    <!-- Family Background -->
                                    <h4> II. Family Background</h4>
                                    <h5>Spouse's Information</h5>
                                    <div class="mb-3 col-md-6">
                                        <label for="spouse_first_name" class="form-label">Spouse's First Name</label>
                                        <input class="form-control" type="text" id="spouse_first_name"
                                            name="spouse_first_name" placeholder="Input your Spouse's First Name"
                                            autofocus
                                            value="<?= htmlspecialchars($family_data['spouse_first_name'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="spouse_extension_name" class="form-label">Name Extension (If
                                            applicable)</label>
                                        <input class="form-control" type="text" id="spouse_extension_name"
                                            name="spouse_extension_name" placeholder="JR/SR" autofocus
                                            value="<?= htmlspecialchars($family_data['spouse_extension_name'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="spouse_middle_name" class="form-label">Spouse's Middle Name</label>
                                        <input class="form-control" type="text" id="spouse_middle_name"
                                            name="spouse_middle_name" placeholder="Input your Spouse's Middle Name"
                                            autofocus
                                            value="<?= htmlspecialchars($family_data['spouse_middle_name'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="spouse_last_name" class="form-label">Spouse's Last Name</label>
                                        <input class="form-control" type="text" id="spouse_last_name"
                                            name="spouse_last_name" placeholder="Input your Spouse's Last Name"
                                            autofocus
                                            value="<?= htmlspecialchars($family_data['spouse_last_name'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="spouse_occupation" class="form-label">Spouse's Occupation</label>
                                        <input class="form-control" type="text" id="spouse_occupation"
                                            name="spouse_occupation" placeholder="Input your Spouse's Occupation"
                                            autofocus
                                            value="<?= htmlspecialchars($family_data['spouse_occupation'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="spouse_employer_business_name" class="form-label">Spouse's
                                            Employer/Business Name</label>
                                        <input class="form-control" type="text" id="spouse_employer_business_name"
                                            name="spouse_employer_business_name"
                                            placeholder="Input your Spouse's Employer/Business Name" autofocus
                                            value="<?= htmlspecialchars($family_data['spouse_employer_business_name'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="spouse_business_address" class="form-label">Spouse's Business
                                            Address</label>
                                        <input class="form-control" type="text" id="spouse_business_address"
                                            name="spouse_business_address"
                                            placeholder="Input your Spouse's Business Address" autofocus
                                            value="<?= htmlspecialchars($family_data['spouse_business_address'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="spouse_telephone_no" class="form-label"> Spouse's Telephone
                                            No.</label>
                                        <input class="form-control" type="text" id="spouse_telephone_no"
                                            name="spouse_telephone_no"
                                            placeholder="Input your Spouse's Telephone Number" autofocus
                                            value="<?= htmlspecialchars($family_data['spouse_telephone_no'] ?? '') ?>">
                                    </div>

                                    <hr style="border: 1px solid; width: 100%;"> <!-- Full-width black line -->

                                    <h5>Father's Information</h5>
                                    <div class="mb-3 col-md-6">
                                        <label for="father_first_name" class="form-label">Father's First Name</label>
                                        <input class="form-control" type="text" id="father_first_name"
                                            name="father_first_name" placeholder="Input your Father's First Name"
                                            autofocus
                                            value="<?= htmlspecialchars($family_data['father_first_name'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="father_name_extension" class="form-label">Name Extension (If
                                            applicable)</label>
                                        <input class="form-control" type="text" id="father_name_extension"
                                            name="father_name_extension" placeholder="JR/SR" autofocus
                                            value="<?= htmlspecialchars($family_data['father_name_extension'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="father_middle_name" class="form-label">Father's Middle Name</label>
                                        <input class="form-control" type="text" id="father_middle_name"
                                            name="father_middle_name" placeholder="Input your Father's Middle Name"
                                            autofocus
                                            value="<?= htmlspecialchars($family_data['father_middle_name'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="father_last_name" class="form-label">Father's Last Name</label>
                                        <input class="form-control" type="text" id="father_last_name"
                                            name="father_last_name" placeholder="Input your Father's Last Name"
                                            autofocus
                                            value="<?= htmlspecialchars($family_data['father_last_name'] ?? '') ?>">
                                    </div>

                                    <hr style="border: 1px solid; width: 100%;"> <!-- Full-width black line -->
                                    <h5>Mother's Information</h5>
                                    <div class="mb-3 col-md-6">
                                        <label for="mother_first_name" class="form-label">Mother's First Name</label>
                                        <input class="form-control" type="text" id="mother_first_name"
                                            name="mother_first_name" placeholder="Input your Mother's First Name"
                                            autofocus
                                            value="<?= htmlspecialchars($family_data['mother_first_name'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="mother_last_name" class="form-label">Mother's Last Name</label>
                                        <input class="form-control" type="text" id="mother_last_name"
                                            name="mother_last_name" placeholder="Input your Mother's Last Name"
                                            autofocus
                                            value="<?= htmlspecialchars($family_data['mother_last_name'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="mother_middle_name" class="form-label">Mother's Middle Name</label>
                                        <input class="form-control" type="text" id="mother_middle_name"
                                            name="mother_middle_name" placeholder="Input your Mother's Middle Name"
                                            autofocus
                                            value="<?= htmlspecialchars($family_data['mother_middle_name'] ?? '') ?>">
                                    </div>

                                    <hr style="border: 1px solid; width: 100%;"> <!-- Full-width black line -->

                                    <h5>Child's Information</h5>
                                    <div id="childrenContainer">
                                        <?php
                                        if (!isset($_SESSION['children'])) {
                                            $_SESSION['children'] = [];
                                            $_SESSION['birthdate'] = [];
                                        }

                                        // Load children from session if available
                                        $totalChildren = count($_SESSION['children']);
                                        if ($totalChildren === 0) {
                                            $totalChildren = 1; // Ensure at least one child input is displayed
                                        }

                                        for ($i = 0; $i < $totalChildren; $i++): ?>
                                            <div class="child-entry">
                                                <div class="mb-3 col-md-12">
                                                    <label for="child<?= $i + 1 ?>" class="form-label">Name of Child
                                                        <?= $i + 1 ?></label>
                                                    <input class="form-control" type="text" id="child<?= $i + 1 ?>FullName"
                                                        name="children[]"
                                                        placeholder="Input Child <?= $i + 1 ?>'s Full Name"
                                                        value="<?= isset($_SESSION['children'][$i]) ? htmlspecialchars($_SESSION['children'][$i], ENT_QUOTES) : ''; ?>" />
                                                </div>
                                                <div class="mb-3 col-md-12">
                                                    <label for="birthdate<?= $i + 1 ?>" class="form-label">Date of
                                                        Birth</label>
                                                    <input class="form-control" type="date" id="birthdate<?= $i + 1 ?>"
                                                        name="birthdate[]"
                                                        value="<?= isset($_SESSION['birthdate'][$i]) ? htmlspecialchars($_SESSION['birthdate'][$i], ENT_QUOTES) : ''; ?>" />
                                                </div>
                                            </div>
                                        <?php endfor; ?>
                                    </div>

                                    <button type="button" class="custom-outline-blue" onclick="addChild()"> + Add Child
                                    </button>

                                    <script>
                                        let childCount = <?= count($_SESSION['children']) ?: 1 ?>;
                                        const maxChildren = 10;

                                        function addChild() {
                                            if (childCount >= maxChildren) {
                                                alert("You can only add up to 10 children.");
                                                return;
                                            }

                                            childCount++;
                                            const container = document.getElementById("childrenContainer");

                                            const childDiv = document.createElement("div");
                                            childDiv.classList.add("child-entry");
                                            childDiv.innerHTML = `
            <div class="mb-3 col-md-12">
                <label for="child${childCount}FullName" class="form-label">Name of Child ${childCount}</label>
                <input class="form-control" type="text" id="child${childCount}FullName" name="children[]" placeholder="Input Child ${childCount}'s Full Name"/>
            </div>
            <div class="mb-3 col-md-12">
                <label for="birthdate${childCount}" class="form-label">Date of Birth</label>
                <input class="form-control" type="date" id="birthdate${childCount}" name="birthdate[]"/>
            </div>
        `;
                                            container.appendChild(childDiv);
                                        }
                                    </script>

                                    <!-- END Family Background -->

                                    <!-- Educational Background -->

                                    <!--ELEMENTARY-->
                                    <h4>III. Educational Background</h4>
                                    <div class="mb-3 col-md-6">
                                        <label for="elementarySchool" class="form-label">Elementary School</label>
                                        <input class="form-control" type="text" id="elementarySchool"
                                            name="elementarySchool" placeholder="Input your Elementary School" autofocus
                                            value="<?= htmlspecialchars($education_data['elementarySchool'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="basicEd" class="form-label">Basic Education</label>
                                        <input class="form-control" type="text" id="basicEd" name="basicEd"
                                            placeholder="Input your Basic Education" autofocus
                                            value="<?= htmlspecialchars($education_data['basicEd'] ?? '') ?>">

                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="From_1" class="form-label">From</label>
                                        <input class="form-control" type="text" id="From_1" name="From_1"
                                            placeholder="Input your Period of Attendance(From)" autofocus
                                            value="<?= htmlspecialchars($education_data['From_1'] ?? '') ?>">
                                    </div>


                                    <div class="mb-3 col-md-6">
                                        <label for="To_1" class="form-label">To</label>
                                        <input class="form-control" type="text" id="To_1" name="To_1"
                                            placeholder="Input your Period of Attendace(To)" autofocus
                                            value="<?= htmlspecialchars($education_data['To_1'] ?? '') ?>">
                                    </div>


                                    <div class="mb-3 col-md-6">
                                        <label for="elementaryYearGraduated" class="form-label">Year Graduated</label>
                                        <input class="form-control" type="text" id="elementaryYearGraduated"
                                            name="elementaryYearGraduated" placeholder="YYYY" autofocus
                                            value="<?= htmlspecialchars($education_data['elementaryYearGraduated'] ?? '') ?>">

                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="LevelUnitsEarned" class="form-label">Highest Level/Units Earned(If
                                            not Graduated)</label>
                                        <input class="form-control" type="text" id="LevelUnitsEarned"
                                            name="LevelUnitsEarned"
                                            placeholder="Input your Highest Level or Units Earned" autofocus
                                            value="<?= htmlspecialchars($education_data['LevelUnitsEarned'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="scholar_honors" class="form-label">Scholarship/Academic
                                            Honors</label>
                                        <input class="form-control" type="text" id="scholar_honors"
                                            name="scholar_honors" placeholder="Input your Scholarship/Academic Honors"
                                            autofocus
                                            value="<?= htmlspecialchars($education_data['scholar_honors'] ?? '') ?>">
                                    </div>


                                    <hr style="border: 1px solid; width: 100%;">
                                    <!--HIGH SCHOOL-->
                                    <div class="mb-3 col-md-6">
                                        <label for="highSchool" class="form-label">High School</label>
                                        <input class="form-control" type="text" id="highSchool" name="highSchool"
                                            placeholder="Input your High School" autofocus
                                            value="<?= htmlspecialchars($education_data['highSchool'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="basicEd2" class="form-label">Basic Education</label>
                                        <input class="form-control" type="text" id="basicEd2" name="basicEd2"
                                            placeholder="Input your Basic Education" autofocus
                                            value="<?= htmlspecialchars($education_data['basicEd2'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="From_2" class="form-label">From</label>
                                        <input class="form-control" type="text" id="From_2" name="From_2"
                                            placeholder="Input your Period of Attendance(From)" autofocus
                                            value="<?= htmlspecialchars($education_data['From_2'] ?? '') ?>">
                                    </div>


                                    <div class="mb-3 col-md-6">
                                        <label for="To_2" class="form-label">To</label>
                                        <input class="form-control" type="text" id="To_2" name="To_2"
                                            placeholder="Input your Period of Attendace(To)" autofocus
                                            value="<?= htmlspecialchars($education_data['To_2'] ?? '') ?>">
                                    </div>


                                    <div class="mb-3 col-md-6">
                                        <label for="highSchoolYearGraduated" class="form-label">Year Graduated</label>
                                        <input class="form-control" type="text" id="highSchoolYearGraduated"
                                            name="highSchoolYearGraduated" placeholder="YYYY" autofocus
                                            value="<?= htmlspecialchars($education_data['highSchoolYearGraduated'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="LevelUnitsEarned2" class="form-label">Highest Level/Units Earned(If
                                            not Graduated)</label>
                                        <input class="form-control" type="text" id="LevelUnitsEarned2"
                                            name="LevelUnitsEarned2"
                                            placeholder="Input your Highest Level or Units Earned" autofocus
                                            value="<?= htmlspecialchars($education_data['LevelUnitsEarned2'] ?? '') ?>">
                                    </div>


                                    <div class="mb-3 col-md-6">
                                        <label for="scholar_honors2" class="form-label">Scholarship/Academic
                                            Honors</label>
                                        <input class="form-control" type="text" id="scholar_honors2"
                                            name="scholar_honors2" placeholder="Input your Scholarship/Academic Honors"
                                            autofocus
                                            value="<?= htmlspecialchars($education_data['scholar_honors2'] ?? '') ?>">
                                    </div>


                                    <hr style="border: 1px solid; width: 100%;">
                                    <!--COLLEGE-->
                                    <div class="mb-3 col-md-6">
                                        <label for="college" class="form-label">College/University</label>
                                        <input class="form-control" type="text" id="college" name="college"
                                            placeholder="Input your College/University" autofocus
                                            value="<?= htmlspecialchars($education_data['college'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="collegeDegree" class="form-label">Degree</label>
                                        <input class="form-control" type="text" id="collegeDegree" name="collegeDegree"
                                            placeholder="Input your Degree" autofocus
                                            value="<?= htmlspecialchars($education_data['collegeDegree'] ?? '') ?>">

                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="From_3" class="form-label">From</label>
                                        <input class="form-control" type="text" id="From_3" name="From_3"
                                            placeholder="Input your Period of Attendance(From)" autofocus
                                            value="<?= htmlspecialchars($education_data['From_3'] ?? '') ?>">
                                    </div>


                                    <div class="mb-3 col-md-6">
                                        <label for="To_3" class="form-label">To</label>
                                        <input class="form-control" type="text" id="To_3" name="To_3"
                                            placeholder="Input your Period of Attendace(To)" autofocus
                                            value="<?= htmlspecialchars($education_data['To_3'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="collegeYearGraduated" class="form-label">Year Graduated</label>
                                        <input class="form-control" type="text" id="collegeYearGraduated"
                                            name="collegeYearGraduated" placeholder="YYYY" autofocus
                                            value="<?= htmlspecialchars($education_data['collegeYearGraduated'] ?? '') ?>">
                                    </div>


                                    <div class="mb-3 col-md-6">
                                        <label for="LevelUnitsEarned3" class="form-label">Highest Level/Units Earned(If
                                            not Graduated)</label>
                                        <input class="form-control" type="text" id="LevelUnitsEarned3"
                                            name="LevelUnitsEarned3"
                                            placeholder="Input your Highest Level or Units Earned" autofocus
                                            value="<?= htmlspecialchars($education_data['LevelUnitsEarned3'] ?? '') ?>">
                                    </div>


                                    <div class="mb-3 col-md-6">
                                        <label for="scholar_honors3" class="form-label">Scholarship/Academic
                                            Honors</label>
                                        <input class="form-control" type="text" id="scholar_honors3"
                                            name="scholar_honors3" placeholder="Input your Scholarship/Academic Honors"
                                            autofocus
                                            value="<?= htmlspecialchars($education_data['scholar_honors3'] ?? '') ?>">
                                    </div>

                                    <hr style="border: 1px solid; width: 100%;">

                                    <!--VOCATIONAL-->

                                    <div class="mb-3 col-md-6">
                                        <label for="vocTradeCourse" class="form-label">Vocational/Trade Course (If
                                            applicable)</label>
                                        <input class="form-control" type="text" id="vocTradeCourse"
                                            name="vocTradeCourse"
                                            placeholder="Input your Vocational/Trade Course School" autofocus
                                            value="<?= htmlspecialchars($education_data['vocTradeCourse'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="vocTradeDegree" class="form-label">Degree</label>
                                        <input class="form-control" type="text" id="vocTradeDegree"
                                            name="vocTradeDegree" placeholder="Input your Vocational/Trade Degree"
                                            autofocus
                                            value="<?= htmlspecialchars($education_data['vocTradeDegree'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="From_4" class="form-label">From</label>
                                        <input class="form-control" type="text" id="From_4" name="From_4"
                                            placeholder="Input your Period of Attendance(From)" autofocus
                                            value="<?= htmlspecialchars($education_data['From_4'] ?? '') ?>">
                                    </div>


                                    <div class="mb-3 col-md-6">
                                        <label for="To_4" class="form-label">To</label>
                                        <input class="form-control" type="text" id="To_4" name="To_4"
                                            placeholder="Input your Period of Attendace(To)" autofocus
                                            value="<?= htmlspecialchars($education_data['To_4'] ?? '') ?>">
                                    </div>


                                    <div class="mb-3 col-md-6">
                                        <label for="vocTradeGraduated" class="form-label">Year Graduated</label>
                                        <input class="form-control" type="text" id="vocTradeGraduated"
                                            name="vocTradeGraduated" placeholder="YYYY" autofocus
                                            value="<?= htmlspecialchars($education_data['vocTradeGraduated'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="LevelUnitsEarned4" class="form-label">Highest Level/Units Earned(If
                                            not Graduated)</label>
                                        <input class="form-control" type="text" id="LevelUnitsEarned4"
                                            name="LevelUnitsEarned4"
                                            placeholder="Input your Highest Level or Units Earned" autofocus
                                            value="<?= htmlspecialchars($education_data['LevelUnitsEarned4'] ?? '') ?>">
                                    </div>


                                    <div class="mb-3 col-md-6">
                                        <label for="scholar_honors4" class="form-label">Scholarship/Academic
                                            Honors</label>
                                        <input class="form-control" type="text" id="scholar_honors4"
                                            name="scholar_honors4" placeholder="Input your Scholarship/Academic Honors"
                                            autofocus
                                            value="<?= htmlspecialchars($education_data['scholar_honors4'] ?? '') ?>">
                                    </div>

                                    <hr style="border: 1px solid; width: 100%;">
                                    <!--GRADUATE SCHOOL-->
                                    <div class="mb-3 col-md-6">
                                        <label for="graduateSchool" class="form-label">Graduate School (If
                                            applicable)</label>
                                        <input class="form-control" type="text" id="graduateSchool"
                                            name="graduateSchool" placeholder="Input your Graduate School" autofocus
                                            value="<?= htmlspecialchars($education_data['graduateSchool'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="graduateDegree" class="form-label">Degree</label>
                                        <input class="form-control" type="text" id="graduateDegree"
                                            name="graduateDegree" placeholder="Input your Graduate Degree" autofocus
                                            value="<?= htmlspecialchars($education_data['graduateDegree'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="From_5" class="form-label">From</label>
                                        <input class="form-control" type="text" id="From_5" name="From_5"
                                            placeholder="Input your Period of Attendance(From)" autofocus
                                            value="<?= htmlspecialchars($education_data['From_5'] ?? '') ?>">
                                    </div>


                                    <div class="mb-3 col-md-6">
                                        <label for="To_5" class="form-label">To</label>
                                        <input class="form-control" type="text" id="To_5" name="To_5"
                                            placeholder="Input your Period of Attendace(To)" autofocus
                                            value="<?= htmlspecialchars($education_data['To_5'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="LevelUnitsEarned5" class="form-label">Highest Level/Units Earned(If
                                            not Graduated)</label>
                                        <input class="form-control" type="text" id="LevelUnitsEarned5"
                                            name="LevelUnitsEarned5"
                                            placeholder="Input your Highest Level or Units Earned" autofocus
                                            value="<?= htmlspecialchars($education_data['LevelUnitsEarned5'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="graduateYearGraduated" class="form-label">Year Graduated</label>
                                        <input class="form-control" type="text" id="graduateYearGraduated"
                                            name="graduateYearGraduated" placeholder="YYYY" autofocus
                                            value="<?= htmlspecialchars($education_data['graduateYearGraduated'] ?? '') ?>">
                                    </div>


                                    <div class="mb-3 col-md-6">
                                        <label for="scholar_honors5" class="form-label">Scholarship/Academic
                                            Honors</label>
                                        <input class="form-control" type="text" id="scholar_honors5"
                                            name="scholar_honors5" placeholder="Input your Scholarship/Academic Honors"
                                            autofocus
                                            value="<?= htmlspecialchars($education_data['scholar_honors5'] ?? '') ?>">
                                    </div>

                                    <!-- END Educational Background -->


                                    <!-- Civil Service Eligibility -->
                                    <div class="table-responsive mt-3">
                                        <h4>IV. Civil Service Eligibility</h4>
                                        <table class="table table-bordered" id="civilServiceTable">
                                            <thead>
                                                <tr>
                                                    <th>Eligibility Type</th>
                                                    <th>Rating</th>
                                                    <th>Exam Date</th>
                                                    <th>Place of Examination</th>
                                                    <th>License Number</th>
                                                    <th> License Number Validity</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($eligibility_data)): ?>
                                                    <?php foreach ($eligibility_data as $row): ?>
                                                        <tr>
                                                            <td>
                                                                <input type="text" class="form-control" name="eligibility[]"
                                                                    value="<?= htmlspecialchars($row['eligibility_type'] ?? '', ENT_QUOTES) ?>" />
                                                            </td>
                                                            <td>
                                                                <input type="text" class="form-control" name="rating[]"
                                                                    value="<?= htmlspecialchars($row['rating'] ?? '', ENT_QUOTES) ?>" />
                                                            </td>
                                                            <td>
                                                                <input type="date" class="form-control" name="examDate[]"
                                                                    value="<?= htmlspecialchars($row['exam_date'] ?? '', ENT_QUOTES) ?>" />
                                                            </td>
                                                            <td>
                                                                <input type="text" class="form-control" name="examPlace[]"
                                                                    value="<?= htmlspecialchars($row['exam_place'] ?? '', ENT_QUOTES) ?>" />
                                                            </td>
                                                            <td>
                                                                <input type="text" class="form-control" name="licenseNumber[]"
                                                                    value="<?= htmlspecialchars($row['license_number'] ?? '', ENT_QUOTES) ?>" />
                                                            </td>
                                                            <td>
                                                                <input type="date" class="form-control" name="validity[]"
                                                                    value="<?= htmlspecialchars($row['validity'] ?? '', ENT_QUOTES) ?>" />
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
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
                                                <?php else: ?>
                                                    <!-- If no data found, show at least one empty row -->
                                                    <tr>
                                                        <td><input type="text" class="form-control" name="eligibility[]"
                                                                placeholder="Input Eligibility Type" /></td>
                                                        <td><input type="text" class="form-control" name="rating[]"
                                                                placeholder="Rating" /></td>
                                                        <td><input type="date" class="form-control" name="examDate[]" />
                                                        </td>
                                                        <td><input type="text" class="form-control" name="examPlace[]"
                                                                placeholder="Exam Place" /></td>
                                                        <td><input type="text" class="form-control" name="licenseNumber[]"
                                                                placeholder="Number" /></td>
                                                        <td><input type="date" class="form-control" name="validity[]" />
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <button type="button" class="custom-outline-blue2 mt-3"
                                        onclick="addEligibilityRow()">+ Add Eligibility</button>

                                    <script>

                                        let EgibilityCount = 1;
                                        const EgibilityMax = 6;
                                        i
                                        function addEligibilityRow() {
                                            if (EgibilityCount >= EgibilityMax) {
                                                alert("You can only add up to 5 civil service egibility.");
                                                return;
                                            }
                                            EgibilityCount++;
                                            const table = document.getElementById("civilServiceTable").getElementsByTagName('tbody')[0];
                                            const newRow = table.insertRow();
                                            newRow.innerHTML = `
                        <td><input type="text" class="form-control" name="eligibility[]" placeholder="Input Eligibility Type"/></td>
                        <td><input type="text" class="form-control" name="rating[]" placeholder="Rating"/></td>
                        <td><input type="date" class="form-control" name="examDate[]"/></td>
                        <td><input type="text" class="form-control" name="examPlace[]" placeholder="Exam Place"/></td>
                        <td><input type="text" class="form-control" name="licenseNumber[]" placeholder="Number"/></td>
                        <td><input type="date" class="form-control" name="validity[]"/></td>
                        `;
                                        }
                                    </script>
                                    <!-- END Civil Service Eligibility -->


                                    <!-- Work Experience -->
                                    <div class="table-responsive mt-3">
                                        <h4 style="margin-top:20px;">V. Work Experience</h4>
                                        <table class="table table-bordered" id="workExperienceTable">
                                            <thead>
                                                <tr>
                                                    <th>Position Title</th>
                                                    <th>Department / Agency / Office / Company</th>
                                                    <th>From</th>
                                                    <th>To</th>
                                                    <th>Monthly Salary</th>
                                                    <th>Salary Grade</th>
                                                    <th>Status of Appointment</th>
                                                    <th>Govt Service (Y/N)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if ($work_result->num_rows > 0): ?>
                                                    <?php while ($row = $work_result->fetch_assoc()): ?>
                                                        <tr>
                                                            <td>
                                                                <input type="text" class="form-control" name="position_title[]"
                                                                    value="<?= htmlspecialchars($row['position_title'], ENT_QUOTES); ?>" />
                                                            </td>
                                                            <td>
                                                                <input type="text" class="form-control" name="we_department[]"
                                                                    value="<?= htmlspecialchars($row['we_department'], ENT_QUOTES); ?>" />
                                                            </td>
                                                            <td>
                                                                <input type="date" class="form-control" name="work_from_date[]"
                                                                    value="<?= htmlspecialchars($row['work_from_date'], ENT_QUOTES); ?>" />
                                                            </td>
                                                            <td>
                                                                <input type="date" class="form-control" name="work_to_date[]"
                                                                    value="<?= htmlspecialchars($row['work_to_date'], ENT_QUOTES); ?>" />
                                                            </td>
                                                            <td>
                                                                <input type="text" class="form-control" name="monthly_salary[]"
                                                                    value="<?= htmlspecialchars($row['monthly_salary'], ENT_QUOTES); ?>" />
                                                            </td>
                                                            <td>
                                                                <input type="text" class="form-control" name="salary_grade[]"
                                                                    value="<?= htmlspecialchars($row['salary_grade'], ENT_QUOTES); ?>" />
                                                            </td>
                                                            <td>
                                                                <input type="text" class="form-control"
                                                                    name="appointment_status[]"
                                                                    value="<?= htmlspecialchars($row['appointment_status'], ENT_QUOTES); ?>" />
                                                            </td>
                                                            <td>
                                                                <select class="form-control" name="govt_service[]">
                                                                    <option value="YES" <?= ($row['govt_service'] == "YES") ? 'selected' : ''; ?>>Yes</option>
                                                                    <option value="NO" <?= ($row['govt_service'] == "NO") ? 'selected' : ''; ?>>No</option>
                                                                </select>
                                                            </td>
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
                                                        </tr>
                                                    <?php endwhile; ?>
                                                <?php else: ?>
                                                    <!-- If no data found, show at least one empty row -->
                                                    <tr>
                                                        <td><input type="text" class="form-control" name="position_title[]"
                                                                placeholder="Position Title" /></td>
                                                        <td><input type="text" class="form-control" name="we_department[]"
                                                                placeholder="Department / Company" /></td>
                                                        <td><input type="date" class="form-control"
                                                                name="work_from_date[]" /></td>
                                                        <td><input type="date" class="form-control" name="work_to_date[]" />
                                                        </td>
                                                        <td><input type="text" class="form-control" name="monthly_salary[]"
                                                                placeholder="Monthly Salary" /></td>
                                                        <td><input type="text" class="form-control" name="salary_grade[]"
                                                                placeholder="Salary Grade" /></td>
                                                        <td><input type="text" class="form-control"
                                                                name="appointment_status[]"
                                                                placeholder="Status of Appointment" /></td>
                                                        <td>
                                                            <select class="form-control" name="govt_service[]">
                                                                <option value="YES">Yes</option>
                                                                <option value="NO">No</option>
                                                            </select>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <button type="button" class="custom-outline-blue3 mt-3"
                                        onclick="addWorkExperienceRow()">+ Add Work Experience</button>

                                    <script>

                                        let WorkExperienceCount = 1;
                                        const WorkExperienceMax = 6;

                                        function addWorkExperienceRow() {
                                            if (WorkExperienceCount >= WorkExperienceMax) {
                                                alert("You can only add up to 5 work experiences.");
                                                return;
                                            }
                                            WorkExperienceCount++;

                                            const table = document.getElementById("workExperienceTable").getElementsByTagName('tbody')[0];
                                            const newRow = table.insertRow();
                                            newRow.innerHTML = `
        <td><input type="text" class="form-control" name="position_title[]" placeholder="Position Title"/></td>
        <td><input type="text" class="form-control" name="we_department[]" placeholder="Department/Agency/Company"/></td>
        <td><input type="date" class="form-control" name="work_from_date[]"/></td>
        <td><input type="date" class="form-control" name="work_to_date[]"/></td>
        <td><input type="text" class="form-control" name="monthly_salary[]" placeholder="Monthly Salary"/></td>
        <td><input type="text" class="form-control" name="salary_grade[]" placeholder="Salary Grade"/></td>
        <td><input type="text" class="form-control" name="appointment_status[]" placeholder="Appointment Status"/></td>
        <td>
            <select class="form-control" name="govt_service[]">
                <option value="YES">Yes</option>
                <option value="NO">No</option>
            </select>
        </td>
    `;
                                        }

                                    </script>


                                    <!-- Voluntary Work -->
                                    <div class="table-responsive mt-3">
                                        <h4 style="margin-top:20px;">VI. Voluntary Work Or Involvement In Civic /
                                            Non-Government / People / Voluntary Organizations</h4>
                                        <table class="table table-bordered" id="voluntaryWorkTable">
                                            <thead>
                                                <tr>
                                                    <th>Name & Address of Organization</th>
                                                    <th>Inclusive Dates (From)</th>
                                                    <th>Inclusive Dates (To)</th>
                                                    <th>Number of Hours</th>
                                                    <th>Position / Nature of Work</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if ($result_voluntary->num_rows > 0): ?>
                                                    <?php while ($row = $result_voluntary->fetch_assoc()): ?>
                                                        <tr>
                                                            <td>
                                                                <input type="text" class="form-control" name="organization[]"
                                                                    value="<?= htmlspecialchars($row['organization'] ?? '', ENT_QUOTES) ?>" />

                                                            </td>
                                                            <td>
                                                                <input type="date" class="form-control"
                                                                    name="voluntary_from_date[]"
                                                                    value="<?= htmlspecialchars($row['voluntary_from_date'] ?? '', ENT_QUOTES); ?>" />
                                                            </td>
                                                            <td>
                                                                <input type="date" class="form-control"
                                                                    name="voluntary_to_date[]"
                                                                    value="<?= htmlspecialchars($row['voluntary_to_date'] ?? '', ENT_QUOTES); ?>" />
                                                            </td>
                                                            <td>
                                                                <input type="number" class="form-control"
                                                                    name="voluntary_hours[]"
                                                                    value="<?= htmlspecialchars($row['voluntary_hours'] ?? '', ENT_QUOTES); ?>" />
                                                            </td>
                                                            <td>
                                                                <input type="text" class="form-control"
                                                                    name="voluntary_position[]"
                                                                    value="<?= htmlspecialchars($row['voluntary_position'] ?? '', ENT_QUOTES); ?>" />
                                                            </td>
                                                        </tr>
                                                    <?php endwhile; ?>
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
                                                <?php else: ?>
                                                    <td><input type="text" class="form-control" name="organization[]"
                                                            placeholder="Organization Name & Address" /></td>
                                                    <td><input type="date" class="form-control"
                                                            name="voluntary_from_date[]" /></td>
                                                    <td><input type="date" class="form-control"
                                                            name="voluntary_to_date[]" /></td>
                                                    <td><input type="number" class="form-control" name="voluntary_hours[]"
                                                            placeholder="Hours" /></td>
                                                    <td><input type="text" class="form-control" name="voluntary_position[]"
                                                            placeholder="Position / Nature of Work" /></td>
                                                    `;
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <button type="button" class="custom-outline-blue4 mt-3"
                                        onclick="addVoluntaryWorkRow()">+ Add Voluntary Work</button>
                                </div>

                                <script>
                                    let voluntaryWorkCount = 1;
                                    const maxVoluntaryWork = 5;

                                    function addVoluntaryWorkRow() {
                                        if (voluntaryWorkCount >= maxVoluntaryWork) {
                                            alert("You can only add up to 5 voluntary work entries.");
                                            return;
                                        }
                                        voluntaryWorkCount++;

                                        const table = document.getElementById("voluntaryWorkTable").getElementsByTagName('tbody')[0];
                                        const newRow = table.insertRow();
                                        newRow.innerHTML = `
          <td><input type="text" class="form-control" name="organization[]" placeholder="Organization Name & Address"/></td>
          <td><input type="date" class="form-control" name="voluntary_from_date[]"/></td>
          <td><input type="date" class="form-control" name="voluntary_to_date[]" /></td>
          <td><input type="number" class="form-control" name="voluntary_hours[]" placeholder="Hours"/></td>
          <td><input type="text" class="form-control" name="voluntary_position[]" placeholder="Position / Nature of Work"/></td>
      `;
                                    }
                                </script>

                                <!-- END Voluntary Work -->

                                <!-- Learning Development -->
                                <div class="table-responsive mt-3">
                                    <h4 style="margin-top:20px;">VII. Learning and Development (L&D)
                                        Interventions/Training Programs Attended</h4>
                                    <div class="mb-3 col-md-12">
                                        <table class="table table-bordered" id="learningDevelopmentTable">
                                            <thead>
                                                <tr>
                                                    <th>Title of Learning and Development Interventions/Training
                                                        Programs</th>
                                                    <th>Inclusive Dates (From)</th>
                                                    <th>Inclusive Dates (To)</th>
                                                    <th>Number of Hours</th>
                                                    <th>Type of LD (Managerial/Supervisory/Technical etc.)</th>
                                                    <th>Conducted / Sponsored By</th>
                                                </tr>
                                            </thead>

                                            <tbody>
                                                <?php if (!empty($learning_development_data)): ?>
                                                    <?php foreach ($learning_development_data as $ld): ?>
                                                        <?php
                                                        // Ensure the dates are formatted as YYYY-MM-DD
                                                        $ld['from_date'] = date('Y-m-d', strtotime($ld['from_date']));
                                                        $ld['to_date'] = date('Y-m-d', strtotime($ld['to_date']));
                                                        ?>
                                                        <tr>
                                                            <td><input type="text" class="form-control" name="training_title[]"
                                                                    value="<?= htmlspecialchars($ld['training_title'], ENT_QUOTES) ?>" />
                                                            </td>
                                                            <td><input type="date" class="form-control" name="from_date[]"
                                                                    value="<?= htmlspecialchars($ld['from_date'], ENT_QUOTES) ?>" />
                                                            </td>
                                                            <td><input type="date" class="form-control" name="to_date[]"
                                                                    value="<?= htmlspecialchars($ld['to_date'], ENT_QUOTES) ?>" />
                                                            </td>
                                                            <td><input type="text" class="form-control" name="hours[]"
                                                                    value="<?= htmlspecialchars($ld['hours'], ENT_QUOTES) ?>" />
                                                            </td>
                                                            <td><input type="text" class="form-control" name="type_of_ld[]"
                                                                    value="<?= htmlspecialchars($ld['type_of_ld'], ENT_QUOTES) ?>" />
                                                            </td>
                                                            <td><input type="text" class="form-control" name="sponsor[]"
                                                                    value="<?= htmlspecialchars($ld['sponsor'], ENT_QUOTES) ?>" />
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
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
                                                <?php else: ?>
                                                    <!-- If no data found, show an empty input row -->
                                                    <tr>
                                                        <td><input type="text" class="form-control" name="training_title[]"
                                                                placeholder="Enter Training Title" /></td>
                                                        <td><input type="date" class="form-control" name="from_date[]" />
                                                        </td>
                                                        <td><input type="date" class="form-control" name="to_date[]" /></td>
                                                        <td><input type="text" class="form-control" name="hours[]"
                                                                placeholder="Hours" /></td>
                                                        <td><input type="text" class="form-control" name="type_of_ld[]"
                                                                placeholder="Type of LD" /></td>
                                                        <td><input type="text" class="form-control" name="sponsor[]"
                                                                placeholder="Conducted By" /></td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <button type="button" class="custom-outline-blue7 mt-3"
                                    onclick="addLearningDevelopmentRow()">+ Add Training Program</button>

                                <script>
                                    let learningDevelopmentCount = 1;
                                    const maxlearningDevelopment = 5;

                                    function addLearningDevelopmentRow() {
                                        if (learningDevelopmentCount >= maxlearningDevelopment) {
                                            alert("You can only add up to 5 learning development entries.");
                                            return;
                                        }
                                        learningDevelopmentCount++;
                                        const table = document.getElementById("learningDevelopmentTable").getElementsByTagName('tbody')[0];
                                        const newRow = table.insertRow();
                                        newRow.innerHTML = `
      <td><input type="text" class="form-control" name="training_title[]" placeholder="Enter Training Title"/></td>
      <td><input type="date" class="form-control" name="from_date[]"/></td>
      <td><input type="date" class="form-control" name="to_date[]"/></td>
      <td><input type="number" class="form-control" name="hours[]" placeholder="Hours"/></td>
      <td><input type="text" class="form-control" name="type_of_ld[]" placeholder="Managerial / Supervisory / Technical"/></td>
      <td><input type="text" class="form-control" name="sponsor[]" placeholder="Organization Name"/></td>
    `;
                                    }
                                </script>
                                <!-- END Learning Development -->

                                <!-- Other Information Table-->
                                <div class="table-responsive mt-3">
                                    <h4>VIII. Other Information</h4>
                                    <div class="mb-3 col-md-12">
                                        <table class="table table-bordered" id="OtherInfoCountTable">
                                            <thead>
                                                <tr>
                                                    <th>Special Skills and Hobbies</th>
                                                    <th>Non-Academic Distinctions / Hobbies</th>
                                                    <th>Membership in Association / Organization</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                if (!isset($_SESSION['skillsTitle'])) {
                                                    $_SESSION['nonAcad'] = [];
                                                    $_SESSION['membership'] = [];
                                                }
                                                $totalSkilltitle = count($_SESSION['skillsTitle'] ?? []);
                                                if ($totalSkilltitle === 0) {
                                                    $totalSkilltitle = 1; // Ensure at least one row is displayed
                                                }

                                                for ($i = 0; $i < $totalSkilltitle; $i++): ?>
                                                    <tr>
                                                        <td>
                                                            <input type="text" class="form-control" name="skillsTitle[]"
                                                                placeholder="Enter Special Skills and Hobbies"
                                                                value="<?= isset($_SESSION['skillsTitle'][$i]) ? htmlspecialchars($_SESSION['skillsTitle'][$i], ENT_QUOTES) : ''; ?>" />
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control" name="nonAcad[]"
                                                                placeholder="Non-Academic Distinctions / Hobbies"
                                                                value="<?= isset($_SESSION['nonAcad'][$i]) ? htmlspecialchars($_SESSION['nonAcad'][$i], ENT_QUOTES) : ''; ?>" />
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control" name="membership[]"
                                                                placeholder="Membership in Association / Organization"
                                                                value="<?= isset($_SESSION['membership'][$i]) ? htmlspecialchars($_SESSION['membership'][$i], ENT_QUOTES) : ''; ?>" />
                                                        </td>
                                                    </tr>
                                                <?php endfor; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <button type="button" class="custom-outline-blue6 mt-3"
                                        onclick="addOtherInfoCountRow()">+ Add Skills</button>
                                </div>

                                <script>
                                    let OtherInfoCount = 1;
                                    const Other = 5;

                                    function addOtherInfoCountRow() {
                                        if (OtherInfoCount >= Other) {
                                            alert("You can only add up to 5 skills.");
                                            return;
                                        }
                                        OtherInfoCount++;
                                        const table = document.getElementById("OtherInfoCountTable").getElementsByTagName('tbody')[0];
                                        const newRow = table.insertRow();
                                        newRow.innerHTML = `
      <td><input type="text" class="form-control" name="skillsTitle[]" placeholder="Enter Special Skills and Hobbies"/></td>
      <td><input type="text" class="form-control" name="nonAcad[]" placeholder="Non-Academic Distinctions / Hobbies"/></td>
      <td><input type="text" class="form-control" name="membership[]" placeholder="Membership in Association / Organization"/></td>
    `;
                                    }
                                </script>
                                <!-- END Other Information Table-->

                                <!-- Questions -->
                                <div class="mb-3 mt-3">
                                    <label class="form-label" for="q1">
                                        Are you related by consanguinity or affinity to the appointing or recommending
                                        authority,
                                        or to the chief of bureau or office, or to the person who has immediate
                                        supervision over you
                                        in the Office, Bureau, or Department where you will be appointed?
                                    </label>
                                    <p>A. Within the third degree?</p>

                                    <select class="form-select" id="q1" name="q1" onchange="toggleDetails1()">
                                        <option value="No" <?= ($q1 == 'No') ? 'selected' : ''; ?>>No</option>
                                        <option value="Yes" <?= ($q1 == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <p>B. Within the fourth degree (for Local Government Unit - Career Employees)?</p>
                                    <select class="form-select" id="q2" name="q2" onchange="toggleDetails2()">
                                        <option value="No" <?= ($q2 == 'No') ? 'selected' : ''; ?>>No</option>
                                        <option value="Yes" <?= ($q2 == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                    </select>
                                </div>

                                <!-- Details input field -->
                                <div class="mb-3" id="Details_2" style="display: none;">
                                    <input type="text" id="details2" name="details2" class="form-control mt-2"
                                        placeholder="Provide details" value="<?= htmlspecialchars($details2) ?>" />
                                </div>

                                <script>
                                    function toggleDetails2() {
                                        var q2 = document.getElementById("q2").value;
                                        var Details_2 = document.getElementById("Details_2");

                                        // Show the text field only if "Yes" is selected
                                        Details_2.style.display = (q2 === "Yes") ? "block" : "none";
                                    }

                                    // Run function on page load to reflect database data
                                    document.addEventListener("DOMContentLoaded", function () {
                                        toggleDetails2();
                                    });
                                </script>

                                <div class="mb-3">
                                    <label class="form-label">Have you ever been found guilty of any administrative
                                        offense?</label>
                                    <select class="form-select" id="q3" name="q3" onchange="toggleDetails3()">
                                        <option value="No" <?= ($q3 == 'No') ? 'selected' : ''; ?>>No</option>
                                        <option value="Yes" <?= ($q3 == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                    </select>
                                </div>

                                <!-- Details input field -->
                                <div class="mb-3" id="Details_3" style="display: none;">
                                    <input type="text" id="details3" name="details3" class="form-control mt-2"
                                        placeholder="Provide details" value="<?= htmlspecialchars($details3) ?>" />
                                </div>

                                <script>
                                    function toggleDetails3() {
                                        var q3 = document.getElementById("q3").value;
                                        var Details_3 = document.getElementById("Details_3");

                                        // Show the text field only if "Yes" is selected
                                        Details_3.style.display = (q3 === "Yes") ? "block" : "none";
                                    }

                                    // Run function on page load to reflect database data
                                    document.addEventListener("DOMContentLoaded", function () {
                                        toggleDetails3();
                                    });
                                </script>

                                <div class="mb-3">
                                    <label class="form-label">Have you been criminally charged before any court?</label>
                                    <select class="form-select" id="q4" name="q4" onchange="toggleDetails4()">
                                        <option value="No" <?= ($q4 == 'No') ? 'selected' : ''; ?>>No</option>
                                        <option value="Yes" <?= ($q4 == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                    </select>
                                </div>

                                <!-- Details input field -->
                                <div class="mb-3" id="Details_4" style="display: none;">
                                    <input type="date" id="details4" name="details4" class="form-control mt-2"
                                        placeholder="Date Filed" value="<?= htmlspecialchars($details4) ?>" />
                                    <input type="text" id="details41" name="details41" class="form-control mt-2"
                                        placeholder="Status of Case/s" value="<?= htmlspecialchars($details41) ?>" />
                                </div>

                                <script>
                                    function toggleDetails4() {
                                        var q4 = document.getElementById("q4").value;
                                        var Details_4 = document.getElementById("Details_4");

                                        // Show the text field only if "Yes" is selected
                                        Details_4.style.display = (q4 === "Yes") ? "block" : "none";
                                    }

                                    // Run function on page load to reflect database data
                                    document.addEventListener("DOMContentLoaded", function () {
                                        toggleDetails4();
                                    });
                                </script>
                                <div class="mb-3">
                                    <label class="form-label">Have you ever been convicted of any crime or violation of
                                        any law, decree, ordinance or regulation by any court or tribunal?</label>
                                    <select class="form-select" id="q5" name="q5" onchange="toggleDetails5()">
                                        <option value="No" <?= ($q5 == 'No') ? 'selected' : ''; ?>>No</option>
                                        <option value="Yes" <?= ($q5 == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                    </select>
                                </div>

                                <!-- Details input field -->
                                <div class="mb-3" id="Details_5" style="display: none;">
                                    <input type="text" id="details5" name="details5" class="form-control mt-2"
                                        placeholder="Provide details" value="<?= htmlspecialchars($details5) ?>" />
                                </div>

                                <script>
                                    function toggleDetails5() {
                                        var q5 = document.getElementById("q5").value;
                                        var Details_5 = document.getElementById("Details_5");

                                        // Show the text field only if "Yes" is selected
                                        Details_5.style.display = (q5 === "Yes") ? "block" : "none";
                                    }

                                    // Run function on page load to reflect database data
                                    document.addEventListener("DOMContentLoaded", function () {
                                        toggleDetails5();
                                    });
                                </script>

                                <div class="mb-3">
                                    <label class="form-label">Have you ever been seperated from the service in any of
                                        the following modes: resignation, retirement, dropped from the rolls,
                                        dismissal, termination, end of term, finished contract or phased out (abolition)
                                        in the public or private sector?</label>
                                    <select class="form-select" id="q6" name="q6" onchange="toggleDetails6()">
                                        <option value="No" <?= ($q6 == 'No') ? 'selected' : ''; ?>>No</option>
                                        <option value="Yes" <?= ($q6 == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                    </select>
                                </div>

                                <!-- Details input field -->
                                <div class="mb-3" id="Details_6" style="display: none;">
                                    <input type="text" id="details6" name="details6" class="form-control mt-2"
                                        placeholder="Provide details" value="<?= htmlspecialchars($details6) ?>" />
                                </div>

                                <script>
                                    function toggleDetails6() {
                                        var q6 = document.getElementById("q6").value;
                                        var Details_6 = document.getElementById("Details_6");

                                        // Show the text field only if "Yes" is selected
                                        Details_6.style.display = (q6 === "Yes") ? "block" : "none";
                                    }

                                    // Run function on page load to reflect database data
                                    document.addEventListener("DOMContentLoaded", function () {
                                        toggleDetails6();
                                    });
                                </script>

                                <div class="mb-3">
                                    <label class="form-label">Have you ever been a candidate in a national or local
                                        election held within last year (except Barangay Election)?</label>
                                    <select class="form-select" id="q7" name="q7" onchange="toggleDetails7()">
                                        <option value="No" <?= ($q7 == 'No') ? 'selected' : ''; ?>>No</option>
                                        <option value="Yes" <?= ($q7 == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                    </select>
                                </div>

                                <!-- Details input field -->
                                <div class="mb-3" id="Details_7" style="display: none;">
                                    <input type="text" id="details7" name="details7" class="form-control mt-2"
                                        placeholder="Provide details" value="<?= htmlspecialchars($details7) ?>" />
                                </div>

                                <script>
                                    function toggleDetails7() {
                                        var q7 = document.getElementById("q7").value;
                                        var Details_7 = document.getElementById("Details_7");

                                        // Show the text field only if "Yes" is selected
                                        Details_7.style.display = (q7 === "Yes") ? "block" : "none";
                                    }

                                    // Run function on page load to reflect database data
                                    document.addEventListener("DOMContentLoaded", function () {
                                        toggleDetails7();
                                    });
                                </script>

                                <div class="mb-3">
                                    <label class="form-label">Have you resigned from the government service during the
                                        three (3)-month period before the last election
                                        to promote/actively campaign for a national or local candidate?</label>
                                    <select class="form-select" id="q8" name="q8" onchange="toggleDetails8()">
                                        <option value="No" <?= ($q8 == 'No') ? 'selected' : ''; ?>>No</option>
                                        <option value="Yes" <?= ($q8 == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                    </select>
                                </div>

                                <!-- Details input field -->
                                <div class="mb-3" id="Details_8" style="display: none;">
                                    <input type="text" id="details8" name="details8" class="form-control mt-2"
                                        placeholder="Provide details" vvalue="<?= htmlspecialchars($details8) ?>" />
                                </div>

                                <script>
                                    function toggleDetails8() {
                                        var q8 = document.getElementById("q8").value;
                                        var Details_8 = document.getElementById("Details_8");

                                        // Show the text field only if "Yes" is selected
                                        Details_8.style.display = (q8 === "Yes") ? "block" : "none";
                                    }

                                    // Run function on page load to reflect database data
                                    document.addEventListener("DOMContentLoaded", function () {
                                        toggleDetails8();
                                    });
                                </script>

                                <div class="mb-3">
                                    <label class="form-label">Have you acquired the status of an immigrant or permanent
                                        resident of another country?</label>
                                    <select class="form-select" id="q9" name="q9" onchange="toggleDetails9()">
                                        <option value="No" <?= ($q9 == 'No') ? 'selected' : ''; ?>>No</option>
                                        <option value="Yes" <?= ($q9 == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                    </select>
                                </div>

                                <!-- Details input field -->
                                <div class="mb-3" id="Details_9" style="display: none;">
                                    <input type="text" id="details9" name="details9" class="form-control mt-2"
                                        placeholder="Provide details" value="<?= htmlspecialchars($details9) ?>" />
                                </div>

                                <script>
                                    function toggleDetails9() {
                                        var q9 = document.getElementById("q9").value;
                                        var Details_9 = document.getElementById("Details_9");

                                        // Show the text field only if "Yes" is selected
                                        Details_9.style.display = (q9 === "Yes") ? "block" : "none";
                                    }

                                    // Run function on page load to reflect database data
                                    document.addEventListener("DOMContentLoaded", function () {
                                        toggleDetails9();
                                    });
                                </script>
                                <div class="mb-3">
                                    <label class="form-label">Pursuant to: (a) Indigenous People's Act (RA 8371); (b)
                                        Magna Carta for Disabled Persons (RA 7277); and
                                        (c) Solo Parents Welfare Act of 2000 (RA 8972), please answer the following
                                        items:'</label>
                                    <p>A. Are you a member of any indigenous group?</p>
                                    <select class="form-select" id="q10" name="q10" onchange="toggleDetails10()">
                                        <option value="No" <?= ($q10 == 'No') ? 'selected' : ''; ?>>No</option>
                                        <option value="Yes" <?= ($q10 == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                    </select>
                                </div>

                                <!-- Details input field -->
                                <div class="mb-3" id="Details_10" style="display: none;">
                                    <input type="text" id="details10" name="details10" class="form-control mt-2"
                                        placeholder="Provide details" value="<?= htmlspecialchars($details10) ?>" />
                                </div>

                                <script>
                                    function toggleDetails10() {
                                        var q10 = document.getElementById("q10").value;
                                        var Details_10 = document.getElementById("Details_10");

                                        // Show the text field only if "Yes" is selected
                                        Details_10.style.display = (q10 === "Yes") ? "block" : "none";
                                    }

                                    // Run function on page load to reflect database data
                                    document.addEventListener("DOMContentLoaded", function () {
                                        toggleDetails10();
                                    });
                                </script>
                                <div class="mb-3">
                                    <p>B. Are you a person with disability?</p>
                                    <select class="form-select" id="q11" name="q11" onchange="toggleDetails11()">
                                        <option value="No" <?= ($q11 == 'No') ? 'selected' : ''; ?>>No</option>
                                        <option value="Yes" <?= ($q11 == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                    </select>
                                </div>

                                <!-- Details input field -->
                                <div class="mb-3" id="Details_11" style="display: none;">
                                    <input type="text" id="details11" name="details11" class="form-control mt-2"
                                        placeholder="Provide details" value="<?= htmlspecialchars($details11) ?>" />
                                </div>

                                <script>
                                    function toggleDetails11() {
                                        var q11 = document.getElementById("q11").value;
                                        var Details_11 = document.getElementById("Details_11");

                                        // Show the text field only if "Yes" is selected
                                        Details_11.style.display = (q11 === "Yes") ? "block" : "none";
                                    }

                                    // Run function on page load to reflect database data
                                    document.addEventListener("DOMContentLoaded", function () {
                                        toggleDetails11();
                                    });
                                </script>

                                <div class="mb-3">
                                    <p>C. Are you a solo parent?</p>
                                    <select class="form-select" id="q12" name="q12" onchange="toggleDetails12()">
                                        <option value="No" <?= ($q12 == 'No') ? 'selected' : ''; ?>>No</option>
                                        <option value="Yes" <?= ($q12 == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                    </select>
                                </div>

                                <!-- Details input field -->
                                <div class="mb-3" id="Details_12" style="display: none;">
                                    <input type="text" id="details12" name="details12" class="form-control mt-2"
                                        placeholder="Provide details" value="<?= htmlspecialchars($details12) ?>" />
                                </div>

                                <script>
                                    function toggleDetails12() {
                                        var q12 = document.getElementById("q12").value;
                                        var Details_12 = document.getElementById("Details_12");

                                        // Show the text field only if "Yes" is selected
                                        Details_12.style.display = (q12 === "Yes") ? "block" : "none";
                                    }

                                    // Run function on page load to reflect database data
                                    document.addEventListener("DOMContentLoaded", function () {
                                        toggleDetails12();
                                    });
                                </script>


                                <!-- References Section -->
                                <h5 class="mt-4">References (Provide at least three references)</h5>
                                <div id="referenceContainer">
                                    <?php if (!empty($reference_data)): ?>
                                        <?php foreach ($reference_data as $index => $ref): ?>
                                            <div class="mb-3 row reference-item">
                                                <div class="col-md-4">
                                                    <label class="form-label">Name</label>
                                                    <input type="text" class="form-control" name="refName1[]"
                                                        value="<?= htmlspecialchars($ref['refName1'] ?? '') ?>"
                                                        placeholder="Enter Name">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Address</label>
                                                    <input type="text" class="form-control" name="refAddress1[]"
                                                        value="<?= htmlspecialchars($ref['refAddress1'] ?? '') ?>"
                                                        placeholder="Enter Address">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Phone Number</label>
                                                    <input type="text" class="form-control" name="refPhone1[]"
                                                        value="<?= htmlspecialchars($ref['refPhone1'] ?? '') ?>"
                                                        placeholder="Enter Phone Number">
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <!-- Show at least one empty row if no data exists -->
                                        <div class="mb-3 row reference-item">
                                            <div class="col-md-4">
                                                <label class="form-label">Name</label>
                                                <input type="text" class="form-control" name="refName1[]"
                                                    placeholder="Enter Name">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Address</label>
                                                <input type="text" class="form-control" name="refAddress1[]"
                                                    placeholder="Enter Address">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Phone Number</label>
                                                <input type="text" class="form-control" name="refPhone1[]"
                                                    placeholder="Enter Phone Number">
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <button type="button" class="custom-outline-blue" onclick="addReference()">+ Add
                                    Reference</button>


                                <script>
                                    // Function to dynamically add reference fields
                                    let refCount = 1;
                                    const refC = 5;
                                    function addReference() {
                                        if (refCount >= refC) {
                                            alert("You can only add up to 5 references.");
                                            return;
                                        }
                                        refCount++;
                                        const container = document.getElementById("referenceContainer");
                                        const newRow = document.createElement("div");
                                        newRow.classList.add("mb-3", "row");
                                        newRow.innerHTML = `
                <div class="col-md-4">
                    <label class="form-label">Name</label>
                    <input type="text" class="form-control" name="refName1[]" placeholder="Enter Name">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Address</label>
                    <input type="text" class="form-control" name="refAddress1[]" placeholder="Enter Address">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Phone Number</label>
                    <input type="text" class="form-control" name="refPhone1[]" placeholder="Enter Phone Number">
                </div>
            `;
                                        container.appendChild(newRow);
                                    }
                                </script>

                                <!-- END Other Information -->

                        </div>

                        <div class="mt-2">
                            <button type="button" class="btn btn-primary me-2" name="save" id="save"
                                style="background-color: #007bff; border-color: #007bff; position:relative; top:-20px; left:10px;">Save</button>


                            <!-- <button type="submit" class="btn btn-outline-secondary" name="download_excel"  DISABLED TEMPORARY WHILE REVISING PDS
                                id="download_excel"
                                style="background-color: #3CB371; border-color: #3CB371; color: white; position:relative; left:10px; top: -20px;">Download
                                as Excel</button> -->
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
            e.preventDefault(); // Prevent the form from submitting immediately

            Swal.fire({
                title: 'Save Personal Data Sheet',
                text: 'Are you sure you want to save?',
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
                        title: 'Saved!',
                        text: 'Your personal data sheet has been saved!',
                        confirmButtonColor: '#007bff'
                    }).then(() => {
                        document.getElementById('dataForm').submit();
                    });
                }
            });
        });
    </script>
</body>

</html>