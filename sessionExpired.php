<!DOCTYPE html>
<html lang="en" class="light-style customizer-hide" dir="ltr" data-theme="theme-default" data-assets-path="assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Session Expired</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/img/favicon/districtone.png" />

    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="assets/vendor/fonts/boxicons.css" />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="assets/vendor/css/core.css" />
    <link rel="stylesheet" href="assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="assets/css/demo.css" />
    <link rel="stylesheet" href="css/login.css">

    <script src="assets/vendor/js/helpers.js"></script>
    <script src="assets/js/config.js"></script>
    <script src="sweetalert2.all.min.js"></script>

    <style>
        body,
        html {
            height: 100%;
            margin: 0;
            font-family: 'Public Sans', sans-serif;
        }

        .bg-container {
            background-image: url('assets/img/backgrounds/login-bg.jpg');
            background-size: cover;
            background-position: center;
            height: 100%;
            width: 100%;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .bg-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: 1;
        }

        .session-box {
            position: relative;
            z-index: 2;
            background: #ffffff;
            padding: 40px 30px;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }

        .session-box img {
            margin-bottom: 20px;
        }

        .session-box h4 {
            margin-bottom: 15px;
        }

        .session-box p {
            margin-bottom: 25px;
            color: #6c757d;
        }

        .btn-login {
            width: 100%;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .session-box {
                padding: 30px 20px;
            }
        }
    </style>
</head>

<body>
    <div class="bg-container">
        <div class="bg-overlay"></div>

        <div class="session-box">
            <img src="assets/img/backgrounds/districtone.png" alt="DistrictOne Logo" width="150" />
            <h4>Session Expired</h4>
            <p>Your session has expired. Please log in again to continue.</p>
            <a href="login" class="btn btn-primary btn-login">Go to Login</a>
        </div>
    </div>

    <script>
        // Optional SweetAlert popup
        document.addEventListener('DOMContentLoaded', function () {
            Swal.fire({
                icon: 'warning',
                title: 'Session Expired',
                text: 'Your session has expired. Please log in again to continue.',
                confirmButtonText: 'Go to Login',
                confirmButtonColor: '#568df5'
            }).then(() => {
                window.location.href = 'login.php';
            });
        });
    </script>
</body>

</html>