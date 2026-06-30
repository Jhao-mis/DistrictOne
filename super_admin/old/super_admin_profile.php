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

$query = $conn->prepare("SELECT users.id, users.profile_picture, users.cover_photo, users.department, users.firstname, users.middlename, users.lastname, users.email, personal_data_sheet.position
                         FROM users 
                         LEFT JOIN personal_data_sheet ON users.id = personal_data_sheet.user_id 
                         WHERE users.username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->store_result();
$query->bind_result($user_id, $profile_picture, $cover_photo, $department, $firstname, $middlename, $lastname, $email, $position);
$query->fetch();
$query->close();

$result = $conn->query("SELECT * FROM image_slider");

$images = [];
while ($row = $result->fetch_assoc()) {
    $images[] = $row['image_url'];
}

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
$upload_dir = "uploads/";

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
                $_SESSION['cover_photo'] = $cover_photo ?: 'uploads/default_cover.png'; // Update session
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
                header("Location: admin_profile.php?success=1");
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

$stmt_reservations = $pdo->query("SELECT * FROM room_reservations WHERE status = 'Approved' ORDER BY reservation_date");
$reservations = $stmt_reservations->fetchAll(PDO::FETCH_ASSOC);

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

foreach ($reservations as $reservation) {
    $events[] = [
        'id' => 'res-' . $reservation['id'], // Unique ID for reservation events
        'title' => 'Room Reserved: ' . $reservation['room'],
        'department' => $reservation['department'], // ✅ Fixed variable and added comma
        'start' => $reservation['reservation_date'] . ' ' . $reservation['start_time'],
        'end' => $reservation['reservation_date'] . ' ' . $reservation['end_time'],
        'description' => $reservation['purpose'],
        'color' => "#4caf50", // Green for reserved rooms
        'textColor' => "#ffffff",
        'event_location' => $reservation['room'],
        'editable' => false, // 🔥 Important: Mark reservation as NOT editable
        'deletable' => false, // 🔥 Optional: If you manually control delete button
        'type' => 'Room Reservation' // 🔥 Add type to detect in JS easily
    ];
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
    <title>My Profile</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/districtone.png" />
    <!-- Option Button -->
    <link rel="stylesheet" href="../css/option_button.css" />
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet" />
    <!-- Icons -->
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
    <!-- Core Template CSS -->
    <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="./css/profile.css">

    <!-- Perfect Scrollbar -->
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <!-- Template Helpers -->
    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>
</head>

<body>

    <?php include 'sidebar.php' ?>

    <div class="content-wrapper">
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
                            <?php
                            include '../modals/terms_modal.php'; //include the terms modal
                            ?>
                            <div class="flex-grow-1 mt-3 mt-lg-5">
                                <div class="d-flex justify-content-between align-items-center mx-5 mt-2 ms-3 flex-wrap">

                                    <!-- User Name and Info -->
                                    <div class="user-profile-info">
                                        <h2 class="mb-2 mt-lg-5 user-name">
                                            <?php echo htmlspecialchars($firstname . " " . $middlename . " " . $lastname); ?>
                                        </h2>
                                        <p class="user-details">
                                            <strong><?php echo htmlspecialchars($department); ?></strong><br>
                                            <?php echo htmlspecialchars($position); ?>
                                        </p>
                                    </div>

                                    <!-- Option Button (⋮) -->
                                    <div class="dropdown" id="dropdownMenu">
                                        <button class="dropdown-btn" id="dropdownBtn"
                                            style="background: none; border: none; font-size: 24px;">⋮</button>
                                        <div class="dropdown-content"
                                            style="position: absolute; display: none; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.1); z-index: 100;">
                                            <a href="../modals/about_option.php">About</a>
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and
                                                Conditions</a>
                                        </div>
                                    </div>

                                </div>
                            </div>

                            <!-- Dropdown Script -->
                            <script>
                                const dropdownBtn = document.getElementById('dropdownBtn');
                                const dropdownMenu = document.getElementById('dropdownMenu').querySelector('.dropdown-content');

                                dropdownBtn.addEventListener('click', function (e) {
                                    e.stopPropagation();
                                    dropdownMenu.style.display = dropdownMenu.style.display === 'block' ? 'none' : 'block';
                                });

                                window.addEventListener('click', function () {
                                    dropdownMenu.style.display = 'none';
                                });
                            </script>


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
                        <a class="nav-link active" href="javascript:void(0);"><i
                                class="icon-base bx bx-user icon-sm me-1_5"></i> Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="super_admin_teams.php"><i
                                class="icon-base bx bx-group icon-sm me-1_5"></i> Teams</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

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
                                <a href="super_announcement.php">View all announcements</a>
                            </li>
                        </ul>
                    </ul>
                </div>
            </div>

            <div class="col-xl-12 col-lg-7 col-md-7 mt-4">
                <!-- Activity Timeline -->
                <div class="card card-action mb-6">
                    <div class="card-header align-items-center">
                        <h5 class="card-action-title mb-0"><i
                                class="icon-base bx bx-bar-chart-alt-2 icon-lg text-body me-4"></i>Calendar Event</h5>
                        <a href="view_activities.php" class="text-decoration-none text-primary">View</a>
                    </div>

                    <!-- Calendar & Modal -->
                    <div class="card-body pt-3">
                        <div class="col app-calendar-content" style="width:100%; max-width: 100%;">
                            <div class="card shadow-none border-0" style="width:100%; max-width: 100%;">
                                <div class="card-body pb-0">

                                    <!-- FullCalendar -->
                                    <div id="calendar" class="fc fc-media-screen fc-direction-ltr fc-theme-standard">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

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
    <script src="../assets/js/main.js"></script>

    <!-- Calendar + Event Scripts -->
    <script>
        $(document).ready(function () {
            $('#calendar').fullCalendar({
                header: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'month,agendaWeek,agendaDay'
                },
                events: <?php echo json_encode($events); ?>,
                eventRender: function (event, element) {
                    element.css('background-color', event.color);
                    element.css('color', event.textColor);
                    element.qtip({
                        content: event.description,
                        style: { classes: 'qtip-bootstrap' }
                    });
                },
                height: 'auto',
                eventClick: function (event) {
                    $('#eventTitle').text(event.title);
                    $('#eventDepartment').text(event.department);
                    $('#eventDate').text(moment(event.start).format('MMMM D, YYYY'));
                    $('#eventTime').text(moment(event.start).format('h:mm A') + " - " + moment(event.end).format('h:mm A'));
                    $('#eventDescription').text(event.description);
                    $('#eventLocation').text(event.event_location);

                    if (event.type === 'Room Reservation') {
                        $('#eventURL').parent().hide();
                    } else {
                        $('#eventURL').attr('href', event.event_url).text(event.event_url);
                        $('#eventURL').parent().show();
                    }

                    $('#eventModal').modal('show');
                }
            });
        });
    </script>

    <!-- Cover Photo Drag + Preview -->
    <script>
        function previewImage(event) {
            const coverPhoto = document.getElementById("coverPreview");
            const saveBtn = document.querySelector(".save-btn");
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = e => {
                    coverPhoto.src = e.target.result;
                    coverPhoto.style.display = "block";
                    saveBtn.classList.add("show");
                };
                reader.readAsDataURL(file);
            }
        }

        const coverPhoto = document.querySelector('.cover-photo');
        const coverPhotoContainer = document.querySelector('.cover-photo-container');
        const saveBtn = document.querySelector('.save-btn');
        let isDragging = false, offsetX, offsetY;

        if (coverPhoto) {
            coverPhoto.addEventListener('mousedown', e => {
                isDragging = true;
                offsetX = e.clientX - coverPhoto.getBoundingClientRect().left;
                offsetY = e.clientY - coverPhoto.getBoundingClientRect().top;
                coverPhoto.classList.add('dragging');
                document.body.style.cursor = 'grabbing';
            });

            document.addEventListener('mousemove', e => {
                if (isDragging) {
                    let x = e.clientX - offsetX;
                    let y = e.clientY - offsetY;
                    let maxX = coverPhotoContainer.offsetWidth - coverPhoto.offsetWidth;
                    let maxY = coverPhotoContainer.offsetHeight - coverPhoto.offsetHeight;
                    x = Math.max(0, Math.min(x, maxX));
                    y = Math.max(0, Math.min(y, maxY));
                    coverPhoto.style.left = `${x}px`;
                    coverPhoto.style.top = `${y}px`;
                }
            });

            document.addEventListener('mouseup', () => {
                isDragging = false;
                coverPhoto.classList.remove('dragging');
                document.body.style.cursor = 'grab';
                saveBtn.style.display = 'block';
            });
        }
    </script>
</body>

</html>