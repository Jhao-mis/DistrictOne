<?php
session_start();
require '../vendor/autoload.php';
require 'login_verification.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require '../db.php';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error)
  die("DB Error");

// =======================
// FETCH USER
// =======================
$username = $_SESSION['username'] ?? '';

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

$mir_list = $conn->query("SELECT * FROM mir_reports ORDER BY id DESC");

?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/"
  data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
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

  <style>
    :root {
      --mir-primary: #007bff;
      --mir-border: #e4e6ef;
      --mir-muted: #6c757d;
    }

    .swal2-container {
      z-index: 99999 !important;
    }

    .swal2-popup {
      z-index: 100000 !important;
    }

    .mir-card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .75rem;
      flex-wrap: wrap;
    }

    .mir-search-wrap {
      position: relative;
    }

    .mir-search-wrap i {
      position: absolute;
      left: .75rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--mir-muted);
    }

    .mir-search-wrap input {
      padding-left: 2.2rem;
    }

    .mir-table-scroll {
      max-height: 65vh;
      overflow-y: auto;
      border: 1px solid var(--mir-border);
      border-radius: 8px;
    }

    .mir-table-scroll thead th {
      position: sticky;
      top: 0;
      z-index: 1;
    }

    .mir-table-scroll table {
      margin-bottom: 0;
    }

    .mir-actions {
      display: flex;
      gap: .35rem;
      flex-wrap: wrap;
    }

    .mir-actions .btn {
      min-width: 36px;
    }

    .mir-empty {
      text-align: center;
      padding: 2.5rem 1rem;
      color: var(--mir-muted);
    }

    .mir-empty i {
      font-size: 2rem;
      display: block;
      margin-bottom: .5rem;
    }

    #mirContent .spinner-border {
      width: 2rem;
      height: 2rem;
    }

    /* =======================
       MOBILE RESPONSIVE FIXES
       ======================= */
    @media (max-width: 767.98px) {
      .container-xxl {
        padding-left: .75rem;
        padding-right: .75rem;
      }

      .card-header.mir-card-header {
        flex-direction: column;
        align-items: stretch;
      }

      .card-header.mir-card-header .btn {
        width: 100%;
        justify-content: center;
      }
    }

    /* Below md: convert the table into a stacked card list */
    @media (max-width: 767.98px) {
      .mir-table-scroll {
        max-height: none;
        border: none;
        overflow: visible;
      }

      .mir-table-scroll table,
      .mir-table-scroll thead,
      .mir-table-scroll tbody,
      .mir-table-scroll tr,
      .mir-table-scroll td {
        display: block;
        width: 100%;
      }

      .mir-table-scroll thead {
        display: none;
      }

      .mir-table-scroll tbody tr {
        border: 1px solid var(--mir-border);
        border-radius: 10px;
        margin-bottom: .75rem;
        padding: .75rem;
        background: #fff;
      }

      .mir-table-scroll tbody tr:hover {
        background: #fff;
      }

      .mir-table-scroll td {
        border: none !important;
        padding: .3rem 0 !important;
      }

      .mir-table-scroll td::before {
        content: attr(data-label);
        display: block;
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: var(--mir-muted);
        margin-bottom: .1rem;
      }

      .mir-table-scroll td.mir-actions-cell {
        padding-top: .5rem !important;
        border-top: 1px solid var(--mir-border) !important;
        margin-top: .4rem;
      }

      .mir-table-scroll td.mir-actions-cell::before {
        content: '';
        margin: 0;
      }

      .mir-actions .btn {
        flex: 1;
      }
    }

    @media (max-width: 575.98px) {
      .modal-dialog {
        margin: .5rem;
      }
    }
  </style>
</head>

<body>

  <?php
  // ALERTS
  if (isset($_SESSION['error'])) {
    echo "<script>document.addEventListener('DOMContentLoaded', () => Swal.fire('Error','" . addslashes($_SESSION['error']) . "','error'));</script>";
    unset($_SESSION['error']);
  }
  if (isset($_SESSION['success'])) {
    echo "<script>document.addEventListener('DOMContentLoaded', () => Swal.fire('Success','" . addslashes($_SESSION['success']) . "','success'));</script>";
    unset($_SESSION['success']);
  }
  ?>

  <?php
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
        <div class="card-header mir-card-header">
          <h5 class="mb-0">MIR List</h5>

          <a href="mir.php" class="btn btn-primary btn-sm">
            <i class="bx bx-plus"></i> New MIR
          </a>
        </div>

        <div class="card-body">

          <div class="mir-search-wrap mb-3">
            <i class='bx bx-search'></i>
            <input type="text" id="search" class="form-control" placeholder="Search by report no, end user, department...">
          </div>

          <div class="mir-table-scroll">
            <table class="table table-hover table-bordered align-middle">
              <thead class="table-light">
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
                <?php if ($mir_list->num_rows === 0): ?>
                  <tr>
                    <td colspan="6">
                      <div class="mir-empty">
                        <i class='bx bx-file-blank'></i>
                        No MIR reports yet. Click "New MIR" to create one.
                      </div>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php $i = 1;
                  while ($row = $mir_list->fetch_assoc()): ?>
                    <tr>
                      <td data-label="#"><?= $i++ ?></td>
                      <td data-label="Report No"><strong><?= htmlspecialchars($row['report_no']) ?></strong></td>
                      <td data-label="End User"><?= htmlspecialchars($row['end_user']) ?></td>
                      <td data-label="Department"><?= htmlspecialchars($row['department']) ?></td>
                      <td data-label="Date"><?= htmlspecialchars($row['report_date']) ?></td>

                      <td class="mir-actions-cell">
                        <div class="mir-actions">
                          <button class="btn btn-info btn-sm" onclick="viewMIR(<?= (int) $row['id'] ?>)" title="View">
                            <i class="bx bx-show"></i>
                          </button>

                          <a href="mir_print.php?id=<?= (int) $row['id'] ?>" target="_blank" class="btn btn-primary btn-sm" title="Print">
                            <i class="bx bx-printer"></i>
                          </a>

                          <a href="mir.php?edit_id=<?= (int) $row['id'] ?>" class="btn btn-warning btn-sm" title="Edit">
                            <i class="bx bx-edit"></i>
                          </a>

                          <!-- <button onclick="deleteMIR(<?= (int) $row['id'] ?>)" class="btn btn-danger btn-sm" title="Delete">
                            <i class="bx bx-trash"></i>
                          </button> -->
                        </div>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php endif; ?>
              </tbody>

            </table>
          </div>

          <div id="noResults" class="mir-empty d-none">
            <i class='bx bx-search-alt'></i>
            No matching reports found.
          </div>

        </div>
      </div>

    </div>
  </div>

  <div class="layout-overlay layout-menu-toggle"></div>

  <!-- MODAL -->
  <div class="modal fade" id="mirModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
      <div class="modal-content">

        <div class="modal-header">
          <h5 class="modal-title">MIR Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body" id="mirContent">
          <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status"></div>
            <div class="mt-2 text-muted">Loading...</div>
          </div>
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
      const content = document.getElementById('mirContent');
      content.innerHTML = `
        <div class="text-center py-4">
          <div class="spinner-border text-primary" role="status"></div>
          <div class="mt-2 text-muted">Loading...</div>
        </div>`;

      new bootstrap.Modal(document.getElementById('mirModal')).show();

      fetch('mir_view.php?id=' + id)
        .then(res => res.text())
        .then(data => {
          content.innerHTML = data;
        })
        .catch(() => {
          content.innerHTML = '<div class="text-danger text-center py-4">Failed to load MIR details.</div>';
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
      let visibleCount = 0;

      document.querySelectorAll(".mir-table-scroll tbody tr").forEach(r => {
        const match = r.innerText.toLowerCase().includes(v);
        r.style.display = match ? '' : 'none';
        if (match) visibleCount++;
      });

      document.getElementById('noResults').classList.toggle('d-none', visibleCount !== 0);
    });
  </script>

</body>

</html>