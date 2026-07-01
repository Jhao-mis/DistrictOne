<?php
include '../db.php';
require '../vendor/autoload.php';
require 'db_saln.php';
require_once('../tcpdf/tcpdf.php'); // Include TCPDF if manually downloaded
require 'login_verification.php';

$username = $_SESSION['username'];

date_default_timezone_set('Asia/Manila'); // Set timezone to your local
$now = date('Y-m-d H:i:s');
$updateActivity = $conn->prepare("UPDATE users SET last_activity = ? WHERE username = ?");
$updateActivity->bind_param("ss", $now, $username);
$updateActivity->execute();
$updateActivity->close();

// Fetch user details
$query = $conn->prepare("SELECT id, profile_picture, cover_photo, department, firstname, middlename, lastname, email FROM users WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->store_result();
$query->bind_result($user_id, $profile_picture, $cover_photo, $department, $firstname, $middlename, $lastname, $email);
$query->fetch();
$query->close();
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>File SALN</title>
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Core CSS -->
    <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="./css/fileSaln.css">

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../assets/vendor/libs/apex-charts/apex-charts.css" />

    <!-- Helpers -->
    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --saln-primary: #007bff;
            --saln-primary-dark: #0056b3;
            --saln-success: #3CB371;
            --saln-border: #e4e6ef;
            --saln-muted: #6c757d;
        }

        .saln-section {
            border: 1px solid var(--saln-border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.75rem;
            background: #fff;
        }

        .saln-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            margin-bottom: 1.25rem;
            padding-bottom: .75rem;
            border-bottom: 2px solid var(--saln-border);
        }

        .saln-section-header h5 {
            display: flex;
            align-items: center;
            gap: .6rem;
            margin: 0;
            font-weight: 600;
        }

        .saln-section-header h5 i {
            color: var(--saln-primary);
            font-size: 1.15rem;
        }

        .saln-badge {
            background: #eef4ff;
            color: var(--saln-primary);
            font-weight: 600;
            font-size: .75rem;
            padding: .3rem .65rem;
            border-radius: 999px;
        }

        .saln-note {
            background: #fff8e6;
            border: 1px solid #ffe4a3;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            font-size: .95rem;
            font-weight: 500;
            text-align: center;
            margin-bottom: 1.75rem;
        }

        .saln-filing-options {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: .75rem;
        }

        .saln-row-entry {
            position: relative;
            border: 1px solid var(--saln-border);
            border-radius: 10px;
            padding: 1rem 1rem .25rem;
            margin-bottom: 1rem;
            background: #fbfbfd;
        }

        .saln-remove-row {
            border: none;
            background: transparent;
            color: #dc3545;
            font-size: 1.1rem;
            line-height: 1;
            cursor: pointer;
            padding: .25rem .4rem;
        }

        .saln-remove-row:hover {
            color: #a71d2a;
        }

        table.table thead th {
            background: #f5f6fa;
            font-size: .82rem;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: var(--saln-muted);
            white-space: nowrap;
            vertical-align: middle;
        }

        table.table td .form-control {
            min-width: 130px;
        }

        table.table td {
            vertical-align: middle;
        }

        .custom-outline-blue {
            border: 1.5px solid var(--saln-primary);
            color: var(--saln-primary);
            background: transparent;
            border-radius: 8px;
            padding: .45rem 1rem;
            font-weight: 500;
            font-size: .9rem;
            transition: all .15s ease-in-out;
        }

        .custom-outline-blue:hover {
            background: var(--saln-primary);
            color: #fff;
        }

        .saln-action-bar {
            position: sticky;
            bottom: 0;
            background: #fff;
            border-top: 1px solid var(--saln-border);
            padding: 1rem 1.5rem;
            margin: 1.5rem -1.5rem -1.5rem;
            display: flex;
            justify-content: flex-end;
            gap: .75rem;
            border-radius: 0 0 12px 12px;
        }

        .saln-action-bar .btn {
            min-width: 160px;
            font-weight: 600;
        }

        @media (max-width: 576px) {
            .saln-action-bar {
                flex-direction: column;
            }

            .saln-action-bar .btn {
                width: 100%;
                min-width: 0;
            }
        }
    </style>
</head>

<body>
    <?php
    $role = $_SESSION['role'];

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
    <div class="container-xxl flex-grow-1 container-p-y">
        <h4 class="fw-bold py-3 mb-4">
            <span class="text-muted fw-light"></span> File SALN (Statement of Assets, Liabilities, and Net Worth)
        </h4>

        <div class="row">
            <div class="col-md-12">
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="POST" id="submitSALN" action="fileSaln.php">
                            <input type="hidden" name="save" value="1">

                            <div class="saln-note">
                                Husband and wife who are both public officials and employees may file the required
                                statements jointly or separately.
                                <div class="saln-filing-options">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="salnFiling"
                                            id="salnFilingJoint" value="Joint Filing"
                                            <?= (isset($saln_data['salnFiling']) && $saln_data['salnFiling'] == 'Joint Filing') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="salnFilingJoint">Joint Filing</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="salnFiling"
                                            id="salnFilingSeparate" value="Separate Filing"
                                            <?= (isset($saln_data['salnFiling']) && $saln_data['salnFiling'] == 'Separate Filing') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="salnFilingSeparate">Separate Filing</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="salnFiling"
                                            id="salnFilingNA" value="Not Applicable"
                                            <?= (isset($saln_data['salnFiling']) && $saln_data['salnFiling'] == 'Not Applicable') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="salnFilingNA">Not Applicable</label>
                                    </div>
                                </div>
                            </div>

                            <!-- DECLARANT -->
                            <div class="saln-section">
                                <div class="saln-section-header">
                                    <h5><i class='bx bx-user'></i> Declarant</h5>
                                </div>
                                <div class="row">
                                    <div class="mb-3 col-md-6">
                                        <label for="declarantFirstName" class="form-label">First Name</label>
                                        <input class="form-control" type="text" name="declarantFirstName"
                                            placeholder="Input the declarant's first name"
                                            value="<?= htmlspecialchars($saln_data['declarantFirstName'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label for="declarantMiddleName" class="form-label">Middle Name</label>
                                        <input class="form-control" type="text" name="declarantMiddleName"
                                            placeholder="Input the declarant's middle name"
                                            value="<?= htmlspecialchars($saln_data['declarantMiddleName'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label for="declarantLastName" class="form-label">Last Name</label>
                                        <input class="form-control" type="text" name="declarantLastName"
                                            placeholder="Input the declarant's last name"
                                            value="<?= htmlspecialchars($saln_data['declarantLastName'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label for="declarantAddress" class="form-label">Address</label>
                                        <input class="form-control" type="text" name="declarantAddress"
                                            placeholder="Input the declarant's address"
                                            value="<?= htmlspecialchars($saln_data['declarantAddress'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label for="declarantPosition" class="form-label">Position</label>
                                        <input class="form-control" type="text" name="declarantPosition"
                                            placeholder="Input the declarant's position"
                                            value="<?= htmlspecialchars($saln_data['declarantPosition'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label for="declarantAgency" class="form-label">Agency/Office</label>
                                        <input class="form-control" type="text" name="declarantAgency"
                                            placeholder="Input the declarant's agency/office"
                                            value="<?= htmlspecialchars($saln_data['declarantAgency'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label for="declarantOfficeAddress" class="form-label">Office Address</label>
                                        <input class="form-control" type="text" name="declarantOfficeAddress"
                                            placeholder="Input the office address"
                                            value="<?= htmlspecialchars($saln_data['declarantOfficeAddress'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- SPOUSE -->
                            <div class="saln-section">
                                <div class="saln-section-header">
                                    <h5><i class='bx bx-user-plus'></i> Spouse</h5>
                                </div>
                                <div class="row">
                                    <div class="mb-3 col-md-6">
                                        <label for="spouseFirstName" class="form-label">First Name</label>
                                        <input class="form-control" type="text" name="spouseFirstName"
                                            placeholder="Input the spouse's first name"
                                            value="<?= htmlspecialchars($saln_data['spouseFirstName'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label for="spouseMiddleName" class="form-label">Middle Name</label>
                                        <input class="form-control" type="text" name="spouseMiddleName"
                                            placeholder="Input the spouse's middle name"
                                            value="<?= htmlspecialchars($saln_data['spouseMiddleName'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label for="spouseLastName" class="form-label">Last Name</label>
                                        <input class="form-control" type="text" name="spouseLastName"
                                            placeholder="Input the spouse's last name"
                                            value="<?= htmlspecialchars($saln_data['spouseLastName'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label for="spousePosition" class="form-label">Position</label>
                                        <input class="form-control" type="text" name="spousePosition"
                                            placeholder="Input the spouse's position"
                                            value="<?= htmlspecialchars($saln_data['spousePosition'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label for="spouseAgency" class="form-label">Agency/Office</label>
                                        <input class="form-control" type="text" name="spouseAgency"
                                            placeholder="Input the spouse's agency/office"
                                            value="<?= htmlspecialchars($saln_data['spouseAgency'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label for="spouseOfficeAddress" class="form-label">Office Address</label>
                                        <input class="form-control" type="text" name="spouseOfficeAddress"
                                            placeholder="Input the office address"
                                            value="<?= htmlspecialchars($saln_data['spouseOfficeAddress'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- CHILDREN -->
                            <div class="saln-section">
                                <div class="saln-section-header">
                                    <h5><i class='bx bx-child'></i> Unmarried Children Below 18 Living in Household
                                    </h5>
                                    <span class="saln-badge" id="childrenBadge"></span>
                                </div>

                                <div id="childrenContainer">
                                    <?php
                                    if (!isset($_SESSION['children_FullName'])) {
                                        $_SESSION['children_FullName'] = [];
                                        $_SESSION['children_Birthdate'] = [];
                                        $_SESSION['children_Age'] = [];
                                    }

                                    $totalChildren = count($_SESSION['children_FullName']);
                                    if ($totalChildren === 0) {
                                        $totalChildren = 1; // Ensure at least one child input is displayed
                                    }

                                    for ($i = 0; $i < $totalChildren; $i++): ?>
                                        <div class="saln-row-entry child-entry">
                                            <button type="button" class="saln-remove-row float-end"
                                                onclick="removeRow(this)" title="Remove"><i class='bx bx-x'></i></button>
                                            <div class="row align-items-end">
                                                <div class="mb-3 col-md-6">
                                                    <label for="child<?= $i + 1 ?>FullName" class="form-label">Name of
                                                        Child <?= $i + 1 ?></label>
                                                    <input class="form-control" type="text" id="child<?= $i + 1 ?>FullName"
                                                        name="children_FullName[]" placeholder="Input Child's Full Name"
                                                        value="<?= isset($_SESSION['children_FullName'][$i]) ? htmlspecialchars($_SESSION['children_FullName'][$i], ENT_QUOTES) : ''; ?>" />
                                                </div>
                                                <div class="mb-3 col-md-3">
                                                    <label for="children_Birthdate<?= $i + 1 ?>"
                                                        class="form-label">Birthdate</label>
                                                    <input class="form-control" type="date"
                                                        id="children_Birthdate<?= $i + 1 ?>" name="children_Birthdate[]"
                                                        value="<?= isset($_SESSION['children_Birthdate'][$i]) ? htmlspecialchars($_SESSION['children_Birthdate'][$i], ENT_QUOTES) : ''; ?>"
                                                        onchange="validateBirthdate(this)" />
                                                </div>
                                                <div class="mb-3 col-md-3">
                                                    <label for="children_Age<?= $i + 1 ?>" class="form-label">Age</label>
                                                    <input class="form-control" type="number" id="children_Age<?= $i + 1 ?>"
                                                        name="children_Age[]" placeholder="Enter Child's Age"
                                                        value="<?= isset($_SESSION['children_Age'][$i]) ? htmlspecialchars($_SESSION['children_Age'][$i], ENT_QUOTES) : ''; ?>" />
                                                </div>
                                            </div>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                                <button type="button" class="custom-outline-blue" onclick="addChild()">
                                    <i class='bx bx-plus'></i> Add Child</button>
                            </div>

                            <!-- ASSETS -->
                            <div class="saln-section">
                                <div class="saln-section-header">
                                    <h5><i class='bx bx-building-house'></i> I. Assets &mdash; a. Real Properties</h5>
                                </div>
                                <p class="text-muted small mt-n2 mb-3">Including those of the spouse and unmarried
                                    children below eighteen (18) years of age living in declarant's household.</p>

                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover align-middle" id="assetsTable">
                                        <thead>
                                            <tr>
                                                <th>Description</th>
                                                <th>Kind</th>
                                                <th>Exact Location</th>
                                                <th>Assessed Value</th>
                                                <th>Current Fair Market Value</th>
                                                <th>Acquisition Year</th>
                                                <th>Acquisition Mode</th>
                                                <th>Acquisition Cost</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($assets_data)): ?>
                                                <?php foreach ($assets_data as $row): ?>
                                                    <tr>
                                                        <td><input type="text" class="form-control" name="description[]"
                                                                value="<?= htmlspecialchars($row['description']) ?>" /></td>
                                                        <td><input type="text" class="form-control" name="kind[]"
                                                                value="<?= htmlspecialchars($row['kind']) ?>" /></td>
                                                        <td><input type="text" class="form-control" name="location[]"
                                                                value="<?= htmlspecialchars($row['location']) ?>" /></td>
                                                        <td><input type="text" class="form-control" name="assessed_value[]"
                                                                value="<?= htmlspecialchars($row['assessed_value']) ?>" /></td>
                                                        <td><input type="text" class="form-control" name="fair_market_value[]"
                                                                value="<?= htmlspecialchars($row['fair_market_value']) ?>" />
                                                        </td>
                                                        <td><input type="number" class="form-control" name="acquisition_year[]"
                                                                value="<?= htmlspecialchars($row['acquisition_year']) ?>" />
                                                        </td>
                                                        <td><input type="text" class="form-control" name="acquisition_mode[]"
                                                                value="<?= htmlspecialchars($row['acquisition_mode']) ?>" />
                                                        </td>
                                                        <td><input type="text" class="form-control" name="acquisition_cost[]"
                                                                value="<?= htmlspecialchars($row['acquisition_cost']) ?>" />
                                                        </td>
                                                        <td><button type="button" class="saln-remove-row"
                                                                onclick="removeTableRow(this)" title="Remove"><i
                                                                    class='bx bx-x'></i></button></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td><input type="text" class="form-control" name="description[]"
                                                            placeholder="Description" /></td>
                                                    <td><input type="text" class="form-control" name="kind[]"
                                                            placeholder="Kind" /></td>
                                                    <td><input type="text" class="form-control" name="location[]"
                                                            placeholder="Exact Location" /></td>
                                                    <td><input type="text" class="form-control" name="assessed_value[]"
                                                            placeholder="Assessed Value" /></td>
                                                    <td><input type="text" class="form-control" name="fair_market_value[]"
                                                            placeholder="Fair Market Value" /></td>
                                                    <td><input type="number" class="form-control" name="acquisition_year[]"
                                                            placeholder="Year" /></td>
                                                    <td><input type="text" class="form-control" name="acquisition_mode[]"
                                                            placeholder="Mode" /></td>
                                                    <td><input type="text" class="form-control" name="acquisition_cost[]"
                                                            placeholder="Cost" /></td>
                                                    <td><button type="button" class="saln-remove-row"
                                                            onclick="removeTableRow(this)" title="Remove"><i
                                                                class='bx bx-x'></i></button></td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <button type="button" class="custom-outline-blue mt-2" onclick="addAssetRow()">
                                    <i class='bx bx-plus'></i> Add Asset</button>

                                <hr class="my-4">

                                <div class="saln-section-header">
                                    <h5><i class='bx bx-devices'></i> b. Personal Properties</h5>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover align-middle" id="personalPropertiesTable">
                                        <thead>
                                            <tr>
                                                <th>Description</th>
                                                <th>Year Acquired</th>
                                                <th>Acquisition Cost/Amount</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($personal_properties_data)): ?>
                                                <?php foreach ($personal_properties_data as $row): ?>
                                                    <tr>
                                                        <td><input type="text" class="form-control" name="personal_description[]"
                                                                value="<?= htmlspecialchars($row['personal_description'] ?? '', ENT_QUOTES) ?>" />
                                                        </td>
                                                        <td><input type="number" class="form-control" name="yearAcquired[]"
                                                                value="<?= htmlspecialchars($row['yearAcquired'] ?? '', ENT_QUOTES) ?>" />
                                                        </td>
                                                        <td><input type="number" step="0.01" class="form-control"
                                                                name="personal_acquisition_cost[]"
                                                                value="<?= htmlspecialchars($row['personal_acquisition_cost'] ?? '', ENT_QUOTES) ?>" />
                                                        </td>
                                                        <td><button type="button" class="saln-remove-row"
                                                                onclick="removeTableRow(this)" title="Remove"><i
                                                                    class='bx bx-x'></i></button></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td><input type="text" class="form-control" name="personal_description[]"
                                                            placeholder="Enter Property Description" /></td>
                                                    <td><input type="number" class="form-control" name="yearAcquired[]"
                                                            placeholder="Year Acquired" /></td>
                                                    <td><input type="number" step="0.01" class="form-control"
                                                            name="personal_acquisition_cost[]" placeholder="Acquisition Cost" />
                                                    </td>
                                                    <td><button type="button" class="saln-remove-row"
                                                            onclick="removeTableRow(this)" title="Remove"><i
                                                                class='bx bx-x'></i></button></td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <button type="button" class="custom-outline-blue mt-2" onclick="addPropertyRow()">
                                    <i class='bx bx-plus'></i> Add Property</button>
                            </div>

                            <!-- LIABILITIES -->
                            <div class="saln-section">
                                <div class="saln-section-header">
                                    <h5><i class='bx bx-credit-card-front'></i> II. Liabilities</h5>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover align-middle" id="LiabilitiesTable">
                                        <thead>
                                            <tr>
                                                <th>Nature</th>
                                                <th>Name of Creditors</th>
                                                <th>Outstanding Balance</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($liabilities_data)): ?>
                                                <?php foreach ($liabilities_data as $row): ?>
                                                    <tr>
                                                        <td><input type="text" class="form-control" name="nature[]"
                                                                value="<?= htmlspecialchars($row['nature'] ?? '', ENT_QUOTES) ?>" />
                                                        </td>
                                                        <td><input type="text" class="form-control" name="name_of_creditors[]"
                                                                value="<?= htmlspecialchars($row['name_of_creditors'] ?? '', ENT_QUOTES) ?>" />
                                                        </td>
                                                        <td><input type="number" step="0.01" class="form-control"
                                                                name="outstandingBalance[]"
                                                                value="<?= htmlspecialchars($row['outstandingBalance'] ?? '', ENT_QUOTES) ?>" />
                                                        </td>
                                                        <td><button type="button" class="saln-remove-row"
                                                                onclick="removeTableRow(this)" title="Remove"><i
                                                                    class='bx bx-x'></i></button></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td><input type="text" class="form-control" name="nature[]"
                                                            placeholder="Enter Nature Description" /></td>
                                                    <td><input type="text" class="form-control" name="name_of_creditors[]"
                                                            placeholder="Name of Creditors" /></td>
                                                    <td><input type="number" step="0.01" class="form-control"
                                                            name="outstandingBalance[]" placeholder="Outstanding Balance" />
                                                    </td>
                                                    <td><button type="button" class="saln-remove-row"
                                                            onclick="removeTableRow(this)" title="Remove"><i
                                                                class='bx bx-x'></i></button></td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <button type="button" class="custom-outline-blue mt-2" onclick="addLiabilitiesRow()">
                                    <i class='bx bx-plus'></i> Add Liabilities</button>
                            </div>

                            <!-- BUSINESS INTERESTS -->
                            <div class="saln-section">
                                <div class="saln-section-header">
                                    <h5><i class='bx bx-briefcase'></i> Business Interests and Financial Connections
                                    </h5>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover align-middle" id="BusinessTable">
                                        <thead>
                                            <tr>
                                                <th>Name of Entity/Business Enterprise</th>
                                                <th>Business Address</th>
                                                <th>Nature of Business Interest &/Or Financial Connection</th>
                                                <th>Date of Acquisition of Interest or Connection</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($business_data)): ?>
                                                <?php foreach ($business_data as $row): ?>
                                                    <tr>
                                                        <td><input type="text" class="form-control" name="business_enterprise[]"
                                                                value="<?= htmlspecialchars($row['business_enterprise'] ?? '', ENT_QUOTES) ?>" />
                                                        </td>
                                                        <td><input type="text" class="form-control" name="business_address[]"
                                                                value="<?= htmlspecialchars($row['business_address'] ?? '', ENT_QUOTES) ?>" />
                                                        </td>
                                                        <td><input type="text" class="form-control" name="nature_of_business[]"
                                                                value="<?= htmlspecialchars($row['nature_of_business'] ?? '', ENT_QUOTES) ?>" />
                                                        </td>
                                                        <td><input type="date" class="form-control" name="date_of_acquisition[]"
                                                                value="<?= htmlspecialchars($row['date_of_acquisition'] ?? '', ENT_QUOTES) ?>" />
                                                        </td>
                                                        <td><button type="button" class="saln-remove-row"
                                                                onclick="removeTableRow(this)" title="Remove"><i
                                                                    class='bx bx-x'></i></button></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td><input type="text" class="form-control" name="business_enterprise[]"
                                                            placeholder="Enter Business Enterprise" /></td>
                                                    <td><input type="text" class="form-control" name="business_address[]"
                                                            placeholder="Enter Business Address" /></td>
                                                    <td><input type="text" class="form-control" name="nature_of_business[]"
                                                            placeholder="Nature of Business" /></td>
                                                    <td><input type="date" class="form-control" name="date_of_acquisition[]"
                                                            placeholder="Date of Acquisition" /></td>
                                                    <td><button type="button" class="saln-remove-row"
                                                            onclick="removeTableRow(this)" title="Remove"><i
                                                                class='bx bx-x'></i></button></td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <button type="button" class="custom-outline-blue mt-2" onclick="addConnectionsRow()">
                                    <i class='bx bx-plus'></i> Add Connections</button>
                            </div>

                            <!-- RELATIVES -->
                            <div class="saln-section">
                                <div class="saln-section-header">
                                    <h5><i class='bx bx-group'></i> Relatives in the Government Service</h5>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover align-middle" id="RelativesTable">
                                        <thead>
                                            <tr>
                                                <th>Name of Relative</th>
                                                <th>Relationship</th>
                                                <th>Position</th>
                                                <th>Name of Agency/Office and Address</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($relatives_data)): ?>
                                                <?php foreach ($relatives_data as $row): ?>
                                                    <tr>
                                                        <td><input type="text" class="form-control" name="name_of_relative[]"
                                                                value="<?= htmlspecialchars($row['name_of_relative'] ?? '', ENT_QUOTES) ?>" />
                                                        </td>
                                                        <td><input type="text" class="form-control" name="relationship[]"
                                                                value="<?= htmlspecialchars($row['relationship'] ?? '', ENT_QUOTES) ?>" />
                                                        </td>
                                                        <td><input type="text" class="form-control" name="relative_position[]"
                                                                value="<?= htmlspecialchars($row['relative_position'] ?? '', ENT_QUOTES) ?>" />
                                                        </td>
                                                        <td><input type="text" class="form-control" name="name_of_office_address[]"
                                                                value="<?= htmlspecialchars($row['name_of_office_address'] ?? '', ENT_QUOTES) ?>" />
                                                        </td>
                                                        <td><button type="button" class="saln-remove-row"
                                                                onclick="removeTableRow(this)" title="Remove"><i
                                                                    class='bx bx-x'></i></button></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td><input type="text" class="form-control" name="name_of_relative[]"
                                                            placeholder="Enter Name of Relative" /></td>
                                                    <td><input type="text" class="form-control" name="relationship[]"
                                                            placeholder="Enter Relationship" /></td>
                                                    <td><input type="text" class="form-control" name="relative_position[]"
                                                            placeholder="Enter Position" /></td>
                                                    <td><input type="text" class="form-control" name="name_of_office_address[]"
                                                            placeholder="Enter Office and Address" /></td>
                                                    <td><button type="button" class="saln-remove-row"
                                                            onclick="removeTableRow(this)" title="Remove"><i
                                                                class='bx bx-x'></i></button></td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <button type="button" class="custom-outline-blue mt-2" onclick="addRelativesRow()">
                                    <i class='bx bx-plus'></i> Add Relatives</button>
                            </div>

                            <div class="saln-action-bar">
                                <button type="button" class="btn btn-primary" name="save" id="save">
                                    <i class='bx bx-save'></i> Save
                                </button>
                                <button type="submit" class="btn text-white" name="download_excel" id="download_excel"
                                    style="background-color:var(--saln-success); border-color:var(--saln-success);">
                                    <i class='bx bx-download'></i> Download as Excel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../assets/vendor/libs/popper/popper.js"></script>
    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pages-account-settings-account.js"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>

    <script>
        const MAX_ROWS = 10;

        // ---- Birthdate validation (single definition, used by children rows) ----
        function validateBirthdate(input) {
            if (!input.value) return;
            const birthdate = new Date(input.value);
            const today = new Date();
            let age = today.getFullYear() - birthdate.getFullYear();
            const m = today.getMonth() - birthdate.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birthdate.getDate())) age--;

            if (birthdate > today) {
                alert("Birthdate cannot be in the future.");
                input.value = '';
                return;
            }
            if (age >= 18) {
                alert("Only children under 18 years old are allowed.");
                input.value = '';
            }
        }

        // ---- Generic row removal helpers ----
        function removeRow(button) {
            const container = document.getElementById("childrenContainer");
            if (container.querySelectorAll(".child-entry").length <= 1) {
                alert("At least one child entry must remain. Clear the fields instead if not applicable.");
                return;
            }
            button.closest(".child-entry").remove();
            updateChildrenBadge();
        }

        function removeTableRow(button) {
            const row = button.closest("tr");
            const tbody = row.parentElement;
            if (tbody.rows.length <= 1) {
                alert("At least one row must remain. Clear the fields instead if not applicable.");
                return;
            }
            row.remove();
        }

        function updateChildrenBadge() {
            const count = document.querySelectorAll("#childrenContainer .child-entry").length;
            const badge = document.getElementById("childrenBadge");
            if (badge) badge.textContent = count + (count === 1 ? " child" : " children");
        }

        // ---- Children ----
        let childCount = document.querySelectorAll("#childrenContainer .child-entry").length;

        function addChild() {
            if (childCount >= MAX_ROWS) {
                alert("You can only add up to " + MAX_ROWS + " children.");
                return;
            }
            childCount++;
            const container = document.getElementById("childrenContainer");
            const childDiv = document.createElement("div");
            childDiv.classList.add("saln-row-entry", "child-entry");
            childDiv.innerHTML = `
                <button type="button" class="saln-remove-row float-end" onclick="removeRow(this)" title="Remove"><i class='bx bx-x'></i></button>
                <div class="row align-items-end">
                    <div class="mb-3 col-md-6">
                        <label class="form-label">Name of Child ${childCount}</label>
                        <input class="form-control" type="text" name="children_FullName[]" placeholder="Input Child's Full Name"/>
                    </div>
                    <div class="mb-3 col-md-3">
                        <label class="form-label">Birthdate</label>
                        <input class="form-control" type="date" name="children_Birthdate[]" onchange="validateBirthdate(this)"/>
                    </div>
                    <div class="mb-3 col-md-3">
                        <label class="form-label">Age</label>
                        <input class="form-control" type="number" name="children_Age[]" placeholder="Enter Child's Age"/>
                    </div>
                </div>`;
            container.appendChild(childDiv);
            updateChildrenBadge();
        }

        // ---- Assets ----
        function addAssetRow() {
            addTableRow("assetsTable", `
                <td><input type="text" class="form-control" name="description[]" placeholder="Description"/></td>
                <td><input type="text" class="form-control" name="kind[]" placeholder="Kind"/></td>
                <td><input type="text" class="form-control" name="location[]" placeholder="Exact Location"/></td>
                <td><input type="text" class="form-control" name="assessed_value[]" placeholder="Assessed Value"/></td>
                <td><input type="text" class="form-control" name="fair_market_value[]" placeholder="Fair Market Value"/></td>
                <td><input type="number" class="form-control" name="acquisition_year[]" placeholder="Year"/></td>
                <td><input type="text" class="form-control" name="acquisition_mode[]" placeholder="Mode"/></td>
                <td><input type="text" class="form-control" name="acquisition_cost[]" placeholder="Cost"/></td>
                <td><button type="button" class="saln-remove-row" onclick="removeTableRow(this)" title="Remove"><i class='bx bx-x'></i></button></td>
            `, "assets");
        }

        // ---- Personal properties ----
        function addPropertyRow() {
            addTableRow("personalPropertiesTable", `
                <td><input type="text" class="form-control" name="personal_description[]" placeholder="Enter Property Description" /></td>
                <td><input type="number" class="form-control" name="yearAcquired[]" placeholder="Year Acquired" /></td>
                <td><input type="number" step="0.01" class="form-control" name="personal_acquisition_cost[]" placeholder="Acquisition Cost" /></td>
                <td><button type="button" class="saln-remove-row" onclick="removeTableRow(this)" title="Remove"><i class='bx bx-x'></i></button></td>
            `, "properties");
        }

        // ---- Liabilities ----
        function addLiabilitiesRow() {
            addTableRow("LiabilitiesTable", `
                <td><input type="text" class="form-control" name="nature[]" placeholder="Enter Nature Description" /></td>
                <td><input type="text" class="form-control" name="name_of_creditors[]" placeholder="Name of Creditors" /></td>
                <td><input type="number" step="0.01" class="form-control" name="outstandingBalance[]" placeholder="Outstanding Balance" /></td>
                <td><button type="button" class="saln-remove-row" onclick="removeTableRow(this)" title="Remove"><i class='bx bx-x'></i></button></td>
            `, "liabilities");
        }

        // ---- Business connections ----
        function addConnectionsRow() {
            addTableRow("BusinessTable", `
                <td><input type="text" class="form-control" name="business_enterprise[]" placeholder="Enter Business Enterprise" /></td>
                <td><input type="text" class="form-control" name="business_address[]" placeholder="Enter Business Address" /></td>
                <td><input type="text" class="form-control" name="nature_of_business[]" placeholder="Nature of Business" /></td>
                <td><input type="date" class="form-control" name="date_of_acquisition[]" placeholder="Date of Acquisition" /></td>
                <td><button type="button" class="saln-remove-row" onclick="removeTableRow(this)" title="Remove"><i class='bx bx-x'></i></button></td>
            `, "connections");
        }

        // ---- Relatives ----
        function addRelativesRow() {
            addTableRow("RelativesTable", `
                <td><input type="text" class="form-control" name="name_of_relative[]" placeholder="Enter Name of Relative" /></td>
                <td><input type="text" class="form-control" name="relationship[]" placeholder="Enter Relationship" /></td>
                <td><input type="text" class="form-control" name="relative_position[]" placeholder="Enter Position" /></td>
                <td><input type="text" class="form-control" name="name_of_office_address[]" placeholder="Enter Office and Address" /></td>
                <td><button type="button" class="saln-remove-row" onclick="removeTableRow(this)" title="Remove"><i class='bx bx-x'></i></button></td>
            `, "relatives");
        }

        // Shared helper: adds a row to any table, respecting the MAX_ROWS cap
        function addTableRow(tableId, rowHtml, label) {
            const tbody = document.getElementById(tableId).getElementsByTagName('tbody')[0];
            if (tbody.rows.length >= MAX_ROWS) {
                alert("You can only add up to " + MAX_ROWS + " " + label + ".");
                return;
            }
            const newRow = tbody.insertRow();
            newRow.innerHTML = rowHtml;
        }

        // ---- Save flow ----
        document.getElementById('save').addEventListener('click', function (e) {
            e.preventDefault();
            Swal.fire({
                title: 'Filing of SALN',
                text: 'Are you sure you want to save?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes',
                cancelButtonText: 'No',
                confirmButtonColor: '#007bff',
                cancelButtonColor: '#d33',
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Saved!',
                        text: 'Your SALN has been saved.',
                        confirmButtonColor: '#007bff'
                    }).then(() => {
                        document.getElementById('submitSALN').submit();
                    });
                }
            });
        });

        updateChildrenBadge();
    </script>
</body>

</html>