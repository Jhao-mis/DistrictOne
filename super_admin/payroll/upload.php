<?php
/* =====================
   PERFORMANCE FIXES
===================== */
set_time_limit(0);
ini_set('memory_limit', '512M');

require '../../vendor/autoload.php';
require '../../db.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

/* =====================
   HELPER: REDIRECT ERROR
===================== */
function fail($msg)
{
    header("Location: ../payrollmng.php?status=error&msg=" . urlencode($msg));
    exit;
}

/* =====================
   REQUEST METHOD CHECK
===================== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail("Invalid request method.");
}

/* =====================
   FILE VALIDATION
===================== */
if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== 0) {
    fail("Please select a valid Excel file.");
}

$file = $_FILES['excel_file']['tmp_name'];

/* =====================
   LOAD EXCEL
===================== */
try {
    $spreadsheet = IOFactory::load($file);
} catch (Exception $e) {
    fail("Unable to read Excel file.");
}

$rows = $spreadsheet->getActiveSheet()->toArray();

/* =====================
   DATA VALIDATION
===================== */
if (count($rows) <= 1) {
    fail("Excel file contains no data.");
}

/* =====================
   HEADER MAPPING (🔥 FIX)
===================== */
$header = array_map(function ($h) {
    return strtolower(trim($h));
}, $rows[0]);

$colMap = [
    'batch_id'     => array_search('batchid', $header),
    'emp_id'       => array_search('emp id', $header),
    'account_code' => array_search('account code', $header),
    'account_type' => array_search('account type', $header),
    'amount'       => array_search('amount', $header),
    'pay_date'     => array_search('paydate', $header),
    'week'         => array_search('week', $header),
    'payroll_from' => array_search('payrollfrom', $header),
    'payroll_to'   => array_search('payrollto', $header),
    'remarks'      => array_search('remarks', $header),
];

/* =====================
   VALIDATE REQUIRED COLUMNS
===================== */
foreach ($colMap as $key => $index) {
    if ($index === false && $key !== 'remarks') {
        fail("Missing required column in Excel: " . strtoupper($key));
    }
}

/* =====================
   PREPARE STATEMENTS
===================== */
$checkStmt = $conn->prepare("
    SELECT 1 FROM payroll
    WHERE batch_id = ?
      AND emp_id = ?
      AND account_code = ?
      AND payroll_from = ?
      AND payroll_to = ?
    LIMIT 1
");

$insertStmt = $conn->prepare("
    INSERT INTO payroll (
        batch_id,
        emp_id,
        account_code,
        account_type,
        amount,
        pay_date,
        week,
        payroll_from,
        payroll_to,
        remarks
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$checkStmt || !$insertStmt) {
    fail("Database preparation failed.");
}

/* =====================
   TRANSACTION START
===================== */
$conn->begin_transaction();

/* =====================
   INSERT LOOP
===================== */
for ($i = 1; $i < count($rows); $i++) {

    // Skip empty rows
    if (empty($rows[$i][$colMap['batch_id']]) &&
        empty($rows[$i][$colMap['emp_id']]) &&
        empty($rows[$i][$colMap['account_code']])) {
        continue;
    }

    $batch_id     = (int) $rows[$i][$colMap['batch_id']];
    $emp_id       = (int) $rows[$i][$colMap['emp_id']];
    $account_code = strtoupper(trim($rows[$i][$colMap['account_code']]));
    $account_type = strtoupper(trim($rows[$i][$colMap['account_type']]));
    $amount       = (float) str_replace(',', '', $rows[$i][$colMap['amount']] ?? 0);

    $pay_date = !empty($rows[$i][$colMap['pay_date']])
        ? date('Y-m-d', strtotime($rows[$i][$colMap['pay_date']]))
        : null;

    $week = trim((string) ($rows[$i][$colMap['week']] ?? 'N/A'));
    if ($week === '') $week = 'N/A';

    $payroll_from = !empty($rows[$i][$colMap['payroll_from']])
        ? date('Y-m-d', strtotime($rows[$i][$colMap['payroll_from']]))
        : null;

    $payroll_to = !empty($rows[$i][$colMap['payroll_to']])
        ? date('Y-m-d', strtotime($rows[$i][$colMap['payroll_to']]))
        : null;

    /* =====================
       ✅ REMARKS FIX
    ===================== */
    $remarks = null;
    if ($colMap['remarks'] !== false) {
        $remarks = trim((string) $rows[$i][$colMap['remarks']]);
        if ($remarks === '') $remarks = null;
    }

    /* =====================
       DUPLICATE CHECK
    ===================== */
    $checkStmt->bind_param(
        "iisss",
        $batch_id,
        $emp_id,
        $account_code,
        $payroll_from,
        $payroll_to
    );
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        $conn->rollback();
        fail("Duplicate payroll detected (Row " . ($i + 1) . "). Upload aborted.");
    }

    /* =====================
       INSERT
    ===================== */
    $insertStmt->bind_param(
        "iissdsssss",
        $batch_id,
        $emp_id,
        $account_code,
        $account_type,
        $amount,
        $pay_date,
        $week,
        $payroll_from,
        $payroll_to,
        $remarks
    );

    if (!$insertStmt->execute()) {
        $conn->rollback();
        fail("Failed importing row " . ($i + 1));
    }
}

/* =====================
   COMMIT
===================== */
$conn->commit();

/* =====================
   SUCCESS
===================== */
header("Location: ../payrollmng.php?status=success");
exit;