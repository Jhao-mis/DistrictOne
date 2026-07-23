<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

session_start();
require 'vendor/autoload.php';
require 'db.php';   // ✅ Use centralized DB connection

$alertType = $_SESSION['alertType'] ?? '';
$alertText = $_SESSION['alertText'] ?? '';
$redirectFrom = $_SESSION['redirectFrom'] ?? '';

unset($_SESSION['alertType'], $_SESSION['alertText'], $_SESSION['redirectFrom']);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Enable error reporting

// ✅ $conn (MySQLi) is already available from db.php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $username = trim($_POST['username']);
  $firstname = trim($_POST['firstname']);
  $middlename = trim($_POST['middlename']);
  $lastname = trim($_POST['lastname']);
  $email = trim($_POST['email']);
  $department = trim($_POST['department']);
  $password = $_POST['password'];
  $confirm_password = $_POST['confirm-password'];
  $VerificationCode = md5(rand()); // Random verification code

  // Check if all fields are filled
  if (empty($username) || empty($firstname) || empty($middlename) || empty($lastname) || empty($email) || empty($department) || empty($password) || empty($confirm_password)) {
    $_SESSION['error'] = 'All fields must be completed before proceeding!';
    header('Location: register.php');
    exit();
  }

  // Validate email
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = 'Invalid email format!';
    header('Location: register.php');
    exit();
  }

  // Check if username or email already exists
  $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
  $stmt->bind_param("ss", $email, $username);
  $stmt->execute();
  $stmt->store_result();

  if ($stmt->num_rows > 0) {
    $_SESSION['error'] = 'This email or username is already taken.';
    header('Location: register.php');
    exit();
  }
  $stmt->close();

  // Check if passwords match
  if ($password !== $confirm_password) {
    $_SESSION['error'] = 'Passwords do not match.';
    header('Location: register.php');
    exit();
  }

  // Hash the password
  $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

  // Insert user into database
  $stmt = $conn->prepare("INSERT INTO users (username, firstname, middlename, lastname, email, password, department, VerificationCode, isVerified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
  $stmt->bind_param("ssssssss", $username, $firstname, $middlename, $lastname, $email, $hashedPassword, $department, $VerificationCode);

  if ($stmt->execute()) {
    $stmt->close();
    $_SESSION['username'] = $username;
    $_SESSION['email'] = $email;
    $_SESSION['firstname'] = $firstname;
    $_SESSION['middlename'] = $middlename;
    $_SESSION['lastname'] = $lastname;
    $_SESSION['department'] = $department;

    // Send verification email
    $mail = new PHPMailer(true);
    try {
      $mail->isSMTP();
      $mail->Host = 'smtp.gmail.com';
      $mail->SMTPAuth = true;
      $mail->Username = 'districtone.cwd@gmail.com';
      $mail->Password = 'xwwu ehin bzej ttio'; // App Password
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
      $mail->Port = 465;

      $mail->setFrom($email);
      $mail->addAddress('districtone.cwd@gmail.com');
      $mail->isHTML(true);
      $mail->Subject = 'New User Registration - Verify Account';
      $mail->Body = "
                A new user has registered: <br><br>
                Name: $firstname $middlename $lastname <br>
                Department: $department <br>
                Email: $email <br><br>
            ";

      if ($mail->send()) {
        $_SESSION['success'] = 'Registration Successful! Wait for the admin`s approval to verify your account.';
      } else {
        $_SESSION['error'] = 'Your account is still pending admin approval.';
      }
    } catch (Exception $e) {
      $_SESSION['error'] = 'Mailer Error: ' . $mail->ErrorInfo;
    }
  } else {
    $_SESSION['error'] = 'Database insert failed. Please try again.';
  }

  $conn->close();
}
?>

<?php
if (isset($_SESSION['error'])) {
  echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                confirmButtonColor: '#d33',
                confirmButtonText: 'Try again',
                text: '" . $_SESSION['error'] . "'
            });
        });
    </script>";
  unset($_SESSION['error']); // Clear message after displaying
}

if (isset($_SESSION['success'])) {
  echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                confirmButtonColor: '#28a745',
                text: '" . $_SESSION['success'] . "'
            });
        });
    </script>";
  unset($_SESSION['success']); // Clear message after displaying
}
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!DOCTYPE html>
<html lang="en" class="light-style customizer-hide" dir="ltr" data-theme="theme-default" data-assets-path="assets/"
  data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>Register</title>

  <meta name="description" content="" />

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="assets/img/favicon/districtone.png" />

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
    rel="stylesheet" />

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Icons. Uncomment required icon fonts -->
  <link rel="stylesheet" href="assets/vendor/fonts/boxicons.css" />

  <!-- Core CSS -->
  <link rel="stylesheet" href="assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="assets/css/demo.css" />
  <link rel="stylesheet" href="./css/register.css">

  <!-- Vendors CSS -->
  <link rel="stylesheet" href="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="assets/vendor/css/pages/page-auth.css" />
  <!-- Helpers -->
  <script src="assets/vendor/js/helpers.js"></script>
  <script src="assets/js/config.js"></script>
</head>

<body>

  <div class="container-xxl">
    <div class="authentication-wrapper authentication-basic container-p-y">

      <!-- Register Card -->
      <div class="card">
        <div class="card-body">
          <!-- Logo -->
          <div class="app-brand justify-content-center">
            <a class="app-brand-link gap-2">
              <span class="app-brand-logo demo">
                <svg width="25" viewBox="0 0 25 42" version="1.1" xmlns="http://www.w3.org/2000/svg"
                  xmlns:xlink="http://www.w3.org/1999/xlink">
                  <defs>
                    <path
                      d="M13.7918663,0.358365126 L3.39788168,7.44174259 C0.566865006,9.69408886 -0.379795268,12.4788597 0.557900856,15.7960551 C0.68998853,16.2305145 1.09562888,17.7872135 3.12357076,19.2293357 C3.8146334,19.7207684 5.32369333,20.3834223 7.65075054,21.2172976 L7.59773219,21.2525164 L2.63468769,24.5493413 C0.445452254,26.3002124 0.0884951797,28.5083815 1.56381646,31.1738486 C2.83770406,32.8170431 5.20850219,33.2640127 7.09180128,32.5391577 C8.347334,32.0559211 11.4559176,30.0011079 16.4175519,26.3747182 C18.0338572,24.4997857 18.6973423,22.4544883 18.4080071,20.2388261 C17.963753,17.5346866 16.1776345,15.5799961 13.0496516,14.3747546 L10.9194936,13.4715819 L18.6192054,7.984237 L13.7918663,0.358365126 Z"
                      id="path-1"></path>
                    <path
                      d="M5.47320593,6.00457225 C4.05321814,8.216144 4.36334763,10.0722806 6.40359441,11.5729822 C8.61520715,12.571656 10.0999176,13.2171421 10.8577257,13.5094407 L15.5088241,14.433041 L18.6192054,7.984237 C15.5364148,3.11535317 13.9273018,0.573395879 13.7918663,0.358365126 C13.5790555,0.511491653 10.8061687,2.3935607 5.47320593,6.00457225 Z"
                      id="path-3"></path>
                    <path
                      d="M7.50063644,21.2294429 L12.3234468,23.3159332 C14.1688022,24.7579751 14.397098,26.4880487 13.008334,28.506154 C11.6195701,30.5242593 10.3099883,31.790241 9.07958868,32.3040991 C5.78142938,33.4346997 4.13234973,34 4.13234973,34 C4.13234973,34 2.75489982,33.0538207 2.37032616e-14,31.1614621 C-0.55822714,27.8186216 -0.55822714,26.0572515 -4.05231404e-15,25.8773518 C0.83734071,25.6075023 2.77988457,22.8248993 3.3049379,22.52991 C3.65497346,22.3332504 5.05353963,21.8997614 7.50063644,21.2294429 Z"
                      id="path-4"></path>
                    <path
                      d="M20.6,7.13333333 L25.6,13.8 C26.2627417,14.6836556 26.0836556,15.9372583 25.2,16.6 C24.8538077,16.8596443 24.4327404,17 24,17 L14,17 C12.8954305,17 12,16.1045695 12,15 C12,14.5672596 12.1403557,14.1461923 12.4,13.8 L17.4,7.13333333 C18.0627417,6.24967773 19.3163444,6.07059163 20.2,6.73333333 C20.3516113,6.84704183 20.4862915,6.981722 20.6,7.13333333 Z"
                      id="path-5"></path>
                  </defs>
                  <g id="g-app-brand" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                    <g id="Brand-Logo" transform="translate(-27.000000, -15.000000)">
                      <g id="Icon" transform="translate(27.000000, 15.000000)">
                        <g id="Mask" transform="translate(0.000000, 8.000000)">
                          <g id="Path-3" mask="url(#mask-2)">
                            <use fill-opacity="0.2" fill="#FFFFFF" xlink:href="#path-3"></use>
                          </g>
                        </g>
                      </g>
                    </g>
                  </g>

                </svg>
              </span>
              <!-- CHANGE FOR BG -->
              <img src="assets/img/backgrounds/bg69.jpg" alt="CWD Logo" width="60" class="logo" />  
              <span class="app-brand-text text-body fw-bolder">District One</span>
              <br />
              <span class="app-brand-text text-body fw-bolder small-text">Calamba Water District Management Information
                System</span>
            </a>
          </div>

          <form id="formAuthentication" class="mb-3" method="POST">
            <div class="mb-3">
              <label for="username" class="form-label">Username</label>
              <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username"
                autofocus />

            </div>
            <div class="mb-3">
              <label for="firstname" class="form-label">First name</label>
              <input type="text" class="form-control" id="firstname" name="firstname"
                placeholder="Enter your first name" autofocus />
            </div>

            <div class="mb-3">
              <label for="middlename" class="form-label">Middle name</label>
              <input type="text" class="form-control" id="middlename" name="middlename"
                placeholder="Enter your middle name" autofocus />
            </div>
            <div class="mb-3">
              <label for="lasttname" class="form-label">Last name</label>
              <input type="text" class="form-control" id="lastname" name="lastname" placeholder="Enter your last name"
                autofocus />

              <label for="email" class="form-label">Email</label>
              <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email"
                autofocus />

            </div>
            <div class="mb-3 form-password-toggle">
              <label class="form-label" for="password">Password</label>
              <div class="input-group input-group-merge">
                <input type="password" id="password" class="form-control" name="password"
                  placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                  aria-describedby="password" minlength="8" />
                <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
              </div>
            </div>

            <script>
              function validatePassword() {
                const passwordInput = document.getElementById("password");
                const errorText = document.getElementById("password-error");

                if (passwordInput.value.length < 8) {
                  errorText.style.display = "block";
                } else {
                  errorText.style.display = "none";
                }
              }
            </script>

            <div class="mb-3 form-password-toggle">
              <label class="form-label" for="confirm-password">Confirm Password</label>
              <div class="input-group input-group-merge">
                <input type="password" id="confirm-password" class="form-control" name="confirm-password"
                  placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                  aria-describedby="password" />
                <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
              </div>
            </div>

            <!-- Department Dropdown -->
            <div class="mb-3">
              <label for="department" class="form-label">Department</label>
              <select class="form-select" id="department" name="department">
                <option value="Office of the General Manager">Office of the General Manager</option>
                <option value="Management Information Services Section">Management Information Services Section</option>
                <option value="Administrative Department">Administrative Department</option>
                <option value="Finance Department">Finance Department</option>
                <option value="Commercial Department">Commercial Department</option>
                <option value="Technical Services Department">Technical Services Department</option>
                <option value="Operations Department">Operations Department</option>
              </select>
            </div>


            <!-- Terms Checkbox and Sign Up Button -->
            <div class="mb-3 form-check">
              <input type="checkbox" class="form-check-input" id="termsCheckbox" onclick="toggleSignup()" />
              <label class="form-check-label" for="termsCheckbox">
                I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a>
              </label>
            </div>

            <div class="mb-3">
              <button type="submit" id="signUpBtn" class="btn btn-primary d-grid w-100" disabled>Sign Up</button>
            </div>

            <!-- script para sa button -->
            <script>
              function toggleSignup() {

                const termsCheckbox = document.getElementById('termsCheckbox');
                const signUpBtn = document.getElementById('signUpBtn');

                if (termsCheckbox.checked) {
                  signUpBtn.disabled = false;
                } else {
                  signUpBtn.disabled = true;
                }
              }
            </script>

            <!-- <button type="signup" class="btn btn-primary d-grid w-100" style="background-color: #568df5;">Sign up</button> -->

          </form>

          <p class="text-center">
            <span>Already have an account?</span>
            <a href="login.php">
              <span style="color: #568df5;">Sign in instead</span>

            </a>
          </p>
        </div>
      </div>
      <!-- Register Card -->
    </div>
  </div>
  </div>

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
        confirmButtonColor: '#d33',
        customClass: {
          popup: 'poppins-font'
        }
      });
    </script>




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

  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <?php
  if (isset($_SESSION['error'])) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: '" . $_SESSION['error'] . "',
                confirmButtonColor: '#d33',
                confirmButtonText: 'Try again'
            });
        });
    </script>";
    unset($_SESSION['error']);
  }

  if (isset($_SESSION['success'])) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '" . $_SESSION['success'] . "',
                confirmButtonColor: '#28a745',
                confirmButtonText: 'OK'
            });
        });
    </script>";
    unset($_SESSION['success']);
  }
  ?>


  <!-- Page JS -->

  <!-- Place this tag in your head or just before your close body tag. -->
  <script async defer src="https://buttons.github.io/buttons.js"></script>




  <!-- Terms and Conditions Modal -->
  <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="termsModalLabel">Terms and Conditions for Account Creation</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" style="text-align: justify;">
          <p><strong>Effective Date:</strong> April 11, 2025</p>
          <p>Welcome to District One, the official Management Information System (MIS) of Calamba Water District (CWD).
            By creating an account and using this system, you acknowledge and agree to the following terms and
            conditions, in compliance with the Data Privacy Act of 2012 (RA 10173) and other applicable laws of the
            Republic of the Philippines.</p>

          <h6>1. Acceptance of Terms</h6>
          <ul>
            <li>Affirm that you are a legitimate employee of Calamba Water District.</li>
            <li>Agree to be bound by these Terms and Conditions.</li>
            <li>Consent to the collection, processing, storage, and use of your personal data for lawful and legitimate
              organizational purposes.</li>
          </ul>

          <h6>2. Data Privacy and Protection</h6>
          <p>In compliance with the Data Privacy Act of 2012, we are committed to ensuring that your personal data is:
          </p>
          <ul>
            <li>Collected for specified and legitimate purposes only.</li>
            <li>Processed fairly, accurately, and in a secure manner.</li>
            <li>Stored only for as long as necessary to fulfill its purpose or as required by law.</li>
            <li>Not disclosed to unauthorized persons or entities.</li>
          </ul>
          <p>We implement appropriate organizational, technical, and physical security measures to safeguard your data
            from unauthorized access, alteration, and disclosure.</p>

          <h6>3. Data Collected</h6>
          <p>Upon account creation and during system use, we may collect the following:</p>
          <ul>
            <li><strong>Personal Information:</strong> Full name, employee number, department, position, contact
              details, and other identifiers.</li>
            <li><strong>System Use Data:</strong> Login logs, user activity, and submitted transactions within the
              system.</li>
          </ul>

          <h6>4. Purpose of Data Collection</h6>
          <p>Your data will only be used for:</p>
          <ul>
            <li>Internal records and employee management.</li>
            <li>Access control and identity verification.</li>
            <li>Generation of reports and analytics for organizational planning.</li>
            <li>Compliance with regulatory and legal obligations.</li>
          </ul>

          <h6>5. User Responsibilities</h6>
          <p>You agree to:</p>
          <ul>
            <li>Provide accurate and truthful information during account registration.</li>
            <li>Keep your login credentials confidential and secure.</li>
            <li>Use the system responsibly and only for its intended work-related purposes.</li>
            <li>Immediately report any suspicious activity or security breach.</li>
          </ul>
          <p>You are solely responsible for any actions taken under your account.</p>

          <h6>6. System Access and Monitoring</h6>
          <p>CWD reserves the right to:</p>
          <ul>
            <li>Monitor and audit user activity within the system for security, performance, and compliance purposes.
            </li>
            <li>Suspend or terminate access in cases of misuse, unauthorized access, or policy violations.</li>
          </ul>

          <h6>7. Intellectual Property</h6>
          <p>All content, data, and software within District One are the property of CWD. Unauthorized copying,
            distribution, or use is strictly prohibited.</p>

          <h6>8. Modification of Terms</h6>
          <p>CWD may revise these Terms and Conditions at any time. Continued use of the system after changes are made
            constitutes your acceptance of the updated terms.</p>

          <h6>9. Contact and MIS Supervisor</h6>
          <p>For inquiries or concerns regarding your data or these terms, you may contact the MIS Supervisor of Calamba
            Water District at:</p>
          <ul>
            <li><strong>Name:</strong> Engr. Jonathan Dave A. Fajarda</li>
            <li><strong>Email:</strong> calambawaterdistrict.mis@gmail.com</li>
          </ul>

          <p>By clicking "I Agree" or creating an account, you acknowledge that you have read, understood, and agreed to
            these Terms and Conditions.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>



  </div>

  <!-- Floating About Us Button -->
  <a href="about_us.php" class="btn btn-light shadow position-fixed"
    style="bottom: 20px; right: 20px; z-index: 9999; padding: 10px 20px; font-weight: bold;" title="About DistrictOne">
    About DistrictOne
  </a>



</body>

</html>