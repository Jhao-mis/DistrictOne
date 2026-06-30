<?php
include '../db.php';
require '../vendor/autoload.php';
require 'login_verification.php';

$username = $_SESSION['username'];

date_default_timezone_set('Asia/Manila');
$now = date('Y-m-d H:i:s');

$updateActivity = $conn->prepare("UPDATE users SET last_activity = ? WHERE username = ?");
$updateActivity->bind_param("ss", $now, $username);
$updateActivity->execute();
$updateActivity->close();

/* ===========================
   FETCH USER PROFILE DATA
   =========================== */
$query = $conn->prepare("
    SELECT 
        users.id,
        users.emp_id,
        users.profile_picture,
        users.cover_photo,
        users.department,
        users.firstname,
        users.middlename,
        users.lastname,
        users.email,
        personal_data_sheet.position
    FROM users
    LEFT JOIN personal_data_sheet 
        ON users.id = personal_data_sheet.user_id
    WHERE users.username = ?
");

$query->bind_param("s", $username);
$query->execute();
$query->store_result();

/* 🔥 FIXED: bind_result COUNT & ORDER */
$query->bind_result(
    $user_id,
    $emp_id,
    $profile_picture,
    $cover_photo,
    $department,
    $firstname,
    $middlename,
    $lastname,
    $email,
    $position
);

$query->fetch();
$query->close();

/* ===========================
   DEFAULT IMAGES
   =========================== */
if (empty($profile_picture)) {
    $profile_picture = '../assets/img/avatars/default_dp.jpg';
}
if (empty($cover_photo)) {
    $cover_photo = '../assets/img/avatars/default_cover.png';
}

$_SESSION['profile_picture'] = $profile_picture;
$_SESSION['cover_photo'] = $cover_photo;

/* ===========================
   PERSONAL DATA SHEET
   =========================== */
$query = $conn->prepare("
    SELECT 
        name_extension,
        personal_date_of_birth,
        place_of_birth,
        sex,
        civil_status,
        citizenship,
        telephone_no,
        mobile_no
    FROM personal_data_sheet
    WHERE user_id = ?
");
$query->bind_param("i", $user_id);
$query->execute();
$query->store_result();
$query->bind_result(
    $name_extension,
    $personal_date_of_birth,
    $place_of_birth,
    $sex,
    $civil_status,
    $citizenship,
    $telephone_no,
    $mobile_no
);
$query->fetch();
$query->close();

/* ===========================
   FAMILY BACKGROUND
   =========================== */
$query = $conn->prepare("
    SELECT 
        spouse_first_name,
        spouse_extension_name,
        spouse_middle_name,
        spouse_last_name,
        father_first_name,
        father_name_extension,
        father_middle_name,
        father_last_name,
        mother_first_name,
        mother_middle_name,
        mother_last_name
    FROM family_background
    WHERE user_id = ?
");
$query->bind_param("i", $user_id);
$query->execute();
$query->store_result();
$query->bind_result(
    $spouse_first_name,
    $spouse_extension_name,
    $spouse_middle_name,
    $spouse_last_name,
    $father_first_name,
    $father_name_extension,
    $father_middle_name,
    $father_last_name,
    $mother_first_name,
    $mother_middle_name,
    $mother_last_name
);
$query->fetch();
$query->close();

/* ===========================
   EDUCATIONAL BACKGROUND
   =========================== */
$query = $conn->prepare("
    SELECT 
        elementarySchool,
        highSchool,
        college,
        vocTradeCourse,
        graduateSchool
    FROM educational_background
    WHERE user_id = ?
");
$query->bind_param("i", $user_id);
$query->execute();
$query->store_result();
$query->bind_result(
    $elementarySchool,
    $highSchool,
    $college,
    $vocTradeCourse,
    $graduateSchool
);
$query->fetch();
$query->close();

/* ===========================
   SAFE DEFAULTS
   =========================== */
$name_extension = $name_extension ?? '';
$personal_date_of_birth = $personal_date_of_birth ?? '';
$emp_id = $emp_id ?? '—';

/* ===========================
   FILE UPLOAD SETTINGS
   =========================== */
$successMessage = "";
$errorMessage = "";

$allowed_types = ["image/jpeg", "image/jpg", "image/png"];
$max_size = 2 * 1024 * 1024;
$upload_dir = "../uploads/";

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

/* ===========================
   COVER PHOTO UPLOAD
   =========================== */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['cover_photo'])) {
    $cover_photo_file = $_FILES['cover_photo'];

    if ($cover_photo_file["error"] !== 0) {
        $errorMessage = "Cover photo upload error.";
    } elseif (!in_array($cover_photo_file["type"], $allowed_types)) {
        $errorMessage = "Only JPG, JPEG, PNG allowed.";
    } elseif ($cover_photo_file["size"] > $max_size) {
        $errorMessage = "Cover photo must be under 2MB.";
    } else {
        $cover_name = time() . "_cover_" . basename($cover_photo_file["name"]);
        $cover_path = $upload_dir . $cover_name;

        if (move_uploaded_file($cover_photo_file["tmp_name"], $cover_path)) {
            $stmt = $conn->prepare("UPDATE users SET cover_photo = ? WHERE id = ?");
            $stmt->bind_param("si", $cover_path, $user_id);
            $stmt->execute();
            $stmt->close();

            $_SESSION['cover_photo'] = $cover_path;
            header("Location: user_profile.php?success=1");
            exit();
        }
    }
}

/* ===========================
   ANNOUNCEMENTS
   =========================== */
$client_department = $department;

if ($client_department == "All Departments") {
    $stmt = $conn->prepare("SELECT * FROM announcements ORDER BY created_at DESC");
} else {
    $stmt = $conn->prepare("
        SELECT * FROM announcements 
        WHERE department = ? OR department = 'All Departments'
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("s", $client_department);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!-- Display messages -->
<?php if (!empty($successMessage)): ?>
    <script>alert("<?php echo $successMessage; ?>");</script>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
    <script>alert("<?php echo $errorMessage; ?>");</script>
<?php endif; ?>

<!DOCTYPE html>

<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Teams</title>

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
    <link rel="stylesheet" href="./css/profileTeams.css">

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="../assets/vendor/libs/apex-charts/apex-charts.css" />

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Calendar CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/qtip2/3.0.3/jquery.qtip.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qtip2/3.0.3/jquery.qtip.min.js"></script>
    <!-- Helpers -->
    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>

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
        <!-- Content -->
        <div class="container-xxl flex-grow-1 container-p-y">
            <div class="row">
                <div class="col-12">
                    <div class="card mb-6">
                        <div class="user-profile-header-banner">
                            <div class="cover-photo-container"
                                style="height: 300px; overflow: hidden; position: relative;">
                                <!-- Bootstrap Carousel -->
                                <div id="coverCarousel" class="carousel slide" data-bs-ride="carousel"
                                    data-bs-interval="10000" style="height: 100%;">

                                    <!-- Indicators -->
                                    <div class="carousel-indicators">
                                        <button type="button" data-bs-target="#coverCarousel" data-bs-slide-to="0"
                                            class="active" aria-current="true" aria-label="Slide 1"></button>
                                        <button type="button" data-bs-target="#coverCarousel" data-bs-slide-to="1"
                                            aria-label="Slide 2"></button>
                                        <button type="button" data-bs-target="#coverCarousel" data-bs-slide-to="2"
                                            aria-label="Slide 3"></button>
                                        <button type="button" data-bs-target="#coverCarousel" data-bs-slide-to="3"
                                            aria-label="Slide 4"></button>
                                        <button type="button" data-bs-target="#coverCarousel" data-bs-slide-to="4"
                                            aria-label="Slide 5"></button>
                                    </div>

                                    <!-- Carousel Items -->
                                    <div class="carousel-inner" style="height: 100%;">
                                        <div class="carousel-item active" style="height: 100%;">
                                            <img src="../assets/img/carousel/1.png" class="d-block w-100 h-100"
                                                style="object-fit: cover;" alt="Cover 1">
                                        </div>
                                        <div class="carousel-item" style="height: 100%;">
                                            <img src="../assets/img/carousel/2.png" class="d-block w-100 h-100"
                                                style="object-fit: cover;" alt="Cover 2">
                                        </div>
                                        <div class="carousel-item" style="height: 100%;">
                                            <img src="../assets/img/carousel/3.png" class="d-block w-100 h-100"
                                                style="object-fit: cover;" alt="Cover 3">
                                        </div>
                                        <div class="carousel-item" style="height: 100%;">
                                            <img src="../assets/img/carousel/4.png" class="d-block w-100 h-100"
                                                style="object-fit: cover;" alt="Cover 3">
                                        </div>
                                        <div class="carousel-item" style="height: 100%;">
                                            <img src="../assets/img/carousel/5.png" class="d-block w-100 h-100"
                                                style="object-fit: cover;" alt="Cover 3">
                                        </div>
                                    </div>

                                    <!-- Carousel Controls -->
                                    <button class="carousel-control-prev" type="button" data-bs-target="#coverCarousel"
                                        data-bs-slide="prev">
                                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                        <span class="visually-hidden">Previous</span>
                                    </button>
                                    <button class="carousel-control-next" type="button" data-bs-target="#coverCarousel"
                                        data-bs-slide="next">
                                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                        <span class="visually-hidden">Next</span>
                                    </button>
                                </div>

                            </div>
                        </div>

                        <!-- Profile Picture -->
                        <div class="user-profile-header d-flex flex-column flex-lg-row text-sm-start text-center mb-8">
                            <div class="flex-shrink-0 mt-1 mx-sm-0 mx-auto">
                                <img src="<?php echo !empty($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : '../assets/img/avatars/default_dp.jpg'; ?>"
                                    alt="user-avatar" class="d-block h-80 ms-0 ms-sm-6 rounded-5 profile-img"
                                    id="uploadedAvatar">
                            </div>

                            <!-- User Info -->
                            <div class="flex-grow-1 mt-3 mt-lg-5">
                                <div
                                    class="d-flex align-items-md-end align-items-sm-start align-items-center justify-content-md-between justify-content-start mx-5 flex-md-row flex-column gap-4 mt-2 ms-3">
                                    <!-- User Info -->
                                    <style>
                                        .user-profile-info {
                                            line-height: 1.4;
                                        }

                                        .user-name {
                                            font-size: 1.75rem;
                                        }

                                        @media (max-width: 576px) {
                                            .user-name {
                                                font-size: 1.4rem;
                                            }
                                        }
                                    </style>
                                    <div class="user-profile-info">
                                        <h2 class="fw-bold mb-1 user-name">
                                            <?php echo htmlspecialchars("$firstname $middlename $lastname"); ?>
                                        </h2>

                                        <p class="text-muted mb-2">
                                            <?php echo htmlspecialchars($position); ?>
                                            <span class="mx-1">•</span>
                                            <strong><?php echo htmlspecialchars($department); ?></strong>
                                        </p>

                                        <span class="badge bg-label-primary">
                                            Emp No: <?php echo htmlspecialchars($emp_id); ?>
                                        </span>
                                    </div>

                                    <div id="success-message" style="display: none; 
             color: green; 
             padding: 10px; 
             background-color: #e7f7e7; 
             border: 1px solid green; 
             position: relative; 
             top: -300px; 
             right: 90px; 
             transition: opacity 0.5s ease-in-out; ">
                                        The Cover Photo has been successfully changed.

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="mb-3 mt-lg-4 mt-3 ms-2 nav-align-top">
                        <ul class="nav nav-pills flex-column flex-sm-row mb-6 gap-sm-0 gap-2">
                            <li class="nav-item">
                                <a class="nav-link" href="profile.php"><i
                                        class="icon-base bx bx-user icon-sm me-1_5"></i> Profile</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link active" href="javascript:void(0);"><i
                                        class="icon-base bx bx-group icon-sm me-1_5"></i> Teams</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>


    <!-- SIDE INFO -->
    <div class="row">
        <div class="col-xl-4 col-lg-5 col-md-5">

            <div class="card mb-6" style="margin-bottom:20px;">

                <div class="card-body">
                    <small class="card-text text-uppercase text-body-secondary small"
                        style="font-size:15px;">About</small>
                    <ul class="list-unstyled my-3 py-1">
                        <li class="d-flex align-items-center mb-4"><i class="icon-base bx bx-user"></i><span
                                class="fw-medium mx-2">Full Name:</span>
                            <span><?php echo htmlspecialchars($firstname); ?>
                                <?php echo htmlspecialchars($middlename); ?>
                                <?php echo htmlspecialchars($lastname); ?></span>
                        </li>
                        <li class="d-flex align-items-center mb-4"><i class="fa fa-birthday-cake"></i><span
                                class="fw-medium mx-2">Birthday:</span>
                            <span><?php echo htmlspecialchars($personal_date_of_birth); ?></span>
                        </li>
                        <li class="d-flex align-items-center mb-4"><i class="fa fa-venus-mars"></i><span
                                class="fw-medium mx-2">Gender:</span> <span><?php echo htmlspecialchars($sex); ?></span>
                        </li>
                        <li class="d-flex align-items-center mb-4"><i class="icon-base bx bx-heart"></i><span
                                class="fw-medium mx-2">Civil Status:</span>
                            <span><?php echo htmlspecialchars($civil_status); ?></span>
                        </li>
                        <li class="d-flex align-items-center mb-4"><i class="icon-base bx bx-flag"></i><span
                                class="fw-medium mx-2">Citizenship:</span>
                            <span><?php echo htmlspecialchars($citizenship); ?></span>
                        </li>
                    </ul>
                    <small class="card-text text-uppercase text-body-secondary small"
                        style="font-size:15px;">Contacts</small>
                    <ul class="list-unstyled my-3 py-1">
                        <li class="d-flex align-items-center mb-4"><i class="icon-base bx bx-phone"></i><span
                                class="fw-medium mx-2">Telephone:</span>
                            <span><?php echo htmlspecialchars($telephone_no); ?></span>
                        </li>
                        <li class="d-flex align-items-center mb-4"><i class="icon-base bx bx-chat"></i><span
                                class="fw-medium mx-2">Mobile:</span>
                            <span><?php echo htmlspecialchars($mobile_no); ?></span>
                        </li>
                        <li class="d-flex align-items-center mb-4"><i class="icon-base bx bx-envelope"></i><span
                                class="fw-medium mx-2">Email:</span>
                            <span><?php echo htmlspecialchars($email); ?></span>
                        </li>
                    </ul>
                    <small class="card-text text-uppercase text-body-secondary small"
                        style="font-size:15px;">Educational Background</small>
                    <ul class="list-unstyled my-3 py-1">
                        <li class="d-flex align-items-center mb-4"></i><span class="fw-medium mx-2">Elementary:</span>
                            <span><?php echo htmlspecialchars($elementarySchool); ?> </span>
                        </li>
                        <li class="d-flex align-items-center mb-4"></i><span class="fw-medium mx-2">Highshool:</span>
                            <span><?php echo htmlspecialchars($highSchool); ?> </span>
                        </li>
                        <li class="d-flex align-items-center mb-4"></i><span class="fw-medium mx-2">College:</span>
                            <span><?php echo htmlspecialchars($college); ?></span>
                        </li>
                        <li class="d-flex align-items-center mb-4"></i><span class="fw-medium mx-2">Graduate
                                School:</span> <span><?php echo htmlspecialchars($graduateSchool); ?> </span></li>
                        <li class="d-flex align-items-center mb-4"></i><span class="fw-medium mx-2">Vocational
                                Course:</span> <span><?php echo htmlspecialchars($vocTradeCourse); ?></span></li>
                    </ul>
                    <small class="card-text text-uppercase text-body-secondary small" style="font-size:15px;">Family
                        Members</small>
                    <ul class="list-unstyled my-3 py-1">
                        <li class="d-flex align-items-center mb-4"></i><span class="fw-medium mx-2">Spouse:</span>
                            <span><?php echo htmlspecialchars($spouse_first_name); ?>
                                <?php echo htmlspecialchars($spouse_middle_name); ?>
                                <?php echo htmlspecialchars($spouse_last_name); ?>
                                <?php echo htmlspecialchars($spouse_extension_name); ?></span>
                        </li>
                        <li class="d-flex align-items-center mb-4"></i><span class="fw-medium mx-2">Mother:</span>
                            <span><?php echo htmlspecialchars($mother_first_name); ?>
                                <?php echo htmlspecialchars($mother_middle_name); ?>
                                <?php echo htmlspecialchars($mother_last_name); ?></span>
                        </li>
                        <li class="d-flex align-items-center mb-4"></i><span class="fw-medium mx-2">Father:</span>
                            <span><?php echo htmlspecialchars($father_first_name); ?>
                                <?php echo htmlspecialchars($father_middle_name); ?>
                                <?php echo htmlspecialchars($father_last_name); ?>
                                <?php echo htmlspecialchars($father_name_extension); ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>




        <!-- Recent Announcements -->
        <div class="col-lg-12 col-xl-8">
            <div class="card card-action mb-6">

                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="card-action-title mb-0">Recent Announcements</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <?php
                        // Fetch the logged-in user's department
                        $client_department = $department; // Assume this is fetched from session or user data
                        
                        // Prepare the query based on user's department
                        if ($client_department == "All Departments") {
                            $recent_query = "SELECT title, department, created_at FROM announcements ORDER BY created_at DESC LIMIT 3";
                            $stmt = $conn->prepare($recent_query);
                        } else {
                            $recent_query = "SELECT title, department, created_at FROM announcements 
                                     WHERE department = ? OR department = 'All Departments' 
                                     ORDER BY created_at DESC LIMIT 3";
                            $stmt = $conn->prepare($recent_query);
                            $stmt->bind_param("s", $client_department);
                        }

                        // Execute query
                        $stmt->execute();
                        $recent_result = $stmt->get_result();

                        if ($recent_result->num_rows > 0):
                            while ($recent = $recent_result->fetch_assoc()):
                                ?>
                                <li class="list-group-item">
                                    <div class="d-flex align-items-start">

                                        <!-- Post Details -->
                                        <div>
                                            <p class="mb-1 text-muted small">
                                                <?php echo htmlspecialchars($recent['department']); ?>
                                            </p>
                                            <a href="#" class="fw-bold text-dark text-decoration-none">
                                                <?php echo htmlspecialchars($recent['title']); ?>
                                            </a>
                                            <p class="mb-0 text-muted small">
                                                <?php echo date("F j, Y g:i A", strtotime($recent['created_at'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                </li>
                                <?php
                            endwhile;
                        else:
                            ?>
                            <li class="list-group-item text-muted text-center">No recent posts</li>
                            <?php
                        endif;
                        $stmt->close();
                        ?>

                        <ul class="list-unstyled mb-0" style="margin-top:10px;">
                            <li class="text-center">
                                <a href="announcement.php">View all announcements</a>
                            </li>
                        </ul>
                    </ul>
                </div>
            </div>
      

            <!-- CALENDAR SCRIPT -->
            <div class="col-xl-12 col-lg-7 col-md-7 mt-4">

                <div id="calendar"></div>

                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        var calendarEl = document.getElementById('calendar');

                        var calendar = new FullCalendar.Calendar(calendarEl, {
                            initialView: 'dayGridMonth',
                            themeSystem: 'bootstrap5',

                            // ✅ Fetch events from your PHP file
                            events: 'fetch_events.php',

                            // 🔹 Ensure the event boxes are styled blue
                            eventDidMount: function (info) {
                                info.el.style.backgroundColor = '#7CB9FF'; // Blue background
                                info.el.style.borderColor = '#7CB9FF';     // Slightly darker border
                                info.el.style.color = '#ffffff';           // White text
                                info.el.style.borderRadius = '6px';        // Rounded corners
                                info.el.style.padding = '2px 4px';         // Padding inside the box
                            },

                            eventClick: function (info) {
                                alert(info.event.title + "\n\n" + info.event.extendedProps.description);
                            }
                        });

                        calendar.render();
                    });
                </script>


                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        var calendarEl = document.getElementById('calendar');

                        var calendar = new FullCalendar.Calendar(calendarEl, {
                            initialView: 'dayGridMonth',
                            themeSystem: 'bootstrap5',
                            events: 'fetch_events.php', // your PHP events file

                            eventDidMount: function (info) {
                                // Style each event box
                                info.el.style.backgroundColor = '#2196f3'; // blue
                                info.el.style.borderColor = '#1976d2';
                                info.el.style.color = '#ffffff';
                                info.el.style.borderRadius = '6px';
                                info.el.style.padding = '2px 4px';
                                info.el.style.cursor = 'pointer';
                            },

                            eventClick: function (info) {
                                const e = info.event.extendedProps;

                                document.getElementById('eventTitle').innerText = info.event.title;
                                document.getElementById('eventDepartment').innerText = e.department || 'N/A';

                                const start = info.event.start ? moment(info.event.start).format('MMMM D, YYYY') : '';
                                const end = info.event.end ? moment(info.event.end).format('MMMM D, YYYY') : '';
                                document.getElementById('eventDate').innerText = start && end ? (start === end ? start : start + ' - ' + end) : start;

                                document.getElementById('eventTime').innerText = info.event.start
                                    ? (moment(info.event.start).format('h:mm A') + (info.event.end ? ' - ' + moment(info.event.end).format('h:mm A') : ''))
                                    : 'N/A';

                                document.getElementById('eventLocation').innerText = e.event_location || 'N/A';

                                const urlEl = document.getElementById('eventURL');
                                if (e.event_url) {
                                    urlEl.href = e.event_url;
                                    urlEl.innerText = e.event_url;
                                    urlEl.style.display = 'inline';
                                } else {
                                    urlEl.href = '#';
                                    urlEl.innerText = 'N/A';
                                    urlEl.style.display = 'inline';
                                }

                                document.getElementById('eventDescription').innerText = e.description || 'N/A';

                                // Show Bootstrap modal
                                new bootstrap.Modal(document.getElementById('eventModal')).show();
                            }
                        });

                        calendar.render();
                    });
                </script>
                <!-- Bootstrap Modal for Event Details -->
                <div class="modal fade" id="eventModal" tabindex="-1" role="dialog" aria-labelledby="eventModalLabel"
                    aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="eventTitle"></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                    aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p><strong>Department:</strong> <span id="eventDepartment"></span></p>
                                <p><strong>Date:</strong> <span id="eventDate"></span></p>
                                <p><strong>Time:</strong> <span id="eventTime"></span></p>
                                <p><strong>Location:</strong> <span id="eventLocation"></span></p>
                                <p><strong>URL:</strong> <a id="eventURL" target="_blank"></a></p>
                                <p><strong>Description:</strong> <span id="eventDescription"></span></p>
                            </div>
                            <div class="modal-footer">
                            </div>
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

    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/vendor/libs/apex-charts/apexcharts.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/dashboards-analytics.js"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>

    <script>
        $(document).ready(function () {
            function updateMonthDisplay() {
                let currentMonth = $('#calendar').fullCalendar('getDate').format('MMMM YYYY');
                $("#monthButtonText").text(currentMonth);
                $("#eventMonthTitle").text(currentMonth);
                displayEventsForMonth(currentMonth);
            }

            function displayEventsForMonth(selectedMonth) {
                let eventListHTML = "";
                let filteredEvents = events.filter(event => moment(event.start).format('MMMM YYYY') === selectedMonth);

                if (filteredEvents.length === 0) {
                    eventListHTML = "<p>No events this month.</p>";
                } else {
                    filteredEvents.forEach(event => {
                        eventListHTML += `
                    <div class="card mb-2 p-3" style="background-color:${event.color || '#f8f9fa'};">
                        <h6 class="fw-bold">${event.title}</h6>
                        <p><strong>Department:</strong> ${event.department}</p>
                        <p><strong>Date:</strong> ${moment(event.start).format('MMMM D, YYYY')} - ${moment(event.end).format('MMMM D, YYYY')}</p>
                        <p><strong>Time:</strong> ${moment(event.start).format('h:mm A')} - ${moment(event.end).format('h:mm A')}</p>
                        <p><strong>Location:</strong> ${event.event_location}</p>
                        <p><strong>URL:</strong> <a href="${event.event_url}" target="_blank">${event.event_url}</a></p>
                        <p><strong>Description:</strong> ${event.description}</p>
                    </div>
                `;
                    });
                }

                $("#eventList").html(eventListHTML);
            }

            $('#calendar').fullCalendar({
                header: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'month,agendaWeek,agendaDay'
                },
                customButtons: {
                    prev: {
                        text: '‹', // Unicode left arrow
                        click: function () {
                            $('#calendar').fullCalendar('prev');
                        }
                    },
                    next: {
                        text: '›', // Unicode right arrow
                        click: function () {
                            $('#calendar').fullCalendar('next');
                        }
                    }
                },
                events: <?php echo json_encode($events); ?>,
                eventRender: function (event, element) {
                    element.css('background-color', event.color);
                    element.css('color', event.textColor);
                    element.qtip({
                        content: event.description,
                        style: {
                            classes: 'qtip-bootstrap'
                        }
                    });
                },
                height: 'auto',
                aspectRatio: 2,
                contentHeight: 600, // Set a fixed height for scrolling
                scrollTime: '08:00:00', // Default scroll position
                eventClick: function (event) {
                    let currentEventId = null; // Store the event ID
                    currentEventId = event.id;
                    $('#eventTitle').text(event.title);
                    $('#eventDepartment').text(event.department);
                    $('#eventDate').text(moment(event.start).format('MMMM D, YYYY'));
                    $('#eventTime').text(moment(event.start).format('h:mm A') + " - " + moment(event.end).format('h:mm A'));
                    $('#eventDescription').text(event.description);
                    $('#eventLocation').text(event.event_location);
                    if (event.type === 'Room Reservation') {
                        $('#eventURL').parent().hide(); // Hide the whole <p> that contains the URL
                    } else {
                        $('#eventURL').attr('href', event.event_url).text(event.event_url);
                        $('#eventURL').parent().show(); // Show if it's a normal event
                    }

                    // Set input values for editing
                    $('#editDepartment').val(event.department);
                    $('#editDate').val(moment(event.start).format('YYYY-MM-DD'));
                    $('#editTime').val(moment(event.start).format('HH:mm'));
                    $('#editLocation').val(event.event_location);
                    $('#editURL').val(event.event_url);
                    $('#editDescription').val(event.description);

                    // Set the event ID for deletion
                    $('#deleteEventBtn').attr('data-id', event.id);

                    // Show modal
                    $('#eventModal').modal('show');
                }
            });

            // Ensure the delete button gets the correct ID when the modal is shown
            $('#eventModal').on('show.bs.modal', function (event) {
                let button = $(event.relatedTarget); // The button that triggered the modal
                let activityId = button.data('id'); // Get ID from the clicked event

                $('#deleteEventBtn').attr('data-id', activityId); // Set the ID for delete button
            });

            // DELETE EVENT FUNCTION
            $('#deleteEventBtn').on('click', function () {
                let activityId = $(this).attr('data-id'); // Get the ID from the delete button

                if (!activityId) {
                    alert("Error: No activity ID found!");
                    return;
                }

                if (confirm("Are you sure you want to delete this activity?")) {
                    fetch("delete_activity.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded"
                        },
                        body: new URLSearchParams({ id: activityId }).toString()
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === "success") {
                                alert("Activity deleted successfully!");
                                $('#eventModal').modal('hide'); // Close modal
                                $('#calendar').fullCalendar('removeEvents', activityId); // Remove event from calendar
                            } else {
                                alert("Error: " + data.message);
                            }
                        })
                        .catch(error => console.error("Fetch Error:", error));
                }
            });

            // When update button is clicked, open the modal and set the ID
            $('#updateEventBtn').on('click', function () {
                let activityId = $('#deleteEventBtn').attr('data-id'); // Get ID from the delete button
                if (!activityId) {
                    alert("Error: No activity ID found!");
                    return;
                }

                $('#editEventId').val(activityId); // Store it in the hidden input field
                $('#editEventModal').modal('show');
            });


            // Confirm update and send to backend
            $(document).on('click', '#confirmUpdateBtn', function () {
                let activityId = $('#editEventId').val(); // Get the stored ID

                if (!activityId) {
                    alert("Error: No activity ID found!");
                    return;
                }

                let updatedData = new URLSearchParams({
                    id: activityId,
                    title: $('#editTitle').val(),
                    event_url: $('#editURL').val(),
                    event_location: $('#editLocation').val(),
                    activity_date: $('#editActivityDate').val(),
                    activity_end_date: $('#editActivityEndDate').val(),
                    start_time: $('#editStartTime').val(),
                    end_time: $('#editEndTime').val(),
                    description: $('#editDescription').val()
                }).toString();

                console.log("Data being sent:", updatedData); // Debugging

                fetch("update_activity.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: updatedData
                })
                    .then(response => response.text())
                    .then(data => {
                        console.log("Raw Server Response:", data);
                        try {
                            let jsonData = JSON.parse(data);
                            console.log("Parsed JSON Response:", jsonData);

                            if (jsonData.status === "success") {
                                $('#editEventModal').modal('hide');
                                location.reload();
                            } else {
                                alert("Error updating event: " + jsonData.message);
                            }
                        } catch (error) {
                            alert("Invalid JSON response: " + data);
                        }
                    })
                    .catch(error => {
                        console.error("Fetch Error:", error);
                        alert("AJAX request failed: " + error);
                    });
            });


        });

    </script>

    <script>
        function openEventModal(event) {
            document.getElementById("eventTitle").innerText = event.title;
            document.getElementById("eventDepartment").innerText = event.department;
            document.getElementById("eventDate").innerText = event.start;
            document.getElementById("eventTime").innerText = event.end;
            document.getElementById("eventLocation").innerText = event.event_location;
            document.getElementById("eventURL").innerText = event.event_url;
            document.getElementById("eventDescription").innerText = event.description;

            $('#deleteEventBtn').attr('data-id', event.id); // Ensure delete button has correct ID
            $("#eventModal").modal("show");
        }
    </script>

    <script>
        function previewImage(event) {
            const coverPhoto = document.getElementById("coverPreview");
            const saveBtn = document.querySelector(".save-btn");
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    coverPhoto.src = e.target.result;  // Update preview immediately
                    coverPhoto.style.display = "block"; // Make sure it's visible
                    coverPhoto.style.left = "0px";
                    coverPhoto.style.top = "0px";
                    coverPhoto.style.width = "100%";
                    coverPhoto.style.height = "100%";
                    const preview = document.getElementById('coverPreview');
                    preview.src = reader.result;


                    // Show the save button
                    saveBtn.classList.add("show"); // Add show class to display the button
                };
                reader.readAsDataURL(event.target.files[0]);
            }
        }

    </script>
    <script>
        // Preview the image before uploading
        function previewImage(event) {
            const coverPhoto = document.getElementById("coverPreview");
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    coverPhoto.src = e.target.result;  // Update preview immediately
                    coverPhoto.style.display = "block"; // Make sure it's visible
                };
                reader.readAsDataURL(file);
            }
        }
    </script>
    <script>
        let coverPhoto = document.querySelector('.cover-photo');
        let coverPhotoContainer = document.querySelector('.cover-photo-container');
        let saveBtn = document.querySelector('.save-btn');

        // Ensure that the cover photo is visible when the image is uploaded
        document.addEventListener("DOMContentLoaded", () => {
            if (coverPhoto) {
                coverPhoto.style.display = 'block';  // Show cover photo
            }
        });

        // Variables for dragging functionality
        let isDragging = false;
        let offsetX, offsetY;

        coverPhoto.addEventListener('mousedown', (e) => {
            isDragging = true;
            offsetX = e.clientX - coverPhoto.getBoundingClientRect().left;
            offsetY = e.clientY - coverPhoto.getBoundingClientRect().top;
            coverPhoto.classList.add('dragging');  // Add dragging effect
            document.body.style.cursor = 'grabbing';  // Change cursor style
        });

        document.addEventListener('mousemove', (e) => {
            if (isDragging) {
                let x = e.clientX - offsetX;
                let y = e.clientY - offsetY;

                // Keep the image within the container bounds
                let maxX = coverPhotoContainer.offsetWidth - coverPhoto.offsetWidth;
                let maxY = coverPhotoContainer.offsetHeight - coverPhoto.offsetHeight;

                // Set position of the image within the container
                x = Math.max(0, Math.min(x, maxX));
                y = Math.max(0, Math.min(y, maxY));

                coverPhoto.style.left = `${x}px`;
                coverPhoto.style.top = `${y}px`;
            }
        });

        document.addEventListener('mouseup', () => {
            isDragging = false;
            coverPhoto.classList.remove('dragging');  // Remove dragging effect
            document.body.style.cursor = 'grab';  // Change cursor back to normal
            saveBtn.style.display = 'block';  // Show save button after drag
        });

        // Handle save button click
        saveBtn.addEventListener('click', () => {
            // You can implement a function to save the new position in the database here
            alert('Cover photo position saved!');
            // Hide the save button after save
            saveBtn.style.display = 'none';
        });

    </script>
    <script>
        document.getElementById("cover-form").addEventListener("submit", function (event) {
            event.preventDefault(); // Prevent the form from submitting immediately

            // Show success message with fade-in effect
            const successMessage = document.getElementById("success-message");
            successMessage.style.display = "block"; // Make sure it's visible
            setTimeout(function () {
                successMessage.style.opacity = 1; // Fade in the message
            }, 0);

            // Simulate form submission (use AJAX or redirect as needed)
            setTimeout(function () {
                // After a short time, fade out the message and submit the form
                successMessage.style.opacity = 0; // Fade out the message
                setTimeout(function () {
                    document.getElementById("cover-form").submit(); // Submit the form
                }, 500); // Wait for the fade-out effect to complete
            }, 2000); // Wait for 2 seconds before fading out and submitting the form
        });
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </script>



    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

</body>

</html>