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
    $uploadDir = "../uploads/announcements/";
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="./css/admin_announcement.css" />
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <!-- Helpers -->
    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --ann-primary: #007bff;
            --ann-border: #e4e6ef;
            --ann-muted: #6c757d;
        }

        .ann-toolbar {
            display: flex;
            align-items: center;
            gap: .75rem;
            flex-wrap: wrap;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--ann-border);
            border-radius: 12px;
            background: #fff;
        }

        .ann-toolbar .btn-primary {
            background-color: var(--ann-primary);
            border-color: var(--ann-primary);
            font-weight: 600;
        }

        .ann-post {
            border: 1px solid var(--ann-border);
            border-radius: 12px;
            margin-bottom: 1.25rem;
            transition: box-shadow .15s ease-in-out;
        }

        .ann-post:hover {
            box-shadow: 0 2px 10px rgba(0, 0, 0, .06);
        }

        .ann-post .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: .35rem;
        }

        .ann-post-meta {
            display: flex;
            flex-wrap: wrap;
            gap: .4rem .9rem;
            font-size: .82rem;
            color: var(--ann-muted);
            margin-bottom: .5rem;
        }

        .ann-dept-badge {
            display: inline-block;
            background: #eef4ff;
            color: var(--ann-primary);
            font-size: .72rem;
            font-weight: 600;
            padding: .25rem .6rem;
            border-radius: 999px;
        }

        .ann-empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--ann-muted);
        }

        .ann-empty-state i {
            font-size: 2.5rem;
            margin-bottom: .5rem;
            display: block;
        }

        .ann-attachment img {
            border-radius: 8px;
            max-height: 320px;
            object-fit: cover;
        }

        .offcanvas-body label.form-label {
            font-weight: 500;
        }

        #preview-container {
            border: 1px dashed var(--ann-border);
            border-radius: 10px;
            padding: .75rem;
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
            <div class="card app-calendar-wrapper">
                <div class="row g-0">
                    <div class="col border-end" id="app-calendar-sidebar">
                        <div class="px-3 pt-2">
                            <div class="container mt-4">
                                <div class="row">
                                    <div class="col-md-12">

                                        <div class="ann-toolbar">
                                            <button class="btn btn-primary" data-bs-toggle="offcanvas"
                                                data-bs-target="#addEventSidebar">
                                                <i class='bx bx-plus'></i> Create Announcement
                                            </button>

                                            <div class="dropdown">
                                                <button class="btn btn-light dropdown-toggle" type="button"
                                                    id="departmentDropdown" data-bs-toggle="dropdown">
                                                    <?= htmlspecialchars($selectedDepartment === 'All' ? 'All Departments' : $selectedDepartment) ?>
                                                </button>
                                                <ul class="dropdown-menu" aria-labelledby="departmentDropdown">
                                                    <li><a class="dropdown-item department-filter <?= $selectedDepartment === 'All' ? 'active' : '' ?>"
                                                            href="#" data-department="All">All Departments</a></li>
                                                    <?php foreach ($departments as $dept): ?>
                                                        <li><a class="dropdown-item department-filter <?= $selectedDepartment === $dept ? 'active' : '' ?>"
                                                                href="#" data-department="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></a>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>

                                            <span class="text-muted small ms-auto"><?= (int) $total ?> announcement<?= $total == 1 ? '' : 's' ?></span>
                                        </div>

                                        <?php include 'pigination.php' ?>

                                        <?php if ($result->num_rows > 0): ?>
                                            <?php while ($row = $result->fetch_assoc()): ?>
                                                <div class="card ann-post">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div>
                                                                <h3 class="card-title">
                                                                    <?php echo htmlspecialchars($row['title']); ?>
                                                                </h3>
                                                                <span class="ann-dept-badge">
                                                                    <?php echo htmlspecialchars($row['department']); ?>
                                                                </span>
                                                            </div>

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
                                                                            data-message="<?php echo htmlspecialchars($row['message']); ?>"
                                                                            data-file="<?php echo htmlspecialchars($row['file_path']); ?>">
                                                                            <i class='bx bx-edit-alt'></i> Edit
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <form method="POST" class="delete-form">
                                                                            <input type="hidden" name="delete_id"
                                                                                value="<?php echo $row['id']; ?>">
                                                                            <input type="hidden" name="delete_announcement"
                                                                                value="true">
                                                                            <button type="button"
                                                                                class="dropdown-item text-danger btn-delete">
                                                                                <i class='bx bx-trash'></i> Delete</button>
                                                                        </form>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        </div>

                                                        <p class="card-text mt-2">
                                                            <?php echo nl2br(htmlspecialchars($row['message'])); ?>
                                                        </p>

                                                        <div class="ann-post-meta">
                                                            <span><i class='bx bx-user'></i>
                                                                <?php echo htmlspecialchars($row['firstname'] . " " . $row['middlename'] . " " . $row['lastname']); ?>
                                                            </span>
                                                            <span><i class='bx bx-time'></i>
                                                                <?php echo htmlspecialchars($row['created_at']); ?></span>
                                                        </div>

                                                        <!-- File Attachments -->
                                                        <?php if (!empty($row['file_path'])): ?>
                                                            <?php
                                                            $file_path = '../uploads/announcements/' . basename($row['file_path']);
                                                            $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                                                            ?>
                                                            <?php if (file_exists($file_path)): ?>
                                                                <div class="ann-attachment mt-2">
                                                                    <?php if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                                                        <img src="<?php echo htmlspecialchars($file_path); ?>"
                                                                            alt="Post Image" class="img-fluid">
                                                                    <?php elseif ($ext === 'pdf'): ?>
                                                                        <a href="<?php echo htmlspecialchars($file_path); ?>"
                                                                            target="_blank" class="btn btn-sm btn-danger">
                                                                            <i class='bx bxs-file-pdf'></i> View PDF
                                                                        </a>
                                                                        <a href="<?php echo htmlspecialchars($file_path); ?>"
                                                                            download class="btn btn-sm btn-secondary">
                                                                            <i class='bx bx-download'></i> Download
                                                                        </a>
                                                                    <?php else: ?>
                                                                        <a href="<?php echo htmlspecialchars($file_path); ?>"
                                                                            download class="btn btn-sm btn-secondary">
                                                                            <i class='bx bx-file'></i> Download File
                                                                        </a>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php else: ?>
                                                                <p class="text-danger mt-2 mb-0">File not found.</p>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <div class="ann-empty-state">
                                                <i class='bx bx-news'></i>
                                                No announcements available.
                                            </div>
                                        <?php endif; ?>
                                        <?php include 'pigination.php' ?>
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

                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="edit_title" id="edit_title" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea name="edit_message" id="edit_message" class="form-control" rows="4"
                                required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Attached File</label>
                            <input type="file" name="edit_attachment" class="form-control">
                            <p class="mt-2 mb-0"><a id="current_file_link" href="#" target="_blank">View Current
                                    File</a></p>
                        </div>

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
            <form method="POST" enctype="multipart/form-data" id="addAnnouncementForm">
                <div class="mb-3">
                    <label class="form-label" for="title">Title</label>
                    <input type="text" class="form-control" id="title" name="title" placeholder="Announcement title">
                </div>

                <div class="mb-3">
                    <label class="form-label" for="department">Department</label>
                    <select class="form-select" id="department" name="department">
                        <option value="All Departments" selected>All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="message">Message</label>
                    <textarea class="form-control" name="message" id="message" rows="4"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="attachment">Attach File</label>
                    <input type="file" class="form-control" id="attachment" name="attachment">
                </div>

                <div id="preview-container" class="mb-3" style="display: none;">
                    <p class="mb-2 fw-medium">File Preview</p>
                    <img id="file-preview" src="#" alt="File Preview" style="max-width: 100%; display: none;">
                    <iframe id="pdf-preview" style="width:100%; height:300px; display:none;"></iframe>
                    <a id="doc-preview" href="#" target="_blank" style="display:none;">Open Document</a>
                    <p id="file-name" class="small text-muted mb-0 mt-1"></p>
                </div>

                <div class="d-flex justify-content-start gap-2 mt-4">
                    <button type="button" id="postAnnouncementBtn" class="btn btn-primary">
                        <i class='bx bx-send'></i> Post
                    </button>
                    <button type="reset" class="btn"
                        style="background-color:#ff3737; border-color:#b33c3c; color:#fff;">Cancel</button>
                </div>
            </form>
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
    <script async defer src="https://buttons.github.io/buttons.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // ---- Delete confirmation ----
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

            // ---- Edit modal population ----
            document.querySelectorAll(".edit-btn").forEach(button => {
                button.addEventListener("click", function (event) {
                    event.preventDefault();

                    document.getElementById("edit_id").value = this.getAttribute("data-id");
                    document.getElementById("edit_title").value = this.getAttribute("data-title");
                    document.getElementById("edit_message").value = this.getAttribute("data-message");

                    const filePath = this.getAttribute("data-file");
                    const fileLink = document.getElementById("current_file_link");
                    if (filePath) {
                        fileLink.href = filePath;
                        fileLink.style.display = "inline";
                    } else {
                        fileLink.style.display = "none";
                    }

                    new bootstrap.Modal(document.getElementById("editModal")).show();
                });
            });

            // ---- Department filter ----
            document.querySelectorAll(".department-filter").forEach(item => {
                item.addEventListener("click", function (event) {
                    event.preventDefault();
                    const selectedDepartment = this.getAttribute("data-department");
                    window.location.href = "?department=" + encodeURIComponent(selectedDepartment);
                });
            });

            // ---- Post announcement confirmation ----
            const postBtn = document.getElementById('postAnnouncementBtn');
            const form = document.getElementById('addAnnouncementForm');

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
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'post_announcement';
                        hiddenInput.value = 'true';
                        form.appendChild(hiddenInput);
                        postBtn.disabled = true;
                        form.submit();
                    }
                });
            });

            // ---- Attachment preview ----
            document.getElementById('attachment').addEventListener('change', function (event) {
                const file = event.target.files[0];
                const previewContainer = document.getElementById('preview-container');
                const previewImage = document.getElementById('file-preview');
                const pdfPreview = document.getElementById('pdf-preview');
                const docPreview = document.getElementById('doc-preview');
                const fileName = document.getElementById('file-name');

                previewImage.style.display = "none";
                pdfPreview.style.display = "none";
                docPreview.style.display = "none";

                if (!file) {
                    previewContainer.style.display = "none";
                    return;
                }

                previewContainer.style.display = "block";
                fileName.textContent = "Selected file: " + file.name;

                const reader = new FileReader();
                if (file.type.startsWith("image")) {
                    reader.onload = e => {
                        previewImage.src = e.target.result;
                        previewImage.style.display = "block";
                    };
                    reader.readAsDataURL(file);
                } else if (file.type === "application/pdf") {
                    reader.onload = e => {
                        pdfPreview.src = e.target.result;
                        pdfPreview.style.display = "block";
                    };
                    reader.readAsDataURL(file);
                } else if (file.type.includes("word") || file.type.includes("excel") ||
                    file.name.endsWith(".docx") || file.name.endsWith(".xlsx")) {
                    const fileURL = URL.createObjectURL(file);
                    docPreview.href = "https://view.officeapps.live.com/op/view.aspx?src=" + encodeURIComponent(fileURL);
                    docPreview.style.display = "block";
                    docPreview.textContent = "Preview in Microsoft Office";
                } else {
                    fileName.textContent += " (no preview available)";
                }
            });
        });
    </script>
</body>

</html>