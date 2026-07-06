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

    <!-- Icons -->
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        :root {
            --pds-primary: #007bff;
            --pds-border: #e4e6ef;
            --pds-muted: #6c757d;
        }

        .pds-section {
            border: 1px solid var(--pds-border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.75rem;
            background: #fff;
        }

        .pds-section-header {
            display: flex;
            align-items: center;
            gap: .6rem;
            margin-bottom: 1.25rem;
            padding-bottom: .75rem;
            border-bottom: 2px solid var(--pds-border);
            font-weight: 600;
            font-size: 1.1rem;
        }

        .pds-section-header i {
            color: var(--pds-primary);
            font-size: 1.2rem;
        }

        .pds-subheader {
            font-weight: 600;
            font-size: .95rem;
            color: var(--pds-primary);
            margin: 1.25rem 0 .75rem;
            padding-top: 1rem;
            border-top: 1px dashed var(--pds-border);
        }

        .pds-subheader:first-of-type {
            border-top: none;
            padding-top: 0;
            margin-top: 0;
        }

        .pds-question {
            border: 1px solid var(--pds-border);
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
            background: #fbfbfd;
        }

        .pds-question label.form-label {
            font-weight: 500;
        }

        .pds-question p {
            margin-bottom: .5rem;
            font-weight: 500;
        }

        table.table thead th {
            background: #f5f6fa;
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: var(--pds-muted);
            white-space: nowrap;
            vertical-align: middle;
        }

        table.table td {
            vertical-align: middle;
        }

        table.table td .form-control,
        table.table td .select2 {
            min-width: 130px;
        }

        .custom-outline-blue {
            border: 1.5px solid var(--pds-primary);
            color: var(--pds-primary);
            background: transparent;
            border-radius: 8px;
            padding: .45rem 1rem;
            font-weight: 500;
            font-size: .9rem;
            transition: all .15s ease-in-out;
        }

        .custom-outline-blue:hover {
            background: var(--pds-primary);
            color: #fff;
        }

        .pds-action-bar {
            position: sticky;
            bottom: 0;
            background: #fff;
            border-top: 1px solid var(--pds-border);
            padding: 1rem 1.5rem;
            margin-top: 1.5rem;
            display: flex;
            justify-content: flex-end;
            gap: .75rem;
            border-radius: 0 0 12px 12px;
        }

        .pds-action-bar .btn {
            min-width: 160px;
            font-weight: 600;
        }

        @media (max-width: 576px) {
            .pds-action-bar {
                flex-direction: column;
            }

            .pds-action-bar .btn {
                width: 100%;
            }
        }
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
    <!-- Content wrapper -->
    <div class="content-wrapper">
        <!-- Content -->
        <div class="container-xxl flex-grow-1 container-p-y">
            <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Account /</span> Personal Data Sheet</h4>
            <div class="row">
                <div class="col-md-12">
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="POST" id="dataForm" action="personaldataSheet.php">
                                <div class="row">
                                    <!-- I. Personal Information -->
                                    <div class="pds-section">
                                        <div class="pds-section-header"><i class='bx bx-id-card'></i> I. Personal
                                            Information</div>

                                        <div class="row">
                                            <div class="mb-3 col-md-6">
                                                <label for="firstName" class="form-label">First Name</label>
                                                <input class="form-control" type="text" id="firstName" name="firstName"
                                                    value="<?php echo htmlspecialchars($firstname); ?>" readonly />
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="middleName" class="form-label">Middle Name</label>
                                                <input class="form-control" type="text" name="middleName" id="middleName"
                                                    value="<?php echo htmlspecialchars($middlename); ?>" readonly />
                                            </div>

                                            <div class="mb-3 col-md-6">
                                                <label for="lastName" class="form-label">Last Name</label>
                                                <input class="form-control" type="text" name="lastName" id="lastName"
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
                                                <input class="form-control" type="text" name="department" id="department"
                                                    value="<?php echo htmlspecialchars($department); ?>" readonly />
                                            </div>

                                            <div class="mb-3 col-md-6">
                                                <label for="agency_employee_no" class="form-label">AGENCY EMPLOYEE
                                                    NO.</label>
                                                <input class="form-control" type="text" name="agency_employee_no"
                                                    placeholder="Input your Agency Employee number"
                                                    value="<?= htmlspecialchars($user_data['agency_employee_no'] ?? '') ?>">
                                            </div>

                                            <div class="mb-3 col-md-6">
                                                <label for="position" class="form-label">Position</label>
                                                <input class="form-control" type="text" name="position"
                                                    placeholder="Position"
                                                    value="<?= htmlspecialchars($user_data['position'] ?? '') ?>">
                                            </div>

                                            <div class="mb-3 col-md-6">
                                                <label for="date_hired" class="form-label">Date Hired</label>
                                                <input class="form-control" type="date" id="date_hired" name="date_hired"
                                                    value="<?= htmlspecialchars($user_data['date_hired'] ?? '') ?>">
                                            </div>

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
                                                    <option value="" disabled <?= empty($sgStep) ? 'selected' : ''; ?>>
                                                        Select
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

                                            <div class="mb-3 col-md-6">
                                                <label for="personal_date_of_birth" class="form-label">Date of
                                                    Birth</label>
                                                <input class="form-control" type="date" id="personal_date_of_birth"
                                                    name="personal_date_of_birth"
                                                    value="<?= htmlspecialchars($personal_date_of_birth ?? '') ?>" />
                                            </div>

                                            <div class="mb-3 col-md-6">
                                                <label for="place_of_birth" class="form-label">Place of Birth</label>
                                                <input class="form-control" type="text" name="place_of_birth"
                                                    placeholder="Input your place of birth"
                                                    value="<?= htmlspecialchars($user_data['place_of_birth'] ?? '') ?>">
                                            </div>

                                            <div class="mb-3 col-md-6">
                                                <label class="form-label" for="sex">Sex</label>
                                                <select id="sex" name="sex" class="select2 form-select">
                                                    <option value="" disabled <?= empty($sex) ? 'selected' : ''; ?>>
                                                        Select Sex
                                                    </option>
                                                    <option value="Male" <?= ($sex == 'Male') ? 'selected' : ''; ?>>Male
                                                    </option>
                                                    <option value="Female" <?= ($sex == 'Female') ? 'selected' : ''; ?>>
                                                        Female
                                                    </option>
                                                </select>
                                            </div>

                                            <div class="mb-3 col-md-6">
                                                <label class="form-label" for="civil_status">Civil Status</label>
                                                <select id="civil_status" name="civil_status" class="select2 form-select">
                                                    <option value="" disabled <?= empty($civil_status) ? 'selected' : ''; ?>>Select Civil Status</option>
                                                    <option value="Single" <?= ($civil_status == 'Single') ? 'selected' : ''; ?>>
                                                        Single</option>
                                                    <option value="Married" <?= ($civil_status == 'Married') ? 'selected' : ''; ?>>
                                                        Married</option>
                                                    <option value="Separated/Divorced" <?= ($civil_status == 'Separated/Divorced') ? 'selected' : ''; ?>>Separated/Divorced</option>
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
                                                    <option value="" disabled <?= empty($blood_type) ? 'selected' : ''; ?>>Select Blood Type</option>
                                                    <option value="A+" <?= ($blood_type == 'A+') ? 'selected' : ''; ?>>A+
                                                    </option>
                                                    <option value="A-" <?= ($blood_type == 'A-') ? 'selected' : ''; ?>>A-
                                                    </option>
                                                    <option value="B+" <?= ($blood_type == 'B+') ? 'selected' : ''; ?>>B+
                                                    </option>
                                                    <option value="B-" <?= ($blood_type == 'B-') ? 'selected' : ''; ?>>B-
                                                    </option>
                                                    <option value="O+" <?= ($blood_type == 'O+') ? 'selected' : ''; ?>>O+
                                                    </option>
                                                    <option value="O-" <?= ($blood_type == 'O-') ? 'selected' : ''; ?>>O-
                                                    </option>
                                                    <option value="AB+" <?= ($blood_type == 'AB+') ? 'selected' : ''; ?>>
                                                        AB+
                                                    </option>
                                                    <option value="AB-" <?= ($blood_type == 'AB-') ? 'selected' : ''; ?>>
                                                        AB-
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
                                                    <option value="Dual" <?= ($citizenship == 'Dual') ? 'selected' : ''; ?>>
                                                        Dual
                                                    </option>
                                                </select>
                                            </div>

                                            <div class="mb-3 col-md-6" id="dualCitizenshipDetails" style="display: none;">
                                                <label for="dual_holder" class="form-label">If holder of Dual
                                                    Citizenship,
                                                    please indicate details</label>
                                                <input class="form-control" type="text" id="dual_holder"
                                                    name="dual_holder" placeholder="Specify your Other Citizenship"
                                                    value="<?= htmlspecialchars($user_data['dual_holder'] ?? '') ?>" />
                                            </div>

                                            <div class="pds-subheader">Residential Address</div>
                                            <div class="mb-3 col-md-6">
                                                <label for="RA_house_block_lot_no" class="form-label">House Block Lot
                                                    No.</label>
                                                <input class="form-control" type="text" id="RA_house_block_lot_no"
                                                    name="RA_house_block_lot_no" placeholder="House Block Lot No."
                                                    value="<?= htmlspecialchars($user_data['RA_house_block_lot_no'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="RA_subdivision_village"
                                                    class="form-label">Subdivision/Village</label>
                                                <input class="form-control" type="text" id="RA_subdivision_village"
                                                    name="RA_subdivision_village" placeholder="Subdivision/Village"
                                                    value="<?= htmlspecialchars($user_data['RA_subdivision_village'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="RA_city_municipality" class="form-label">City/Municipality
                                                    Address</label>
                                                <input class="form-control" type="text" id="RA_city_municipality"
                                                    name="RA_city_municipality" placeholder="City/Municipality"
                                                    value="<?= htmlspecialchars($user_data['RA_city_municipality'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="RA_street" class="form-label">Street</label>
                                                <input class="form-control" type="text" id="RA_street" name="RA_street"
                                                    placeholder="Street"
                                                    value="<?= htmlspecialchars($user_data['RA_street'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="RA_barangay" class="form-label">Barangay</label>
                                                <input class="form-control" type="text" id="RA_barangay"
                                                    name="RA_barangay" placeholder="Barangay"
                                                    value="<?= htmlspecialchars($user_data['RA_barangay'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="RA_province" class="form-label">Province</label>
                                                <input class="form-control" type="text" id="RA_province"
                                                    name="RA_province" placeholder="Province"
                                                    value="<?= htmlspecialchars($user_data['RA_province'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="RA_zip_code" class="form-label">ZIP CODE</label>
                                                <input class="form-control" type="text" id="RA_zip_code"
                                                    name="RA_zip_code" placeholder="ZIP CODE"
                                                    value="<?= htmlspecialchars($user_data['RA_zip_code'] ?? '') ?>">
                                            </div>

                                            <div class="pds-subheader">Permanent Address</div>
                                            <div class="mb-3 col-md-6">
                                                <label for="PA_house_block_lot_no" class="form-label">House Block Lot
                                                    No.</label>
                                                <input class="form-control" type="text" id="PA_house_block_lot_no"
                                                    name="PA_house_block_lot_no" placeholder="House Block Lot No."
                                                    value="<?= htmlspecialchars($user_data['PA_house_block_lot_no'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="PA_subdivision_village"
                                                    class="form-label">Subdivision/Village</label>
                                                <input class="form-control" type="text" id="PA_subdivision_village"
                                                    name="PA_subdivision_village" placeholder="Subdivision/Village"
                                                    value="<?= htmlspecialchars($user_data['PA_subdivision_village'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="PA_city_municipality" class="form-label">City/Municipality
                                                    Address</label>
                                                <input class="form-control" type="text" id="PA_city_municipality"
                                                    name="PA_city_municipality" placeholder="City/Municipality"
                                                    value="<?= htmlspecialchars($user_data['PA_city_municipality'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="PA_street" class="form-label">Street</label>
                                                <input class="form-control" type="text" id="PA_street" name="PA_street"
                                                    placeholder="Street"
                                                    value="<?= htmlspecialchars($user_data['PA_street'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="PA_barangay" class="form-label">Barangay</label>
                                                <input class="form-control" type="text" id="PA_barangay"
                                                    name="PA_barangay" placeholder="Barangay"
                                                    value="<?= htmlspecialchars($user_data['PA_barangay'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="PA_province" class="form-label">Province</label>
                                                <input class="form-control" type="text" id="PA_province"
                                                    name="PA_province" placeholder="Province"
                                                    value="<?= htmlspecialchars($user_data['PA_province'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="PA_zip_code" class="form-label">ZIP CODE</label>
                                                <input class="form-control" type="text" id="PA_zip_code"
                                                    name="PA_zip_code" placeholder="ZIP CODE"
                                                    value="<?= htmlspecialchars($user_data['PA_zip_code'] ?? '') ?>">
                                            </div>

                                            <div class="pds-subheader">Contact</div>
                                            <div class="mb-3 col-md-6">
                                                <label for="telephone_no" class="form-label">Telephone No.</label>
                                                <input class="form-control" type="text" id="telephone_no"
                                                    name="telephone_no" placeholder="Input your Telephone Number"
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
                                        </div>
                                    </div>
                                    <!-- END I. Personal Information -->
                                    <!-- II. Family Background -->
                                    <div class="pds-section">
                                        <div class="pds-section-header"><i class='bx bx-group'></i> II. Family
                                            Background</div>

                                        <div class="pds-subheader" style="margin-top:0; border-top:none; padding-top:0;">
                                            Spouse's Information</div>
                                        <div class="row">
                                            <div class="mb-3 col-md-6">
                                                <label for="spouse_first_name" class="form-label">Spouse's First
                                                    Name</label>
                                                <input class="form-control" type="text" id="spouse_first_name"
                                                    name="spouse_first_name" placeholder="Input your Spouse's First Name"
                                                    value="<?= htmlspecialchars($family_data['spouse_first_name'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="spouse_extension_name" class="form-label">Name Extension (If
                                                    applicable)</label>
                                                <input class="form-control" type="text" id="spouse_extension_name"
                                                    name="spouse_extension_name" placeholder="JR/SR"
                                                    value="<?= htmlspecialchars($family_data['spouse_extension_name'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="spouse_middle_name" class="form-label">Spouse's Middle
                                                    Name</label>
                                                <input class="form-control" type="text" id="spouse_middle_name"
                                                    name="spouse_middle_name"
                                                    placeholder="Input your Spouse's Middle Name"
                                                    value="<?= htmlspecialchars($family_data['spouse_middle_name'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="spouse_last_name" class="form-label">Spouse's Last
                                                    Name</label>
                                                <input class="form-control" type="text" id="spouse_last_name"
                                                    name="spouse_last_name" placeholder="Input your Spouse's Last Name"
                                                    value="<?= htmlspecialchars($family_data['spouse_last_name'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="spouse_occupation" class="form-label">Spouse's
                                                    Occupation</label>
                                                <input class="form-control" type="text" id="spouse_occupation"
                                                    name="spouse_occupation"
                                                    placeholder="Input your Spouse's Occupation"
                                                    value="<?= htmlspecialchars($family_data['spouse_occupation'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="spouse_employer_business_name" class="form-label">Spouse's
                                                    Employer/Business Name</label>
                                                <input class="form-control" type="text"
                                                    id="spouse_employer_business_name"
                                                    name="spouse_employer_business_name"
                                                    placeholder="Input your Spouse's Employer/Business Name"
                                                    value="<?= htmlspecialchars($family_data['spouse_employer_business_name'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="spouse_business_address" class="form-label">Spouse's
                                                    Business
                                                    Address</label>
                                                <input class="form-control" type="text" id="spouse_business_address"
                                                    name="spouse_business_address"
                                                    placeholder="Input your Spouse's Business Address"
                                                    value="<?= htmlspecialchars($family_data['spouse_business_address'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="spouse_telephone_no" class="form-label"> Spouse's Telephone
                                                    No.</label>
                                                <input class="form-control" type="text" id="spouse_telephone_no"
                                                    name="spouse_telephone_no"
                                                    placeholder="Input your Spouse's Telephone Number"
                                                    value="<?= htmlspecialchars($family_data['spouse_telephone_no'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="pds-subheader">Father's Information</div>
                                        <div class="row">
                                            <div class="mb-3 col-md-6">
                                                <label for="father_first_name" class="form-label">Father's First
                                                    Name</label>
                                                <input class="form-control" type="text" id="father_first_name"
                                                    name="father_first_name"
                                                    placeholder="Input your Father's First Name"
                                                    value="<?= htmlspecialchars($family_data['father_first_name'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="father_name_extension" class="form-label">Name Extension (If
                                                    applicable)</label>
                                                <input class="form-control" type="text" id="father_name_extension"
                                                    name="father_name_extension" placeholder="JR/SR"
                                                    value="<?= htmlspecialchars($family_data['father_name_extension'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="father_middle_name" class="form-label">Father's Middle
                                                    Name</label>
                                                <input class="form-control" type="text" id="father_middle_name"
                                                    name="father_middle_name"
                                                    placeholder="Input your Father's Middle Name"
                                                    value="<?= htmlspecialchars($family_data['father_middle_name'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="father_last_name" class="form-label">Father's Last
                                                    Name</label>
                                                <input class="form-control" type="text" id="father_last_name"
                                                    name="father_last_name" placeholder="Input your Father's Last Name"
                                                    value="<?= htmlspecialchars($family_data['father_last_name'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="pds-subheader">Mother's Information</div>
                                        <div class="row">
                                            <div class="mb-3 col-md-6">
                                                <label for="mother_first_name" class="form-label">Mother's First
                                                    Name</label>
                                                <input class="form-control" type="text" id="mother_first_name"
                                                    name="mother_first_name"
                                                    placeholder="Input your Mother's First Name"
                                                    value="<?= htmlspecialchars($family_data['mother_first_name'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="mother_last_name" class="form-label">Mother's Last
                                                    Name</label>
                                                <input class="form-control" type="text" id="mother_last_name"
                                                    name="mother_last_name" placeholder="Input your Mother's Last Name"
                                                    value="<?= htmlspecialchars($family_data['mother_last_name'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="mother_middle_name" class="form-label">Mother's Middle
                                                    Name</label>
                                                <input class="form-control" type="text" id="mother_middle_name"
                                                    name="mother_middle_name"
                                                    placeholder="Input your Mother's Middle Name"
                                                    value="<?= htmlspecialchars($family_data['mother_middle_name'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="pds-subheader">Child's Information</div>
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
                                                <div class="child-entry row">
                                                    <div class="mb-3 col-md-6">
                                                        <label for="child<?= $i + 1 ?>FullName" class="form-label">Name
                                                            of Child <?= $i + 1 ?></label>
                                                        <input class="form-control" type="text"
                                                            id="child<?= $i + 1 ?>FullName" name="children[]"
                                                            placeholder="Input Child <?= $i + 1 ?>'s Full Name"
                                                            value="<?= isset($_SESSION['children'][$i]) ? htmlspecialchars($_SESSION['children'][$i], ENT_QUOTES) : ''; ?>" />
                                                    </div>
                                                    <div class="mb-3 col-md-6">
                                                        <label for="birthdate<?= $i + 1 ?>" class="form-label">Date of
                                                            Birth</label>
                                                        <input class="form-control" type="date"
                                                            id="birthdate<?= $i + 1 ?>" name="birthdate[]"
                                                            value="<?= isset($_SESSION['birthdate'][$i]) ? htmlspecialchars($_SESSION['birthdate'][$i], ENT_QUOTES) : ''; ?>" />
                                                    </div>
                                                </div>
                                            <?php endfor; ?>
                                        </div>

                                        <button type="button" class="custom-outline-blue" onclick="addChild()">
                                            <i class='bx bx-plus'></i> Add Child</button>
                                    </div>
                                    <!-- END II. Family Background -->
                                    <!-- III. Educational Background -->
                                    <div class="pds-section">
                                        <div class="pds-section-header"><i class='bx bx-book-reader'></i> III.
                                            Educational Background</div>

                                        <div class="pds-subheader" style="margin-top:0; border-top:none; padding-top:0;">
                                            Elementary</div>
                                        <div class="row">
                                            <div class="mb-3 col-md-6">
                                                <label for="elementarySchool" class="form-label">Elementary
                                                    School</label>
                                                <input class="form-control" type="text" id="elementarySchool"
                                                    name="elementarySchool" placeholder="Input your Elementary School"
                                                    value="<?= htmlspecialchars($education_data['elementarySchool'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="basicEd" class="form-label">Basic Education</label>
                                                <input class="form-control" type="text" id="basicEd" name="basicEd"
                                                    placeholder="Input your Basic Education"
                                                    value="<?= htmlspecialchars($education_data['basicEd'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="From_1" class="form-label">From</label>
                                                <input class="form-control" type="text" id="From_1" name="From_1"
                                                    placeholder="Input your Period of Attendance(From)"
                                                    value="<?= htmlspecialchars($education_data['From_1'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="To_1" class="form-label">To</label>
                                                <input class="form-control" type="text" id="To_1" name="To_1"
                                                    placeholder="Input your Period of Attendace(To)"
                                                    value="<?= htmlspecialchars($education_data['To_1'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="elementaryYearGraduated" class="form-label">Year
                                                    Graduated</label>
                                                <input class="form-control" type="text" id="elementaryYearGraduated"
                                                    name="elementaryYearGraduated" placeholder="YYYY"
                                                    value="<?= htmlspecialchars($education_data['elementaryYearGraduated'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="LevelUnitsEarned" class="form-label">Highest Level/Units
                                                    Earned(If not Graduated)</label>
                                                <input class="form-control" type="text" id="LevelUnitsEarned"
                                                    name="LevelUnitsEarned"
                                                    placeholder="Input your Highest Level or Units Earned"
                                                    value="<?= htmlspecialchars($education_data['LevelUnitsEarned'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="scholar_honors" class="form-label">Scholarship/Academic
                                                    Honors</label>
                                                <input class="form-control" type="text" id="scholar_honors"
                                                    name="scholar_honors"
                                                    placeholder="Input your Scholarship/Academic Honors"
                                                    value="<?= htmlspecialchars($education_data['scholar_honors'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="pds-subheader">High School</div>
                                        <div class="row">
                                            <div class="mb-3 col-md-6">
                                                <label for="highSchool" class="form-label">High School</label>
                                                <input class="form-control" type="text" id="highSchool" name="highSchool"
                                                    placeholder="Input your High School"
                                                    value="<?= htmlspecialchars($education_data['highSchool'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="basicEd2" class="form-label">Basic Education</label>
                                                <input class="form-control" type="text" id="basicEd2" name="basicEd2"
                                                    placeholder="Input your Basic Education"
                                                    value="<?= htmlspecialchars($education_data['basicEd2'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="From_2" class="form-label">From</label>
                                                <input class="form-control" type="text" id="From_2" name="From_2"
                                                    placeholder="Input your Period of Attendance(From)"
                                                    value="<?= htmlspecialchars($education_data['From_2'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="To_2" class="form-label">To</label>
                                                <input class="form-control" type="text" id="To_2" name="To_2"
                                                    placeholder="Input your Period of Attendace(To)"
                                                    value="<?= htmlspecialchars($education_data['To_2'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="highSchoolYearGraduated" class="form-label">Year
                                                    Graduated</label>
                                                <input class="form-control" type="text" id="highSchoolYearGraduated"
                                                    name="highSchoolYearGraduated" placeholder="YYYY"
                                                    value="<?= htmlspecialchars($education_data['highSchoolYearGraduated'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="LevelUnitsEarned2" class="form-label">Highest Level/Units
                                                    Earned(If not Graduated)</label>
                                                <input class="form-control" type="text" id="LevelUnitsEarned2"
                                                    name="LevelUnitsEarned2"
                                                    placeholder="Input your Highest Level or Units Earned"
                                                    value="<?= htmlspecialchars($education_data['LevelUnitsEarned2'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="scholar_honors2" class="form-label">Scholarship/Academic
                                                    Honors</label>
                                                <input class="form-control" type="text" id="scholar_honors2"
                                                    name="scholar_honors2"
                                                    placeholder="Input your Scholarship/Academic Honors"
                                                    value="<?= htmlspecialchars($education_data['scholar_honors2'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="pds-subheader">College/University</div>
                                        <div class="row">
                                            <div class="mb-3 col-md-6">
                                                <label for="college" class="form-label">College/University</label>
                                                <input class="form-control" type="text" id="college" name="college"
                                                    placeholder="Input your College/University"
                                                    value="<?= htmlspecialchars($education_data['college'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="collegeDegree" class="form-label">Degree</label>
                                                <input class="form-control" type="text" id="collegeDegree"
                                                    name="collegeDegree" placeholder="Input your Degree"
                                                    value="<?= htmlspecialchars($education_data['collegeDegree'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="From_3" class="form-label">From</label>
                                                <input class="form-control" type="text" id="From_3" name="From_3"
                                                    placeholder="Input your Period of Attendance(From)"
                                                    value="<?= htmlspecialchars($education_data['From_3'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="To_3" class="form-label">To</label>
                                                <input class="form-control" type="text" id="To_3" name="To_3"
                                                    placeholder="Input your Period of Attendace(To)"
                                                    value="<?= htmlspecialchars($education_data['To_3'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="collegeYearGraduated" class="form-label">Year
                                                    Graduated</label>
                                                <input class="form-control" type="text" id="collegeYearGraduated"
                                                    name="collegeYearGraduated" placeholder="YYYY"
                                                    value="<?= htmlspecialchars($education_data['collegeYearGraduated'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="LevelUnitsEarned3" class="form-label">Highest Level/Units
                                                    Earned(If not Graduated)</label>
                                                <input class="form-control" type="text" id="LevelUnitsEarned3"
                                                    name="LevelUnitsEarned3"
                                                    placeholder="Input your Highest Level or Units Earned"
                                                    value="<?= htmlspecialchars($education_data['LevelUnitsEarned3'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="scholar_honors3" class="form-label">Scholarship/Academic
                                                    Honors</label>
                                                <input class="form-control" type="text" id="scholar_honors3"
                                                    name="scholar_honors3"
                                                    placeholder="Input your Scholarship/Academic Honors"
                                                    value="<?= htmlspecialchars($education_data['scholar_honors3'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="pds-subheader">Vocational/Trade Course</div>
                                        <div class="row">
                                            <div class="mb-3 col-md-6">
                                                <label for="vocTradeCourse" class="form-label">Vocational/Trade Course
                                                    (If applicable)</label>
                                                <input class="form-control" type="text" id="vocTradeCourse"
                                                    name="vocTradeCourse"
                                                    placeholder="Input your Vocational/Trade Course School"
                                                    value="<?= htmlspecialchars($education_data['vocTradeCourse'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="vocTradeDegree" class="form-label">Degree</label>
                                                <input class="form-control" type="text" id="vocTradeDegree"
                                                    name="vocTradeDegree"
                                                    placeholder="Input your Vocational/Trade Degree"
                                                    value="<?= htmlspecialchars($education_data['vocTradeDegree'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="From_4" class="form-label">From</label>
                                                <input class="form-control" type="text" id="From_4" name="From_4"
                                                    placeholder="Input your Period of Attendance(From)"
                                                    value="<?= htmlspecialchars($education_data['From_4'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="To_4" class="form-label">To</label>
                                                <input class="form-control" type="text" id="To_4" name="To_4"
                                                    placeholder="Input your Period of Attendace(To)"
                                                    value="<?= htmlspecialchars($education_data['To_4'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="vocTradeGraduated" class="form-label">Year Graduated</label>
                                                <input class="form-control" type="text" id="vocTradeGraduated"
                                                    name="vocTradeGraduated" placeholder="YYYY"
                                                    value="<?= htmlspecialchars($education_data['vocTradeGraduated'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="LevelUnitsEarned4" class="form-label">Highest Level/Units
                                                    Earned(If not Graduated)</label>
                                                <input class="form-control" type="text" id="LevelUnitsEarned4"
                                                    name="LevelUnitsEarned4"
                                                    placeholder="Input your Highest Level or Units Earned"
                                                    value="<?= htmlspecialchars($education_data['LevelUnitsEarned4'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="scholar_honors4" class="form-label">Scholarship/Academic
                                                    Honors</label>
                                                <input class="form-control" type="text" id="scholar_honors4"
                                                    name="scholar_honors4"
                                                    placeholder="Input your Scholarship/Academic Honors"
                                                    value="<?= htmlspecialchars($education_data['scholar_honors4'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="pds-subheader">Graduate School</div>
                                        <div class="row">
                                            <div class="mb-3 col-md-6">
                                                <label for="graduateSchool" class="form-label">Graduate School (If
                                                    applicable)</label>
                                                <input class="form-control" type="text" id="graduateSchool"
                                                    name="graduateSchool" placeholder="Input your Graduate School"
                                                    value="<?= htmlspecialchars($education_data['graduateSchool'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="graduateDegree" class="form-label">Degree</label>
                                                <input class="form-control" type="text" id="graduateDegree"
                                                    name="graduateDegree" placeholder="Input your Graduate Degree"
                                                    value="<?= htmlspecialchars($education_data['graduateDegree'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="From_5" class="form-label">From</label>
                                                <input class="form-control" type="text" id="From_5" name="From_5"
                                                    placeholder="Input your Period of Attendance(From)"
                                                    value="<?= htmlspecialchars($education_data['From_5'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="To_5" class="form-label">To</label>
                                                <input class="form-control" type="text" id="To_5" name="To_5"
                                                    placeholder="Input your Period of Attendace(To)"
                                                    value="<?= htmlspecialchars($education_data['To_5'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="LevelUnitsEarned5" class="form-label">Highest Level/Units
                                                    Earned(If not Graduated)</label>
                                                <input class="form-control" type="text" id="LevelUnitsEarned5"
                                                    name="LevelUnitsEarned5"
                                                    placeholder="Input your Highest Level or Units Earned"
                                                    value="<?= htmlspecialchars($education_data['LevelUnitsEarned5'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="graduateYearGraduated" class="form-label">Year
                                                    Graduated</label>
                                                <input class="form-control" type="text" id="graduateYearGraduated"
                                                    name="graduateYearGraduated" placeholder="YYYY"
                                                    value="<?= htmlspecialchars($education_data['graduateYearGraduated'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="scholar_honors5" class="form-label">Scholarship/Academic
                                                    Honors</label>
                                                <input class="form-control" type="text" id="scholar_honors5"
                                                    name="scholar_honors5"
                                                    placeholder="Input your Scholarship/Academic Honors"
                                                    value="<?= htmlspecialchars($education_data['scholar_honors5'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <!-- END III. Educational Background -->
                                    <!-- IV. Civil Service Eligibility -->
                                    <div class="pds-section">
                                        <div class="pds-section-header"><i class='bx bx-certification'></i> IV. Civil
                                            Service Eligibility</div>
                                        <div class="table-responsive">
                                            <table class="table table-bordered" id="civilServiceTable">
                                                <thead>
                                                    <tr>
                                                        <th>Eligibility Type</th>
                                                        <th>Rating</th>
                                                        <th>Exam Date</th>
                                                        <th>Place of Examination</th>
                                                        <th>License Number</th>
                                                        <th>License Number Validity</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($eligibility_data)): ?>
                                                        <?php foreach ($eligibility_data as $row): ?>
                                                            <tr>
                                                                <td>
                                                                    <input type="text" class="form-control"
                                                                        name="eligibility[]"
                                                                        value="<?= htmlspecialchars($row['eligibility_type'] ?? '', ENT_QUOTES) ?>" />
                                                                </td>
                                                                <td>
                                                                    <input type="text" class="form-control" name="rating[]"
                                                                        value="<?= htmlspecialchars($row['rating'] ?? '', ENT_QUOTES) ?>" />
                                                                </td>
                                                                <td>
                                                                    <input type="date" class="form-control"
                                                                        name="examDate[]"
                                                                        value="<?= htmlspecialchars($row['exam_date'] ?? '', ENT_QUOTES) ?>" />
                                                                </td>
                                                                <td>
                                                                    <input type="text" class="form-control"
                                                                        name="examPlace[]"
                                                                        value="<?= htmlspecialchars($row['exam_place'] ?? '', ENT_QUOTES) ?>" />
                                                                </td>
                                                                <td>
                                                                    <input type="text" class="form-control"
                                                                        name="licenseNumber[]"
                                                                        value="<?= htmlspecialchars($row['license_number'] ?? '', ENT_QUOTES) ?>" />
                                                                </td>
                                                                <td>
                                                                    <input type="date" class="form-control"
                                                                        name="validity[]"
                                                                        value="<?= htmlspecialchars($row['validity'] ?? '', ENT_QUOTES) ?>" />
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td><input type="text" class="form-control"
                                                                    name="eligibility[]" placeholder="Input Eligibility Type" />
                                                            </td>
                                                            <td><input type="text" class="form-control" name="rating[]"
                                                                    placeholder="Rating" /></td>
                                                            <td><input type="date" class="form-control" name="examDate[]" />
                                                            </td>
                                                            <td><input type="text" class="form-control" name="examPlace[]"
                                                                    placeholder="Exam Place" /></td>
                                                            <td><input type="text" class="form-control"
                                                                    name="licenseNumber[]" placeholder="Number" /></td>
                                                            <td><input type="date" class="form-control" name="validity[]" />
                                                            </td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <button type="button" class="custom-outline-blue mt-2"
                                            onclick="addEligibilityRow()"><i class='bx bx-plus'></i> Add Eligibility</button>
                                    </div>
                                    <!-- END IV. Civil Service Eligibility -->

                                    <!-- V. Work Experience -->
                                    <div class="pds-section">
                                        <div class="pds-section-header"><i class='bx bx-briefcase'></i> V. Work
                                            Experience</div>
                                        <div class="table-responsive">
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
                                                                    <input type="text" class="form-control"
                                                                        name="position_title[]"
                                                                        value="<?= htmlspecialchars($row['position_title'], ENT_QUOTES); ?>" />
                                                                </td>
                                                                <td>
                                                                    <input type="text" class="form-control"
                                                                        name="we_department[]"
                                                                        value="<?= htmlspecialchars($row['we_department'], ENT_QUOTES); ?>" />
                                                                </td>
                                                                <td>
                                                                    <input type="date" class="form-control"
                                                                        name="work_from_date[]"
                                                                        value="<?= htmlspecialchars($row['work_from_date'], ENT_QUOTES); ?>" />
                                                                </td>
                                                                <td>
                                                                    <input type="date" class="form-control"
                                                                        name="work_to_date[]"
                                                                        value="<?= htmlspecialchars($row['work_to_date'], ENT_QUOTES); ?>" />
                                                                </td>
                                                                <td>
                                                                    <input type="text" class="form-control"
                                                                        name="monthly_salary[]"
                                                                        value="<?= htmlspecialchars($row['monthly_salary'], ENT_QUOTES); ?>" />
                                                                </td>
                                                                <td>
                                                                    <input type="text" class="form-control"
                                                                        name="salary_grade[]"
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
                                                            </tr>
                                                        <?php endwhile; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td><input type="text" class="form-control"
                                                                    name="position_title[]" placeholder="Position Title" />
                                                            </td>
                                                            <td><input type="text" class="form-control"
                                                                    name="we_department[]"
                                                                    placeholder="Department / Company" /></td>
                                                            <td><input type="date" class="form-control"
                                                                    name="work_from_date[]" /></td>
                                                            <td><input type="date" class="form-control"
                                                                    name="work_to_date[]" /></td>
                                                            <td><input type="text" class="form-control"
                                                                    name="monthly_salary[]" placeholder="Monthly Salary" />
                                                            </td>
                                                            <td><input type="text" class="form-control"
                                                                    name="salary_grade[]" placeholder="Salary Grade" />
                                                            </td>
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
                                        <button type="button" class="custom-outline-blue mt-2"
                                            onclick="addWorkExperienceRow()"><i class='bx bx-plus'></i> Add Work
                                            Experience</button>
                                    </div>
                                    <!-- END V. Work Experience -->

                                    <!-- VI. Voluntary Work -->
                                    <div class="pds-section">
                                        <div class="pds-section-header"><i class='bx bx-heart'></i> VI. Voluntary Work
                                            or Involvement in Civic / Non-Government / People / Voluntary Organizations
                                        </div>
                                        <div class="table-responsive">
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
                                                                    <input type="text" class="form-control"
                                                                        name="organization[]"
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
                                                    <?php else: ?>
                                                        <tr>
                                                            <td><input type="text" class="form-control" name="organization[]"
                                                                    placeholder="Organization Name & Address" /></td>
                                                            <td><input type="date" class="form-control"
                                                                    name="voluntary_from_date[]" /></td>
                                                            <td><input type="date" class="form-control"
                                                                    name="voluntary_to_date[]" /></td>
                                                            <td><input type="number" class="form-control"
                                                                    name="voluntary_hours[]" placeholder="Hours" /></td>
                                                            <td><input type="text" class="form-control"
                                                                    name="voluntary_position[]"
                                                                    placeholder="Position / Nature of Work" /></td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <button type="button" class="custom-outline-blue mt-2"
                                            onclick="addVoluntaryWorkRow()"><i class='bx bx-plus'></i> Add Voluntary
                                            Work</button>
                                    </div>
                                    <!-- END VI. Voluntary Work -->

                                    <!-- VII. Learning and Development -->
                                    <div class="pds-section">
                                        <div class="pds-section-header"><i class='bx bx-chalkboard'></i> VII. Learning
                                            and Development (L&D) Interventions/Training Programs Attended</div>
                                        <div class="table-responsive">
                                            <table class="table table-bordered" id="learningDevelopmentTable">
                                                <thead>
                                                    <tr>
                                                        <th>Title of L&D Interventions/Training Programs</th>
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
                                                                <td><input type="text" class="form-control"
                                                                        name="training_title[]"
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
                                                                <td><input type="text" class="form-control"
                                                                        name="type_of_ld[]"
                                                                        value="<?= htmlspecialchars($ld['type_of_ld'], ENT_QUOTES) ?>" />
                                                                </td>
                                                                <td><input type="text" class="form-control" name="sponsor[]"
                                                                        value="<?= htmlspecialchars($ld['sponsor'], ENT_QUOTES) ?>" />
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
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
                                        <button type="button" class="custom-outline-blue mt-2"
                                            onclick="addLearningDevelopmentRow()"><i class='bx bx-plus'></i> Add Training
                                            Program</button>
                                    </div>
                                    <!-- END VII. Learning and Development -->

                                    <!-- VIII. Other Information -->
                                    <div class="pds-section">
                                        <div class="pds-section-header"><i class='bx bx-info-circle'></i> VIII. Other
                                            Information</div>
                                        <div class="table-responsive">
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
                                        <button type="button" class="custom-outline-blue mt-2"
                                            onclick="addOtherInfoCountRow()"><i class='bx bx-plus'></i> Add Skills</button>
                                    </div>
                                    <!-- END VIII. Other Information -->
                                    <!-- Additional Questions -->
                                    <div class="pds-section">
                                        <div class="pds-section-header"><i class='bx bx-question-mark'></i> Additional
                                            Questions</div>

                                        <div class="pds-question">
                                            <label class="form-label" for="q1">
                                                Are you related by consanguinity or affinity to the appointing or
                                                recommending authority, or to the chief of bureau or office, or to the
                                                person who has immediate supervision over you in the Office, Bureau, or
                                                Department where you will be appointed?
                                            </label>
                                            <p>A. Within the third degree?</p>
                                            <select class="form-select" id="q1" name="q1">
                                                <option value="No" <?= ($q1 == 'No') ? 'selected' : ''; ?>>No</option>
                                                <option value="Yes" <?= ($q1 == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                            </select>
                                        </div>

                                        <div class="pds-question">
                                            <p>B. Within the fourth degree (for Local Government Unit - Career
                                                Employees)?</p>
                                            <select class="form-select" id="q2" name="q2">
                                                <option value="No" <?= ($q2 == 'No') ? 'selected' : ''; ?>>No</option>
                                                <option value="Yes" <?= ($q2 == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                            </select>
                                            <div class="mt-2" id="Details_2" style="display: none;">
                                                <input type="text" id="details2" name="details2" class="form-control"
                                                    placeholder="Provide details"
                                                    value="<?= htmlspecialchars($details2 ?? '') ?>" />
                                            </div>
                                        </div>

                                        <div class="pds-question">
                                            <label class="form-label">Have you ever been found guilty of any
                                                administrative offense?</label>
                                            <select class="form-select" id="q3" name="q3">
                                                <option value="No" <?= ($q3 == 'No') ? 'selected' : ''; ?>>No</option>
                                                <option value="Yes" <?= ($q3 == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                            </select>
                                            <div class="mt-2" id="Details_3" style="display: none;">
                                                <input type="text" id="details3" name="details3" class="form-control"
                                                    placeholder="Provide details"
                                                    value="<?= htmlspecialchars($details3 ?? '') ?>" />
                                            </div>
                                        </div>

                                        <div class="pds-question">
                                            <label class="form-label">Have you been criminally charged before any
                                                court?</label>
                                            <select class="form-select" id="q4" name="q4">
                                                <option value="No" <?= ($q4 == 'No') ? 'selected' : ''; ?>>No</option>
                                                <option value="Yes" <?= ($q4 == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                            </select>
                                            <div class="mt-2" id="Details_4" style="display: none;">
                                                <input type="date" id="details4" name="details4" class="form-control mb-2"
                                                    placeholder="Date Filed" value="<?= htmlspecialchars($details4 ?? '') ?>" />
                                                <input type="text" id="details41" name="details41" class="form-control"
                                                    placeholder="Status of Case/s"
                                                    value="<?= htmlspecialchars($details41 ?? '') ?>" />
                                            </div>
                                        </div>

                                        <div class="pds-question">
                                            <label class="form-label">Have you ever been convicted of any crime or
                                                violation of any law, decree, ordinance or regulation by any court or
                                                tribunal?</label>
                                            <select class="form-select" id="q5" name="q5">
                                                <option value="No" <?= ($q5 == 'No') ? 'selected' : ''; ?>>No</option>
                                                <option value="Yes" <?= ($q5 == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                            </select>
                                            <div class="mt-2" id="Details_5" style="display: none;">
                                                <input type="text" id="details5" name="details5" class="form-control"
                                                    placeholder="Provide details"
                                                    value="<?= htmlspecialchars($details5 ?? '') ?>" />
                                            </div>
                                        </div>

                                        <div class="pds-question">
                                            <label class="form-label">Have you ever been separated from the service in
                                                any of the following modes: resignation, retirement, dropped from the
                                                rolls, dismissal, termination, end of term, finished contract or phased
                                                out (abolition) in the public or private sector?</label>
                                            <select class="form-select" id="q6" name="q6">
                                                <option value="No" <?= ($q6 == 'No') ? 'selected' : ''; ?>>No</option>
                                                <option value="Yes" <?= ($q6 == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                            </select>
                                            <div class="mt-2" id="Details_6" style="display: none;">
                                                <input type="text" id="details6" name="details6" class="form-control"
                                                    placeholder="Provide details"
                                                    value="<?= htmlspecialchars($details6 ?? '') ?>" />
                                            </div>
                                        </div>

                                        <div class="pds-question">
                                            <label class="form-label">Have you ever been a candidate in a national or
                                                local election held within last year (except Barangay Election)?</label>
                                            <select class="form-select" id="q7" name="q7">
                                                <option value="No" <?= ($q7 == 'No') ? 'selected' : ''; ?>>No</option>
                                                <option value="Yes" <?= ($q7 == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                            </select>
                                            <div class="mt-2" id="Details_7" style="display: none;">
                                                <input type="text" id="details7" name="details7" class="form-control"
                                                    placeholder="Provide details"
                                                    value="<?= htmlspecialchars($details7 ?? '') ?>" />
                                            </div>
                                        </div>

                                        <div class="pds-question">
                                            <label class="form-label">Have you resigned from the government service
                                                during the three (3)-month period before the last election to
                                                promote/actively campaign for a national or local candidate?</label>
                                            <select class="form-select" id="q8" name="q8">
                                                <option value="No" <?= ($q8 == 'No') ? 'selected' : ''; ?>>No</option>
                                                <option value="Yes" <?= ($q8 == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                            </select>
                                            <div class="mt-2" id="Details_8" style="display: none;">
                                                <input type="text" id="details8" name="details8" class="form-control"
                                                    placeholder="Provide details"
                                                    value="<?= htmlspecialchars($details8 ?? '') ?>" />
                                            </div>
                                        </div>

                                        <div class="pds-question">
                                            <label class="form-label">Have you acquired the status of an immigrant or
                                                permanent resident of another country?</label>
                                            <select class="form-select" id="q9" name="q9">
                                                <option value="No" <?= ($q9 == 'No') ? 'selected' : ''; ?>>No</option>
                                                <option value="Yes" <?= ($q9 == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                            </select>
                                            <div class="mt-2" id="Details_9" style="display: none;">
                                                <input type="text" id="details9" name="details9" class="form-control"
                                                    placeholder="Provide details"
                                                    value="<?= htmlspecialchars($details9 ?? '') ?>" />
                                            </div>
                                        </div>

                                        <div class="pds-question">
                                            <label class="form-label">Pursuant to: (a) Indigenous People's Act (RA
                                                8371); (b) Magna Carta for Disabled Persons (RA 7277); and (c) Solo
                                                Parents Welfare Act of 2000 (RA 8972), please answer the following
                                                items:</label>
                                            <p>A. Are you a member of any indigenous group?</p>
                                            <select class="form-select" id="q10" name="q10">
                                                <option value="No" <?= ($q10 == 'No') ? 'selected' : ''; ?>>No</option>
                                                <option value="Yes" <?= ($q10 == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                            </select>
                                            <div class="mt-2" id="Details_10" style="display: none;">
                                                <input type="text" id="details10" name="details10" class="form-control"
                                                    placeholder="Provide details"
                                                    value="<?= htmlspecialchars($details10 ?? '') ?>" />
                                            </div>
                                        </div>

                                        <div class="pds-question">
                                            <p>B. Are you a person with disability?</p>
                                            <select class="form-select" id="q11" name="q11">
                                                <option value="No" <?= ($q11 == 'No') ? 'selected' : ''; ?>>No</option>
                                                <option value="Yes" <?= ($q11 == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                            </select>
                                            <div class="mt-2" id="Details_11" style="display: none;">
                                                <input type="text" id="details11" name="details11" class="form-control"
                                                    placeholder="Provide details"
                                                    value="<?= htmlspecialchars($details11 ?? '') ?>" />
                                            </div>
                                        </div>

                                        <div class="pds-question">
                                            <p>C. Are you a solo parent?</p>
                                            <select class="form-select" id="q12" name="q12">
                                                <option value="No" <?= ($q12 == 'No') ? 'selected' : ''; ?>>No</option>
                                                <option value="Yes" <?= ($q12 == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                            </select>
                                            <div class="mt-2" id="Details_12" style="display: none;">
                                                <input type="text" id="details12" name="details12" class="form-control"
                                                    placeholder="Provide details"
                                                    value="<?= htmlspecialchars($details12 ?? '') ?>" />
                                            </div>
                                        </div>
                                    </div>
                                    <!-- END Additional Questions -->

                                    <!-- References -->
                                    <div class="pds-section mb-0">
                                        <div class="pds-section-header"><i class='bx bx-contact'></i> References
                                            <span class="text-muted small fw-normal">(provide at least three)</span>
                                        </div>
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
                                        <button type="button" class="custom-outline-blue" onclick="addReference()">
                                            <i class='bx bx-plus'></i> Add Reference</button>
                                    </div>
                                    <!-- END References -->

                                </div>

                                <div class="pds-action-bar">
                                    <button type="button" class="btn btn-primary" name="save" id="save">
                                        <i class='bx bx-save'></i> Save
                                    </button>
                                    <!-- <button type="submit" class="btn btn-outline-secondary" name="download_excel" DISABLED TEMPORARY WHILE REVISING PDS
                                        id="download_excel">Download as Excel</button> -->
                                </div>
                            </form>
                        </div>
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
        // ---- Salary data: SG => { Step => Salary } ----
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
        };

        function updateSalary() {
            const grade = $("#employeeSalaryGrade").val();
            const step = $("#sgStep").val();
            if (grade && step && salaryData[grade] && salaryData[grade][step]) {
                $("#salary").val(salaryData[grade][step]);
            } else {
                $("#salary").val('');
            }
        }
        $("#employeeSalaryGrade, #sgStep").change(updateSalary);

        // ---- Citizenship toggle ----
        function toggleCitizenshipFields() {
            const citizenship = document.getElementById("citizenship").value;
            document.getElementById("dualCitizenshipDetails").style.display = (citizenship === "Dual") ? "block" : "none";
        }

        // ---- Yes/No question -> details field toggle (q2-q12; q1 has no details field) ----
        function bindDetailsToggle(questionId, detailsId) {
            const select = document.getElementById(questionId);
            const details = document.getElementById(detailsId);
            if (!select || !details) return;
            const update = () => { details.style.display = (select.value === "Yes") ? "block" : "none"; };
            select.addEventListener("change", update);
            update();
        }

        // ---- Children (Family Background) ----
        let childCount = <?= (int) ($totalChildren ?? 1) ?>;
        const maxChildren = 10;

        function addChild() {
            if (childCount >= maxChildren) {
                alert("You can only add up to 10 children.");
                return;
            }
            childCount++;
            const container = document.getElementById("childrenContainer");
            const childDiv = document.createElement("div");
            childDiv.classList.add("child-entry", "row");
            childDiv.innerHTML = `
                <div class="mb-3 col-md-6">
                    <label class="form-label">Name of Child ${childCount}</label>
                    <input class="form-control" type="text" name="children[]" placeholder="Input Child ${childCount}'s Full Name"/>
                </div>
                <div class="mb-3 col-md-6">
                    <label class="form-label">Date of Birth</label>
                    <input class="form-control" type="date" name="birthdate[]"/>
                </div>`;
            container.appendChild(childDiv);
        }

        // ---- Civil Service Eligibility ----
        let EgibilityCount = 1;
        const EgibilityMax = 6;

        function addEligibilityRow() {
            if (EgibilityCount >= EgibilityMax) {
                alert("You can only add up to 5 civil service eligibilities.");
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
                <td><input type="date" class="form-control" name="validity[]"/></td>`;
        }

        // ---- Work Experience ----
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
                </td>`;
        }

        // ---- Voluntary Work ----
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
                <td><input type="text" class="form-control" name="voluntary_position[]" placeholder="Position / Nature of Work"/></td>`;
        }

        // ---- Learning & Development ----
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
                <td><input type="text" class="form-control" name="sponsor[]" placeholder="Organization Name"/></td>`;
        }

        // ---- Other Information ----
        let OtherInfoCount = 1;
        const OtherInfoMax = 5;

        function addOtherInfoCountRow() {
            if (OtherInfoCount >= OtherInfoMax) {
                alert("You can only add up to 5 skills.");
                return;
            }
            OtherInfoCount++;
            const table = document.getElementById("OtherInfoCountTable").getElementsByTagName('tbody')[0];
            const newRow = table.insertRow();
            newRow.innerHTML = `
                <td><input type="text" class="form-control" name="skillsTitle[]" placeholder="Enter Special Skills and Hobbies"/></td>
                <td><input type="text" class="form-control" name="nonAcad[]" placeholder="Non-Academic Distinctions / Hobbies"/></td>
                <td><input type="text" class="form-control" name="membership[]" placeholder="Membership in Association / Organization"/></td>`;
        }

        // ---- References ----
        let refCount = 1;
        const refMax = 5;

        function addReference() {
            if (refCount >= refMax) {
                alert("You can only add up to 5 references.");
                return;
            }
            refCount++;
            const container = document.getElementById("referenceContainer");
            const newRow = document.createElement("div");
            newRow.classList.add("mb-3", "row", "reference-item");
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
                </div>`;
            container.appendChild(newRow);
        }

        // ---- Date inputs: enforce a 4-digit year on blur (delegated once, covers dynamically added rows too) ----
        document.addEventListener('blur', function (e) {
            if (e.target && e.target.matches && e.target.matches('input[type="date"]')) {
                const value = e.target.value;
                if (!value) return;
                const year = value.split('-')[0];
                if (year.length !== 4) {
                    alert('Please enter a valid date with a 4-digit year (YYYY-MM-DD).');
                    e.target.value = '';
                }
            }
        }, true);

        document.addEventListener("DOMContentLoaded", function () {
            toggleCitizenshipFields();
            ['q2', 'q3', 'q4', 'q5', 'q6', 'q7', 'q8', 'q9', 'q10', 'q11', 'q12'].forEach(q => {
                bindDetailsToggle(q, 'Details_' + q.slice(1));
            });
        });

        // ---- Save flow ----
        document.getElementById('save').addEventListener('click', function (e) {
            e.preventDefault();
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