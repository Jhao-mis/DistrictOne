<?php
session_start();

require 'vendor/autoload.php';
require 'db.php';

$alertType = $_SESSION['alertType'] ?? '';
$alertText = $_SESSION['alertText'] ?? '';
$redirectFrom = $_SESSION['redirectFrom'] ?? '';

unset($_SESSION['alertType'], $_SESSION['alertText'], $_SESSION['redirectFrom']);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Enable error reporting

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  if (empty($_POST['username']) || empty($_POST['password'])) {
    $_SESSION['error'] = 'Username and password cannot be empty!';
    header("Location: login.php");
    exit();
  }

  $username = htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8'); // Prevent XSS
  $password = $_POST['password'];

  $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $result = $stmt->get_result();
  $user = $result->fetch_assoc();
  $stmt->close();

  if ($user) {
    if ($user['isVerified'] == 0) {
      $_SESSION['error'] = 'Please wait for admin approval to verify your account.';
      header("Location: login.php");
      exit();
    }

    if (password_verify($password, $user['password'])) {
      session_regenerate_id(true); // Secure session handling

      $_SESSION['user_id'] = $user['id'];
      $_SESSION['username'] = $user['username'];
      $_SESSION['role'] = $user['role'];
      $_SESSION['success'] = "Welcome, " . $_SESSION['username'] . "!"; // Set success message

      // Redirect to the same page to display the SweetAlert
      header("Location: login.php?login_success=1&role=" . urlencode($user['role']));
      exit();

      // Redirect based on role
      if ($user['role'] === 'Super Admin') {
        header("Location: modules/profile.php");
      } elseif ($user['role'] === 'Admin') {
        header("Location: modules/profile.php");
      } elseif ($user['role'] === 'mis') {
        header("Location: modules/profile.php");
      } else {
        header("Location: modules/profile.php");
      }
      exit();
    } else {
      $_SESSION['error'] = 'Invalid username or password. Please try again!';
      header("Location: login.php");
      exit();
    }
  } else {
    $_SESSION['error'] = 'Invalid username or password. Please try again!';
    header("Location: login.php");
    exit();
  }
}
?>

<!-- Include SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!DOCTYPE html>

<html lang="en" class="light-style customizer-hide" dir="ltr" data-theme="theme-default" data-assets-path="assets/"
  data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
  <title>Welcome to DistrictOne</title>
  <meta name="description" content="" />

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="assets/img/favicon/districtone.png" />

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
    rel="stylesheet" />

  <!-- Icons -->
  <link rel="stylesheet" href="assets/vendor/fonts/boxicons.css" />

  <!-- Core CSS -->
  <link rel="stylesheet" href="assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="assets/css/demo.css" />
  <link rel="stylesheet" href="css/login.css">

  <!-- Vendors CSS -->
  <link rel="stylesheet" href="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="assets/vendor/css/pages/page-auth.css" />
  <script src="assets/vendor/js/helpers.js"></script>
  <script src="assets/js/config.js"></script>

</head>

<body>

  <?php
  if (isset($_SESSION['error'])) {
    echo "<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            confirmButtonText: 'Try again',
            text: '" . $_SESSION['error'] . "',
            confirmButtonColor: '#d33',
        });
    });
    </script>";
    unset($_SESSION['error']); // Clear message after displaying
  }

  if (isset($_SESSION['success']) && isset($_GET['login_success'])) {
    $redirect_url = 'modules/profile.php'; // Default for users
  
    if (isset($_GET['role'])) {
      $role = $_GET['role'];

      switch ($role) {
        case 'Super Admin':
          $redirect_url = 'modules/profile.php';
          break;
        case 'Admin':
          $redirect_url = 'modules/profile.php';
          break;
        case 'mis':
          $redirect_url = 'modules/profile.php';
          break;
        case 'user':
        default:
          $redirect_url = 'modules/profile.php';
          break;
      }
    }

    echo "<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '" . $_SESSION['success'] . "',
            confirmButtonColor: '#28a745'
        }).then(() => {
            window.location.href = '$redirect_url'; // Redirect based on role
        });
    });
    </script>";
    unset($_SESSION['success']); // Clear message after displaying
  }
  ?>

  <style>
    /* Fade-up animation for the welcome text */
    .fade-up {
      animation: fadeUp 1s ease-in-out;
    }

    @keyframes fadeUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Stylish Welcome Text */
    .welcome-subtitle {
      font-size: 2rem;
      font-weight: 400;
      letter-spacing: 1px;
      opacity: 0.9;
    }

    .welcome-title {
      font-size: 4.5rem;
      font-weight: 800;
      background: linear-gradient(90deg, #ffffffff, #ffffffff);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      letter-spacing: 2px;
      text-shadow: 0 4px 25px rgba(255, 255, 255, 1);
    }

    /* Mobile adjustments */
    @media (max-width: 768px) {
      .welcome-title {
        font-size: 3rem;
      }

      .welcome-subtitle {
        font-size: 1.5rem;
      }

      .col-lg-4 {
        width: 100%;
        background: rgba(255, 255, 255, 1);
      }
    }

    /* Smooth body fade-in */
    body {
      animation: fadeIn 1s ease-in;
      font-family: 'Public Sans', sans-serif;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
      }

      to {
        opacity: 1;
      }
    }
  </style>

  <!-- Dark Overlay -->
  <div style="position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.1); z-index:0;"></div>

  <div class="container-fluid h-100 position-relative p-0 m-0" style="z-index:1;">
    <div class="row h-100 g-0 m-0">

      <!-- LEFT SIDE: SOLID WHITE LOGIN PANEL -->
      <div class="col-lg-4 col-md-5 col-12 d-flex align-items-center justify-content-center"
        style="background: #ffffff; box-shadow: 4px 0 25px rgba(0,0,0,0.25);">

        <div class="w-75 p-3 p-md-4 text-dark">
          <div class="text-center mb-4">
            <img src="assets/img/backgrounds/districtone.png" alt="DistrictOne Logo" width="150" class="mb-3" />
            <h4 class="fw-bold mb-1">Login</h4>
            <p class="mb-0 text-muted">Please sign-in to your account</p>
          </div>

          <form id="formAuthentication" class="mb-3" method="POST">
            <div class="mb-3">
              <label for="username" class="form-label fw-semibold">Username</label>
              <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username"
                autofocus />
            </div>

            <div class="mb-3 form-password-toggle">
              <div class="d-flex justify-content-between">
                <label class="form-label fw-semibold" for="password">Password</label>
                <a href="auth-forgot-password-basic.php">
                  <small style="color:#568df5">Forgot Password?</small>
                </a>
              </div>
              <div class="input-group input-group-merge">
                <input type="password" id="password" class="form-control" name="password" placeholder="••••••••••••"
                  aria-describedby="password" />
                <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
              </div>
            </div>

            <button class="btn d-grid w-100 mb-3 text-white fw-semibold" type="submit"
              style="background-color:#568df5;">Sign In</button>

            <p class="text-center mb-0">
              <span>New on our platform?</span>
              <a href="register.php">
                <span style="color:#568df5; font-weight:600;">Create an account</span>
              </a>
            </p>
          </form>
        </div>
      </div>

      <!-- RIGHT SIDE: WELCOME PANEL -->
      <div
        class="col-lg-8 col-md-7 d-none d-md-flex align-items-center justify-content-center text-center text-white position-relative"
        style="background-image: url('assets/img/backgrounds/login-bg.jpg'); background-size: cover; background-position: center;">

        <style>
          .hero-content {
            position: relative;
            z-index: 1;
            transform: translateY(-40px);
            text-align: center;
          }

          @media (max-width: 768px) {
            .hero-content {
              transform: translateY(-20px);
            }
          }
        </style>

        </style>
        <!-- Semi-dark overlay (KEEP EMPTY) -->
        <div style="position:absolute; inset:0; background:rgba(0,0,0,0.4); z-index:0;"></div>

        <!-- Text content ABOVE overlay -->
        <div class="fade-up hero-content">

          <h2 class="welcome-subtitle mb-2" style="color: white;">
            Welcome to
          </h2>

          <h1 class="welcome-title mb-3">
            DistrictOne
          </h1>

          <p class="fw-light welcome-description mb-4">
            Calamba Water District Management Information System
          </p>

          <!-- <a href="./insight/index.html" class="btn btn-primary btn-lg px-4 py-2 shadow-sm">
            <i class="fa-solid fa-chart-line me-2"></i>
            Calamba Water District Insights
          </a> -->

        </div>


        <!-- Floating About Button -->
        <a href="about_us.php" class="btn btn-light shadow position-fixed"
          style="bottom: 20px; right: 20px; z-index: 9999; padding: 10px 20px; font-weight: bold;"
          title="About DistrictOne">
          About DistrictOne
        </a>

        <!-- Core JS -->
        <script src="assets/vendor/libs/jquery/jquery.js"></script>
        <script src="assets/vendor/libs/popper/popper.js"></script>
        <script src="assets/vendor/js/bootstrap.js"></script>
        <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
        <script src="assets/vendor/js/menu.js"></script>
        <script src="assets/js/main.js"></script>
        <script src="jquery-3.4.1.min.js"></script>
        <script src="sweetalert2.all.min.js"></script>

</body>

<?php if (!empty($alertText) && $redirectFrom === 'verify'): ?>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
    }

    .swal2-popup {
      font-family: 'Poppins', sans-serif !important;
    }
  </style>

  <script>
    Swal.fire({
      title: '<?php echo ucfirst($alertType); ?>',
      text: '<?php echo $alertText; ?>',
      icon: '<?php echo $alertType; ?>',
      confirmButtonText: 'OK',
      confirmButtonColor: '#28a745',
      customClass: {
        popup: 'poppins-font'
      }
    });
  </script>
<?php endif; ?>

</html>