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

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Welcome to DistrictOne</title>

  <link rel="icon" type="image/x-icon" href="assets/img/favicon/districtone.png" />

  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap"
    rel="stylesheet">

  <link rel="stylesheet" href="assets/vendor/css/core.css" />
  <link rel="stylesheet" href="assets/vendor/css/theme-default.css" />
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

  <style>
    /* ===== RESET ===== */
    * {
      box-sizing: border-box;
    }

    html,
    body {
      height: 100%;
      margin: 0;
      padding: 0;
      font-family: 'Public Sans', sans-serif;
      overflow: hidden;
    }

    /* ===== MAIN WRAPPER ===== */
    .login-wrapper {
      display: flex;
      width: 100%;
      height: 100vh;
    }

    /* ===== LEFT LOGIN PANEL ===== */
    .login-panel {
      width: 450px;
      /* fixed width */
      min-width: 400px;
      background-color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 30px;
    }

    .login-box {
      width: 100%;
      max-width: 320px;
    }

    /* ===== FORM STYLING ===== */
    .login-box h4 {
      font-weight: 700;
    }

    .form-control {
      height: 45px;
      border-radius: 6px;
    }

    .btn-login {
      height: 45px;
      background-color: #568df5;
      border: none;
      border-radius: 6px;
      transition: 0.3s ease;
    }

    .btn-login:hover {
      background-color: #3d6fd4;
    }

    /* ===== RIGHT IMAGE PANEL ===== */
    .image-panel {
      flex: 1;
      /* fill remaining space */
      min-height: 100vh;

      /* 🔥 YOUR BACKGROUND IMAGE HERE */
      background-image: url("assets/img/backgrounds/May.jpg");


      background-position: center center;
      background-size: cover;
      background-repeat: no-repeat;

      display: flex;
      align-items: center;
      justify-content: center;

      position: relative;
    }

    /* Dark overlay */
    .image-panel::after {
      content: "";
      position: absolute;
      inset: 0;
      background: rgba(0, 0, 0, 0.45);
    }

    /* ===== HERO TEXT ===== */
    .hero-content {
      position: relative;
      z-index: 2;
      text-align: center;
      color: #ffffff;
      padding: 40px;
      margin-bottom: 470px;
      /*CHANGE THE HEIGHT IF NEEDED MOSSING */
      animation: fadeUp 1s ease;
    }

    .hero-content h2 {
      font-weight: 400;
      font-size: 1.8rem;
    }

    .hero-content h1 {
      font-size: 3.8rem;
      font-weight: 800;
      letter-spacing: 2px;
    }

    .hero-content p {
      font-weight: 300;
      margin-top: 15px;
    }

    /* ===== ANIMATION ===== */
    @keyframes fadeUp {
      from {
        opacity: 0;
        transform: translateY(40px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* ===== MOBILE RESPONSIVE ===== */
    @media (max-width: 992px) {

      .login-wrapper {
        flex-direction: column;
      }

      .login-panel {
        width: 100%;
        min-width: unset;
        height: auto;
      }

      .image-panel {
        display: none;
        /* hide image on mobile */
      }
    }
  </style>

</head>

<body>

  <?php
  if (isset($_SESSION['error'])) {
    echo "<script>
document.addEventListener('DOMContentLoaded', function() {
Swal.fire({
icon: 'error',
title: 'Oops...',
text: '" . $_SESSION['error'] . "',
confirmButtonColor: '#d33'
});
});
</script>";
    unset($_SESSION['error']);
  }

  if (isset($_SESSION['success']) && isset($_GET['login_success'])) {
    $redirect_url = 'modules/profile.php';
    echo "<script>
document.addEventListener('DOMContentLoaded', function() {
Swal.fire({
icon: 'success',
title: 'Success!',
text: '" . $_SESSION['success'] . "',
confirmButtonColor: '#28a745'
}).then(() => {
window.location.href = '$redirect_url';
});
});
</script>";
    unset($_SESSION['success']);
  }
  ?>

  <div class="login-wrapper">

    <!-- LEFT LOGIN -->
    <div class="login-panel">
      <div class="login-box">

        <div class="text-center mb-4">
          <img src="assets/img/backgrounds/districtone.png" width="140" class="mb-3">
          <h4 class="fw-bold">Login</h4>
          <p class="text-muted">Please sign-in to your account</p>
        </div>

        <form method="POST">

          <div class="mb-3">
            <label class="form-label fw-semibold">Username</label>
            <input type="text" class="form-control" name="username" required>
          </div>

          <!-- <div class="mb-3">
            <div class="d-flex justify-content-between">
              <label class="form-label fw-semibold">Password</label>
              <a href="auth-forgot-password-basic.php" class="small text-primary">
                Forgot Password?
              </a>
            </div>
            <input type="password" class="form-control" name="password" required>
          </div> -->

          <div class="mb-3">
            <div class="d-flex justify-content-between">
              <label class="form-label fw-semibold">Password</label>
              <a href="auth-forgot-password-basic.php" class="small text-primary">
                Forgot Password?
              </a>
            </div>

            <div class="input-group">
              <input type="password" class="form-control" name="password" id="passwordField" required>

              <span class="input-group-text" style="cursor: pointer;" onclick="togglePassword()">
                <i class="bx bx-hide" id="toggleIcon"></i>
              </span>
            </div>
          </div>

          <button type="submit" class="btn btn-login text-white w-100 mb-3">
            Sign In
          </button>

          <p class="text-center mb-0">
            New on our platform?
            <a href="register.php" class="fw-semibold text-primary">
              Create an account
            </a>
          </p>

        </form>
      </div>
    </div>

    <!-- RIGHT IMAGE -->
    <!-- RIGHT IMAGE -->
    <div class="image-panel">
      <div class="hero-content">
        <h2>
          <font color="white">Welcome to</font>
        </h2>
        <h1>
          <font color="white">DistrictOne</font>
        </h1>
        <p>Calamba Water District Management Information System</p>
      </div>

      <!-- BOTTOM LEFT CONTENT CONTAINER -->
      <div
        style="position: absolute; bottom: 40px; left: 40px; z-index: 3; max-width: 400px; width: calc(100% - 80px); color: #ffffff;">

        <!-- BIBLE VERSE CARD -->
        <div id="bibleVerseBox" style="margin-bottom: 25px;
              background: rgba(255, 255, 255, 0.08);
              border: 1px solid rgba(255, 255, 255, 0.15);
              padding: 20px;
              border-radius: 16px;
              backdrop-filter: blur(12px);
              -webkit-backdrop-filter: blur(12px);
              box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.25);
              color: #ffffff;">

          <small
            style="font-weight: 700; text-transform: uppercase; letter-spacing: 1px; font-size: 11px; opacity: 0.7; display: block; margin-bottom: 8px; color: #ffffff;">Daily
            Bible Verse</small>

          <p id="verseText"
            style="margin: 0 0 8px 0; font-size: 15px; line-height: 1.5; font-style: italic; font-weight: 400; color: #ffffff;">
            Loading verse...
          </p>

          <small id="verseReference"
            style="display: block; text-align: right; font-weight: 600; opacity: 0.8; font-size: 13px; color: #ffffff;"></small>
        </div>

        <!-- SECTION SUBTEXT -->
        <div style="padding-left: 4px; color: #ffffff;">
          <p style="margin: 0 0 4px 0; font-size: 18px; font-weight: 600; letter-spacing: 0.5px; color: #ffffff;">
            Greater things are coming.
          </p>
          <small
            style="display: block; opacity: 0.65; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #ffffff;">
            Management Information Services Section
          </small>
        </div>

      </div>

      <a href="about_us.php" class="btn btn-light position-absolute"
        style="bottom: 30px; right: 30px; font-weight: 600; z-index: 3;">
        About DistrictOne
      </a>

    </div>

    <script src="assets/vendor/libs/jquery/jquery.js"></script>
    <script src="assets/vendor/js/bootstrap.js"></script>

</body>

</html>

<!-- SCRIPT FOR BIBLE VERSE AND API -->
<script>
  async function fetchBibleVerse() {
    try {
      // Random verse API
      const response = await fetch('https://labs.bible.org/api/?passage=random&type=json');

      const data = await response.json();

      if (data && data.length > 0) {
        document.getElementById('verseText').innerText =
          `"${data[0].text}"`;

        document.getElementById('verseReference').innerText =
          `${data[0].bookname} ${data[0].chapter}:${data[0].verse}`;
      }
    } catch (error) {
      document.getElementById('verseText').innerText =
        'Unable to load verse today.';
    }
  }

  fetchBibleVerse();
</script>
<script src="assets/vendor/libs/jquery/jquery.js"></script>
<script src="assets/vendor/js/bootstrap.js"></script>


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

<script>
  function togglePassword() {
    const passwordField = document.getElementById("passwordField");
    const toggleIcon = document.getElementById("toggleIcon");

    if (passwordField.type === "password") {
      passwordField.type = "text";
      toggleIcon.classList.remove("bx-hide");
      toggleIcon.classList.add("bx-show");
    } else {
      passwordField.type = "password";
      toggleIcon.classList.remove("bx-show");
      toggleIcon.classList.add("bx-hide");
    }
  }
</script>

</html>