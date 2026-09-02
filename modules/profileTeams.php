<?php
include '../db.php';
require '../vendor/autoload.php';
require 'login_verification.php';

$username = $_SESSION['username'];

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

        /* ── Profile header (shared w/ profile.php) ───────────────────── */
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

        /* ── Team section ──────────────────────────────────────────── */
        .pf-card-head {
            padding: 16px 20px;
            border-bottom: 1px solid var(--tk-border);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .pf-card-head svg { width: 16px; height: 16px; color: var(--tk-primary); flex-shrink: 0; }
        .pf-team-title { font-weight: 700; font-size: 15px; }
        .pf-team-dept { font-size: 13px; color: var(--tk-text-muted); margin-top: 1px; }
        .pf-team-count {
            margin-left: auto;
            font-size: 12px;
            font-weight: 700;
            color: #2563a8;
            background: var(--tk-primary-soft);
            padding: 4px 11px;
            border-radius: 999px;
        }

        .pf-member-card {
            background: var(--tk-bg);
            border: 1px solid var(--tk-border);
            border-radius: 12px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 13px;
            height: 100%;
            transition: border-color .12s ease, transform .12s ease, box-shadow .12s ease, background .12s ease;
        }
        .pf-member-card:hover {
            border-color: var(--tk-primary);
            background: var(--tk-surface);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px -8px rgba(20,20,43,.18);
        }
        .pf-member-avatar {
            width: 54px; height: 54px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
            border: 2px solid var(--tk-surface);
            box-shadow: 0 0 0 1.5px var(--tk-border);
        }
        .pf-member-info { min-width: 0; }
        .pf-member-name {
            font-weight: 700;
            font-size: 14px;
            color: var(--tk-text);
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pf-member-position {
            font-size: 12px;
            color: var(--tk-text-muted);
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pf-member-email {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11.5px;
            font-weight: 600;
            color: #2563a8;
            background: var(--tk-primary-soft);
            padding: 3px 9px;
            border-radius: 999px;
            text-decoration: none;
            max-width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pf-member-email svg { width: 11px; height: 11px; flex-shrink: 0; }

        .pf-empty-team {
            text-align: center;
            padding: 50px 20px;
            color: var(--tk-text-muted);
        }
        .pf-empty-team svg { width: 38px; height: 38px; opacity: .35; margin-bottom: 10px; }
        .pf-empty-team p { margin: 0; font-size: 14px; font-weight: 600; color: var(--tk-text); }
        .pf-empty-team span { font-size: 12.5px; }
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
                                    <br>
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
                                    <a class="nav-link active " href="profileTeams.php"><i
                                            class="icon-base bx bx-group icon-sm me-1_5"></i> Teams</a>
                                </li>

                                <li class="nav-item">
                                    <a class="nav-link " href="tell.php"><i
                                            class="icon-base bx bx-phone icon-sm me-1_5"></i> Local Directory</a>
                                </li>

                                <li class="nav-item">
                                    <a class="nav-link" href="comms.php"><i
                                            class="icon-base bx bx-sitemap icon-sm me-1_5"></i> Committees</a>
                                </li>

                            </ul>
                        </div>
                    </div>
                </div>


                <div class="pf-card mt-3">
                    <div class="pf-card-head">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        <div>
                            <div class="pf-team-title">Team Members</div>
                            <div class="pf-team-dept"><?php echo htmlspecialchars($department); ?></div>
                        </div>
                        <span class="pf-team-count"><?php echo count($same_department_users); ?> member<?php echo count($same_department_users) === 1 ? '' : 's'; ?></span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($same_department_users)): ?>
                            <div class="row g-3">
                                <?php foreach ($same_department_users as $user): ?>
                                    <div class="col-md-4">
                                        <div class="pf-member-card">
                                            <img src="<?php
                                            echo !empty($user['profile_picture']) && file_exists($user['profile_picture'])
                                                ? htmlspecialchars($user['profile_picture'])
                                                : '../assets/img/avatars/default_dp.jpg'; ?>" alt="Profile"
                                                class="pf-member-avatar">
                                            <div class="pf-member-info">
                                                <div class="pf-member-name">
                                                    <?php echo htmlspecialchars(trim($user['firstname'] . ' ' . $user['middlename'] . ' ' . $user['lastname'])); ?>
                                                </div>
                                                <div class="pf-member-position">
                                                    <?php echo htmlspecialchars($user['position'] ?: 'No position set'); ?>
                                                </div>
                                                <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>" class="pf-member-email">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/></svg>
                                                    <?php echo htmlspecialchars($user['email']); ?>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="pf-empty-team">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                <p>No other team members found</p>
                                <span>You're the only one listed in <?php echo htmlspecialchars($department); ?> right now.</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="content-backdrop fade"></div>
    <div class="layout-overlay layout-menu-toggle"></div>

    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/vendor/libs/apex-charts/apexcharts.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/dashboards-analytics.js"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>

</body>

</html>