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

                                <!-- Executive Level (Top) -->
                                <div class="d-flex flex-column align-items-center">

                                    <!-- BOD Button -->
                                    <button onclick="openOrgModal('BOD')"
                                        class="btn org-card-exec p-3 rounded-4 shadow-sm text-center d-flex flex-column align-items-center justify-content-center"
                                        style="width: 100%; max-width: 320px;">
                                        <div class="badge bg-primary rounded-circle mb-2 badge-avatar d-flex align-items-center justify-content-center fw-bold fs-6"
                                            style="width: 48px; height: 48px;">
                                            BOD
                                        </div>
                                        <h5 class="fw-bold text-dark mb-0">Board of Directors</h5>
                                        <small class="text-primary fw-bold text-uppercase"
                                            style="font-size: 10px; letter-spacing: 0.5px;">Office of the B.O.D</small>
                                    </button>

                                    <!-- Vertical Line Connector -->
                                    <div class="exec-connector-line"></div>

                                    <!-- OGM Button -->
                                    <button onclick="openOrgModal('OGM')"
                                        class="btn org-card-exec p-3 rounded-4 shadow-sm text-center d-flex flex-column align-items-center justify-content-center"
                                        style="width: 100%; max-width: 320px;">
                                        <div class="badge bg-primary rounded-circle mb-2 badge-avatar d-flex align-items-center justify-content-center fw-bold fs-6"
                                            style="width: 48px; height: 48px;">
                                            OGM
                                        </div>
                                        <h5 class="fw-bold text-dark mb-0">General Manager</h5>
                                        <small class="text-primary fw-bold text-uppercase"
                                            style="font-size: 10px; letter-spacing: 0.5px;">Office of the General
                                            Manager</small>
                                    </button>

                                    <!-- Vertical Line Connector -->
                                    <div class="exec-connector-line"></div>

                                    <!-- MIS Pill Button -->
                                    <button onclick="openOrgModal('MIS')"
                                        class="btn org-card-exec p-2 px-3 rounded-3 shadow-sm d-flex align-items-center justify-content-between"
                                        style="width: 100%; max-width: 340px;">
                                        <div class="d-flex align-items-center gap-2">
                                            <span
                                                class="badge bg-success rounded-circle d-flex align-items-center justify-content-center fw-bold"
                                                style="width: 32px; height: 32px; font-size: 10px;">MIS</span>
                                            <span class="fw-bold text-dark text-start"
                                                style="font-size: 11px; line-height: 1.2;">MANAGEMENT INFORMATION
                                                SERVICES SECTION</span>
                                        </div>
                                        <i class="bx bx-chevron-right text-muted fs-5"></i>
                                    </button>

                                </div>

                                <hr class="my-5 opacity-10">

                                <!-- Departments Title -->
                                <div class="text-center mb-4">
                                    <h6 class="fw-bold text-uppercase tracking-wider fs-5">Departments &
                                        Divisions</h6>
                                </div>

                                <!-- Top Departments Row (3 Columns) -->
                                <div class="row g-4 mb-4">

                                    <!-- Administrative Department -->
                                    <div class="col-12 col-md-4">
                                        <div class="card h-100 p-3 dept-card shadow-sm rounded-4">
                                            <button onclick="openOrgModal('ADMIN')"
                                                class="btn btn-dept-header d-flex align-items-center justify-content-between p-3 rounded-3 mb-3 text-start fw-bold shadow-sm">
                                                <span class="d-flex align-items-center gap-2">
                                                    <i class="bx bx-briefcase fs-5"></i>
                                                    <span>Administrative</span>
                                                </span>
                                                <i class="bx bx-chevron-right fs-5"></i>
                                            </button>
                                            <div class="d-flex flex-column gap-2">
                                                <button onclick="openOrgModal('HRD')"
                                                    class="btn btn-division-item p-2 px-3 rounded-3 d-flex align-items-center justify-content-between text-start small fw-semibold">
                                                    <span class="d-flex align-items-center gap-2">
                                                        <span class="icon-box"><i class="bx bx-user"></i></span>
                                                        <span>Human Resource Division</span>
                                                    </span>
                                                    <i class="bx bx-right-arrow-alt text-muted fs-5"></i>
                                                </button>
                                                <button onclick="openOrgModal('PMMD')"
                                                    class="btn btn-division-item p-2 px-3 rounded-3 d-flex align-items-center justify-content-between text-start small fw-semibold">
                                                    <span class="d-flex align-items-center gap-2">
                                                        <span class="icon-box"><i class="bx bx-package"></i></span>
                                                        <span>Property & Materials Mgmt</span>
                                                    </span>
                                                    <i class="bx bx-right-arrow-alt text-muted fs-5"></i>
                                                </button>
                                                <button onclick="openOrgModal('GSD')"
                                                    class="btn btn-division-item p-2 px-3 rounded-3 d-flex align-items-center justify-content-between text-start small fw-semibold">
                                                    <span class="d-flex align-items-center gap-2">
                                                        <span class="icon-box"><i class="bx bx-wrench"></i></span>
                                                        <span>General Services Division</span>
                                                    </span>
                                                    <i class="bx bx-right-arrow-alt text-muted fs-5"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Finance Department -->
                                    <div class="col-12 col-md-4">
                                        <div class="card h-100 p-3 dept-card shadow-sm rounded-4">
                                            <button onclick="openOrgModal('FINANCE')"
                                                class="btn btn-dept-header d-flex align-items-center justify-content-between p-3 rounded-3 mb-3 text-start fw-bold shadow-sm">
                                                <span class="d-flex align-items-center gap-2">
                                                    <i class="bx bx-dollar-circle fs-5"></i>
                                                    <span>Finance</span>
                                                </span>
                                                <i class="bx bx-chevron-right fs-5"></i>
                                            </button>
                                            <div class="d-flex flex-column gap-2">
                                                <button onclick="openOrgModal('BUDGET')"
                                                    class="btn btn-division-item p-2 px-3 rounded-3 d-flex align-items-center justify-content-between text-start small fw-semibold">
                                                    <span class="d-flex align-items-center gap-2">
                                                        <span class="icon-box"><i
                                                                class="bx bx-pie-chart-alt-2"></i></span>
                                                        <span>Budget Division</span>
                                                    </span>
                                                    <i class="bx bx-right-arrow-alt text-muted fs-5"></i>
                                                </button>
                                                <button onclick="openOrgModal('gad')"
                                                    class="btn btn-division-item p-2 px-3 rounded-3 d-flex align-items-center justify-content-between text-start small fw-semibold">
                                                    <span class="d-flex align-items-center gap-2">
                                                        <span class="icon-box"><i class="bx bx-calculator"></i></span>
                                                        <span>General Accounting Division</span>
                                                    </span>
                                                    <i class="bx bx-right-arrow-alt text-muted fs-5"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Commercial Department -->
                                    <div class="col-12 col-md-4">
                                        <div class="card h-100 p-3 dept-card shadow-sm rounded-4">
                                            <button onclick="openOrgModal('COMMERCIAL')"
                                                class="btn btn-dept-header d-flex align-items-center justify-content-between p-3 rounded-3 mb-3 text-start fw-bold shadow-sm">
                                                <span class="d-flex align-items-center gap-2">
                                                    <i class="bx bx-store-alt fs-5"></i>
                                                    <span>Commercial</span>
                                                </span>
                                                <i class="bx bx-chevron-right fs-5"></i>
                                            </button>
                                            <div class="d-flex flex-column gap-2">
                                                <button onclick="openOrgModal('BILLING')"
                                                    class="btn btn-division-item p-2 px-3 rounded-3 d-flex align-items-center justify-content-between text-start small fw-semibold">
                                                    <span class="d-flex align-items-center gap-2">
                                                        <span class="icon-box"><i class="bx bx-receipt"></i></span>
                                                        <span>Billing & Meter Reading</span>
                                                    </span>
                                                    <i class="bx bx-right-arrow-alt text-muted fs-5"></i>
                                                </button>
                                                <button onclick="openOrgModal('cad')"
                                                    class="btn btn-division-item p-2 px-3 rounded-3 d-flex align-items-center justify-content-between text-start small fw-semibold">
                                                    <span class="d-flex align-items-center gap-2">
                                                        <span class="icon-box"><i class="bx bx-group"></i></span>
                                                        <span>Customer Accounts Division</span>
                                                    </span>
                                                    <i class="bx bx-right-arrow-alt text-muted fs-5"></i>
                                                </button>
                                                <button onclick="openOrgModal('CUSTOMER_CARE')"
                                                    class="btn btn-division-item p-2 px-3 rounded-3 d-flex align-items-center justify-content-between text-start small fw-semibold">
                                                    <span class="d-flex align-items-center gap-2">
                                                        <span class="icon-box"><i class="bx bx-support"></i></span>
                                                        <span>Customer Care Division</span>
                                                    </span>
                                                    <i class="bx bx-right-arrow-alt text-muted fs-5"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                </div>

                                <!-- Bottom Departments Row (2 Columns) -->
                                <div class="row g-4">

                                    <!-- Technical Services Department -->
                                    <div class="col-12 col-md-6">
                                        <div class="card h-100 p-3 dept-card shadow-sm rounded-4">
                                            <button onclick="openOrgModal('TECHNICAL')"
                                                class="btn btn-dept-header d-flex align-items-center justify-content-between p-3 rounded-3 mb-3 text-start fw-bold shadow-sm">
                                                <span class="d-flex align-items-center gap-2">
                                                    <i class="bx bx-cog fs-5"></i>
                                                    <span>Technical Services</span>
                                                </span>
                                                <i class="bx bx-chevron-right fs-5"></i>
                                            </button>
                                            <div class="d-flex flex-column gap-2">
                                                <button onclick="openOrgModal('eamd')"
                                                    class="btn btn-division-item p-2 px-3 rounded-3 d-flex align-items-center justify-content-between text-start small fw-semibold">
                                                    <span class="d-flex align-items-center gap-2">
                                                        <span class="icon-box"><i class="bx bx-chip"></i></span>
                                                        <span>Engineering & Maintenance</span>
                                                    </span>
                                                    <i class="bx bx-right-arrow-alt text-muted fs-5"></i>
                                                </button>
                                                <button onclick="openOrgModal('PIPELINE')"
                                                    class="btn btn-division-item p-2 px-3 rounded-3 d-flex align-items-center justify-content-between text-start small fw-semibold">
                                                    <span class="d-flex align-items-center gap-2">
                                                        <span class="icon-box"><i class="bx bx-git-commit"></i></span>
                                                        <span>Pipeline & Appurtenances Maint.</span>
                                                    </span>
                                                    <i class="bx bx-right-arrow-alt text-muted fs-5"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Operations Department -->
                                    <div class="col-12 col-md-6">
                                        <div class="card h-100 p-3 dept-card shadow-sm rounded-4">
                                            <button onclick="openOrgModal('OPERATIONS')"
                                                class="btn btn-dept-header d-flex align-items-center justify-content-between p-3 rounded-3 mb-3 text-start fw-bold shadow-sm">
                                                <span class="d-flex align-items-center gap-2">
                                                    <i class="bx bx-buildings fs-5"></i>
                                                    <span>Operations</span>
                                                </span>
                                                <i class="bx bx-chevron-right fs-5"></i>
                                            </button>
                                            <div class="d-flex flex-column gap-2">
                                                <button onclick="openOrgModal('PRODUCTION')"
                                                    class="btn btn-division-item p-2 px-3 rounded-3 d-flex align-items-center justify-content-between text-start small fw-semibold">
                                                    <span class="d-flex align-items-center gap-2">
                                                        <span class="icon-box"><i class="bx bx-water"></i></span>
                                                        <span>Production Division</span>
                                                    </span>
                                                    <i class="bx bx-right-arrow-alt text-muted fs-5"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                </div>

                                <!-- External Website Redirect Link Section -->
                                <div class="mt-4 pt-2 text-center border-top">
                                    <div class="p-4 rounded-4 bg-light d-inline-block text-center shadow-xs"
                                        style="max-width: 500px; width: 100%;">
                                        <p class="text-muted small mb-3 fw-semibold">Looking for the public Organizational Chart?</p>
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
                            <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
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
                                    <div id="modalBody" class="modal-body p-4 bg-light">
                                        <!-- Dito mai-inject ang Javascript output -->
                                    </div>

                                    <!-- Modal Footer -->
                                    <div class="modal-footer bg-white border-top px-4 py-2">
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

    <!-- JAVASCRIPT FOR ORG CHART MODAL -->
    <script>
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

                let html = '';

                const renderNames = (nameStr) => {
                    if (!nameStr) return `<div class="badge bg-warning-subtle text-warning border border-warning-subtle fw-semibold mt-1"><i class="bx bx-error-circle me-1"></i>Vacant</div>`;
                    const namesList = nameStr.split(',').map(n => n.trim());
                    return namesList.map(n => {
                        if (n.toLowerCase() === 'vacant') {
                            return `<div class="badge bg-warning-subtle text-warning border border-warning-subtle fw-semibold mt-1"><i class="bx bx-error-circle me-1"></i>Vacant</div>`;
                        }
                        return `<div class="text-dark fw-bold small mt-1"><i class="bx bx-user text-muted me-1"></i>${n}</div>`;
                    }).join('');
                };

                // 1. Leadership & Administrative Support
                if (data.manager || data.secretary) {
                    html += `<div class="row g-3 mb-4">`;

                    if (data.manager) {
                        html += `
                <div class="${data.secretary ? 'col-md-7' : 'col-12'}">
                    <div class="p-3 modal-card-lead border rounded-4 h-100">
                        <div class="d-flex justify-content-between align-items-start">
                            <span class="text-uppercase fw-bold text-primary" style="font-size: 10px; letter-spacing: 0.5px;">Leadership</span>
                            ${data.manager.salary_grade && data.manager.salary_grade !== '0' && data.manager.salary_grade !== 'N/A' ? `<span class="badge-sg">SG ${data.manager.salary_grade}</span>` : ''}
                        </div>
                        <h6 class="fw-bold text-dark mb-0 mt-1">${data.manager.position_title || 'Department Head'}</h6>
                        <div>${renderNames(data.manager.employee_name)}</div>
                    </div>
                </div>`;
                    }

                    if (data.secretary) {
                        html += `
                <div class="col-md-5">
                    <div class="p-3 modal-card-sec border rounded-4 h-100">
                        <div class="d-flex justify-content-between align-items-start">
                            <span class="text-uppercase fw-bold text-secondary" style="font-size: 9px; letter-spacing: 0.5px;">Admin Support</span>
                            ${data.secretary.salary_grade && data.secretary.salary_grade !== '0' && data.secretary.salary_grade !== 'N/A' ? `<span class="badge-sg">SG ${data.secretary.salary_grade}</span>` : ''}
                        </div>
                        <h6 class="fw-bold text-dark mb-0 mt-1">${data.secretary.position_title || 'Secretary'}</h6>
                        <div>${renderNames(data.secretary.employee_name)}</div>
                    </div>
                </div>`;
                    }

                    html += `</div>`;
                }

                // Helper Function para mag-render ng Sections & Divisions cards
                const renderSubGroup = (title, items, iconClass) => {
                    if (!items || !Array.isArray(items) || items.length === 0) return '';
                    let subHtml = `<div class="d-flex align-items-center gap-2 mb-3 mt-4">
                <i class="${iconClass} text-primary fs-5"></i>
                <h6 class="fw-bold text-uppercase text-secondary mb-0" style="font-size: 12px; letter-spacing: 0.5px;">${title}</h6>
            </div>`;

                    items.forEach(item => {
                        const positions = item.positions || [];
                        const head = positions.find(p => p.is_head == 1);
                        const regularPositions = positions.filter(p => p.is_head == 0);

                        subHtml += `
                <div class="card border-0 shadow-sm rounded-4 mb-3 overflow-hidden">
                    <div class="card-header bg-white border-bottom p-3">
                        <h6 class="fw-bold text-primary mb-0 d-flex align-items-center gap-2">
                            <i class="bx bx-folder"></i> ${item.name || item.code || ''}
                        </h6>
                    </div>
                    <div class="card-body p-3 bg-light-subtle">`;

                        if (head) {
                            subHtml += `
                    <div class="p-3 bg-white border border-primary-subtle rounded-3 mb-3 d-flex justify-content-between align-items-center shadow-xs">
                        <div>
                            <span class="badge bg-info-subtle text-info text-uppercase fw-bold mb-1" style="font-size: 9px;">Head</span>
                            <h6 class="fw-bold text-dark mb-0">${head.position_title || ''}</h6>
                            <div>${renderNames(head.employee_name)}</div>
                        </div>
                        ${head.salary_grade && head.salary_grade !== '0' && head.salary_grade !== 'N/A' ? `<span class="badge-sg">SG ${head.salary_grade}</span>` : ''}
                    </div>`;
                        }

                        if (regularPositions.length > 0) {
                            subHtml += `<div class="row g-2">`;
                            regularPositions.forEach(pos => {
                                subHtml += `
                        <div class="col-12 col-md-6">
                            <div class="pos-card-item p-2 px-3 rounded-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="fw-bold text-dark mb-0 small">${pos.position_title || ''}</p>
                                    <div>${renderNames(pos.employee_name)}</div>
                                </div>
                                ${pos.salary_grade && pos.salary_grade !== '0' && pos.salary_grade !== 'N/A' ? `<span class="badge-sg">SG ${pos.salary_grade}</span>` : ''}
                            </div>
                        </div>`;
                            });
                            subHtml += `</div>`;
                        }

                        subHtml += `</div></div>`;
                    });
                    return subHtml;
                };

                // 2. Direct Sections
                html += renderSubGroup('Sections', data.sections, 'bx bx-layer');

                // 3. Divisions
                html += renderSubGroup('Divisions', data.divisions, 'bx bx-sitemap');

                // 4. Direct Department Positions
                if (data.positions && Array.isArray(data.positions) && data.positions.length > 0) {
                    html += `<div class="d-flex align-items-center gap-2 mb-3 mt-4">
                <i class="bx bx-id-card text-primary fs-5"></i>
                <h6 class="fw-bold text-uppercase text-secondary mb-0" style="font-size: 12px; letter-spacing: 0.5px;">Plantilla Positions</h6>
            </div>`;
                    html += `<div class="row g-2">`;
                    data.positions.forEach(pos => {
                        html += `
                <div class="col-12 col-md-6">
                    <div class="pos-card-item p-3 rounded-3 d-flex justify-content-between align-items-center bg-white">
                        <div>
                            <span class="text-uppercase fw-bold text-muted d-block mb-1" style="font-size: 10px;">${pos.position_title || ''}</span>
                            <div>${renderNames(pos.employee_name)}</div>
                        </div>
                        ${pos.salary_grade && pos.salary_grade !== '0' && pos.salary_grade !== 'N/A' ? `<span class="badge-sg">SG ${pos.salary_grade}</span>` : ''}
                    </div>
                </div>`;
                    });
                    html += `</div>`;
                }

                const modalBody = document.getElementById('modalBody');
                const modalEl = document.getElementById('orgChartModal');

                if (modalBody) modalBody.innerHTML = html;

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

        function closeOrgModal() {
            const modalEl = document.getElementById('orgChartModal');
            let bsModal = bootstrap.Modal.getInstance(modalEl);
            if (bsModal) {
                bsModal.hide();
            }
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