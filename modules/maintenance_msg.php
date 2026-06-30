<?php
session_start();
require '../vendor/autoload.php';
require '../db.php';
require 'login_verification.php';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Database Connection Failed");
}

/* Update last activity */
date_default_timezone_set('Asia/Manila');
$now = date('Y-m-d H:i:s');
$username = $_SESSION['username'];

$updateActivity = $conn->prepare(
    "UPDATE users SET last_activity = ? WHERE username = ?"
);
$updateActivity->bind_param("ss", $now, $username);
$updateActivity->execute();
$updateActivity->close();

/* ✅ FETCH department for sidebar */
$stmt = $conn->prepare(
    "SELECT department FROM users WHERE username = ?"
);
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($department);
$stmt->fetch();
$stmt->close();

$conn->close();
?>


<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
  data-assets-path="../assets/" data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
  <title>Maintenance</title>

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="../assets/img/favicon/districtone.png" />

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
  <link rel="stylesheet" href="./css/accountSettings.css">

  <!-- Vendors CSS -->
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

  <!-- Core Helper Scripts -->
  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>

  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>


<body>

<?php
$role = $_SESSION['role'];
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
  <div class="container-xxl flex-grow-1 container-p-y">

    <div class="row justify-content-center">
      <div class="col-md-6">
        <div class="card text-center p-5">
          <div class="card-body">
            <i class="bx bx-cog bx-spin mb-3" style="font-size: 64px; color:#696cff;"></i>
            <h3 class="fw-bold mb-2">Under Maintenance</h3>
            <p class="text-muted">
              We’re improving our system to serve you better.<br><br><br>
              <strong>MANAGEMENT INFORMATION SERVICES SECTION</strong>
            </p>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<div class="layout-overlay layout-menu-toggle"></div>

<!-- Core JS -->
<script src="../assets/vendor/libs/jquery/jquery.js"></script>
<script src="../assets/vendor/libs/popper/popper.js"></script>
<script src="../assets/vendor/js/bootstrap.js"></script>
<script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="../assets/vendor/js/menu.js"></script>
<script src="../assets/js/main.js"></script>

</body>
</html>
