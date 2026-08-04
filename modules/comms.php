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

    <title>Committees</title>

    <meta name="description" content="" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/districtone.png" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
        rel="stylesheet" />

    <!-- Icons -->
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
            --tk-shadow: 0 1px 2px rgba(20, 20, 43, .04), 0 8px 24px -12px rgba(20, 20, 43, .10);
        }

        .pf-wrap {
            font-family: inherit;
            color: var(--tk-text);
        }

        .pf-card {
            background: var(--tk-surface);
            border: 1px solid var(--tk-border);
            border-radius: var(--tk-radius);
            box-shadow: var(--tk-shadow);
            overflow: hidden;
        }

        .user-name {
            font-size: 1.75rem;
        }

        @media (max-width: 576px) {
            .user-name {
                font-size: 1.4rem;
            }

            .user-profile-header {
                padding-top: 2rem !important;
            }
        }

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

        .pf-emp-badge svg {
            width: 13px;
            height: 13px;
        }

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
            box-shadow: 0 4px 10px -4px rgba(124, 185, 255, .6);
            transition: background .12s ease, transform .12s ease;
        }

        .pf-visit-btn:hover {
            background: #4e96f0;
            color: #fff;
            transform: translateY(-1px);
        }

        .dir-card {
            background: var(--tk-surface);
            border: 1px solid var(--tk-border);
            border-radius: var(--tk-radius);
            box-shadow: var(--tk-shadow);
        }

        .dir-search {
            position: relative;
            margin-bottom: 20px;
        }

        .dir-search svg {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            color: var(--tk-text-muted);
            pointer-events: none;
        }

        .dir-search input {
            width: 100%;
            padding: 13px 16px 13px 46px;
            border: 1.5px solid var(--tk-border);
            border-radius: 999px;
            font-size: 14px;
            background: var(--tk-bg);
            outline: none;
            transition: border-color .12s ease, box-shadow .12s ease, background .12s ease;
        }

        .dir-search input:focus {
            border-color: var(--tk-primary);
            background: var(--tk-surface);
            box-shadow: 0 0 0 4px var(--tk-primary-soft);
        }

        .dir-filters {
            display: flex;
            gap: 8px;
            flex-wrap: nowrap;
            overflow-x: auto;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }

        .dir-filters::-webkit-scrollbar {
            height: 4px;
        }

        .dir-filters::-webkit-scrollbar-thumb {
            background: var(--tk-primary-soft);
            border-radius: 4px;
        }

        .dir-filter-btn {
            flex-shrink: 0;
            border: 1.5px solid var(--tk-border);
            background: var(--tk-surface);
            color: var(--tk-text-muted);
            font-size: 12.5px;
            font-weight: 700;
            padding: 7px 15px;
            border-radius: 999px;
            cursor: pointer;
            white-space: nowrap;
            transition: all .12s ease;
        }

        .dir-filter-btn:hover {
            border-color: var(--tk-primary);
            color: var(--tk-primary);
        }

        .dir-filter-btn.active {
            background: var(--tk-primary);
            border-color: var(--tk-primary);
            color: #fff;
        }

        .dept-header {
            margin-bottom: 18px;
        }

        .dept-header h5 {
            color: var(--tk-text);
            font-weight: 700;
            letter-spacing: .2px;
        }

        .dept-count {
            background: var(--tk-primary-soft);
            color: #2563a8;
            font-weight: 700;
            font-size: 11.5px;
            padding: 4px 11px;
            border-radius: 999px;
        }

        /* Card Container Optimization */
        .tel-card-ui {
            border-radius: 16px;
            background: #fff;
            transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            will-change: transform, box-shadow;
            backface-visibility: hidden;
        }

        .tel-card-ui:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.75rem 1.75rem rgba(0, 0, 0, 0.08) !important;
        }

        /* Profile Picture Hover Scaling */
        .tel-avatar-wrapper img {
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            will-change: transform;
        }

        .tel-card-ui:hover .tel-avatar-wrapper img {
            transform: scale(1.06);
        }

        /* Typography & Component Micro-adjustments */
        .tel-card-ui h5 {
            font-size: 1.05rem;
            line-height: 1.3;
        }

        .tel-card-ui .small {
            font-size: 0.825rem;
        }

        .tel-card-ui .badge {
            font-size: 0.8125rem;
            letter-spacing: 0.2px;
            line-height: 1.4;
        }

        .tel-name {
            font-size: 15px;
            font-weight: 700;
            color: var(--tk-text);
        }

        .tel-footer {
            background: var(--tk-bg) !important;
            border-color: var(--tk-border) !important;
        }

        .tel-copy-btn {
            border-color: var(--tk-border) !important;
            color: var(--tk-text-muted);
            transition: border-color .12s ease, color .12s ease, background .12s ease;
        }

        .tel-copy-btn:hover {
            border-color: var(--tk-primary) !important;
            color: var(--tk-primary);
            background: var(--tk-primary-soft) !important;
        }

        /* Committee Dropdown Custom Styling */
        .comm-filter-card {
            background: #ffffff;
            padding: 16px 20px;
            border-radius: var(--tk-radius, 12px);
            border: 1px solid var(--tk-border, #e8eaee);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .comm-filter-card:hover {
            border-color: var(--tk-primary, #7cb9ff);
        }

        .custom-comm-select {
            border-radius: 10px !important;
            border: 1.5px solid var(--tk-border, #e8eaee) !important;
            font-size: 0.95rem !important;
            font-weight: 500 !important;
            color: var(--tk-text, #1f2430) !important;
            background-color: var(--tk-bg, #f7f8fa) !important;
            padding: 12px 16px !important;
            cursor: pointer;
            transition: all 0.2s ease-in-out !important;
        }

        /* Hover at Focus States */
        .custom-comm-select:hover {
            background-color: #ffffff !important;
            border-color: var(--tk-primary, #7cb9ff) !important;
        }

        .custom-comm-select:focus {
            background-color: #ffffff !important;
            border-color: #4e96f0 !important;
            box-shadow: 0 0 0 4px var(--tk-primary-soft, #eaf3ff) !important;
            outline: none !important;
        }

        /* Styling para sa mismong options list */
        .custom-comm-select option {
            padding: 10px;
            font-weight: 500;
            color: #333;
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
        <div class="container-xxl flex-grow-1 container-p-y">
            <div class="pf-wrap">

                <div class="row">
                    <div class="col-12">
                        <div class="pf-card mb-6">
                            <div class="user-profile-header-banner">
                                <div class="cover-photo-container"
                                    style="height: 300px; overflow: hidden; position: relative;">
                                    <div id="coverCarousel" class="carousel slide" data-bs-ride="carousel"
                                        data-bs-interval="10000" style="height: 100%;">
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
                                        <div class="carousel-inner" style="height: 100%;">
                                            <div class="carousel-item active" style="height: 100%;">
                                                <img src="../assets/img/carousel/new-web.png"
                                                    class="d-block w-100 h-100" style="object-fit: cover;"
                                                    alt="Cover 1">
                                            </div>
                                            <div class="carousel-item" style="height: 100%;">
                                                <img src="../assets/img/carousel/tell2.png" class="d-block w-100 h-100"
                                                    style="object-fit: cover;" alt="Cover 2">
                                            </div>
                                            <div class="carousel-item" style="height: 100%;">
                                                <img src="../assets/img/carousel/gm.png" class="d-block w-100 h-100"
                                                    style="object-fit: cover;" alt="Cover 3">
                                            </div>
                                            <div class="carousel-item" style="height: 100%;">
                                                <img src="../assets/img/carousel/6.png" class="d-block w-100 h-100"
                                                    style="object-fit: cover;" alt="Cover 4">
                                            </div>
                                            <div class="carousel-item" style="height: 100%;">
                                                <img src="../assets/img/carousel/1.png" class="d-block w-100 h-100"
                                                    style="object-fit: cover;" alt="Cover 5">
                                            </div>
                                            <div class="carousel-item" style="height: 100%;">
                                                <img src="../assets/img/carousel/3.png" class="d-block w-100 h-100"
                                                    style="object-fit: cover;" alt="Cover 6">
                                            </div>
                                        </div>
                                        <button class="carousel-control-prev" type="button"
                                            data-bs-target="#coverCarousel" data-bs-slide="prev">
                                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                            <span class="visually-hidden">Previous</span>
                                        </button>
                                        <button class="carousel-control-next" type="button"
                                            data-bs-target="#coverCarousel" data-bs-slide="next">
                                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                            <span class="visually-hidden">Next</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="user-profile-header d-flex flex-column flex-lg-row text-sm-start text-center mb-8">
                                <div class="flex-shrink-0 mt-1 mx-sm-0 mx-auto">
                                    <img src="<?php echo !empty($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : '../assets/img/avatars/default_dp.jpg'; ?>"
                                        alt="user-avatar" class="d-block h-80 ms-0 ms-sm-6 rounded-5 profile-img"
                                        id="uploadedAvatar">
                                </div>
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
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                    <rect x="3" y="4" width="18" height="16" rx="2" />
                                                    <path d="M3 9h18M9 21V9" />
                                                </svg>
                                                Emp No: <?php echo htmlspecialchars($emp_id); ?>
                                            </span>
                                        </div>
                                        <div class="d-flex flex-column align-items-md-end align-items-center gap-2">
                                            <a href="https://cwd.com.ph/" target="_blank" class="pf-visit-btn">
                                                <i class="bx bx-globe"></i>
                                                <span>Visit Our New Website</span>
                                            </a>
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
                                    <a class="nav-link" href="profileTeams.php"><i
                                            class="icon-base bx bx-group icon-sm me-1_5"></i> Teams</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="tell.php"><i
                                            class="icon-base bx bx-phone icon-sm me-1_5"></i> Local Directory</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link active" href="comms.php"><i
                                            class="icon-base bx bx-sitemap icon-sm me-1_5"></i> Committees</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <?php

                // ==========================================
                // MEMORANDUM CIRCULAR NO. 2026-030 COMMITTEES DATA
                // ==========================================
                
                $committees_data = [
                    [
                        'committee_name' => 'Bids and Awards Committee (BAC)',
                        'description' => 'BR 10, S. 2025 Hold over capacity',
                        'members' => [
                            ['name' => 'Engr. Joselito A. Gillera', 'designation' => 'Chairman'],
                            ['name' => 'Engr. Elizaldy O. Novillos', 'designation' => 'Vice-Chairman'],
                            ['name' => 'Engr. Bernard Joseph G. Rodriguez', 'designation' => 'Member'],
                            ['name' => 'Engr. Ranely S. Cartago', 'designation' => 'Member'],
                            ['name' => 'Ronnie G. Sierva', 'designation' => 'Member'],
                            ['name' => 'Rolando M. Pizarra', 'designation' => 'BAC Secretariat Head'],
                            ['name' => 'Beverly Joy B. Acierto', 'designation' => 'BAC Secretariat Member'],
                            ['name' => 'Engr. Ronaldo V. Baro', 'designation' => 'Technical Working Group Head'],
                            ['name' => 'Engr. John Carlo R. Alibutod', 'designation' => 'Technical Working Group Member'],
                            ['name' => 'Paul G. Evangelista', 'designation' => 'Technical Working Group Member'],
                            ['name' => 'Rogelio E. Baricanosa', 'designation' => 'Technical Working Group Member'],
                            ['name' => 'Engr. Renante A. Capitle', 'designation' => 'Technical Working Group Member'],
                            ['name' => 'Jovi Anne D. Dizon', 'designation' => 'Technical Working Group Member'],
                        ]
                    ],
                    [
                        'committee_name' => 'Business Plan Committee',
                        'description' => '',
                        'members' => [
                            ['name' => 'Mr. Exequiel A. Aguilar, Jr.', 'designation' => 'Chairperson'],
                            ['name' => 'Mercedes A. Carreon', 'designation' => 'Member'],
                            ['name' => 'Emanuel B. Capulong', 'designation' => 'Member'],
                            ['name' => 'Maureen Rose B. Masa', 'designation' => 'Member'],
                            ['name' => 'Ma. Carmela M. Elepaño', 'designation' => 'Member'],
                            ['name' => 'Junsy Ybuan-Nieron', 'designation' => 'Member'],
                            ['name' => 'Engr. Alberto L. Mabalot, Jr.', 'designation' => 'Member'],
                            ['name' => 'Ronnie G. Sierva', 'designation' => 'Member'],
                            ['name' => 'Henry S. Junio', 'designation' => 'Member'],
                            ['name' => 'Shirmae Joy G. Magsino', 'designation' => 'Secretariat'],
                        ]
                    ],
                    [
                        'committee_name' => 'CNA Monitoring Committee',
                        'description' => '',
                        'members' => [
                            ['name' => 'Edwin L. Cartago', 'designation' => 'Chairperson'],
                            ['name' => 'Mercedes A. Carreon', 'designation' => 'Member'],
                            ['name' => 'Ma. Carmela M. Elepaño', 'designation' => 'Member'],
                            ['name' => 'Engr. Ranely S. Cartago', 'designation' => 'Member'],
                            ['name' => 'Engr. Joselito A. Gillera', 'designation' => 'Member'],
                            ['name' => 'Elenita V. Panganiban', 'designation' => 'Member'],
                            ['name' => 'Junsy Ybuan-Nieron', 'designation' => 'Member'],
                            ['name' => 'Anatolio C. Maiquez', 'designation' => 'Member'],
                            ['name' => 'John Norman L. Tidon', 'designation' => '2nd Level Representative'],
                            ['name' => 'Leo V. Resano', 'designation' => '2nd Level Alternate'],
                            ['name' => 'Ma. Carminda G. Paringit', 'designation' => '1st Level Representative'],
                            ['name' => 'Geminiano A. Gevaña, Jr.', 'designation' => '1st Level Alternate'],
                        ]
                    ],
                    [
                        'committee_name' => 'CNA Panel - Management',
                        'description' => '',
                        'members' => [
                            ['name' => 'Edwin L. Cartago', 'designation' => 'Chairperson'],
                            ['name' => 'Mercedes A. Carreon', 'designation' => 'Member'],
                            ['name' => 'Ma. Carmela M. Elepaño', 'designation' => 'Member'],
                            ['name' => 'Engr. Ranely S. Cartago', 'designation' => 'Member'],
                            ['name' => 'Engr. Joselito A. Gillera', 'designation' => 'Member'],
                            ['name' => 'Elenita V. Panganiban', 'designation' => 'Member'],
                            ['name' => 'Emanuel B. Capulong', 'designation' => 'Member'],
                            ['name' => 'Corporate Attorney', 'designation' => 'Member'],
                            ['name' => 'Beverly Joy B. Acierto', 'designation' => 'Secretariat'],
                            ['name' => 'Shirmae Joy G. Magsino', 'designation' => 'Alternate Secretariat'],
                        ]
                    ],
                    [
                        'committee_name' => 'Committee on Anti-Red Tape (CART)',
                        'description' => '',
                        'members' => [
                            ['name' => 'Mr. Exequiel A. Aguilar, Jr.', 'designation' => 'Chairperson'],
                            ['name' => 'Ma. Carmela M. Elepaño', 'designation' => 'Vice Chairperson'],
                            ['name' => 'Edwin L. Cartago', 'designation' => 'Member'],
                            ['name' => 'Engr. Ranely S. Cartago', 'designation' => 'Member'],
                            ['name' => 'Engr. Joselito A. Gillera', 'designation' => 'Member'],
                            ['name' => 'Elenita V. Panganiban', 'designation' => 'Member'],
                            ['name' => 'Engr. Jonathan Dave A. Fajarda', 'designation' => 'Member'],
                            ['name' => 'Emanuel B. Capulong', 'designation' => 'Member'],
                            ['name' => 'Ronnie G. Sierva', 'designation' => 'Member'],
                            ['name' => 'Geraldine G. Manguiat', 'designation' => 'Focal Person'],
                            ['name' => 'Verra Mae Mariano', 'designation' => 'Secretariat'],
                            ['name' => 'Mitch D. Mendoza', 'designation' => 'Secretariat'],
                        ]
                    ],
                    [
                        'committee_name' => 'Committee on Decorum and Investigation on Sexual Harassment (CODI)',
                        'description' => '',
                        'members' => [
                            ['name' => 'Pablita L. Rapal', 'designation' => 'Chairperson'],
                            ['name' => 'Inocentes G. Mendoza', 'designation' => 'Member'],
                            ['name' => 'Henry S. Junio', 'designation' => 'Member'],
                            ['name' => 'Emanuel B. Capulong', 'designation' => 'Member'],
                            ['name' => 'Emma A. Fandiño', 'designation' => '2nd Level Representative'],
                            ['name' => 'Geminiano A. Gevaña, Jr.', 'designation' => '2nd Level Alternate'],
                        ]
                    ],
                    [
                        'committee_name' => 'Disaster Management Team',
                        'description' => '',
                        'members' => [
                            ['name' => 'Engr. Joselito A. Gillera', 'designation' => 'Command Post'],
                            ['name' => 'Engr. Elizaldy O. Novillos', 'designation' => 'Chairperson'],
                            ['name' => 'Engr. Bernard Joseph G. Rodriguez', 'designation' => 'Chair, Fire Brigade Team'],
                            ['name' => 'Teodorico T. Ortiz', 'designation' => 'Chair, Damage Team'],
                            ['name' => 'Jonathan M. Rico', 'designation' => 'Chair, Communication Team'],
                            ['name' => 'Engr. Rolando V. Baro', 'designation' => 'Chair, Rescue Team'],
                            ['name' => 'Engr. Alberto L. Mabalot, Jr.', 'designation' => 'Co-Chair, Rescue Team'],
                            ['name' => 'Oliver V. Pasayan', 'designation' => 'Member, Rescue Team'],
                            ['name' => 'Darwin Salinas', 'designation' => 'Chair, Evacuation Team'],
                            ['name' => 'Teodoro L. Fajardo', 'designation' => 'Co-Chair, Evacuation Team'],
                            ['name' => 'Eddie M. Leona', 'designation' => 'Alternate Member (NLM)'],
                            ['name' => 'Geminiano A. Gevaña, Jr.', 'designation' => 'Chair, Logistics and Supply Team'],
                            ['name' => 'Emmanuel T. Salvador', 'designation' => 'Member, Logistics and Supply Team'],
                            ['name' => 'Geraldine G. Manguiat', 'designation' => 'Secretariat / Chair, Medical Team'],
                            ['name' => 'Paulo L. Bonifacio', 'designation' => 'Member, Medical Team'],
                        ]
                    ],
                    [
                        'committee_name' => 'Emergency Response Committee (ERC)',
                        'description' => '',
                        'members' => [
                            ['name' => 'Mr. Exequiel A. Aguilar, Jr.', 'designation' => 'Chairperson'],
                            ['name' => 'Engr. Joselito A. Gillera', 'designation' => 'Co-Chairperson'],
                            ['name' => 'Engr. Ranely S. Cartago', 'designation' => 'Member'],
                            ['name' => 'Edwin L. Cartago', 'designation' => 'Member'],
                            ['name' => 'Engr. Elizaldy O. Novillos', 'designation' => 'Member'],
                            ['name' => 'Engr. Bernard Joseph G. Rodriguez', 'designation' => 'Member'],
                            ['name' => 'Engr. Rolando V. Baro', 'designation' => 'Member'],
                            ['name' => 'Engr. Alberto L. Mabalot Jr.', 'designation' => 'Member'],
                            ['name' => 'Emmanuel T. Salvador', 'designation' => 'Member'],
                            ['name' => 'Pedro M. Estalar', 'designation' => 'Member'],
                            ['name' => 'Odin G. Sanchez', 'designation' => 'Member'],
                            ['name' => 'Emanuel B. Capulong', 'designation' => 'Member'],
                            ['name' => 'Ronnie G. Sierva', 'designation' => 'Member'],
                            ['name' => 'Anatolio C. Maiquez', 'designation' => 'Member'],
                            ['name' => 'Geminiano A. Gevaña Jr.', 'designation' => 'Member'],
                            ['name' => 'Geraldine G. Manguiat', 'designation' => 'Member'],
                            ['name' => 'Jovi Anne D. Dizon', 'designation' => 'Secretariat'],
                        ]
                    ],
                    [
                        'committee_name' => 'Energy Committee',
                        'description' => '',
                        'members' => [
                            ['name' => 'Engr. Joselito A. Gillera', 'designation' => 'Chairperson'],
                            ['name' => 'Engr. Elizaldy O. Novillos', 'designation' => 'Vice-Chairperson'],
                            ['name' => 'Engr. Nazelon J. Marfori', 'designation' => 'Designated Energy Manager'],
                            ['name' => 'Engr. Lawrence Guinto', 'designation' => 'Member'],
                            ['name' => 'Engr. Fernando H. Arellano, Jr.', 'designation' => 'Member'],
                            ['name' => 'Engr. Jonathan Dave A. Fajarda', 'designation' => 'Member'],
                            ['name' => 'Pedro M. Estalar', 'designation' => 'Member'],
                            ['name' => 'Teodorico T. Ortiz', 'designation' => 'Member'],
                            ['name' => 'Paul G. Evangelista', 'designation' => 'Member'],
                            ['name' => 'Maria Claryl T. Salumbides', 'designation' => 'Member'],
                            ['name' => 'Geraldine G. Manguiat', 'designation' => 'Secretariat'],
                        ]
                    ],
                    [
                        'committee_name' => 'Freedom of Information (FOI) Committee',
                        'description' => '',
                        'members' => [
                            ['name' => 'Corporate Attorney', 'designation' => 'FOI Appeal and Review Committee Head'],
                            ['name' => 'Mr. Exequiel A. Aguilar, Jr.', 'designation' => 'FOI Final Decision Maker'],
                            ['name' => 'Edwin L. Cartago', 'designation' => 'Decision Maker'],
                            ['name' => 'Engr. Joselito A. Gillera', 'designation' => 'Decision Maker'],
                            ['name' => 'Ma. Carmela M. Elepaño', 'designation' => 'Decision Maker'],
                            ['name' => 'Mercedes A. Carreon', 'designation' => 'Decision Maker'],
                            ['name' => 'Engr. Ranely S. Cartago', 'designation' => 'Decision Maker'],
                            ['name' => 'Geraldine G. Manguiat', 'designation' => 'FOI Receiving Officer'],
                            ['name' => 'Engr. Jonathan Dave A. Fajarda', 'designation' => 'FOI Alternate Receiving Officer'],
                        ]
                    ],
                    [
                        'committee_name' => 'GAD Executive Committee',
                        'description' => '',
                        'members' => [
                            ['name' => 'Mr. Exequiel A. Aguilar, Jr.', 'designation' => 'Focal Person'],
                            ['name' => 'Edwin L. Cartago', 'designation' => 'Member'],
                            ['name' => 'Mercedes A. Carreon', 'designation' => 'Member'],
                            ['name' => 'Ma. Carmela M. Elepaño', 'designation' => 'Member'],
                            ['name' => 'Engr. Ranely S. Cartago', 'designation' => 'Member'],
                            ['name' => 'Engr. Joselito A. Gillera', 'designation' => 'Member'],
                            ['name' => 'Maribeth R. Gratela', 'designation' => '2nd Level Representative'],
                            ['name' => 'Emma A. Fandiño', 'designation' => '1st Level Representative'],
                        ]
                    ],
                    [
                        'committee_name' => 'GAD Technical Working Group (TWG)',
                        'description' => '',
                        'members' => [
                            ['name' => 'Ma. Carmela M. Elepaño', 'designation' => 'Chairperson'],
                            ['name' => 'Geraldine G. Manguiat', 'designation' => 'Member / Secretariat Head'],
                            ['name' => 'Ma. Carminda G. Paringit', 'designation' => 'Member'],
                            ['name' => 'Florante F. Durolfo', 'designation' => 'Member'],
                            ['name' => 'Reynet L. Khan', 'designation' => 'Member'],
                            ['name' => 'Ethel O. Paderes', 'designation' => 'Member'],
                        ]
                    ],
                    [
                        'committee_name' => 'GAD Secretariat',
                        'description' => '',
                        'members' => [
                            ['name' => 'Geraldine G. Manguiat', 'designation' => 'Head'],
                            ['name' => 'Shirmae Joy G. Magsino', 'designation' => 'Member'],
                            ['name' => 'Ruth Ann C. Llarena', 'designation' => 'Member'],
                            ['name' => 'Karen Mae S. Calderon', 'designation' => 'Member'],
                            ['name' => 'Depro Mari A. Alinsunurin', 'designation' => 'Member'],
                            ['name' => 'Joana Leana Mae V. Villanueva', 'designation' => 'Member'],
                            ['name' => 'Leo Piamonte', 'designation' => 'Member'],
                        ]
                    ],
                    [
                        'committee_name' => 'GAD Project Implementation & Management Monitoring & Evaluation (PIMME) Team',
                        'description' => '',
                        'members' => [
                            ['name' => 'Engr. Elizaldy O. Novillos', 'designation' => 'Member'],
                            ['name' => 'Engr. John Carlo R. Alibutod', 'designation' => 'Member'],
                            ['name' => 'Engr. Miccoh N. Quimio', 'designation' => 'Member'],
                            ['name' => 'Engr. Renante A. Capitle', 'designation' => 'Member'],
                        ]
                    ],
                    [
                        'committee_name' => 'Grievance Machinery Committee',
                        'description' => '',
                        'members' => [
                            ['name' => 'Edwin L. Cartago', 'designation' => 'Chairperson'],
                            ['name' => 'Elenita V. Panganiban', 'designation' => 'Vice-Chairperson'],
                            ['name' => 'Engr. Elizaldy O. Novillos', 'designation' => 'Member'],
                            ['name' => 'Ronnie G. Sierva', 'designation' => 'Member'],
                            ['name' => 'Anatolio C. Maiquez', 'designation' => 'Member'],
                            ['name' => 'Emma A. Fandiño', 'designation' => '2nd Level Representative'],
                            ['name' => 'John Norman L. Tidon', 'designation' => '1st Level Representative'],
                            ['name' => 'Ronaldo M. Javier', 'designation' => '1st Level Alternate'],
                            ['name' => 'Geraldine G. Manguiat', 'designation' => 'Secretariat'],
                        ]
                    ],
                    [
                        'committee_name' => 'Groundwater Assessment (GWA) Committee',
                        'description' => '',
                        'members' => [
                            ['name' => 'Engr. Elizaldy O. Novillos', 'designation' => 'Chairperson'],
                            ['name' => 'Maria Ana S. De Ocampo', 'designation' => 'Member'],
                            ['name' => 'Emanuel B. Capulong', 'designation' => 'Member'],
                            ['name' => 'Jovi Anne D. Dizon', 'designation' => 'Member'],
                            ['name' => 'Jonathan M. Rico', 'designation' => 'Member'],
                            ['name' => 'Bernilo Jose B. Morales', 'designation' => 'Member'],
                        ]
                    ],
                    [
                        'committee_name' => 'Human Resource Development Committee (HRDC)',
                        'description' => '',
                        'members' => [
                            ['name' => 'Elenita V. Panganiban', 'designation' => 'Chairperson'],
                            ['name' => 'Edwin L. Cartago', 'designation' => 'Member'],
                            ['name' => 'Mercedes A. Carreon', 'designation' => 'Member'],
                            ['name' => 'Ma. Carmela M. Elepaño', 'designation' => 'Member'],
                            ['name' => 'Engr. Joselito A. Gillera', 'designation' => 'Member'],
                            ['name' => 'Engr. Ranely S. Cartago', 'designation' => 'Member'],
                            ['name' => 'Maribeth R. Gratela', 'designation' => '2nd Level Representative'],
                            ['name' => 'Anatolio C. Maiquez', 'designation' => '2nd Level Alternate'],
                            ['name' => 'Teodoro Fajardo', 'designation' => '1st Level Representative'],
                            ['name' => 'Geraldine G. Manguiat', 'designation' => 'Secretariat'],
                        ]
                    ],
                    [
                        'committee_name' => 'Human Resource Merit Promotion and Selection Board (HRMPSB)',
                        'description' => '',
                        'members' => [
                            ['name' => 'Edwin L. Cartago', 'designation' => 'Chairperson'],
                            ['name' => 'Mercedes A. Carreon', 'designation' => 'Vice Chairperson'],
                            ['name' => 'Elenita V. Panganiban', 'designation' => 'Member'],
                            ['name' => 'Susan P. Mac', 'designation' => 'Member'],
                            ['name' => 'Ma. Carmela M. Elepaño', 'designation' => 'Member'],
                            ['name' => 'Engr. Ranely S. Cartago', 'designation' => 'Member'],
                            ['name' => 'Engr. Joselito A. Gillera', 'designation' => 'Member'],
                            ['name' => 'Anatolio C. Maiquez', 'designation' => 'Member'],
                            ['name' => 'John Norman L. Tidon', 'designation' => '2nd Level Representative'],
                            ['name' => 'Emma A. Fandiño', 'designation' => '2nd Level Alternate'],
                            ['name' => 'Teodoro L. Fajardo', 'designation' => '1st Level Representative'],
                            ['name' => 'Janine M. Caluag', 'designation' => '1st Level Alternate'],
                            ['name' => 'Pablita L. Rapal', 'designation' => 'Secretariat'],
                        ]
                    ],
                    [
                        'committee_name' => 'Inspection and Acceptance Team',
                        'description' => '',
                        'members' => [
                            ['name' => 'Engr. Joselito A. Gillera', 'designation' => 'Chairperson'],
                            ['name' => 'Mercedes A. Carreon', 'designation' => 'Member'],
                            ['name' => 'Edwin L. Cartago', 'designation' => 'Member'],
                            ['name' => 'Engr. Bernard Joseph G. Rodriguez', 'designation' => 'Member'],
                            ['name' => 'Engr. Nazelon J. Marfori', 'designation' => 'Member'],
                            ['name' => 'Junsy Ybuan-Nieron', 'designation' => 'Member'],
                        ]
                    ],
                    [
                        'committee_name' => 'Inventory Committee',
                        'description' => '',
                        'members' => [
                            ['name' => 'Mr. Edwin L. Cartago', 'designation' => 'Chairperson'],
                            ['name' => 'Mercedes A. Carreon', 'designation' => 'Member'],
                            ['name' => 'Ms. Ma. Carmela M. Elepaño', 'designation' => 'Member'],
                            ['name' => 'Engr. Ranely S. Cartago', 'designation' => 'Member'],
                            ['name' => 'Engr. Joselito A. Gillera', 'designation' => 'Member'],
                            ['name' => 'Junsy Ybuan-Nieron', 'designation' => 'Member'],
                            ['name' => 'Mr. Emmanuel T. Salvador', 'designation' => 'Member'],
                            ['name' => 'Ms. Remedios L. Marfori', 'designation' => 'TWG - Head'],
                            ['name' => 'Remedios L. Marfori', 'designation' => 'Sub-Comm: Inventory Taking Chair'],
                            ['name' => 'Mercedes A. Carreon', 'designation' => 'Sub-Comm: Inventory Taking Chair'],
                            ['name' => 'Mary Grace G. Magsino', 'designation' => 'Team Leader'],
                            ['name' => 'Eileen C. Reyes', 'designation' => 'Team Leader'],
                            ['name' => 'Geminiano Gevana, Jr.', 'designation' => 'Sub-Comm: Reconciliation Chair'],
                            ['name' => 'Romela Marie J. Manaig', 'designation' => 'Sub-Comm: Reconciliation Chair'],
                            ['name' => 'Glicel M. Sarmiento', 'designation' => 'Team Leader'],
                            ['name' => 'Miracle V. Salamat', 'designation' => 'Team Leader'],
                            ['name' => 'Junsy Ybuan-Nieron', 'designation' => 'Sub-Comm: Investigation Chair'],
                            ['name' => 'Jinkie Joy L. Custodio', 'designation' => 'Sub-Comm: Investigation Member'],
                            ['name' => 'John Norman L. Tidon', 'designation' => 'Sub-Comm: Investigation Member'],
                        ]
                    ],
                    [
                        'committee_name' => 'Investigation Committee',
                        'description' => '',
                        'members' => [
                            ['name' => 'Edwin L. Cartago', 'designation' => 'Chairperson'],
                            ['name' => 'Elenita V. Panganiban', 'designation' => 'Member'],
                            ['name' => 'Henry S. Junio', 'designation' => 'Member'],
                            ['name' => 'Emanuel B. Capulong', 'designation' => 'Member'],
                            ['name' => 'Engr. Bernard Joseph Rodriguez', 'designation' => 'Member'],
                            ['name' => 'Anatolio C. Maiquez', 'designation' => 'Member'],
                            ['name' => 'Emma A. Fandiño', 'designation' => '2nd Level Representative'],
                            ['name' => 'Geminiano A. Gevaña, Jr.', 'designation' => '2nd Level Alternate'],
                            ['name' => 'John Norman L. Tidon', 'designation' => '1st Level Representative'],
                            ['name' => 'Guillermo DJ. Banalan', 'designation' => '1st Level Alternate'],
                            ['name' => 'Maureen Rose B. Masa', 'designation' => 'Secretariat'],
                        ]
                    ],
                    [
                        'committee_name' => 'ISO Committee',
                        'description' => '',
                        'members' => [
                            ['name' => 'Engr. Joselito A. Gillera', 'designation' => 'ISO Head / Facilitator'],
                            ['name' => 'Geraldine G. Manguiat', 'designation' => 'QMR / Deputy Facilitator'],
                            ['name' => 'Ethel O. Paderes', 'designation' => 'Document Control Custodian (DCC)'],
                            ['name' => 'Engr. Bernard Joseph G. Rodriguez', 'designation' => 'Lead Auditor'],
                            ['name' => 'Maribeth R. Gratela', 'designation' => 'Internal Auditor'],
                            ['name' => 'Maria Lourdes B. Tan', 'designation' => 'Internal Auditor'],
                            ['name' => 'Gregoria B. Olea', 'designation' => 'Internal Auditor'],
                            ['name' => 'Florante F. Durolfo', 'designation' => 'Internal Auditor'],
                            ['name' => 'Engr. John Carlo R. Alibutod', 'designation' => 'Internal Auditor'],
                            ['name' => 'Engr. Vergell C. David', 'designation' => 'Internal Auditor'],
                            ['name' => 'Abby Mae S. Espiel', 'designation' => 'Internal Auditor'],
                            ['name' => 'Joseph Bernard G. Quiatchon', 'designation' => 'Internal Auditor'],
                            ['name' => 'Shirmae Joy G. Magsino', 'designation' => 'Internal Auditor'],
                            ['name' => 'Mitch D. Mendoza', 'designation' => 'Internal Auditor'],
                            ['name' => 'Lorna P. Siman', 'designation' => 'Internal Auditor'],
                            ['name' => 'Reynet L. Khan', 'designation' => 'Internal Auditor'],
                            ['name' => 'John Norman L. Tidon', 'designation' => 'Internal Auditor'],
                            ['name' => 'John Paul C. Tejada', 'designation' => 'Internal Auditor'],
                            ['name' => 'Ma. Carminda G. Paringit', 'designation' => 'Internal Auditor'],
                            ['name' => 'Ma. Claryl Ann S. Talaga', 'designation' => 'Internal Auditor'],
                            ['name' => 'Ceferino O. Legaspi', 'designation' => 'Internal Auditor'],
                            ['name' => 'Engr. Miccoh N. Quimio', 'designation' => 'Internal Auditor'],
                            ['name' => 'Odin G. Sanchez', 'designation' => 'Internal Auditor'],
                        ]
                    ],
                    [
                        'committee_name' => 'ISSP Steering Committee',
                        'description' => '',
                        'members' => [
                            ['name' => 'Remedios L. Marfori', 'designation' => 'Chairperson'],
                            ['name' => 'Rolando V. Baro', 'designation' => 'Member'],
                            ['name' => 'Henry S. Junio', 'designation' => 'Member'],
                            ['name' => 'Engr. Nazelon J. Marfori', 'designation' => 'Member'],
                            ['name' => 'Junsy Ybuan-Nieron', 'designation' => 'Member'],
                            ['name' => 'Mercedes A. Carreon', 'designation' => 'Member'],
                        ]
                    ],
                    [
                        'committee_name' => 'ISSP Technical Working Group',
                        'description' => '',
                        'members' => [
                            ['name' => 'Engr. Jonathan Dave A. Fajarda', 'designation' => 'Chairperson'],
                            ['name' => 'Lenard Martin G. Tancangco', 'designation' => 'Member - OGM'],
                            ['name' => 'John Paul C. Tejada', 'designation' => 'Member - Admin Dept.'],
                            ['name' => 'Ma. Claryl Ann S. Talaga', 'designation' => 'Member - Fin. Dept.'],
                            ['name' => 'Jewelle Dela Raga', 'designation' => 'Member - Comm\'l Dept.'],
                            ['name' => 'Engr. Vergel David', 'designation' => 'Member - Tech. Serv. Dept.'],
                            ['name' => 'Depro Mari A. Alinsunurin', 'designation' => 'Member - Operations Dept.'],
                        ]
                    ],
                    [
                        'committee_name' => 'Performance Management Team (PMT)',
                        'description' => '',
                        'members' => [
                            ['name' => 'Edwin L. Cartago', 'designation' => 'Chairperson / Champion'],
                            ['name' => 'Mercedes A. Carreon', 'designation' => 'Member'],
                            ['name' => 'Elenita V. Panganiban', 'designation' => 'Member'],
                            ['name' => 'Ma. Carmela M. Elepaño', 'designation' => 'Member'],
                            ['name' => 'Engr. Ranely S. Cartago', 'designation' => 'Member'],
                            ['name' => 'Engr. Joselito A. Gillera', 'designation' => 'Member'],
                            ['name' => 'Engr. Jonathan Dave A. Fajarda', 'designation' => 'Member'],
                            ['name' => 'Anatolio C. Maiquez', 'designation' => 'Member'],
                            ['name' => 'Emma A. Fandiño', 'designation' => '2nd Level Representative'],
                            ['name' => 'Rolando M. Pizarra', 'designation' => '2nd Level Alternate'],
                            ['name' => 'Teodoro Fajardo', 'designation' => '1st Level Representative'],
                            ['name' => 'Ambrosio Jamila', 'designation' => '1st Level Alternate'],
                            ['name' => 'Pablita L. Rapal', 'designation' => 'Secretariat'],
                        ]
                    ],
                    [
                        'committee_name' => 'Program on Awards and Incentives for Service Excellence (PRAISE)',
                        'description' => '',
                        'members' => [
                            ['name' => 'Edwin L. Cartago', 'designation' => 'Chairperson'],
                            ['name' => 'Ma. Carmela Elepaño', 'designation' => 'Member'],
                            ['name' => 'Elenita V. Panganiban', 'designation' => 'Member'],
                            ['name' => 'Anatolio C. Maiquez', 'designation' => 'Member'],
                            ['name' => 'Rolando M. Pizarra', 'designation' => '2nd Level Representative'],
                            ['name' => 'Maribeth R. Gratela', 'designation' => '2nd Level Alternate'],
                            ['name' => 'Ambrosio Jamila', 'designation' => '1st Level Representative'],
                            ['name' => 'Miracle V. Salamat', 'designation' => '1st Level Alternate'],
                            ['name' => 'Gregoria B. Olea', 'designation' => 'Secretariat'],
                        ]
                    ],
                    [
                        'committee_name' => 'Project Evaluation Committee',
                        'description' => '',
                        'members' => [
                            ['name' => 'Engr. Ranely S. Cartago', 'designation' => 'Chairperson'],
                            ['name' => 'Engr. Rolando V. Baro', 'designation' => 'Member'],
                            ['name' => 'Engr. Fernando H. Arellano, Jr.', 'designation' => 'Member'],
                            ['name' => 'Engr. Elizaldy O. Novillos', 'designation' => 'Member'],
                            ['name' => 'Engr. Nazelon J. Marfori', 'designation' => 'Member'],
                            ['name' => 'Jonathan M. Rico', 'designation' => 'Member'],
                            ['name' => 'Mercedes A. Carreon', 'designation' => 'Member'],
                            ['name' => 'Joana Leana Mae V. Villanueva', 'designation' => 'Secretariat'],
                        ]
                    ],
                    [
                        'committee_name' => 'Project Monitoring Committee',
                        'description' => '',
                        'members' => [
                            ['name' => 'Engr. Joselito A. Gillera', 'designation' => 'Chairperson'],
                            ['name' => 'Engr. Vergell C. David', 'designation' => 'Member'],
                            ['name' => 'Engr. Renante A. Capitle', 'designation' => 'Member'],
                            ['name' => 'Engr. Elizaldy O. Novillos', 'designation' => 'Member'],
                            ['name' => 'Maria Claryl Ann S. Talaga', 'designation' => 'Member'],
                            ['name' => 'Joana Leana Mae V. Villanueva', 'designation' => 'Secretariat'],
                        ]
                    ],
                    [
                        'committee_name' => 'Property Appraisal Committee',
                        'description' => '',
                        'members' => [
                            ['name' => 'Edwin L. Cartago', 'designation' => 'Chairperson'],
                            ['name' => 'Mercedes A. Carreon', 'designation' => 'Member'],
                            ['name' => 'Engr. Ranely S. Cartago', 'designation' => 'Member'],
                            ['name' => 'Engr. Joselito A. Gillera', 'designation' => 'Member'],
                            ['name' => 'Ma. Carmela M. Elepaño', 'designation' => 'Member'],
                            ['name' => 'Remedios L. Marfori', 'designation' => 'Secretariat Head'],
                        ]
                    ],
                    [
                        'committee_name' => 'Public Information Committee (PIC)',
                        'description' => '',
                        'members' => [
                            ['name' => 'Emanuel B. Capulong', 'designation' => 'Chairperson'],
                            ['name' => 'Ronnie G. Sierva', 'designation' => 'Member'],
                            ['name' => 'Jonathan M. Rico', 'designation' => 'Member'],
                            ['name' => 'Jewell C. Precilla', 'designation' => 'Member'],
                            ['name' => 'Jewelle T. Dela Raga', 'designation' => 'Member'],
                        ]
                    ],
                    [
                        'committee_name' => 'Records Management Improvement Committee (RMIC)',
                        'description' => '',
                        'members' => [
                            ['name' => 'Edwin L. Cartago', 'designation' => 'Chairperson'],
                            ['name' => 'John Paulo C. Tejada', 'designation' => 'Records Officer'],
                            ['name' => 'Paulo L. Bonifacio', 'designation' => 'Records Assistant'],
                            ['name' => 'Willy A. Vanguardia', 'designation' => 'Records Assistant'],
                            ['name' => 'Crecille R. Furto', 'designation' => 'Records Secretary'],
                            ['name' => 'Joana Leana Mae V. Villanueva', 'designation' => 'Sub-Committee Member'],
                            ['name' => 'Ambrosio M. Jamilla', 'designation' => 'Sub-Committee Member'],
                            ['name' => 'Jennifer M. Alano', 'designation' => 'Sub-Committee Member'],
                            ['name' => 'Shirmae Joy G. Magsino', 'designation' => 'Sub-Committee Member'],
                            ['name' => 'Ma. Dinah A. Arellano', 'designation' => 'Sub-Committee Member'],
                            ['name' => 'Marianito H. Villanueva', 'designation' => 'Sub-Committee Member'],
                            ['name' => 'Karen Mae S. Calderon', 'designation' => 'Sub-Committee Member'],
                            ['name' => 'Depro Mari L. Alinsunurin', 'designation' => 'Sub-Committee Member'],
                        ]
                    ],
                    [
                        'committee_name' => 'Safety and Health Committee',
                        'description' => '',
                        'members' => [
                            ['name' => 'Edwin L. Cartago', 'designation' => 'Chairperson'],
                            ['name' => 'Elenita V. Panganiban', 'designation' => 'Vice-Chairperson'],
                            ['name' => 'Emmanuel T. Salvador', 'designation' => 'Member'],
                            ['name' => 'Engr. Elizaldy O. Novillos', 'designation' => 'Member'],
                            ['name' => 'Anatolio C. Maiquez', 'designation' => 'Member'],
                            ['name' => 'Henry B. Junio', 'designation' => 'Member'],
                            ['name' => 'Shirmae Joy G. Magsino', 'designation' => 'Member'],
                            ['name' => 'Maribeth R. Gratela', 'designation' => '2nd Level Representative'],
                            ['name' => 'Emma A. Fandiño', 'designation' => '2nd Level Alternate'],
                            ['name' => 'Miracle V. Salamat', 'designation' => '1st Level Representative'],
                            ['name' => 'Geminiano A. Gevaña, Jr.', 'designation' => '1st Level Alternate'],
                            ['name' => 'Engr. Bernard Joseph G. Rodriguez', 'designation' => 'Overall Safety Officer'],
                            ['name' => 'Allan Morris M. Gecale', 'designation' => 'Safety Officer - Ground Floor'],
                            ['name' => 'Leo D. Legaspi', 'designation' => 'Safety Officer - 2nd Floor'],
                            ['name' => 'Engr. Nazelon J. Marfori', 'designation' => 'Safety Officer - 3rd Floor'],
                            ['name' => 'Angelo L. Del Prado', 'designation' => 'Safety Officer - 4th & 5th Floor'],
                            ['name' => 'Emmanuel T. Salvador', 'designation' => 'Safety Officer - Warehouse'],
                            ['name' => 'Ethel O. Paredes', 'designation' => 'Safety Officer - Upper BPS'],
                            ['name' => 'Ariel M. Mercado', 'designation' => 'Safety Officer - Lower BPS'],
                            ['name' => 'Engr. Miccoh N. Quimio', 'designation' => 'Safety Officer - STP Palo Alto'],
                            ['name' => 'Mr. Ramil Malabanan', 'designation' => 'Safety Officer - Dispatching'],
                            ['name' => 'Odin G. Sanchez, Jr.', 'designation' => 'TWG Head'],
                            ['name' => 'Paulo L. Bonifacio', 'designation' => 'TWG Member'],
                            ['name' => 'Joana Leana Mae V. Villanueva', 'designation' => 'TWG Member'],
                        ]
                    ],
                    [
                        'committee_name' => 'SALN Review and Compliance Committee',
                        'description' => '',
                        'members' => [
                            ['name' => 'Ma. Carmela M. Elepaño', 'designation' => 'Chairperson'],
                            ['name' => 'Edwin L. Cartago', 'designation' => 'Co-Chairperson'],
                            ['name' => 'Elenita V. Panganiban', 'designation' => 'Member'],
                            ['name' => 'Geminiano A. Gevaña, Jr.', 'designation' => '2nd Level Representative'],
                            ['name' => 'John Norman L. Tidon', 'designation' => '2nd Level Alternate'],
                            ['name' => 'Jennifer M. Ante', 'designation' => '1st Level Representative'],
                            ['name' => 'Ronaldo M. Javier', 'designation' => '1st Level Alternate'],
                            ['name' => 'Geraldine G. Manguiat', 'designation' => 'Secretariat'],
                        ]
                    ],
                    [
                        'committee_name' => 'Scrap Disposal and Appraisal Team',
                        'description' => '',
                        'members' => [
                            ['name' => 'Edwin L. Cartago', 'designation' => 'Chairperson'],
                            ['name' => 'Mercedes A. Carreon', 'designation' => 'Member'],
                            ['name' => 'Ma. Carmela M. Elepaño', 'designation' => 'Member'],
                            ['name' => 'Engr. Ranely S. Cartago', 'designation' => 'Member'],
                            ['name' => 'Engr. Joselito A. Gillera', 'designation' => 'Member'],
                            ['name' => 'Junsy Ybuan-Nieron', 'designation' => 'Member'],
                        ]
                    ],
                    [
                        'committee_name' => 'Special Events Committee',
                        'description' => '',
                        'members' => [
                            ['name' => 'Ma. Carmela M. Elepaño', 'designation' => 'Chairperson'],
                            ['name' => 'Geraldine G. Manguiat', 'designation' => 'Member'],
                            ['name' => 'Maribeth R. Gratela', 'designation' => 'Member'],
                            ['name' => 'Eileen C. Reyes', 'designation' => 'Member'],
                            ['name' => 'Maureen Rose B. Masa', 'designation' => 'Member'],
                            ['name' => 'Alpha Amor G. Manguiat', 'designation' => 'Member'],
                            ['name' => 'Rebecca B. Corpuz', 'designation' => 'Member'],
                            ['name' => 'Lenard Martin G. Tancangco', 'designation' => 'Member'],
                            ['name' => 'Depro Mari L. Alinsunurin', 'designation' => 'Member'],
                            ['name' => 'Emma A. Fandiño', 'designation' => '2nd Level Representative'],
                            ['name' => 'Miracle V. Salamat', 'designation' => '1st Level Representative'],
                        ]
                    ],
                    [
                        'committee_name' => 'Special HRMPSB',
                        'description' => '',
                        'members' => [
                            ['name' => 'Mr. Exequiel A. Aguilar, Jr.', 'designation' => 'Chairperson'],
                            ['name' => 'Edwin L. Cartago', 'designation' => 'Member'],
                            ['name' => 'Mercedes A. Carreon', 'designation' => 'Member'],
                            ['name' => 'Engr. Joselito A. Gillera', 'designation' => 'Member'],
                            ['name' => 'Engr. Ranely S. Cartago', 'designation' => 'Member'],
                            ['name' => 'Ma. Carmela M. Elepaño', 'designation' => 'Member'],
                            ['name' => 'Elenita V. Panganiban', 'designation' => 'Member'],
                            ['name' => 'Junsy Ybuan-Nieron', 'designation' => 'Member'],
                            ['name' => 'Emma A. Fandiño', 'designation' => '2nd Level Representative'],
                            ['name' => 'Geminiano A. Gevaña, Jr.', 'designation' => '2nd Level Alternate'],
                            ['name' => 'Mitch D. Mendoza', 'designation' => 'Secretariat'],
                        ]
                    ],
                    [
                        'committee_name' => 'Sports Committee',
                        'description' => '',
                        'members' => [
                            ['name' => 'Gregoria B. Olea', 'designation' => 'Chairperson'],
                            ['name' => 'Anatolio C. Maiquez', 'designation' => 'Member'],
                            ['name' => 'Engr. Bernard Joseph G. Rodriguez', 'designation' => 'Member'],
                            ['name' => 'Geminiano A. Gevaña, Jr.', 'designation' => 'Member'],
                            ['name' => 'Shirmae Joy G. Magsino', 'designation' => 'Member'],
                            ['name' => 'Paul G. Evangelista', 'designation' => 'Member'],
                            ['name' => 'Maribeth R. Gratela', 'designation' => 'Member'],
                        ]
                    ],
                    [
                        'committee_name' => 'Technical Planning Committee',
                        'description' => '',
                        'members' => [
                            ['name' => 'Mr. Exequiel A. Aguilar, Jr.', 'designation' => 'Chairperson'],
                            ['name' => 'Engr. Joselito A. Gillera', 'designation' => 'Vice-Chair'],
                            ['name' => 'Engr. Ranely S. Cartago', 'designation' => 'Member'],
                            ['name' => 'Engr. Elizaldy O. Novillos', 'designation' => 'Member'],
                            ['name' => 'Engr. Alberto L. Mabalot, Jr.', 'designation' => 'Member'],
                            ['name' => 'Engr. Vergell C. David', 'designation' => 'Member'],
                            ['name' => 'Engr. Rolando V. Baro', 'designation' => 'Member'],
                            ['name' => 'Engr. Fernando H. Arellano, Jr.', 'designation' => 'Member'],
                            ['name' => 'Engr. Renante A. Capitle', 'designation' => 'Member'],
                            ['name' => 'Engr. Bernard Joseph G. Rodriguez', 'designation' => 'Member'],
                            ['name' => 'Engr. John Carlos R. Alibutod', 'designation' => 'Member'],
                            ['name' => 'Pedro M. Estalar', 'designation' => 'Member'],
                            ['name' => 'Angelo L. Del Prado', 'designation' => 'Member'],
                            ['name' => 'Odin G. Sanchez, Jr.', 'designation' => 'Member'],
                            ['name' => 'Jewell C. Precilla', 'designation' => 'Secretariat'],
                        ]
                    ],
                    [
                        'committee_name' => 'Uniform Committee',
                        'description' => '',
                        'members' => [
                            ['name' => 'Rolando M. Pizarra', 'designation' => 'Chairperson'],
                            ['name' => 'Jennifer M. Ante', 'designation' => 'Member'],
                            ['name' => 'Ronilo S. Muzares', 'designation' => 'Member'],
                            ['name' => 'Emma A. Fandiño', 'designation' => 'Member'],
                            ['name' => 'Miracle V. Salamat', 'designation' => 'Member'],
                            ['name' => 'Reynet L. Khan', 'designation' => 'Member'],
                            ['name' => 'Leo V. Resano', 'designation' => 'Member'],
                            ['name' => 'Ronald M. Gregorio', 'designation' => 'Member'],
                            ['name' => 'Shirmae Joy G. Magsino', 'designation' => 'Member'],
                            ['name' => 'Engr. Nazelon J. Marfori', 'designation' => 'Member'],
                        ]
                    ],
                    [
                        'committee_name' => 'Water Safety Plan Committee',
                        'description' => '',
                        'members' => [
                            ['name' => 'Engr. Joselito A. Gillera', 'designation' => 'Chairperson'],
                            ['name' => 'Engr. Elizaldy O. Novillos', 'designation' => 'Team Leader'],
                            ['name' => 'Engr. Bernard Joseph G. Rodriguez', 'designation' => 'Assistant Team Leader'],
                            ['name' => 'Pedro M. Estalar', 'designation' => 'Operations Dept. - Production Div.'],
                            ['name' => 'Engr. Miccoh N. Quimio', 'designation' => 'Operations Dept. - Water Quality'],
                            ['name' => 'Odin G. Sanchez, Jr.', 'designation' => 'Operations Dept. - Water Quality'],
                            ['name' => 'Engr. John Carlos R. Alibutod', 'designation' => 'Tech. Serv. - EMD'],
                            ['name' => 'Napoleon N. Rodriguez, Jr.', 'designation' => 'Customer Service / Public Info.'],
                            ['name' => 'Ma. Carminda G. Paringit', 'designation' => 'Finance / Budget Specialist'],
                            ['name' => 'Geraldine G. Manguiat', 'designation' => 'Coordinator / Resource Person & GAD'],
                            ['name' => 'Ethel O. Paredes', 'designation' => 'Secretariat'],
                            ['name' => 'Depro Mari L. Alinsunurin', 'designation' => 'Documentation Staff'],
                        ]
                    ],
                    [
                        'committee_name' => 'Waterlife Editorial Staff',
                        'description' => '',
                        'members' => [
                            ['name' => 'Emanuel B. Capulong', 'designation' => 'Editor-in-Chief'],
                            ['name' => 'Joseph Bernard B. Quiatchon', 'designation' => 'Associate Editor'],
                            ['name' => 'John Norman L. Tidon', 'designation' => 'Associate Editor'],
                            ['name' => 'Depro Mari L. Alinsunurin', 'designation' => 'Design / Layout Artist'],
                            ['name' => 'Edgardo D. Mangeron', 'designation' => 'Cartoonist'],
                            ['name' => 'Henry S. Junio', 'designation' => 'Feature Writer'],
                            ['name' => 'Engr. Rolando V. Baro', 'designation' => 'Feature Writer'],
                            ['name' => 'Engr. Miccoh N. Quimio', 'designation' => 'Feature Writer'],
                            ['name' => 'Shirmae Joy G. Magsino', 'designation' => 'Feature Writer'],
                            ['name' => 'Mitch D. Mendoza', 'designation' => 'Feature Writer'],
                            ['name' => 'Reynet L. Khan', 'designation' => 'Feature Writer'],
                        ]
                    ],
                    [
                        'committee_name' => 'Septage Management Committee',
                        'description' => '',
                        'members' => [
                            ['name' => 'Engr. Joselito A. Gillera', 'designation' => 'Chairperson'],
                            ['name' => 'Engr. Ranely S. Cartago', 'designation' => 'Member'],
                            ['name' => 'Engr. Elizaldy O. Novillos', 'designation' => 'Member'],
                            ['name' => 'Ma. Carmela M. Elepaño', 'designation' => 'Member'],
                            ['name' => 'Mercedes A. Carreon', 'designation' => 'Member'],
                            ['name' => 'Alberto L. Mabalot Jr.', 'designation' => 'Member'],
                            ['name' => 'Engr. Miccoh N. Quimio', 'designation' => 'Member'],
                            ['name' => 'Ethel O. Paredes', 'designation' => 'Member'],
                            ['name' => 'Ronnie G. Sierva', 'designation' => 'Member'],
                            ['name' => 'Ronald M. Gregorio', 'designation' => 'Member'],
                            ['name' => 'Emanuel B. Capulong', 'designation' => 'Member'],
                            ['name' => 'Geraldine G. Manguiat', 'designation' => 'Member'],
                            ['name' => 'Depro Mari Alinsunurin', 'designation' => 'Secretariat'],
                        ]
                    ],
                ];



                ?>

                <!-- COMMITTEE MEMBERSHIPS UI CONTAINER -->
                <div class="dir-card">
                    <div class="card-body p-4 p-md-5">

                        <!-- SEARCH BAR -->
                        <div class="dir-search">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="7" />
                                <path d="M21 21l-4.3-4.3" />
                            </svg>
                            <input type="text" id="commSearch" placeholder="Search by member name or designation">
                        </div>


                        <!-- CONTROLS: COMMITTEE DROPDOWN FILTER -->
                        <div class="mb-4 comm-filter-card">
                            <label for="committeeSelect"
                                class="form-label fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                                <i class="bx bx-filter-alt text-primary fs-5"></i>
                                <span>Filter by Committee</span>
                            </label>

                            <div class="position-relative">
                                <select id="committeeSelect"
                                    class="form-select form-select-lg shadow-sm custom-comm-select">
                                    <option value="all">📁 All Committees</option>
                                    <?php foreach ($committees_data as $index => $comm): ?>
                                        <option value="comm-<?= $index ?>">
                                            👥 <?= htmlspecialchars($comm['committee_name']) ?>
                                            (<?= count($comm['members']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- COMMITTEE MEMBERSHIP CONTAINER -->
                        <div id="committeeContainer">
                            <?php foreach ($committees_data as $index => $comm): ?>
                                <div class="comm-group mb-5" data-comm="comm-<?= $index ?>">

                                    <!-- COMMITTEE HEADER & COUNT -->
                                    <div class="dept-header d-flex align-items-center flex-wrap gap-2">
                                        <h5 class="mb-0 d-flex align-items-center">
                                            <i class="bx bx-group fs-4 me-2" style="color: var(--tk-primary);"></i>
                                            <?= htmlspecialchars($comm['committee_name']) ?>
                                        </h5>
                                        <span class="dept-count ms-2">
                                            <?= count($comm['members']) ?> Members
                                        </span>
                                        <hr class="flex-grow-1 ms-3 text-muted opacity-25 d-none d-md-block">
                                    </div>

                                    <?php if (!empty($comm['description'])): ?>
                                        <p class="text-muted small mb-4 ms-1">
                                            <i class="bx bx-info-circle me-1"></i><?= htmlspecialchars($comm['description']) ?>
                                        </p>
                                    <?php endif; ?>

                                    <!-- MEMBER CARDS GRID -->
                                    <div class="row g-4 mb-4 justify-content-center">
                                        <?php
                                        $chairs = [];
                                        $regular_members = [];

                                        foreach ($comm['members'] as $member) {
                                            $desig = strtolower($member['designation']);
                                            $name = strtolower($member['name']);

                                            $is_chair = (
                                                strpos($desig, 'chairperson') !== false ||
                                                strpos($desig, 'chairman') !== false ||
                                                strpos($desig, 'editor-in-chief') !== false ||
                                                strpos($desig, 'final') !== false ||
                                                strpos($desig, 'focal') !== false ||
                                                strpos($desig, 'iso head') !== false
                                            );

                                            $is_vice = (
                                                strpos($desig, 'vice') !== false ||
                                                strpos($desig, 'co-') !== false ||
                                                strpos($desig, 'co ') !== false
                                            );

                                            
                                            $is_excluded_person = (strpos($name, 'geraldine') !== false);

                                            if ($is_chair && !$is_vice && !$is_excluded_person) {
                                                $chairs[] = $member;
                                            } else {
                                                $regular_members[] = $member;
                                            }
                                        }

                                        // 1. RENDER MAIN CHAIRPERSON / CHAIRMANSHIP POSITIONS (Mas Malaki & Nasa Gitna)
                                        foreach ($chairs as $member):
                                            ?>

                                            <div class="col-xl-4 col-lg-5 col-md-6 col-sm-10 member-card">
                                                <div class="card tel-card-ui border-0 shadow-sm h-100 overflow-hidden border-start border-primary"
                                                    style="border-left-width: 4px !important;">
                                                    <!-- Binawasan ang padding mula p-4 papuntang p-3 -->
                                                    <div
                                                        class="card-body p-3 d-flex flex-column align-items-center text-center">

                                                        <!-- Avatar (Ginawang 85px mula 95px) -->
                                                        <div class="tel-avatar-wrapper mb-2">
                                                            <img src="<?= !empty($member['profile_picture']) ? htmlspecialchars($member['profile_picture']) : '../assets/img/avatars/default_dp.jpg'; ?>"
                                                                class="rounded-circle border border-3 border-primary shadow-sm"
                                                                alt="<?= htmlspecialchars($member['name']); ?>"
                                                                style="width: 85px; height: 85px; object-fit: cover;">
                                                        </div>

                                                        <!-- Name (Ginawang fs-5 / H5-size para mas sakto lang) -->
                                                        <h5 class="fw-bold text-dark mb-1 text-truncate w-100"
                                                            title="<?= htmlspecialchars($member['name']); ?>">
                                                            <?= htmlspecialchars($member['name']); ?>
                                                        </h5>

                                                        <!-- Department -->
                                                        <?php if (!empty($member['department'])): ?>
                                                            <div class="small text-muted text-truncate w-100 mb-2"
                                                                title="<?= htmlspecialchars($member['department']); ?>">
                                                                <i class="bx bx-building-house me-1 align-middle"></i>
                                                                <span
                                                                    class="align-middle"><?= htmlspecialchars($member['department']); ?></span>
                                                            </div>
                                                        <?php endif; ?>

                                                        <!-- Spacer Push -->
                                                        <div class="mt-auto pt-2 w-100">
                                                            <!-- Designation Badge (Mas compact na padding) -->
                                                            <span
                                                                class="badge rounded-pill bg-primary text-white fs-6 fw-semibold px-3 py-2 w-100 d-inline-flex align-items-center justify-content-center text-wrap lh-sm shadow-sm"
                                                                title="<?= htmlspecialchars($member['designation']); ?>">
                                                                <i class="fa-solid fa-user-tie me-2"></i>
                                                                <span><?= htmlspecialchars($member['designation']); ?></span>
                                                            </span>
                                                        </div>

                                                    </div>
                                                </div>
                                            </div>

                                        <?php endforeach; ?>

                                        <!-- Break Line para hiwalay ang Row ng Chair sa ibang Members -->
                                        <?php if (!empty($chairs) && !empty($regular_members)): ?>
                                            <div class="w-100"></div>
                                        <?php endif; ?>

                                        <!-- 2. RENDER THE REST OF THE MEMBERS (Kasama na rito ang Vice Chairman) -->
                                        <?php foreach ($regular_members as $member): ?>
                                            <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 member-card">
                                                <div class="card tel-card-ui border-0 shadow-sm h-100 overflow-hidden">
                                                    <div
                                                        class="card-body p-4 d-flex flex-column align-items-center text-center">

                                                        <!-- Avatar -->
                                                        <div class="tel-avatar-wrapper mb-3">
                                                            <img src="<?= !empty($member['profile_picture']) ? htmlspecialchars($member['profile_picture']) : '../assets/img/avatars/default_dp.jpg'; ?>"
                                                                class="rounded-circle border border-3 border-white shadow-sm"
                                                                alt="<?= htmlspecialchars($member['name']); ?>"
                                                                style="width: 78px; height: 78px; object-fit: cover;">
                                                        </div>

                                                        <!-- Name -->
                                                        <h5 class="fw-bold text-dark mb-1 text-truncate w-100"
                                                            title="<?= htmlspecialchars($member['name']); ?>">
                                                            <?= htmlspecialchars($member['name']); ?>
                                                        </h5>

                                                        <!-- Department -->
                                                        <?php if (!empty($member['department'])): ?>
                                                            <div class="small text-muted text-truncate w-100 mb-3"
                                                                title="<?= htmlspecialchars($member['department']); ?>">
                                                                <i class="bx bx-building-house me-1 align-middle"></i>
                                                                <span
                                                                    class="align-middle"><?= htmlspecialchars($member['department']); ?></span>
                                                            </div>
                                                        <?php endif; ?>

                                                        <!-- Spacer Push -->
                                                        <div class="mt-auto pt-2 w-100">
                                                            <!-- Designation -->
                                                            <span
                                                                class="badge rounded-pill bg-label-primary text-primary fw-semibold px-3 py-2 w-100 d-inline-flex align-items-center justify-content-center text-wrap lh-sm"
                                                                title="<?= htmlspecialchars($member['designation']); ?>">
                                                                <i
                                                                    class="fa-solid fa-user-check text-[#2E96EC] text-xs me-1 flex-shrink-0"></i>
                                                                <span><?= htmlspecialchars($member['designation']); ?></span>
                                                            </span>
                                                        </div>

                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                </div>
                            <?php endforeach; ?>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT FOR SEARCH, FILTERING, AND COPY -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const searchInput = document.getElementById("commSearch");
            const filterButtons = document.querySelectorAll("#committeeFilters button");

            // Live Search Filter
            searchInput.addEventListener("keyup", function () {
                let value = this.value.toLowerCase().trim();

                document.querySelectorAll(".member-card").forEach(card => {
                    let matches = card.innerText.toLowerCase().includes(value);
                    card.style.setProperty("display", matches ? "" : "none", "important");
                });

                // Hide committee section if no member matches
                document.querySelectorAll(".comm-group").forEach(group => {
                    let visibleCards = group.querySelectorAll(".member-card:not([style*='display: none'])").length;
                    group.style.display = visibleCards > 0 ? "" : "none";
                });
            });

            // Committee Select Dropdown Filter
            const committeeSelect = document.getElementById("committeeSelect");

            if (committeeSelect) {
                committeeSelect.addEventListener("change", function () {
                    const selectedFilter = this.value;

                    // Reset search input kapag nagpalit ng filter (optional pero magandang UX)
                    if (searchInput) searchInput.value = "";

                    document.querySelectorAll(".comm-group").forEach(group => {
                        // I-reset ang visibility ng member cards sa napiling committee
                        group.querySelectorAll(".member-card").forEach(card => card.style.display = "");

                        if (selectedFilter === "all" || group.getAttribute("data-comm") === selectedFilter) {
                            group.style.display = "";
                        } else {
                            group.style.display = "none";
                        }
                    });
                });
            }
        });

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert("Copied name: " + text);
            }).catch(err => {
                console.error("Could not copy text: ", err);
            });
        }
    </script>

    <!-- VENDOR SCRIPTS -->
    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/vendor/libs/apex-charts/apexcharts.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/dashboards-analytics.js"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>

</body>

</html>