<?php
session_start();
require '../vendor/autoload.php';
require 'login_verification.php';
require '../db.php';
// =======================
// FETCH USER
// =======================
$username = $_SESSION['username'];

$query = $conn->prepare("
  SELECT id, firstname, lastname, department 
  FROM users 
  WHERE username=?
");

$query->bind_param("s", $username);
$query->execute();
$query->bind_result($user_id, $firstname, $lastname, $department);
$query->fetch();
$query->close();

$conn = new mysqli($host, $user, $pass, $db);



$mir_list = $conn->query("SELECT * FROM mir_reports ORDER BY id DESC");


?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/"
  data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8">
  <title>MIR List</title>

  <!-- Favicon -->
  <link rel="icon" type="image/png" href="../assets/img/favicon/districtone.png">

  <!-- Fonts & Icons -->
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css">

  <!-- Core CSS -->
  <link rel="stylesheet" href="../assets/vendor/css/core.css">
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css">
  <link rel="stylesheet" href="../assets/css/demo.css">
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css">

  <!-- SweetAlert -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <!-- Helpers -->
  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>
</head>

<body>

  <?php
  // ALERTS
  if (isset($_SESSION['error'])) {
    echo "<script>Swal.fire('Error','" . addslashes($_SESSION['error']) . "','error')</script>";
    unset($_SESSION['error']);
  }
  if (isset($_SESSION['success'])) {
    echo "<script>Swal.fire('Success','" . addslashes($_SESSION['success']) . "','success')</script>";
    unset($_SESSION['success']);
  }
  ?>

  <?php
  $username = $_SESSION['username'] ?? '';

  switch ($_SESSION['role']) {
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
  }
  ?>

  <!-- CONTENT -->
  <div class="content-wrapper">
    <div class="container-xxl flex-grow-1 container-p-y">



      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">MIR List</h5>

          <a href="mir.php" class="btn btn-primary btn-sm">
            <i class="bx bx-plus"></i> New MIR
          </a>
        </div>

        <div class="card-body">

          <input type="text" id="search" class="form-control mb-3" placeholder="🔍 Search...">

          <div style="max-height: 500px; overflow-y: auto;">
            <table class="table table-hover table-bordered">
              <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                <tr>
                  <th>#</th>
                  <th>Report No</th>
                  <th>End User</th>
                  <th>Department</th>
                  <th>Date</th>
                  <th width="220">Actions</th>
                </tr>
              </thead>

              <tbody>
                <?php $i = 1;
                while ($row = $mir_list->fetch_assoc()): ?>
                  <tr>
                    <td><?= $i++ ?></td>
                    <td><strong><?= htmlspecialchars($row['report_no']) ?></strong></td>
                    <td><?= htmlspecialchars($row['end_user']) ?></td>
                    <td><?= htmlspecialchars($row['department']) ?></td>
                    <td><?= htmlspecialchars($row['report_date']) ?></td>

                    <td class="d-flex gap-1 flex-wrap">

                      <button class="btn btn-info btn-sm" onclick="viewMIR(<?= $row['id'] ?>)">
                        <i class="bx bx-show"></i>
                      </button>

                      <a href="mir_print.php?id=<?= $row['id'] ?>" target="_blank" class="btn btn-primary btn-sm">
                        <i class="bx bx-printer"></i>
                      </a>

                      <a href="mir.php?edit_id=<?= $row['id'] ?>" class="btn btn-warning btn-sm">
                        <i class="bx bx-edit"></i>
                      </a>

                      <!-- <button onclick="deleteMIR(<?= $row['id'] ?>)" class="btn btn-danger btn-sm">
                        <i class="bx bx-trash"></i>
                      </button> -->

                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>

            </table>
          </div>

        </div>
      </div>

    </div>
  </div>

  <div class="layout-overlay layout-menu-toggle"></div>

  <!-- MODAL -->
  <div class="modal fade" id="mirModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">

        <div class="modal-header">
          <h5 class="modal-title">MIR Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body" id="mirContent">
          Loading...
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>

      </div>
    </div>
  </div>

  <!-- CORE JS -->
  <script src="../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../assets/vendor/libs/popper/popper.js"></script>
  <script src="../assets/vendor/js/bootstrap.js"></script>
  <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="../assets/vendor/js/menu.js"></script>
  <script src="../assets/js/main.js"></script>

  <script>
    function viewMIR(id) {
      fetch('mir_view.php?id=' + id)
        .then(res => res.text())
        .then(data => {
          document.getElementById('mirContent').innerHTML = data;
          new bootstrap.Modal(document.getElementById('mirModal')).show();
        });
    }

    function deleteMIR(id) {
      Swal.fire({
        title: 'Delete this MIR?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33'
      }).then(res => {
        if (res.isConfirmed) {
          window.location = 'mir.php?delete_id=' + id;
        }
      });
    }

    document.getElementById('search').addEventListener('keyup', function () {
      let v = this.value.toLowerCase();
      document.querySelectorAll("tbody tr").forEach(r => {
        r.style.display = r.innerText.toLowerCase().includes(v) ? '' : 'none';
      });
    });
  </script>

</body>

</html>