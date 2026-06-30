<?php
require '../vendor/autoload.php';
include '../db.php';           // ✅ load $pdo and $conn FIRST
require 'login_verification.php'; // ✅ verify role AFTER DB is loaded

//Count Pending Tickets
$approveCountQuery = $conn->query("SELECT COUNT(*) AS total FROM tickets WHERE status = 'Pending' AND admin_approved = 0");
$approveCount = $approveCountQuery->fetch_assoc()['total'] ?? 0;

//Count Pending Room Reservations
$reservationCountQuery = $conn->query("SELECT COUNT(*) AS total FROM room_reservations WHERE status = 'Pending'");
$reservationCount = $reservationCountQuery->fetch_assoc()['total'] ?? 0;

//Count Unverified Accounts
$unverifiedCountQuery = $conn->query("SELECT COUNT(*) AS total FROM users WHERE isVerified = 0");
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

            <li class="menu-item <?= ($currentPage == 'personaldataSheet.php') ? 'active' : '' ?>">
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
        <!-- ADMIN PANEL -->
        <li
          class="menu-item <?= ($currentPage == 'monitoring.php' || $currentPage == 'ticketRequest.php' || $currentPage == 'approveTicket.php' || $currentPage == 'addAnnouncement.php' || $currentPage == 'addEvents.php' || $currentPage == 'approveRoom.php' || $currentPage == 'accountManagement.php' || $currentPage == 'payrollmng.php') ? 'active open' : '' ?>">
          <a href="javascript:void(0);" class="menu-link menu-toggle">
            <i class="menu-icon tf-icons bx bx-menu me-1"></i>
            <div data-i18n="Admin">Admin Panel</div>
          </a>

          <ul class="menu-sub">
            <li class="menu-item <?= ($currentPage == 'monitoring.php') ? 'active' : '' ?>">
              <a href="../super_admin/monitoring.php" class="menu-link">
                <div data-i18n="Ticketing">Live Report</div>
              </a>
            </li>
            <li class="menu-item <?= ($currentPage == 'ticketRequest.php') ? 'active' : '' ?>">
              <a href="../super_admin/ticketRequest.php" class="menu-link">
                <div data-i18n="Ticketing">Ticket Request</div>
              </a>
            </li>
            <li class="menu-item <?= ($currentPage == 'approveTicket.php') ? 'active' : '' ?>">
              <a href="../super_admin/approveTicket.php" class="menu-link">
                <div data-i18n="Calendar">Ticket Approval (<?= $approveCount ?>)</div>
              </a>
            </li>
            <li class="menu-item <?= ($currentPage == 'addAnnouncement.php') ? 'active' : '' ?>">
              <a href="../super_admin/addAnnouncement.php" class="menu-link">
                <div data-i18n="Announcements">Add Announcements</div>
              </a>
            </li>
            <li class="menu-item <?= ($currentPage == 'addEvents.php') ? 'active' : '' ?>">
              <a href="../super_admin/addEvents.php" class="menu-link">
                <div data-i18n="Calendar">Add Calendar Activity</div>
              </a>
            </li>

            <li class="menu-item <?= ($currentPage == 'approveRoom.php') ? 'active' : '' ?>">
              <a href="../super_admin/approveRoom.php" class="menu-link">
                <div data-i18n="Room Reservation">
                  Room Reservation Requests<?= $reservationCount > 0 ? " ({$reservationCount})" : "" ?>
                </div>
              </a>
            </li>
            <li class="menu-item <?= ($currentPage == 'payrollmng.php') ? 'active' : '' ?>">
              <a href="../super_admin/payrollmng.php" class="menu-link">
                <div data-i18n="Room Reservation">
                  Payroll Management
                </div>
              </a>
            </li>
            <li class="menu-item <?= ($currentPage == 'accountManagement.php') ? 'active' : '' ?>">
              <a href="../super_admin/accountManagement.php" class="menu-link">
                <div data-i18n="Account Management">
                  Account Management<?= $unverifiedCount > 0 ? " ({$unverifiedCount})" : "" ?>
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

            <!-- 🔔 Notification Icon -->
            <li class="nav-item dropdown me-3">
              <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                <i class="bx bx-bell bx-sm"></i>

                <span id="notifBadge" class="badge bg-danger rounded-pill badge-notifications"
                  style="display:none; position:absolute; top:2px; right:2px;">
                  0
                </span>
              </a>

              <!-- Dropdown Notification Menu -->
              <ul class="dropdown-menu dropdown-menu-end p-0 shadow"
                style="width: 450px; max-width: 95vw; border-radius: 14px; overflow-x: hidden !important;">

                <!-- Header -->
                <li class="bg-primary text-white fw-bold py-3 px-4 rounded-top" style="font-size: 16px;">
                  <i class="bx bx-bell me-2"></i> Notifications
                </li>

                <!-- Notification List -->
                <div id="notifList" style="
                  max-height: 450px; 
                  overflow-y: auto; 
                  overflow-x: hidden;
                    ">
                  <!-- Default -->
                  <li class="dropdown-item text-center py-4 text-muted" id="noNotif">
                    <i class='bx bx-info-circle fs-2 d-block mb-2'></i>
                    No new notifications
                  </li>
                </div>
              </ul>
            </li>



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

      <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

      <script>
        function loadNotifications() {
          $.ajax({
            url: "../super_admin/get_notifications.php",
            method: "GET",
            dataType: "json",
            success: function (data) {

              // Update badge
              if (data.count > 0) {
                $("#notifBadge").text(data.count).show();
              } else {
                $("#notifBadge").hide();
              }

              let notifHTML = "";

              if (data.items.length === 0) {
                notifHTML = `
                <li class="dropdown-item text-center py-4 text-muted">
                    <i class='bx bx-info-circle fs-2 d-block mb-2'></i>
                    No new notifications
                </li>`;
              } else {
                data.items.forEach(function (item) {
                  // Choose icon based on type
                  let iconClass = item.type === "Ticket Request" ? "bx-message-square-detail" : "bx-calendar-event";

                  notifHTML += `
<li class="dropdown-item py-3" style="
    border-bottom: 1px solid #eee;
    white-space: normal !important;
    word-wrap: break-word !important;
    word-break: break-word !important;
">
  <a href="${item.link}" 
     class="d-flex align-items-start text-decoration-none"
     style="white-space: normal !important; width:100%;">

    <div class="avatar flex-shrink-0 me-3">
      <span class="avatar-initial rounded-circle bg-primary text-white"
            style="width: 42px; height: 42px; display:flex; 
                   align-items:center; justify-content:center; font-size:20px;">
        <i class='bx ${iconClass}'></i>
      </span>
    </div>

    <div class="flex-grow-1" style="white-space: normal !important;">

      <!-- Type -->
      <div class="fw-semibold" style="font-size: 15px; line-height: 18px;">
        ${item.type}
      </div>

      <!-- Subject -->
      <div class="text-muted mt-1" style="
          font-size: 13px;
          white-space: normal !important;
          word-wrap: break-word !important;
          word-break: break-word !important;
      ">
        ${item.subject}
      </div>

      <!-- Requested by -->
      <div class="text-muted mt-1" style="font-size: 12px;">
        <i class="bx bx-user me-1"></i>Requested by: ${item.firstname} ${item.lastname}
      </div>

      <!-- Time -->
      <div class="text-muted mt-1" style="font-size: 11px;">
        <i class="bx bx-time-five me-1"></i>${item.time}
      </div>

    </div>
  </a>
</li>`;
                });
              }

              $("#notifList").html(notifHTML);
            }
          });
        }

        // Auto refresh every 5 seconds
        setInterval(loadNotifications, 5000);
        loadNotifications();
      </script>