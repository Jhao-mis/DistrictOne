<?php

require '../db.php'; // Database connection
require __DIR__ . '../../vendor/autoload.php';
require 'login_verification.php';


use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;


$username = $_SESSION['username'];

// Fetch user ID
$user_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
$user_check->bind_param("s", $username);
$user_check->execute();
$user_check->bind_result($user_id);
$user_check->fetch();
$user_check->close();

if (!$user_id) {
    die("Error: User not found.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['download_excel'])) {

    // Load the Excel template
    $templatePath = realpath(__DIR__ . "/2015-SALN-Form.xlsx");

    if (!$templatePath || !file_exists($templatePath)) {
        die("Error: Template not found.");
    }

    $spreadsheet = IOFactory::load($templatePath);
    $sheet = $spreadsheet->getSheet(0);

    // Fetch SALN data for this user
    $stmt = $conn->prepare("SELECT salnFiling,
        declarantFirstName, declarantMiddleName, declarantLastName, declarantAddress,
        declarantPosition, declarantAgency, declarantOfficeAddress,
        spouseFirstName, spouseMiddleName, spouseLastName,
        spousePosition, spouseAgency, spouseOfficeAddress
        FROM saln WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $saln = $result->fetch_assoc();
    $stmt->close();

    if (!$saln) {
        die("Error: No SALN data found.");
    }

    $salnFiling = trim($saln['salnFiling'] ?? '');


    if (strcasecmp($salnFiling, "Joint Filing") === 0) {
        $sheet->setCellValue('D6', "✓");
    } else {
        $sheet->setCellValue('D6', "");
    }
    
    if (strcasecmp($salnFiling, "Separate Filing") === 0) {
        $sheet->setCellValue('G6', "✓");
    } else {
        $sheet->setCellValue('G6', "");
    }
    
    if (strcasecmp($salnFiling, "Not Applicable") === 0) {
        $sheet->setCellValue('L6', "✓");
    } else {
        $sheet->setCellValue('L6', "");
    }
    

    $sheet->setCellValue('G3', date('m/d/Y')); 


    // Fill in values to Excel
    $sheet->setCellValue('D8', $saln['declarantLastName']);
    $sheet->setCellValue('E8', $saln['declarantFirstName']);
    $sheet->setCellValue('H8', $saln['declarantMiddleName']);
    $sheet->setCellValue('D10', $saln['declarantAddress']);

    $sheet->setCellValue('D12', $saln['spouseLastName']);
    $sheet->setCellValue('E12', $saln['spouseFirstName']);
    $sheet->setCellValue('H12', $saln['spouseMiddleName']);

    $sheet->setCellValue('N8', $saln['declarantPosition']);
    $sheet->setCellValue('N9', $saln['declarantAgency']);
    $sheet->setCellValue('N10', $saln['declarantOfficeAddress']);

    $sheet->setCellValue('N12', $saln['spousePosition']); // Shifted down to avoid overwrite
    $sheet->setCellValue('N13', $saln['spouseAgency']);
    $sheet->setCellValue('N14', $saln['spouseOfficeAddress']);

    $children_FullName = isset($_POST['children_FullName']) ? $_POST['children_FullName'] : [];
    $children_Birthdate = isset($_POST['children_Birthdate']) ? $_POST['children_Birthdate'] : [];
    $children_Age = isset($_POST['children_Age']) ? $_POST['children_Age'] : [];

    // Start inserting from row 17
    $startRow = 21;
    // Ensure at least one field is populated before looping
    if (!empty($children_FullName) || !empty($children_Birthdate) || !empty($children_Age)) {
        foreach ($children_FullName as $index => $type) {
            $birthdateValue = isset($children_Birthdate[$index]) ? date('m/d/Y', strtotime($children_Birthdate[$index])) : '';
            $ageValue = isset($children_Age[$index]) ? trim($children_Age[$index]) : '';


            // Insert into Excel (adjust column letters accordingly)
            $sheet->setCellValue('C' . $startRow, $type);
            $sheet->setCellValue('I' . $startRow, $birthdateValue);
            $sheet->setCellValue('P' . $startRow, $ageValue);


            // Move to the next row for the next set of data
            $startRow++;
        }
    }

    $description = isset($_POST['description']) ? $_POST['description'] : [];
    $kind = isset($_POST['kind']) ? $_POST['kind'] : [];
    $location = isset($_POST['location']) ? $_POST['location'] : [];
    $assessed_value = isset($_POST['assessed_value']) ? $_POST['assessed_value'] : [];
    $fair_market_value = isset($_POST['fair_market_value']) ? $_POST['fair_market_value'] : [];
    $acquisition_year = isset($_POST['acquisition_year']) ? $_POST['acquisition_year'] : [];
    $acquisition_mode = isset($_POST['acquisition_mode']) ? $_POST['acquisition_mode'] : [];
    $acquisition_cost = isset($_POST['acquisition_cost']) ? $_POST['acquisition_cost'] : [];


    $startRow = 32;
    // Ensure at least one field is populated before looping
    if (!empty($children_FullName) || !empty($children_Birthdate) || !empty($children_Age)) {
        foreach ($description as $index => $desc) {
            $kindValue = isset($kind[$index]) ? $kind[$index] : '';
            $locationValue = isset($location[$index]) ? $location[$index] : '';
            $assessedValue = isset($assessed_value[$index]) ? $assessed_value[$index] : '';
            $fairMarketValue = isset($fair_market_value[$index]) ? $fair_market_value[$index] : '';
            $acquisitionYear = isset($acquisition_year[$index]) ? $acquisition_year[$index] : '';
            $acquisitionMode = isset($acquisition_mode[$index]) ? $acquisition_mode[$index] : '';
            $acquisitionCost = isset($acquisition_cost[$index]) ? $acquisition_cost[$index] : '';
        

            $sheet->setCellValue('A' . $startRow, $desc);                // Description
            $sheet->setCellValue('D' . $startRow, $kindValue);           // Kind
            $sheet->setCellValue('E' . $startRow, $locationValue);       // Location
            $sheet->setCellValue('H' . $startRow, $assessedValue);       // Assessed Value
            $sheet->setCellValue('K' . $startRow, $fairMarketValue);     // Fair Market Value
            $sheet->setCellValue('N' . $startRow, $acquisitionYear);     // Year Acquired
            $sheet->setCellValue('O' . $startRow, $acquisitionMode);     // Mode of Acquisition
            $sheet->setCellValue('P' . $startRow, $acquisitionCost);     // Acquisition Cost

    $startRow++; // Go to the next row
        }
    }

    $personal_description = isset($_POST['personal_description']) ? $_POST['personal_description'] : [];
    $yearAcquired = isset($_POST['yearAcquired']) ? $_POST['yearAcquired'] : [];
    $personal_acquisition_cost = isset($_POST['personal_acquisition_cost']) ? $_POST['personal_acquisition_cost'] : [];

    // Start inserting from row 17
    $startRow = 39;
    // Ensure at least one field is populated before looping
    if (!empty($personal_description) || !empty($yearAcquired) || !empty($personal_acquisition_cost)) {
        foreach ($personal_description as $index => $descr) {
            $yearValue = isset($yearAcquired[$index]) ? trim($yearAcquired[$index]) : '';
            $personalcostValue = isset($personal_acquisition_cost[$index]) ? trim($personal_acquisition_cost[$index]) : '';


            // Insert into Excel (adjust column letters accordingly)
            $sheet->setCellValue('A' . $startRow, $descr);
            $sheet->setCellValue('J' . $startRow, $yearValue);
            $sheet->setCellValue('P' . $startRow, $personalcostValue);


            // Move to the next row for the next set of data
            $startRow++;
        }
    }

    $sheet = $spreadsheet->getSheet(1);

    $nature = isset($_POST['nature']) ? $_POST['nature'] : [];
    $name_of_creditors = isset($_POST['name_of_creditors']) ? $_POST['name_of_creditors'] : [];
    $outstandingBalance = isset($_POST['outstandingBalance']) ? $_POST['outstandingBalance'] : [];

    // Start inserting from row 17
    $startRow = 3;
    // Ensure at least one field is populated before looping
    if (!empty($nature) || !empty($name_of_creditors) || !empty($outstandingBalance)) {
        foreach ($nature as $index => $type) {
            $outstandingValue = isset($outstandingBalance[$index]) ? trim($outstandingBalance[$index]) : '';
            $creditorsValue = isset($name_of_creditors[$index]) ? trim($name_of_creditors[$index]) : '';


            // Insert into Excel (adjust column letters accordingly)
            $sheet->setCellValue('A' . $startRow, $type);
            $sheet->setCellValue('D' . $startRow, $creditorsValue);
            $sheet->setCellValue('H' . $startRow, $outstandingValue);


            // Move to the next row for the next set of data
            $startRow++;
        }
    }

    $sheet = $spreadsheet->getSheet(1);

    $business_enterprise = isset($_POST['business_enterprise']) ? $_POST['business_enterprise'] : [];
    $business_address = isset($_POST['business_address']) ? $_POST['business_address'] : [];
    $nature_of_business = isset($_POST['nature_of_business']) ? $_POST['nature_of_business'] : [];
    $date_of_acquisition = isset($_POST['date_of_acquisition']) ? $_POST['date_of_acquisition'] : [];


    $startRow = 12;
    // Ensure at least one field is populated before looping
    if (!empty($business_enterprise) || !empty($business_address) || !empty($nature_of_business) || !empty($date_of_acquisition)) {
        foreach ($business_enterprise as $index => $type) {
            $businessAddValue = isset($business_address[$index]) ? trim($business_address[$index]) : '';
            $natureBusValue = isset($nature_of_business[$index]) ? trim($nature_of_business[$index]) : '';
            $dateAcqValue = isset($date_of_acquisition[$index]) ? date('m/d/Y', strtotime($date_of_acquisition[$index])) : '';


            // Insert into Excel (adjust column letters accordingly)
            $sheet->setCellValue('A' . $startRow, $type);
            $sheet->setCellValue('C' . $startRow, $businessAddValue);
            $sheet->setCellValue('E' . $startRow, $natureBusValue);
            $sheet->setCellValue('H' . $startRow, $dateAcqValue);



            // Move to the next row for the next set of data
            $startRow++;
        }
    }

    $sheet = $spreadsheet->getSheet(1);

    $name_of_relative = isset($_POST['name_of_relative']) ? $_POST['name_of_relative'] : [];
    $relationship = isset($_POST['relationship']) ? $_POST['relationship'] : [];
    $relative_position = isset($_POST['relative_position']) ? $_POST['relative_position'] : [];
    $name_of_office_address = isset($_POST['name_of_office_address']) ? $_POST['name_of_office_address'] : [];


    $startRow = 17;
    // Ensure at least one field is populated before looping
    if (!empty($name_of_relative) || !empty($relationship) || !empty($relative_position) || !empty($name_of_office_address)) {
        foreach ($name_of_relative as $index => $type) {
            $relationshipValue = isset($relationship[$index]) ? trim($relationship[$index]) : '';
            $relationshipPosValue = isset($relative_position[$index]) ? trim($relative_position[$index]) : '';
            $nameOfficeValue = isset($name_of_office_address[$index]) ? trim($name_of_office_address[$index]) : '';


            // Insert into Excel (adjust column letters accordingly)
            $sheet->setCellValue('A' . $startRow, $type);
            $sheet->setCellValue('D' . $startRow, $relationshipValue);
            $sheet->setCellValue('E' . $startRow, $relationshipPosValue);
            $sheet->setCellValue('G' . $startRow, $nameOfficeValue);



            // Move to the next row for the next set of data
            $startRow++;
        }
    }


    $sheet = $spreadsheet->getSheet(1);
    $sheet->setCellValue('B22', date('m/d/Y')); 
    $fullName = $saln['declarantFirstName'] . ' ' . $saln['declarantMiddleName'] . ' ' . $saln['declarantLastName'];
    $sheet->setCellValue('B23', $fullName);
    $fullName = $saln['spouseFirstName'] . ' ' . $saln['spouseMiddleName'] . ' ' . $saln['spouseLastName'];
    $sheet->setCellValue('F23', $fullName);
    


    // Download as Excel
    $filename = "SALN " . $saln['declarantLastName'] . ".xlsx";

  // Clean output buffer first
if (ob_get_length()) ob_end_clean();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Expires: 0');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

}

//<--------------------------------------------------------------------------------------------END OF EXCEL------------------------------------------------------------------------------>

     //<------------------------FETCHING OF DATA------------------------>


    // Fetch existing leave request data
// Fetch existing SALN data
$sql = "SELECT salnFiling, declarantFirstName, declarantMiddleName, declarantLastName, declarantAddress,
declarantPosition, declarantAgency, declarantOfficeAddress, spouseFirstName, spouseMiddleName, spouseLastName, 
spousePosition, spouseAgency, spouseOfficeAddress FROM saln WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$saln_data = $result->fetch_assoc();
$stmt->close();


// Fetch SALN   child data of the user
$_SESSION['children_FullName'] = [];
$_SESSION['children_Birthdate'] = [];
$_SESSION['children_Age'] = [];

$stmt = $conn->prepare("SELECT children_FullName, children_Birthdate, children_Age FROM saln_children WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $_SESSION['children_FullName'][] = $row['children_FullName'];
    $_SESSION['children_Birthdate'][] = $row['children_Birthdate'];
    $_SESSION['children_Age'][] = $row['children_Age'];
}

// Fetch assets data of the user
$sql = "SELECT description, kind, location, assessed_value, fair_market_value, acquisition_year, acquisition_mode, acquisition_cost FROM saln_assets WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$assets_data = [];
while ($row = $result->fetch_assoc()) {
    $assets_data[] = $row;
}

$sql = "SELECT personal_description, yearAcquired, personal_acquisition_cost FROM saln_personal_properties WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$personal_properties_data = [];
while ($row = $result->fetch_assoc()) {
    $personal_properties_data[] = $row;
}

// Fetch liabilities data of the user
$sql = "SELECT nature, name_of_creditors, outstandingBalance FROM saln_liabilities WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$liabilities_data = [];
while ($row = $result->fetch_assoc()) {
    $liabilities_data[] = $row;
}

// Fetch business interest data of the user
$sql = "SELECT business_enterprise, business_address, nature_of_business, date_of_acquisition FROM saln_business_interest WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$business_data = [];
while ($row = $result->fetch_assoc()) {
    $business_data[] = $row;
}

// Fetch relatives data of the user
$sql = "SELECT name_of_relative, relationship, relative_position, name_of_office_address FROM saln_relatives WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$relatives_data = [];
while ($row = $result->fetch_assoc()) {
    $relatives_data[] = $row;
}

$sql = "SELECT * FROM saln"; // Replace 'your_table_name' with your actual table name
$result = $conn->query($sql);



    //<------------------------UPDATE DB------------------------>
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save'])) {




        // Retrieve and sanitize input data
    $salnFiling = trim($_POST['salnFiling'] ?? '');
    $declarantFirstName = trim($_POST['declarantFirstName'] ?? '');
        $declarantMiddleName = trim($_POST['declarantMiddleName'] ?? '');
        $declarantLastName = trim($_POST['declarantLastName'] ?? '');
        $declarantAddress = trim($_POST['declarantAddress'] ?? '');
        $declarantPosition = trim($_POST['declarantPosition'] ?? '');
        $declarantAgency = trim($_POST['declarantAgency'] ?? '');
        $declarantOfficeAddress = trim($_POST['declarantOfficeAddress'] ?? '');
        $spouseFirstName = trim($_POST['spouseFirstName'] ?? '');
        $spouseMiddleName = trim($_POST['spouseMiddleName'] ?? '');
        $spouseLastName = trim($_POST['spouseLastName'] ?? '');
        $spousePosition = trim($_POST['spousePosition'] ?? '');
        $spouseAgency = trim($_POST['spouseAgency'] ?? '');
        $spouseOfficeAddress = trim($_POST['spouseOfficeAddress'] ?? '');


        // Check if a leave request already exists for this user
        $check_query = $conn->prepare("SELECT id FROM saln WHERE user_id = ?");
        $check_query->bind_param("i", $user_id);
        $check_query->execute();
        $check_query->store_result();
        $record_exists = $check_query->num_rows > 0;
        $check_query->close();

        if ($record_exists) {
            // Update existing record
            $stmt = $conn->prepare("UPDATE saln SET 
            salnFiling = ?, declarantFirstName = ?, declarantMiddleName = ?,
            declarantLastName = ?, declarantAddress = ?, declarantPosition = ?, declarantAgency = ?, 
            declarantOfficeAddress = ?, spouseFirstName = ?, spouseMiddleName = ?, spouseLastName = ?, 
            spousePosition = ?, spouseAgency = ?, spouseOfficeAddress = ? WHERE user_id = ?");

            if (!$stmt) {
                die("Error preparing update statement: " . $conn->error);
            }

            $stmt->bind_param(
                "ssssssssssssssi",
                $salnFiling,
                $declarantFirstName,
                $declarantMiddleName,
                $declarantLastName,
                $declarantAddress,
                $declarantPosition,
                $declarantAgency,
                $declarantOfficeAddress,
                $spouseFirstName,
                $spouseMiddleName,
                $spouseLastName,
                $spousePosition,
                $spouseAgency,
                $spouseOfficeAddress,
                $user_id
            );

            if (!$stmt->execute()) {
                die("Error updating leave_request: " . $stmt->error);
            }
            $stmt->close();
        } else {
            // Insert new record
            $stmt = $conn->prepare("INSERT INTO saln 
            (salnFiling, declarantFirstName, declarantMiddleName, declarantLastName, 
            declarantAddress, declarantPosition, declarantAgency, declarantOfficeAddress, spouseFirstName, 
            spouseMiddleName, spouseLastName, spousePosition, spouseAgency, spouseOfficeAddress, user_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            if (!$stmt) {
                die("Error preparing insert statement: " . $conn->error);
            }

            $stmt->bind_param(
                "ssssssssssssssi",
                $salnFiling,
                $declarantFirstName,
                $declarantMiddleName,
                $declarantLastName,
                $declarantAddress,
                $declarantPosition,
                $declarantAgency,
                $declarantOfficeAddress,
                $spouseFirstName,
                $spouseMiddleName,
                $spouseLastName,
                $spousePosition,
                $spouseAgency,
                $spouseOfficeAddress,
                $user_id
            );

            if (!$stmt->execute()) {
                die("Error inserting into saln: " . $stmt->error);
            }
            $stmt->close();
        }

        // Update Children
    if (!empty($_POST['children_FullName'])) {
        // Delete existing children entries before inserting new ones
        $stmt = $conn->prepare("DELETE FROM saln_children WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO saln_children (user_id, children_FullName, children_Birthdate, children_Age) VALUES (?, ?, ?, ?)");

        foreach ($_POST['children_FullName'] as $index => $children_FullName) {
            if (!empty($children_FullName)) { // Ensure child name is not empty
                $children_Birthdate = $_POST['children_Birthdate'][$index] ?? '';
                $children_Age = $_POST['children_Age'][$index] ?? '';
                $stmt->bind_param("isss", $user_id, $children_FullName, $children_Birthdate, $children_Age);
                $stmt->execute();
            }
        }
        $stmt->close();
    }


        if (!empty($_POST['description'])) {
            // Delete previous assets for this user
            $stmt = $conn->prepare("DELETE FROM saln_assets WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        
            // Prepare the insert query
            $stmt = $conn->prepare("INSERT INTO saln_assets (user_id, description, kind, location, assessed_value, fair_market_value, acquisition_year, acquisition_mode, acquisition_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
            foreach ($_POST['description'] as $key => $desc) {
                $kind = $_POST['kind'][$key];
                $location = $_POST['location'][$key];
                $assessed_value = $_POST['assessed_value'][$key];
                $fair_market_value = $_POST['fair_market_value'][$key];
                $acquisition_year = $_POST['acquisition_year'][$key];
                $acquisition_mode = $_POST['acquisition_mode'][$key];
                $acquisition_cost = $_POST['acquisition_cost'][$key];
        
                $stmt->bind_param("isssddisd", $user_id, $desc, $kind, $location, $assessed_value, $fair_market_value, $acquisition_year, $acquisition_mode, $acquisition_cost);
                $stmt->execute();
            }
            $stmt->close();
        }

        if (!empty($_POST['personal_description'])) {
            // First, delete existing personal properties for the user
            $stmt = $conn->prepare("DELETE FROM saln_personal_properties WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        
            // Prepare insert statement
            $stmt = $conn->prepare("INSERT INTO saln_personal_properties (user_id, personal_description, yearAcquired, personal_acquisition_cost) VALUES (?, ?, ?, ?)");
        
            foreach ($_POST['personal_description'] as $index => $description) {
                $yearAcquired = $_POST['yearAcquired'][$index] ?? '';
                $acquisition_cost = $_POST['personal_acquisition_cost'][$index] ?? '';
        
                if (!empty($description) && !empty($yearAcquired) && !empty($acquisition_cost)) {
                    $stmt->bind_param("isid", $user_id, $description, $yearAcquired, $acquisition_cost);
                    $stmt->execute();
                }
            }
            $stmt->close();
        }
        // Update Liabilities
if (!empty($_POST['nature'])) {
    // Delete existing liabilities for the user before inserting new ones
    $stmt = $conn->prepare("DELETE FROM saln_liabilities WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    // Insert new liabilities records
    $stmt = $conn->prepare("INSERT INTO saln_liabilities (user_id, nature, name_of_creditors, outstandingBalance) VALUES (?, ?, ?, ?)");
    foreach ($_POST['nature'] as $index => $nature) {
        $name_of_creditors = $_POST['name_of_creditors'][$index] ?? '';
        $outstandingBalance = $_POST['outstandingBalance'][$index] ?? 0.00;
        $stmt->bind_param("issd", $user_id, $nature, $name_of_creditors, $outstandingBalance);
        $stmt->execute();
    }
    $stmt->close();
}
if (!empty($_POST['business_enterprise'])) {
    // Delete old data before inserting new ones
    $stmt = $conn->prepare("DELETE FROM saln_business_interest WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    // Insert new data
    $stmt = $conn->prepare("INSERT INTO saln_business_interest (user_id, business_enterprise, business_address, nature_of_business, date_of_acquisition) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($_POST['business_enterprise'] as $index => $business_enterprise) {
        $business_address = $_POST['business_address'][$index] ?? '';
        $nature_of_business = $_POST['nature_of_business'][$index] ?? '';
        $date_of_acquisition = $_POST['date_of_acquisition'][$index] ?? '';
        $stmt->bind_param("issss", $user_id, $business_enterprise, $business_address, $nature_of_business, $date_of_acquisition);
        $stmt->execute();
    }
    $stmt->close();
}

// Update Relatives Data
if (!empty($_POST['name_of_relative'])) {
    $stmt = $conn->prepare("DELETE FROM saln_relatives WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO saln_relatives (user_id, name_of_relative, relationship, relative_position, name_of_office_address) VALUES (?, ?, ?, ?, ?)");
    foreach ($_POST['name_of_relative'] as $index => $name_of_relative) {
        $relationship = $_POST['relationship'][$index] ?? '';
        $relative_position = $_POST['relative_position'][$index] ?? '';
        $name_of_office_address = $_POST['name_of_office_address'][$index] ?? '';
        $stmt->bind_param("issss", $user_id, $name_of_relative, $relationship, $relative_position, $name_of_office_address);
        $stmt->execute();
    }
    $stmt->close();
}

        // Redirect after successful update
        header("Location: fileSaln.php?success=1");
        exit();
    }
// Close database connection
?>