<?php
session_start();
require '../vendor/autoload.php';
require 'login_verification.php';
require '../db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Database connection
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}


// Fetch current user details
$username = $_SESSION['username'];

$stmt = $conn->prepare("
    SELECT id, firstname, middlename, lastname, email, department, profile_picture
    FROM users
    WHERE username = ?
");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result(
    $user_id,
    $firstname,
    $middlename,
    $lastname,
    $email,
    $department,
    $profile_picture
);
$stmt->fetch();
$stmt->close();

// Set default profile picture
if (!isset($_SESSION['profile_picture'])) {
    $_SESSION['profile_picture'] = $profile_picture ?: '../assets/img/avatars/1.png';
}

// Update last activity
date_default_timezone_set('Asia/Manila');
$now = date('Y-m-d H:i:s');

$updateActivity = $conn->prepare("
    UPDATE users SET last_activity = ? WHERE username = ?
");
$updateActivity->bind_param("ss", $now, $username);
$updateActivity->execute();
$updateActivity->close();

// Close DB connection
$conn->close();
?>


<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Automated Payslip</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet" />

    <!-- Icons -->
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <style>
        body {
            font-family: 'Public Sans', sans-serif;
        }

        .payslip-card {
            border: 1px dashed #d9dee3;
        }

        .payslip-header {
            border-bottom: 2px solid #696cff;
        }

        .amount {
            text-align: right;
        }

        .net-pay {
            font-size: 1.25rem;
            font-weight: 700;
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

    <div class="container-xxl flex-grow-1 container-p-y">
        <h4 class="fw-bold py-3 mb-4">Automated Payslip</h4>

        <div class="row">
            <!-- Payslip Form -->
            <div class="col-lg-5 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Employee & Payroll Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Employee ID</label>
                                <input type="text" class="form-control" placeholder="EMP-001" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Pay Period</label>
                                <input type="text" class="form-control" placeholder="Aug 1–15, 2025" />
                            </div>
                            <div class="col-12">
                                <label class="form-label">Employee Name</label>
                                <input type="text" class="form-control" placeholder="Juan Dela Cruz" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Department</label>
                                <input type="text" class="form-control" placeholder="Finance" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Position</label>
                                <input type="text" class="form-control" placeholder="Accountant" />
                            </div>

                            <hr class="my-3" />

                            <div class="col-md-6">
                                <label class="form-label">Basic Salary</label>
                                <input type="number" class="form-control" placeholder="0.00" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Overtime Pay</label>
                                <input type="number" class="form-control" placeholder="0.00" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Allowances</label>
                                <input type="number" class="form-control" placeholder="0.00" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Other Earnings</label>
                                <input type="number" class="form-control" placeholder="0.00" />
                            </div>

                            <hr class="my-3" />

                            <div class="col-md-6">
                                <label class="form-label">SSS</label>
                                <input type="number" class="form-control" placeholder="0.00" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">PhilHealth</label>
                                <input type="number" class="form-control" placeholder="0.00" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Pag-IBIG</label>
                                <input type="number" class="form-control" placeholder="0.00" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Withholding Tax</label>
                                <input type="number" class="form-control" placeholder="0.00" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payslip Preview -->
            <div class="col-lg-7">
                <div class="card payslip-card">
                    <div class="card-body">
                        <div class="payslip-header pb-2 mb-3">
                            <h5 class="mb-0">Company Name</h5>
                            <small class="text-muted">Payslip Preview</small>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Employee:</strong> Juan Dela Cruz</p>
                                <p class="mb-1"><strong>ID:</strong> EMP-001</p>
                                <p class="mb-0"><strong>Department:</strong> Finance</p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <p class="mb-1"><strong>Pay Period:</strong> Aug 1–15, 2025</p>
                                <p class="mb-0"><strong>Position:</strong> Accountant</p>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Earnings</th>
                                        <th class="amount">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Basic Salary</td>
                                        <td class="amount">₱ 0.00</td>
                                    </tr>
                                    <tr>
                                        <td>Overtime Pay</td>
                                        <td class="amount">₱ 0.00</td>
                                    </tr>
                                    <tr>
                                        <td>Allowances</td>
                                        <td class="amount">₱ 0.00</td>
                                    </tr>
                                    <tr>
                                        <td>Other Earnings</td>
                                        <td class="amount">₱ 0.00</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="table-responsive mt-3">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Deductions</th>
                                        <th class="amount">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>SSS</td>
                                        <td class="amount">₱ 0.00</td>
                                    </tr>
                                    <tr>
                                        <td>PhilHealth</td>
                                        <td class="amount">₱ 0.00</td>
                                    </tr>
                                    <tr>
                                        <td>Pag-IBIG</td>
                                        <td class="amount">₱ 0.00</td>
                                    </tr>
                                    <tr>
                                        <td>Withholding Tax</td>
                                        <td class="amount">₱ 0.00</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <hr />

                        <div class="d-flex justify-content-between net-pay">
                            <span>Net Pay</span>
                            <span>₱ 0.00</span>
                        </div>

                        <div class="mt-4 text-end">
                            <button class="btn btn-outline-secondary me-2">Print</button>
                            <button class="btn btn-primary">Generate PDF</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- ================= REQUIRED SCRIPTS ================= -->

    <!-- Core JS -->
    <script src="../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../assets/vendor/libs/popper/popper.js"></script>
    <script src="../assets/vendor/js/bootstrap.js"></script>

    <!-- Vendors JS -->
    <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>

    <!-- Sidebar Menu JS (VERY IMPORTANT) -->
    <script src="../assets/vendor/js/menu.js"></script>

    <!-- Main JS -->
    <script src="../assets/js/main.js"></script>

</body>
</html>