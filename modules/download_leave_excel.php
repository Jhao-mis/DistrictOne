<?php
require '../db.php'; 
require __DIR__ . '../../vendor/autoload.php';
require 'login_verification.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

$username = $_SESSION['username'] ?? '';
if (!$username) die("Not logged in.");

// DB connect
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

// 1. Get user info
$query = $conn->prepare("SELECT id, firstname, middlename, lastname, email, department 
                         FROM users WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->bind_result($user_id, $firstname, $middlename, $lastname, $email, $department);
$query->fetch();
$query->close();

if (!$user_id) die("User not found.");

// 2. Fetch position & salary from personal_data_sheet
$stmt = $conn->prepare("SELECT position, salary FROM personal_data_sheet WHERE user_id=? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pds = $stmt->get_result()->fetch_assoc();
$stmt->close();

$position = $pds['position'] ?? '';
$salary   = $pds['salary'] ?? '';

// ✅ Handle Excel Download
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['download_excel'])) {

    // 3. Fetch latest leave request from DB
$leave_id = $_POST['leave_id'] ?? 0;

$sql = "SELECT * FROM leave_requests WHERE id = ? AND user_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $leave_id, $user_id);
$stmt->execute();
$leave_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

    // 4. Merge DB + POST values
    $dateOfFiling      = $leave_data['date_of_filing']      ?? ($_POST['date_of_filing'] ?? '');
    $leave_type        = $leave_data['leave_type']          ?? ($_POST['leave_type'] ?? '');
    $commutation       = $leave_data['commutation']         ?? ($_POST['commutation'] ?? '');
    $leave_philippines = $leave_data['leave_philippines']   ?? ($_POST['leave_philippines'] ?? '');
    $leave_abroad      = $leave_data['leave_abroad']        ?? ($_POST['leave_abroad'] ?? '');
    $sick_hospital     = $leave_data['sick_hospital']       ?? ($_POST['sick_hospital'] ?? '');
    $sick_outpatient   = $leave_data['sick_outpatient']     ?? ($_POST['sick_outpatient'] ?? '');
    $others            = $leave_data['others']              ?? ($_POST['others'] ?? '');
    $specialleave_women= $leave_data['specialleave_women']  ?? ($_POST['specialleave_women'] ?? '');
    $study_leave_opts  = $leave_data['study_leave_options'] ?? ($_POST['study_leave_options'] ?? '');
    $no_of_working_days= $leave_data['no_of_working_days']  ?? ($_POST['no_of_working_days'] ?? '');
    $startDate         = $leave_data['start_date']          ?? ($_POST['start_date'] ?? '');
    $endDate           = $leave_data['end_date']            ?? ($_POST['end_date'] ?? '');
    // 🚫 Do NOT overwrite $position and $salary – keep from personal_data_sheet

    // 5. Load Excel template
    $templatePath = realpath(__DIR__ . "/Leave_Form_CWD.xlsx");
    if (!$templatePath || !file_exists($templatePath)) {
        die("Error: Template file not found.");
    }
    $spreadsheet = IOFactory::load($templatePath);
    $sheet = $spreadsheet->getSheet(0);

    // 6. Fill basic info
    $sheet->setCellValue('B11', $department);
    $sheet->setCellValue('G11', $lastname);
    $sheet->setCellValue('N11', $middlename);
    $sheet->setCellValue('K11', $firstname);
    $sheet->setCellValue('G12', "POSITION: " . $position);
    $sheet->setCellValue('M12', "SALARY: " . $salary);
    $sheet->setCellValue('C49', "6.C  NUMBER OF WORKING DAYS APPLIED FOR: " . $no_of_working_days);

    // Date of filing
    if (!empty($dateOfFiling)) {
        $sheet->getStyle('E12')->getNumberFormat()->setFormatCode('mm/dd/yyyy');
        $correctedDate = date('m/d/Y', strtotime($dateOfFiling));
        $sheet->setCellValue('E12', $correctedDate);
        $sheet->setCellValue('N7', $correctedDate);
    }

    // 7. Leave type checkboxes
    $sheet->setCellValue('B17', ($leave_type === "Vacation Leave") ? "✓" : "");
    $sheet->setCellValue('B19', ($leave_type === "Mandatory/Forced Leave") ? "✓" : "");
    $sheet->setCellValue('B21', ($leave_type === "Sick Leave") ? "✓" : "");
    $sheet->setCellValue('B23', ($leave_type === "Maternity Leave") ? "✓" : "");
    $sheet->setCellValue('B25', ($leave_type === "Paternity Leave") ? "✓" : "");
    $sheet->setCellValue('B27', ($leave_type === "Special Privilege Leave") ? "✓" : "");
    $sheet->setCellValue('B29', ($leave_type === "Solo Parent Leave") ? "✓" : "");
    $sheet->setCellValue('B31', ($leave_type === "Study Leave") ? "✓" : "");
    $sheet->setCellValue('B33', ($leave_type === "10-Day VAWC Leave") ? "✓" : "");
    $sheet->setCellValue('B35', ($leave_type === "Rehabilitation Privilege") ? "✓" : "");
    $sheet->setCellValue('B37', ($leave_type === "Special Leave Benefits for Women") ? "✓" : "");
    $sheet->setCellValue('B39', ($leave_type === "Special Emergency (Calamity) Leave") ? "✓" : "");
    $sheet->setCellValue('B41', ($leave_type === "Adoption Leave") ? "✓" : "");
    $sheet->setCellValue('B45', ($leave_type === "Others") ? "✓" : "");

    // Commutation
    $sheet->setCellValue('K51', ($commutation === "Not Requested") ? "✓" : "");
    $sheet->setCellValue('K53', ($commutation === "Requested") ? "✓" : "");

    // Others
    if ($leave_type === "Others") {
        $sheet->setCellValue('B47', $others);
    }

    // Vacation Leave / Special Privilege
    if ($leave_type === "Vacation Leave" || $leave_type === "Special Privilege Leave") {
        $sheet->setCellValue('M19', $leave_philippines);
        $sheet->setCellValue('M21', $leave_abroad);
    }

    // Sick Leave
    if ($leave_type === "Sick Leave") {
        $sheet->setCellValue('N25', $sick_hospital);
        $sheet->setCellValue('N27', $sick_outpatient);
    }

    // Special Leave for Women
    if ($leave_type === "Special Leave Benefits for Women") {
        $sheet->setCellValue('M33', $specialleave_women);
    }

    // Study Leave options
    if ($leave_type === "Study Leave") {
        switch ($study_leave_opts) {
            case "Completion of Master`s Degree": $sheet->setCellValue('K39', "✓"); break;
            case "BAR/Board Examination Review": $sheet->setCellValue('K41', "✓"); break;
            case "Monetization of Leave Credits": $sheet->setCellValue('K45', "✓"); break;
            case "Terminal Leave": $sheet->setCellValue('K47', "✓"); break;
        }
    }

    // Full name & Date range
    $sheet->setCellValue('M54', $firstname . ' ' . $middlename . ' ' . $lastname);
    if (!empty($startDate) && !empty($endDate)) {
        $dateRange = date('m/d/Y', strtotime($startDate)) . ' - ' . date('m/d/Y', strtotime($endDate));
        $sheet->getStyle('C54')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        $sheet->setCellValue('C54', $dateRange);
    }

    $sheet->setCellValue('A74', "This is a system-generated document.");

    // 8. Output Excel
    $filename = "Leave Request " . $lastname . ".xls";
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    ob_end_clean();
    $writer = new Xls($spreadsheet);
    $writer->save('php://output');
    exit;
}
