<?php
session_start();
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

/* =====================================
   LOAD ENV
===================================== */

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$host = $_ENV['DB_HOST'];
$db = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASS'];

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
  die("Database Connection Failed: " . $conn->connect_error);
}

/* =====================================
   SHOW RESET ERROR UI
===================================== */

function showResetMessage($title, $message)
{
  ?>

  <!DOCTYPE html>
  <html lang="en">

  <head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Password Reset</title>

    <link rel="stylesheet" href="assets/vendor/css/core.css">
    <link rel="stylesheet" href="assets/vendor/css/theme-default.css">
    <link rel="stylesheet" href="assets/vendor/css/pages/page-auth.css">

    <style>
      body {
        background-image: url('assets/img/backgrounds/jan.jpg');
        background-size: cover;
        background-position: center;
      }

      .card {
        max-width: 450px;
        margin: auto;
      }
    </style>

  </head>

  <body>

    <div class="container-xxl">
      <div class="authentication-wrapper authentication-basic container-p-y">

        <div class="card">

          <div class="card-body text-center">

            <img src="assets/img/backgrounds/districtone.png" width="60" style="margin-bottom:15px">

            <h4><?php echo $title; ?></h4>

            <p class="text-muted"><?php echo $message; ?></p>

            <a href="auth-forgot-password-basic.php" class="btn btn-primary w-100 mt-3" style="background:#568df5">

              Request New Reset Link

            </a>

            <br><br>

            <a href="login.php">Back to Login</a>

          </div>

        </div>

      </div>
    </div>

  </body>

  </html>

  <?php
  exit();
}

/* =====================================
   VALIDATE TOKEN
===================================== */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

  if (!isset($_GET['token']) && !isset($_SESSION['alert'])) {
    showResetMessage(
      "Invalid Reset Link",
      "This password reset link is invalid or already used."
    );
  }

  if (isset($_GET['token'])) {

    $token = $_GET['token'];

    $stmt = $conn->prepare("SELECT id,reset_expires FROM users WHERE reset_token=?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
      showResetMessage(
        "Reset Link Already Used",
        "This password reset link has already been used."
      );
    }

    $user = $result->fetch_assoc();

    if (strtotime($user['reset_expires']) < time()) {
      showResetMessage(
        "Reset Link Expired",
        "This password reset link has expired. Please request a new one."
      );
    }

    $_SESSION['reset_user_id'] = $user['id'];

  }
}

/* =====================================
   UPDATE PASSWORD
===================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

  if (!isset($_SESSION['reset_user_id'])) {
    showResetMessage(
      "Session Expired",
      "Please request a new password reset link."
    );
  }

  $user_id = $_SESSION['reset_user_id'];

  $password = $_POST['password'];
  $confirmPassword = $_POST['confirm_password'];

  if ($password !== $confirmPassword) {

    $_SESSION['alert'] = [
      'type' => 'error',
      'message' => 'Passwords do not match!'
    ];

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
  }

  $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

  $updateStmt = $conn->prepare("
UPDATE users
SET password=?,reset_token=NULL,reset_expires=NULL
WHERE id=?
");

  $updateStmt->bind_param("si", $hashedPassword, $user_id);

  if ($updateStmt->execute()) {

    unset($_SESSION['reset_user_id']);

    $_SESSION['alert'] = [
      'type' => 'success',
      'message' => 'Your password has been successfully changed.',
      'redirect' => 'login.php'
    ];

    header("Location: reset_password.php");
    exit();

  } else {

    $_SESSION['alert'] = [
      'type' => 'error',
      'message' => 'Error updating password!'
    ];

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
  }

}
?>

<!DOCTYPE html>
<html lang="en" class="light-style customizer-hide">

<head>

  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>Reset Password</title>

  <link rel="icon" href="assets/img/favicon/districtone.png" />

  <link rel="stylesheet" href="assets/vendor/css/core.css" />
  <link rel="stylesheet" href="assets/vendor/css/theme-default.css" />
  <link rel="stylesheet" href="assets/vendor/css/pages/page-auth.css" />

  <link rel="stylesheet" href="assets/vendor/fonts/boxicons.css" />

  <style>
    body {
      background-image: url('assets/img/backgrounds/jan.jpg');
      background-size: cover;
      background-position: center;
    }

    .card {
      max-width: 450px;
      margin: auto;
    }
  </style>

</head>

<body>

  <div class="container-xxl">
    <div class="authentication-wrapper authentication-basic container-p-y">

      <div class="card">

        <div class="card-body">

          <div class="text-center mb-3">
            <img src="assets/img/backgrounds/districtone.png" width="60">
          </div>

          <h4 class="text-center">Reset Your Password</h4>

          <p class="text-center text-muted">
            Enter your new password below.
          </p>

          <form method="POST">

            <div class="mb-3">

              <label class="form-label">New Password</label>

              <div class="input-group">

                <input type="password" name="password" class="form-control" required minlength="8">

                <span class="input-group-text toggle-password">
                  <i class="bx bx-hide"></i>
                </span>

              </div>

            </div>

            <div class="mb-3">

              <label class="form-label">Confirm Password</label>

              <div class="input-group">

                <input type="password" name="confirm_password" class="form-control" required minlength="8">

                <span class="input-group-text toggle-password">
                  <i class="bx bx-hide"></i>
                </span>

              </div>

            </div>

            <button class="btn btn-primary w-100" style="background:#568df5">

              Update Password

            </button>

          </form>

          <hr>

          <div class="text-center">

            <a href="login.php">
              <i class="bx bx-chevron-left"></i>
              Back to Login
            </a>

          </div>

        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <?php if (isset($_SESSION['alert'])): ?>

    <script>

      Swal.fire({
        icon: '<?= $_SESSION['alert']['type'] ?>',
        title: '<?= $_SESSION['alert']['message'] ?>',
        text: 'Click OK to return to login.',
        confirmButtonColor: '#568df5'
      }).then((result) => {

        <?php if (isset($_SESSION['alert']['redirect'])): ?>

          if (result.isConfirmed) {
            window.location.href = '<?= $_SESSION['alert']['redirect'] ?>';
          }

        <?php endif; ?>

      });

    </script>

    <?php unset($_SESSION['alert']); endif; ?>

  <script>

    document.querySelectorAll(".toggle-password").forEach(btn => {

      btn.addEventListener("click", function () {

        let input = this.parentElement.querySelector("input");

        if (input.type === "password") {
          input.type = "text";
          this.innerHTML = '<i class="bx bx-show"></i>';
        } else {
          input.type = "password";
          this.innerHTML = '<i class="bx bx-hide"></i>';
        }

      });

    });

  </script>

</body>

</html>