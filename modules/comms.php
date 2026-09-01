<?php
include '../db.php';
require '../vendor/autoload.php';
require 'login_verification.php';

$username = $_SESSION['username'];

date_default_timezone_set('Asia/Manila');
$now = date('Y-m-d H:i:s');

$updateActivity = $conn->prepare("UPDATE users SET last_activity = ? WHERE username = ?");
$updateActivity->bind_param("ss", $now, $username);
$updateActivity->execute();
$updateActivity->close();

/* ===========================
   FETCH USER PROFILE DATA
   =========================== */
$query = $conn->prepare("
    SELECT 
        users.id,
        users.emp_id,
        users.profile_picture,
        users.cover_photo,
        users.department,
        users.firstname,
        users.middlename,
        users.lastname,
        users.email,
        personal_data_sheet.position
    FROM users
    LEFT JOIN personal_data_sheet 
        ON users.id = personal_data_sheet.user_id
    WHERE users.username = ?
");

$query->bind_param("s", $username);
$query->execute();
$query->store_result();

$query->bind_result(
    $user_id,
    $emp_id,
    $profile_picture,
    $cover_photo,
    $department,
    $firstname,
    $middlename,
    $lastname,
    $email,
    $position
);

$query->fetch();
$query->close();

/* ===========================
   DEFAULT IMAGES
   =========================== */
if (empty($profile_picture)) {
    $profile_picture = '../assets/img/avatars/default_dp.jpg';
}
if (empty($cover_photo)) {
    $cover_photo = '../assets/img/avatars/default_cover.png';
}

$_SESSION['profile_picture'] = $profile_picture;
$_SESSION['cover_photo'] = $cover_photo;

/* ===========================
   SAFE DEFAULTS
   =========================== */
$emp_id = $emp_id ?? '—';

?>

<!DOCTYPE html>

<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Committees</title>

    <meta name="description" content="" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/districtone.png" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
        rel="stylesheet" />

    <!-- Icons -->
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="./css/profileTeams.css">

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="../assets/vendor/libs/apex-charts/apex-charts.css" />

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Helpers -->
    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>

    <style>
        :root {
            --tk-bg: #f7f8fa;
            --tk-surface: #ffffff;
            --tk-border: #e8eaee;
            --tk-text: #1f2430;
            --tk-text-muted: #767e8c;
            --tk-primary: #7cb9ff;
            --tk-primary-soft: #eaf3ff;
            --tk-radius: 12px;
            --tk-shadow: 0 1px 2px rgba(20, 20, 43, .04), 0 8px 24px -12px rgba(20, 20, 43, .10);
        }

        .pf-wrap {
            font-family: inherit;
            color: var(--tk-text);
        }

        .pf-card {
            background: var(--tk-surface);
            border: 1px solid var(--tk-border);
            border-radius: var(--tk-radius);
            box-shadow: var(--tk-shadow);
            overflow: hidden;
        }

        .user-profile-info {
            line-height: 1.4;
        }

        .user-name {
            font-size: 1.75rem;
        }

        @media (max-width: 576px) {
            .user-name {
                font-size: 1.4rem;
            }

            .user-profile-header {
                padding-top: 2rem !important;
            }
        }

        .pf-emp-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--tk-primary-soft);
            color: #2563a8;
            font-size: 12.5px;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 999px;
        }

        .pf-emp-badge svg {
            width: 13px;
            height: 13px;
        }

        .pf-visit-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--tk-primary);
            color: #fff;
            font-weight: 600;
            font-size: 13.5px;
            padding: 9px 16px;
            border-radius: 9px;
            text-decoration: none;
            box-shadow: 0 4px 10px -4px rgba(124, 185, 255, .6);
            transition: background .12s ease, transform .12s ease;
        }

        .pf-visit-btn:hover {
            background: #4e96f0;
            color: #fff;
            transform: translateY(-1px);
        }

        .dir-card {
            background: var(--tk-surface);
            border: 1px solid var(--tk-border);
            border-radius: var(--tk-radius);
            box-shadow: var(--tk-shadow);
        }

        .dir-search {
            position: relative;
            margin-bottom: 20px;
        }

        .dir-search svg {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            color: var(--tk-text-muted);
            pointer-events: none;
        }

        .dir-search input {
            width: 100%;
            padding: 13px 16px 13px 46px;
            border: 1.5px solid var(--tk-border);
            border-radius: 999px;
            font-size: 14px;
            background: var(--tk-bg);
            outline: none;
            transition: border-color .12s ease, box-shadow .12s ease, background .12s ease;
        }

        .dir-search input:focus {
            border-color: var(--tk-primary);
            background: var(--tk-surface);
            box-shadow: 0 0 0 4px var(--tk-primary-soft);
        }

        .dir-filters {
            display: flex;
            gap: 8px;
            flex-wrap: nowrap;
            overflow-x: auto;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }

        .dir-filters::-webkit-scrollbar {
            height: 4px;
        }

        .dir-filters::-webkit-scrollbar-thumb {
            background: var(--tk-primary-soft);
            border-radius: 4px;
        }

        .dir-filter-btn {
            flex-shrink: 0;
            border: 1.5px solid var(--tk-border);
            background: var(--tk-surface);
            color: var(--tk-text-muted);
            font-size: 12.5px;
            font-weight: 700;
            padding: 7px 15px;
            border-radius: 999px;
            cursor: pointer;
            white-space: nowrap;
            transition: all .12s ease;
        }

        .dir-filter-btn:hover {
            border-color: var(--tk-primary);
            color: var(--tk-primary);
        }

        .dir-filter-btn.active {
            background: var(--tk-primary);
            border-color: var(--tk-primary);
            color: #fff;
        }

        .dept-header {
            margin-bottom: 18px;
        }

        .dept-header h5 {
            color: var(--tk-text);
            font-weight: 700;
            letter-spacing: .2px;
        }

        .dept-count {
            background: var(--tk-primary-soft);
            color: #2563a8;
            font-weight: 700;
            font-size: 11.5px;
            padding: 4px 11px;
            border-radius: 999px;
        }

        /* Card Container Optimization */
        .tel-card-ui {
            border-radius: 16px;
            background: #fff;
            transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            will-change: transform, box-shadow;
            backface-visibility: hidden;
        }

        .tel-card-ui:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.75rem 1.75rem rgba(0, 0, 0, 0.08) !important;
        }

        /* Profile Picture Hover Scaling */
        .tel-avatar-wrapper img {
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            will-change: transform;
        }

        .tel-card-ui:hover .tel-avatar-wrapper img {
            transform: scale(1.06);
        }

        /* Typography & Component Micro-adjustments */
        .tel-card-ui h5 {
            font-size: 1.05rem;
            line-height: 1.3;
        }

        .tel-card-ui .small {
            font-size: 0.825rem;
        }

        .tel-card-ui .badge {
            font-size: 0.8125rem;
            letter-spacing: 0.2px;
            line-height: 1.4;
        }

        .tel-name {
            font-size: 15px;
            font-weight: 700;
            color: var(--tk-text);
        }

        .tel-footer {
            background: var(--tk-bg) !important;
            border-color: var(--tk-border) !important;
        }

        .tel-copy-btn {
            border-color: var(--tk-border) !important;
            color: var(--tk-text-muted);
            transition: border-color .12s ease, color .12s ease, background .12s ease;
        }

        .tel-copy-btn:hover {
            border-color: var(--tk-primary) !important;
            color: var(--tk-primary);
            background: var(--tk-primary-soft) !important;
        }

        /* Committee Dropdown Custom Styling */
        .comm-filter-card {
            background: #ffffff;
            padding: 16px 20px;
            border-radius: var(--tk-radius, 12px);
            border: 1px solid var(--tk-border, #e8eaee);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .comm-filter-card:hover {
            border-color: var(--tk-primary, #7cb9ff);
        }

        .custom-comm-select {
            border-radius: 10px !important;
            border: 1.5px solid var(--tk-border, #e8eaee) !important;
            font-size: 0.95rem !important;
            font-weight: 500 !important;
            color: var(--tk-text, #1f2430) !important;
            background-color: var(--tk-bg, #f7f8fa) !important;
            padding: 12px 16px !important;
            cursor: pointer;
            transition: all 0.2s ease-in-out !important;
        }

        /* Hover at Focus States */
        .custom-comm-select:hover {
            background-color: #ffffff !important;
            border-color: var(--tk-primary, #7cb9ff) !important;
        }

        .custom-comm-select:focus {
            background-color: #ffffff !important;
            border-color: #4e96f0 !important;
            box-shadow: 0 0 0 4px var(--tk-primary-soft, #eaf3ff) !important;
            outline: none !important;
        }

        /* Styling para sa mismong options list */
        .custom-comm-select option {
            padding: 10px;
            font-weight: 500;
            color: #333;
        }
    </style>
</head>

<body>

    <?php $role = $_SESSION['role'];

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

    <div class="content-wrapper">
        <div class="container-xxl flex-grow-1 container-p-y">
            <div class="pf-wrap">

                <div class="row">
                    <div class="col-12">
                        <div class="pf-card mb-6">
                            <div class="user-profile-header-banner">
                                <div class="cover-photo-container"
                                    style="height: 300px; overflow: hidden; position: relative;">
                                    <div id="coverCarousel" class="carousel slide" data-bs-ride="carousel"
                                        data-bs-interval="10000" style="height: 100%;">
                                        <div class="carousel-indicators">
                                            <button type="button" data-bs-target="#coverCarousel" data-bs-slide-to="0"
                                                class="active" aria-current="true" aria-label="Slide 1"></button>
                                            <button type="button" data-bs-target="#coverCarousel" data-bs-slide-to="1"
                                                aria-label="Slide 2"></button>
                                            <button type="button" data-bs-target="#coverCarousel" data-bs-slide-to="2"
                                                aria-label="Slide 3"></button>
                                            <button type="button" data-bs-target="#coverCarousel" data-bs-slide-to="3"
                                                aria-label="Slide 4"></button>
                                            <button type="button" data-bs-target="#coverCarousel" data-bs-slide-to="4"
                                                aria-label="Slide 5"></button>
                                            <button type="button" data-bs-target="#coverCarousel" data-bs-slide-to="5"
                                                aria-label="Slide 6"></button>
                                        </div>
                                        <div class="carousel-inner" style="height: 100%;">
                                            <div class="carousel-item active" style="height: 100%;">
                                                <img src="../assets/img/carousel/new-web.png"
                                                    class="d-block w-100 h-100" style="object-fit: cover;"
                                                    alt="Cover 1">
                                            </div>
                                            <div class="carousel-item" style="height: 100%;">
                                                <img src="../assets/img/carousel/tell2.png" class="d-block w-100 h-100"
                                                    style="object-fit: cover;" alt="Cover 2">
                                            </div>
                                            <div class="carousel-item" style="height: 100%;">
                                                <img src="../assets/img/carousel/gm.png" class="d-block w-100 h-100"
                                                    style="object-fit: cover;" alt="Cover 3">
                                            </div>
                                            <div class="carousel-item" style="height: 100%;">
                                                <img src="../assets/img/carousel/6.png" class="d-block w-100 h-100"
                                                    style="object-fit: cover;" alt="Cover 4">
                                            </div>
                                            <div class="carousel-item" style="height: 100%;">
                                                <img src="../assets/img/carousel/1.png" class="d-block w-100 h-100"
                                                    style="object-fit: cover;" alt="Cover 5">
                                            </div>
                                            <div class="carousel-item" style="height: 100%;">
                                                <img src="../assets/img/carousel/3.png" class="d-block w-100 h-100"
                                                    style="object-fit: cover;" alt="Cover 6">
                                            </div>
                                        </div>
                                        <button class="carousel-control-prev" type="button"
                                            data-bs-target="#coverCarousel" data-bs-slide="prev">
                                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                            <span class="visually-hidden">Previous</span>
                                        </button>
                                        <button class="carousel-control-next" type="button"
                                            data-bs-target="#coverCarousel" data-bs-slide="next">
                                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                            <span class="visually-hidden">Next</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="user-profile-header d-flex flex-column flex-lg-row text-sm-start text-center mb-8">
                                <div class="flex-shrink-0 mt-1 mx-sm-0 mx-auto">
                                    <img src="<?php echo !empty($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : '../assets/img/avatars/default_dp.jpg'; ?>"
                                        alt="user-avatar" class="d-block h-80 ms-0 ms-sm-6 rounded-5 profile-img"
                                        id="uploadedAvatar">
                                </div>
                                <div class="flex-grow-1 mt-3 mt-lg-5">
                                    <br>
                                    <div
                                        class="d-flex align-items-md-end align-items-sm-start align-items-center justify-content-md-between justify-content-start mx-5 flex-md-row flex-column gap-4 mt-2 ms-3">
                                        <div class="user-profile-info">
                                            <h2 class="fw-bold mb-1 user-name">
                                                <?php echo htmlspecialchars("$firstname $middlename $lastname"); ?>
                                            </h2>
                                            <p class="text-muted mb-2">
                                                <?php echo htmlspecialchars($position); ?>
                                                <span class="mx-1">•</span>
                                                <strong><?php echo htmlspecialchars($department); ?></strong>
                                            </p>
                                            <span class="pf-emp-badge">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                    <rect x="3" y="4" width="18" height="16" rx="2" />
                                                    <path d="M3 9h18M9 21V9" />
                                                </svg>
                                                Emp No: <?php echo htmlspecialchars($emp_id); ?>
                                            </span>
                                        </div>
                                        <div class="d-flex flex-column align-items-md-end align-items-center gap-2">
                                            <a href="https://cwd.com.ph/" target="_blank" class="pf-visit-btn">
                                                <i class="bx bx-globe"></i>
                                                <span>Visit Our New Website</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3 mt-lg-4 mt-3 ms-2 nav-align-top">
                            <ul class="nav nav-pills flex-column flex-sm-row mb-6 gap-sm-0 gap-2">
                                <li class="nav-item">
                                    <a class="nav-link" href="profile.php"><i
                                            class="icon-base bx bx-user icon-sm me-1_5"></i> Profile</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="profileTeams.php"><i
                                            class="icon-base bx bx-group icon-sm me-1_5"></i> Teams</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="tell.php"><i
                                            class="icon-base bx bx-phone icon-sm me-1_5"></i> Local Directory</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link active" href="comms.php"><i
                                            class="icon-base bx bx-sitemap icon-sm me-1_5"></i> Committees</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <?php

                // ==========================================
                // COMMITTEES DATA (fetched from database)
                // ==========================================

                $committees_data = [];

                // ---------------------------------------------------------
                // Build a lastname => [user records] lookup (for profile_picture / department)
                // Grouped as a list per lastname so we can disambiguate
                // duplicate lastnames using the guessed firstname below.
                // ---------------------------------------------------------
                $usersLookup = [];
                $userLookupQuery = $conn->prepare("
                    SELECT firstname, lastname, profile_picture, department
                    FROM users
                ");
                $userLookupQuery->execute();
                $userLookupResult = $userLookupQuery->get_result();

                while ($u = $userLookupResult->fetch_assoc()) {
                    $lnKey = strtolower(trim($u['lastname']));
                    if ($lnKey !== '') {
                        $usersLookup[$lnKey][] = $u;
                    }
                }
                $userLookupQuery->close();

                // Titles/suffixes to ignore when guessing the lastname
                // (e.g. "Mr. Exequiel A. Aguilar Jr." -> lastname is "Aguilar",
                // not "Jr."). Add more here if new ones show up.
                $titlesToStrip = ['mr', 'mrs', 'ms', 'dr', 'engr', 'atty', 'hon', 'prof'];
                $suffixesToStrip = ['jr', 'sr', 'ii', 'iii', 'iv', 'v'];

                // Manual overrides for the rare Member Name that still won't
                // parse correctly with the rule above. Key = exact value of
                // `Member Name` in the committee table, Value = [lastname, firstname].
                $lastnameOverrides = [
                    // 'Mr. Exequiel A. Aguilar Jr.' => ['Aguilar', 'Exequiel'],
                ];

                $commQuery = $conn->prepare("
                    SELECT `Committee`, `Member Name`, `Designation`
                    FROM `committee`
                    ORDER BY `Committee` ASC
                ");
                $commQuery->execute();
                $commResult = $commQuery->get_result();

                while ($row = $commResult->fetch_assoc()) {
                    $committeeName = $row['Committee'];

                    if (!isset($committees_data[$committeeName])) {
                        $committees_data[$committeeName] = [
                            'committee_name' => $committeeName,
                            'description' => '',
                            'members' => []
                        ];
                    }

                    // ---- Guess the lastname (and firstname) out of "Member Name" ----
                    $memberName = trim($row['Member Name']);

                    if (isset($lastnameOverrides[$memberName])) {
                        $lastnameGuess = $lastnameOverrides[$memberName][0];
                        $firstnameGuess = $lastnameOverrides[$memberName][1] ?? '';
                    } elseif (strpos($memberName, ',') !== false) {
                        // Format assumed: "Lastname, Firstname MI."
                        $nameParts = explode(',', $memberName, 2);
                        $lastnameGuess = trim($nameParts[0]);

                        $firstRemainder = trim($nameParts[1] ?? '');
                        $firstTokens = preg_split('/\s+/', $firstRemainder);
                        $firstnameGuess = $firstTokens[0] ?? '';
                    } else {
                        // Format assumed: "[Title] Firstname MI. Lastname [Suffix]"
                        $nameTokens = preg_split('/\s+/', $memberName);

                        // Drop leading title(s): "Mr.", "Dr.", etc.
                        while (count($nameTokens) > 1 && in_array(strtolower(rtrim($nameTokens[0], '.')), $titlesToStrip)) {
                            array_shift($nameTokens);
                        }

                        // Drop trailing suffix(es): "Jr.", "Sr.", "III", etc.
                        while (count($nameTokens) > 1 && in_array(strtolower(rtrim(end($nameTokens), '.')), $suffixesToStrip)) {
                            array_pop($nameTokens);
                        }

                        $firstnameGuess = $nameTokens[0] ?? '';
                        $lastnameGuess = end($nameTokens);
                    }

                    $lastnameKey = strtolower($lastnameGuess);
                    $firstnameKey = strtolower($firstnameGuess);

                    $memberProfilePic = '';
                    $memberDepartment = '';

                    if (isset($usersLookup[$lastnameKey])) {
                        $candidates = $usersLookup[$lastnameKey];
                        $matchedUser = null;

                        // Try to find a candidate whose firstname reasonably
                        // matches the guessed firstname (exact, or one is a
                        // prefix of the other - covers nicknames/initials).
                        foreach ($candidates as $candidate) {
                            $candidateFirstKey = strtolower(trim($candidate['firstname']));
                            if (
                                $candidateFirstKey === $firstnameKey ||
                                ($firstnameKey !== '' && strpos($candidateFirstKey, $firstnameKey) === 0) ||
                                ($candidateFirstKey !== '' && strpos($firstnameKey, $candidateFirstKey) === 0)
                            ) {
                                $matchedUser = $candidate;
                                break;
                            }
                        }

                        // Fallback: if nothing matched but there's exactly ONE
                        // user with this lastname, allow it ONLY if the first
                        // initial still lines up. This is deliberately strict -
                        // just sharing a lastname is NOT enough (e.g. "Geraldine
                        // Manguiat" must never pick up "Alpha Manguiat"'s data
                        // just because Alpha is the only Manguiat in `users`).
                        if (!$matchedUser && count($candidates) === 1) {
                            $onlyCandidate = $candidates[0];
                            $onlyFirstKey = strtolower(trim($onlyCandidate['firstname']));
                            if ($firstnameKey !== '' && $onlyFirstKey !== '' && $firstnameKey[0] === $onlyFirstKey[0]) {
                                $matchedUser = $onlyCandidate;
                            }
                        }

                        if ($matchedUser) {
                            $memberProfilePic = $matchedUser['profile_picture'];
                            $memberDepartment = $matchedUser['department'];
                        }
                    }

                    $committees_data[$committeeName]['members'][] = [
                        'name' => $memberName,
                        'designation' => $row['Designation'],
                        'profile_picture' => $memberProfilePic,
                        'department' => $memberDepartment
                    ];
                }
                $commQuery->close();

                // Re-index so foreach ($committees_data as $index => $comm) keeps working as before
                $committees_data = array_values($committees_data);

                ?>

                <!-- COMMITTEE MEMBERSHIPS UI CONTAINER -->
                <div class="dir-card">
                    <div class="card-body p-4 p-md-5">

                        <!-- SEARCH BAR -->
                        <div class="dir-search">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="7" />
                                <path d="M21 21l-4.3-4.3" />
                            </svg>
                            <input type="text" id="commSearch" placeholder="Search by member name or designation">
                        </div>


                        <!-- CONTROLS: COMMITTEE DROPDOWN FILTER -->
                        <div class="mb-4 comm-filter-card">
                            <label for="committeeSelect"
                                class="form-label fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                                <i class="bx bx-filter-alt text-primary fs-5"></i>
                                <span>Filter by Committee</span>
                            </label>

                            <div class="position-relative">
                                <select id="committeeSelect"
                                    class="form-select form-select-lg shadow-sm custom-comm-select">
                                    <option value="all">📁 All Committees</option>
                                    <?php foreach ($committees_data as $index => $comm): ?>
                                        <option value="comm-<?= $index ?>">
                                            👥 <?= htmlspecialchars($comm['committee_name']) ?>
                                            (<?= count($comm['members']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- COMMITTEE MEMBERSHIP CONTAINER -->
                        <div id="committeeContainer">
                            <?php foreach ($committees_data as $index => $comm): ?>
                                <div class="comm-group mb-5" data-comm="comm-<?= $index ?>">

                                    <!-- COMMITTEE HEADER & COUNT -->
                                    <div class="dept-header d-flex align-items-center flex-wrap gap-2">
                                        <h5 class="mb-0 d-flex align-items-center">
                                            <i class="bx bx-group fs-4 me-2" style="color: var(--tk-primary);"></i>
                                            <?= htmlspecialchars($comm['committee_name']) ?>
                                        </h5>
                                        <span class="dept-count ms-2">
                                            <?= count($comm['members']) ?> Members
                                        </span>
                                        <hr class="flex-grow-1 ms-3 text-muted opacity-25 d-none d-md-block">
                                    </div>



                                    <!-- MEMBER CARDS GRID -->
                                    <div class="row g-4 mb-4 justify-content-center">
                                        <?php
                                        $chairs = [];
                                        $regular_members = [];

                                        foreach ($comm['members'] as $member) {
                                            $desig = strtolower($member['designation']);
                                            $name = strtolower($member['name']);

                                            $is_chair = (
                                                strpos($desig, 'chairperson') !== false ||
                                                strpos($desig, 'chairman') !== false ||
                                                strpos($desig, 'editor-in-chief') !== false ||
                                                strpos($desig, 'final') !== false ||
                                                strpos($desig, 'focal') !== false ||
                                                strpos($desig, 'iso head') !== false
                                            );

                                            $is_vice = (
                                                strpos($desig, 'vice') !== false ||
                                                strpos($desig, 'co-') !== false ||
                                                strpos($desig, 'co ') !== false
                                            );


                                            $is_excluded_person = (strpos($name, 'geraldine') !== false);

                                            if ($is_chair && !$is_vice && !$is_excluded_person) {
                                                $chairs[] = $member;
                                            } else {
                                                $regular_members[] = $member;
                                            }
                                        }



                                        // 1. RENDER MAIN CHAIRPERSON / CHAIRMANSHIP POSITIONS (Mas Malaki & Nasa Gitna)
                                        foreach ($chairs as $member):
                                            ?>

                                            <div class="col-xl-4 col-lg-5 col-md-6 col-sm-10 member-card">
                                                <div class="card tel-card-ui border-0 shadow-sm h-100 overflow-hidden border-start border-primary"
                                                    style="border-left-width: 4px !important;">
                                                    <!-- Binawasan ang padding mula p-4 papuntang p-3 -->
                                                    <div
                                                        class="card-body p-3 d-flex flex-column align-items-center text-center">

                                                        <!-- Avatar (Ginawang 85px mula 95px) -->
                                                        <div class="tel-avatar-wrapper mb-2">
                                                            <img src="<?= !empty($member['profile_picture']) ? htmlspecialchars($member['profile_picture']) : '../assets/img/avatars/default_dp.jpg'; ?>"
                                                                class="rounded-circle border border-3 border-primary shadow-sm"
                                                                alt="<?= htmlspecialchars($member['name']); ?>"
                                                                style="width: 85px; height: 85px; object-fit: cover;">
                                                        </div>

                                                        <!-- Name (Ginawang fs-5 / H5-size para mas sakto lang) -->
                                                        <h5 class="fw-bold text-dark mb-1 text-truncate w-100"
                                                            title="<?= htmlspecialchars($member['name']); ?>">
                                                            <?= htmlspecialchars($member['name']); ?>
                                                        </h5>

                                                        <!-- Department -->
                                                        <?php if (!empty($member['department'])): ?>
                                                            <div class="small text-muted text-truncate w-100 mb-2"
                                                                title="<?= htmlspecialchars($member['department']); ?>">
                                                                <i class="bx bx-building-house me-1 align-middle"></i>
                                                                <span
                                                                    class="align-middle"><?= htmlspecialchars($member['department']); ?></span>
                                                            </div>
                                                        <?php endif; ?>

                                                        <!-- Spacer Push -->
                                                        <div class="mt-auto pt-2 w-100">
                                                            <!-- Designation Badge (Mas compact na padding) -->
                                                            <span
                                                                class="badge rounded-pill bg-primary text-white fs-6 fw-semibold px-3 py-2 w-100 d-inline-flex align-items-center justify-content-center text-wrap lh-sm shadow-sm"
                                                                title="<?= htmlspecialchars($member['designation']); ?>">
                                                                <i class="fa-solid fa-user-tie me-2"></i>
                                                                <span><?= htmlspecialchars($member['designation']); ?></span>
                                                            </span>
                                                        </div>

                                                    </div>
                                                </div>
                                            </div>

                                        <?php endforeach; ?>



                                        <!-- Break Line para hiwalay ang Row ng Chair sa ibang Members -->
                                        <?php if (!empty($chairs) && !empty($regular_members)): ?>
                                            <div class="w-100"></div>
                                        <?php endif; ?>

                                        <!-- 2. RENDER THE REST OF THE MEMBERS (Kasama na rito ang Vice Chairman) -->
                                        <?php foreach ($regular_members as $member): ?>
                                            <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 member-card">
                                                <div class="card tel-card-ui border-0 shadow-sm h-100 overflow-hidden">
                                                    <div
                                                        class="card-body p-4 d-flex flex-column align-items-center text-center">

                                                        <!-- Avatar -->
                                                        <div class="tel-avatar-wrapper mb-3">
                                                            <img src="<?= !empty($member['profile_picture']) ? htmlspecialchars($member['profile_picture']) : '../assets/img/avatars/default_dp.jpg'; ?>"
                                                                class="rounded-circle border border-3 border-white shadow-sm"
                                                                alt="<?= htmlspecialchars($member['name']); ?>"
                                                                style="width: 78px; height: 78px; object-fit: cover;">
                                                        </div>

                                                        <!-- Name -->
                                                        <h5 class="fw-bold text-dark mb-1 text-truncate w-100"
                                                            title="<?= htmlspecialchars($member['name']); ?>">
                                                            <?= htmlspecialchars($member['name']); ?>
                                                        </h5>

                                                        <!-- Department -->
                                                        <?php if (!empty($member['department'])): ?>
                                                            <div class="small text-muted text-truncate w-100 mb-3"
                                                                title="<?= htmlspecialchars($member['department']); ?>">
                                                                <i class="bx bx-building-house me-1 align-middle"></i>
                                                                <span
                                                                    class="align-middle"><?= htmlspecialchars($member['department']); ?></span>
                                                            </div>
                                                        <?php endif; ?>

                                                        <!-- Spacer Push -->
                                                        <div class="mt-auto pt-2 w-100">
                                                            <!-- Designation -->
                                                            <span
                                                                class="badge rounded-pill bg-label-primary text-primary fw-semibold px-3 py-2 w-100 d-inline-flex align-items-center justify-content-center text-wrap lh-sm"
                                                                title="<?= htmlspecialchars($member['designation']); ?>">
                                                                <i
                                                                    class="fa-solid fa-user-check text-[#2E96EC] text-xs me-1 flex-shrink-0"></i>
                                                                <span><?= htmlspecialchars($member['designation']); ?></span>
                                                            </span>
                                                        </div>

                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>

                                        <?php if (!empty($comm['description'])): ?>
                                            <p class="text-muted small mb-2 ms-1">
                                                <i class="bx bx-info-circle me-1"></i>
                                                <?= htmlspecialchars($comm['description']) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>

                                </div>
                            <?php endforeach; ?>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT FOR SEARCH, FILTERING, AND COPY -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const searchInput = document.getElementById("commSearch");
            const filterButtons = document.querySelectorAll("#committeeFilters button");

            // Live Search Filter
            searchInput.addEventListener("keyup", function () {
                let value = this.value.toLowerCase().trim();

                document.querySelectorAll(".member-card").forEach(card => {
                    let matches = card.innerText.toLowerCase().includes(value);
                    card.style.setProperty("display", matches ? "" : "none", "important");
                });

                // Hide committee section if no member matches
                document.querySelectorAll(".comm-group").forEach(group => {
                    let visibleCards = group.querySelectorAll(".member-card:not([style*='display: none'])").length;
                    group.style.display = visibleCards > 0 ? "" : "none";
                });
            });

            // Committee Select Dropdown Filter
            const committeeSelect = document.getElementById("committeeSelect");

            if (committeeSelect) {
                committeeSelect.addEventListener("change", function () {
                    const selectedFilter = this.value;

                    // Reset search input kapag nagpalit ng filter (optional pero magandang UX)
                    if (searchInput) searchInput.value = "";

                    document.querySelectorAll(".comm-group").forEach(group => {
                        // I-reset ang visibility ng member cards sa napiling committee
                        group.querySelectorAll(".member-card").forEach(card => card.style.display = "");

                        if (selectedFilter === "all" || group.getAttribute("data-comm") === selectedFilter) {
                            group.style.display = "";
                        } else {
                            group.style.display = "none";
                        }
                    });
                });
            }
        });

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert("Copied name: " + text);
            }).catch(err => {
                console.error("Could not copy text: ", err);
            });
        }
    </script>

    <!-- VENDOR SCRIPTS -->
    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/vendor/libs/apex-charts/apexcharts.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/dashboards-analytics.js"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>

</body>

</html>