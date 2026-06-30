<?php

require '../db.php'; // Include database connection
require __DIR__ . '../../vendor/autoload.php';
require 'login_verification.php';



use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

$username = $_SESSION['username']; // Retrieve logged-in user username

// Establish database connection
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}


// Verify user exists in `users` table and retrieve their ID
$user_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
$user_check->bind_param("s", $username);
$user_check->execute();
$user_check->bind_result($id);
$user_check->fetch();
$user_check->close();

if (!$id) {
    die("Error: User does not exist in the database.");
}
// Fetch the current user details
$username = $_SESSION['username'];
$query = $conn->prepare("SELECT id, firstname, middlename, lastname, email, department FROM users WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->store_result();

if ($query->num_rows === 0) {
    die("User not found.");
}

$query->bind_result($user_id, $firstname, $middlename, $lastname, $email, $department);
$query->fetch();
$query->close();

// Verify user exists in `users` table and retrieve their ID
$user_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
$user_check->bind_param("s", $username);
$user_check->execute();
$user_check->bind_result($id);
$user_check->fetch();
$user_check->close();




if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['download_excel'])) {

    $conn = mysqli_connect($host, $user, $pass, $db);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $sql = "SELECT * FROM personal_data_sheet"; // Replace 'your_table_name' with your actual table name
    $result = $conn->query($sql);

    $sql2 = "SELECT * FROM family_background"; // Replace 'your_table_name' with your actual table name
    $result2 = $conn->query($sql2);

    $sql3 = "SELECT * FROM children"; // Replace 'your_table_name' with your actual table name
    $result3 = $conn->query($sql3);

    $sql4 = "SELECT * FROM educational_background"; // Replace 'your_table_name' with your actual table name
    $result4 = $conn->query($sql4);

    $sql5 = "SELECT * FROM eligibility"; // Replace 'your_table_name' with your actual table name
    $result5 = $conn->query($sql5);

    $sql6 = "SELECT * FROM learning_development"; // Replace 'your_table_name' with your actual table name
    $result6 = $conn->query($sql6);

    $sql7 = "SELECT * FROM other_info"; // Replace 'your_table_name' with your actual table name
    $result7 = $conn->query($sql7);

    $sql8 = "SELECT * FROM questions"; // Replace 'your_table_name' with your actual table name
    $result8 = $conn->query($sql8);

    $sql9 = "SELECT * FROM reference"; // Replace 'your_table_name' with your actual table name
    $result9 = $conn->query($sql9);

    $sql10 = "SELECT * FROM voluntary_work"; // Replace 'your_table_name' with your actual table name
    $result10 = $conn->query($sql10);

    $sql11 = "SELECT * FROM work_experience"; // Replace 'your_table_name' with your actual table name
    $result11 = $conn->query($sql11);




    // Load the Excel template
    $templatePath = realpath(__DIR__ . "/CS_Form_No_212_Personal_Data_Sheet.xlsx");

    if (!$templatePath || !file_exists($templatePath)) {
        die("Error: Template file not found. Resolved Path: " . ($templatePath ?: "Invalid Path"));
    }

    $spreadsheet = IOFactory::load($templatePath);
    $sheet = $spreadsheet->getActiveSheet();
    //C1--SHEET 1
    $sheet = $spreadsheet->getSheet(0); // Change to Sheet 2 (index 1)
    // Fill in the form
    $sheet->setCellValue('D11', $firstname);
    $sheet->setCellValue('D12', $middlename);
    $sheet->setCellValue('D10', $lastname);
    $sheet->setCellValue('L11', "NAME EXTENSION (JR., SR): " . ($_POST['name_extension'] ?? ''));



    date_default_timezone_set('Asia/Manila'); // Change to your timezone if needed

    $dateOfBirth = $_POST['personal_date_of_birth'] ?? '';

    // Convert and set the date if it's not empty
    if (!empty($dateOfBirth)) {
        // Apply the correct date format (mm/dd/yyyy)
        $sheet->getStyle('D13')->getNumberFormat()->setFormatCode('mm/dd/yyyy');
        $correctedDate = date('m/d/Y', strtotime($dateOfBirth));
        // Set the value in the cell
        $sheet->setCellValue('D13', $correctedDate);
    }

    $sheet->setCellValue('D15', $_POST['place_of_birth'] ?? '');


    if ($_POST["sex"] == "Male") {
        $sheet->setCellValue('D16', 'Male:  ✓        Female:'); // Assuming "Male" checkbox was at E15
    } elseif ($_POST["sex"] == "Female") {
        $sheet->setCellValue('D16', 'Male:           Female:   ✓'); // Assuming "Female" checkbox was at G15
    }

    if ($_POST["civil_status"] == "Single") {
        $sheet->setCellValue('D17', '        Single:  ✓     Married:      Widowed: ');
        $sheet->setCellValue('D20', '        Seperated/Divorce:   ');
    } elseif ($_POST["civil_status"] == "Married") {
        $sheet->setCellValue('D17', '        Single:          Married: ✓  Widowed:');
        $sheet->setCellValue('D20', '        Seperated/Divorce:   ');
    } elseif ($_POST["civil_status"] == "Widowed") {
        $sheet->setCellValue('D17', '       Single:          Married:    Widowed: ✓');
        $sheet->setCellValue('D20', '        Seperated/Divorce:   ');
    } elseif ($_POST["civil_status"] == "Seperated/Divorce") {
        $sheet->setCellValue('D17', '       Single:          Married:    Widowed: ');
        $sheet->setCellValue('D20', '        Seperated/Divorce: ✓  ');
    }

    // Assuming you are using PHPSpreadsheet
    $children = isset($_POST['children']) ? $_POST['children'] : [];
    $birthdates = isset($_POST['birthdate']) ? $_POST['birthdate'] : [];

    // Start inserting from row 37
    $startRow = 37;

    if (!empty($children) && !empty($birthdates)) {
        foreach ($children as $index => $child) {
            $childName = trim($child);
            $childDOB = isset($birthdates[$index]) ? trim($birthdates[$index]) : '';
            $correctedDate = (!empty($childDOB)) ? date('m/d/Y', strtotime($childDOB)) : '';
            // Insert into Excel
            $sheet->setCellValue('I' . $startRow, $childName);
            $sheet->setCellValue('M' . $startRow, $correctedDate);

            // Move to the next row for the next child
            $startRow++;
        }
    }







    $sheet->setCellValue('D22', $_POST['height'] ?? '');
    $sheet->setCellValue('D24', $_POST['weight'] ?? '');
    $sheet->setCellValue('D25', $_POST['blood_type'] ?? '');
    $sheet->setCellValue('D27', $_POST['gsis_id_no'] ?? '');
    $sheet->setCellValue('D29', $_POST['pagibig_id_no'] ?? '');
    $sheet->setCellValue('D31', $_POST['philhealth_no'] ?? '');
    $sheet->setCellValue('D32', $_POST['sss_no'] ?? '');
    $sheet->setCellValue('D33', $_POST['tin_no'] ?? '');
    $sheet->setCellValue('D34', $_POST['agency_employee_no'] ?? '');
    $sheet->setCellValue('D33', $_POST['tin_no'] ?? '');





    $sheet->setCellValue('I17', $_POST['RA_house_block_lot_no'] ?? '');
    $sheet->setCellValue('L17', $_POST['RA_street'] ?? '');
    $sheet->setCellValue('I19', $_POST['RA_subdivision_village'] ?? '');
    $sheet->setCellValue('L19', $_POST['RA_barangay'] ?? '');
    $sheet->setCellValue('I22', $_POST['RA_city_municipality'] ?? '');
    $sheet->setCellValue('L22', $_POST['RA_province'] ?? '');
    $sheet->setCellValue('I24', $_POST['RA_zip_code'] ?? '');

    $sheet->setCellValue('I25', $_POST['PA_house_block_lot_no'] ?? '');
    $sheet->setCellValue('L25', $_POST['PA_street'] ?? '');
    $sheet->setCellValue('I27', $_POST['PA_subdivision_village'] ?? '');
    $sheet->setCellValue('L27', $_POST['PA_barangay'] ?? '');
    $sheet->setCellValue('I29', $_POST['PA_city_municipality'] ?? '');
    $sheet->setCellValue('L29', $_POST['PA_province'] ?? '');
    $sheet->setCellValue('I31', $_POST['PA_zip_code'] ?? '');

    $sheet->setCellValue('I32', $_POST['telephone_no'] ?? '');
    $sheet->setCellValue('I33', $_POST['mobile_no'] ?? '');
    $sheet->setCellValue('I34', $_POST['email'] ?? '');

    $sheet->setCellValue('D36', $_POST['spouse_last_name'] ?? '');
    $sheet->setCellValue('D37', $_POST['spouse_first_name'] ?? '');
    $sheet->setCellValue('D38', $_POST['spouse_middle_name'] ?? '');
    $sheet->setCellValue('G37', "NAME EXTENSION (JR., SR): " . $_POST['spouse_extension_name'] ?? '');
    $sheet->setCellValue('L11', "NAME EXTENSION (JR., SR): " . ($_POST['name_extension'] ?? ''));
    $sheet->setCellValue('D39', $_POST['spouse_occupation'] ?? '');
    $sheet->setCellValue('D40', $_POST['spouse_employer_business_name'] ?? '');
    $sheet->setCellValue('D41', $_POST['spouse_business_address'] ?? '');
    $sheet->setCellValue('D42', $_POST['spouse_telephone_no'] ?? '');

    $sheet->setCellValue('D43', $_POST['father_last_name'] ?? '');
    $sheet->setCellValue('D44', $_POST['father_first_name'] ?? '');
    $sheet->setCellValue('D45', $_POST['father_middle_name'] ?? '');
    $sheet->setCellValue('G44', "NAME EXTENSION (JR., SR): " . $_POST['father_name_extension'] ?? '');

    $sheet->setCellValue('D47', $_POST['mother_last_name'] ?? '');
    $sheet->setCellValue('D48', $_POST['mother_first_name'] ?? '');
    $sheet->setCellValue('D49', $_POST['mother_middle_name'] ?? '');

    $sheet->setCellValue('D54', $_POST['elementarySchool'] ?? '');
    $sheet->setCellValue('G54', $_POST['basicEd'] ?? '');
    $sheet->setCellValue('M54', $_POST['elementaryYearGraduated'] ?? '');
    $sheet->setCellValue('J54', $_POST['From_1'] ?? '');
    $sheet->setCellValue('K54', $_POST['To_1'] ?? '');
    $sheet->setCellValue('L54', $_POST['LevelUnitsEarned'] ?? '');
    $sheet->setCellValue('N54', $_POST['scholar_honors'] ?? '');


    $sheet->setCellValue('D55', $_POST['highSchool'] ?? '');
    $sheet->setCellValue('G55', $_POST['basicEd2'] ?? '');
    $sheet->setCellValue('M55', $_POST['highSchoolYearGraduated'] ?? '');
    $sheet->setCellValue('J55', $_POST['From_2'] ?? '');
    $sheet->setCellValue('K55', $_POST['To_2'] ?? '');
    $sheet->setCellValue('L55', $_POST['LevelUnitsEarned2'] ?? '');
    $sheet->setCellValue('N55', $_POST['scholar_honors2'] ?? '');

    $sheet->setCellValue('D56', $_POST['vocTradeCourse'] ?? '');
    $sheet->setCellValue('G56', $_POST['vocTradeDegree'] ?? '');
    $sheet->setCellValue('M56', $_POST['vocTradeGraduated'] ?? '');
    $sheet->setCellValue('J56', $_POST['From_3'] ?? '');
    $sheet->setCellValue('K56', $_POST['To_3'] ?? '');
    $sheet->setCellValue('L56', $_POST['LevelUnitsEarned3'] ?? '');
    $sheet->setCellValue('N56', $_POST['scholar_honors4'] ?? '');

    $sheet->setCellValue('D57', $_POST['college'] ?? '');
    $sheet->setCellValue('G57', $_POST['collegeDegree'] ?? '');
    $sheet->setCellValue('M57', $_POST['collegeYearGraduated'] ?? '');
    $sheet->setCellValue('J57', $_POST['From_4'] ?? '');
    $sheet->setCellValue('K57', $_POST['To_4'] ?? '');
    $sheet->setCellValue('L57', $_POST['LevelUnitsEarned4'] ?? '');
    $sheet->setCellValue('N57', $_POST['scholar_honors3'] ?? '');

    $sheet->setCellValue('D58', $_POST['graduateSchool'] ?? '');
    $sheet->setCellValue('G58', $_POST['graduateDegree'] ?? '');
    $sheet->setCellValue('M58', $_POST['graduateYearGraduated'] ?? '');
    $sheet->setCellValue('J58', $_POST['From_5'] ?? '');
    $sheet->setCellValue('K58', $_POST['To_5'] ?? '');
    $sheet->setCellValue('L58', $_POST['LevelUnitsEarned5'] ?? '');
    $sheet->setCellValue('N58', $_POST['scholar_honors5'] ?? '');

    $citizenship = isset($_POST["citizenship"]) ? trim($_POST["citizenship"]) : "";
    $dual_holder = isset($_POST["dual_holder"]) ? trim($_POST["dual_holder"]) : ""; // Country name stored as dual_holder

    // Debugging: Print $_POST data (remove this after testing)
    error_log("Raw POST Data: " . print_r($_POST, true));

    // Check if Filipino or Dual Citizenship
    $filipino_check = ($citizenship === "Filipino") ? "✓" : "";
    $dual_check = ($citizenship === "Dual") ? "✓" : "";

    // ✅ Fix: Make sure Dual Citizenship check appears correctly
    $sheet->setCellValue('J13', "Filipino: $filipino_check        Dual Citizenship: $dual_check");

    // ✅ Fix: Ensure the country appears in J16 if Dual Citizenship is selected
    if ($citizenship === "Dual") {
        $sheet->setCellValue('J16', !empty($dual_holder) ? $dual_holder : "No country specified");
    } else {
        $sheet->setCellValue('J16', ""); // Clear cell if not Dual Citizenship
    }

    $sheet = $spreadsheet->getSheet(1);
    $eligibility_type = isset($_POST['eligibility']) ? $_POST['eligibility'] : [];
    $rating = isset($_POST['rating']) ? $_POST['rating'] : [];
    $exam_date = isset($_POST['examDate']) ? $_POST['examDate'] : [];
    $place_of_examination = isset($_POST['examPlace']) ? $_POST['examPlace'] : [];
    $license_no = isset($_POST['licenseNumber']) ? $_POST['licenseNumber'] : [];
    $license_no_validity = isset($_POST['validity']) ? $_POST['validity'] : [];

    // Start inserting from row 37
    $startRow = 5;
    // Ensure at least one field is populated before looping
    if (!empty($eligibility_type) || !empty($rating) || !empty($exam_date) || !empty($place_of_examination) || !empty($license_no) || !empty($license_no_validity)) {
        foreach ($eligibility_type as $index => $type) {
            $ratingValue = isset($rating[$index]) ? trim($rating[$index]) : '';
            $examDateValue = isset($exam_date[$index]) ? date('m/d/Y', strtotime($exam_date[$index])) : '';
            $examPlaceValue = isset($place_of_examination[$index]) ? trim($place_of_examination[$index]) : '';
            $licenseNumber = isset($license_no[$index]) ? trim($license_no[$index]) : '';
            $licenseValidity = isset($license_no_validity[$index]) ? date('m/d/Y', strtotime($license_no_validity[$index])) : '';

            // Insert into Excel (adjust column letters accordingly)
            $sheet->setCellValue('A' . $startRow, $type);
            $sheet->setCellValue('F' . $startRow, $ratingValue);
            $sheet->setCellValue('G' . $startRow, $examDateValue);
            $sheet->setCellValue('I' . $startRow, $examPlaceValue);
            $sheet->setCellValue('L' . $startRow, $licenseNumber);
            $sheet->setCellValue('M' . $startRow, $licenseValidity);

            // Move to the next row for the next set of data
            $startRow++;
        }
    }
    //C2--SHEET 2
    $sheet = $spreadsheet->getSheet(1);
    $position_title = isset($_POST['position_title']) ? $_POST['position_title'] : [];
    $department = isset($_POST['we_department']) ? $_POST['we_department'] : [];
    $work_from_date = isset($_POST['work_from_date']) ? $_POST['work_from_date'] : [];
    $work_to_date = isset($_POST['work_to_date']) ? $_POST['work_to_date'] : [];
    $monthly_salary = isset($_POST['monthly_salary']) ? $_POST['monthly_salary'] : [];
    $salary_grade = isset($_POST['salary_grade']) ? $_POST['salary_grade'] : [];
    $appointment_status = isset($_POST['appointment_status']) ? $_POST['appointment_status'] : [];
    $govt_service = isset($_POST['govt_service']) ? $_POST['govt_service'] : [];

    $startRow = 18;
    // Ensure at least one field is populated before looping
    if (
        !empty($position_title) || !empty($department) || !empty($work_from_date) || !empty($work_to_date) || !empty($monthly_salary) || !empty($salary_grade) || !empty($appointment_status)
        || !empty($govt_service)
    ) {
        foreach ($position_title as $index => $title) {
            $departmentValue = isset($department[$index]) ? trim($department[$index]) : '';
            $from_dateValue = isset($work_from_date[$index]) ? date('m/d/Y', strtotime($work_from_date[$index])) : '';
            $to_dateValue = isset($work_to_date[$index]) ? date('m/d/Y', strtotime($work_to_date[$index])) : '';
            $monthly_salaryValue = isset($monthly_salary[$index]) ? trim($monthly_salary[$index]) : '';
            $salary_gradeValue = isset($salary_grade[$index]) ? trim($salary_grade[$index]) : '';
            $appointment_statusValue = isset($appointment_status[$index]) ? trim($appointment_status[$index]) : '';
            $govt_serviceValue = isset($govt_service[$index]) ? trim($govt_service[$index]) : '';


            // Insert into Excel (adjust column letters accordingly)
            $sheet->setCellValue('A' . $startRow, $from_dateValue);
            $sheet->setCellValue('C' . $startRow, $to_dateValue);
            $sheet->setCellValue('D' . $startRow, $title);
            $sheet->setCellValue('G' . $startRow, $departmentValue);
            $sheet->setCellValue('J' . $startRow, $monthly_salaryValue);
            $sheet->setCellValue('K' . $startRow, $salary_gradeValue);
            $sheet->setCellValue('L' . $startRow, $appointment_statusValue);
            $sheet->setCellValue('M' . $startRow, $govt_serviceValue);

            // Move to the next row for the next set of data
            $startRow++;
        }
    }
    // C3--SHEET 3

    $sheet = $spreadsheet->getSheet(2);
    $organization = isset($_POST['organization']) ? $_POST['organization'] : [];
    $voluntary_from_date = isset($_POST['voluntary_from_date']) ? $_POST['voluntary_from_date'] : [];
    $voluntary_to_date = isset($_POST['voluntary_to_date']) ? $_POST['voluntary_to_date'] : [];
    $voluntary_hours = isset($_POST['voluntary_hours']) ? $_POST['voluntary_hours'] : [];
    $voluntary_position = isset($_POST['voluntary_position']) ? $_POST['voluntary_position'] : [];

    $startRow = 6;

    // Ensure at least one field is populated before looping
    if (!empty($organization) || !empty($voluntary_from_date) || !empty($voluntary_to_date) || !empty($voluntary_hours) || !empty($voluntary_position)) {
        foreach ($organization as $index => $type) {
            // Correcting variable references
            $from_dateValue = isset($voluntary_from_date[$index]) ? date('m/d/Y', strtotime($voluntary_from_date[$index])) : '';
            $to_dateValue = isset($voluntary_to_date[$index]) ? date('m/d/Y', strtotime($voluntary_to_date[$index])) : '';
            $hoursValue = isset($voluntary_hours[$index]) ? trim($voluntary_hours[$index]) : '';
            $voluntarypositionValue = isset($voluntary_position[$index]) ? trim($voluntary_position[$index]) : '';

            // Insert into Excel (adjust column letters accordingly)
            $sheet->setCellValue('A' . $startRow, $type);
            $sheet->setCellValue('E' . $startRow, $from_dateValue);
            $sheet->setCellValue('F' . $startRow, $to_dateValue);
            $sheet->setCellValue('G' . $startRow, $hoursValue);
            $sheet->setCellValue('H' . $startRow, $voluntarypositionValue);

            // Move to the next row for the next set of data
            $startRow++;
        }
    }

    $sheet = $spreadsheet->getSheet(2);
    $training_title = isset($_POST['training_title']) ? $_POST['training_title'] : [];
    $from_date = isset($_POST['from_date']) ? $_POST['from_date'] : [];
    $to_date = isset($_POST['to_date']) ? $_POST['to_date'] : [];
    $hours = isset($_POST['hours']) ? $_POST['hours'] : [];
    $type_of_ld = isset($_POST['type_of_ld']) ? $_POST['type_of_ld'] : [];
    $sponsor = isset($_POST['sponsor']) ? $_POST['sponsor'] : [];

    $startRow = 18;

    // Ensure at least one field is populated before looping
    if (!empty($training_title) || !empty($from_date) || !empty($to_date) || !empty($hours) || !empty($type_of_ld) || !empty($sponsor)) {
        foreach ($training_title as $index => $title) {
            // Correcting variable references
            $from_dateValue = isset($from_date[$index]) ? date('m/d/Y', strtotime($from_date[$index])) : '';
            $to_dateValue = isset($to_date[$index]) ? date('m/d/Y', strtotime($to_date[$index])) : '';
            $hoursValue = isset($hours[$index]) ? trim($hours[$index]) : '';
            $typeOfLDValue = isset($type_of_ld[$index]) ? trim($type_of_ld[$index]) : '';
            $sponsorValue = isset($sponsor[$index]) ? trim($sponsor[$index]) : '';

            // Insert into Excel (adjust column letters accordingly)
            $sheet->setCellValue('A' . $startRow, $title);
            $sheet->setCellValue('E' . $startRow, $from_dateValue);
            $sheet->setCellValue('F' . $startRow, $to_dateValue);
            $sheet->setCellValue('G' . $startRow, $hoursValue);
            $sheet->setCellValue('H' . $startRow, $typeOfLDValue);
            $sheet->setCellValue('I' . $startRow, $sponsorValue);
            // Move to the next row for the next set of data
            $startRow++;
        }
    }

    $sheet = $spreadsheet->getSheet(2);
    $skills_hobbies = isset($_POST['skillsTitle']) ? $_POST['skillsTitle'] : [];
    $non_academic = isset($_POST['nonAcad']) ? $_POST['nonAcad'] : [];
    $membership = isset($_POST['membership']) ? $_POST['membership'] : [];

    $startRow = 42;

    // Ensure at least one field is populated before looping
    if (!empty($skills_hobbies) || !empty($non_academic) || !empty($membership)) {
        foreach ($skills_hobbies as $index => $title) {
            // Correcting variable references
            $nonAcademicValue = isset($non_academic[$index]) ? trim($non_academic[$index]) : '';
            $membershipValue = isset($membership[$index]) ? trim($membership[$index]) : '';

            // Insert into Excel (adjust column letters accordingly)
            $sheet->setCellValue('A' . $startRow, $title);
            $sheet->setCellValue('C' . $startRow, $nonAcademicValue);
            $sheet->setCellValue('I' . $startRow, $membershipValue);
            // Move to the next row for the next set of data
            $startRow++;
        }
    }
    $sheet = $spreadsheet->getSheet(3);

    $startRow = 6;

    if ($_POST["q1"] == "Yes") {
        $sheet->setCellValue('H6', 'Yes:  ✓        No:');
        $sheet->setCellValue('H11', " " . $_POST['details1'] ?? '');
    } elseif ($_POST["q1"] == "No") {
        $sheet->setCellValue('H6', 'Yes:          No: ✓');
    }
    if ($_POST["q2"] == "Yes") {
        $sheet->setCellValue('H8', 'Yes:  ✓        No:');
        $sheet->setCellValue('H11', " " . $_POST['details2'] ?? '');
    } elseif ($_POST["q2"] == "No") {
        $sheet->setCellValue('H8', 'Yes:           No:   ✓');
    }
    if ($_POST["q3"] == "Yes") {
        $sheet->setCellValue('H13', 'Yes:  ✓        No:');
        $sheet->setCellValue('H15', " " . $_POST['details3'] ?? '');
    } elseif ($_POST["q3"] == "No") {
        $sheet->setCellValue('H13', 'Yes:           No:   ✓');
    }

    $date = $_POST['details4'] ?? '';
    if (!empty($date)) {
        $formattedDate = date('m/d/Y', strtotime($date)); // Convert to MM/DD/YYYY format
    } else {
        $formattedDate = ''; // Default to an empty string if no date is provided
    }
    if ($_POST["q4"] == "Yes") {
        $sheet->setCellValue('H18', 'Yes:  ✓        No:');
        $sheet->setCellValue('K20', $formattedDate);
        $sheet->setCellValue('K21', " " . $_POST['details41'] ?? '');
    } elseif ($_POST["q4"] == "No") {
        $sheet->setCellValue('H18', 'Yes:           No:   ✓');
    }
    if ($_POST["q5"] == "Yes") {
        $sheet->setCellValue('H23', 'Yes:  ✓        No:');
        $sheet->setCellValue('H25', " " . $_POST['details5'] ?? '');
    } elseif ($_POST["q5"] == "No") {
        $sheet->setCellValue('H23', 'Yes:           No:   ✓');
    }
    if ($_POST["q6"] == "Yes") {
        $sheet->setCellValue('H27', 'Yes:  ✓        No:');
        $sheet->setCellValue('H29', " " . $_POST['details6'] ?? '');
    } elseif ($_POST["q6"] == "No") {
        $sheet->setCellValue('H27', 'Yes:           No:   ✓');
    }
    if ($_POST["q7"] == "Yes") {
        $sheet->setCellValue('H31', 'Yes:  ✓        No:');
        $sheet->setCellValue('K32', " " . $_POST['details7'] ?? '');
    } elseif ($_POST["q7"] == "No") {
        $sheet->setCellValue('H31', 'Yes:           No:   ✓');
    }
    if ($_POST["q8"] == "Yes") {
        $sheet->setCellValue('H34', 'Yes:  ✓        No:');
        $sheet->setCellValue('K35', " " . $_POST['details8'] ?? '');
    } elseif ($_POST["q8"] == "No") {
        $sheet->setCellValue('H34', 'Yes:           No:   ✓');
    }
    if ($_POST["q9"] == "Yes") {
        $sheet->setCellValue('H37', 'Yes:  ✓        No:');
        $sheet->setCellValue('H39', " " . $_POST['details9'] ?? '');
    } elseif ($_POST["q9"] == "No") {
        $sheet->setCellValue('H37', 'Yes:           No:   ✓');
    }
    if ($_POST["q10"] == "Yes") {
        $sheet->setCellValue('H41', 'A. Yes:  ✓        No:');
        $sheet->setCellValue('L44', " " . $_POST['details10'] ?? '');
    } elseif ($_POST["q10"] == "No") {
        $sheet->setCellValue('H41', 'A. Yes:           No:   ✓');
    }
    if ($_POST["q11"] == "Yes") {
        $sheet->setCellValue('L41', 'B. Yes:  ✓        No:');
        $sheet->setCellValue('L46', " " . $_POST['details11'] ?? '');
    } elseif ($_POST["q11"] == "No") {
        $sheet->setCellValue('L41', 'B. Yes:           No:   ✓');
    }
    if ($_POST["q12"] == "Yes") {
        $sheet->setCellValue('H43', 'C. Yes:  ✓        No:');
        $sheet->setCellValue('L48', " " . $_POST['details12'] ?? '');
    } elseif ($_POST["q11"] == "No") {
        $sheet->setCellValue('H43', 'C. Yes:           No:   ✓');
    }

    $refName1 = isset($_POST['refName1']) ? $_POST['refName1'] : [];
    $refAddress1 = isset($_POST['refAddress1']) ? $_POST['refAddress1'] : [];
    $refPhone1 = isset($_POST['refPhone1']) ? $_POST['refPhone1'] : [];

    $startRow = 52;

    // Ensure at least one field is populated before looping
    if (!empty($refName1) || !empty($refAddress1) || !empty($refPhone1)) {
        foreach ($refName1 as $index => $nameValue) {
            // Correcting variable references
            $addressValue = isset($refAddress1[$index]) ? trim($refAddress1[$index]) : '';
            $phoneValue = isset($refPhone1[$index]) ? trim($refPhone1[$index]) : '';

            // Insert into Excel (adjust column letters accordingly)
            $sheet->setCellValue('A' . $startRow, $nameValue);
            $sheet->setCellValue('F' . $startRow, $addressValue);
            $sheet->setCellValue('G' . $startRow, $phoneValue);
            // Move to the next row for the next set of data
            $startRow++;
        }
    }

    // Set filename for download
    $filename = "Personal_Data_Sheet_" . $lastname . ".xls";

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

    $writer = new Xls($spreadsheet); // Correct writer for .xls
    ob_end_clean(); // Clean any previous output
    $writer->save('php://output');
}
//<--------------------------------------------------------------------------------------------END OF EXCEL------------------------------------------------------------------------------>



//<------------------------FETCHING OF DATA------------------------>

// Now fetch the personal data sheet using the `user_id`
$sql = "SELECT * FROM personal_data_sheet WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close(); // Close statement



// Fetch personal data including sex
$sql = "SELECT sex, employeeSalaryGrade, sgStep, salary, civil_status, blood_type, citizenship, dual_holder, personal_date_of_birth
        FROM personal_data_sheet WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($sex, $employeeSalaryGrade, $sgStep, $salary, $civil_status, $blood_type, $citizenship, $dual_holder, $personal_date_of_birth);
$stmt->fetch();
$stmt->close(); // Close statement

// Ensure proper handling of NULL values
$sex = $sex ?? ''; // If NULL, set empty string
$personal_date_of_birth = $personal_date_of_birth ? htmlspecialchars($personal_date_of_birth, ENT_QUOTES) : '';

// Fetch the latest family_background data AFTER updating
$sql = "SELECT * FROM family_background WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$family_data = $result->fetch_assoc();
$stmt->close();

// Now fetch the educational background using the `user_id`
$sql = "SELECT * FROM educational_background WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$education_data = $result->fetch_assoc();
$stmt->close(); // Close statement

// Now fetch the other info questions using the `user_id`
$sql = "SELECT * FROM questions WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$question_data = $result->fetch_assoc();
$stmt->close(); // Close statement

// Fetch personal data including sex
$sql = "SELECT q1, q2, details2, q3, details3, q4, details41, details4, q5, details5, q6, details6, q7, details7,
        q8, details8, q9, details9, q10, details10, q11, details11, q12, details12
        FROM questions WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result(
    $q1,
    $q2,
    $details2,
    $q3,
    $details3,
    $q4,
    $details41,
    $details4,
    $q5,
    $details5,
    $q6,
    $details6,
    $q7,
    $details7,
    $q8,
    $details8,
    $q9,
    $details9,
    $q10,
    $details10,
    $q11,
    $details11,
    $q12,
    $details12
);
$stmt->fetch();
$stmt->close(); // Close statement

// Fetch eligibility data of the user
$sql = "SELECT eligibility_type, rating, exam_date, exam_place, license_number, validity FROM eligibility WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$eligibility_data = [];
while ($row = $result->fetch_assoc()) {
    $eligibility_data[] = $row;
}

$stmt->close(); // Close statement

// Fetch child data of the user
$_SESSION['children'] = [];
$_SESSION['birthdate'] = [];

$stmt = $conn->prepare("SELECT full_name, birthdate FROM children WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $_SESSION['children'][] = $row['full_name'];
    $_SESSION['birthdate'][] = $row['birthdate'];
}

$stmt->close();

// Fetch voluntary work data
$sql = "SELECT organization, voluntary_from_date, voluntary_to_date, voluntary_hours, voluntary_position FROM voluntary_work WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_voluntary = $stmt->get_result();
$stmt->close();

// Fetch other info data
// Ensure session arrays are initialized only if they are empty (to prevent accidental reset)
if (!isset($_SESSION['skillsTitle'])) {
    $_SESSION['skillsTitle'] = [];
}
if (!isset($_SESSION['nonAcad'])) {
    $_SESSION['nonAcad'] = [];
}
if (!isset($_SESSION['membership'])) {
    $_SESSION['membership'] = [];
}

// Fetch skills and related data from the correct table (assuming 'users' table instead of 'children')
$stmt = $conn->prepare("SELECT skills_hobbies, non_academic, membership FROM other_info WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Clear session arrays before refilling them
$_SESSION['skillsTitle'] = [];
$_SESSION['nonAcad'] = [];
$_SESSION['membership'] = [];

while ($row = $result->fetch_assoc()) {
    $_SESSION['skillsTitle'][] = $row['skills_hobbies'];
    $_SESSION['nonAcad'][] = $row['non_academic'];
    $_SESSION['membership'][] = $row['membership'];
}

$stmt->close();


// Fetch Learning and Development (L&D) data
$sql = "SELECT training_title, from_date, to_date, hours, type_of_ld, sponsor FROM learning_development WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$learning_development_data = [];
while ($row = $result->fetch_assoc()) {
    $learning_development_data[] = $row;
}

$stmt->close(); // Close statement


// Fetch work experience data
$sql = "SELECT position_title, we_department, work_from_date, work_to_date, monthly_salary, salary_grade, appointment_status, govt_service FROM work_experience WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$work_result = $stmt->get_result();


$stmt->close(); // Close statement

// Fetch reference data of the user
$sql = "SELECT refName1, refAddress1, refPhone1 FROM reference WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$reference_data = [];
while ($row = $result->fetch_assoc()) {
    $reference_data[] = $row;
}

$stmt->close(); // Close statement

//<------------------------END OF FETCHING OF DATA------------------------>

//<------------------------UPDATE DB------------------------>
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Retrieve and sanitize input data
    $name_extension = trim($_POST['name_extension'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $date_hired = trim($_POST['date_hired'] ?? '');
    $place_of_birth = trim($_POST['place_of_birth'] ?? '');
    $personal_date_of_birth = trim($_POST['personal_date_of_birth'] ?? '');
    $sex = trim($_POST['sex'] ?? '');
    $employeeSalaryGrade = trim($_POST['employeeSalaryGrade'] ?? '');
    $sgStep = trim($_POST['sgStep'] ?? '');
    $salary = trim($_POST['salary'] ?? '');
    $civil_status = trim($_POST['civil_status'] ?? '');
    $height = trim($_POST['height'] ?? '');
    $weight = trim($_POST['weight'] ?? '');
    $blood_type = trim($_POST['blood_type'] ?? '');
    $gsis_id_no = trim($_POST['gsis_id_no'] ?? '');
    $pagibig_id_no = trim($_POST['pagibig_id_no'] ?? '');
    $philhealth_no = trim($_POST['philhealth_no'] ?? '');
    $sss_no = trim($_POST['sss_no'] ?? '');
    $tin_no = trim($_POST['tin_no'] ?? '');
    $agency_employee_no = trim($_POST['agency_employee_no'] ?? '');
    $RA_zip_code = trim($_POST['RA_zip_code'] ?? '');
    $PA_zip_code = trim($_POST['PA_zip_code'] ?? '');
    $telephone_no = trim($_POST['telephone_no'] ?? '');
    $mobile_no = trim($_POST['mobile_no'] ?? '');
    $citizenship = trim($_POST['citizenship'] ?? '');
    $dual_holder = ($citizenship === "Dual" && !empty($_POST['dual_holder'])) ? trim($_POST['dual_holder']) : NULL;
    $RA_house_block_lot_no = trim($_POST['RA_house_block_lot_no'] ?? '');
    $RA_subdivision_village = trim($_POST['RA_subdivision_village'] ?? '');
    $RA_province = trim($_POST['RA_province'] ?? '');
    $RA_city_municipality = trim($_POST['RA_city_municipality'] ?? '');
    $RA_street = trim($_POST['RA_street'] ?? '');
    $RA_barangay = trim($_POST['RA_barangay'] ?? '');
    $PA_house_block_lot_no = trim($_POST['PA_house_block_lot_no'] ?? '');
    $PA_subdivision_village = trim($_POST['PA_subdivision_village'] ?? '');
    $PA_city_municipality = trim($_POST['PA_city_municipality'] ?? '');
    $PA_street = trim($_POST['PA_street'] ?? '');
    $PA_barangay = trim($_POST['PA_barangay'] ?? '');
    $PA_province = trim($_POST['PA_province'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($user_data) {
        // Update personal_data_sheet
        $stmt = $conn->prepare("UPDATE personal_data_sheet SET
            position = ?, department = ?, date_hired = ?, employeeSalaryGrade = ?, sgStep = ?, salary = ?, name_extension = ?, personal_date_of_birth = ?, place_of_birth = ?, sex = ?, civil_status = ?,
            height = ?, weight = ?, blood_type = ?, gsis_id_no = ?, pagibig_id_no = ?,
            philhealth_no = ?, sss_no = ?, tin_no = ?, agency_employee_no = ?, citizenship = ?,
            dual_holder = ?,
            RA_house_block_lot_no = ?, RA_subdivision_village = ?, RA_city_municipality = ?, RA_street = ?,
            RA_barangay = ?, RA_province = ?, RA_zip_code = ?,
            PA_house_block_lot_no = ?, PA_subdivision_village = ?, PA_city_municipality = ?, PA_street = ?,
            PA_barangay = ?, PA_province = ?, PA_zip_code = ?, telephone_no = ?, mobile_no = ?
            WHERE user_id = ?");

        if (!$stmt) {
            die("Error preparing update statement: " . $conn->error);
        }

        $stmt->bind_param(
            "sssssissssssssiiiiiissssssssssssssssssi",
            $_POST['position'],
            $_POST['department'],
            $_POST['date_hired'],
            $_POST['employeeSalaryGrade'],
            $_POST['sgStep'],
            $_POST['salary'],
            $_POST['name_extension'],
            $_POST['personal_date_of_birth'],
            $_POST['place_of_birth'],
            $_POST['sex'],
            $_POST['civil_status'],
            $_POST['height'],
            $_POST['weight'],
            $_POST['blood_type'],
            $_POST['gsis_id_no'],
            $_POST['pagibig_id_no'],
            $_POST['philhealth_no'],
            $_POST['sss_no'],
            $_POST['tin_no'],
            $_POST['agency_employee_no'],
            $citizenship,
            $dual_holder,
            $_POST['RA_house_block_lot_no'],
            $_POST['RA_subdivision_village'],
            $_POST['RA_city_municipality'],
            $_POST['RA_street'],
            $_POST['RA_barangay'],
            $_POST['RA_province'],
            $_POST['RA_zip_code'],
            $_POST['PA_house_block_lot_no'],
            $_POST['PA_subdivision_village'],
            $_POST['PA_city_municipality'],
            $_POST['PA_street'],
            $_POST['PA_barangay'],
            $_POST['PA_province'],
            $_POST['PA_zip_code'],
            $_POST['telephone_no'],
            $_POST['mobile_no'],
            $user_id
        );

        if (!$stmt->execute()) {
            die("Error updating personal_data_sheet: " . $stmt->error);
        }

        $stmt->close(); // Close only after execution
    } else {
        // INSERT new record
        $insert_query = $conn->prepare("INSERT INTO personal_data_sheet
        (firstname, middlename, lastname, name_extension, department, position, date_hired, employeeSalaryGrade, sgStep, salary, personal_date_of_birth, place_of_birth, sex, civil_status,
        height, weight, blood_type, gsis_id_no, pagibig_id_no, philhealth_no,
        sss_no, tin_no, agency_employee_no, citizenship, dual_holder,
        RA_house_block_lot_no, RA_subdivision_village, RA_province,
        RA_city_municipality, RA_street, RA_barangay, RA_zip_code,
        PA_house_block_lot_no, PA_subdivision_village, PA_city_municipality,
        PA_street, PA_barangay, PA_province, PA_zip_code, telephone_no,
        mobile_no, email, user_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$insert_query) {
            die("Error preparing insert statement: " . $conn->error);
        }


        $insert_query->bind_param(
            "sssssssssissssssiiiiiissssssssssssssssssssi",
            $firstname,
            $middlename,
            $lastname,
            $name_extension,
            $department,
            $position,
            $date_hired,
            $employeeSalaryGrade,
            $sgStep,
            $salary,
            $personal_date_of_birth,
            $place_of_birth,
            $sex,
            $civil_status,
            $height,
            $weight,
            $blood_type,
            $gsis_id_no,
            $pagibig_id_no,
            $philhealth_no,
            $sss_no,
            $tin_no,
            $agency_employee_no,
            $citizenship,
            $dual_holder,
            $RA_house_block_lot_no,
            $RA_subdivision_village,
            $RA_province,
            $RA_city_municipality,
            $RA_street,
            $RA_barangay,
            $RA_zip_code,
            $PA_house_block_lot_no,
            $PA_subdivision_village,
            $PA_city_municipality,
            $PA_street,
            $PA_barangay,
            $PA_province,
            $PA_zip_code,
            $telephone_no,
            $mobile_no,
            $email,
            $user_id
        );

        if (!$insert_query->execute()) {
            die("Error inserting into personal_data_sheet: " . $insert_query->error);
        }

        $insert_query->close(); // Close only after execution
    }


    //Family Background
    $spouse_first_name = $_POST['spouse_first_name'] ?? '';
    $spouse_extension_name = $_POST['spouse_extension_name'] ?? '';
    $spouse_middle_name = $_POST['spouse_middle_name'] ?? '';
    $spouse_last_name = $_POST['spouse_last_name'] ?? '';
    $spouse_occupation = $_POST['spouse_occupation'] ?? '';
    $spouse_employer_business_name = $_POST['spouse_employer_business_name'] ?? '';
    $spouse_business_address = $_POST['spouse_business_address'] ?? '';
    $spouse_telephone_no = $_POST['spouse_telephone_no'] ?? '';

    $father_first_name = $_POST['father_first_name'] ?? '';
    $father_extension_name = $_POST['father_extension_name'] ?? '';
    $father_middle_name = $_POST['father_middle_name'] ?? '';
    $father_last_name = $_POST['father_last_name'] ?? '';

    $mother_first_name = $_POST['mother_first_name'] ?? '';
    $mother_middle_name = $_POST['mother_middle_name'] ?? '';
    $mother_last_name = $_POST['mother_last_name'] ?? '';

    if ($family_data) {
        //Update Familiy Background

        $stmt = $conn->prepare("UPDATE family_background SET
        spouse_first_name = ?, spouse_extension_name = ?, spouse_middle_name = ?, spouse_last_name = ?, spouse_occupation = ?,
        spouse_employer_business_name = ?, spouse_business_address = ?, spouse_telephone_no = ?, father_first_name = ?, father_name_extension = ?,
        father_middle_name = ?, father_last_name = ?, mother_first_name = ?, mother_middle_name = ?, mother_last_name = ?
        WHERE user_id = ?");

        $stmt->bind_param(
            "sssssssssssssssi",
            $_POST['spouse_first_name'],
            $_POST['spouse_extension_name'],
            $_POST['spouse_middle_name'],
            $_POST['spouse_last_name'],
            $_POST['spouse_occupation'],
            $_POST['spouse_employer_business_name'],
            $_POST['spouse_business_address'],
            $_POST['spouse_telephone_no'],
            $_POST['father_first_name'],
            $_POST['father_name_extension'],
            $_POST['father_middle_name'],
            $_POST['father_last_name'],
            $_POST['mother_first_name'],
            $_POST['mother_middle_name'],
            $_POST['mother_last_name'],
            $user_id
        );

        if (!$stmt->execute()) {
            die("Error updating family_background: " . $stmt->error);
        }

        $stmt->close(); // Close only after execution

    } else {
        $insert_query = $conn->prepare("INSERT INTO family_background (user_id, spouse_first_name, spouse_extension_name, spouse_middle_name, spouse_last_name, spouse_occupation, spouse_employer_business_name, spouse_business_address, spouse_telephone_no, father_first_name, father_name_extension, father_middle_name, father_last_name, mother_first_name, mother_middle_name, mother_last_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$insert_query) {
            die("Statement preparation failed: " . $conn->error);
        }

        // Bind parameters
        $insert_query->bind_param(
            "isssssssssssssss",
            $user_id,
            $spouse_first_name,
            $spouse_extension_name,
            $spouse_middle_name,
            $spouse_last_name,
            $spouse_occupation,
            $spouse_employer_business_name,
            $spouse_business_address,
            $spouse_telephone_no,
            $father_first_name,
            $father_extension_name,
            $father_middle_name,
            $father_last_name,
            $mother_first_name,
            $mother_middle_name,
            $mother_last_name
        );
        if (!$insert_query->execute()) {
            die("Error inserting into family_background: " . $insert_query->error);
        }

        $insert_query->close(); // Close only after execution
    }


    //Educational Background
    $elementarySchool = $_POST['elementarySchool'] ?? '';
    $basicEd = $_POST['basicEd'] ?? '';
    $elementaryYearGraduated = $_POST['elementaryYearGraduated'] ?? '';
    $From_1 = $_POST['From_1'] ?? '';
    $To_1 = $_POST['To_1'] ?? '';
    $LevelUnitsEarned = $_POST['LevelUnitsEarned'] ?? '';
    $scholar_honors = $_POST['scholar_honors'] ?? '';

    $highSchool = $_POST['highSchool'] ?? '';
    $basicEd2 = $_POST['basicEd2'] ?? '';
    $highSchoolYearGraduated = $_POST['highSchoolYearGraduated'] ?? '';
    $From_2 = $_POST['From_2'] ?? '';
    $To_2 = $_POST['To_2'] ?? '';
    $LevelUnitsEarned2 = $_POST['LevelUnitsEarned2'] ?? '';
    $scholar_honors2 = $_POST['scholar_honors2'] ?? '';

    $college = $_POST['college'] ?? '';
    $collegeDegree = $_POST['collegeDegree'] ?? '';
    $collegeYearGraduated = $_POST['collegeYearGraduated'] ?? '';
    $From_3 = $_POST['From_3'] ?? '';
    $To_3 = $_POST['To_3'] ?? '';
    $LevelUnitsEarned3 = $_POST['LevelUnitsEarned3'] ?? '';
    $scholar_honors3 = $_POST['scholar_honors3'] ?? '';

    $vocTradeCourse = $_POST['vocTradeCourse'] ?? '';
    $vocTradeDegree = $_POST['vocTradeDegree'] ?? '';
    $vocTradeGraduated = $_POST['vocTradeGraduated'] ?? '';
    $From_4 = $_POST['From_4'] ?? '';
    $To_4 = $_POST['To_4'] ?? '';
    $LevelUnitsEarned4 = $_POST['LevelUnitsEarned4'] ?? '';
    $scholar_honors4 = $_POST['scholar_honors4'] ?? '';

    $graduateSchool = $_POST['graduateSchool'] ?? '';
    $graduateDegree = $_POST['graduateDegree'] ?? '';
    $graduateYearGraduated = $_POST['graduateYearGraduated'] ?? '';
    $From_5 = $_POST['From_5'] ?? '';
    $To_5 = $_POST['To_5'] ?? '';
    $LevelUnitsEarned5 = $_POST['LevelUnitsEarned5'] ?? '';
    $scholar_honors5 = $_POST['scholar_honors5'] ?? '';

    if ($education_data) {
        //Update educational_background

        $stmt = $conn->prepare("UPDATE educational_background SET
        elementarySchool = ?, basicEd = ?, elementaryYearGraduated = ?, From_1 = ?, To_1 = ?,
        LevelUnitsEarned = ?, scholar_honors = ?, highSchool = ?, basicEd2 = ?, highSchoolYearGraduated = ?,
        From_2 = ?, To_2 = ?, LevelUnitsEarned2 = ?, scholar_honors2 = ?, college = ?,  collegeDegree = ?,
        collegeYearGraduated = ?, From_3 = ?, To_3 = ?, LevelUnitsEarned3 = ?, scholar_honors3 = ?,
        vocTradeCourse = ?, vocTradeDegree = ?, vocTradeGraduated = ?, From_4 = ?, To_4 = ?,
        LevelUnitsEarned4 = ?, scholar_honors4 = ?,  graduateSchool = ?, graduateDegree = ?, graduateYearGraduated = ?,
        From_5 = ?, To_5 = ?, LevelUnitsEarned5 = ?, scholar_honors5 = ?
        WHERE user_id = ?");

        $stmt->bind_param(
            "sssssssssssssssssssssssssssssssssssi",
            $_POST['elementarySchool'],
            $_POST['basicEd'],
            $_POST['elementaryYearGraduated'],
            $_POST['From_1'],
            $_POST['To_1'],
            $_POST['LevelUnitsEarned'],
            $_POST['scholar_honors'],
            $_POST['highSchool'],
            $_POST['basicEd2'],
            $_POST['highSchoolYearGraduated'],
            $_POST['From_2'],
            $_POST['To_2'],
            $_POST['LevelUnitsEarned2'],
            $_POST['scholar_honors2'],
            $_POST['college'],
            $_POST['collegeDegree'],
            $_POST['collegeYearGraduated'],
            $_POST['From_3'],
            $_POST['To_3'],
            $_POST['LevelUnitsEarned3'],
            $_POST['scholar_honors3'],
            $_POST['vocTradeCourse'],
            $_POST['vocTradeDegree'],
            $_POST['vocTradeGraduated'],
            $_POST['From_4'],
            $_POST['To_4'],
            $_POST['LevelUnitsEarned4'],
            $_POST['scholar_honors4'],
            $_POST['graduateSchool'],
            $_POST['graduateDegree'],
            $_POST['graduateYearGraduated'],
            $_POST['From_5'],
            $_POST['To_5'],
            $_POST['LevelUnitsEarned5'],
            $_POST['scholar_honors5'],
            $user_id
        );

        if (!$stmt->execute()) {
            die("Error updating personal_data_sheet: " . $stmt->error);
        }

        $stmt->close(); // Close only after execution
    } else {

        $insert_query = $conn->prepare("INSERT INTO educational_background (
        user_id, elementarySchool, basicEd, elementaryYearGraduated, From_1, To_1, LevelUnitsEarned, scholar_honors,
        highSchool, basicEd2, highSchoolYearGraduated, From_2, To_2, LevelUnitsEarned2, scholar_honors2,
        college, collegeDegree, collegeYearGraduated, From_3, To_3, LevelUnitsEarned3, scholar_honors3,
        vocTradeCourse, vocTradeDegree, vocTradeGraduated, From_4, To_4, LevelUnitsEarned4, scholar_honors4,
        graduateSchool, graduateDegree, graduateYearGraduated, From_5, To_5, LevelUnitsEarned5, scholar_honors5
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$insert_query) {
            die("Statement preparation failed: " . $conn->error);
        }

        // Bind parameters
        $insert_query->bind_param(
            "isssssssssssssssssssssssssssssssssss",
            $user_id,
            $elementarySchool,
            $basicEd,
            $elementaryYearGraduated,
            $From_1,
            $To_1,
            $LevelUnitsEarned,
            $scholar_honors,
            $highSchool,
            $basicEd2,
            $highSchoolYearGraduated,
            $From_2,
            $To_2,
            $LevelUnitsEarned2,
            $scholar_honors2,
            $college,
            $collegeDegree,
            $collegeYearGraduated,
            $From_3,
            $To_3,
            $LevelUnitsEarned3,
            $scholar_honors3,
            $vocTradeCourse,
            $vocTradeDegree,
            $vocTradeGraduated,
            $From_4,
            $To_4,
            $LevelUnitsEarned4,
            $scholar_honors4,
            $graduateSchool,
            $graduateDegree,
            $graduateYearGraduated,
            $From_5,
            $To_5,
            $LevelUnitsEarned5,
            $scholar_honors5
        );
        if (!$insert_query->execute()) {
            die("Error inserting into family_background: " . $insert_query->error);
        }

        $insert_query->close(); // Close only after execution

    }

    //Questions
    // Retrieve form data
    $q1 = $_POST['q1'] ?? '';
    $q2 = $_POST['q2'] ?? '';
    $details2 = ($q2 === "Yes" && !empty($_POST['details2'])) ? trim($_POST['details2']) : '';
    $q3 = $_POST['q3'] ?? '';
    $details3 = ($q3 === "Yes" && !empty($_POST['details3'])) ? trim($_POST['details3']) : '';
    $q4 = $_POST['q4'] ?? '';
    $details41 = ($q4 === "Yes" && !empty($_POST['details41'])) ? trim($_POST['details41']) : '';
    $details4 = ($q4 === "Yes" && !empty($_POST['details4'])) ? trim($_POST['details4']) : '';
    $q5 = $_POST['q5'] ?? '';
    $details5 = ($q5 === "Yes" && !empty($_POST['details5'])) ? trim($_POST['details5']) : '';
    $q6 = $_POST['q6'] ?? '';
    $details6 = ($q6 === "Yes" && !empty($_POST['details6'])) ? trim($_POST['details6']) : '';
    $q7 = $_POST['q7'] ?? '';
    $details7 = ($q7 === "Yes" && !empty($_POST['details7'])) ? trim($_POST['details7']) : '';
    $q8 = $_POST['q8'] ?? '';
    $details8 = ($q8 === "Yes" && !empty($_POST['details8'])) ? trim($_POST['details8']) : '';
    $q9 = $_POST['q9'] ?? '';
    $details9 = ($q9 === "Yes" && !empty($_POST['details9'])) ? trim($_POST['details9']) : '';
    $q10 = $_POST['q10'] ?? '';
    $details10 = ($q10 === "Yes" && !empty($_POST['details10'])) ? trim($_POST['details10']) : '';
    $q11 = $_POST['q11'] ?? '';
    $details11 = ($q11 === "Yes" && !empty($_POST['details11'])) ? trim($_POST['details11']) : '';
    $q12 = $_POST['q12'] ?? '';
    $details12 = ($q12 === "Yes" && !empty($_POST['details12'])) ? trim($_POST['details12']) : '';

    if ($question_data) {
        //Update questions

        $stmt = $conn->prepare("UPDATE questions SET
        q1 = ?, q2 = ?, details2 = ?, q3 = ?,
        details3 = ?, q4 = ?, details4 = ?, details41 = ?, q5 = ?,
        details5 = ?, q6 = ?, details6 = ?, q7 = ?, details7 = ?,  q8 = ?,
        details8 = ?, q9 = ?, details9 = ?, q10 = ?, details10 = ?,
        q11 = ?, details11 = ?, q12 = ?, details12 = ?
        WHERE user_id = ?");

        $stmt->bind_param(
            "ssssssssssssssssssssssssi",
            $_POST['q1'],
            $_POST['q2'],
            $_POST['details2'],
            $_POST['q3'],
            $_POST['details3'],
            $_POST['q4'],
            $_POST['details4'],
            $_POST['details41'],
            $_POST['q5'],
            $_POST['details5'],
            $_POST['q6'],
            $_POST['details6'],
            $_POST['q7'],
            $_POST['details7'],
            $_POST['q8'],
            $_POST['details8'],
            $_POST['q9'],
            $_POST['details9'],
            $_POST['q10'],
            $_POST['details10'],
            $_POST['q11'],
            $_POST['details11'],
            $_POST['q12'],
            $_POST['details12'],
            $user_id
        );

        if (!$stmt->execute()) {
            die("Error updating personal_data_sheet: " . $stmt->error);
        }

        $stmt->close(); // Close only after execution
    } else {

        $insert_query = $conn->prepare("INSERT INTO questions
    (user_id, q1, q2, details2, q3, details3, q4, details4, details41, q5, details5,
    q6, details6, q7, details7, q8, details8, q9, details9, q10, details10, q11, details11, q12, details12)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

// Bind parameters
$insert_query->bind_param(
    "issssssssssssssssssssssss",  // Ensure this matches the number of placeholders in the query
    $user_id,
    $q1,
    $q2,
    $details2,
    $q3,
    $details3,
    $q4,
    $details4,
    $details41,
    $q5,
    $details5,
    $q6,
    $details6,
    $q7,
    $details7,
    $q8,
    $details8,
    $q9,
    $details9,
    $q10,
    $details10,
    $q11,
    $details11,
    $q12,
    $details12
);


        if (!$insert_query->execute()) {
            die("Error inserting into questions: " . $insert_query->error);
        }

        $insert_query->close(); // Close only after execution

    }

}




// Process form submission to update data
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Update Eligibility
    if (!empty($_POST['eligibility'])) {
        $stmt = $conn->prepare("DELETE FROM eligibility WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO eligibility (user_id, eligibility_type, rating, exam_date, exam_place, license_number, validity) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($_POST['eligibility'] as $index => $eligibility_type) {
            $rating = $_POST['rating'][$index] ?? '';
            $exam_date = $_POST['examDate'][$index] ?? '';
            $exam_place = $_POST['examPlace'][$index] ?? '';
            $license_number = $_POST['licenseNumber'][$index] ?? '';
            $validity = $_POST['validity'][$index] ?? '';
            $stmt->bind_param("issssss", $user_id, $eligibility_type, $rating, $exam_date, $exam_place, $license_number, $validity);
            $stmt->execute();
        }
        $stmt->close();
    }

    // Update Children
    if (!empty($_POST['children'])) {
        // Delete existing children for the user (optional: only if fully replacing data)
        $stmt = $conn->prepare("DELETE FROM children WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // Prepare statement for inserting new children
        $stmt = $conn->prepare("INSERT INTO children (user_id, full_name, birthdate) VALUES (?, ?, ?)");

        foreach ($_POST['children'] as $index => $full_name) {
            if (!empty($full_name)) { // Prevent inserting empty rows
                $birthdate = $_POST['birthdate'][$index] ?? '';
                $stmt->bind_param("iss", $user_id, $full_name, $birthdate);
                $stmt->execute();
            }
        }
        $stmt->close();
    }

    // Update Other Info
    if (!empty($_POST['skillsTitle'])) {
        // Delete existing children for the user (optional: only if fully replacing data)
        $stmt = $conn->prepare("DELETE FROM other_info WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // Prepare statement for inserting new children
        $stmt = $conn->prepare("INSERT INTO other_info (user_id, skills_hobbies, non_academic, membership) VALUES (?, ?, ?, ?)");

        foreach ($_POST['skillsTitle'] as $index => $skills_hobbies) {
            if (!empty($skills_hobbies)) { // Prevent inserting empty rows
                $non_academic = $_POST['nonAcad'][$index] ?? '';
                $membership = $_POST['membership'][$index] ?? '';
                $stmt->bind_param("isss", $user_id, $skills_hobbies, $non_academic, $membership);
                $stmt->execute();
            }
        }
        $stmt->close();
    }



    // Update Work Experience
if (!empty($_POST['position_title']) && is_array($_POST['position_title'])) {
    $hasValidData = false;

    foreach ($_POST['position_title'] as $index => $position_title) {
        if (!empty($position_title)) { // Check if there's valid input
            $hasValidData = true;
            break;
        }
    }

    if ($hasValidData) {
        $stmt = $conn->prepare("DELETE FROM work_experience WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO work_experience (user_id, position_title, we_department, work_from_date, work_to_date, monthly_salary, salary_grade, appointment_status, govt_service) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($_POST['position_title'] as $index => $position_title) {
            if (!empty($position_title)) { // Ensure it's not empty before inserting
                $we_department = $_POST['we_department'][$index] ?? '';
                $work_from_date = $_POST['work_from_date'][$index] ?? '';
                $work_to_date = $_POST['work_to_date'][$index] ?? '';
                $monthly_salary = $_POST['monthly_salary'][$index] ?? '';
                $salary_grade = $_POST['salary_grade'][$index] ?? '';
                $appointment_status = $_POST['appointment_status'][$index] ?? '';
                $govt_service = $_POST['govt_service'][$index] ?? '';

                $stmt->bind_param("issssisss", $user_id, $position_title, $we_department, $work_from_date, $work_to_date, $monthly_salary, $salary_grade, $appointment_status, $govt_service);
                $stmt->execute();
            }
        }
        $stmt->close();
    }
}

// Update Voluntary Work (Only delete if new data is provided)
if (!empty($_POST['organization']) && is_array($_POST['organization'])) {
    $hasValidData = false;

    foreach ($_POST['organization'] as $index => $organization) {
        if (!empty($organization)) { // Check if at least one valid input exists
            $hasValidData = true;
            break;
        }
    }

    if ($hasValidData) {
        $stmt = $conn->prepare("DELETE FROM voluntary_work WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO voluntary_work (user_id, organization, voluntary_from_date, voluntary_to_date, voluntary_hours, voluntary_position) VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($_POST['organization'] as $index => $organization) {
            if (!empty($organization)) { // Prevent inserting empty records
                $voluntary_from_date = $_POST['voluntary_from_date'][$index] ?? '';
                $voluntary_to_date = $_POST['voluntary_to_date'][$index] ?? '';
                $voluntary_hours = intval($_POST['voluntary_hours'][$index] ?? 0); // Convert to integer
                $voluntary_position = $_POST['voluntary_position'][$index] ?? '';

                $stmt->bind_param("isssis", $user_id, $organization, $voluntary_from_date, $voluntary_to_date, $voluntary_hours, $voluntary_position);
                $stmt->execute();
            }
        }
        $stmt->close();
    }
}

    // Update Voluntary Work (Only delete if new data is provided)
    if (!empty($_POST['organization']) && is_array($_POST['organization'])) {
        $stmt = $conn->prepare("DELETE FROM voluntary_work WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO voluntary_work (user_id, organization, voluntary_from_date, voluntary_to_date, voluntary_hours, voluntary_position) VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($_POST['organization'] as $index => $organization) {
            if (!empty($organization)) { // Prevent inserting empty records
                $voluntary_from_date = $_POST['voluntary_from_date'][$index] ?? '';
                $voluntary_to_date = $_POST['voluntary_to_date'][$index] ?? '';
                $voluntary_hours = intval($_POST['voluntary_hours'][$index] ?? 0); // Convert to integer
                $voluntary_position = $_POST['voluntary_position'][$index] ?? '';
                $stmt->bind_param("isssis", $user_id, $organization, $voluntary_from_date, $voluntary_to_date, $voluntary_hours, $voluntary_position);
                $stmt->execute();
            }
        }
        $stmt->close();
    }

    // Update Learning & Development
    if (!empty($_POST['training_title']) && is_array($_POST['training_title'])) {
        $stmt = $conn->prepare("DELETE FROM learning_development WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO learning_development (user_id, training_title, from_date, to_date, hours, type_of_ld, sponsor) VALUES (?, ?, ?, ?, ?, ?, ?)");

        foreach ($_POST['training_title'] as $index => $training_title) {
            if (!empty($training_title)) { // Prevent inserting empty records
                $from_date = $_POST['from_date'][$index] ?? '';
                $to_date = $_POST['to_date'][$index] ?? '';
                $hours = intval($_POST['hours'][$index] ?? 0); // Convert to integer
                $type_of_ld = $_POST['type_of_ld'][$index] ?? '';
                $sponsor = $_POST['sponsor'][$index] ?? '';
                $stmt->bind_param("isssiss", $user_id, $training_title, $from_date, $to_date, $hours, $type_of_ld, $sponsor);
                $stmt->execute();
            }
        }
        $stmt->close();
    }

    // Update reference
    if (!empty($_POST['refName1']) && is_array($_POST['refName1'])) {
        $stmt = $conn->prepare("DELETE FROM reference WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO reference (user_id, refName1, refAddress1, refPhone1) VALUES (?, ?, ?, ?)");

        foreach ($_POST['refName1'] as $index => $refName1) {
            if (!empty($refName1)) { // Prevent inserting empty records
                $refAddress1 = $_POST['refAddress1'][$index] ?? '';
                $refPhone1 = $_POST['refPhone1'][$index] ?? '';
                $stmt->bind_param("isss", $user_id, $refName1, $refAddress1, $refPhone1);
                $stmt->execute();
            }
        }
        $stmt->close();
    }
    // Redirect after successful update
    header("Location: super_admin_datasheet.php");
    exit();
}



//Add here the db functions for each form...
$conn->close();
?>