<?php
session_start();
require '../vendor/autoload.php';
require 'login_verification.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require '../db.php';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Fetch current user details
$username = $_SESSION['username'];
$query = $conn->prepare("SELECT id, firstname, middlename, lastname, email, department, profile_picture FROM users WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->store_result();
$query->bind_result($user_id, $firstname, $middlename, $lastname, $email, $department, $profile_picture);
$query->fetch();
$query->close();

date_default_timezone_set('Asia/Manila'); // Set timezone to your local
$now = date('Y-m-d H:i:s');
$updateActivity = $conn->prepare("UPDATE users SET last_activity = ? WHERE username = ?");
$updateActivity->bind_param("ss", $now, $username);
$updateActivity->execute();
$updateActivity->close();

if (!isset($_SESSION['profile_picture'])) {
    $_SESSION['profile_picture'] = $profile_picture ?: '../assets/img/avatars/1.png';
}

// Handle GET success message
if (isset($_GET['submit']) && $_GET['submit'] == 'success') {
    $_SESSION['success'] = 'Profile updated successfully.';
    header("Location: super_admin_settings.php"); // redirect to remove GET param
    exit();
}

// Handle POST (form submission)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_username = trim($_POST['new_username'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $profile_picture = $_FILES['profile_picture'] ?? null;

    try {
        if (!empty($new_password) && $new_password !== $confirm_password) {
            throw new Exception("Passwords do not match.");
        } elseif (!empty($new_password) && strlen($new_password) < 8) {
            throw new Exception("Password must be at least 8 characters.");
        }

        $conn->begin_transaction();

        // Profile picture update
        if ($profile_picture && $profile_picture["error"] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            $max_size = 2 * 1024 * 1024;

            if (!in_array($profile_picture["type"], $allowed_types)) {
                throw new Exception("Only JPG, JPEG, and PNG files are allowed.");
            }
            if ($profile_picture["size"] > $max_size) {
                throw new Exception("File size must be less than 2MB.");
            }

            $upload_dir = "../uploads/dp/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $image_name = time() . "_" . basename($profile_picture["name"]);
            $image_path = $upload_dir . $image_name;

            if (move_uploaded_file($profile_picture["tmp_name"], $image_path)) {
                $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                $stmt->bind_param("si", $image_path, $user_id);
                $stmt->execute();
                $stmt->close();
                $_SESSION['profile_picture'] = $image_path;
            } else {
                throw new Exception("Failed to upload image.");
            }
        }

        // Username update
        if (!empty($new_username) && $new_username !== $username) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param("s", $new_username);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                throw new Exception("Username already taken.");
            }
            $stmt->close();

            $stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
            $stmt->bind_param("si", $new_username, $user_id);
            $stmt->execute();
            $stmt->close();

            $_SESSION['username'] = $new_username;
        }

        // Password update
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();
        session_write_close();
        header("Location: super_admin_settings.php?submit=success");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        header("Location: super_admin_settings.php");
        exit();
    }
}

$conn->close();
?>



<!DOCTYPE html>

<!-- =========================================================
* Sneat - Bootstrap 5 HTML Admin Template - Pro | v1.0.0
==============================================================

* Product Page: https://themeselection.com/products/sneat-bootstrap-html-admin-template/
* Created by: ThemeSelection
* License: You must have a valid license purchased in order to legally use the theme for your project.
* Copyright ThemeSelection (https://themeselection.com)

=========================================================
 -->
<!-- beautify ignore:start -->
<html
  lang="en"
  class="light-style layout-menu-fixed"
  dir="ltr"
  data-theme="theme-default"
  data-assets-path="../assets/"
  data-template="vertical-menu-template-free"
>
  <head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"
    />

    <title>Account settings - District One</title>

    <meta name="description" content="" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/districtone.png" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
      rel="stylesheet"
    />

    <!-- Icons. Uncomment required icon fonts -->
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <!-- Page CSS -->

    <!-- Helpers -->
    <script src="../assets/vendor/js/helpers.js"></script>

    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="../assets/js/config.js"></script>
  </head>

<style>
  .app-brand-text {
  font-size: 20px !important;
  font-weight: bold;
  margin-left: 5px;
  margin-top: 10px;
}
.logo {
    margin-left: -30px;
    margin-top: 5px;
}
    body {
        font-family: Arial, sans-serif;
        max-width: 100%;
        overflow-x: hidden;

    }
    .btn-primary {
        background-color: #007bff !important; /* Bootstrap Blue */
        border-color: #007bff !important;
        color: white !important;
    }
    .nav-link.active {
        background-color: #007bff !important; /* Bootstrap blue */
        color: white !important;
        border-radius: 5px; /* Optional: adds rounded corners */
        padding: 8px 12px; /* Optional: adjusts padding */
        display: inline-block;
    }
    .nav-link:hover {
        background-color: white !important; /* Bootstrap blue */
        color: #007bff !important;
        border-radius: 5px; /* Optional: adds rounded corners */
        padding: 8px 12px; /* Optional: adjusts padding */
        display: inline-block;
    }
    .bg-menu-theme .menu-inner > .menu-item.active > .menu-link {
  color: #fff;
  background-color: rgba(47, 144, 255, 0.63) !important;
}
.bg-menu-theme .menu-sub > .menu-item.active > .menu-link:not(.menu-toggle):before {
  background-color: #2793eb !important;
  border: 3px solid #e7e7ff !important;
}
.bg-menu-theme .menu-inner > .menu-item.active:before {
  background-color: #2793eb;
}
    .swal2-container {
        z-index: 99999 !important;
    }



</style>

  <body>
          <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

      <?php
      if (isset($_SESSION['error'])) {
          echo "
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: '" . addslashes($_SESSION['error']) . "',
            confirmButtonColor: '#d33',
            confirmButtonText: 'Try again'
        });
    });
    </script>";
          unset($_SESSION['error']);
      }

      if (isset($_SESSION['success'])) {
          echo "
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '" . addslashes($_SESSION['success']) . "',
            confirmButtonColor: '#28a745'
        });
    });
    </script>";
          unset($_SESSION['success']);
      }
      ?>
       <?php include 'sidebar.php' ?>

          <!-- Content wrapper -->
          <div class="content-wrapper">
            <!-- Content -->

            <div class="container-xxl flex-grow-1 container-p-y">
              <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Account /</span> Account Settings</h4>

              <div class="row">
                <div class="col-md-12">
                  <ul class="nav nav-pills flex-column flex-md-row mb-3">                     
                  </ul>
                   <form method="POST" action="super_admin_settings.php" enctype="multipart/form-data">
                                            <input type="hidden" name="save" value="1">

                  <div class="card mb-4">
                    <h5 class="card-header">Profile Details</h5>

                    <!-- Account -->
                    <div class="card-body">
                      <div class="d-flex align-items-start align-items-sm-center gap-4">
                          <!-- Profile Picture Preview -->
                <img 
                src="<?php echo isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : '../assets/img/avatars/1.png'; ?>" 
                alt="user-avatar" 
                class="d-block rounded" 
                id="uploadedAvatar"
                style="width: 100px; height: 100px; object-fit: cover; border-radius: 50%;"
              />
                       
                        <div class="button-wrapper">
                          <label for="upload" class="btn btn-primary me-2 mb-4" tabindex="0">
                            <span class="d-none d-sm-block">Upload new photo</span>
                            <i class="bx bx-upload d-block d-sm-none"></i>
                            <input
                              type="file"
                              id="upload"
                              class="account-file-input"
                              name="profile_picture"
                              hidden
                              accept="image/png, image/jpeg"
                              onchange="previewImage(event)"
                            />
                          </label>


                          <p class="text-muted mb-0">Allowed JPG, GIF or PNG. Max size of 800K</p>
                        </div>
                      </div>
                    </div>
                    <hr class="my-0" />

                    <div class="card-body">
                                   <div class="row">
                          <div class="mb-3 col-md-6">
                            <label for="username" class="form-label">Username</label>
                            <input
                              class="form-control"
                              type="text"
                              id="username"
                              name="new_username"
                              value="<?php echo htmlspecialchars($username); ?>"
                            />
                          </div>
                          <div class="mb-3 col-md-6">
                            <label for="firstname" class="form-label">First Name</label>
                            <input class="form-control" type="text" name="firstname" id="firstname" value="<?php echo htmlspecialchars($firstname); ?>" readonly  />
                          </div>
                          <div class="mb-3 col-md-6">
                            <label for="middlename" class="form-label">Middle Name</label> 
                            <input
                              class="form-control"
                              type="text"
                              id="middlename"
                              name="middlename"
                              value="<?php echo htmlspecialchars($middlename); ?>" readonly 
                            />
                          </div>
                          <div class="mb-3 col-md-6">
                            <label for="lastname" class="form-label">Last Name</label>
                            <input
                              type="text"
                              class="form-control"
                              id="lastname"
                              name="lastname"
                              value="<?php echo htmlspecialchars($lastname); ?>" readonly 
                            />
                          </div>
                          <div class="mb-3 col-md-6">
                            <label class="form-label" for="email">Email</label>
                            <div class="input-group input-group-merge">
                              <input
                                type="text"
                                id="email"
                                name="email"
                                class="form-control"
                                value="<?php echo htmlspecialchars($email); ?>" readonly 
                              />
                            </div>
                          </div>
                            <div class="mb-3 col-md-6">
                            <label class="form-label" for="password">Password</label>
                            <div class="input-group input-group-merge">
                              <input
                                type="password"
                                id="password"
                                name="new_password"
                                class="form-control"
                                placeholder="********"
                              />
                            </div>
                          </div>
                                   <div class="mb-3 col-md-6">
                            <label class="form-label" for="password"> Confirm Password</label>
                            <div class="input-group input-group-merge">
                              <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                class="form-control"
                                placeholder="********"
                              />
                            </div>
                          </div>
                       
                        <div class="mt-2">
                          <button type="submit" name="submit" class="btn btn-primary me-2" id="submit">Update Profile</button>
                        </div>
                      </form>
                    </div>
                    <!-- /Account -->
                  </div>
            <div class="content-backdrop fade"></div>
          </div>
          <!-- Content wrapper -->
        </div>
        <!-- / Layout page -->
      </div>

      <!-- Overlay -->
      <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <!-- / Layout wrapper -->

      <script>
  function previewImage(event) {
    var reader = new FileReader();
    reader.onload = function(){
      var output = document.getElementById('uploadedAvatar');
      output.src = reader.result;
    };
    reader.readAsDataURL(event.target.files[0]);
  }

  function resetPreview() {
    document.getElementById('uploadedAvatar').src = "../assets/img/avatars/1.png"; // Default avatar
    document.getElementById('upload').value = ""; // Clear input
  }
</script>



    <!-- Core JS -->
    <!-- build:js assets/vendor/js/core.js -->
    <script src="../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../assets/vendor/libs/popper/popper.js"></script>
    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>

    <script src="../assets/vendor/js/menu.js"></script>
    <!-- endbuild -->

    <!-- Vendors JS -->

    <!-- Main JS -->
    <script src="../assets/js/main.js"></script>

    <!-- Page JS -->
    <script src="../assets/js/pages-account-settings-account.js"></script>

    <!-- Place this tag in your head or just before your close body tag. -->
    <script async defer src="https://buttons.github.io/buttons.js"></script>
  </body>
</html>