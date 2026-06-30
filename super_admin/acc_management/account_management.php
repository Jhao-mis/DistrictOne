<?php
include '../../db.php'; // your database connection file
include '../login_verification.php'; // your session file

$username = $_SESSION['username'];

// 🔍 Fetch user details
$query = $conn->prepare("SELECT id, profile_picture, cover_photo, department, firstname, middlename, lastname, email, role, last_activity 
                       FROM users WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->store_result();
$query->bind_result($user_id, $profile_picture, $cover_photo, $department, $firstname, $middlename, $lastname, $email, $user_role, $last_activity);
$query->fetch();
$query->close();

// Fetching all users' details including their profile pictures
$result = mysqli_query($conn, "SELECT id, firstname, middlename, lastname, username, email, department, role, profile_picture, isVerified, last_activity FROM users");

?>

<!DOCTYPE html>

<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/"
  data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>Dashboard - District One</title>

  <meta name="description" content="" />

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="../../assets/img/favicon/districtone.png" />

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
    rel="stylesheet" />

  <!-- Icons. Uncomment required icon fonts -->
  <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />

  <!-- Core CSS -->
  <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../../assets/css/demo.css" />

  <!-- Vendors CSS -->
  <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

  <!-- Calendar CSS -->
  <link rel="stylesheet" href="main.css">
  <link rel="stylesheet" href="acc.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/qtip2/3.0.3/jquery.qtip.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qtip2/3.0.3/jquery.qtip.min.js"></script>
  <!-- Helpers -->
  <script src="../../assets/vendor/js/helpers.js"></script>

  <script src="../../assets/js/config.js"></script>



</head>

<body>

  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
      <!-- Menu -->
      <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
        <div class="app-brand demo">
          <a href="super_admin_profile.php" class="app-brand-link">
            <span class="app-brand-logo demo">
              <svg width="25" viewBox="0 0 25 42" version="1.1" xmlns="http://www.w3.org/2000/svg"
                xmlns:xlink="http://www.w3.org/1999/xlink">
              </svg>
            </span>
            <img src="../assets/img/backgrounds/districtone.png" alt="CWD Logo" width="40" class="logo"
              style="margin-left: -20px; display: block;" />
            <span class="app-brand-text text-body fw-bolder">District One</span>
          </a>

          <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
            <i class="bx bx-chevron-left bx-sm align-middle"></i>
          </a>
        </div>

        <div class="menu-inner-shadow"></div>

        <ul class="menu-inner py-1">
          <!-- Dashboard -->
          <li class="menu-item">
            <a href="super_admin_profile.php" class="menu-link">
              <i class="menu-icon tf-icons bx bx-home-circle"></i>
              <div data-i18n="Analytics">My Profile</div>
            </a>
          </li>


          <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-dock-top"></i>
              <div data-i18n="Account">Account</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item">
                <a href="super_admin_settings.php" class="menu-link">
                  <div data-i18n="Account Settings">Account Settings</div>
                </a>
              </li>
              <li class="menu-item">
                <a href="super_admin_datasheet.php" class="menu-link">
                  <div data-i18n="Personal Data Sheet">Personal Data Sheet</div>
                </a>
              </li>

            </ul>
          </li>

          <!-- Announcements -->
          <li class="menu-item">
            <a href="../super_announcement.php" class="menu-link">
              <i class="bx bx-bell me-1"></i>
              <div data-i18n="Announcements" style="margin-left:9px;">Announcements</div>
            </a>
          </li>
          <!-- Calendar -->
          <li class="menu-item">
            <a href="../view_activities.php" class="menu-link">
              <i class="menu-icon tf-icons bx bx-calendar"></i>
              <div data-i18n="Calendar">Calendar Activity</div>
            </a>
          </li>

          <li class="menu-item">
            <a href="../room_reservation.php" class="menu-link">
              <i class="menu-icon tf-icons bx bx-calendar-event"></i>
              <div data-i18n="Ticketing">Room Reservation</div>
            </a>
          </li>

          <!--File SALN-->
          <li class="menu-item">
            <a href="../super_admin_saln.php" class="menu-link">
              <i class="menu-icon icon-base bx bx-detail"></i>
              <div data-i18n="saln">File SALN</div>
            </a>
          </li>

          <!--Leave Request-->
          <li class="menu-item">
            <a href="../super_admin_leaverequest.php" class="menu-link">
              <i class='bx bx-receipt' style="margin-right: 15px;"></i>
              <div data-i18n="Leave Request">Leave Request</div>
            </a>
          </li>

          <!-- Meeting -->
          <li class="menu-item">
            <a href="../super_admin_meeting.php" class="menu-link">
              <i class="menu-icon bx bx-group icon-sm me-1_5"></i>
              <div data-i18n="Meeting">Create Notice of Meeting</div>
            </a>
          </li>
          <!-- Ticketing -->
          <li class="menu-item">
            <a href="../superAdmin_dashboard.php" class="menu-link">
              <i class="menu-icon tf-icons bx bx-support"></i>
              <div data-i18n="Ticketing">IT Service Request</div>
            </a>
          </li>

          <!-- Dropdown Menu -->

          <li class="menu-item open active">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-menu me-1"></i>
              <div data-i18n="Admin">Admin Panel</div>
            </a>
            <!-- Add Announcements -->
            <ul class="menu-sub">
              <li class="menu-item">
                <a href="../super_admin_announcement.php" class="menu-link">
                  <div data-i18n="Announcements">Add Announcements</div>
                </a>
              </li>
              <!-- Add Calendar -->
              <li class="menu-item">
                <a href="../super_add_activity.php" class="menu-link">
                  <div data-i18n="Calendar">Add Calendar Activity</div>
                </a>
              </li>
              <!-- Ticketing -->
              <li class="menu-item">
                <a href="../super_admin_dashboard.php" class="menu-link">
                  <div data-i18n="Ticketing">IT Service Request</div>
                </a>
              </li>
              <!-- Room Reservation Request -->
              <li class="menu-item">
                <a href="../super_reservation_admin.php" class="menu-link">
                  <div data-i18n="Room Reservation">Room Reservation Requests</div>
                </a>
              </li>
              <!-- Room Reservation Request -->
              <li class="menu-item active">
                <a href="account_management.php" class="menu-link">
                  <div data-i18n="Account Management">Account Management</div>
                </a>
              </li>

            </ul>
          </li>


        </ul>
      </aside>

      <!-- / Menu -->

      <!-- Layout container -->
      <div class="layout-page">
        <!-- Navbar -->

        <nav
          class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
          id="layout-navbar">
          <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
            <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
              <i class="bx bx-menu bx-sm"></i>
            </a>
          </div>

          <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
            <!-- Search -->
            <div class="navbar-nav align-items-center">
              <div class="nav-item d-flex align-items-center">
                <i class="bx bx-search fs-4 lh-0"></i>
                <input type="text" class="form-control border-0 shadow-none" placeholder="Search..."
                  aria-label="Search..." />
              </div>
            </div>
            <!-- /Search -->

            <ul class="navbar-nav flex-row align-items-center ms-auto">
              <li class="nav-item lh-1 me-3">
                <div>
                  <?php echo htmlspecialchars($username); ?>
                </div>
              </li>


              <!-- User -->
              <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                  <div class="avatar avatar-online">
                    <img
                      src="<?php echo isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : '../../assets/img/avatars/1.png'; ?>"
                      alt="user-avatar" class="w-px-40 h-100 rounded-circle" />
                  </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li>
                    <a class="dropdown-item" href="#">
                      <div class="d-flex">
                        <div class="flex-shrink-0 me-3">
                          <div class="avatar avatar-online">
                            <img
                              src="<?php echo isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : '../../assets/img/avatars/1.png'; ?>"
                              alt="user-avatar" class="d-block rounded" height="100" width="100" id="uploadedAvatar" />
                          </div>
                        </div>
                        <div class="flex-grow-1">
                          <span class="fw-semibold d-block"><?php echo htmlspecialchars($username); ?></span>
                          <small class="text-muted"><?php echo htmlspecialchars($department); ?></small>
                        </div>
                      </div>

                    </a>
                  </li>
                  <li>
                    <div class="dropdown-divider"></div>
                  </li>
                  <li>
                    <a class="dropdown-item" href="super_admin_profile.php">
                      <i class="bx bx-user me-2"></i>
                      <span class="align-middle">My Profile</span>
                    </a>
                  </li>
                  <li>
                    <a class="dropdown-item" href="super_admin_settings.php">
                      <i class="bx bx-cog me-2"></i>
                      <span class="align-middle">Settings</span>
                    </a>
                  </li>
                  <li>
                    <div class="dropdown-divider"></div>
                  </li>
                  <li>
                    <a class="dropdown-item" href="../logout.php">
                      <i class="bx bx-power-off me-2"></i>
                      <span class="align-middle">Log Out</span>
                    </a>
                  </li>
                </ul>
              </li>
              <!--/ User -->
            </ul>
          </div>
        </nav>

        <!-- / Navbar -->
        <div class="content-wrapper">
          <!-- Content -->
          <div class="container-xxl flex-grow-1 container-p-y">







            <!-- MAIN CONTENT -->

            <div class="card border-0 shadow-sm p-4">
              <h5 class="mb-3 fw-semibold">User Accounts</h5>

              <!-- Search Bar -->
              <div class="mb-3">
                <input type="text" class="form-control" placeholder="Search accounts..." id="searchInput">
              </div>

              <!-- Table -->
              <div class="table-responsive">
                <table id="accountTable" class="table align-middle table-hover">
                  <thead class="table-light">
                    <tr>
                      <th>Profile</th>
                      <th>Name</th>
                      <th>Username</th>
                      <th>Email</th>
                      <th>Department</th>
                      <th>Role</th>
                      <th>Status</th>
                      <th class="text-center">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                      <tr>
                        <td class="d-flex align-items-center">
                          <img src="<?php
                          $profilePath = !empty($row['profile_picture']) ? 'uploads/' . $row['profile_picture'] : '../../assets/img/avatars/default_dp.jpg';
                          echo file_exists($profilePath) ? htmlspecialchars($profilePath) : '../../assets/img/avatars/default_dp.jpg';
                          ?>" alt="Profile" class="rounded-circle me-3"
                            style="width: 50px; height: 50px; object-fit: cover;">

                          <div>
                            <strong class="d-block text-capitalize">
                              <?= htmlspecialchars($row['firstname'] . ' ' . $row['middlename'] . ' ' . $row['lastname']) ?>
                            </strong>
                            <small class="text-muted"><?= htmlspecialchars($row['username']) ?></small>
                          </div>
                        </td>
                        <td class="text-capitalize">
                          <?= htmlspecialchars($row['firstname'] . ' ' . $row['middlename'] . ' ' . $row['lastname']) ?>
                        </td>
                        <td><?= htmlspecialchars($row['username']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td class="text-muted"><?= htmlspecialchars($row['department']) ?></td>
                        <td><?= htmlspecialchars($row['role']) ?></td>
                        <td>
                          <span
                            class="badge rounded-pill <?= $row['isVerified'] ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                            <?= $row['isVerified'] ? 'Verified' : 'Unverified' ?>
                          </span>
                        </td>
                        <td class="text-center">
                          <div class="btn-group" role="group">
                            <a href="edit_account.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                            <a href="deactivate_account.php?id=<?= $row['id'] ?>&status=<?= $row['isVerified'] ? '0' : '1' ?>"
                              class="btn btn-sm btn-warning">
                              <?= $row['isVerified'] ? 'Deactivate' : 'Activate' ?>
                            </a>
                            <a href="delete_account.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger"
                              onclick="return confirm('Are you sure you want to delete this account?')">
                              Delete
                            </a>
                          </div>
                        </td>
                      </tr>
                    <?php } ?>
                  </tbody>
                </table>
              </div>
            </div>

            <script>
              // Search functionality for the table
              document.getElementById('searchInput').addEventListener('input', function () {
                let filter = this.value.toLowerCase();
                let rows = document.getElementById('accountTable').getElementsByTagName('tr');
                for (let i = 1; i < rows.length; i++) {
                  let columns = rows[i].getElementsByTagName('td');
                  let name = columns[1].textContent.toLowerCase();
                  let username = columns[2].textContent.toLowerCase();
                  if (name.includes(filter) || username.includes(filter)) {
                    rows[i].style.display = '';
                  } else {
                    rows[i].style.display = 'none';
                  }
                }
              });
            </script>

          </div>
        </div>

        <div class="content-backdrop fade"></div>
      </div>
      <!-- Content wrapper -->
    </div>
    <!-- / Layout page -->
  </div>

  <!-- Overlay -->
  <div class="layout-overlay layout-menu-toggle"></div>
  </div>
  <!-- / Layout wrapper -->








  <!-- Core JS -->
  <!-- build:js assets/vendor/js/core.js -->
  <script src="../assets/vendor/js/bootstrap.js"></script>

  <script src="../assets/vendor/js/menu.js"></script>

  <!-- endbuild -->

  <!-- Vendors JS -->
  <script src="../assets/vendor/libs/apex-charts/apexcharts.js"></script>

  <!-- Main JS -->
  <script src="../assets/js/main.js"></script>

  <!-- Page JS -->
  <script src="../assets/js/dashboards-analytics.js"></script>

  <!-- Place this tag in your head or just before your close body tag. -->
  <script async defer src="https://buttons.github.io/buttons.js"></script>





</body>

</html>