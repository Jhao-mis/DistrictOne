<?php
session_start();
require '../vendor/autoload.php';
require 'login_verification.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require '../db.php';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB Error");
}

$scope = $_GET['scope'] ?? 'all';
if ($scope !== 'all' && $scope !== 'range') {
    http_response_code(400);
    exit('Invalid export selection.');
}

$sql = "
    SELECT
        r.report_no,
        r.end_user,
        r.department,
        r.report_date,
        r.prepared_by,
        r.certified_by,
        i.asset_code,
        i.description,
        i.acquisition_date,
        i.remarks
    FROM mir_reports r
    LEFT JOIN mir_items i ON i.mir_id = r.id";

if ($scope === 'range') {
    $fromInput = $_GET['from_report'] ?? null;
    $toInput = $_GET['to_report'] ?? null;
    if (!is_string($fromInput) || !is_string($toInput)) {
        http_response_code(400);
        exit('Enter both report numbers in the format MIR-2026-2635.');
    }

    $fromReport = trim($fromInput);
    $toReport = trim($toInput);
    $reportPattern = '/\AMIR-(\d{4})-(\d{1,10})\z/';

    if (!preg_match($reportPattern, $fromReport, $fromParts) ||
        !preg_match($reportPattern, $toReport, $toParts)) {
        http_response_code(400);
        exit('Enter both report numbers in the format MIR-2026-2635.');
    }

    $fromYear = (int) $fromParts[1];
    $fromSequence = (int) $fromParts[2];
    $toYear = (int) $toParts[1];
    $toSequence = (int) $toParts[2];

    if ($fromYear > $toYear || ($fromYear === $toYear && $fromSequence > $toSequence)) {
        http_response_code(400);
        exit('The starting report number must not be after the ending report number.');
    }

    $yearExpression = "CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(r.report_no, '-', -2), '-', 1) AS UNSIGNED)";
    $sequenceExpression = "CAST(SUBSTRING_INDEX(r.report_no, '-', -1) AS UNSIGNED)";
    $sql .= " WHERE (
        $yearExpression > ? OR ($yearExpression = ? AND $sequenceExpression >= ?)
    ) AND (
        $yearExpression < ? OR ($yearExpression = ? AND $sequenceExpression <= ?)
    )";

    $stmt = $conn->prepare($sql . " ORDER BY r.id DESC, i.id ASC");
    $stmt->bind_param(
        "iiiiii",
        $fromYear,
        $fromYear,
        $fromSequence,
        $toYear,
        $toYear,
        $toSequence
    );
    $stmt->execute();
    $result = $stmt->get_result();
    $filename = 'MIR_Records_' . $fromReport . '_to_' . $toReport . '_' . date('Y-m-d') . '.xlsx';
} else {
    $result = $conn->query($sql . " ORDER BY r.id DESC, i.id ASC");
    $filename = 'MIR_Records_' . date('Y-m-d') . '.xlsx';
}

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('MIR Records');

$headers = [
    'Report No',
    'End User',
    'Department',
    'Report Date',
    'Prepared By',
    'Certified By',
    'Asset Code',
    'Description',
    'Acquisition Date',
    'Remarks',
];

foreach ($headers as $index => $header) {
    $coordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1) . '1';
    $sheet->setCellValue($coordinate, $header);
}

$sheet->getStyle('A1:J1')->getFont()->setBold(true);
$sheet->freezePane('A2');

$rowNumber = 2;
while ($row = $result->fetch_assoc()) {
    foreach (array_values($row) as $index => $value) {
        $coordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1) . $rowNumber;
        $sheet->setCellValueExplicit(
            $coordinate,
            (string) ($value ?? ''),
            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
        );
    }
    $rowNumber++;
}

$sheet->setAutoFilter('A1:J' . ($rowNumber - 1));

foreach (range('A', 'J') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
exit;
