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
  header("Location: accountSettings.php"); // redirect to remove GET param
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
      $max_size = 20 * 1024 * 1024; //adjust here for the max image size.

      if (!in_array($profile_picture["type"], $allowed_types)) {
        throw new Exception("Only JPG, JPEG, and PNG files are allowed.");
      }
      if ($profile_picture["size"] > $max_size) {
        throw new Exception("File size must be less than 20MB.");
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
    header("Location: accountSettings.php?submit=success");
    exit();

  } catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = $e->getMessage();
    header("Location: accountSettings.php");
    exit();
  }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/"
  data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
  <title>Account Settings</title>

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="../assets/img/favicon/districtone.png" />

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap"
    rel="stylesheet" />

  <!-- Icons -->
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />

  <!-- Core CSS -->
  <link rel="stylesheet" href="../assets/vendor/css/core.css" />
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />
  <link rel="stylesheet" href="./css/accountSettings.css">

  <!-- Vendors CSS -->
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

  <!-- Core Helper Scripts -->
  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>

  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
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

  <!-- Content wrapper -->
  <div class="content-wrapper">
    <div class="container-xxl flex-grow-1 container-p-y">
      <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Account Settings /</span> Account Settings</h4>

      <div class="row">
        <div class="col-md-12">
          <form method="POST" action="accountSettings.php" enctype="multipart/form-data">
            <div class="card mb-4">
              <h5 class="card-header">Profile Details</h5>

              <div class="card-body">
                <div class="d-flex align-items-start align-items-sm-center gap-4">
                  <img
                    src="<?php echo isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : '../assets/img/avatars/1.png'; ?>"
                    alt="user-avatar" class="d-block rounded" height="100" width="100" id="uploadedAvatar" />

                  <div class="button-wrapper">
                    <label for="upload" class="btn btn-primary me-2 mb-4" tabindex="0">
                      <span class="d-none d-sm-block">Upload new photo</span>
                      <i class="bx bx-upload d-block d-sm-none"></i>
                      <input type="file" id="upload" class="account-file-input" name="profile_picture" hidden
                        accept="image/png, image/jpeg" onchange="previewImage(event)" />
                    </label>
                    <p class="text-muted mb-0">Allowed JPG and PNG. Max size of 20MB</p>
                  </div>
                </div>
              </div>
              <hr class="my-0" />

              <div class="card-body">
                <div class="row">
                  <div class="mb-3 col-md-6">
                    <label for="username" class="form-label">Username</label>
                    <input class="form-control" type="text" id="username" name="new_username"
                      value="<?php echo htmlspecialchars($username); ?>" />
                  </div>
                  <div class="mb-3 col-md-6">
                    <label for="firstname" class="form-label">First Name</label>
                    <input class="form-control" type="text" name="firstname" id="firstname"
                      value="<?php echo htmlspecialchars($firstname); ?>" readonly />
                  </div>
                  <div class="mb-3 col-md-6">
                    <label for="middlename" class="form-label">Middle Name</label>
                    <input class="form-control" type="text" id="middlename" name="middlename"
                      value="<?php echo htmlspecialchars($middlename); ?>" readonly />
                  </div>
                  <div class="mb-3 col-md-6">
                    <label for="lastname" class="form-label">Last Name</label>
                    <input type="text" class="form-control" id="lastname" name="lastname"
                      value="<?php echo htmlspecialchars($lastname); ?>" readonly />
                  </div>
                  <div class="mb-3 col-md-6">
                    <label class="form-label" for="email">Email</label>
                    <input type="text" id="email" name="email" class="form-control"
                      value="<?php echo htmlspecialchars($email); ?>" readonly />
                  </div>
                  <div class="mb-3 col-md-6">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="new_password" class="form-control"
                      placeholder="********" />
                  </div>
                  <div class="mb-3 col-md-6">
                    <label class="form-label" for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                      placeholder="********" />
                  </div>

                  <div class="mt-2">
                    <button type="submit" name="submit" class="btn btn-primary me-2" id="submit">Update Profile</button>
                  </div>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>

      <div class="content-backdrop fade"></div>
    </div>
  </div>

  <div class="layout-overlay layout-menu-toggle"></div>

  <!-- Core JS -->
  <script src="../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../assets/vendor/libs/popper/popper.js"></script>
  <script src="../assets/vendor/js/bootstrap.js"></script>
  <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="../assets/vendor/js/menu.js"></script>
  <script src="../assets/js/main.js"></script>

  <!-- Page Specific -->
  <script src="../assets/js/pages-account-settings-account.js"></script>

  <!-- Custom Scripts -->
  <script>
    function previewImage(event) {
      var reader = new FileReader();
      reader.onload = function () {
        document.getElementById('uploadedAvatar').src = reader.result;
      };
      reader.readAsDataURL(event.target.files[0]);
    }
  </script>
</body>

</html>