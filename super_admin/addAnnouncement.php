<?php
require '../vendor/autoload.php';
require '../db.php';
require 'login_verification.php';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$username = $_SESSION['username'] ?? null;
date_default_timezone_set('Asia/Manila');

// Update user last activity
if ($username) {
    $now = date('Y-m-d H:i:s');
    $updateActivity = $conn->prepare("UPDATE users SET last_activity = ? WHERE username = ?");
    $updateActivity->bind_param("ss", $now, $username);
    $updateActivity->execute();
    $updateActivity->close();
}

// Fetch logged-in user details
$query = $conn->prepare("SELECT id, profile_picture, cover_photo, department, firstname, middlename, lastname, email 
                         FROM users WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->store_result();
$query->bind_result($user_id, $profile_picture, $cover_photo, $department, $firstname, $middlename, $lastname, $email);
$query->fetch();
$query->close();

/* -----------------------------------------------------------
   🔹 Function to handle file upload
----------------------------------------------------------- */
function handleFileUpload($fileField)
{
    $uploadDir = "../uploads/";
    if (!is_dir($uploadDir))
        mkdir($uploadDir, 0777, true);

    if (!empty($_FILES[$fileField]['name'])) {
        $fileName = basename($_FILES[$fileField]['name']);
        $fileTmpName = $_FILES[$fileField]['tmp_name'];
        $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt'];

        if (in_array($fileType, $allowedTypes)) {
            $newPath = $uploadDir . time() . "_" . $fileName;
            if (move_uploaded_file($fileTmpName, $newPath))
                return $newPath;
        }
    }
    return "";
}

/* -----------------------------------------------------------
   🔹 Add New Announcement
----------------------------------------------------------- */
if (isset($_POST['post_announcement'])) {
    $title = $_POST['title'];
    $message = $_POST['message'];
    $department = $_POST['department'] ?? 'All departments';
    $filePath = handleFileUpload('attachment');

    $stmt = $conn->prepare("INSERT INTO announcements (title, message, file_path, department, user_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $title, $message, $filePath, $department, $user_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['success'] = "Announcement posted successfully!";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

/* -----------------------------------------------------------
   🔹 Update Announcement
----------------------------------------------------------- */
if (isset($_POST['update_announcement'])) {
    $edit_id = $_POST['edit_id'];
    $edit_title = $_POST['edit_title'];
    $edit_message = $_POST['edit_message'];
    $filePath = handleFileUpload('edit_attachment');

    // Keep existing file if no new upload
    if (empty($filePath)) {
        $stmt = $conn->prepare("SELECT file_path FROM announcements WHERE id = ?");
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $stmt->bind_result($existingFile);
        $stmt->fetch();
        $stmt->close();
        $filePath = $existingFile;
    }

    $stmt = $conn->prepare("UPDATE announcements SET title = ?, message = ?, file_path = ? WHERE id = ?");
    $stmt->bind_param("sssi", $edit_title, $edit_message, $filePath, $edit_id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Announcement updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update announcement.";
    }

    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

/* -----------------------------------------------------------
   🔹 Delete Announcement
----------------------------------------------------------- */
if (isset($_POST['delete_announcement'])) {
    $delete_id = $_POST['delete_id'];

    // Fetch file path for deletion
    $stmt = $conn->prepare("SELECT file_path FROM announcements WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->bind_result($file_path);
    $stmt->fetch();
    $stmt->close();

    // Remove file if exists
    if (!empty($file_path) && file_exists($file_path))
        unlink($file_path);

    // Delete from database
    $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->bind_param("i", $delete_id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Announcement deleted successfully!";
    } else {
        $_SESSION['error'] = "Failed to delete announcement.";
    }

    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

/* -----------------------------------------------------------
   🔹 Fetch Announcements with Pagination
----------------------------------------------------------- */
$selectedDepartment = $_GET['department'] ?? 'All';
$limit = 5; // Number of announcements per page
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $limit;

// Count total announcements
if ($selectedDepartment === 'All') {
    $countQuery = $conn->query("SELECT COUNT(*) AS total FROM announcements");
} else {
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM announcements WHERE department = ?");
    $countStmt->bind_param("s", $selectedDepartment);
    $countStmt->execute();
    $countQuery = $countStmt->get_result();
}
$total = $countQuery->fetch_assoc()['total'] ?? 0;
$totalPages = ceil($total / $limit);

// Fetch paginated results
if ($selectedDepartment === 'All') {
    $query = "SELECT a.id, a.title, a.message, a.file_path, a.department, a.user_id, a.created_at,
                     u.firstname, u.middlename, u.lastname 
              FROM announcements a 
              JOIN users u ON a.user_id = u.id 
              ORDER BY a.created_at DESC 
              LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $limit, $offset);
} else {
    $query = "SELECT a.id, a.title, a.message, a.file_path, a.department, a.user_id, a.created_at,
                     u.firstname, u.middlename, u.lastname 
              FROM announcements a 
              JOIN users u ON a.user_id = u.id 
              WHERE a.department = ? 
              ORDER BY a.created_at DESC 
              LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $selectedDepartment, $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

/* -----------------------------------------------------------
   🔹 SweetAlert Notifications
----------------------------------------------------------- */
if (isset($_SESSION['error'])) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', () => {
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                confirmButtonColor: '#d33',
                confirmButtonText: 'Try again',
                text: '" . addslashes($_SESSION['error']) . "'
            });
        });
    </script>";
    unset($_SESSION['error']);
}

if (isset($_SESSION['success'])) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', () => {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                confirmButtonColor: '#3085d6',
                text: '" . addslashes($_SESSION['success']) . "'
            });
        });
    </script>";
    unset($_SESSION['success']);
}
?>
<!DOCTYPE html>

<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/"
    data-template="vertical-menu-template-free">

<head>

    <head>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Add Announcement</title>

        <link rel="icon" type="image/x-icon" href="../assets/img/favicon/districtone.png" />

        <!-- Fonts & Icons -->
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&display=swap"
            rel="stylesheet" />
        <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />

        <!-- Core CSS -->
        <link rel="stylesheet" href="../assets/vendor/css/core.css" />
        <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
        <link rel="stylesheet" href="../assets/css/demo.css" />
        <link rel="stylesheet" href="./css/admin_announcement.css" />
        <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
        <link rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" />

        <!-- Helpers -->
        <script src="../assets/vendor/js/helpers.js"></script>
        <script src="../assets/js/config.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    </head>

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
            <div class="card app-calendar-wrapper">
                <div class="row g-0">
                    <div class="col border-end" id="app-calendar-sidebar">
                        <div class="px-3 pt-2">
                            <div class="container mt-4">
                                <div class="row">
                                    <!-- Left Side: Posts Feed -->
                                    <div class="col-md-12">
                                        <div class="border-bottom p-3 mb-4 d-flex align-items-center gap-3">
                                            <button class="btn btn-primary" data-bs-toggle="offcanvas"
                                                data-bs-target="#addEventSidebar" style="background-color:#007bff">
                                                + Create
                                            </button>

                                            <!-- Dropdown Sort Filter -->

                                            <div class="dropdown">
                                                <button class="btn btn-light dropdown-toggle" type="button"
                                                    id="departmentDropdown" data-bs-toggle="dropdown">
                                                    All Departments
                                                </button>
                                                <ul class="dropdown-menu" aria-labelledby="departmentDropdown">
                                                    <li><a class="dropdown-item department-filter active" href="#"
                                                            data-department="All">All Departments</a></li>
                                                    <li><a class="dropdown-item department-filter" href="#"
                                                            data-department="Office of the General Manager">Office of
                                                            the General Manager</a></li>
                                                    <li><a class="dropdown-item department-filter" href="#"
                                                            data-department="Management Information Services Section">Management
                                                            Information Services Section</a></li>
                                                    <li><a class="dropdown-item department-filter" href="#"
                                                            data-department="Administrative Department">Administrative
                                                            Department</a></li>
                                                    <li><a class="dropdown-item department-filter" href="#"
                                                            data-department="Finance Department">Finance Department</a>
                                                    </li>
                                                    <li><a class="dropdown-item department-filter" href="#"
                                                            data-department="Commercial Department">Commercial
                                                            Department</a></li>
                                                    <li><a class="dropdown-item department-filter" href="#"
                                                            data-department="Technical Services Department">Technical
                                                            Services Department</a></li>
                                                    <li><a class="dropdown-item department-filter" href="#"
                                                            data-department="Operations Department">Operations
                                                            Department</a></li>
                                                </ul>
                                            </div>
                                        </div>

                                        <?php include 'pigination.php' ?>
                                        <!-- PIGINATION MODULE -->
                                        <?php if ($result->num_rows > 0): ?>
                                            <?php while ($row = $result->fetch_assoc()): ?>
                                                <div class="card mb-3">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <h3 class="card-title">
                                                                <?php echo htmlspecialchars($row['title']); ?>
                                                            </h3>

                                                            <!-- Three-Dot Dropdown Menu -->
                                                            <div class="dropdown">
                                                                <button class="btn btn-light btn-sm border-0" type="button"
                                                                    data-bs-toggle="dropdown">
                                                                    <i class="bi bi-three-dots-vertical"></i>
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end">
                                                                    <li>
                                                                        <a class="dropdown-item edit-btn" href="#"
                                                                            data-id="<?php echo $row['id']; ?>"
                                                                            data-title="<?php echo htmlspecialchars($row['title']); ?>"
                                                                            data-message="<?php echo htmlspecialchars($row['message']); ?>">
                                                                            Edit
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <form method="POST" class="delete-form">
                                                                            <input type="hidden" name="delete_id"
                                                                                value="<?php echo $row['id']; ?>">
                                                                            <input type="hidden" name="delete_announcement"
                                                                                value="true">
                                                                            <button type="button"
                                                                                class="dropdown-item text-danger btn-delete">Delete</button>
                                                                        </form>

                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        </div>

                                                        <p class="card-text">
                                                            <?php echo nl2br(htmlspecialchars($row['message'])); ?>
                                                        </p>
                                                        <small class="text-muted">Posted by:
                                                            <?php echo htmlspecialchars($row['firstname'] . " " . $row['middlename'] . " " . $row['lastname']); ?>
                                                        </small><br>
                                                        <small class="text-muted">Posted on:
                                                            <?php echo $row['created_at']; ?></small>

                                                        <!-- File Attachments -->
                                                        <?php if (!empty($row['file_path'])): ?>
                                                            <?php
                                                            $file_path = '../uploads/' . basename($row['file_path']);
                                                            $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                                                            ?>

                                                            <?php if (file_exists($file_path)): ?>
                                                                <?php if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                                                    <div class="mt-3">
                                                                        <img src="<?php echo htmlspecialchars($file_path); ?>"
                                                                            alt="Post Image" class="img-fluid">
                                                                    </div>
                                                                <?php elseif ($ext === 'pdf'): ?>
                                                                    <!-- Replace iframe with View button -->
                                                                    <div class="mt-3">
                                                                        <a href="<?php echo htmlspecialchars($file_path); ?>"
                                                                            target="_blank" class="btn btn-sm btn-danger">
                                                                            View PDF
                                                                        </a>
                                                                        <a href="<?php echo htmlspecialchars($file_path); ?>" download
                                                                            class="btn btn-sm btn-secondary">
                                                                            Download PDF
                                                                        </a>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <div class="mt-3">
                                                                        <a href="<?php echo htmlspecialchars($file_path); ?>" download
                                                                            class="btn btn-sm btn-secondary">Download File</a>
                                                                    </div>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <p class="text-danger mt-2">File not found.</p>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <p class="text-center">No announcements available.</p>
                                        <?php endif; ?>
                                        <?php include 'pigination.php' ?>
                                        <!-- PIGINATION MODULE -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <input type="hidden" name="edit_id" id="edit_id">

                        <label>Title:</label>
                        <input type="text" name="edit_title" id="edit_title" class="form-control" required>

                        <label>Message:</label>
                        <textarea name="edit_message" id="edit_message" class="form-control" rows="4"
                            required></textarea>

                        <label>Attached File:</label>
                        <input type="file" name="edit_attachment" class="form-control">
                        <p class="mt-2"><a id="current_file_link" href="#" target="_blank">View Current File</a></p>

                        <div class="modal-footer">
                            <button type="submit" name="update_announcement" class="btn btn-primary">Update</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Announcement Offcanvas -->
    <div class="offcanvas offcanvas-end event-sidebar" tabindex="-1" id="addEventSidebar"
        aria-labelledby="addEventSidebarLabel">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title" id="addEventSidebarLabel">Add Announcement</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">

            <form method="POST" enctype="multipart/form-data">
                <div class="mb-6 form-control-validation fv-plugins-icon-container">
                    <label class="form-label" for="eventTitle">Title</label>

                    <input type="text" class="form-control" id="title" name="title" placeholder="Event Title">

                    <div class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                    </div>
                </div>
                <div class="mb-6">
                    <label class="form-label" for="department">Department</label>
                    <div class="position-relative">
                        <select class="select2 select-event-label form-select" id="department" name="department">
                            <option value="All Departments" selected>All Departments
                            </option>
                            <option value="Office of the General Manager">Office of the
                                General
                                Manager</option>
                            <option value="Management Information Services Section">
                                Management
                                Information Services Section</option>
                            <option value="Administrative Department">Administrative
                                Department
                            </option>
                            <option value="Finance Department">Finance Department
                            </option>
                            <option value="Commercial Department">Commercial Department
                            </option>
                            <option value="Technical Services Department">Technical
                                Services
                                Department</option>
                            <option value="Operations Department">Operations Department
                            </option>
                        </select>
                    </div>
                    <div class="mb-6">
                        <label class="form-label" for="message">Message</label>
                        <textarea class="form-control" name="message" id="message"></textarea>
                    </div>

                    <div class="mb-6">
                        <label class="form-label" for="attachment"
                            style="position:relative; top:10px; font-size:15px;">Attach
                            File</label>
                        <input type="file" class="form-control" id="attachment" name="attachment" placeholder="">
                    </div>

                    <!-- Preview Section -->
                    <div id="preview-container" style="display: none; margin-top: 10px;">
                        <p>File Preview:</p>
                        <img id="file-preview" src="#" alt="File Preview" style="max-width: 100%; display: none;">
                        <iframe id="pdf-preview" style="width:100%; height:500px; display:none;"></iframe>
                        <a id="doc-preview" href="#" target="_blank" style="display:none;">Open
                            Document</a>
                        <p id="file-name"></p>
                    </div>


                    <div class="d-flex justify-content-sm-between justify-content-start mt-6 gap-2">
                        <div class="d-flex" style="margin-top:20px;">
                            <button type="button" name="post_announcement" id="postAnnouncementBtn"
                                class="btn btn-primary btn-add-event me-4"
                                style="background-color: #007bff;">Post</button>
                            <button type="reset" class="btn btn-primary btn-cancel"
                                style="background-color:rgb(255, 55, 55); border-color:rgb(179, 60, 60);">Cancel</button>
                        </div>
                        <button class="btn btn-label-danger btn-delete-event d-none">Delete</button>
                    </div>
                    <input type="hidden">
            </form>
        </div>
    </div>
    </div>

    <div class="content-backdrop fade"></div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">Success</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (isset($_SESSION['success'])) {
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                    } ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="errorModalLabel">Error</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (isset($_SESSION['error'])) {
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                    } ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>





    <script>
        // SweetAlert delete confirmation (keep your existing script)
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.btn-delete').forEach(btn => {
                btn.addEventListener('click', function () {
                    const form = this.closest('form');
                    Swal.fire({
                        title: 'Delete Post?',
                        text: "Are you sure you want to delete this post?",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Yes, delete it!'
                    }).then((result) => {
                        if (result.isConfirmed) form.submit();
                    });
                });
            });
        });
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            document.querySelectorAll(".edit-btn").forEach(button => {
                button.addEventListener("click", function (event) {
                    event.preventDefault(); // Prevent default link behavior

                    document.getElementById("edit_id").value = this.getAttribute("data-id");
                    document.getElementById("edit_title").value = this.getAttribute("data-title");
                    document.getElementById("edit_message").value = this.getAttribute("data-message");

                    var filePath = this.getAttribute("data-file"); // Get file path
                    var fileLink = document.getElementById("current_file_link");
                    if (filePath) {
                        fileLink.href = filePath;
                        fileLink.textContent = "View Current File";
                        fileLink.style.display = "inline";
                    } else {
                        fileLink.style.display = "none";
                    }

                    var editModal = new bootstrap.Modal(document.getElementById("editModal"));
                    editModal.show();
                });
            });
        });
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            document.querySelectorAll(".department-filter").forEach(item => {
                item.addEventListener("click", function (event) {
                    event.preventDefault();

                    let selectedDepartment = this.getAttribute("data-department");
                    document.getElementById("departmentDropdown").textContent = selectedDepartment;

                    // Update active class
                    document.querySelectorAll(".department-filter").forEach(el => el.classList.remove("active"));
                    this.classList.add("active");

                    // Reload announcements with selected department
                    window.location.href = "?department=" + encodeURIComponent(selectedDepartment);
                });
            });
        });
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            <?php if (isset($_SESSION['success'])) { ?>
                var successModal = new bootstrap.Modal(document.getElementById('successModal'));
                successModal.show();
            <?php } ?>

            <?php if (isset($_SESSION['error'])) { ?>
                var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                errorModal.show();
            <?php } ?>
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const postBtn = document.getElementById('postAnnouncementBtn');
            const form = postBtn.closest('form');

            postBtn.addEventListener('click', function () {
                Swal.fire({
                    title: 'Post Announcement',
                    text: "Are you sure you want to post this announcement?",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, post it!',
                    cancelButtonText: 'No, cancel',
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show success message FIRST
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: "Announcement has been successfully posted!",
                            showConfirmButton: true,
                            confirmButtonColor: '#3085d6',
                        }).then(() => {
                            // After success alert, submit the form
                            const hiddenInput = document.createElement('input');
                            hiddenInput.type = 'hidden';
                            hiddenInput.name = 'post_announcement';
                            hiddenInput.value = 'true';
                            form.appendChild(hiddenInput);
                            postBtn.disabled = true;
                            form.submit();
                        });
                    }
                });
            });
        });
    </script>

    <script>
        document.getElementById('attachment').addEventListener('change', function (event) {
            const file = event.target.files[0];
            const previewContainer = document.getElementById('preview-container');
            const previewImage = document.getElementById('file-preview');
            const pdfPreview = document.getElementById('pdf-preview');
            const docPreview = document.getElementById('doc-preview');
            const fileName = document.getElementById('file-name');

            if (file) {
                const fileType = file.type;
                const reader = new FileReader();

                previewContainer.style.display = "block";
                fileName.textContent = "Selected File: " + file.name;

                // Reset display settings
                previewImage.style.display = "none";
                pdfPreview.style.display = "none";
                docPreview.style.display = "none";

                if (fileType.startsWith("image")) {
                    reader.onload = function (e) {
                        previewImage.src = e.target.result;
                        previewImage.style.display = "block";
                    };
                    reader.readAsDataURL(file);
                } else if (fileType === "application/pdf") {
                    reader.onload = function (e) {
                        pdfPreview.src = e.target.result;
                        pdfPreview.style.display = "block";
                    };
                    reader.readAsDataURL(file);
                } else if (fileType.includes("word") || fileType.includes("excel") || file.name.endsWith(".docx") || file.name.endsWith(".xlsx")) {
                    const fileURL = URL.createObjectURL(file);
                    docPreview.href = "https://view.officeapps.live.com/op/view.aspx?src=" + encodeURIComponent(fileURL);
                    docPreview.style.display = "block";
                    docPreview.textContent = "Preview in Microsoft Office";
                } else {
                    fileName.textContent += " (No preview available)";
                }
            } else {
                previewContainer.style.display = "none";
            }
        });

        document.getElementById('post-button').addEventListener('click', function () {
            const previewContainer = document.getElementById('preview-container');
            const announcementContainer = document.getElementById('announcement');

            // If there is a selected file with preview
            if (previewContainer.style.display === "block") {
                // Clone the preview and file name, and append them to the announcement
                const previewClone = previewContainer.cloneNode(true);
                previewClone.style.display = "block"; // Make sure it's visible
                announcementContainer.appendChild(previewClone);
                announcementContainer.appendChild(document.createElement('hr')); // Adding a separator line for better visual distinction
            }
        });
    </script>
</body>

</html>