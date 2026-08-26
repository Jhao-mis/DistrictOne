<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
session_start();
require 'vendor/autoload.php';

require 'db.php';

// Check connection
if ($conn->connect_error) {
  die("Database Connection Failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

  $email = trim($_POST['email']);

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['alert'] = ['type' => 'error', 'message' => 'Invalid email format'];
    header("Location: auth-forgot-password-basic.php");
    exit();
  }

  $stmt = $conn->prepare("SELECT id, username FROM users WHERE email=?");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows == 0) {
    $_SESSION['alert'] = ['type' => 'error', 'message' => 'Email not found'];
    header("Location: auth-forgot-password-basic.php");
    exit();
  }

  $user = $result->fetch_assoc();
  $user_id = $user['id'];
  $username = $user['username'];

  // Generate secure token
  $token = bin2hex(random_bytes(32));

  // Expiration (10 minutes)
  $expires = date("Y-m-d H:i:s", strtotime('+10 minutes'));

  // Save token
  $stmt = $conn->prepare("UPDATE users SET reset_token=?, reset_expires=? WHERE id=?");
  $stmt->bind_param("ssi", $token, $expires, $user_id);
  $stmt->execute();

  $reset_link = "http://districtone.com/DistrictOne/reset_password.php?token=" . $token;

  $mail = new PHPMailer(true);

  try {

    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'districtone.cwd@gmail.com';
    $mail->Password = 'qoxz mcnu xibt ywuq';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;

    $mail->setFrom('districtone.cwd@gmail.com', 'District One');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = "DistrictOne Password Reset";

    $mail->Body = '

<div style="background:#f2f4f6;padding:40px 20px;font-family:Arial,sans-serif">

<div style="max-width:520px;margin:auto;background:#ffffff;border-radius:8px;overflow:hidden">

<!-- HEADER -->

<div style="background:#568df5;padding:20px;text-align:center">

<img src="https://i.imgur.com/NV7ibVf.png"
width="60" style="margin-bottom:8px">

<h2 style="color:white;margin:0;font-weight:600">
DistrictOne
</h2>

<p style="color:white;font-size:13px;margin:0">
Calamba Water District Management Information System
</p>

</div>

<!-- BODY -->

<div style="padding:30px;text-align:center">

<h3 style="color:#333;margin-bottom:10px">
Password Reset Request
</h3>

<p style="color:#555;font-size:14px;line-height:1.6">
We received a request to reset the password for your
<b>DistrictOne account</b> (username: <b>' . htmlspecialchars($username) . '</b>).
</p>

<p style="color:#555;font-size:14px">
Click the button below to set a new password.
</p>

<!-- BUTTON -->

<a href="' . $reset_link . '"
style="
display:inline-block;
background:#568df5;
color:#ffffff;
padding:12px 28px;
border-radius:5px;
text-decoration:none;
font-weight:bold;
margin-top:15px;
font-size:14px;
">

Reset Password

</a>

<p style="font-size:13px;color:#777;margin-top:20px">
This reset link will expire in <b>10 minutes</b>.
</p>

<p style="font-size:12px;color:#999;margin-top:15px">
If you did not request a password reset, you can safely ignore this email.
</p>

<hr style="margin:25px 0">

<p style="font-size:12px;color:#888">
Manage Information Services Section
</p>

<div style="background:#f7f7f7;padding:15px;text-align:center">

<p style="font-size:11px;color:#888;margin:0">
© ' . date("Y") . ' DistrictOne — Calamba Water District
</p>
</div>

<!-- FOOTER -->

</div>

</div>

</div>

';

    $mail->send();

    // Store username in session so it can be displayed on the forgot-password page
    $_SESSION['alert'] = [
      'type' => 'success',
      'message' => 'Reset link sent to your email',
      'username' => $username
    ];

  } catch (Exception $e) {
    $_SESSION['alert'] = ['type' => 'error', 'message' => 'Mailer Error'];
  }

  header("Location: auth-forgot-password-basic.php");
  exit();
}
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
  class="light-style customizer-hide"
  dir="ltr"
  data-theme="theme-default"
  data-assets-path="assets/"
  data-template="vertical-menu-template-free"
>
  <head>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"
    />

    <title>Forgot Password</title>

    <meta name="description" content="" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/img/favicon/districtone.png" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
      rel="stylesheet"
    />

    <!-- Icons. Uncomment required icon fonts -->
    <link rel="stylesheet" href="assets/vendor/fonts/boxicons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <!-- Page CSS -->
    <!-- Page -->
    <link rel="stylesheet" href="assets/vendor/css/pages/page-auth.css" />
    <!-- Helpers -->
    <script src="assets/vendor/js/helpers.js"></script>

    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="assets/js/config.js"></script>
  </head>
  <style>
  body {
        background-image: url('assets/img/backgrounds/bg69.jpg');
        background-attachment: fixed; /* Makes background image fixed */
        background-size: cover; /* Ensures the background image covers the entire screen */
        background-position: center center; /* Center the background image */
        background-repeat: no-repeat; /* Prevents the image from repeating */
      }
      .app-brand-text {
        font-size: 20px !important;
        font-weight: bold;
      }

      .logo {
        margin-left: -30px;
        margin-top: 5px;
      }

      .small-text {
        font-size: 10px !important;
        display: block;
        margin-top: 35px;
        margin-left: -128px;
      }

      .btn .btn-primary .d-grid .w-100P {
        background-color: blue;
      }

      /* For all input fields (text, password, and dropdowns) */
      input:focus,
      select:focus,
      .form-control:focus {
        border-color: #568df5 !important; /* Blue border */
      }

</style>
  <body>
     <!-- Content -->
     <div class="container-xxl">
      <div class="authentication-wrapper authentication-basic container-p-y">
        <!-- Register -->
        <div class="card">
          <div class="card-body">
            <!-- Logo -->
            <div class="app-brand justify-content-center">
              <a href="javascript:void(0);" class="app-brand-link gap-2">
                <span class="app-brand-logo demo">
                  <svg
                    width="25"
                    viewBox="0 0 25 42"
                    version="1.1"
                    xmlns="http://www.w3.org/2000/svg"
                    xmlns:xlink="http://www.w3.org/1999/xlink"
                  >
                    <!-- SVG content here -->
                  </svg>
                </span>
                <img src="assets/img/backgrounds/districtone.png" alt="CWD Logo" width="60" class="logo" />
                <span class="app-brand-text text-body fw-bolder">District One</span>
                <br />
                <span class="app-brand-text text-body fw-bolder small-text">Calamba Water District Management Information System</span>
              </a>
            </div>
            <!-- /Logo -->
              <h4 class="mb-2">Forgot Password?</h4>
              <p class="mb-4">Enter your email and we'll send you a link to reset your password</p>
              <form id="formAuthentication" class="mb-3" method="POST">

                <div class="mb-3">
                  <label for="email" class="form-label">Email</label>
                  <input
                    type="text"
                    class="form-control"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
                    autofocus
                    required
                  />
                </div>
<!-- Button -->
<button id="resetLinkBtn" class="btn btn-primary d-grid w-100" style="background-color:#568df5">
  Send Reset Link
</button>


              </form>
              <div class="text-center">
                <a href="login.php" class="d-flex align-items-center justify-content-center">
                  <i class="bx bx-chevron-left scaleX-n1-rtl bx-sm" style="color:#568df5"></i>
                  <span style="color:#568df5">Back to login</span>

                </a>
              </div>
            </div>
          </div>
          <!-- /Forgot Password -->
               <!-- Floating About Us Button -->
    <a href="about_us.php" class="btn btn-light shadow position-fixed"
      style="bottom: 20px; right: 20px; z-index: 9999; padding: 10px 20px; font-weight: bold;"
      title="About DistrictOne">
      About DistrictOne
    </a>
        </div>
      </div>
    </div>

    <!-- SweetAlert2 Script -->
    <?php if (isset($_SESSION['alert'])): ?>
                      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                      <script>
                        Swal.fire({
                          icon: '<?= $_SESSION['alert']['type'] ?>',
                          title: '<?= $_SESSION['alert']['message'] ?>',
                          confirmButtonText: 'OK'
                        });
                      </script>
                      <?php unset($_SESSION['alert']); ?>
<?php endif; ?>



    <!-- Core JS -->
    <!-- build:js assets/vendor/js/core.js -->
    <script src="assets/vendor/libs/jquery/jquery.js"></script>
    <script src="assets/vendor/libs/popper/popper.js"></script>
    <script src="assets/vendor/js/bootstrap.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>

    <script src="assets/vendor/js/menu.js"></script>
    <!-- endbuild -->

    <!-- Vendors JS -->

    <!-- Main JS -->
    <script src="assets/js/main.js"></script>

    <!-- Page JS -->

    <!-- Place this tag in your head or just before your close body tag. -->
    <script async defer src="https://buttons.github.io/buttons.js"></script>
    
  </body>
</html>