<?php
include '../db.php';

$mis_id = $_SESSION["user_id"]; // Logged-in MIS user

// 🧮 Count Assigned Tickets (for this MIS user)
$assignedCountQuery = $conn->query("
    SELECT COUNT(*) AS total 
    FROM tickets 
    WHERE assigned_to = $mis_id AND admin_approved = 1
");
$assignedTicketCount = $assignedCountQuery->fetch_assoc()['total'] ?? 0;

// 🧮 Count Pending Room Reservations
$reservationCountQuery = $conn->query("
    SELECT COUNT(*) AS total 
    FROM room_reservations 
    WHERE status = 'Pending'
");
$reservationCount = $reservationCountQuery->fetch_assoc()['total'] ?? 0;

// 🧮 Count Unverified Accounts (if you want to show on MIS too)
$unverifiedCountQuery = $conn->query("
    SELECT COUNT(*) AS total 
    FROM users 
    WHERE isVerified = 0
");
$unverifiedCount = $unverifiedCountQuery->fetch_assoc()['total'] ?? 0;
?>


<!-- Layout wrapper -->
<div class="layout-wrapper layout-content-navbar">
  <div class="layout-container">
    <!-- Menu -->
    <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
      <div class="app-brand demo">
        <a href="../modules/profile.php" class="app-brand-link">
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


        <?php
        // Detect current page
        $currentPage = basename($_SERVER['PHP_SELF']);
        ?>

        <li
          class="menu-item <?= ($currentPage == 'profile.php' || $currentPage == 'profileTeams.php') ? 'active' : '' ?>">
          <a href="../modules/profile.php" class="menu-link">
            <i class="menu-icon tf-icons bx bx-home-circle"></i>
            <div data-i18n="Analytics">My Profile</div>
          </a>
        </li>

        <li class="menu-item <?= (
          $currentPage == 'accountSettings.php' ||
          $currentPage == 'personaldataSheet.php' ||
          $currentPage == 'payroll.php'
        ) ? 'active open' : '' ?>">

          <a href="javascript:void(0);" class="menu-link menu-toggle">
            <i class="menu-icon tf-icons bx bx-dock-top"></i>
            <div data-i18n="Account">Account</div>
          </a>

          <ul class="menu-sub">
            <li class="menu-item <?= ($currentPage == 'accountSettings.php') ? 'active' : '' ?>">
              <a href="../modules/accountSettings.php" class="menu-link">
                <div data-i18n="Account Settings">Account Settings</div>
              </a>
            </li>

            <li class="menu-item <?= ($currentPage == 'maintenance_msg.php') ? 'active' : '' ?>">
              <a href="../modules/personaldataSheet.php" class="menu-link">
                <div data-i18n="Personal Data Sheet">Personal Data Sheet</div>
              </a>
            </li>

            <!-- My Payslip -->
            <li class="menu-item <?= ($currentPage == 'payroll.php') ? 'active' : '' ?>">
              <a href="../modules/payroll.php" class="menu-link">
                <div data-i18n="My Payslip">My Payslip</div>
              </a>
            </li>
          </ul>
        </li>

        <li class="menu-item <?= ($currentPage == 'announcement.php') ? 'active' : '' ?>">
          <a href="../modules/announcement.php" class="menu-link">
            <i class="bx bx-bell me-1"></i>
            <div data-i18n="Announcements" style="margin-left:9px;">Announcements</div>
          </a>
        </li>

        <li class="menu-item <?= ($currentPage == 'calendar.php') ? 'active' : '' ?>">
          <a href="../modules/calendar.php" class="menu-link">
            <i class="menu-icon tf-icons bx bx-calendar"></i>
            <div data-i18n="Calendar">Calendar Activity</div>
          </a>
        </li>

        <li class="menu-item <?= ($currentPage == 'roomReservation.php') ? 'active' : '' ?>">
          <a href="../modules/roomReservation.php" class="menu-link">
            <i class="menu-icon tf-icons bx bx-calendar-event"></i>
            <div data-i18n="Ticketing">Room Reservation</div>
          </a>
        </li>

        <li class="menu-item <?= ($currentPage == 'fileSaln.php') ? 'active' : '' ?>">
          <a href="../modules/fileSaln.php" class="menu-link">
            <i class="menu-icon icon-base bx bx-detail"></i>
            <div data-i18n="saln">File SALN</div>
          </a>
        </li>

        <li class="menu-item <?= ($currentPage == 'leaveRequest.php') ? 'active' : '' ?>">
          <a href="../modules/leaveRequest.php" class="menu-link">
            <i class='bx bx-receipt' style="margin-right: 15px;"></i>
            <div data-i18n="Leave Request">Leave Request</div>
          </a>
        </li>

        <li class="menu-item <?= ($currentPage == 'noticeofMeeting.php') ? 'active' : '' ?>">
          <a href="../modules/noticeofMeeting.php" class="menu-link">
            <i class="menu-icon bx bx-group icon-sm me-1_5"></i>
            <div data-i18n="Meeting">Create Notice of Meeting</div>
          </a>
        </li>

        <li class="menu-item <?= ($currentPage == 'serviceRequest.php') ? 'active' : '' ?>">
          <a href="../modules/serviceRequest.php" class="menu-link">
            <i class="menu-icon tf-icons bx bx-support"></i>
            <div data-i18n="Ticketing">IT Service Request</div>
          </a>
        </li>

        <hr style="width: 100%; height: 3px; border: 2px black;">

        <li class="menu-item <?= ($currentPage == 'ticketAssign.php') ? 'active' : '' ?>">
          <a href="../mis/ticketAssign.php" class="menu-link">
            <i class="menu-icon tf-icons bx bx-task"></i>
            <div data-i18n="Ticketing">
              Ticket Assignment<?= $assignedTicketCount > 0 ? " ({$assignedTicketCount})" : "" ?>
            </div>
          </a>
        </li>

        <li class="menu-item <?= ($currentPage == 'mir.php') ? 'active' : '' ?>">
          <a href="../mis/mir.php" class="menu-link">
            <i class="menu-icon tf-icons bx bx-wrench"></i>
            <div data-i18n="Ticketing">
              Machine Inspection Report
            </div>
          </a>
        </li>


      </ul>
    </aside>

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
                    src="<?php echo isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : '../assets/img/avatars/1.png'; ?>"
                    alt="user-avatar" class="w-px-40 h-100 rounded-circle" />
                </div>
              </a>
              <ul class="dropdown-menu dropdown-menu-end">
                <li>
                  <a class="dropdown-item" href="../modules/profile.php">
                    <div class="d-flex">
                      <div class="flex-shrink-0 me-3">
                        <div class="avatar avatar-online">
                          <img
                            src="<?php echo isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : '../assets/img/avatars/1.png'; ?>"
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
                  <a class="dropdown-item" href="../modules/profile.php">
                    <i class="bx bx-user me-2"></i>
                    <span class="align-middle">My Profile</span>
                  </a>
                </li>
                <li>
                  <a class="dropdown-item" href="../modules/accountSettings.php">
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