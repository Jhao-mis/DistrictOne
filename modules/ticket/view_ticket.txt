<?php
// Start session & connect to database
session_start();
require '../../vendor/autoload.php';
include '../../db.php';


$alert = null;

if (isset($_SESSION['alert'])) {
    $alert = $_SESSION['alert'];
    unset($_SESSION['alert']); // Clear after showing once
}


$username = $_SESSION['username'];

date_default_timezone_set('Asia/Manila'); // Set timezone to your local
$now = date('Y-m-d H:i:s');
$updateActivity = $conn->prepare("UPDATE users SET last_activity = ? WHERE username = ?");
$updateActivity->bind_param("ss", $now, $username);
$updateActivity->execute();
$updateActivity->close();


// ✅ Fetch user details
$query = $conn->prepare("SELECT id, profile_picture, cover_photo, department, firstname, middlename, lastname, email 
                         FROM users WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->store_result();
$query->bind_result($user_id, $profile_picture, $cover_photo, $department, $firstname, $middlename, $lastname, $email);
$query->fetch();
$query->close();

// ✅ Ensure user_id is set properly
if (!$user_id) {
    die("❌ Error: User details not found.");
}
$_SESSION['user_id'] = $user_id;

// Check if 'ticket_id' is provided
if (!isset($_GET['ticket_id']) || empty($_GET['ticket_id'])) {
    die("<p style='color:red; text-align:center;'>❌ Error: Ticket ID is missing or invalid.</p>");
}

$ticket_id = intval($_GET['ticket_id']); // Ensure ticket_id is an integer

// ✅ Adjusted query: handles tickets without "assigned_to"
$query = "SELECT tickets.*, 
                 CONCAT(users.firstname, ' ', IFNULL(users.middlename, ''), ' ', users.lastname) AS mis_name, 
                 users.department AS mis_department 
          FROM tickets 
          LEFT JOIN users ON tickets.assigned_to = users.id 
          WHERE tickets.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$result = $stmt->get_result();

// 🛑 Check if ticket exists
if ($result->num_rows === 0) {
    die("<p style='color:red; text-align:center;'>❌ Error: No ticket found with ID: $ticket_id</p>");
}

$ticket = $result->fetch_assoc();

// 🕒 Fetch ticket history with timestamps
$history_query = "SELECT ticket_history.*, 
                         CONCAT(users.firstname, ' ', IFNULL(users.middlename, ''), ' ', users.lastname) AS action_by 
                  FROM ticket_history 
                  LEFT JOIN users ON ticket_history.action_by = users.id 
                  WHERE ticket_history.ticket_id = ? 
                  ORDER BY ticket_history.timestamp ASC";
$stmt = $conn->prepare($history_query);
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$history_result = $stmt->get_result();

// ✅ Close everything
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>

<html
  lang="en"
  class="light-style layout-menu-fixed"
  dir="ltr"
  data-theme="theme-default"
  data-assets-path="../../assets/"
  data-template="vertical-menu-template-free"
>
  <head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"
    />

    <title>Dashboard - District One</title>

    <meta name="description" content="" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../assets/img/favicon/districtone.png" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
      rel="stylesheet"
    />

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

    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="../../assets/js/config.js"></script>

    <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      padding: 20px;
      height: 100vh;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: #f4f8fc;
      color: #333;
      overflow: hidden;
    }

    .main-container {
      display: flex;
      gap: 20px;
      height: calc(100vh - 100px);
      max-width: 1300px;
      margin: auto;
      overflow: hidden;
    }

    .column {
      background: #ffffff;
      padding: 25px;
      border-radius: 12px;
      box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    .column h3 {
      font-size: 22px;
      color: #1c4e80;
      margin-bottom: 20px;
      border-bottom: 2px solid #e0e0e0;
      padding-bottom: 10px;
      position: sticky;
      top: 0;
      background: white;
      z-index: 1;
    }

    .ticket-detail p {
      margin: 12px 0;
      font-size: 16px;
      line-height: 1.6;
    }

    .badge {
      padding: 6px 12px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: bold;
      display: inline-block;
      text-transform: uppercase;
    }

    .badge-pending {
      background-color: #ffc107;
      color: white;
    }

    .badge-in-progress {
      background-color: #17a2b8;
      color: white;
    }

    .badge-resolved {
      background-color: #28a745;
      color: white;
    }

    .badge-cancelled {
      background-color: #dc3545;
      color: white;
    }

    .badge-mis {
      background-color: #6c757d;
      color: white;
    }

    label {
      margin-top: 15px;
      font-weight: 600;
      font-size: 16px;
    }

    select, textarea {
      width: 100%;
      padding: 12px;
      margin-top: 5px;
      margin-bottom: 20px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 16px;
    }

    textarea {
    resize: none; /* Disable resizing */
    height: 260px; /* Set a fixed height for the description textarea */
    font-size: 16px;
    line-height: 1.6;
    color: #333;
    background-color: #f9f9f9;
    border: 1px solid #ccc;
    border-radius: 8px;
    padding: 15px;
    box-sizing: border-box;
    overflow-y: auto; /* Enable vertical scroll when content overflows */
}

textarea:focus {
    border-color: #007bff; /* Focused border color */
    outline: none;
    background-color: #fff; /* Slightly different background when focused */
}


    .btn-submit {
      padding: 14px;
      background-color: #007bff;
      color: white;
      border: none;
      border-radius: 8px;
      font-weight: bold;
      cursor: pointer;
      transition: 0.3s;
      width: 100%;
    }

    .btn-submit:hover {
      background-color: #0056b3;
    }

    .btn-back {
      margin-top: 25px;
      padding: 12px;
      background-color: #6c757d;
      color: white;
      text-align: center;
      text-decoration: none;
      border-radius: 6px;
      font-weight: bold;
      display: inline-block;
      width: 100%;
    }

    /* History Logs */
    .history-list {
      list-style: none;
      padding: 0;
      margin: 0;
      overflow-y: auto;
      flex-grow: 1;
      max-height: 100%;
      padding-right: 10px;
    }

    .history-list li {
      border-left: 4px solid #1c4e80;
      padding-left: 12px;
      margin-bottom: 15px;
      background-color: #f1f7fd;
      padding: 12px 18px;
      border-radius: 6px;
      font-size: 15px;
      line-height: 1.6;
    }

    .history-list li:last-child {
      margin-bottom: 0;
    }
</style>


</head>
<body>


<!-- Layout wrapper -->
<!-- Layout wrapper -->
<div class="layout-wrapper layout-content-navbar">
  <div class="layout-container">
    <!-- Menu -->
    <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
      <div class="app-brand demo">
        <a href="../user_profile.php" class="app-brand-link">
          <span class="app-brand-logo demo">
            <svg
              width="25"
              viewBox="0 0 25 42"
              version="1.1"
              xmlns="http://www.w3.org/2000/svg"
              xmlns:xlink="http://www.w3.org/1999/xlink"
            >
            </svg>
          </span>
          <img src="../../assets/img/backgrounds/districtone.png" alt="CWD Logo" width="40" class="logo" style="margin-left: -20px; display: block;"/>
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
              <a href="../user_profile.php" class="menu-link">
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
                  <a href="../settings-my-profile.php" class="menu-link">
                    <div data-i18n="Account Settings">Account Settings</div>
                  </a>
                </li>
                <li class="menu-item">
                  <a href="../datasheet.php" class="menu-link">
                    <div data-i18n="Personal Data Sheet">Personal Data Sheet</div>
                  </a>
                </li>

              
              </ul>
            </li>
             <!-- Announcements -->
             <li class="menu-item">
            <a href="../client_announcement.php" class="menu-link">
            <i class="bx bx-bell me-1"></i>
            <div data-i18n="Announcements" style="margin-left:9px;">Announcements</div>
            </a>
            </li>
            <!-- Calendar -->
            <li class="menu-item">
              <a href="../view_activities.php" class="menu-link">
                <i class="menu-icon tf-icons bx bx-calendar"></i>
                <div data-i18n="Calendar">Calendar</div>
              </a>
            </li>
            <!-- Room Reservation -->
            <li class="menu-item">
            <a href="../room_reservation.php" class="menu-link">
            <i class="menu-icon tf-icons bx bx-calendar-event"></i>
            <div data-i18n="Ticketing">Room Reservation</div>
            </a>
            </li>
            <!--File SALN-->
            <li class="menu-item">
              <a href="../saln.php" class="menu-link">
              <i class="menu-icon icon-base bx bx-detail"></i>
              <div data-i18n="saln">File SALN</div>
              </a>
              </li>
            <!--Leave Request-->
            <li class="menu-item">
        <a href="../leaverequest.php" class="menu-link">
        <i class='bx bx-receipt' style="margin-right: 15px;"></i>
        <div data-i18n="Leave Request">Leave Request</div>
    </a>
    </li>

            <!-- Meeting -->
            <li class="menu-item">
              <a href="../meeting.php" class="menu-link">
              <i class="menu-icon bx bx-group icon-sm me-1_5"></i>
              <div data-i18n="Meeting">Create Notice of Meeting</div>
              </a>
              </li>
                          <!-- Ticketing -->
                          <li class="menu-item active">
              <a href="../client_dashboard.php" class="menu-link">
              <i class="menu-icon tf-icons bx bx-support"></i>
              <div data-i18n="Ticketing">IT Service Request</div>
              </a>
              </li>

            
          </ul>
        </aside>

        <!-- / Menu -->

        <!-- Layout container -->
        <div class="layout-page">
          <!-- Navbar -->

          <nav
            class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
            id="layout-navbar"
          >
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
                  <input
                    type="text"
                    class="form-control border-0 shadow-none"
                    placeholder="Search..."
                    aria-label="Search..."
                  />
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
          src="<?php echo isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : '../assets/img/avatars/1.png'; ?>" 
          alt="user-avatar" 
          class="w-px-40 h-100 rounded-circle"/>
                   </div>
                   </a>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                      <a class="dropdown-item" href="../user_profile.php">
                        <div class="d-flex">
                          <div class="flex-shrink-0 me-3">
                            <div class="avatar avatar-online">
                              <img 
                       src="<?php echo isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : '../assets/img/avatars/1.png'; ?>" 
                       alt="user-avatar" 
                       class="d-block rounded" 
                       height="100" 
                       width="100" 
                       id="uploadedAvatar"
                       />
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
                      <a class="dropdown-item" href="../user_profile.php">
                        <i class="bx bx-user me-2"></i>
                        <span class="align-middle">My Profile</span>
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item" href="../settings-my-profile.php">
                        <i class="bx bx-cog me-2"></i>
                        <span class="align-middle">Settings</span>
                      </a>
                    </li>
                    <li>
                      <div class="dropdown-divider"></div>
                    </li>
                    <li>
                      <a class="dropdown-item" href="../../logout.php">
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

<div class="main-container">
        <!-- Column 1: Ticket Information -->
        <div class="column">
    <h3>Ticket Information</h3>
    <div class="ticket-detail">
        <p><strong>Ticket Number:</strong> <?php echo htmlspecialchars($ticket['id']); ?></p>
        <p><strong>Status:</strong> 
            <span class="badge 
                <?php 
                    if ($ticket['status'] == 'Resolved') {
                        echo 'badge-resolved';
                    } elseif ($ticket['status'] == 'In Progress') {
                        echo 'badge-in-progress';
                    } elseif ($ticket['status'] == 'Pending') {
                        echo 'badge-pending';
                    } else {
                        echo 'badge-cancelled';
                    }
                ?>">
                <?php echo htmlspecialchars($ticket['status']); ?>
            </span>
        </p>
        <p><strong>Assigned to:</strong> <?php echo htmlspecialchars($ticket['mis_name'] ?? 'Not Assigned'); ?></p>

        <p><strong>Subject:</strong> <?php echo htmlspecialchars($ticket['subject']); ?></p>
        
        <label for="ticket-description"><strong>Description:</strong></label>
        <textarea id="ticket-description" readonly><?php echo htmlspecialchars($ticket['description']); ?></textarea>
    </div>

    <!-- Button to navigate back to the dashboard -->
    <div class="btn-container">
        <a href="../../user/client_dashboard.php" class="btn-back">Back to Dashboard</a>
    </div>
</div>


        <!-- Column 2: History Logs -->
        <div class="column">
            <h3>History Timeline</h3>
            <?php if ($history_result->num_rows > 0): ?>
                <ul class="history-list">
                    <?php while ($row = $history_result->fetch_assoc()): ?>
                        <li><strong><?php echo $row['timestamp']; ?>:</strong> <?php echo htmlspecialchars($row['action']); ?></li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <p>No history records found for this ticket.</p>
            <?php endif; ?>
        </div>

    </div>
    

    <!-- Core JS -->
    <!-- build:js assets/vendor/js/core.js -->
    <script src="../../assets/vendor/js/bootstrap.js"></script>

<script src="../../assets/vendor/js/menu.js"></script>

    <!-- endbuild -->

    <!-- Vendors JS -->
    <script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>

    <!-- Main JS -->
    <script src="../../assets/js/main.js"></script>

    <!-- Page JS -->
    <script src="../../assets/js/dashboards-analytics.js"></script>

    <!-- Place this tag in your head or just before your close body tag. -->
    <script async defer src="https://buttons.github.io/buttons.js"></script>





  </body>
</html>
