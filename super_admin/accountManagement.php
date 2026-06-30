<?php
require '../vendor/autoload.php';
require '../db.php';
require 'login_verification.php';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Update current user activity
$username = $_SESSION['username'];
date_default_timezone_set('Asia/Manila');
$now = date('Y-m-d H:i:s');
$updateActivity = $conn->prepare("UPDATE users SET last_activity = ? WHERE username = ?");
$updateActivity->bind_param("ss", $now, $username);
$updateActivity->execute();
$updateActivity->close();

// Fetch logged-in user details
$query = $conn->prepare("
  SELECT id, profile_picture, cover_photo, department, firstname, middlename, lastname, email, role 
  FROM users 
  WHERE username = ?
");
$query->bind_param("s", $username);
$query->execute();
$query->store_result();
$query->bind_result($user_id, $profile_picture, $cover_photo, $department, $firstname, $middlename, $lastname, $email, $user_role);
$query->fetch();
$query->close();

// // Fetch all users OLD FETCH QUERY
// $result = $conn->query("
//   SELECT id, firstname, middlename, lastname, username, email, department, role, profile_picture, isVerified, last_activity 
//   FROM users
// ");

// Fetch all users 
$result = $conn->query("
  SELECT id, firstname, middlename, lastname, username, email, department, role, profile_picture, isVerified, last_activity 
  FROM users
  ORDER BY firstname ASC, lastname ASC, middlename ASC
");

// Prepare arrays and counters
$activeUsers = [];
$unverifiedUsers = [];
$activeCount = 0;
$unverifiedCount = 0;

while ($row = mysqli_fetch_assoc($result)) {
  if ($row['isVerified']) {
    $activeUsers[] = $row;
    $activeCount++;
  } else {
    $unverifiedUsers[] = $row;
    $unverifiedCount++;
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

  <title>Account Management</title>

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
  <link rel="stylesheet" href="./css/acc.css">

  <!-- Vendors CSS -->
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <link rel="stylesheet" href="../assets/vendor/libs/apex-charts/apex-charts.css" />

  <!-- Page CSS -->
  <link rel="stylesheet" href="../../assets/vendor/css/pages/app-calendar.css">
  <!-- Helpers -->
  <script src="../assets/vendor/js/helpers.js"></script>

  <script src="../assets/js/config.js"></script>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>


  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/qtip2/3.0.3/jquery.qtip.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qtip2/3.0.3/jquery.qtip.min.js"></script>

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
      <div class="card border-0 shadow-sm p-4">
        <h5 class="mb-3 fw-semibold">User Accounts</h5>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-3" id="userTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button"
              role="tab">
              Active Users (<?= $activeCount ?>)
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="unverified-tab" data-bs-toggle="tab" data-bs-target="#unverified" type="button"
              role="tab">
              Unverified (<?= $unverifiedCount ?>)
            </button>
          </li>
        </ul>

        <!-- Search Bar -->
        <div class="mb-3">
          <input type="text" class="form-control" placeholder="Search accounts..." id="searchInput">
        </div>

        <div class="tab-content" id="userTabContent">

          <!-- Active Users Tab -->
          <div class="tab-pane fade show active" id="active" role="tabpanel">
            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
              <table class="table align-middle table-hover">
                <thead class="table-light">
                  <tr>
                    <th>Profile</th>
                    <th>Email</th>
                    <th>Department</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Availability</th>
                    <th class="text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($activeUsers as $row): ?>
                    <tr>
                      <td class="d-flex align-items-center">
                        <?php
                        $baseDir = '../uploads/dp/';
                        $default = $baseDir . 'default_dp.jpg';
                        $profileFilename = !empty($row['profile_picture']) ? basename($row['profile_picture']) : '';
                        $relativePath = $baseDir . $profileFilename;
                        $absolutePath = __DIR__ . '/' . $relativePath;
                        $finalPath = (file_exists($absolutePath) && !is_dir($absolutePath) && !empty($profileFilename))
                          ? $relativePath : $default;
                        ?>
                        <img src="<?= htmlspecialchars($finalPath) ?>" class="rounded-circle me-3"
                          style="width: 50px; height: 50px; object-fit: cover;" alt="Profile">
                        <div>
                          <strong class="d-block text-capitalize">
                            <?= htmlspecialchars($row['firstname'] . ' ' . $row['middlename'] . ' ' . $row['lastname']) ?>
                          </strong>
                          <small class="text-muted"><?= htmlspecialchars($row['username']) ?></small>
                        </div>
                      </td>
                      <td><?= htmlspecialchars($row['email']) ?></td>
                      <td class="text-muted"><?= htmlspecialchars($row['department']) ?></td>
                      <td><?= htmlspecialchars($row['role']) ?></td>
                      <td><span class="badge bg-success-subtle text-success">Verified</span></td>
                      <td>
                        <?php
                        date_default_timezone_set('Asia/Manila');
                        $last_activity = strtotime($row['last_activity']);
                        $current_time = time();
                        $threshold = 10;
                        if ($current_time - $last_activity <= $threshold) {
                          echo '<span class="badge bg-success-subtle text-success">Online</span>';
                        } else {
                          echo '<span class="badge bg-secondary-subtle text-muted">Offline</span>';
                        }
                        ?>
                      </td>
                      <td class="text-center">
                        <div class="btn-group">
                          <a href="acc_management/edit_account.php?id=<?= $row['id'] ?>"
                            class="btn btn-sm btn-primary">Edit</a>
                          <a href="#" class="btn btn-sm btn-warning"
                            onclick="confirmActivateDeactivate(<?= $row['id'] ?>, 0)">Deactivate</a>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Unverified Users Tab -->
          <div class="tab-pane fade" id="unverified" role="tabpanel">
            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
              <table class="table align-middle table-hover">
                <thead class="table-light">
                  <tr>
                    <th>Profile</th>
                    <th>Email</th>
                    <th>Department</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Availability</th>
                    <th class="text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($unverifiedUsers as $row): ?>
                    <tr>
                      <td class="d-flex align-items-center">
                        <?php
                        $baseDir = '../uploads/dp/';
                        $default = $baseDir . 'default_dp.jpg';
                        $profileFilename = !empty($row['profile_picture']) ? basename($row['profile_picture']) : '';
                        $relativePath = $baseDir . $profileFilename;
                        $absolutePath = __DIR__ . '/' . $relativePath;
                        $finalPath = (file_exists($absolutePath) && !is_dir($absolutePath) && !empty($profileFilename))
                          ? $relativePath : $default;
                        ?>
                        <img src="<?= htmlspecialchars($finalPath) ?>" class="rounded-circle me-3"
                          style="width: 50px; height: 50px; object-fit: cover;" alt="Profile">
                        <div>
                          <strong class="d-block text-capitalize">
                            <?= htmlspecialchars($row['firstname'] . ' ' . $row['middlename'] . ' ' . $row['lastname']) ?>
                          </strong>
                          <small class="text-muted"><?= htmlspecialchars($row['username']) ?></small>
                        </div>
                      </td>
                      <td><?= htmlspecialchars($row['email']) ?></td>
                      <td class="text-muted"><?= htmlspecialchars($row['department']) ?></td>
                      <td><?= htmlspecialchars($row['role']) ?></td>
                      <td><span class="badge bg-danger-subtle text-danger">Unverified</span></td>
                      <td><span class="badge bg-secondary-subtle text-muted">Offline</span></td>
                      <td class="text-center">
                        <div class="btn-group">
                          <a href="acc_management/edit_account.php?id=<?= $row['id'] ?>"
                            class="btn btn-sm btn-primary">Edit</a>
                          <a href="#" class="btn btn-sm btn-success"
                            onclick="confirmActivateDeactivate(<?= $row['id'] ?>, 1)">Activate</a>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="content-backdrop fade"></div>
  </div>
  <!-- Success Modal -->
  <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title" id="successModalLabel">Success</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <?php if (isset($_SESSION['success'])) {
            echo $_SESSION['success'];
            unset($_SESSION['success']);
          } ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Error Modal -->
  <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="errorModalLabel">Error</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <?php if (isset($_SESSION['error'])) {
            echo $_SESSION['error'];
            unset($_SESSION['error']);
          } ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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

  <!-- CORE JS -->
  <script src="../assets/vendor/js/bootstrap.js"></script>
  <script src="../assets/vendor/js/menu.js"></script>
  <script src="../assets/js/main.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <!-- SweetAlert z-index fix so sidebar is darkened properly -->
  <style>
    .swal2-container {
      z-index: 2000 !important;
    }
  </style>

  <script>
    // Show bootstrap modals if PHP set success/error (keeps existing modal markup)
    document.addEventListener("DOMContentLoaded", function () {
      <?php if (isset($_SESSION['success'])): ?>
        var successModal = new bootstrap.Modal(document.getElementById('successModal'));
        successModal.show();
      <?php endif; ?>

      <?php if (isset($_SESSION['error'])): ?>
        var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
        errorModal.show();
      <?php endif; ?>
    });
  </script>

  <script>
    // Confirm Activate/Deactivate using SweetAlert
    function confirmActivateDeactivate(id, status) {
      const action = status === 1 ? 'Activate' : 'Deactivate';
      Swal.fire({
        title: `Are you sure you want to ${action} this account?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: `Yes, ${action} it!`,
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#FFA500',
        cancelButtonColor: '#FF3737'
      }).then((result) => {
        if (result.isConfirmed) {
          // redirect to server action
          window.location.href = `acc_management/deactivate_account.php?id=${id}&status=${status}&redirect=1`;
        }
      });
    }

    // Show success alert after status change via URL param
    window.addEventListener('DOMContentLoaded', () => {
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('status_changed')) {
        const changedStatus = urlParams.get('status_changed') === '1' ? 'activated' : 'deactivated';
        Swal.fire({
          title: 'Success!',
          text: `The account has been ${changedStatus}.`,
          icon: 'success',
          confirmButtonColor: '#3085d6'
        });
      }
    });
  </script>

  <script>
    // Unified search input for both Active and Unverified tables
    (function () {
      const searchInput = document.getElementById("searchInput");
      if (!searchInput) return;

      searchInput.addEventListener("input", function () {
        const value = this.value.trim().toLowerCase();
        // iterate all table body rows on page
        document.querySelectorAll(".card table tbody").forEach(tbody => {
          tbody.querySelectorAll("tr").forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(value) ? "" : "none";
          });
        });
      });
    })();
  </script>

  <script>
    // Sortable table headers (numeric-aware). Skips columns named "Action" / "Actions".
    (function () {
      // Attach to all tables inside this page
      document.querySelectorAll(".card table").forEach(table => {
        const ths = table.querySelectorAll("thead th");
        ths.forEach((th, index) => {
          const headerText = th.textContent.trim().toLowerCase();
          if (headerText === "" || headerText.includes("action")) return; // skip if blank or action column

          th.style.cursor = "pointer";
          th.classList.add("sortable");

          let asc = true;
          th.addEventListener("click", () => {
            const tbody = table.tBodies[0];
            if (!tbody) return;
            const rows = Array.from(tbody.querySelectorAll("tr"));

            const dir = asc ? 1 : -1;
            rows.sort((a, b) => {
              const aCell = (a.cells[index] && a.cells[index].innerText) ? a.cells[index].innerText.trim() : "";
              const bCell = (b.cells[index] && b.cells[index].innerText) ? b.cells[index].innerText.trim() : "";

              // Try numeric compare
              const aNum = parseFloat(aCell.replace(/[^0-9.\-]+/g, ""));
              const bNum = parseFloat(bCell.replace(/[^0-9.\-]+/g, ""));
              const aIsNum = !isNaN(aNum) && aCell !== "";
              const bIsNum = !isNaN(bNum) && bCell !== "";

              if (aIsNum && bIsNum) {
                return (aNum - bNum) * dir;
              }

              // Fallback: string compare (case-insensitive)
              return aCell.toLowerCase().localeCompare(bCell.toLowerCase()) * dir;
            });

            // Re-attach sorted rows
            rows.forEach(r => tbody.appendChild(r));

            // Toggle sort direction and update header classes
            asc = !asc;
            ths.forEach(h => h.classList.remove("asc", "desc"));
            th.classList.add(asc ? "asc" : "desc");
          });
        });
      });
    })();
  </script>

</body>

</html>