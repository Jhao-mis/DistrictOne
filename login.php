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
    $_SESSION['errorIcon'] = 'error';
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
      $_SESSION['errorIcon'] = 'warning';
      header("Location: login.php");
      exit();
    }

    if (password_verify($password, $user['password'])) {
      session_regenerate_id(true); // Secure session handling

      $_SESSION['user_id'] = $user['id'];
      $_SESSION['username'] = $user['username'];
      $_SESSION['role'] = $user['role'];
      $_SESSION['success'] = "Welcome, " . $_SESSION['username'] . "!"; // Set success message

      // Redirect to the same page to display the SweetAlert, then the
      // page itself takes care of moving on to the dashboard.
      header("Location: login.php?login_success=1&role=" . urlencode($user['role']));
      exit();
    } else {
      $_SESSION['error'] = 'Invalid username or password. Please try again!';
      $_SESSION['errorIcon'] = 'error';
      header("Location: login.php");
      exit();
    }
  } else {
    $_SESSION['error'] = 'Invalid username or password. Please try again!';
    $_SESSION['errorIcon'] = 'error';
    header("Location: login.php");
    exit();
  }
}

// Pull the one-time flash messages now, before they get echoed into JS below.
$loginError = $_SESSION['error'] ?? null;
$loginErrorIcon = $_SESSION['errorIcon'] ?? 'error';
unset($_SESSION['error'], $_SESSION['errorIcon']);

$showLoginSuccess = isset($_SESSION['success']) && isset($_GET['login_success']);
$loginSuccessMsg = $_SESSION['success'] ?? '';
$dashboardRedirectUrl = 'modules/profile.php';
unset($_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Welcome to DistrictOne</title>

  <link rel="icon" type="image/x-icon" href="assets/img/favicon/districtone.png" />

  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap"
    rel="stylesheet">

  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {
              DEFAULT: '#568df5',
              dark: '#3d6fd4',
            },
          },
          fontFamily: {
            sans: ['Public Sans', 'sans-serif'],
          },
          keyframes: {
            fadeUp: {
              '0%': { opacity: 0, transform: 'translateY(40px)' },
              '100%': { opacity: 1, transform: 'translateY(0)' },
            },
          },
          animation: {
            fadeUp: 'fadeUp 1s ease',
          },
        },
      },
    };
  </script>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="h-screen m-0 p-0 font-sans overflow-hidden">

  <div class="flex w-full h-screen flex-col lg:flex-row">

    <!-- LEFT LOGIN PANEL -->
    <div class="w-full lg:w-[450px] lg:min-w-[400px] bg-white flex items-center justify-center px-8 py-10">
      <div class="w-full max-w-[320px]">

        <div class="text-center mb-6">
          <img src="assets/img/backgrounds/districtone.png" width="140" class="mx-auto mb-3">
          <h4 class="text-2xl font-bold text-gray-800">Login</h4>
          <p class="text-gray-500 text-sm mt-1">Please sign-in to your account</p>
        </div>

        <form method="POST" id="loginForm">

          <div class="mb-4">
            <label class="block mb-1.5 text-sm font-semibold text-gray-700">Username</label>
            <input
              type="text"
              name="username"
              required
              class="w-full h-11 rounded-md border border-gray-300 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
          </div>

          <div class="mb-5">
            <div class="flex items-center justify-between mb-1.5">
              <label class="text-sm font-semibold text-gray-700">Password</label>
              <a href="auth-forgot-password-basic.php" class="text-xs font-medium text-brand hover:underline">
                Forgot Password?
              </a>
            </div>

            <div class="relative">
              <input
                type="password"
                name="password"
                id="passwordField"
                required
                class="w-full h-11 rounded-md border border-gray-300 px-3 pr-11 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">

              <button
                type="button"
                onclick="togglePassword()"
                class="absolute right-0 top-0 h-11 w-11 flex items-center justify-center text-gray-400 hover:text-brand">
                <i class="bx bx-hide text-lg" id="toggleIcon"></i>
              </button>
            </div>
          </div>

          <button
            type="submit"
            id="loginSubmitBtn"
            class="w-full h-11 rounded-md bg-brand hover:bg-brand-dark text-white font-semibold text-sm transition flex items-center justify-center gap-2 mb-4">
            <span class="hidden w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin" id="loginSpinner"></span>
            <span id="loginSubmitLabel">Sign In</span>
          </button>

          <p class="text-center text-sm text-gray-600 mb-0">
            New on our platform?
            <a href="register.php" class="font-semibold text-brand hover:underline">
              Create an account
            </a>
          </p>

        </form>
      </div>
    </div>

    <!-- RIGHT IMAGE PANEL -->
    <div class="hidden lg:flex flex-1 relative flex-col items-center justify-center bg-[url('assets/img/backgrounds/bg69.jpg')] bg-cover bg-center">

      <div class="absolute inset-0 bg-black/45"></div>

      <div class="relative z-10 text-center text-white px-10 pb-40 animate-fadeUp">
        <h2 class="text-xl font-light">Welcome to</h2>
        <h1 class="text-5xl md:text-6xl font-extrabold tracking-wide">DistrictOne</h1>
        <p class="mt-4 text-white/80 max-w-md mx-auto">Calamba Water District Management Information System</p>
      </div>

      <!-- BOTTOM LEFT: DAILY BIBLE VERSE -->
      <div class="absolute bottom-10 left-10 z-10 max-w-sm w-[calc(100%-5rem)] text-white">
        <div class="bg-white/10 border border-white/15 backdrop-blur-md rounded-2xl p-5 shadow-lg">
          <small class="block mb-2 text-[11px] font-bold uppercase tracking-wide opacity-70">
            Daily Bible Verse
          </small>
          <p id="verseText" class="mb-2 text-[15px] italic leading-relaxed">
            Loading verse...
          </p>
          <small id="verseReference" class="block text-right text-[13px] font-semibold opacity-80"></small>
        </div>
      </div>

      <!-- BOTTOM RIGHT: ABOUT LINK -->
      <a href="about_us.php"
        class="absolute bottom-8 right-8 z-10 bg-white text-gray-800 font-semibold text-sm px-4 py-2 rounded-md shadow hover:bg-gray-100 transition">
        About DistrictOne
      </a>

    </div>

  </div>

  <!-- FULL-SCREEN LOADING OVERLAY (shown only after a successful login) -->
  <div id="dashboardLoadingOverlay" class="hidden fixed inset-0 z-50 flex-col items-center justify-center gap-4 bg-[#3d6fd4]">
    <div class="w-11 h-11 border-4 border-white/25 border-t-white rounded-full animate-spin"></div>
    <p class="text-white text-sm font-medium">Loading your dashboard…</p>
  </div>

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

    document.getElementById('loginForm').addEventListener('submit', function () {
      document.getElementById('loginSubmitBtn').disabled = true;
      document.getElementById('loginSpinner').classList.remove('hidden');
      document.getElementById('loginSubmitLabel').textContent = 'Signing in…';
    });

    async function fetchBibleVerse() {
      try {
        const response = await fetch('https://labs.bible.org/api/?passage=random&type=json');
        const data = await response.json();

        if (data && data.length > 0) {
          document.getElementById('verseText').innerText = `"${data[0].text}"`;
          document.getElementById('verseReference').innerText =
            `${data[0].bookname} ${data[0].chapter}:${data[0].verse}`;
        }
      } catch (error) {
        document.getElementById('verseText').innerText = 'Unable to load verse today.';
      }
    }

    fetchBibleVerse();

    document.addEventListener('DOMContentLoaded', function () {

      <?php if ($loginError): ?>
      Swal.fire({
        icon: <?= json_encode($loginErrorIcon) ?>,
        title: 'Oops...',
        text: <?= json_encode($loginError) ?>,
        confirmButtonColor: '#568df5'
      });
      <?php endif; ?>

      <?php if ($showLoginSuccess): ?>
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: <?= json_encode($loginSuccessMsg) ?>,
        confirmButtonColor: '#568df5',
        timer: 1600,
        timerProgressBar: true,
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false
      }).then(function () {
        var overlay = document.getElementById('dashboardLoadingOverlay');
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
        setTimeout(function () {
          window.location.href = <?= json_encode($dashboardRedirectUrl) ?>;
        }, 900);
      });
      <?php endif; ?>

      var params = new URLSearchParams(window.location.search);
      if (params.get('loggedout') === '1') {
        Swal.fire({
          icon: 'success',
          title: 'You have been signed out',
          toast: true,
          position: 'top-end',
          timer: 2500,
          timerProgressBar: true,
          showConfirmButton: false
        });
        window.history.replaceState({}, document.title, window.location.pathname);
      }
    });
  </script>

  <?php if (!empty($alertText) && $redirectFrom === 'verify'): ?>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
    .swal2-popup.poppins-font { font-family: 'Poppins', sans-serif !important; }
  </style>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      Swal.fire({
        title: <?= json_encode(ucfirst($alertType)) ?>,
        text: <?= json_encode($alertText) ?>,
        icon: <?= json_encode($alertType) ?>,
        confirmButtonText: 'OK',
        confirmButtonColor: '#568df5',
        customClass: {
          popup: 'poppins-font'
        }
      });
    });
  </script>
  <?php endif; ?>

</body>

</html>