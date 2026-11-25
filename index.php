<?php
session_start();

require 'vendor/autoload.php';
require 'db.php';
require 'lib/access_control.php';
require_once 'lib/site_media.php';
require_once 'lib/seasonal_theme.php';

$loginBackground = site_media_login_background($conn);

$alertType = $_SESSION['alertType'] ?? '';
$alertText = $_SESSION['alertText'] ?? '';
$redirectFrom = $_SESSION['redirectFrom'] ?? '';
$activePanel = $_GET['panel'] ?? 'login';
if (!in_array($activePanel, ['login', 'register', 'forgot', 'reset'], true)) {
  $activePanel = 'login';
}
$resetToken = isset($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : '';
$resetTokenValid = false;
if ($activePanel === 'reset' && preg_match('/\A[a-f0-9]{64}\z/i', $resetToken)) {
  $tokenStmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
  $tokenStmt->bind_param('s', $resetToken);
  $tokenStmt->execute();
  $resetTokenValid = $tokenStmt->get_result()->num_rows === 1;
  $tokenStmt->close();
}
$resetError = $_SESSION['resetError'] ?? null;
$resetSuccess = $_SESSION['resetSuccess'] ?? null;
unset($_SESSION['resetError'], $_SESSION['resetSuccess']);
$registrationError = $_SESSION['registrationError'] ?? null;
$registrationSuccess = $_SESSION['registrationSuccess'] ?? null;
unset($_SESSION['registrationError'], $_SESSION['registrationSuccess']);
$forgotAlert = $activePanel === 'forgot' ? ($_SESSION['alert'] ?? null) : null;
if ($activePanel === 'forgot') {
  unset($_SESSION['alert']);
}

unset($_SESSION['alertType'], $_SESSION['alertText'], $_SESSION['redirectFrom']);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Enable error reporting

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_password') {
  $submittedToken = $_POST['reset_token'] ?? '';
  $password = $_POST['password'] ?? '';
  $confirmPassword = $_POST['confirm_password'] ?? '';

  if (!is_string($submittedToken) || !preg_match('/\A[a-f0-9]{64}\z/i', $submittedToken)) {
    $_SESSION['resetError'] = 'This reset link is invalid, expired, or no longer active. Please request a new link.';
    header('Location: index?panel=forgot');
    exit();
  }

  $resetUrl = 'index?panel=reset&token=' . rawurlencode($submittedToken);

  if (!is_string($password) || strlen($password) < 8) {
    $_SESSION['resetError'] = 'Your password must be at least 8 characters long.';
    header('Location: ' . $resetUrl);
    exit();
  }

  if ($password !== $confirmPassword) {
    $_SESSION['resetError'] = 'The passwords do not match.';
    header('Location: ' . $resetUrl);
    exit();
  }

  $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
  $updateStmt = $conn->prepare(
    "UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL
     WHERE reset_token = ? AND reset_expires > NOW()"
  );
  $updateStmt->bind_param('ss', $hashedPassword, $submittedToken);
  $updateStmt->execute();
  $tokenConsumed = $updateStmt->affected_rows === 1;
  $updateStmt->close();

  if (!$tokenConsumed) {
    $_SESSION['resetError'] = 'This reset link is invalid, expired, or no longer active. Please request a new link.';
    header('Location: ' . $resetUrl);
    exit();
  }

  $_SESSION['resetSuccess'] = 'Your password has been successfully changed. You can now sign in.';
  header('Location: index');
  exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  if (empty($_POST['username']) || empty($_POST['password'])) {
    $_SESSION['error'] = 'Username and password cannot be empty!';
    $_SESSION['errorIcon'] = 'error';
    header("Location: index.php");
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
      header("Location: index.php");
      exit();
    }

    if (password_verify($password, $user['password'])) {
      session_regenerate_id(true); // Secure session handling

      $_SESSION['user_id'] = $user['id'];
      $_SESSION['username'] = $user['username'];
      $_SESSION['role'] = access_normalize_role((string) $user['role']);
      access_refresh_session($conn, (int) $user['id']);
      $_SESSION['success'] = "Welcome, " . $_SESSION['username'] . "!"; // Set success message

      // Redirect to the same page to display the SweetAlert, then the
      // page itself takes care of moving on to the dashboard.
      header("Location: index.php?login_success=1&role=" . urlencode($user['role']));
      exit();
    } else {
      $_SESSION['error'] = 'Invalid username or password. Please try again!';
      $_SESSION['errorIcon'] = 'error';
      header("Location: index.php");
      exit();
    }
  } else {
    $_SESSION['error'] = 'Invalid username or password. Please try again!';
    $_SESSION['errorIcon'] = 'error';
    header("Location: index.php");
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
  <link rel="stylesheet" href="assets/css/global-font.css">

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
  <?php seasonal_theme_render($conn); ?>
  <style>
    .auth-view {
      display: none;
    }

    .auth-view.is-active {
      display: block;
      animation: authPanelIn 280ms ease both;
    }

    @keyframes authPanelIn {
      from {
        opacity: 0;
        transform: translateX(18px);
      }

      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    #termsModal {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 10000;
      overflow-y: auto;
      align-items: center;
      justify-content: center;
      padding: 1.25rem;
      background: rgb(15 23 42 / 72%);
      backdrop-filter: blur(6px);
    }

    #termsModal.is-open {
      display: flex;
    }

    #termsModal .modal-dialog {
      margin: auto;
      max-height: min(88vh, 900px);
      max-width: 50rem;
      width: 100%;
    }

    #termsModal .terms-modal-content {
      display: flex;
      max-height: min(88vh, 900px);
      flex-direction: column;
      overflow: hidden;
      border: 0;
      border-radius: 1.25rem;
      background: white;
      padding: 0;
      box-shadow: 0 25px 70px rgb(15 23 42 / 35%);
    }

    #termsModal .terms-modal-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
      padding: 1.75rem 2rem;
      border: 0;
      background: linear-gradient(120deg, #315db7, #568df5);
      color: #fff;
    }

    #termsModal .terms-modal-eyebrow {
      display: block;
      margin-bottom: 0.45rem;
      color: rgb(255 255 255 / 75%);
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.12em;
      text-transform: uppercase;
    }

    #termsModal .terms-modal-header h2 {
      margin: 0;
      color: #fff;
      font-size: 1.5rem;
      font-weight: 700;
    }

    #termsModal .terms-modal-header p {
      margin: 0.35rem 0 0;
      color: rgb(255 255 255 / 82%);
      font-size: 0.875rem;
    }

    #termsModal .btn-close {
      display: grid;
      width: 2.25rem;
      height: 2.25rem;
      flex: 0 0 auto;
      place-items: center;
      border: 0;
      border-radius: 9999px;
      background: rgb(255 255 255 / 16%);
      color: #fff;
      cursor: pointer;
      font-size: 1.75rem;
      line-height: 1;
      opacity: 1;
      padding: 0;
    }

    #termsModal .btn-close::before {
      content: '×';
    }

    #termsModal .btn-close:hover {
      background: rgb(255 255 255 / 28%);
    }

    #termsModal .terms-modal-body {
      overflow-y: auto;
      padding: 1.5rem 2rem;
      color: #4b5563;
      font-size: 0.9rem;
      line-height: 1.75;
    }

    #termsModal .terms-modal-body p {
      margin: 0 0 1rem;
    }

    #termsModal .terms-effective-date {
      display: flex;
      width: fit-content;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 1.25rem;
      border-radius: 9999px;
      background: #eff6ff;
      padding: 0.35rem 0.8rem;
      color: #315db7;
      font-size: 0.78rem;
    }

    #termsModal .terms-effective-date strong {
      font-weight: 700;
    }

    #termsModal .terms-modal-body section {
      border-top: 1px solid #e5e7eb;
      padding: 1.15rem 0 0.25rem;
    }

    #termsModal .terms-modal-body h3 {
      display: flex;
      align-items: center;
      gap: 0.7rem;
      margin: 0 0 0.65rem;
      color: #1f2937;
      font-size: 1rem;
      font-weight: 700;
    }

    #termsModal .terms-modal-body h3 span {
      color: #568df5;
      font-size: 0.75rem;
      letter-spacing: 0.08em;
    }

    #termsModal .terms-modal-body ul {
      margin: 0 0 1rem;
      padding-left: 1.35rem;
    }

    #termsModal .terms-modal-body li {
      margin-bottom: 0.3rem;
      padding-left: 0.2rem;
    }

    #termsModal .terms-modal-body a {
      color: #315db7;
      text-decoration: underline;
      text-underline-offset: 2px;
    }

    #termsModal .terms-modal-body .terms-modal-closing {
      margin: 1rem 0 0;
      border-radius: 0.75rem;
      background: #f8fafc;
      padding: 1rem;
      color: #374151;
      font-weight: 600;
    }

    #termsModal .terms-modal-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      border-top: 1px solid #e5e7eb;
      padding: 1rem 2rem;
      color: #6b7280;
      font-size: 0.75rem;
    }

    #termsModal .terms-modal-footer > div {
      display: flex;
      gap: 0.65rem;
    }

    #termsModal .terms-modal-footer button {
      min-height: 2.5rem;
      border-radius: 0.5rem;
      cursor: pointer;
      font: inherit;
      font-weight: 600;
      padding: 0.5rem 1rem;
      transition: background-color 150ms ease, transform 150ms ease;
    }

    #termsModal .terms-modal-footer button:hover {
      transform: translateY(-1px);
    }

    #termsModal .terms-modal-secondary {
      border: 1px solid #d1d5db;
      background: #fff;
      color: #374151;
    }

    #termsModal .terms-modal-secondary:hover {
      background: #f9fafb;
    }

    #termsModal .terms-modal-primary {
      border: 1px solid #568df5;
      background: #568df5;
      color: #fff;
    }

    #termsModal .terms-modal-primary:hover {
      background: #3d6fd4;
    }

    #registerSubmitBtn:disabled {
      cursor: not-allowed;
      opacity: 0.5;
    }

    @media (max-width: 640px) {
      #termsModal {
        padding: 0.65rem;
      }

      #termsModal .modal-dialog,
      #termsModal .terms-modal-content {
        max-height: 92vh;
      }

      #termsModal .terms-modal-header,
      #termsModal .terms-modal-body {
        padding-right: 1.25rem;
        padding-left: 1.25rem;
      }

      #termsModal .terms-modal-footer {
        align-items: flex-start;
        flex-direction: column;
        padding: 1rem 1.25rem;
      }

      #termsModal .terms-modal-footer > div {
        width: 100%;
      }

      #termsModal .terms-modal-footer button {
        flex: 1;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      .auth-view.is-active {
        animation: none;
      }

      #termsModal .terms-modal-footer button {
        transition: none;
      }
    }
  </style>
</head>

<body class="min-h-screen m-0 p-0 font-sans overflow-x-hidden">

  <div class="flex w-full min-h-screen flex-col lg:flex-row">

    <!-- LEFT LOGIN PANEL -->
    <div class="login-left-panel w-full min-h-screen lg:max-h-screen lg:overflow-y-auto lg:w-[450px] lg:min-w-[400px] bg-white flex items-center justify-center px-8 py-8">
      <div class="login-left-seasonal-overlay" aria-hidden="true">
        <span></span><span></span><span></span><span></span><span></span><span></span>
      </div>
      <div class="relative z-10 w-full max-w-[360px]">

        <section class="auth-view <?= $activePanel === 'login' ? 'is-active' : '' ?>" data-auth-view="login" aria-hidden="<?= $activePanel === 'login' ? 'false' : 'true' ?>">
          <div class="text-center mb-6">
            <img src="assets/img/backgrounds/districtone.png" width="140" class="mx-auto mb-3" alt="DistrictOne">
            <h4 class="text-2xl font-bold text-gray-800">Login</h4>
            <p class="text-gray-500 text-sm mt-1">Please sign-in to your account</p>
          </div>

          <form method="POST" id="loginForm">
            <div class="mb-4">
              <label for="loginUsername" class="block mb-1.5 text-sm font-semibold text-gray-700">Username</label>
              <input type="text" name="username" id="loginUsername" required
                class="w-full h-11 rounded-md border border-gray-300 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
            </div>

            <div class="mb-5">
              <div class="flex items-center justify-between mb-1.5">
                <label for="passwordField" class="text-sm font-semibold text-gray-700">Password</label>
                <a href="?panel=forgot" data-panel-link="forgot" class="text-xs font-medium text-brand hover:underline">
                  Forgot Password?
                </a>
              </div>

              <div class="relative">
                <input type="password" name="password" id="passwordField" required
                  class="w-full h-11 rounded-md border border-gray-300 px-3 pr-11 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
                <button type="button" onclick="togglePassword()" aria-label="Show password"
                  class="absolute right-0 top-0 h-11 w-11 flex items-center justify-center text-gray-400 hover:text-brand">
                  <i class="bx bx-hide text-lg" id="toggleIcon" aria-hidden="true"></i>
                </button>
              </div>
            </div>

            <button type="submit" id="loginSubmitBtn"
              class="w-full h-11 rounded-md bg-brand hover:bg-brand-dark text-white font-semibold text-sm transition flex items-center justify-center gap-2 mb-4">
              <span class="hidden w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin" id="loginSpinner"></span>
              <span id="loginSubmitLabel">Sign In</span>
            </button>

            <p class="text-center text-sm text-gray-600 mb-0">
              New on our platform?
              <a href="?panel=register" data-panel-link="register" class="font-semibold text-brand hover:underline">
                Create an account
              </a>
            </p>
          </form>
        </section>

        <section class="auth-view <?= $activePanel === 'register' ? 'is-active' : '' ?>" data-auth-view="register" aria-hidden="<?= $activePanel === 'register' ? 'false' : 'true' ?>">
          <div class="text-center mb-5">
            <img src="assets/img/backgrounds/districtone.png" width="110" class="mx-auto mb-2" alt="DistrictOne">
            <h4 class="text-2xl font-bold text-gray-800">Create an account</h4>
            <p class="text-gray-500 text-sm mt-1">Register for a DistrictOne account</p>
          </div>

          <form method="POST" action="register.php" id="registrationForm">
            <div class="mb-3">
              <label for="registerUsername" class="block mb-1.5 text-sm font-semibold text-gray-700">Username</label>
              <input type="text" name="username" id="registerUsername" required
                class="w-full h-10 rounded-md border border-gray-300 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
            </div>

            <div class="grid grid-cols-2 gap-3 mb-3">
              <div>
                <label for="registerFirstname" class="block mb-1.5 text-sm font-semibold text-gray-700">First name</label>
                <input type="text" name="firstname" id="registerFirstname" required
                  class="w-full h-10 rounded-md border border-gray-300 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
              </div>
              <div>
                <label for="registerMiddlename" class="block mb-1.5 text-sm font-semibold text-gray-700">Middle name</label>
                <input type="text" name="middlename" id="registerMiddlename" required
                  class="w-full h-10 rounded-md border border-gray-300 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
              </div>
            </div>

            <div class="mb-3">
              <label for="registerLastname" class="block mb-1.5 text-sm font-semibold text-gray-700">Last name</label>
              <input type="text" name="lastname" id="registerLastname" required
                class="w-full h-10 rounded-md border border-gray-300 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
            </div>

            <div class="mb-3">
              <label for="registerEmail" class="block mb-1.5 text-sm font-semibold text-gray-700">Email</label>
              <input type="email" name="email" id="registerEmail" required
                class="w-full h-10 rounded-md border border-gray-300 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
            </div>

            <div class="mb-3">
              <label for="registerPassword" class="block mb-1.5 text-sm font-semibold text-gray-700">Password</label>
              <input type="password" name="password" id="registerPassword" minlength="8" required
                class="w-full h-10 rounded-md border border-gray-300 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
            </div>

            <div class="mb-3">
              <label for="registerConfirmPassword" class="block mb-1.5 text-sm font-semibold text-gray-700">Confirm password</label>
              <input type="password" name="confirm-password" id="registerConfirmPassword" minlength="8" required
                class="w-full h-10 rounded-md border border-gray-300 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
            </div>

            <div class="mb-4">
              <label for="registerDepartment" class="block mb-1.5 text-sm font-semibold text-gray-700">Department</label>
              <select name="department" id="registerDepartment" required
                class="w-full h-10 rounded-md border border-gray-300 bg-white px-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
                <option value="Office of the General Manager">Office of the General Manager</option>
                <option value="Management Information Services Section">Management Information Services Section</option>
                <option value="Administrative Department">Administrative Department</option>
                <option value="Finance Department">Finance Department</option>
                <option value="Commercial Department">Commercial Department</option>
                <option value="Technical Services Department">Technical Services Department</option>
                <option value="Operations Department">Operations Department</option>
              </select>
            </div>

            <label for="termsCheckbox" class="mb-4 flex cursor-pointer items-start gap-2 rounded-md border border-gray-200 p-3 text-xs leading-5 text-gray-600">
              <input type="checkbox" id="termsCheckbox" name="terms" value="1" required class="mt-1 accent-brand">
              <span>I have read and agree to the <a href="#" data-open-terms class="font-semibold text-brand hover:underline">Terms and Conditions</a>.</span>
            </label>

            <p class="mb-3 text-center text-xs text-gray-500" id="termsAgreementHint">Accept the Terms and Conditions to create your account.</p>
            <button type="submit" id="registerSubmitBtn" disabled
              class="w-full h-11 rounded-md bg-brand hover:bg-brand-dark text-white font-semibold text-sm transition mb-4 disabled:cursor-not-allowed disabled:opacity-50">
              Create account
            </button>

            <p class="text-center text-sm text-gray-600">
              Already have an account?
              <a href="?panel=login" data-panel-link="login" class="font-semibold text-brand hover:underline">Sign in</a>
            </p>
          </form>
        </section>

        <section class="auth-view <?= $activePanel === 'forgot' ? 'is-active' : '' ?>" data-auth-view="forgot" aria-hidden="<?= $activePanel === 'forgot' ? 'false' : 'true' ?>">
          <div class="text-center mb-6">
            <img src="assets/img/backgrounds/districtone.png" width="110" class="mx-auto mb-2" alt="DistrictOne">
            <h4 class="text-2xl font-bold text-gray-800">Forgot password?</h4>
            <p class="text-gray-500 text-sm mt-2">Enter your email and we'll send you a link to reset your password.</p>
          </div>

          <form method="POST" action="auth-forgot-password-basic.php" id="forgotPasswordForm">
            <div class="mb-5">
              <label for="forgotEmail" class="block mb-1.5 text-sm font-semibold text-gray-700">Email</label>
              <input type="email" name="email" id="forgotEmail" required
                class="w-full h-11 rounded-md border border-gray-300 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
            </div>
            <button type="submit" class="w-full h-11 rounded-md bg-brand hover:bg-brand-dark text-white font-semibold text-sm transition mb-4">
              Send reset link
            </button>
            <p class="text-center text-sm">
              <a href="?panel=login" data-panel-link="login" class="font-semibold text-brand hover:underline">Back to login</a>
            </p>
          </form>
        </section>

        <section class="auth-view <?= $activePanel === 'reset' ? 'is-active' : '' ?>" data-auth-view="reset" aria-hidden="<?= $activePanel === 'reset' ? 'false' : 'true' ?>">
          <div class="text-center mb-6">
            <img src="assets/img/backgrounds/districtone.png" width="110" class="mx-auto mb-2" alt="DistrictOne">
            <h4 class="text-2xl font-bold text-gray-800">Reset your password</h4>
            <p class="text-gray-500 text-sm mt-2">Choose a new password for your account.</p>
          </div>

          <?php if ($resetTokenValid): ?>
          <form method="POST" action="index?panel=reset&token=<?= rawurlencode($resetToken) ?>" id="resetPasswordForm">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="reset_token" value="<?= htmlspecialchars($resetToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="mb-4">
              <label for="newPassword" class="block mb-1.5 text-sm font-semibold text-gray-700">New password</label>
              <input type="password" name="password" id="newPassword" minlength="8" required
                class="w-full h-11 rounded-md border border-gray-300 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
            </div>

            <div class="mb-5">
              <label for="confirmNewPassword" class="block mb-1.5 text-sm font-semibold text-gray-700">Confirm new password</label>
              <input type="password" name="confirm_password" id="confirmNewPassword" minlength="8" required
                class="w-full h-11 rounded-md border border-gray-300 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
            </div>

            <button type="submit" class="w-full h-11 rounded-md bg-brand hover:bg-brand-dark text-white font-semibold text-sm transition mb-4">
              Update password
            </button>
          </form>
          <?php else: ?>
          <div class="mb-5 rounded-md bg-red-50 p-3 text-sm text-red-700" role="alert">
            <?= htmlspecialchars($resetError ?: 'This reset link is invalid, expired, or no longer active. Request a new reset link to continue.', ENT_QUOTES, 'UTF-8') ?>
          </div>
          <p class="text-center text-sm mb-4">
            <a href="?panel=forgot" data-panel-link="forgot" class="font-semibold text-brand hover:underline">Request a new reset link</a>
          </p>
          <?php endif; ?>

          <p class="text-center text-sm">
            <a href="?panel=login" data-panel-link="login" class="font-semibold text-brand hover:underline">Back to login</a>
          </p>
        </section>
      </div>
    </div>

    <!-- RIGHT IMAGE PANEL -->
    <div class="hidden lg:flex flex-1 min-h-screen relative flex-col items-center justify-center bg-cover bg-center" style="background-image: url('<?php echo htmlspecialchars($loginBackground, ENT_QUOTES, 'UTF-8'); ?>');">

      <div class="absolute inset-0 bg-black/45"></div>
      <div class="login-seasonal-overlay" aria-hidden="true">
        <span></span><span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span><span></span>
      </div>

      <div class="relative z-10 text-center text-white px-10 pb-[670px] animate-fadeUp"> <!--px-[550px] adjust this for height of content -->
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

  <?php include 'modals/terms_modal.php'; ?>

  <!-- FULL-SCREEN LOADING OVERLAY (shown only after a successful login) -->
  <div id="dashboardLoadingOverlay" class="hidden fixed inset-0 z-50 flex-col items-center justify-center gap-4 bg-[#3d6fd4]">
    <div class="w-11 h-11 border-4 border-white/25 border-t-white rounded-full animate-spin"></div>
    <p class="text-white text-sm font-medium">Loading your dashboard…</p>
  </div>

  <script>
    function showAuthPanel(panel, updateHistory) {
      const views = document.querySelectorAll('[data-auth-view]');
      const selectedView = document.querySelector('[data-auth-view="' + panel + '"]');
      if (!selectedView) {
        return;
      }

      views.forEach(function (view) {
        const isActive = view === selectedView;
        view.classList.toggle('is-active', isActive);
        view.setAttribute('aria-hidden', isActive ? 'false' : 'true');
      });

      if (updateHistory) {
        history.pushState({ panel: panel }, '', '?panel=' + encodeURIComponent(panel));
      }

      const firstInput = selectedView.querySelector('input:not([type="checkbox"]), select');
      if (firstInput) {
        firstInput.focus();
      }
    }

    document.querySelectorAll('[data-panel-link]').forEach(function (link) {
      link.addEventListener('click', function (event) {
        event.preventDefault();
        showAuthPanel(link.dataset.panelLink, true);
      });
    });

    window.addEventListener('popstate', function () {
      const panel = new URLSearchParams(window.location.search).get('panel') || 'login';
      showAuthPanel(['login', 'register', 'forgot', 'reset'].includes(panel) ? panel : 'login', false);
    });

    const termsModal = document.getElementById('termsModal');
    const termsCheckbox = document.getElementById('termsCheckbox');
    const registerSubmitButton = document.getElementById('registerSubmitBtn');
    const termsAgreementHint = document.getElementById('termsAgreementHint');
    let termsReturnFocus = null;
    let previousBodyOverflow = '';

    function updateRegistrationAvailability() {
      registerSubmitButton.disabled = !termsCheckbox.checked;
      termsAgreementHint.textContent = termsCheckbox.checked
        ? 'Thank you. You can now create your account.'
        : 'Accept the Terms and Conditions to create your account.';
    }

    function closeTermsModal() {
      termsModal.classList.remove('is-open');
      termsModal.setAttribute('aria-hidden', 'true');
      termsModal.removeAttribute('aria-modal');
      document.body.style.overflow = previousBodyOverflow;
      if (termsReturnFocus) {
        termsReturnFocus.focus();
      }
    }

    termsCheckbox.addEventListener('change', updateRegistrationAvailability);
    updateRegistrationAvailability();

    document.querySelector('[data-open-terms]').addEventListener('click', function (event) {
      event.preventDefault();
      termsReturnFocus = event.currentTarget;
      previousBodyOverflow = document.body.style.overflow;
      termsModal.classList.add('is-open');
      termsModal.setAttribute('aria-hidden', 'false');
      termsModal.setAttribute('aria-modal', 'true');
      document.body.style.overflow = 'hidden';
      termsModal.querySelector('.btn-close').focus();
    });

    document.querySelectorAll('#termsModal [data-bs-dismiss="modal"]').forEach(function (button) {
      button.addEventListener('click', function () {
        closeTermsModal();
      });
    });

    document.querySelector('[data-accept-terms]').addEventListener('click', function () {
      termsCheckbox.checked = true;
      termsCheckbox.dispatchEvent(new Event('change', { bubbles: true }));
      closeTermsModal();
    });

    termsModal.addEventListener('click', function (event) {
      if (event.target === event.currentTarget) {
        closeTermsModal();
      }
    });

    document.addEventListener('keydown', function (event) {
      if (!termsModal.classList.contains('is-open')) {
        return;
      }

      if (event.key === 'Escape') {
        closeTermsModal();
        return;
      }

      if (event.key === 'Tab') {
        const focusableElements = termsModal.querySelectorAll('button:not([disabled]), a[href], input:not([disabled])');
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];
        if (event.shiftKey && document.activeElement === firstElement) {
          event.preventDefault();
          lastElement.focus();
        } else if (!event.shiftKey && document.activeElement === lastElement) {
          event.preventDefault();
          firstElement.focus();
        }
      }
    });

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

      <?php if ($registrationError): ?>
      Swal.fire({
        icon: 'error',
        title: 'Unable to register',
        text: <?= json_encode($registrationError) ?>,
        confirmButtonColor: '#568df5'
      });
      <?php endif; ?>

      <?php if ($registrationSuccess): ?>
      Swal.fire({
        icon: 'success',
        title: 'Registration submitted',
        text: <?= json_encode($registrationSuccess) ?>,
        confirmButtonColor: '#568df5'
      });
      <?php endif; ?>

      <?php if ($forgotAlert): ?>
      Swal.fire({
        icon: <?= json_encode($forgotAlert['type'] ?? 'info') ?>,
        title: <?= json_encode($forgotAlert['message'] ?? '') ?>,
        confirmButtonColor: '#568df5'
      });
      <?php endif; ?>

      <?php if ($resetError && $resetTokenValid): ?>
      Swal.fire({
        icon: 'error',
        title: 'Unable to reset password',
        text: <?= json_encode($resetError) ?>,
        confirmButtonColor: '#568df5'
      });
      <?php endif; ?>

      <?php if ($resetSuccess): ?>
      Swal.fire({
        icon: 'success',
        title: 'Password updated',
        text: <?= json_encode($resetSuccess) ?>,
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
  <style>
    .swal2-popup.public-sans-font { font-family: 'Public Sans', sans-serif !important; }
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
          popup: 'public-sans-font'
        }
      });
    });
  </script>
  <?php endif; ?>

</body>

</html>