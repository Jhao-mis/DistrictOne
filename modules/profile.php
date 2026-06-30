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

    <title>Profile</title>

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

    <!-- Helpers -->
    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>

    <!-- FullCalendar v6 (single version — the old v3/jQuery calendar + qtip2 were removed as dead code) -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>

    <style>
        :root {
            --tk-bg: #f7f8fa;
            --tk-surface: #ffffff;
            --tk-border: #e8eaee;
            --tk-text: #1f2430;
            --tk-text-muted: #767e8c;
            --tk-primary: #7cb9ff;
            --tk-primary-soft: #eaf3ff;
            --tk-radius: 12px;
            --tk-shadow: 0 1px 2px rgba(20,20,43,.04), 0 8px 24px -12px rgba(20,20,43,.10);
        }

        .pf-wrap { font-family: inherit; color: var(--tk-text); }

        /* ── Profile header ────────────────────────────────────────── */
        .pf-card {
            background: var(--tk-surface);
            border: 1px solid var(--tk-border);
            border-radius: var(--tk-radius);
            box-shadow: var(--tk-shadow);
            overflow: hidden;
        }
        .user-profile-info { line-height: 1.4; }
        .user-name { font-size: 1.75rem; }
        @media (max-width: 576px) { .user-name { font-size: 1.4rem; } }

        .pf-emp-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--tk-primary-soft);
            color: #2563a8;
            font-size: 12.5px;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 999px;
        }
        .pf-emp-badge svg { width: 13px; height: 13px; }

        .pf-visit-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--tk-primary);
            color: #fff;
            font-weight: 600;
            font-size: 13.5px;
            padding: 9px 16px;
            border-radius: 9px;
            text-decoration: none;
            box-shadow: 0 4px 10px -4px rgba(124,185,255,.6);
            transition: background .12s ease, transform .12s ease;
        }
        .pf-visit-btn:hover { background: #4e96f0; color: #fff; transform: translateY(-1px); }

        /* ── Section card (about / contacts / education / family) ───── */
        .pf-section-title {
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: .5px;
            font-weight: 700;
            color: var(--tk-text-muted);
            margin: 22px 0 12px;
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .pf-section-title:first-child { margin-top: 0; }
        .pf-section-title svg { width: 14px; height: 14px; color: var(--tk-primary); }

        .pf-info-list { list-style: none; margin: 0; padding: 0; }
        .pf-info-item {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            padding: 8px 0;
            border-bottom: 1px dashed var(--tk-border);
            font-size: 13.5px;
        }
        .pf-info-item:last-child { border-bottom: none; }
        .pf-info-icon {
            width: 28px; height: 28px; border-radius: 8px;
            background: var(--tk-bg);
            color: var(--tk-text-muted);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .pf-info-icon svg { width: 14px; height: 14px; }
        .pf-info-label { font-weight: 600; color: var(--tk-text-muted); min-width: 110px; }
        .pf-info-value { color: var(--tk-text); font-weight: 500; }

        /* ── Announcements ────────────────────────────────────────── */
        .pf-card-head {
            padding: 16px 20px;
            border-bottom: 1px solid var(--tk-border);
            font-weight: 700;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .pf-card-head svg { width: 16px; height: 16px; color: var(--tk-primary); }

        .pf-announcement {
            padding: 13px 0;
            border-bottom: 1px solid var(--tk-border);
        }
        .pf-announcement:last-of-type { border-bottom: none; }
        .pf-announcement-dept {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            color: #2563a8;
            background: var(--tk-primary-soft);
            padding: 2px 9px;
            border-radius: 999px;
            margin-bottom: 6px;
        }
        .pf-announcement-title {
            display: block;
            font-weight: 700;
            color: var(--tk-text);
            text-decoration: none;
            font-size: 14px;
        }
        .pf-announcement-title:hover { color: var(--tk-primary); }
        .pf-announcement-date { font-size: 12px; color: var(--tk-text-muted); margin-top: 3px; }

        .pf-view-all {
            display: block;
            text-align: center;
            margin-top: 12px;
            font-size: 13px;
            font-weight: 600;
            color: var(--tk-primary);
            text-decoration: none;
        }
        .pf-view-all:hover { text-decoration: underline; }

        .pf-empty { text-align: center; color: var(--tk-text-muted); font-size: 13.5px; padding: 16px 0; }

        /* ── Calendar ─────────────────────────────────────────────── */
        .pf-calendar-card {
            background: var(--tk-surface);
            border: 1px solid var(--tk-border);
            border-radius: var(--tk-radius);
            box-shadow: var(--tk-shadow);
            overflow: hidden;
        }
        .pf-calendar-head {
            padding: 16px 20px;
            border-bottom: 1px solid var(--tk-border);
            font-weight: 700;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .pf-calendar-head svg { width: 16px; height: 16px; color: var(--tk-primary); }
        .pf-calendar-head .pf-cal-legend {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11.5px;
            font-weight: 600;
            color: var(--tk-text-muted);
        }
        .pf-cal-legend .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--tk-primary); }

        #calendar { padding: 18px 20px 20px; }

        /* Toolbar */
        #calendar .fc-toolbar.fc-header-toolbar { margin-bottom: 16px; flex-wrap: wrap; gap: 10px; }
        #calendar .fc-toolbar-title { font-size: 16.5px; font-weight: 700; color: var(--tk-text); }
        #calendar .fc-button {
            background: var(--tk-surface) !important;
            border: 1.5px solid var(--tk-border) !important;
            color: var(--tk-text) !important;
            font-weight: 600 !important;
            font-size: 12.5px !important;
            text-transform: capitalize !important;
            box-shadow: none !important;
            padding: 6px 12px !important;
            border-radius: 8px !important;
            transition: border-color .12s ease, background .12s ease;
        }
        #calendar .fc-button:hover { border-color: var(--tk-primary) !important; background: var(--tk-primary-soft) !important; }
        #calendar .fc-button-primary:not(:disabled).fc-button-active,
        #calendar .fc-button-primary:not(:disabled):active {
            background: var(--tk-primary) !important;
            border-color: var(--tk-primary) !important;
            color: #fff !important;
        }
        #calendar .fc-today-button {
            background: var(--tk-primary-soft) !important;
            border-color: var(--tk-primary-soft) !important;
            color: #2563a8 !important;
        }
        #calendar .fc-today-button:disabled { opacity: .5 !important; }
        #calendar .fc-icon { font-size: 14px; }

        /* Grid */
        #calendar .fc-theme-standard td,
        #calendar .fc-theme-standard th,
        #calendar .fc-theme-standard .fc-scrollgrid { border-color: var(--tk-border) !important; }
        #calendar .fc-col-header-cell {
            background: var(--tk-bg);
            padding: 9px 0;
        }
        #calendar .fc-col-header-cell-cushion {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .5px;
            font-weight: 700;
            color: var(--tk-text-muted);
            text-decoration: none;
        }
        #calendar .fc-daygrid-day-number {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--tk-text);
            padding: 6px 8px;
            text-decoration: none;
        }
        #calendar .fc-day-today {
            background: var(--tk-primary-soft) !important;
        }
        #calendar .fc-day-today .fc-daygrid-day-number {
            background: var(--tk-primary);
            color: #fff;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            margin: 4px;
        }
        #calendar .fc-day-other .fc-daygrid-day-number { color: #c3c8d1; }
        #calendar .fc-daygrid-day-frame:hover { background: #fafbfd; }
        #calendar .fc-scrollgrid { border-radius: 10px; overflow: hidden; }

        /* Events */
        #calendar .fc-event {
            cursor: pointer;
            border: none !important;
            font-size: 11.5px !important;
            font-weight: 600 !important;
            padding: 2px 6px !important;
            border-radius: 6px !important;
            box-shadow: 0 1px 2px rgba(20,20,43,.12);
            transition: transform .1s ease, box-shadow .1s ease;
        }
        #calendar .fc-event:hover { transform: translateY(-1px); box-shadow: 0 3px 8px -2px rgba(20,20,43,.25); }
        #calendar .fc-daygrid-event-dot { display: none; }
        #calendar .fc-more-link {
            font-size: 11px;
            font-weight: 700;
            color: var(--tk-primary);
        }
        #calendar .fc-daygrid-day.fc-day-sat .fc-daygrid-day-frame,
        #calendar .fc-daygrid-day.fc-day-sun .fc-daygrid-day-frame {
            background: #fcfcfd;
        }

        @media (max-width: 600px) {
            #calendar .fc-toolbar.fc-header-toolbar { flex-direction: column; align-items: flex-start; }
        }
    </style>

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
            <div class="pf-wrap">

                <div class="row">
                    <div class="col-12">
                        <div class="pf-card mb-6">
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
                                            <button type="button" data-bs-target="#coverCarousel" data-bs-slide-to="5"
                                                aria-label="Slide 6"></button>
                                        </div>

                                        <!-- Carousel Items -->
                                        <div class="carousel-inner" style="height: 100%;">

                                            <div class="carousel-item active" style="height: 100%;">
                                                <img src="../assets/img/carousel/new-web.png" class="d-block w-100 h-100"
                                                    style="object-fit: cover;" alt="Cover 1">
                                            </div>

                                            <div class="carousel-item " style="height: 100%;">
                                                <img src="../assets/img/carousel/tell2.png" class="d-block w-100 h-100"
                                                    style="object-fit: cover;" alt="Cover 1">
                                            </div>

                                            <div class="carousel-item " style="height: 100%;">
                                                <img src="../assets/img/carousel/gm.png" class="d-block w-100 h-100"
                                                    style="object-fit: cover;" alt="Cover 1">
                                            </div>
                                            <div class="carousel-item" style="height: 100%;">
                                                <img src="../assets/img/carousel/6.png" class="d-block w-100 h-100"
                                                    style="object-fit: cover;" alt="Cover 3">
                                            </div>
                                            <div class="carousel-item" style="height: 100%;">
                                                <img src="../assets/img/carousel/1.png" class="d-block w-100 h-100"
                                                    style="object-fit: cover;" alt="Cover 3">
                                            </div>
                                            <div class="carousel-item" style="height: 100%;">
                                                <img src="../assets/img/carousel/3.png" class="d-block w-100 h-100"
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
                                        <div class="user-profile-info">
                                            <h2 class="fw-bold mb-1 user-name">
                                                <?php echo htmlspecialchars("$firstname $middlename $lastname"); ?>
                                            </h2>

                                            <p class="text-muted mb-2">
                                                <?php echo htmlspecialchars($position); ?>
                                                <span class="mx-1">•</span>
                                                <strong><?php echo htmlspecialchars($department); ?></strong>
                                            </p>

                                            <span class="pf-emp-badge">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
                                                Emp No: <?php echo htmlspecialchars($emp_id); ?>
                                            </span>
                                        </div>

                                        <div class="d-flex flex-column align-items-md-end align-items-center gap-2">

                                            <a href="https://cwd.com.ph/" target="_blank" class="pf-visit-btn">
                                                <i class="bx bx-globe"></i>
                                                <span>Visit Our New Website</span>
                                            </a>

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
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3 mt-lg-4 mt-3 ms-2 nav-align-top">
                            <ul class="nav nav-pills flex-column flex-sm-row mb-6 gap-sm-0 gap-2">

                                <li class="nav-item">
                                    <a class="nav-link active" href="profile.php"><i
                                            class="icon-base bx bx-user icon-sm me-1_5"></i> Profile</a>
                                </li>

                                <li class="nav-item">
                                    <a class="nav-link " href="profileTeams.php"><i
                                            class="icon-base bx bx-group icon-sm me-1_5"></i> Teams</a>
                                </li>

                                <li class="nav-item">
                                    <a class="nav-link" href="tell.php"><i class="icon-base bx bx-phone icon-sm me-1_5"></i>
                                        Local Directory</a>
                                </li>

                            </ul>
                        </div>
                    </div>
                </div>


                <!-- SIDE INFO -->
                <div class="row">
                    <div class="col-xl-4 col-lg-5 col-md-5">

                        <div class="pf-card mb-6" style="margin-bottom:20px;">
                            <div class="card-body">

                                <div class="pf-section-title">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    About
                                </div>
                                <ul class="pf-info-list">
                                    <li class="pf-info-item">
                                        <span class="pf-info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                                        <span class="pf-info-label">Full Name</span>
                                        <span class="pf-info-value"><?php echo htmlspecialchars($firstname); ?>
                                            <?php echo htmlspecialchars($middlename); ?>
                                            <?php echo htmlspecialchars($lastname); ?></span>
                                    </li>
                                    <li class="pf-info-item">
                                        <span class="pf-info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></span>
                                        <span class="pf-info-label">Birthday</span>
                                        <span class="pf-info-value"><?php echo htmlspecialchars($personal_date_of_birth); ?></span>
                                    </li>
                                    <li class="pf-info-item">
                                        <span class="pf-info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a8 8 0 0 1 16 0v1"/></svg></span>
                                        <span class="pf-info-label">Gender</span>
                                        <span class="pf-info-value"><?php echo htmlspecialchars($sex); ?></span>
                                    </li>
                                    <li class="pf-info-item">
                                        <span class="pf-info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 1 0-7.78 7.78l1.06 1.06L12 21l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></span>
                                        <span class="pf-info-label">Civil Status</span>
                                        <span class="pf-info-value"><?php echo htmlspecialchars($civil_status); ?></span>
                                    </li>
                                    <li class="pf-info-item">
                                        <span class="pf-info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22V4a1 1 0 0 1 1-1h10l1 2h5a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1h-6l-1-2H6a1 1 0 0 0-1 1v7"/></svg></span>
                                        <span class="pf-info-label">Citizenship</span>
                                        <span class="pf-info-value"><?php echo htmlspecialchars($citizenship); ?></span>
                                    </li>
                                </ul>

                                <div class="pf-section-title">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                    Contacts
                                </div>
                                <ul class="pf-info-list">
                                    <li class="pf-info-item">
                                        <span class="pf-info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span>
                                        <span class="pf-info-label">Telephone</span>
                                        <span class="pf-info-value"><?php echo htmlspecialchars($telephone_no); ?></span>
                                    </li>
                                    <li class="pf-info-item">
                                        <span class="pf-info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
                                        <span class="pf-info-label">Mobile</span>
                                        <span class="pf-info-value"><?php echo htmlspecialchars($mobile_no); ?></span>
                                    </li>
                                    <li class="pf-info-item">
                                        <span class="pf-info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/></svg></span>
                                        <span class="pf-info-label">Email</span>
                                        <span class="pf-info-value"><?php echo htmlspecialchars($email); ?></span>
                                    </li>
                                </ul>

                                <div class="pf-section-title">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                                    Educational Background
                                </div>
                                <ul class="pf-info-list">
                                    <li class="pf-info-item">
                                        <span class="pf-info-icon" style="visibility:hidden;"></span>
                                        <span class="pf-info-label">Elementary</span>
                                        <span class="pf-info-value"><?php echo htmlspecialchars($elementarySchool); ?></span>
                                    </li>
                                    <li class="pf-info-item">
                                        <span class="pf-info-icon" style="visibility:hidden;"></span>
                                        <span class="pf-info-label">High School</span>
                                        <span class="pf-info-value"><?php echo htmlspecialchars($highSchool); ?></span>
                                    </li>
                                    <li class="pf-info-item">
                                        <span class="pf-info-icon" style="visibility:hidden;"></span>
                                        <span class="pf-info-label">College</span>
                                        <span class="pf-info-value"><?php echo htmlspecialchars($college); ?></span>
                                    </li>
                                    <li class="pf-info-item">
                                        <span class="pf-info-icon" style="visibility:hidden;"></span>
                                        <span class="pf-info-label">Graduate School</span>
                                        <span class="pf-info-value"><?php echo htmlspecialchars($graduateSchool); ?></span>
                                    </li>
                                    <li class="pf-info-item">
                                        <span class="pf-info-icon" style="visibility:hidden;"></span>
                                        <span class="pf-info-label">Vocational</span>
                                        <span class="pf-info-value"><?php echo htmlspecialchars($vocTradeCourse); ?></span>
                                    </li>
                                </ul>

                                <div class="pf-section-title">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                    Family Members
                                </div>
                                <ul class="pf-info-list">
                                    <li class="pf-info-item">
                                        <span class="pf-info-icon" style="visibility:hidden;"></span>
                                        <span class="pf-info-label">Spouse</span>
                                        <span class="pf-info-value"><?php echo htmlspecialchars($spouse_first_name); ?>
                                            <?php echo htmlspecialchars($spouse_middle_name); ?>
                                            <?php echo htmlspecialchars($spouse_last_name); ?>
                                            <?php echo htmlspecialchars($spouse_extension_name); ?></span>
                                    </li>
                                    <li class="pf-info-item">
                                        <span class="pf-info-icon" style="visibility:hidden;"></span>
                                        <span class="pf-info-label">Mother</span>
                                        <span class="pf-info-value"><?php echo htmlspecialchars($mother_first_name); ?>
                                            <?php echo htmlspecialchars($mother_middle_name); ?>
                                            <?php echo htmlspecialchars($mother_last_name); ?></span>
                                    </li>
                                    <li class="pf-info-item">
                                        <span class="pf-info-icon" style="visibility:hidden;"></span>
                                        <span class="pf-info-label">Father</span>
                                        <span class="pf-info-value"><?php echo htmlspecialchars($father_first_name); ?>
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
                        <div class="pf-card mb-6">

                            <div class="pf-card-head">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
                                Recent Announcements
                            </div>
                            <div class="card-body">
                                <?php
                                $client_department = $department;

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

                                $stmt->execute();
                                $recent_result = $stmt->get_result();

                                if ($recent_result->num_rows > 0):
                                    while ($recent = $recent_result->fetch_assoc()):
                                        ?>
                                        <div class="pf-announcement">
                                            <span class="pf-announcement-dept"><?php echo htmlspecialchars($recent['department']); ?></span>
                                            <a href="#" class="pf-announcement-title">
                                                <?php echo htmlspecialchars($recent['title']); ?>
                                            </a>
                                            <div class="pf-announcement-date">
                                                <?php echo date("F j, Y g:i A", strtotime($recent['created_at'])); ?>
                                            </div>
                                        </div>
                                        <?php
                                    endwhile;
                                else:
                                    ?>
                                    <div class="pf-empty">No recent posts</div>
                                    <?php
                                endif;
                                $stmt->close();
                                ?>

                                <a href="announcement.php" class="pf-view-all">View all announcements</a>
                            </div>
                        </div>


                        <!-- CALENDAR -->
                        <div class="col-xl-12 col-lg-7 col-md-7 mt-4">
                            <div class="pf-calendar-card">
                                <div class="pf-calendar-head">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                                    Calendar
                                    <span class="pf-cal-legend"><span class="dot"></span>Scheduled event</span>
                                </div>
                                <div id="calendar"></div>
                            </div>
                        </div>

                        <!-- Bootstrap Modal for Event Details -->
                        <div class="modal fade" id="eventModal" tabindex="-1" role="dialog"
                            aria-labelledby="eventModalLabel" aria-hidden="true">
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
                    <div class="content-backdrop fade"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="layout-overlay layout-menu-toggle"></div>

    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/vendor/libs/apex-charts/apexcharts.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/dashboards-analytics.js"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>

    <!-- Single working calendar implementation (FullCalendar v6, fetches real events from fetch_events.php) -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var calendarEl = document.getElementById('calendar');
            if (!calendarEl) return;

            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                themeSystem: 'bootstrap5',
                height: 'auto',
                events: 'fetch_events.php',
                dayMaxEvents: 3,
                fixedWeekCount: false,
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,dayGridWeek,listMonth'
                },
                buttonText: {
                    today: 'Today',
                    month: 'Month',
                    week: 'Week',
                    list: 'List'
                },

                eventDidMount: function (info) {
                    info.el.style.backgroundColor = '#7CB9FF';
                    info.el.style.borderColor = '#7CB9FF';
                    info.el.style.color = '#ffffff';
                    info.el.style.borderRadius = '6px';
                    info.el.style.padding = '2px 4px';
                    info.el.setAttribute('title', info.event.title);
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

                    new bootstrap.Modal(document.getElementById('eventModal')).show();
                }
            });

            calendar.render();
        });
    </script>

</body>

</html>