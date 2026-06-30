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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

    <!-- Icons. Uncomment required icon fonts -->
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="./css/fileSaln.css">

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="../assets/vendor/libs/apex-charts/apex-charts.css" />

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Calendar CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/qtip2/3.0.3/jquery.qtip.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qtip2/3.0.3/jquery.qtip.min.js"></script>
    <!-- Helpers -->
    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>

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
    <div class="container-xxl flex-grow-1 container-p-y">
        <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light"></span> File SALN (Statement of Assets,
            Liabilities, and Net Worth)</h4>
        <div class="row">
            <div class="col-md-12">
                <div class="card mb-4">
                    <!-- Account -->
                    <hr class="my-0" />
                    <div class="card-body">
                        <form method="POST" id="submitSALN" action="fileSaln.php">
                            <input type="hidden" name="save" value="1">
                            <div class="row">

                                <div class="row row-bordered g-0">
                                    <div class="col-md p-6">
                                        <h5 class="fw-medium d-block text-center">
                                            Note: Husband and wife who are both public officials and employees may file
                                            the required statements jointly or separately.
                                        </h5>
                                        <div class="d-flex justify-content-center">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="salnFiling"
                                                    id="salnFiling" value="Joint Filing"
                                                    <?= (isset($saln_data['salnFiling']) && $saln_data['salnFiling'] == 'Joint Filing') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="salnFiling">Joint Filing</label>
                                            </div>

                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="salnFiling"
                                                    id="salnFiling" value="Separate Filing"
                                                    <?= (isset($saln_data['salnFiling']) && $saln_data['salnFiling'] == 'Separate Filing') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="salnFiling">Separate Filing</label>
                                            </div>

                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="salnFiling"
                                                    id="salnFiling" value="Not Applicable"
                                                    <?= (isset($saln_data['salnFiling']) && $saln_data['salnFiling'] == 'Not Applicable') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="salnFiling">Not Applicable</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <hr style="border: 1px solid; width: 100%;"> <!-- Full-width black line -->

                                <h5>DECLARANT</h5>

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

                                <hr style="border: 1px solid; width: 100%;"> <!-- Full-width black line -->

                                <h5>SPOUSE</h5>

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

                                <hr style="border: 1px solid; width: 100%;"> <!-- Full-width black line -->
                                <h5 class="fw-medium d-block text-center">
                                    UNMARRIED CHILDREN BELOW EIGHTEEN (18) YEARS OF AGE LIVING IN DECLARANT’S HOUSEHOLD
                                </h5>
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
                                        <div class="child-entry">
                                            <div class="row align-items-end"> <!-- Align items at the bottom -->
                                                <div class="mb-3 col-md-6">
                                                    <label for="child<?= $i + 1 ?>FullName" class="form-label">Name of Child
                                                        <?= $i + 1 ?></label>
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
                                                <script>
                                                    function validateBirthdate(input) {
                                                        if (!input.value) return;

                                                        const birthdate = new Date(input.value);
                                                        const today = new Date();

                                                        let age = today.getFullYear() - birthdate.getFullYear();
                                                        const m = today.getMonth() - birthdate.getMonth();
                                                        if (m < 0 || (m === 0 && today.getDate() < birthdate.getDate())) {
                                                            age--;
                                                        }

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
                                                </script>

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

                                <button type="button" class="custom-outline-blue" onclick="addChild()"> + Add Child
                                </button>

                                <script>
                                    let childCount = <?= $totalChildren ?>;
                                    const maxChildren = 10;

                                    function addChild() {
                                        if (childCount >= maxChildren) {
                                            alert("You can only add up to 10 children.");
                                            return;
                                        }

                                        childCount++;
                                        const container = document.getElementById("childrenContainer");

                                        const childDiv = document.createElement("div");
                                        childDiv.classList.add("child-entry");
                                        childDiv.innerHTML = `
            <div class="row align-items-end"> <!-- Added align-items-end -->
                <div class="mb-3 col-md-6">
                    <label for="child${childCount}FullName" class="form-label">Name of Child ${childCount}</label>
                    <input class="form-control" type="text" id="child${childCount}FullName" name="children_FullName[]" 
                        placeholder="Input Child's Full Name"/>
                </div>
                <div class="mb-3 col-md-3">
                    <label for="birthdate${childCount}" class="form-label">Birthdate</label>
                    <input class="form-control" type="date" id="birthdate${childCount}" name="children_Birthdate[]" 
                        onchange="validateBirthdate(this)"/>
                </div>
                <div class="mb-3 col-md-3">
                    <label for="age${childCount}" class="form-label">Age</label>
                    <input class="form-control" type="number" id="age${childCount}" name="children_Age[]" 
                        placeholder="Enter Child's Age"/>
                </div>
            </div>
        `;

                                        container.appendChild(childDiv);
                                    }
                                </script>


                                <script>
                                    function validateBirthdate(input) {
                                        const birthdate = new Date(input.value);
                                        const today = new Date();
                                        const age = today.getFullYear() - birthdate.getFullYear();

                                        // Check if birthdate is in the future or age is 18+
                                        if (birthdate > today || age >= 18) {
                                            alert("Only children under 18 years old are allowed.");
                                            input.value = ''; // Clear the invalid input
                                        }
                                    }
                                </script>


                                <hr class="mt-2" style="border: 1px solid; width: 100%;"> <!-- Full-width black line -->
                                <h5 class="fw-medium d-block text-center">
                                    ASSETS, LIABILITIES AND NETWORTH</h5>
                                <p class="text-center">(Including those of the spouse and unmarried children below
                                    eighteen (18)
                                    years of age living in declarant’s household)
                                </p>
                                <!-- Real Properties Assets -->
                                <h5>I. ASSETS</h5>
                                <h6>a. Real Properties</h6>



                                <!-- Responsive Table Wrapper -->
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="assetsTable">
                                        <thead class="thead-dark">
                                            <tr>
                                                <th>Description</th>
                                                <th>Kind</th>
                                                <th>Exact Location</th>
                                                <th>Assessed Value</th>
                                                <th>Current Fair Market Value</th>
                                                <th>Acquisition Year</th>
                                                <th>Acquisition Mode</th>
                                                <th>Acquisition Cost</th>
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
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <button type="button" class="custom-outline-blue  mt-3 mb-3" onclick="addAssetRow()">+
                                    Add Asset</button>


                                <script>
                                    let assetCount = document.querySelectorAll("#assetsTable tbody tr").length;
                                    const assetMax = 10;

                                    function addAssetRow() {
                                        if (assetCount >= assetMax) {
                                            alert("You can only add up to 10 assets.");
                                            return;
                                        }
                                        assetCount++;

                                        const table = document.getElementById("assetsTable").getElementsByTagName('tbody')[0];
                                        const newRow = table.insertRow();
                                        newRow.innerHTML = `
        <td><input type="text" class="form-control" name="description[]" placeholder="Description"/></td>
        <td><input type="text" class="form-control" name="kind[]" placeholder="Kind"/></td>
        <td><input type="text" class="form-control" name="location[]" placeholder="Exact Location"/></td>
        <td><input type="text" class="form-control" name="assessed_value[]" placeholder="Assessed Value"/></td>
        <td><input type="text" class="form-control" name="fair_market_value[]" placeholder="Fair Market Value"/></td>
        <td><input type="number" class="form-control" name="acquisition_year[]" placeholder="Year"/></td>
        <td><input type="text" class="form-control" name="acquisition_mode[]" placeholder="Mode"/></td>
        <td><input type="text" class="form-control" name="acquisition_cost[]" placeholder="Cost"/></td>
    `;
                                    }
                                </script>
                                <!-- END Real Properties Assets -->

                                <!-- Personal Properties -->
                                <h6>b. Personal Properties</h6>
                                <table class="table table-bordered" id="personalPropertiesTable">
                                    <thead>
                                        <tr>
                                            <th>Description</th>
                                            <th>Year Acquired</th>
                                            <th>Acquisition Cost/Amount</th>
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
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                                <button type="button" class="custom-outline-blue mt-3 mb-3" onclick="addPropertyRow()">+
                                    Add Property</button>

                                <script>
                                    let propertyCount = document.querySelectorAll("#personalPropertiesTable tbody tr").length;
                                    const propertyMax = 10;

                                    function addPropertyRow() {
                                        if (propertyCount >= propertyMax) {
                                            alert("You can only add up to 10 personal properties.");
                                            return;
                                        }
                                        propertyCount++;

                                        const table = document.getElementById("personalPropertiesTable").getElementsByTagName('tbody')[0];
                                        const newRow = table.insertRow();
                                        newRow.innerHTML = `
        <td><input type="text" class="form-control" name="personal_description[]" placeholder="Enter Property Description" /></td>
        <td><input type="number" class="form-control" name="yearAcquired[]" placeholder="Year Acquired" /></td>
        <td><input type="number" step="0.01" class="form-control" name="personal_acquisition_cost[]" placeholder="Acquisition Cost" /></td>
    `;
                                    }
                                </script>


                                <h5>II. Liabilities</h5>
                                <table class="table table-bordered" id="LiabilitiesTable">
                                    <thead>
                                        <tr>
                                            <th>Nature</th>
                                            <th>Name of Creditors</th>
                                            <th>Outstanding Balance</th>
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
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <!-- Default empty row -->
                                            <tr>
                                                <td><input type="text" class="form-control" name="nature[]"
                                                        placeholder="Enter Nature Description" /></td>
                                                <td><input type="text" class="form-control" name="name_of_creditors[]"
                                                        placeholder="Name of Creditors" /></td>
                                                <td><input type="number" step="0.01" class="form-control"
                                                        name="outstandingBalance[]" placeholder="Outstanding Balance" />
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                                <button type="button" class="custom-outline-blue mt-3 mb-3"
                                    style="position:relative; top:10px;" onclick="addLiabilitiesRow()">+ Add
                                    Liabilities</button>

                                <script>
                                    let liabilitiesCount = document.getElementById("LiabilitiesTable").getElementsByTagName('tbody')[0].rows.length;
                                    const liabilitiesMax = 10;

                                    function addLiabilitiesRow() {
                                        if (liabilitiesCount >= liabilitiesMax) {
                                            alert("You can only add up to 10 liabilities.");
                                            return;
                                        }
                                        liabilitiesCount++;

                                        const table = document.getElementById("LiabilitiesTable").getElementsByTagName('tbody')[0];
                                        const newRow = table.insertRow();
                                        newRow.innerHTML = `
        <td><input type="text" class="form-control" name="nature[]" placeholder="Enter Nature Description" /></td>
        <td><input type="text" class="form-control" name="name_of_creditors[]" placeholder="Name of Creditors" /></td>
        <td><input type="number" step="0.01" class="form-control" name="outstandingBalance[]" placeholder="Outstanding Balance" /></td>
    `;
                                    }
                                </script>

                                <!-- Business Interest and Financial Connections -->
                                <h5>Business Interests and Financial Connections</h5>
                                <table class="table table-bordered" id="BusinessTable">
                                    <thead>
                                        <tr>
                                            <th>Name of Entity/Business Enterprise</th>
                                            <th>Business Address</th>
                                            <th>Nature of Business Interest &/Or Financial Connection</th>
                                            <th>Date of Acquisition of Interest or Connection</th>
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
                                            </tr>
                                        <?php endif; ?>
                                        <script>
                                            function validateBirthdate(input) {
                                                if (!input.value) return;

                                                const birthdate = new Date(input.value);
                                                const today = new Date();

                                                let age = today.getFullYear() - birthdate.getFullYear();
                                                const m = today.getMonth() - birthdate.getMonth();
                                                if (m < 0 || (m === 0 && today.getDate() < birthdate.getDate())) {
                                                    age--;
                                                }

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
                                        </script>

                                    </tbody>
                                </table>
                                <button type="button" class="custom-outline-blue mt-3 mb-3"
                                    style="position:relative; top:10px;" onclick="addConnectionsRow()">+ Add
                                    Connections</button>

                                <script>
                                    let connectionsCount = 1;
                                    const connectionsMax = 10;

                                    function addConnectionsRow() {
                                        if (connectionsCount >= connectionsMax) {
                                            alert("You can only add up to 10 Connections.");
                                            return;
                                        }
                                        connectionsCount++;

                                        const table = document.getElementById("BusinessTable").getElementsByTagName('tbody')[0];
                                        const newRow = table.insertRow();
                                        newRow.innerHTML = `
        <td><input type="text" class="form-control" name="business_enterprise[]" placeholder="Enter Business Enterprise" /></td>
        <td><input type="text" class="form-control" name="business_address[]" placeholder="Enter Business Address" /></td>
        <td><input type="text" class="form-control" name="nature_of_business[]" placeholder="Nature of Business" /></td>
        <td><input type="date" class="form-control" name="date_of_acquisition[]" placeholder="Date of Acquisition" /></td>
    `;
                                    }
                                </script>


                                <h5>Relatives in the Government Service</h5>
                                <table class="table table-bordered" id="RelativesTable">
                                    <thead>
                                        <tr>
                                            <th>Name of Relative</th>
                                            <th>Relationship</th>
                                            <th>Position</th>
                                            <th>Name of Agency/Office and Address</th>
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
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                                <button type="button" class="custom-outline-blue mt-3 mb-3"
                                    onclick="addRelativesRow()">+ Add Relatives</button>

                                <script>
                                    let relativesCount = 1;
                                    const relativesMax = 10;

                                    function addRelativesRow() {
                                        if (relativesCount >= relativesMax) {
                                            alert("You can only add up to 10 relatives.");
                                            return;
                                        }
                                        relativesCount++;

                                        const table = document.getElementById("RelativesTable").getElementsByTagName('tbody')[0];
                                        const newRow = table.insertRow();
                                        newRow.innerHTML = `
        <td><input type="text" class="form-control" name="name_of_relative[]" placeholder="Enter Name of Relative" /></td>
        <td><input type="text" class="form-control" name="relationship[]" placeholder="Enter Relationship" /></td>
        <td><input type="text" class="form-control" name="relative_position[]" placeholder="Enter Position" /></td>
        <td><input type="text" class="form-control" name="name_of_office_address[]" placeholder="Enter Office and Address" /></td>
    `;
                                    }
                                </script>

                                <div class="mt-2">
                                    <button type="button" class="btn btn-primary me-2" name="save" id="save"
                                        style="background-color: #007bff; border-color: #007bff; position:relative; top:-20px; left:10px;">Save</button>

                                    <button type="submit" class="btn btn-outline-secondary" name="download_excel"
                                        id="download_excel"
                                        style="background-color: #3CB371; border-color: #3CB371; color: white; position:relative; margin-left:20px; top: -20px;">Download
                                        as Excel</button>
                                </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="content-backdrop fade"></div>
    </div>
    </div>
    </div>
    <div class="layout-overlay layout-menu-toggle"></div>
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
        function toggleEdit() {
            let formFields = document.querySelectorAll("input");
            let editButton = document.getElementById("update");
            let submitButton = document.getElementById("save");

            formFields.forEach(field => field.disabled = !field.disabled);
            submitButton.style.display = formFields[0].disabled ? "none" : "block";
            editButton.style.display = formFields[0].disabled ? "block" : "none";
        }
    </script>
    <script>
        document.getElementById('save').addEventListener('click', function (e) {
            e.preventDefault(); // Prevent default form action

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
                    // Show the second SweetAlert
                    Swal.fire({
                        icon: 'success',
                        title: 'Saved!',
                        text: 'Your SALN has been saved.',
                        confirmButtonColor: '#007bff'
                    }).then(() => {
                        // Submit the form after the second alert
                        document.getElementById('submitSALN').submit();
                    });
                }
            });
        });
    </script>
</body>

</html>