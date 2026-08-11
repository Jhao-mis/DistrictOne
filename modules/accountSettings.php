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
    if ($profile_picture && $profile_picture["error"] != UPLOAD_ERR_NO_FILE) {
      if ($profile_picture["error"] !== UPLOAD_ERR_OK) {
        $upload_errors = [
          UPLOAD_ERR_INI_SIZE   => "File exceeds the server's maximum upload size.",
          UPLOAD_ERR_FORM_SIZE  => "File exceeds the form's maximum upload size.",
          UPLOAD_ERR_PARTIAL    => "File was only partially uploaded. Please try again.",
          UPLOAD_ERR_NO_TMP_DIR => "Server is missing a temporary folder for uploads.",
          UPLOAD_ERR_CANT_WRITE => "Server failed to write the file to disk.",
          UPLOAD_ERR_EXTENSION  => "Upload was blocked by a server extension.",
        ];
        throw new Exception($upload_errors[$profile_picture["error"]] ?? "Photo upload failed. Please try again.");
      }

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

  <style>
    :root {
      --acc-primary: #007bff;
      --acc-border: #e4e6ef;
      --acc-muted: #6c757d;
    }

    .acc-section {
      border: 1px solid var(--acc-border);
      border-radius: 12px;
      padding: 1.5rem;
      margin-bottom: 1.75rem;
      background: #fff;
    }

    .acc-section-header {
      display: flex;
      align-items: center;
      gap: .6rem;
      margin-bottom: 1.25rem;
      padding-bottom: .75rem;
      border-bottom: 2px solid var(--acc-border);
      font-weight: 600;
      font-size: 1.05rem;
    }

    .acc-section-header i {
      color: var(--acc-primary);
      font-size: 1.15rem;
    }

    .acc-avatar-wrapper {
      position: relative;
      width: 96px;
      height: 96px;
      flex-shrink: 0;
    }

    .acc-avatar-wrapper img {
      width: 96px;
      height: 96px;
      object-fit: cover;
      border-radius: 50%;
      border: 1px solid var(--acc-border);
    }

    .acc-avatar-edit {
      position: absolute;
      bottom: 0;
      right: 0;
      background: var(--acc-primary);
      color: #fff;
      width: 30px;
      height: 30px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      border: 2px solid #fff;
    }

    .acc-avatar-edit:hover {
      background: #0056b3;
    }

    .acc-readonly-field {
      background: #f7f8fa;
      color: var(--acc-muted);
    }

    .acc-field-hint {
      font-size: .8rem;
      color: var(--acc-muted);
      margin-top: .3rem;
    }

    .acc-password-wrap {
      position: relative;
    }

    .acc-password-toggle {
      position: absolute;
      right: .75rem;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: var(--acc-muted);
      background: none;
      border: none;
      padding: 0;
    }

    .acc-action-bar {
      position: sticky;
      bottom: 0;
      background: #fff;
      border-top: 1px solid var(--acc-border);
      padding: 1rem 1.5rem;
      margin: 0 -1.5rem -1.5rem;
      display: flex;
      justify-content: flex-end;
      border-radius: 0 0 12px 12px;
    }

    .acc-action-bar .btn {
      min-width: 170px;
      font-weight: 600;
    }

    #passwordMismatch {
      display: none;
    }

    @media (max-width: 576px) {
      .acc-action-bar .btn {
        width: 100%;
      }
    }
  </style>
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

  <?php
  $role = $_SESSION['role'];

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
      <!-- <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Account Settings /</span> Account Settings</h4> -->

      <div class="row">
        <div class="col-md-12">
          <form method="POST" action="accountSettings.php" enctype="multipart/form-data" id="accountSettingsForm">
            <div class="card mb-4">
              <div class="card-body">

                <!-- Profile Photo -->
                <div class="acc-section">
                  <div class="acc-section-header"><i class='bx bx-camera'></i> Profile Photo</div>
                  <div class="d-flex align-items-center gap-4 flex-wrap">
                    <div class="acc-avatar-wrapper">
                      <img
                        src="<?php echo htmlspecialchars($_SESSION['profile_picture'] ?? '../assets/img/avatars/1.png'); ?>"
                        alt="user-avatar" id="uploadedAvatar" />
                      <label for="upload" class="acc-avatar-edit" tabindex="0" title="Change photo">
                        <i class='bx bx-pencil'></i>
                      </label>
                      <input type="file" id="upload" class="account-file-input" name="profile_picture" hidden
                        accept="image/png, image/jpeg" onchange="previewImage(event)" />
                    </div>
                    <div>
                      <label for="upload" class="btn btn-primary mb-2" tabindex="0">
                        <i class='bx bx-upload'></i> Upload new photo
                      </label>
                      <p class="acc-field-hint mb-0">JPG or PNG only. Max size 20MB.</p>
                    </div>
                  </div>
                </div>

                <!-- Personal Information (read-only) -->
                <div class="acc-section">
                  <div class="acc-section-header"><i class='bx bx-id-card'></i> Personal Information</div>
                  <!-- <p class="acc-field-hint mt-n2 mb-3">These details are managed by HR/Admin and can't be edited
                    here.</p> -->
                  <div class="row">
                    <div class="mb-3 col-md-6">
                      <label for="firstname" class="form-label">First Name</label>
                      <input class="form-control acc-readonly-field" type="text" id="firstname"
                        value="<?php echo htmlspecialchars($firstname); ?>" readonly />
                    </div>
                    <div class="mb-3 col-md-6">
                      <label for="middlename" class="form-label">Middle Name</label>
                      <input class="form-control acc-readonly-field" type="text" id="middlename"
                        value="<?php echo htmlspecialchars($middlename); ?>" readonly />
                    </div>
                    <div class="mb-3 col-md-6">
                      <label for="lastname" class="form-label">Last Name</label>
                      <input type="text" class="form-control acc-readonly-field" id="lastname"
                        value="<?php echo htmlspecialchars($lastname); ?>" readonly />
                    </div>
                    <div class="mb-3 col-md-6">
                      <label class="form-label" for="email">Email</label>
                      <input type="text" id="email" class="form-control acc-readonly-field"
                        value="<?php echo htmlspecialchars($email); ?>" readonly />
                    </div>
                  </div>
                </div>

                <!-- Account Credentials -->
                <div class="acc-section mb-0">
                  <div class="acc-section-header"><i class='bx bx-lock-alt'></i> Account Credentials</div>
                  <div class="row">
                    <div class="mb-3 col-md-6">
                      <label for="username" class="form-label">Username</label>
                      <input class="form-control" type="text" id="username" name="new_username"
                        value="<?php echo htmlspecialchars($username); ?>" />
                    </div>
                    <div class="mb-3 col-md-6"><!-- spacer to keep grid aligned --></div>

                    <div class="mb-3 col-md-6">
                      <label class="form-label" for="password">New Password</label>
                      <div class="acc-password-wrap">
                        <input type="password" id="password" name="new_password" class="form-control"
                          placeholder="Leave blank to keep current password" minlength="8" />
                        <button type="button" class="acc-password-toggle" onclick="togglePassword('password', this)">
                          <i class='bx bx-show'></i>
                        </button>
                      </div>
                      <p class="acc-field-hint">At least 8 characters.</p>
                    </div>
                    <div class="mb-3 col-md-6">
                      <label class="form-label" for="confirm_password">Confirm Password</label>
                      <div class="acc-password-wrap">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                          placeholder="Re-enter new password" />
                        <button type="button" class="acc-password-toggle"
                          onclick="togglePassword('confirm_password', this)">
                          <i class='bx bx-show'></i>
                        </button>
                      </div>
                      <p class="acc-field-hint text-danger" id="passwordMismatch">Passwords do not match.</p>
                    </div>
                  </div>
                </div>

              </div>

              <div class="acc-action-bar">
                <button type="submit" name="submitBtn" class="btn btn-primary" id="submitBtn">
                  <i class='bx bx-save'></i> Update Profile
                </button>
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
      const reader = new FileReader();
      reader.onload = function () {
        document.getElementById('uploadedAvatar').src = reader.result;
      };
      reader.readAsDataURL(event.target.files[0]);
    }

    function togglePassword(fieldId, button) {
      const field = document.getElementById(fieldId);
      const icon = button.querySelector('i');
      const isHidden = field.type === 'password';
      field.type = isHidden ? 'text' : 'password';
      icon.classList.toggle('bx-show', !isHidden);
      icon.classList.toggle('bx-hide', isHidden);
    }

    document.getElementById('accountSettingsForm').addEventListener('submit', function (e) {
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirm_password').value;
      const mismatchHint = document.getElementById('passwordMismatch');

      if (password && password !== confirmPassword) {
        e.preventDefault();
        mismatchHint.style.display = 'block';
        document.getElementById('confirm_password').focus();
        return;
      }
      mismatchHint.style.display = 'none';

      e.preventDefault();
      Swal.fire({
        title: 'Update Profile',
        text: 'Save these changes to your account?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, update',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#007bff',
        cancelButtonColor: '#d33',
      }).then((result) => {
        if (result.isConfirmed) {
          this.submit();
        }
      });
    });
  </script>
</body>

</html>