<?php
session_start();
require '../db.php';

/* =====================
   AUTH CHECK
===================== */
if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    die("Unauthorized access");
}

/* =====================
   SESSION VARIABLES
===================== */
$user_id        = $_SESSION['user_id'] ?? null;
$username       = $_SESSION['username'] ?? '';
$role           = $_SESSION['role'] ?? '';
$normalizedRole = strtolower($role);

/* =====================
   FETCH USER DEPARTMENT
===================== */
$department = '';

if ($user_id) {
    $stmt = $conn->prepare("SELECT department FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $department = $row['department'] ?? '';
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed"
      dir="ltr"
      data-theme="theme-default"
      data-assets-path="../assets/"
      data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8">
  <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
  <title>Profile</title>
  <meta name="description" content="">

  <!-- Favicon -->
  <link rel="icon" type="image/png" href="../assets/img/favicon/districtone.png">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

  <!-- Icons -->
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css">
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <!-- Core Template CSS -->
  <link rel="stylesheet" href="../assets/vendor/css/core.css">
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css">
  <link rel="stylesheet" href="../assets/css/demo.css">
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css">

  <!-- Page CSS -->
  <link rel="stylesheet" href="./css/profileTeams.css">

  <!-- Apex Charts (kept because profile originally had it) -->
  <link rel="stylesheet" href="../assets/vendor/libs/apex-charts/apex-charts.css">

  <!-- Template Helpers -->
  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>

</head>



<body>

<!-- =====================
   SIDEBAR
===================== -->
<?php
switch ($normalizedRole) {
    case 'user':
        include '../user/sidebar.php';
        break;
    case 'mis':
        include '../mis/sidebar.php';
        break;
    case 'admin':
        include '../admin/sidebar.php';
        break;
    case 'super admin':
        include '../super_admin/sidebar.php';
        break;
    default:
        echo "Unauthorized";
        exit;
}
?>

<!-- =====================
   PAGE CONTENT
===================== -->
<div class="container-xxl flex-grow-1 container-p-y">

  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">

      <div class="card shadow-sm">
        <div class="card-header bg-white border-0 text-center">
          <i class="fa-solid fa-file-excel fa-2x text-success mb-2"></i>
          <h5 class="mb-0">Upload Payroll File</h5>
          <small class="text-muted">Excel-based payroll import</small>
        </div>

        <div class="card-body">
          <form action="payroll/upload.php" method="post" enctype="multipart/form-data" onsubmit="disableBtn(this)">
            <div class="mb-3">
              <label class="form-label fw-semibold">Payroll Excel File</label>
              <input type="file" name="excel_file" class="form-control" accept=".xlsx,.xls" required>
            </div>

            <button type="submit" name="submit" class="btn btn-primary w-100">
              Upload Payroll
            </button>
          </form>
        </div>

        <div class="card-footer bg-white border-0 text-center">
          <a href="payroll/payrollData.php" class="btn btn-outline-success btn-sm">
            <i class="fa fa-table me-1"></i> View Payroll Data
          </a>
        </div>
      </div>

    </div>
  </div>

</div>

<!-- =====================
   CORE JS
===================== -->
<script src="../assets/vendor/libs/jquery/jquery.js"></script>
<script src="../assets/vendor/libs/popper/popper.js"></script>
<script src="../assets/vendor/js/bootstrap.js"></script>
<script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="../assets/vendor/js/menu.js"></script>
<script src="../assets/js/main.js"></script>

<!-- SweetAlert -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if (isset($_GET['status'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {

<?php if ($_GET['status'] === 'success'): ?>
  Swal.fire({
    icon: 'success',
    title: 'Upload Successful',
    text: 'Payroll data has been uploaded successfully.',
    confirmButtonColor: '#3085d6'
  }).then(() => {
    window.history.replaceState({}, document.title, window.location.pathname);
  });

<?php elseif ($_GET['status'] === 'error'): ?>
  Swal.fire({
    icon: 'error',
    title: 'Upload Failed',
    text: <?= json_encode($_GET['msg'] ?? 'An error occurred.'); ?>,
    confirmButtonColor: '#d33'
  }).then(() => {
    window.history.replaceState({}, document.title, window.location.pathname);
  });
<?php endif; ?>

});
</script>
<?php endif; ?>

<script>
function disableBtn(form) {
  const btn = form.querySelector('button[type="submit"]');
  btn.disabled = true;
  btn.innerText = 'Uploading...';
}
</script>

</body>
</html>
