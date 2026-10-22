<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Under Maintenance</title>

  <link rel="icon" type="image/x-icon" href="assets/img/favicon/districtone.png" />

  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800&display=swap"
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
    .maint-wrapper {
      display: flex;
      width: 100%;
      height: 100vh;
    }

    /* ===== LEFT PANEL ===== */
    .maint-panel {
      width: 450px;
      min-width: 400px;
      background-color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 30px;
    }

    .maint-box {
      width: 100%;
      max-width: 320px;
      text-align: center;
    }

    .maint-box img {
      margin-bottom: 22px;
    }

    .maint-icon-wrap {
      width: 72px;
      height: 72px;
      border-radius: 50%;
      background: rgba(86, 141, 245, 0.12);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 20px auto;
    }

    .maint-icon-wrap i {
      font-size: 34px;
      color: #568df5;
      animation: spin 3.5s linear infinite;
    }

    @keyframes spin {
      from {
        transform: rotate(0deg);
      }

      to {
        transform: rotate(360deg);
      }
    }

    .maint-box h4 {
      font-weight: 700;
      margin-bottom: 10px;
    }

    .maint-box p.text-muted {
      font-size: 14.5px;
      line-height: 1.6;
    }

    .maint-status {
      margin-top: 22px;
      padding: 14px 16px;
      background: #f6f8fc;
      border: 1px solid #e9edf5;
      border-radius: 10px;
      text-align: left;
    }

    .maint-status small {
      display: block;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-size: 10.5px;
      color: #7d8aa3;
      margin-bottom: 4px;
    }

    .maint-status span {
      font-size: 13.5px;
      color: #33415c;
      font-weight: 500;
    }

    .btn-refresh {
      height: 45px;
      background-color: #568df5;
      border: none;
      border-radius: 6px;
      transition: 0.3s ease;
      width: 100%;
      margin-top: 24px;
      color: #fff;
      font-weight: 600;
      font-size: 14.5px;
    }

    .btn-refresh:hover {
      background-color: #3d6fd4;
    }

    .maint-footer {
      margin-top: 26px;
      font-size: 12px;
      color: #a3abbd;
    }

    /* ===== RIGHT IMAGE PANEL ===== */
    .image-panel {
      flex: 1;
      min-height: 100vh;
      background-image: url("assets/img/backgrounds/bg69.jpg");
      background-position: center center;
      background-size: cover;
      background-repeat: no-repeat;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
    }

    .image-panel::after {
      content: "";
      position: absolute;
      inset: 0;
      background: rgba(0, 0, 0, 0.55);
    }

    .hero-content {
      position: relative;
      z-index: 2;
      text-align: center;
      color: #ffffff;
      padding: 40px;
      animation: fadeUp 1s ease;
    }

    .hero-content h2 {
      font-weight: 400;
      font-size: 1.8rem;
      color: #ffffff;
    }

    .hero-content h1 {
      font-size: 3.2rem;
      font-weight: 800;
      letter-spacing: 2px;
      color: #ffffff;
    }

    .hero-content p {
      font-weight: 300;
      margin-top: 15px;
      color: #ffffff;
    }

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
      .maint-wrapper {
        flex-direction: column;
      }

      .maint-panel {
        width: 100%;
        min-width: unset;
        height: 100vh;
        justify-content: center;
      }

      .image-panel {
        display: none;
      }
    }
  </style>
</head>

<body>

  <div class="maint-wrapper">

    <!-- LEFT MAINTENANCE MESSAGE -->
    <div class="maint-panel">
      <div class="maint-box">

        <img src="assets/img/backgrounds/districtone.png" width="140" alt="DistrictOne Logo">

        <div class="maint-icon-wrap">
          <i class='bx bx-wrench'></i>
        </div>

        <h4 class="fw-bold">We'll be right back</h4>
        <p class="text-muted">
          DistrictOne is currently undergoing scheduled maintenance to improve
          your experience. Please check back shortly.
        </p>

        <div class="maint-status">
          <small>Status</small>
          <span>Our team is working on it. Thank you for your patience.</span>
        </div>

        <!-- <button type="button" class="btn btn-refresh" onclick="window.location.reload()">
          Try Again
        </button> -->

        <p class="maint-footer">
          For urgent concerns, please contact the MIS Section.
        </p>

      </div>
    </div>

    <!-- RIGHT IMAGE -->
    <div class="image-panel">
      <div class="hero-content">
        <h2><font color="white">Welcome to</font></h2>
        <h1><font color="white">DistrictOne</font></h1>
        <p>Calamba Water District Management Information System</p>
      </div>
    </div>

  </div>

  <script src="assets/vendor/libs/jquery/jquery.js"></script>
  <script src="assets/vendor/js/bootstrap.js"></script>

</body>

</html>