<?php
include '../db.php';
require '../vendor/autoload.php';
require 'login_verification.php';

$username = $_SESSION['username'];

// ==============================
// 📞 TELEPHONE DIRECTORY FETCH (ALL RECORDS)
// ==============================
$tel_query = $conn->query("SELECT * FROM telephone_directory ORDER BY name ASC");

$telephone_records = $tel_query->fetch_all(MYSQLI_ASSOC);

date_default_timezone_set('Asia/Manila'); // Set timezone to your local
$now = date('Y-m-d H:i:s');
$updateActivity = $conn->prepare("UPDATE users SET last_activity = ? WHERE username = ?");
$updateActivity->bind_param("ss", $now, $username);
$updateActivity->execute();
$updateActivity->close();

$query = $conn->prepare("SELECT users.id, users.profile_picture, users.emp_id, users.cover_photo, users.department, users.firstname, users.middlename, users.lastname, users.email, personal_data_sheet.position
                         FROM users 
                         LEFT JOIN personal_data_sheet ON users.id = personal_data_sheet.user_id 
                         WHERE users.username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->store_result();
$query->bind_result($user_id, $profile_picture, $emp_id, $cover_photo, $department, $firstname, $middlename, $lastname, $email, $position);
$query->fetch();
$query->close();

$same_dept_query = $conn->prepare("SELECT users.id, users.firstname, users.middlename, users.lastname, users.profile_picture, users.email, users.department, personal_data_sheet.position 
                                   FROM users 
                                   LEFT JOIN personal_data_sheet ON users.id = personal_data_sheet.user_id 
                                   WHERE users.department = ? AND users.username != ?");
$same_dept_query->bind_param("ss", $department, $username);
$same_dept_query->execute();
$same_dept_result = $same_dept_query->get_result();
$same_department_users = $same_dept_result->fetch_all(MYSQLI_ASSOC);
$same_dept_query->close();

// If profile picture is empty, set the default profile picture
if (empty($profile_picture)) {
    $profile_picture = '../assets/img/avatars/default_dp.jpg';
}
if (empty($cover_photo)) {
    $cover_photo = '../assets/img/avatars/default_cover.png';
}

$_SESSION['profile_picture'] = $profile_picture;
$_SESSION['cover_photo'] = $cover_photo;

$query = $conn->prepare("SELECT name_extension, personal_date_of_birth, place_of_birth, sex, civil_status, citizenship, telephone_no, mobile_no FROM personal_data_sheet WHERE user_id = ?");
$query->bind_param("i", $user_id);
$query->execute();
$query->store_result();
$query->bind_result($name_extension, $personal_date_of_birth, $place_of_birth, $sex, $civil_status, $citizenship, $telephone_no, $mobile_no);
$query->fetch();
$query->close();

$query = $conn->prepare("SELECT spouse_first_name, spouse_extension_name, spouse_middle_name, spouse_last_name, father_first_name, father_name_extension, father_middle_name, father_last_name, mother_first_name, mother_middle_name, mother_last_name FROM family_background WHERE user_id = ?");
$query->bind_param("i", $user_id);
$query->execute();
$query->store_result();
$query->bind_result($spouse_first_name, $spouse_extension_name, $spouse_middle_name, $spouse_last_name, $father_first_name, $father_name_extension, $father_middle_name, $father_last_name, $mother_first_name, $mother_middle_name, $mother_last_name);
$query->fetch();
$query->close();

$query = $conn->prepare("SELECT elementarySchool, highSchool, college, vocTradeCourse, graduateSchool FROM educational_background WHERE user_id = ?");
$query->bind_param("i", $user_id);
$query->execute();
$query->store_result();
$query->bind_result($elementarySchool, $highSchool, $college, $vocTradeCourse, $graduateSchool);
$query->fetch();
$query->close();

// Ensure variables are set to avoid undefined variable warnings
$name_extension = $name_extension ?? '';
$personal_date_of_birth = $personal_date_of_birth ?? '';
// Handle messages
$successMessage = "";
$errorMessage = "";

// File upload settings
$allowed_types = ["image/jpeg", "image/jpg", "image/png"];
$max_size = 2 * 1024 * 1024; // 2MB limit
$upload_dir = "../uploads/";

// Ensure uploads directory exists
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Process Cover Photo Upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['cover_photo'])) {
    $cover_photo = $_FILES['cover_photo'];

    // Debugging: Check if file is uploaded
    if ($cover_photo["error"] !== 0) {
        $errorMessage = "Error: Cover photo upload error. Code: " . $cover_photo["error"];
    } elseif (!in_array($cover_photo["type"], $allowed_types)) {
        $errorMessage = "Error: Only JPG, JPEG, and PNG files are allowed.";
    } elseif ($cover_photo["size"] > $max_size) {
        $errorMessage = "Error: Cover photo size must be less than 2MB.";
    } else {
        // Rename file with timestamp
        $cover_name = time() . "_cover_" . basename($cover_photo["name"]);
        $cover_path = $upload_dir . $cover_name;

        // Move the uploaded file
        if (move_uploaded_file($cover_photo["tmp_name"], $cover_path)) {
            // Update cover photo in the database
            $stmt = $conn->prepare("UPDATE users SET cover_photo = ? WHERE id = ?");
            $stmt->bind_param("si", $cover_path, $user_id);

            if ($stmt->execute()) {
                $_SESSION['cover_photo'] = $cover_photo ?: '../uploads/default_cover.png'; // Update session
                // Fetch the updated cover photo from the database after update
                $query = $conn->prepare("SELECT cover_photo FROM users WHERE id = ?");
                $query->bind_param("i", $user_id);
                $query->execute();
                $query->store_result();
                $query->bind_result($new_cover_photo);
                $query->fetch();
                $query->close();
                // Update session with the new cover photo
                $_SESSION['cover_photo'] = $new_cover_photo;
                header("Location: profile.php?success=1");
                exit();
            } else {
                $errorMessage = "Error: Database update failed.";
            }
            $stmt->close();
        } else {
            $errorMessage = "Error: Failed to upload cover photo.";
        }
    }
}

$stmt = $pdo->query("SELECT * FROM activities ORDER BY start_datetime");
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Department color mapping
$departmentColors = [
    "All Departments" => ["bgcolor" => "#d1d0cf", "textcolor" => "#969593"],
    "Office of the General Manager" => ["bgcolor" => "#b0f5f5", "textcolor" => "#679e9e"],
    "Management Information Services Section" => ["bgcolor" => "#fcc6c0", "textcolor" => "#8f4239"],
    "Administrative Department" => ["bgcolor" => "#cff799", "textcolor" => "#6a8745"],
    "Finance Department" => ["bgcolor" => "#3cc74a", "textcolor" => "#1b6622"],
    "Commercial Department" => ["bgcolor" => "#f8fc9d", "textcolor" => "#b8bf2c"],
    "Technical Services Department" => ["bgcolor" => "#cba5f2", "textcolor" => "#70518f"],
    "Operations Department" => ["bgcolor" => "#fab07a", "textcolor" => "#a8602c"]
];

$events = [];
foreach ($activities as $activity) {
    // Fetch event department
    $eventDepartment = $activity['department'] ?? "All Departments"; // Default to "All Departments"

    // Only add events that belong to the user's department OR "All Departments"
    if ($eventDepartment == "All Departments" || $eventDepartment == $department) {
        $bgcolor = $departmentColors[$eventDepartment]['bgcolor'] ?? "#b0f5f5"; // Default color
        $textcolor = $departmentColors[$eventDepartment]['textcolor'] ?? "#679e9e";

        $events[] = [
            'id' => $activity['id'],
            'title' => $activity['title'],
            'start' => $activity['start_datetime'],
            'end' => $activity['end_datetime'],
            'description' => $activity['description'],
            'color' => $bgcolor,
            'textColor' => $textcolor,
            'department' => $eventDepartment,
            'event_url' => $activity['event_url'],
            'event_location' => $activity['event_location']
        ];
    }
}
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $activity_date = $_POST['activity_date'];
    $activity_end_date = $_POST['activity_end_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $department = $_POST['department'];
    $event_url = $_POST['event_url']; // Get event URL
    $event_location = $_POST['event_location']; // Get event location


    $start_datetime = $activity_date . ' ' . $start_time;
    $end_datetime = $activity_end_date . ' ' . $end_time;

    $stmt = $pdo->prepare("INSERT INTO activities (title, description, start_datetime, end_datetime, department, event_url, event_location) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title, $description, $start_datetime, $end_datetime, $department, $event_url, $event_location]);

    header("Location: add_activity.php");
    exit();
}

$sql = "SELECT * FROM announcements ORDER BY created_at DESC";
$result = $conn->query($sql);

$client_department = $department; // This is fetched from the logged-in user

if ($client_department == "All Departments") {
    $sql = "SELECT * FROM announcements ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
} else {
    $sql = "SELECT * FROM announcements WHERE department = ? OR department = 'All Departments' ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
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

    <title>Local Directory</title>

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

        /* ── Profile header (shared) ───────────────────────────────── */
        .pf-card {
            background: var(--tk-surface);
            border: 1px solid var(--tk-border);
            border-radius: var(--tk-radius);
            box-shadow: var(--tk-shadow);
            overflow: hidden;
        }
        .user-name { font-size: 1.75rem; }
        @media (max-width: 576px) {
            .user-name { font-size: 1.4rem; }
            .user-profile-header { padding-top: 2rem !important; }
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

        /* ── Directory ─────────────────────────────────────────────── */
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
            position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
            width: 18px; height: 18px; color: var(--tk-text-muted); pointer-events: none;
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
        .dir-filters::-webkit-scrollbar { height: 4px; }
        .dir-filters::-webkit-scrollbar-thumb { background: var(--tk-primary-soft); border-radius: 4px; }
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
        .dir-filter-btn:hover { border-color: var(--tk-primary); color: var(--tk-primary); }
        .dir-filter-btn.active { background: var(--tk-primary); border-color: var(--tk-primary); color: #fff; }

        .dir-print-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: var(--tk-surface);
            border: 1.5px solid var(--tk-border);
            color: var(--tk-text);
            font-weight: 600;
            font-size: 13px;
            padding: 9px 16px;
            border-radius: 999px;
            text-decoration: none;
            transition: border-color .12s ease, color .12s ease;
        }
        .dir-print-btn:hover { border-color: var(--tk-primary); color: var(--tk-primary); }
        .dir-print-btn svg { width: 14px; height: 14px; }

        .dept-header { margin-bottom: 18px; }
        .dept-header h5 {
            color: var(--tk-text);
            font-weight: 700;
            letter-spacing: .2px;
        }
        .dept-header h5 svg { width: 18px; height: 18px; color: var(--tk-primary); margin-right: 8px; }
        .dept-count {
            background: var(--tk-primary-soft);
            color: #2563a8;
            font-weight: 700;
            font-size: 11.5px;
            padding: 4px 11px;
            border-radius: 999px;
        }

        .tel-card-ui {
            background: var(--tk-surface);
            border: 1px solid var(--tk-border) !important;
            border-radius: 14px;
            transition: all .2s cubic-bezier(.25,.8,.25,1);
        }
        .tel-card-ui:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -10px rgba(20,20,43,.16) !important;
            border-color: var(--tk-primary) !important;
        }
        .tel-name { font-size: 15px; font-weight: 700; color: var(--tk-text); }
        .tel-email { font-size: 12.5px; }
        .tel-meta {
            font-size: 11px;
            font-weight: 600;
            background: var(--tk-bg) !important;
            color: var(--tk-text-muted) !important;
            border-color: var(--tk-border) !important;
        }
        .tel-meta svg, .tel-meta i { color: var(--tk-primary); }
        .tel-remarks { font-size: 12px; line-height: 1.4; border-color: var(--tk-border) !important; }
        .text-truncate-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: normal;
        }

        .tel-footer {
            background: var(--tk-bg) !important;
            border-color: var(--tk-border) !important;
        }
        .tel-number { color: var(--tk-text); }
        .tel-phone-icon { color: var(--tk-primary); }
        .tel-copy-btn {
            border-color: var(--tk-border) !important;
            color: var(--tk-text-muted);
            transition: border-color .12s ease, color .12s ease, background .12s ease;
        }
        .tel-copy-btn:hover { border-color: var(--tk-primary) !important; color: var(--tk-primary); background: var(--tk-primary-soft) !important; }
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
                                    <a class="nav-link" href="profile.php"><i
                                            class="icon-base bx bx-user icon-sm me-1_5"></i> Profile</a>
                                </li>

                                <li class="nav-item">
                                    <a class="nav-link " href="profileTeams.php"><i
                                            class="icon-base bx bx-group icon-sm me-1_5"></i> Teams</a>
                                </li>

                                <li class="nav-item">
                                    <a class="nav-link active" href="tell.php"><i
                                            class="icon-base bx bx-phone icon-sm me-1_5"></i> Local Directory</a>
                                </li>


                            </ul>
                        </div>
                    </div>
                </div>



                <?php
                // ==============================
                // 📞 FETCH DATA FIRST (IMPORTANT)
                // ==============================
                $tel_query = $conn->query("
                    SELECT 
                        t.*,
                        u.profile_picture,
                        u.email,
                        u.lastname
                    FROM telephone_directory t
                    LEFT JOIN users u 
                        ON LOWER(t.name) LIKE CONCAT('%', LOWER(u.lastname), '%')
                    GROUP BY t.id
                ");

                $telephone_records = $tel_query->fetch_all(MYSQLI_ASSOC);

                $overrides = [
                    3612 => 'nazmarfori.cwd@gmail.com', // Engr. Marfori
                    3601 => 'jojoagillera@gmail.com',   // Engr. Gillera
                    4501 => 'ranely66@yahoo.com',   // Engr. Cartago
                ];

                foreach ($telephone_records as &$tel) {
                    $local = (int) $tel['local_number'];
                    if (isset($overrides[$local])) {
                        $tel['email'] = $overrides[$local];
                    }
                }
                unset($tel);

                // ==============================
                // 🎯 EXACT ORDER (MASTER LIST)
                // ==============================
                $customOrderNumbers = [
                    // OGM / BOD
                    4100,
                    4101,
                    4111,
                    4112,
                    4113,
                    4114,
                    4121,
                    4131,
                    4132,
                    4133,
                    1134,
                    5100,
                    // Administrative
                    2200,
                    2201,
                    2211,
                    2212,
                    2214,
                    1213,
                    2221,
                    2222,
                    2223,
                    1224,
                    2231,
                    2232,
                    2233,
                    2234,
                    2235,
                    2236,
                    2237,
                    // Finance
                    2300,
                    2301,
                    2311,
                    2312,
                    2313,
                    1314,
                    2315,
                    2321,
                    2322,
                    2323,
                    2324,
                    // Commercial
                    3400,
                    3401,
                    3411,
                    3412,
                    3413,
                    1421,
                    1422,
                    1423,
                    1424,
                    1425,
                    1411,
                    1412,
                    // Technical Services
                    4500,
                    4501,
                    4511,
                    4512,
                    4513,
                    4521,
                    4522,
                    4523,
                    4524,
                    // Operations
                    3600,
                    3601,
                    3611,
                    3612,
                    // Others
                    1400,
                    1001,
                    1002,
                    1003,
                    3004,
                    5005,
                    2006,
                    1007,
                    1008,
                    2001
                ];

                // ==============================
                // 📂 GROUP BY DEPARTMENT
                // ==============================
                $grouped = [];
                foreach ($telephone_records as $tel) {
                    $dept = trim($tel['department']);

                    if (empty($dept) || strtoupper($dept) == 'N/A') {
                        $dept = 'Facilities and Committees';
                    }
                    if (strtoupper($dept) == 'OGM / BOD') {
                        $dept = 'Office of the General Manager / Board of Directors';
                    }

                    $grouped[$dept][] = $tel;
                }

                // ==============================
                // 🔥 SORT USING MASTER ORDER
                // ==============================
                foreach ($grouped as $dept => &$records) {
                    usort($records, function ($a, $b) use ($customOrderNumbers) {
                        $numA = (int) $a['local_number'];
                        $numB = (int) $b['local_number'];

                        $posA = array_search($numA, $customOrderNumbers);
                        $posB = array_search($numB, $customOrderNumbers);

                        $posA = ($posA === false) ? 9999 : $posA;
                        $posB = ($posB === false) ? 9999 : $posB;

                        return $posA <=> $posB;
                    });
                }
                unset($records);

                // ==============================
                // 📌 DEPARTMENT ORDER
                // ==============================
                $customOrder = [
                    "Office of the General Manager / Board of Directors",
                    "Administrative",
                    "Finance",
                    "Commercial",
                    "Technical Services",
                    "Operations",
                    "Facilities and Committees"
                ];

                uksort($grouped, function ($a, $b) use ($customOrder) {
                    $posA = array_search($a, $customOrder);
                    $posB = array_search($b, $customOrder);
                    return ($posA ?? 999) <=> ($posB ?? 999);
                });
                ?>

                <!-- Directory Wrapper -->
                <div class="dir-card">
                    <div class="card-body p-4 p-md-5">

                        <!-- 🔍 SEARCH BAR -->
                        <div class="dir-search">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                            <input type="text" id="telSearch"
                                placeholder="Search for a name, local number, department, or location...">
                        </div>


                        <!-- 🎛️ CONTROLS: DEPARTMENT QUICK FILTERS -->
                        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                            <div class="dir-filters" id="deptFilters">
                                <button class="dir-filter-btn active" data-filter="all">All Departments</button>
                                <?php
                                $deptIndex = 0;
                                foreach (array_keys($grouped) as $deptName):
                                    ?>
                                    <button class="dir-filter-btn" data-filter="dept-<?= $deptIndex++ ?>">
                                        <?= htmlspecialchars(explode(' /', $deptName)[0]) ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>

                        </div>

                        <!-- 🖨️ PRINT DIRECTORY BUTTON -->
                        <div class="d-flex justify-content-end mb-3">
                            <a href="tell_print.php" target="_blank" class="dir-print-btn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                Print Directory
                            </a>
                        </div>

                        <!-- 📇 DIRECTORY CONTAINER (CARDS ONLY) -->
                        <div id="directoryContainer">
                            <?php
                            $deptIndex = 0;
                            foreach ($grouped as $dept => $records):
                                ?>
                                <div class="dept-group mb-5" data-dept="dept-<?= $deptIndex++ ?>">
                                    <!-- 🏢 DEPARTMENT HEADER -->
                                    <div class="dept-header d-flex align-items-center">
                                        <h5 class="mb-0 d-flex align-items-center">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/></svg>
                                            <?= htmlspecialchars($dept) ?>
                                        </h5>
                                        <span class="dept-count ms-3">
                                            <?= count($records) ?> Contacts
                                        </span>
                                        <hr class="flex-grow-1 ms-4 text-muted opacity-25 d-none d-md-block">
                                    </div>

                                    <!-- 👤 GRID CARD VIEW -->
                                    <div class="grid-container row g-4 mb-4">
                                        <?php foreach ($records as $tel): ?>
                                            <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 tel-card">
                                                <div class="card tel-card-ui h-100 shadow-sm">
                                                    <div class="card-body p-4 d-flex flex-column">

                                                        <!-- Top Content: Profile Pic & Info -->
                                                        <div class="d-flex align-items-start mb-3">
                                                            <img src="<?php echo !empty($tel['profile_picture']) && file_exists($tel['profile_picture']) ? htmlspecialchars($tel['profile_picture']) : '../assets/img/avatars/default_dp.jpg'; ?>"
                                                                class="rounded-circle shadow-sm border p-1 bg-white me-3 flex-shrink-0"
                                                                style="width: 55px; height: 55px; object-fit: cover;">
                                                            <div class="overflow-hidden w-100">
                                                                <h6 class="tel-name text-truncate mb-1"
                                                                    title="<?= htmlspecialchars($tel['name']); ?>">
                                                                    <?= htmlspecialchars($tel['name']); ?>
                                                                </h6>
                                                                <div class="tel-email text-muted text-truncate d-flex align-items-center mb-1"
                                                                    title="<?= !empty($tel['email']) ? htmlspecialchars($tel['email']) : 'No email'; ?>">
                                                                    <i class="bx bx-envelope me-1 opacity-75"></i>
                                                                    <?= !empty($tel['email']) ? htmlspecialchars($tel['email']) : 'No email'; ?>
                                                                </div>
                                                                <?php if (!empty($tel['location'])): ?>
                                                                    <span
                                                                        class="badge border tel-meta d-inline-flex align-items-center px-2 py-0.5 rounded">
                                                                        <i class="bx bx-map me-1"></i>
                                                                        <?= htmlspecialchars($tel['location']); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>

                                                        <!-- Middle Content: Remarks (if available) -->
                                                        <?php if (!empty($tel['remarks'])): ?>
                                                            <div
                                                                class="tel-remarks mt-1 mb-3 pt-2 border-top d-flex align-items-start text-muted">
                                                                <i class="bx bx-info-circle mt-0.5 me-2 opacity-75" style="color: var(--tk-primary);"></i>
                                                                <span class="small text-truncate-2"
                                                                    title="<?= htmlspecialchars($tel['remarks']); ?>"><?= htmlspecialchars($tel['remarks']); ?></span>
                                                            </div>
                                                        <?php endif; ?>

                                                        <div
                                                            class="tel-footer mt-auto rounded-3 p-3 d-flex justify-content-between align-items-center border">

                                                            <div class="d-flex align-items-center">
                                                                <i class="bx bxs-phone tel-phone-icon fs-4 me-2"></i>

                                                                <span class="tel-number mb-0 fs-6 fw-bold">
                                                                    <?= htmlspecialchars($tel['local_number']); ?>
                                                                </span>
                                                            </div>

                                                            <div class="d-inline-flex gap-1">
                                                                <button type="button"
                                                                    class="btn btn-sm btn-icon tel-copy-btn rounded-circle bg-white"
                                                                    onclick="copyToClipboard('<?= htmlspecialchars($tel['local_number']); ?>')"
                                                                    title="Copy Number">
                                                                    <i class="bx bx-copy fs-5"></i>
                                                                </button>
                                                            </div>

                                                        </div>

                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Copy confirmation Toast -->
                        <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 9999;">
                            <div id="copyToast" class="toast align-items-center text-bg-success border-0 shadow-lg"
                                role="alert" aria-live="assertive" aria-atomic="true">
                                <div class="d-flex">
                                    <div class="toast-body d-flex align-items-center">
                                        <i class="bx bx-check-circle me-2 fs-4"></i> Local number copied to clipboard!
                                    </div>
                                    <button type="button" class="btn-close btn-close-white me-2 m-auto"
                                        data-bs-dismiss="toast" aria-label="Close"></button>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 🔍 SEARCH & FILTER SCRIPTS -->
    <script>
        // Live Search Filter
        document.getElementById("telSearch").addEventListener("keyup", function () {
            let value = this.value.toLowerCase();

            // Search inside cards
            document.querySelectorAll(".tel-card").forEach(element => {
                let match = element.innerText.toLowerCase().includes(value);
                element.style.setProperty("display", match ? "" : "none", "important");
            });

            // Hide department headers if all inner records are filtered out
            document.querySelectorAll(".dept-group").forEach(group => {
                let activeElements = group.querySelectorAll(".tel-card:not([style*='display: none'])").length;
                group.style.display = activeElements > 0 ? "" : "none";
            });
        });

        // Department Quick Filter Pills
        document.querySelectorAll("#deptFilters button").forEach(button => {
            button.addEventListener("click", function () {
                // Toggle active state
                document.querySelectorAll("#deptFilters button").forEach(btn => {
                    btn.classList.remove("active");
                });
                this.classList.add("active");

                const targetFilter = this.getAttribute("data-filter");

                document.querySelectorAll(".dept-group").forEach(group => {
                    if (targetFilter === "all" || group.getAttribute("data-dept") === targetFilter) {
                        group.style.display = "";
                    } else {
                        group.style.display = "none";
                    }
                });
            });
        });

        // Clipboard Action
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                const toastEl = document.getElementById('copyToast');
                const toast = new bootstrap.Toast(toastEl);
                toast.show();
            }).catch(err => {
                console.error('Failed to copy to clipboard', err);
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