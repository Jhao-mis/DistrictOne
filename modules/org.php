<?php
include '../db.php';
require '../vendor/autoload.php';
require 'login_verification.php';

/* ==========================================================================
   API ENDPOINT HANDLING (Inlined from api.php)
   ========================================================================== */
if (isset($_GET['action']) && $_GET['action'] === 'get_details') {
    header('Content-Type: application/json');
    $code = trim($_GET['code'] ?? '');

    if (empty($code)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing code parameter.']);
        exit;
    }

    try {
        $searchCode = strtolower($code);

        // Helper function para mag-format ng position card object
        function formatPositionCard($p)
        {
            return [
                'id' => (int) $p['id'],
                'position_title' => $p['plantilla_position'] ?? 'Position',
                'employee_name' => (!empty($p['personnel_name']) && strtolower(trim($p['personnel_name'])) !== 'vacant') ? $p['personnel_name'] : 'Vacant',
                'salary_grade' => $p['salary_grade'] ?? 'N/A',
                'is_head' => isset($p['is_head']) ? (int) $p['is_head'] : 0,
                'is_secretary' => isset($p['is_secretary']) ? (int) $p['is_secretary'] : 0
            ];
        }

        // Gagamitin ang $pdo na galing sa db.php
        $dbConn = isset($pdo) ? $pdo : null;
        if (!$dbConn) {
            echo json_encode(['status' => 'error', 'message' => 'PDO Connection not available.']);
            exit;
        }

        // -------------------------------------------------------------
        // 1. DEPARTMENT LEVEL QUERY
        // -------------------------------------------------------------
        $stmtDept = $dbConn->prepare("
            SELECT DISTINCT department, dept_code 
            FROM orgchart 
            WHERE LOWER(dept_code) = :code OR LOWER(department) LIKE :likeCode 
            LIMIT 1
        ");
        $stmtDept->execute([':code' => $searchCode, ':likeCode' => "%$searchCode%"]);
        $deptRow = $stmtDept->fetch(PDO::FETCH_ASSOC);

        if ($deptRow) {
            $deptName = $deptRow['department'];
            $deptCode = $deptRow['dept_code'];

            // Manager / Head
            $stmtM = $dbConn->prepare("
                SELECT * FROM orgchart 
                WHERE department = :dept 
                  AND (division IS NULL OR division = '') 
                  AND (section IS NULL OR section = '') 
                  AND is_head = 1 
                ORDER BY CAST(salary_grade AS UNSIGNED) DESC LIMIT 1
            ");
            $stmtM->execute([':dept' => $deptName]);
            $manager = $stmtM->fetch(PDO::FETCH_ASSOC);

            // Secretary
            $stmtS = $dbConn->prepare("
                SELECT * FROM orgchart 
                WHERE department = :dept 
                  AND (division IS NULL OR division = '') 
                  AND (section IS NULL OR section = '') 
                  AND is_secretary = 1 
                LIMIT 1
            ");
            $stmtS->execute([':dept' => $deptName]);
            $secretary = $stmtS->fetch(PDO::FETCH_ASSOC);

            // Direct Positions
            $stmtDirect = $dbConn->prepare("
                SELECT * FROM orgchart 
                WHERE department = :dept 
                  AND (division IS NULL OR division = '') 
                  AND (section IS NULL OR section = '') 
                ORDER BY CAST(salary_grade AS UNSIGNED) DESC
            ");
            $stmtDirect->execute([':dept' => $deptName]);
            $rawDirect = $stmtDirect->fetchAll(PDO::FETCH_ASSOC);

            $directPositions = [];
            foreach ($rawDirect as $p) {
                if (($manager && $p['id'] == $manager['id']) || ($secretary && $p['id'] == $secretary['id'])) {
                    continue;
                }
                $directPositions[] = formatPositionCard($p);
            }

            // Divisions
            $stmtDivs = $dbConn->prepare("
                SELECT DISTINCT division, div_code 
                FROM orgchart 
                WHERE department = :dept AND division IS NOT NULL AND division != ''
            ");
            $stmtDivs->execute([':dept' => $deptName]);
            $divisions = $stmtDivs->fetchAll(PDO::FETCH_ASSOC);

            $divisionsData = [];
            foreach ($divisions as $div) {
                $divName = $div['division'];
                $divCode = $div['div_code'];

                $stmtPos = $dbConn->prepare("
                    SELECT * FROM orgchart 
                    WHERE department = :dept AND division = :div 
                    ORDER BY CAST(salary_grade AS UNSIGNED) DESC
                ");
                $stmtPos->execute([':dept' => $deptName, ':div' => $divName]);
                $rawPos = $stmtPos->fetchAll(PDO::FETCH_ASSOC);

                $positions = [];
                foreach ($rawPos as $p) {
                    $positions[] = formatPositionCard($p);
                }

                $divisionsData[] = [
                    'id' => $divCode ?? $divName,
                    'name' => $divName,
                    'code' => $divCode ?? $divName,
                    'positions' => $positions
                ];
            }

            // Sections
            $stmtSecs = $dbConn->prepare("
                SELECT DISTINCT section, div_code 
                FROM orgchart 
                WHERE department = :dept 
                  AND (division IS NULL OR division = '') 
                  AND section IS NOT NULL AND section != ''
            ");
            $stmtSecs->execute([':dept' => $deptName]);
            $sections = $stmtSecs->fetchAll(PDO::FETCH_ASSOC);

            $sectionsData = [];
            foreach ($sections as $sec) {
                $secName = $sec['section'];
                $secCode = $sec['div_code'];

                $stmtPos = $dbConn->prepare("
                    SELECT * FROM orgchart 
                    WHERE department = :dept AND section = :sec 
                    ORDER BY CAST(salary_grade AS UNSIGNED) DESC
                ");
                $stmtPos->execute([':dept' => $deptName, ':sec' => $secName]);
                $rawPos = $stmtPos->fetchAll(PDO::FETCH_ASSOC);

                $positions = [];
                foreach ($rawPos as $p) {
                    $positions[] = formatPositionCard($p);
                }

                $sectionsData[] = [
                    'id' => $secCode ?? $secName,
                    'name' => $secName,
                    'code' => $secCode ?? $secName,
                    'positions' => $positions
                ];
            }

            echo json_encode([
                'status' => 'success',
                'title' => $deptName,
                'dept_code' => $deptCode,
                'type' => 'DEPARTMENT',
                'manager' => $manager ? formatPositionCard($manager) : null,
                'secretary' => $secretary ? formatPositionCard($secretary) : null,
                'divisions' => $divisionsData,
                'sections' => $sectionsData,
                'positions' => $directPositions
            ], JSON_PRETTY_PRINT);
            exit;
        }

        // -------------------------------------------------------------
        // 2. DIVISION LEVEL QUERY
        // -------------------------------------------------------------
        $stmtDiv = $dbConn->prepare("
            SELECT DISTINCT division, div_code, department 
            FROM orgchart 
            WHERE (LOWER(div_code) = :code OR LOWER(division) LIKE :likeCode) 
              AND division IS NOT NULL AND division != ''
            LIMIT 1
        ");
        $stmtDiv->execute([':code' => $searchCode, ':likeCode' => "%$searchCode%"]);
        $divRow = $stmtDiv->fetch(PDO::FETCH_ASSOC);

        if ($divRow) {
            $divName = $divRow['division'];
            $divCode = $divRow['div_code'];

            $stmtM = $dbConn->prepare("
                SELECT * FROM orgchart 
                WHERE division = :div AND is_head = 1 
                ORDER BY CAST(salary_grade AS UNSIGNED) DESC LIMIT 1
            ");
            $stmtM->execute([':div' => $divName]);
            $manager = $stmtM->fetch(PDO::FETCH_ASSOC);

            $stmtS = $dbConn->prepare("
                SELECT * FROM orgchart 
                WHERE division = :div AND is_secretary = 1 
                LIMIT 1
            ");
            $stmtS->execute([':div' => $divName]);
            $secretary = $stmtS->fetch(PDO::FETCH_ASSOC);

            $stmtPos = $dbConn->prepare("
                SELECT * FROM orgchart 
                WHERE division = :div 
                ORDER BY CAST(salary_grade AS UNSIGNED) DESC
            ");
            $stmtPos->execute([':div' => $divName]);
            $rawPos = $stmtPos->fetchAll(PDO::FETCH_ASSOC);

            $positions = [];
            foreach ($rawPos as $p) {
                if (($manager && $p['id'] == $manager['id']) || ($secretary && $p['id'] == $secretary['id'])) {
                    continue;
                }
                $positions[] = formatPositionCard($p);
            }

            echo json_encode([
                'status' => 'success',
                'title' => $divName,
                'div_code' => $divCode,
                'type' => 'DIVISION',
                'manager' => $manager ? formatPositionCard($manager) : null,
                'secretary' => $secretary ? formatPositionCard($secretary) : null,
                'positions' => $positions
            ], JSON_PRETTY_PRINT);
            exit;
        }

        // -------------------------------------------------------------
        // 3. SECTION LEVEL QUERY
        // -------------------------------------------------------------
        $stmtSec = $dbConn->prepare("
            SELECT DISTINCT section, div_code, department 
            FROM orgchart 
            WHERE (LOWER(div_code) = :code OR LOWER(section) LIKE :likeCode) 
              AND section IS NOT NULL AND section != ''
            LIMIT 1
        ");
        $stmtSec->execute([':code' => $searchCode, ':likeCode' => "%$searchCode%"]);
        $secRow = $stmtSec->fetch(PDO::FETCH_ASSOC);

        if ($secRow) {
            $secName = $secRow['section'];
            $secCode = $secRow['div_code'];

            $stmtM = $dbConn->prepare("
                SELECT * FROM orgchart 
                WHERE section = :sec AND is_head = 1 
                ORDER BY CAST(salary_grade AS UNSIGNED) DESC LIMIT 1
            ");
            $stmtM->execute([':sec' => $secName]);
            $manager = $stmtM->fetch(PDO::FETCH_ASSOC);

            if (!$manager) {
                $stmtFallback = $dbConn->prepare("
                    SELECT * FROM orgchart 
                    WHERE section = :sec 
                    ORDER BY CAST(salary_grade AS UNSIGNED) DESC LIMIT 1
                ");
                $stmtFallback->execute([':sec' => $secName]);
                $manager = $stmtFallback->fetch(PDO::FETCH_ASSOC);
            }

            $stmtS = $dbConn->prepare("
                SELECT * FROM orgchart 
                WHERE section = :sec AND is_secretary = 1 
                LIMIT 1
            ");
            $stmtS->execute([':sec' => $secName]);
            $secretary = $stmtS->fetch(PDO::FETCH_ASSOC);

            $stmtPos = $dbConn->prepare("
                SELECT * FROM orgchart 
                WHERE section = :sec 
                ORDER BY CAST(salary_grade AS UNSIGNED) DESC
            ");
            $stmtPos->execute([':sec' => $secName]);
            $rawPos = $stmtPos->fetchAll(PDO::FETCH_ASSOC);

            $positions = [];
            foreach ($rawPos as $p) {
                if (($manager && $p['id'] == $manager['id']) || ($secretary && $p['id'] == $secretary['id'])) {
                    continue;
                }
                $positions[] = formatPositionCard($p);
            }

            echo json_encode([
                'status' => 'success',
                'title' => $secName,
                'sec_code' => $secCode,
                'type' => 'SECTION',
                'manager' => $manager ? formatPositionCard($manager) : null,
                'secretary' => $secretary ? formatPositionCard($secretary) : null,
                'positions' => $positions
            ], JSON_PRETTY_PRINT);
            exit;
        }

        echo json_encode(['status' => 'error', 'message' => 'Hindi mahanap ang requested code sa database.']);
        exit;

    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

/* ==========================================================================
   REGULAR PAGE CONTROLLER LOGIC
   ========================================================================== */
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

    <title>Organizational Chart</title>

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
            border-radius: 999px;
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

        .tracking-wider {
            letter-spacing: 0.05em;
        }

        /* Custom Org Chart Styling */
        .org-container {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }

        .org-card-exec {
            background: #ffffff;
            border: 2px solid #e2e8f0 !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .org-card-exec:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -10px rgba(13, 110, 253, 0.25) !important;
            border-color: #0d6efd !important;
        }

        /* Connecting Line Effect for Executive Hierarchy */
        .exec-connector-line {
            width: 2px;
            height: 18px;
            background: #cbd5e1;
            margin: 0 auto;
        }

        /* Department Cards Enhancement */
        .dept-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-top: 4px solid #0d6efd;
            transition: all 0.3s ease;
        }

        .dept-card:hover {
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.06) !important;
        }

        /* Custom Interactive Buttons */
        .btn-dept-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            border: none;
            color: #fff;
            transition: all 0.2s ease;
        }

        .btn-dept-header:hover {
            background: linear-gradient(135deg, #0b5ed7 0%, #084298 100%);
            color: #fff;
            transform: scale(1.01);
        }

        .btn-division-item {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #334155;
            transition: all 0.2s ease;
        }

        .btn-division-item:hover {
            background-color: #f1f5f9;
            border-color: #cbd5e1;
            color: #0d6efd;
            transform: translateX(4px);
        }

        .btn-division-item .icon-box {
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            background: #e0e7ff;
            color: #4f46e5;
            transition: all 0.2s ease;
        }

        .btn-division-item:hover .icon-box {
            background: #0d6efd;
            color: #ffffff;
        }

        .badge-avatar {
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Enhanced Modal Styling */
        .modal-content {
            border-radius: 20px !important;
            overflow: hidden;
        }

        .modal-header-custom {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: #ffffff;
        }

        .modal-card-lead {
            background: #ffffff;
            border-left: 4px solid #0d6efd !important;
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.08);
        }

        .modal-card-sec {
            background: #ffffff;
            border-left: 4px solid #6c757d !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .avatar-icon-box {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .badge-sg {
            background-color: #e2e8f0;
            color: #334155;
            font-weight: 700;
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 6px;
        }

        .pos-card-item {
            transition: all 0.2s ease;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .pos-card-item:hover {
            background: #ffffff;
            border-color: #cbd5e1;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }

        /* ===== Org Chart View (Balkan) ===== */
        #balkan-tree-container,
        #modalTreeContainer {
            width: 100%;
            background-color: #f8fafc;
            background-image: radial-gradient(#dbe3ee 1px, transparent 1px);
            background-size: 22px 22px;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            overflow: hidden;
            position: relative;
        }

        #balkan-tree-container {
            height: min(600px, 70vh);
        }

        #modalTreeContainer {
            height: min(560px, 62vh);
        }

        #balkan-tree-container svg,
        #modalTreeContainer svg {
            background: transparent !important;
        }

        #balkan-tree-container [data-n-id] {
            cursor: pointer;
        }

        /* Same as the website: departments and secretaries are not clickable */
        #balkan-tree-container [data-n-id="admin"],
        #balkan-tree-container [data-n-id="finance"],
        #balkan-tree-container [data-n-id="commercial"],
        #balkan-tree-container [data-n-id="tsd"],
        #balkan-tree-container [data-n-id="ops"],
        #balkan-tree-container [data-n-id$="_sec"] {
            cursor: default;
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
                                    <a class="nav-link" href="comms.php"><i
                                            class="icon-base bx bx-sitemap icon-sm me-1_5"></i> Committees</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link active" href="org.php"><i
                                            class="icon-base bx bx-group diversity icon-sm me-1_5"></i> Organizational
                                        Chart</a>
                                </li>

                            </ul>
                        </div>
                    </div>
                </div>

                <!-- ORG CHART UI CONTAINER -->
                <div class="dir-card">
                    <div class="card-body p-3 p-md-5 ">

                        <div class="container my-2">
                            <div class="rounded-4 p-4 p-md-5 bg-white ">

                                <!-- Title Header -->
                                <div class="text-center mb-5">
                                    <span
                                        class="badge bg-primary-subtle text-primary fw-bold text-uppercase px-3 py-2 rounded-pill mb-2">Structure
                                        & Leadership</span>
                                    <h2 class="fw-bold text-dark mb-1">Organizational Chart</h2>
                                    <p class="text-muted small">Select any department or division to view full staffing
                                        and headcount</p>
                                </div>

                                <!-- Organizational Chart -->
                                <div id="balkan-tree-container"></div>



                                <!-- External Website Redirect Link Section -->
                                <div class="mt-4 pt-2 text-center border-top">
                                    <div class="p-4 rounded-4 bg-light d-inline-block text-center shadow-xs"
                                        style="max-width: 500px; width: 100%;">
                                        <p class="text-muted small mb-3 fw-semibold">Looking for the public
                                            Organizational Chart?</p>
                                        <a href="https://cwd.com.ph/about_us" target="_blank" rel="noopener noreferrer"
                                            class="btn btn-outline-primary rounded-pill px-4 py-2 fw-bold d-inline-flex align-items-center justify-content-center gap-2 shadow-sm">
                                            <i class="bx bx-globe fs-5"></i>
                                            <span>View Website</span>
                                            <i class="bx bx-link fs-6 ms-1"></i>
                                        </a>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- Dynamic Org Chart Modal -->
                        <div id="orgChartModal" class="modal fade" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
                                <div class="modal-content border-0 shadow-lg">

                                    <!-- Modal Header (Inalisan ng Left Icon) -->
                                    <div class="modal-header modal-header-custom px-4 py-3 align-items-center">
                                        <div>
                                            <small id="modalCategory"
                                                class="text-uppercase fw-bold text-white-50 d-block"
                                                style="font-size: 10px; letter-spacing: 1px;">Department View</small>
                                            <h5 id="modalTitle" class="modal-title fw-bold text-white m-0">Title</h5>
                                        </div>
                                        <button type="button" class="btn-close btn-close-white"
                                            onclick="closeOrgModal()" aria-label="Close"></button>
                                    </div>

                                    <!-- Dynamic Modal Body -->
                                    <div id="modalBody" class="modal-body p-3 p-md-4 bg-light">
                                        <!-- Dito mai-inject ang Javascript output -->
                                    </div>

                                    <!-- Modal Footer -->
                                    <div class="modal-footer bg-white border-top px-4 py-2">
                                        <small class="text-muted me-auto d-none d-md-inline">Scroll to zoom &middot;
                                            Drag to pan</small>
                                        <button type="button" class="btn btn-sm btn-secondary px-4 rounded-3 fw-bold"
                                            onclick="closeOrgModal()">Close</button>
                                    </div>

                                </div>
                            </div>
                        </div>

                    </div>

                </div>

            </div>

        </div>
    </div>
    </div>

    <!-- JAVASCRIPT FOR ORG CHART (Balkan OrgChart + modal) -->
    <script src="https://balkan.app/js/OrgChart.js"></script>
    <script>
        let modalOrgChartInstance = null;
        let mainOrgChartInstance = null;
        let pendingModalNodes = null;

        const BALKAN_READY = (typeof OrgChart !== 'undefined');
        const MAIN_CHART_START_SCALE = 1.0;   // 1.0 = real card size; lower = farther, higher = closer

        // Chart node id -> code used by the existing DistrictOne modal / API
        const ORG_CODE_MAP = {
            bod: 'BOD', ogm: 'OGM', mis: 'MIS',
            admin: 'ADMIN', hrd: 'HRD', pmmd: 'PMMD', gsd: 'GSD',
            finance: 'FINANCE', budget: 'BUDGET', accounting: 'gad',
            commercial: 'COMMERCIAL', bmrd: 'BILLING', cad: 'cad', ccd: 'CUSTOMER_CARE',
            tsd: 'TECHNICAL', eamd: 'eamd', pamd: 'PIPELINE',
            ops: 'OPERATIONS', production: 'PRODUCTION'
        };

        // ==========================================
        // CARD TEMPLATES (two-tone, color-coded by level)
        // ==========================================
        function buildCardTemplate(c) {
            const t = Object.assign({}, OrgChart.templates.ana);
            t.size = [290, 105];

            t.node = `
                <filter id="card-shadow" x="-10%" y="-10%" width="120%" height="120%">
                    <feDropShadow dx="0" dy="6" stdDeviation="6" flood-color="#0f172a" flood-opacity="0.12"/>
                    <feDropShadow dx="0" dy="1" stdDeviation="2" flood-color="#0f172a" flood-opacity="0.08"/>
                </filter>
                <rect x="0" y="0" width="290" height="105" fill="${c.body}" rx="16" ry="16" filter="url(#card-shadow)" stroke="${c.stroke}" stroke-width="1.5"></rect>
                <path d="M 0 16 A 16 16 0 0 1 16 0 L 274 0 A 16 16 0 0 1 290 16 L 290 50 L 0 50 Z" fill="#f8fafc" />
                <line x1="0" y1="50" x2="290" y2="50" stroke="#e2e8f0" stroke-width="1" />
            `;
            t.field_0 = `
                <foreignObject x="12" y="6" width="266" height="40">
                    <div xmlns="http://www.w3.org/1999/xhtml" style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; text-align: center; font-size: 10.5px; font-weight: 800; color: #0f172a; font-family: 'Inter', system-ui, sans-serif; text-transform: uppercase; line-height: 1.2; overflow: hidden; word-break: break-word; text-rendering: geometricPrecision; -webkit-font-smoothing: antialiased;">
                        {val}
                    </div>
                </foreignObject>
            `;
            t.field_1 = `<text style="font-size: 11.5px; font-weight: 700; font-family: 'Inter', system-ui, sans-serif;" fill="#ffffff" x="145" y="72" text-anchor="middle">{val}</text>`;
            t.field_2 = `<text style="font-size: 10px; font-weight: 600; font-family: 'Inter', system-ui, sans-serif;" fill="${c.sub}" x="145" y="90" text-anchor="middle">{val}</text>`;
            t.img_0 = '';

            if (typeof OrgChart.templates.ana.link === 'string') {
                t.link = OrgChart.templates.ana.link
                    .replace(/stroke="[^"]*"/, 'stroke="#94a3b8"')
                    .replace(/stroke-width="[^"]*"/, 'stroke-width="2px"');
            }
            return t;
        }

        if (BALKAN_READY) {
            OrgChart.SEARCH_PLACEHOLDER = "Search for department, division or plantilla...";
            OrgChart.templates.twoToneTemplate = buildCardTemplate({ body: "#1e3a8a", stroke: "#3b82f6", sub: "#bfdbfe" });
            OrgChart.templates.topTemplate = buildCardTemplate({ body: "#0f2a5c", stroke: "#38bdf8", sub: "#bae6fd" }); // BOD / OGM
            OrgChart.templates.deptTemplate = buildCardTemplate({ body: "#1e3a8a", stroke: "#3b82f6", sub: "#bfdbfe" }); // Departments
            OrgChart.templates.divisionTemplate = buildCardTemplate({ body: "#2563eb", stroke: "#93c5fd", sub: "#dbeafe" }); // Divisions
            OrgChart.templates.staffTemplate = buildCardTemplate({ body: "#475569", stroke: "#94a3b8", sub: "#e2e8f0" }); // Secretaries
            OrgChart.templates.sectionTemplate = buildCardTemplate({ body: "#0f766e", stroke: "#2dd4bf", sub: "#ccfbf1" }); // MIS section
        }

        // ==========================================
        // HELPERS
        // ==========================================
        // Only cosmetic border changes. Do NOT touch placeholder/padding/font-size:
        // Balkan draws its own placeholder layer behind the input (text would overlap).
        function styleChartSearch(container) {
            if (!container) return;
            const searchInput = container.querySelector('input');
            if (searchInput) {
                searchInput.style.borderRadius = "20px";
                searchInput.style.border = "1px solid #cbd5e1";
                searchInput.style.outline = "none";
            }
        }

        // ==========================================
        // CHART HINT (Injected dynamically below search bar)
        // ==========================================
        function addChartHint(container) {
            if (!container) return;

            let hint = container.querySelector('#orgchart-hint');
            if (!hint) {
                hint = document.createElement('p');
                hint.id = 'orgchart-hint';
                hint.style.cssText = "position:absolute;margin:0;font-size:12px;line-height:1.5;color:#64748b;pointer-events:none;z-index:1;font-family:'Inter', system-ui, sans-serif;";
                hint.innerHTML = 'Click a card for details &middot; Scroll to zoom &middot; Drag to pan';
                container.appendChild(hint);
            }

            const input = container.querySelector('input');

            const position = function () {
                const cr = container.getBoundingClientRect();
                const ir = input ? input.getBoundingClientRect() : null;
                if (ir && ir.width > 0) {
                    hint.style.top = (ir.bottom - cr.top + 8) + 'px';
                    hint.style.left = (ir.left - cr.left + 12) + 'px';
                    hint.style.right = 'auto';
                    hint.style.width = Math.max(ir.width - 12, 220) + 'px';
                } else {
                    hint.style.top = '70px';
                    hint.style.right = '16px';
                    hint.style.left = 'auto';
                    hint.style.width = '320px';
                }
            };
            position();

            // Hide hint kapag nag-type ang user sa search input
            if (input && !input.dataset.hintBound) {
                input.dataset.hintBound = '1';
                input.addEventListener('input', function () {
                    hint.style.display = input.value ? 'none' : 'block';
                });
            }

            if (!container.dataset.hintResizeBound) {
                container.dataset.hintResizeBound = '1';
                window.addEventListener('resize', function () { setTimeout(position, 100); });
            }
        }

        // Close-up on the BOD + OGM cards (Balkan's center() has no scale option -> set viewBox).
        // Retries a few times because the layout may not be ready right after init.
        function focusOnTopNodes(chart, container, attempt) {
            attempt = attempt || 0;
            if (!chart || !container) return;
            try {
                const cw = container.clientWidth;
                const ch = container.clientHeight;
                const bod = chart.getNode ? chart.getNode("bod") : null;
                const ready = cw && ch && bod && typeof bod.x === 'number' && typeof bod.y === 'number';

                if (!ready) {
                    if (attempt < 6) {
                        setTimeout(() => focusOnTopNodes(chart, container, attempt + 1), 150);
                    } else {
                        chart.fit();
                        chart.center("bod");
                    }
                    return;
                }
                const vw = cw / MAIN_CHART_START_SCALE;
                const vh = ch / MAIN_CHART_START_SCALE;
                const x = bod.x + (bod.w || 290) / 2 - vw / 2;
                const y = bod.y - 40;
                chart.setViewBox([x, y, vw, vh]);
            } catch (e) {
                console.error("focusOnTopNodes error:", e);
                try { chart.fit(); chart.center("bod"); } catch (_) { }
            }
        }

        // ==========================================
        // MAIN ORG CHART (Chart View tab)
        // ==========================================
        function renderBalkanOrgChart() {
            const container = document.getElementById("balkan-tree-container");
            if (!container) return;

            if (!BALKAN_READY) {
                container.innerHTML = '<div class="d-flex align-items-center justify-content-center h-100 text-muted small p-4 text-center">The chart library could not be loaded. Please check your connection and refresh the page, or use the Cards View.</div>';
                return;
            }

            if (mainOrgChartInstance) {
                setTimeout(() => {
                    focusOnTopNodes(mainOrgChartInstance, container);
                    styleChartSearch(container);
                    addChartHint(container);
                }, 50);
                return;
            }

            const orgChartData = [
        { id: "bod", pid: null, tags: ["top"], name: "OFFICE OF THE BOARD OF DIRECTORS", title: "Board of Directors", sg: "" },
        { id: "ogm", pid: "bod", tags: ["top"], name: "OFFICE OF THE GENERAL MANAGER", title: "Exequiel A. Aguilar, Jr.", sg: "SG 26" },
        { id: "mis", pid: "ogm", tags: ["assistant", "section"], name: "Management Info Services", title: "Engr. Jonathan Dave Fajarda", sg: "SG 19" },
        
        // Administrative Department & Divisions
        { id: "admin", pid: "ogm", tags: ["dept"], name: "ADMINISTRATIVE DEPARTMENT", title: "Edwin L. Cartago", sg: "SG 26" },
        { id: "admin_sec", pid: "admin", tags: ["assistant", "staff"], name: "Janine S. Bercasio", title: "Secretary B", sg: "SG 7" },
        { id: "hrd", pid: "admin", tags: ["division"], name: "HUMAN RESOURCE DIVISION", title: "Elenita V. Panganiban", sg: "SG 24" },
        { id: "pmmd", pid: "admin", tags: ["division"], name: "PROPERTY & MATERIALS MGT.", title: "Rolando M. Pizzara (OIC)", sg: "SG 24" },
        { id: "gsd", pid: "admin", tags: ["division"], name: "GENERAL SERVICES DIVISION", title: "Emmanuel T. Salvador", sg: "SG 24" },
        
        // Finance Department & Divisions
        { id: "finance", pid: "ogm", tags: ["dept"], name: "FINANCE DEPARTMENT", title: "Mercedes A. Carreon (OIC)", sg: "SG 26" },
        { id: "finance_sec", pid: "finance", tags: ["assistant", "staff"], name: "Vacant", title: "Secretary B", sg: "SG 7" },
        { id: "budget", pid: "finance", tags: ["division"], name: "BUDGET DIVISION", title: "Mercedes A. Carreon", sg: "SG 24" },
        { id: "accounting", pid: "finance", tags: ["division"], name: "GENERAL ACCOUNTING DIV.", title: "Junsy Y. Nieron, CPA (OIC)", sg: "SG 24" },
        
        // Commercial Department & Divisions
        { id: "commercial", pid: "ogm", tags: ["dept"], name: "COMMERCIAL DEPARTMENT", title: "Ma. Carmela M. Elepaño", sg: "SG 26" },
        { id: "commercial_sec", pid: "commercial", tags: ["assistant", "staff"], name: "Verra Mae Mariano", title: "Secretary B", sg: "SG 7" },
        { id: "bmrd", pid: "commercial", tags: ["division"], name: "BILLING & METER READING", title: "Basilisa Gillera (OIC)", sg: "SG 24" },
        { id: "cad", pid: "commercial", tags: ["division"], name: "CUSTOMER ACCOUNTS DIV.", title: "Henry B. Junio", sg: "SG 24" },
        { id: "ccd", pid: "commercial", tags: ["division"], name: "CUSTOMER CARE DIVISION", title: "Ronnie G. Sierva", sg: "SG 24" },
        
        // Technical Services Department & Divisions
        { id: "tsd", pid: "ogm", tags: ["dept"], name: "TECHNICAL SERVICES DEPT.", title: "Engr. Ranely S. Cartago", sg: "SG 26" },
        { id: "tsd_sec", pid: "tsd", tags: ["assistant", "staff"], name: "Joana Leana May V. Villanueva", title: "Secretary B", sg: "SG 7" },
        { id: "eamd", pid: "tsd", tags: ["division"], name: "ENGINEERING & MAINTENANCE", title: "Engr. Rolando V. Baro", sg: "SG 24" },
        { id: "pamd", pid: "tsd", tags: ["division"], name: "PIPELINE & APPURTENANCES", title: "Engr. Bernard Joseph Rodriguez (OIC)", sg: "SG 24" },
        
        // Operations Department & Divisions
        { id: "ops", pid: "ogm", tags: ["dept"], name: "OPERATIONS DEPARTMENT", title: "Engr. Joselito A. Gillera", sg: "SG 26" },
        { id: "ops_sec", pid: "ops", tags: ["assistant", "staff"], name: "Vacant", title: "Secretary B", sg: "SG 7" },
        { id: "production", pid: "ops", tags: ["division"], name: "PRODUCTION DIVISION", title: "Engr. Elizaldy D. Novillos", sg: "SG 24" }
    ];

            try {
                mainOrgChartInstance = new OrgChart(container, {
                    nodes: orgChartData,
                    enableSearch: true,
                    mouseScrool: OrgChart.action.zoom,   // scroll = zoom, no Ctrl
                    scaleMin: 0.2,
                    scaleMax: 3,
                    scaleInitial: 1,
                    nodeMouseClick: OrgChart.action.none,
                    layout: OrgChart.top,
                    template: "twoToneTemplate",
                    tags: {
                        top: { template: "topTemplate" },
                        dept: { template: "deptTemplate" },
                        division: { template: "divisionTemplate" },
                        staff: { template: "staffTemplate" },
                        section: { template: "sectionTemplate" }
                    },
                    nodeBinding: { field_0: "name", field_1: "title", field_2: "sg" }
                });

                mainOrgChartInstance.onNodeClick(function (args) {
                    const nodeId = args.node.id;
                    const nonClickableIds = ['admin', 'finance', 'commercial', 'tsd', 'ops'];
                    if (nonClickableIds.includes(nodeId) || nodeId.endsWith('_sec')) return false;   // same as website
                    const code = ORG_CODE_MAP[nodeId];
                    if (code) openOrgModal(code);
                    return false;
                });

                mainOrgChartInstance.onInit(function () {
                    focusOnTopNodes(mainOrgChartInstance, container);
                    styleChartSearch(container);
                    addChartHint(container);
                });

                setTimeout(() => {
                    if (mainOrgChartInstance) {
                        focusOnTopNodes(mainOrgChartInstance, container);
                        styleChartSearch(container);
                        addChartHint(container);
                    }
                }, 150);
            } catch (err) {
                console.error("OrgChart error:", err);
            }
        }

        // ==========================================
        // MODAL: nodes for the sub-chart
        // ==========================================
        function buildModalNodes(key, data) {
            let subNodes = [];

            if (key === 'bod') {
                subNodes.push({ id: "bod_root", pid: null, name: "OFFICE OF THE BOARD OF DIRECTORS", title: "Board of Directors", sg: "" });
                subNodes.push({ id: "bod_chair", pid: "bod_root", name: "RONALDO J. PUA", title: "Chairperson", sg: "" });
                subNodes.push({ id: "bod_vice", pid: "bod_root", name: "ATTY. DANTE M. MANGUIAT", title: "Vice-Chairperson", sg: "" });
                subNodes.push({ id: "bod_sec", pid: "bod_root", name: "SATURNINO J. LAJARA", title: "Corporate Secretary", sg: "" });
                subNodes.push({ id: "bod_treas", pid: "bod_root", name: "ALICIA V. LLAMAS", title: "Treasurer", sg: "" });
                subNodes.push({ id: "bod_pro", pid: "bod_root", name: "BRYAN A. ERCIA", title: "Public Relations Officer", sg: "" });

                subNodes.push({ id: "agenda_officer", pid: "bod_chair", tags: ["assistant"], name: "AMIE B. MENDOZA", title: "Minutes/Agenda Officer B", sg: "SG 11" });
                subNodes.push({ id: "tech_assist", pid: "bod_chair", tags: ["assistant"], name: "VACANT", title: "Technical Assistant B", sg: "SG 20" });
                subNodes.push({ id: "admin_assist", pid: "agenda_officer", name: "VACANT", title: "Admin Services Assistant C", sg: "SG 8" });
            } else if (key === 'ogm') {
                let gm = data.manager;
                subNodes.push({ id: "ogm_root", pid: null, name: gm ? gm.employee_name : 'Exequiel A. Aguilar, Jr.', title: gm ? gm.position_title : 'General Manager A', sg: gm ? `SG ${gm.salary_grade}` : 'SG 26' });

                let dbPositions = [];
                if (data.positions && Array.isArray(data.positions)) dbPositions = dbPositions.concat(data.positions);
                if (data.sections && Array.isArray(data.sections)) {
                    data.sections.forEach(sec => { if (sec.positions) dbPositions = dbPositions.concat(sec.positions); });
                }

                let privSec = dbPositions.find(p => p.position_title.toLowerCase().includes('private secretary'));
                let driver = dbPositions.find(p => p.position_title.toLowerCase().includes('driver'));

                if (privSec) subNodes.push({ id: "priv_sec", pid: "ogm_root", tags: ["assistant"], name: privSec.employee_name, title: privSec.position_title, sg: `SG ${privSec.salary_grade}` });
                if (driver) subNodes.push({ id: "driver_node", pid: "ogm_root", tags: ["assistant"], name: driver.employee_name, title: driver.position_title, sg: `SG ${driver.salary_grade}` });

                let filteredPositions = dbPositions.filter(p => (!gm || p.id !== gm.id) && (!privSec || p.id !== privSec.id) && (!driver || p.id !== driver.id));

                let col1 = filteredPositions.filter(p => p.position_title.toLowerCase().includes('executive assistant') || p.position_title.toLowerCase().includes('customer service'));
                let col2 = filteredPositions.filter(p => p.position_title.toLowerCase().includes('plan'));
                let col3 = filteredPositions.filter(p => p.position_title.toLowerCase().includes('internal control'));
                let col4 = filteredPositions.filter(p => p.position_title.toLowerCase().includes('attorney') || p.position_title.toLowerCase().includes('legal'));
                let col5 = filteredPositions.filter(p => p.position_title.toLowerCase().includes('mis') || p.position_title.toLowerCase().includes('computer') || p.position_title.toLowerCase().includes('encoder') || p.position_title.toLowerCase().includes('data') || p.employee_name.toLowerCase().includes('fajarda') || p.employee_name.toLowerCase().includes('dave'));

                col5.sort((a, b) => (a.employee_name.toLowerCase().includes('fajarda') || a.employee_name.toLowerCase().includes('dave')) ? -1 : 1);

                function addColumnNodes(colArray, colPrefix) {
                    let parentId = "ogm_root";
                    colArray.forEach((pos, idx) => {
                        let nodeId = `${colPrefix}_${idx}`;
                        subNodes.push({ id: nodeId, pid: parentId, name: pos.employee_name, title: pos.position_title, sg: `SG ${pos.salary_grade}` });
                        parentId = nodeId;
                    });
                }
                addColumnNodes(col1, "col1"); addColumnNodes(col2, "col2"); addColumnNodes(col3, "col3"); addColumnNodes(col4, "col4"); addColumnNodes(col5, "col5");
            } else {
                let rootId = "dept_root";
                subNodes.push({ id: rootId, pid: null, name: data.manager ? (data.manager.employee_name || 'Vacant') : (data.title || 'Office'), title: data.manager ? data.manager.position_title : 'Department Manager', sg: data.manager?.salary_grade ? `SG ${data.manager.salary_grade}` : 'SG 26' });

                if (data.secretary) {
                    subNodes.push({ id: "sec_node", pid: rootId, tags: ["assistant"], name: data.secretary.employee_name || 'Vacant', title: data.secretary.position_title, sg: `SG ${data.secretary.salary_grade || 7}` });
                }

                let allPositions = [];
                if (data.sections && Array.isArray(data.sections)) data.sections.forEach(sec => sec.positions && sec.positions.forEach(p => allPositions.push(p)));
                if (data.divisions && Array.isArray(data.divisions)) data.divisions.forEach(div => div.positions && div.positions.forEach(p => allPositions.push(p)));
                if (data.positions && Array.isArray(data.positions)) data.positions.forEach(p => allPositions.push(p));

                allPositions.sort((a, b) => (parseInt(b.salary_grade) || 0) - (parseInt(a.salary_grade) || 0));

                let previousId = rootId;
                allPositions.forEach((pos, idx) => {
                    let currentId = `pos_sg_${idx}`;
                    subNodes.push({ id: currentId, pid: previousId, name: pos.employee_name || 'Vacant', title: pos.position_title, sg: pos.salary_grade ? `SG ${pos.salary_grade}` : 'SG --' });
                    previousId = currentId;
                });
            }
            return subNodes;
        }

        // ==========================================
        // MODAL: open / render / close
        // ==========================================
        async function openOrgModal(code) {
            try {
                const currentUrl = window.location.pathname;
                const response = await fetch(`${currentUrl}?action=get_details&code=${encodeURIComponent(code)}`);
                const data = await response.json();

                if (!data || data.status !== 'success') {
                    alert(data.message || 'Hindi mahanap ang datos.');
                    return;
                }

                const catElement = document.getElementById('modalCategory');
                const titleElement = document.getElementById('modalTitle');
                if (catElement) catElement.innerText = data.type || 'Department View';
                if (titleElement) titleElement.innerText = data.title || '';

                const key = String(code).toLowerCase();
                const modalBody = document.getElementById('modalBody');
                const modalEl = document.getElementById('orgChartModal');

                if (!BALKAN_READY) {
                    alert('The chart library could not be loaded. Please check your connection and refresh the page.');
                    return;
                }

                pendingModalNodes = buildModalNodes(key, data);
                if (modalBody) modalBody.innerHTML = '<div id="modalTreeContainer"></div>';

                let bsModal = bootstrap.Modal.getInstance(modalEl);
                if (!bsModal) {
                    bsModal = new bootstrap.Modal(modalEl);
                }
                bsModal.show();

            } catch (error) {
                console.error('Error fetching org chart details:', error);
                alert('Nagkaroon ng problema sa pag-load ng datos mula sa server.');
            }
        }

        // Create the sub-chart only after the modal is visible (Balkan needs real dimensions)
        function renderModalChart() {
            const container = document.getElementById('modalTreeContainer');
            if (!BALKAN_READY || !container || !pendingModalNodes) return;

            if (modalOrgChartInstance) { modalOrgChartInstance.destroy(); modalOrgChartInstance = null; }

            try {
                modalOrgChartInstance = new OrgChart(container, {
                    nodes: pendingModalNodes,
                    enableSearch: true,
                    mouseScrool: OrgChart.action.zoom,   // scroll = zoom, no Ctrl
                    scaleMin: 0.2,
                    scaleMax: 3,
                    nodeMouseClick: OrgChart.action.none,
                    layout: OrgChart.top,
                    template: "twoToneTemplate",
                    nodeBinding: { field_0: "name", field_1: "title", field_2: "sg" }
                });

                setTimeout(() => {
                    if (modalOrgChartInstance) {
                        modalOrgChartInstance.fit();
                        modalOrgChartInstance.center();
                        styleChartSearch(container);
                    }
                }, 100);
            } catch (err) {
                console.error('Modal OrgChart error:', err);
            }
        }

        function closeOrgModal() {
            const modalEl = document.getElementById('orgChartModal');
            let bsModal = bootstrap.Modal.getInstance(modalEl);
            if (bsModal) {
                bsModal.hide();
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            renderBalkanOrgChart();

            const modalEl = document.getElementById('orgChartModal');
            if (modalEl) {
                modalEl.addEventListener('shown.bs.modal', renderModalChart);
                modalEl.addEventListener('hidden.bs.modal', function () {
                    if (modalOrgChartInstance) { modalOrgChartInstance.destroy(); modalOrgChartInstance = null; }
                    pendingModalNodes = null;
                });
            }
        });

        // Re-apply the close-up once everything (images, sidebar layout) has finished loading
        window.addEventListener('load', function () {
            const c = document.getElementById('balkan-tree-container');
            if (mainOrgChartInstance && c) focusOnTopNodes(mainOrgChartInstance, c);
        });
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