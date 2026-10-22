<?php
require_once '../vendor/autoload.php';
include_once '../db.php';
require_once 'login_verification.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

if (!access_can('special.leave_summary_upload')) {
    http_response_code(403);
    exit('You are not authorized to upload leave summaries.');
}

if (empty($_SESSION['leave_summary_csrf'])) {
    $_SESSION['leave_summary_csrf'] = bin2hex(random_bytes(32));
}

$notice = null;
$noticeType = 'success';
if (isset($_SESSION['leave_summary_flash'])) {
    // Result of the previous POST (post/redirect/get, so a browser refresh can't re-submit the file).
    [$notice, $noticeType] = $_SESSION['leave_summary_flash'];
    unset($_SESSION['leave_summary_flash']);
}
$transactionStarted = false;

/* Current user — sidebar.php reads these to render the name/department block. */
$firstname = $lastname = $department = '';
if (!empty($_SESSION['username'])) {
    $meStmt = $conn->prepare('SELECT id, firstname, lastname, department FROM users WHERE username = ? LIMIT 1');
    $meStmt->bind_param('s', $_SESSION['username']);
    $meStmt->execute();
    if ($me = $meStmt->get_result()->fetch_assoc()) {
        $firstname  = (string) $me['firstname'];
        $lastname   = (string) $me['lastname'];
        $department = (string) ($me['department'] ?? '');
        if (empty($_SESSION['user_id'])) {
            $_SESSION['user_id'] = (int) $me['id'];
        }
    }
    $meStmt->close();
}

/* ─────────────────────────── helpers ─────────────────────────── */

function leaveSummaryHeaderText($value): string
{
    return $value === null ? '' : strtoupper(trim((string) $value));
}

/** Upper-case and strip everything except A-Z / 0-9 ("rate/day" -> "RATEDAY"). */
function leaveSummaryNorm($value): string
{
    return preg_replace('/[^A-Z0-9]/', '', leaveSummaryHeaderText($value));
}

function leaveSummaryIsBlank($value): bool
{
    return $value === null || trim((string) $value) === '';
}

function leaveSummaryMonthFromHeader(string $value): ?int
{
    $months = [
        'JANUARY' => 1, 'FEBRUARY' => 2, 'MARCH' => 3, 'APRIL' => 4,
        'MAY' => 5, 'JUNE' => 6, 'JULY' => 7, 'AUGUST' => 8,
        'SEPTEMBER' => 9, 'OCTOBER' => 10, 'NOVEMBER' => 11, 'DECEMBER' => 12,
        'JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'JUN' => 6,
        'JUL' => 7, 'AUG' => 8, 'SEPT' => 9, 'SEP' => 9, 'OCT' => 10,
        'NOV' => 11, 'DEC' => 12,
    ];

    foreach ($months as $name => $month) {
        if (preg_match('/\b' . $name . '\b/i', $value)) {
            return $month;
        }
    }

    return null;
}

function leaveSummaryMonthName(int $month): string
{
    return date('F', mktime(0, 0, 0, $month, 1));
}

function leaveSummaryNumber($value, string $field, int $rowNumber): float
{
    if (is_string($value)) {
        $value = str_replace([',', '₱', '$', ' '], '', trim($value));
    }

    if (!is_numeric($value)) {
        throw new RuntimeException("Row $rowNumber has an invalid $field value.");
    }

    return (float) $value;
}

/** Like leaveSummaryNumber() but a blank cell returns null instead of failing. */
function leaveSummaryOptionalNumber($value, string $field, int $rowNumber): ?float
{
    return leaveSummaryIsBlank($value) ? null : leaveSummaryNumber($value, $field, $rowNumber);
}

function leaveSummaryEmployeeId($value, int $rowNumber): string
{
    $value = trim((string) $value);
    if ($value === '' || !preg_match('/^\d+(?:\.0+)?$/', $value)) {
        throw new RuntimeException("Row $rowNumber has an invalid employee number.");
    }

    $employeeId = (string) (int) $value;
    if ($employeeId === '0') {
        throw new RuntimeException("Row $rowNumber has an invalid employee number.");
    }

    return $employeeId;
}

/* ─────────────────────────── import ─────────────────────────── */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['leave_summary_csrf'], (string) ($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('The form expired. Refresh the page and try again.');
        }

        if (!isset($_FILES['leave_file']) || $_FILES['leave_file']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Select a valid Excel or CSV file to upload.');
        }

        $file = $_FILES['leave_file'];
        if ($file['size'] <= 0 || $file['size'] > 10 * 1024 * 1024) {
            throw new RuntimeException('The spreadsheet must be smaller than 10 MB.');
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            throw new RuntimeException('Only .xlsx, .xls, and .csv files are supported.');
        }

        $spreadsheet = IOFactory::load($file['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();
        $lastRow = $sheet->getHighestDataRow();
        $lastColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        /* 1. Year — taken from the title, e.g. "... AS OF SEPTEMBER 30, 2026". */
        $year = null;
        for ($rowNumber = 1; $rowNumber <= min(10, $lastRow) && $year === null; $rowNumber++) {
            for ($column = 1; $column <= $lastColumn; $column++) {
                $value = leaveSummaryHeaderText($sheet->getCell([$column, $rowNumber])->getFormattedValue());
                if (preg_match('/\b(20\d{2})\b/', $value, $yearMatch)) {
                    $year = (int) $yearMatch[1];
                    break;
                }
            }
        }
        if ($year === null) {
            throw new RuntimeException('Could not find a four-digit year (for example, 2026) in the first 10 rows of the workbook.');
        }

        /* 2. Header row — the row that holds both an employee-number and a name column. */
        $empAliases  = ['EMPNO', 'EMPID', 'EMPLOYEENO', 'EMPLOYEEID', 'EMPLOYEENUMBER', 'EMPNUMBER'];
        $nameAliases = ['NAME', 'EMPLOYEENAME', 'FULLNAME'];

        $headerRow = null;
        $header = [];       // column => upper-cased header text
        $normalized = [];   // column => normalised header text
        $columns = [];
        for ($rowNumber = 1; $rowNumber <= min(10, $lastRow); $rowNumber++) {
            $header = [];
            $normalized = [];
            $empColumn = $nameColumn = null;
            for ($column = 1; $column <= $lastColumn; $column++) {
                $header[$column] = leaveSummaryHeaderText($sheet->getCell([$column, $rowNumber])->getFormattedValue());
                $normalized[$column] = leaveSummaryNorm($header[$column]);
                if ($empColumn === null && in_array($normalized[$column], $empAliases, true)) {
                    $empColumn = $column;
                } elseif ($nameColumn === null && in_array($normalized[$column], $nameAliases, true)) {
                    $nameColumn = $column;
                }
            }
            if ($empColumn !== null && $nameColumn !== null) {
                $headerRow = $rowNumber;
                $columns = ['emp_id' => $empColumn, 'name' => $nameColumn];
                break;
            }
        }
        if ($headerRow === null) {
            throw new RuntimeException('Could not find the header row. It must contain an "emp_no" (or "Emp ID") column and a "Name" column within the first 10 rows.');
        }

        /* 3. Fixed columns: basic pay, rate/day, amount. */
        foreach ($normalized as $column => $label) {
            if ($label === '') {
                continue;
            }
            if (str_contains($label, 'BASICPAY')) {
                $columns['basic_pay'] = $column;
            } elseif (str_contains($label, 'RATEDAY') || $label === 'RATEPERDAY') {
                $columns['rate_per_day'] = $column;
            } elseif (str_starts_with($label, 'AMOUNT')) {
                $columns['leave_amount'] = $column;
            }
        }
        foreach (['basic_pay', 'rate_per_day', 'leave_amount'] as $requiredColumn) {
            if (!isset($columns[$requiredColumn])) {
                throw new RuntimeException('Missing required spreadsheet column: ' . strtoupper(str_replace('_', ' ', $requiredColumn)) . '.');
            }
        }

        /*
         * 4. Month groups.
         *    A month header such as "August Earned Leave" is a merged cell over three
         *    columns (VL, SL, TOTAL). Only the first cell of a merge holds text, so a
         *    month owns every following blank-header column until the next non-blank
         *    header (the next month, "rate/day", ...).
         */
        $monthColumns = [];
        $activeMonth = null;
        for ($column = 1; $column <= $lastColumn; $column++) {
            if ($header[$column] !== '') {
                $month = leaveSummaryMonthFromHeader($header[$column]);
                if ($month !== null) {
                    $activeMonth = $month;
                    $monthColumns[$month] = ['cols' => []];
                } else {
                    $activeMonth = null;
                }
            }
            if ($activeMonth !== null) {
                $monthColumns[$activeMonth]['cols'][] = $column;
            }
        }
        if (!$monthColumns) {
            throw new RuntimeException('Could not find any month groups (for example "August Earned Leave") in the header row.');
        }
        ksort($monthColumns);
        $latestMonth = array_key_last($monthColumns);

        /*
         * 5. Optional VL / SL / TOTAL sub-header row. The standard workbook has none
         *    (the three columns under each month are VL, SL, TOTAL in that order),
         *    but a labelled sub-header row is honoured when present.
         */
        $subLabels = [];
        $hasSubheader = false;
        if ($headerRow < $lastRow) {
            for ($column = 1; $column <= $lastColumn; $column++) {
                $subLabels[$column] = leaveSummaryNorm($sheet->getCell([$column, $headerRow + 1])->getFormattedValue());
                if (in_array($subLabels[$column], ['VL', 'SL', 'VACATIONLEAVE', 'SICKLEAVE', 'TOTALLEAVE'], true)) {
                    $hasSubheader = true;
                }
            }
        }
        $dataStartRow = $headerRow + ($hasSubheader ? 2 : 1);

        foreach ($monthColumns as $month => $group) {
            $map = [];
            if ($hasSubheader) {
                foreach ($group['cols'] as $column) {
                    $label = $subLabels[$column];
                    if ($label === 'VL' || $label === 'VACATIONLEAVE') {
                        $map['vacation_leave'] = $column;
                    } elseif ($label === 'SL' || $label === 'SICKLEAVE') {
                        $map['sick_leave'] = $column;
                    } elseif ($label === 'TOTALLEAVE' || $label === 'TOTAL') {
                        $map['total_leave'] = $column;
                    }
                }
            } elseif (count($group['cols']) === 3) {
                [$map['vacation_leave'], $map['sick_leave'], $map['total_leave']] = $group['cols'];
            }

            if (count($map) !== 3) {
                throw new RuntimeException('The ' . leaveSummaryMonthName($month) . ' group must span exactly three columns: vacation leave, sick leave, and total.');
            }
            $monthColumns[$month] = $map;
        }

        /* 6. Read the rows. */
        $records = [];
        $seenPeriods = [];
        for ($rowNumber = $dataStartRow; $rowNumber <= $lastRow; $rowNumber++) {
            $rawEmployeeId = $sheet->getCell([$columns['emp_id'], $rowNumber])->getFormattedValue();
            if (trim((string) $rawEmployeeId) === '') {
                continue;
            }

            $employeeId = leaveSummaryEmployeeId($rawEmployeeId, $rowNumber);
            $name = trim(preg_replace('/\s+/', ' ', (string) $sheet->getCell([$columns['name'], $rowNumber])->getFormattedValue()));
            if ($name === '') {
                throw new RuntimeException("Row $rowNumber is missing the employee name.");
            }

            $basicPay   = leaveSummaryNumber($sheet->getCell([$columns['basic_pay'], $rowNumber])->getCalculatedValue(), 'basic pay', $rowNumber);
            $ratePerDay = leaveSummaryNumber($sheet->getCell([$columns['rate_per_day'], $rowNumber])->getCalculatedValue(), 'rate/day', $rowNumber);
            $sheetAmount = leaveSummaryOptionalNumber($sheet->getCell([$columns['leave_amount'], $rowNumber])->getCalculatedValue(), 'amount', $rowNumber);

            foreach ($monthColumns as $month => $map) {
                $rawVl    = $sheet->getCell([$map['vacation_leave'], $rowNumber])->getCalculatedValue();
                $rawSl    = $sheet->getCell([$map['sick_leave'], $rowNumber])->getCalculatedValue();
                $rawTotal = $sheet->getCell([$map['total_leave'], $rowNumber])->getCalculatedValue();

                // Employee has no entry for this month (e.g. hired mid-year) — nothing to import.
                if (leaveSummaryIsBlank($rawVl) && leaveSummaryIsBlank($rawSl) && leaveSummaryIsBlank($rawTotal)) {
                    continue;
                }

                $vl    = leaveSummaryOptionalNumber($rawVl, 'vacation leave', $rowNumber) ?? 0.0;
                $sl    = leaveSummaryOptionalNumber($rawSl, 'sick leave', $rowNumber) ?? 0.0;
                $total = leaveSummaryOptionalNumber($rawTotal, 'total leave', $rowNumber) ?? ($vl + $sl);
                if (abs(($vl + $sl) - $total) > 0.01) {
                    throw new RuntimeException("Row $rowNumber: vacation + sick leave does not equal the total for " . leaveSummaryMonthName($month) . '. No rows were imported.');
                }

                $periodKey = $employeeId . '-' . $year . '-' . $month;
                if (isset($seenPeriods[$periodKey])) {
                    throw new RuntimeException("Row $rowNumber duplicates employee $employeeId for " . leaveSummaryMonthName($month) . " $year. No rows were imported.");
                }
                $seenPeriods[$periodKey] = true;

                /*
                 * The workbook's "amount" is the leave value as of the latest month
                 * (total leave x rate/day). Earlier months use the same formula so each
                 * stored row is internally consistent.
                 */
                $amount = ($month === $latestMonth && $sheetAmount !== null)
                    ? $sheetAmount
                    : round($total * $ratePerDay, 2);

                $records[] = [
                    'emp_id' => $employeeId,
                    'year' => $year,
                    'month' => $month,
                    'name' => $name,
                    'basic_pay' => $basicPay,
                    'vacation_leave' => $vl,
                    'sick_leave' => $sl,
                    'total_leave' => $total,
                    'rate_per_day' => $ratePerDay,
                    'amount' => $amount,
                    'row_number' => $rowNumber,
                ];
            }
        }

        if (!$records) {
            throw new RuntimeException('No employee records were found below the spreadsheet header.');
        }

        /* 7. Every employee number must exist in users.emp_ID (payroll.php joins on it). */
        $employeeCheck = $conn->prepare('SELECT 1 FROM users WHERE CAST(emp_ID AS UNSIGNED) = ? LIMIT 1');
        if (!$employeeCheck) {
            throw new RuntimeException('Unable to prepare employee validation.');
        }
        $validated = [];
        $missing = [];
        foreach ($records as $record) {
            if (isset($validated[$record['emp_id']])) {
                continue;
            }
            $empIdInt = (int) $record['emp_id'];
            $employeeCheck->bind_param('i', $empIdInt);
            if (!$employeeCheck->execute()) {
                throw new RuntimeException('Unable to validate employee number ' . $record['emp_id'] . '.');
            }
            $employeeCheck->store_result();
            $validated[$record['emp_id']] = true;
            if ($employeeCheck->num_rows === 0) {
                $missing[] = $record['emp_id'] . ' (row ' . $record['row_number'] . ')';
            }
        }
        $employeeCheck->close();
        // Employees without an account are always imported; they show on My Payslip once an account with that emp_ID exists.
        $allowUnmatched = true;
        $unmatchedNote = '';
        if ($missing) {
            $shown = implode(', ', array_slice($missing, 0, 10));
            $more = count($missing) > 10 ? ' and ' . (count($missing) - 10) . ' more' : '';
            if (!$allowUnmatched) {
                throw new RuntimeException('These employee numbers have no account in the system: ' . $shown . $more . '. Fix them in the file, or tick "Also import employees without an account" and upload again. No rows were imported.');
            }
            // Imported anyway; they appear on My Payslip once an account with this emp_ID exists.
            $unmatchedNote = ' Imported without an account (not visible to them yet): ' . $shown . $more . '.';
        }

        /* 8. Save atomically. Re-uploading a month updates it instead of failing. */
        $importedBy = (int) ($_SESSION['user_id'] ?? 0);
        if ($importedBy <= 0) {
            throw new RuntimeException('Your session is missing a user id. Log out and back in, then retry.');
        }

        $conn->begin_transaction();
        $transactionStarted = true;
        $upsert = $conn->prepare("
            INSERT INTO employee_leave_summaries
                (emp_id, leave_year, leave_month, employee_name, basic_pay, vacation_leave, sick_leave, total_leave, rate_per_day, leave_amount, imported_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                imported_at = IF(employee_name <=> VALUES(employee_name) AND basic_pay <=> VALUES(basic_pay)
                                 AND vacation_leave <=> VALUES(vacation_leave) AND sick_leave <=> VALUES(sick_leave)
                                 AND total_leave <=> VALUES(total_leave) AND rate_per_day <=> VALUES(rate_per_day)
                                 AND leave_amount <=> VALUES(leave_amount), imported_at, CURRENT_TIMESTAMP),
                imported_by = IF(employee_name <=> VALUES(employee_name) AND basic_pay <=> VALUES(basic_pay)
                                 AND vacation_leave <=> VALUES(vacation_leave) AND sick_leave <=> VALUES(sick_leave)
                                 AND total_leave <=> VALUES(total_leave) AND rate_per_day <=> VALUES(rate_per_day)
                                 AND leave_amount <=> VALUES(leave_amount), imported_by, VALUES(imported_by)),
                employee_name  = VALUES(employee_name),
                basic_pay      = VALUES(basic_pay),
                vacation_leave = VALUES(vacation_leave),
                sick_leave     = VALUES(sick_leave),
                total_leave    = VALUES(total_leave),
                rate_per_day   = VALUES(rate_per_day),
                leave_amount   = VALUES(leave_amount)
        ");
        if (!$upsert) {
            throw new RuntimeException('Unable to prepare the leave summary import.');
        }

        $inserted = $updated = $unchanged = 0;
        $changedRows = [];
        foreach ($records as $record) {
            $upsert->bind_param(
                'siisddddddi',
                $record['emp_id'],
                $record['year'],
                $record['month'],
                $record['name'],
                $record['basic_pay'],
                $record['vacation_leave'],
                $record['sick_leave'],
                $record['total_leave'],
                $record['rate_per_day'],
                $record['amount'],
                $importedBy
            );
            if (!$upsert->execute()) {
                throw new RuntimeException('Import failed while saving row ' . $record['row_number'] . '.');
            }
            // MySQL: 1 = inserted, 2 = existing row changed, 0 = existing row identical.
            if ($upsert->affected_rows === 1) {
                $inserted++;
            } elseif ($upsert->affected_rows === 0) {
                $unchanged++;
            } else {
                $updated++;
                $changedRows[] = $record['emp_id'] . ' (' . leaveSummaryMonthName($record['month']) . ')';
            }
        }
        $upsert->close();

        /* Anti-overwrite: existing records whose values differ are only replaced on request. */
        if ($updated > 0 && empty($_POST['replace_existing'])) {
            $sample = implode(', ', array_slice($changedRows, 0, 5));
            $more = count($changedRows) > 5 ? ' and ' . (count($changedRows) - 5) . ' more' : '';
            throw new RuntimeException($updated . ' existing record(s) already have different values: ' . $sample . $more
                . '. Nothing was imported. If this is a correction, tick "Replace existing records" and upload again.');
        }

        $conn->commit();
        $transactionStarted = false;

        $monthNames = array_map('leaveSummaryMonthName', array_keys($monthColumns));
        if ($inserted === 0 && $updated === 0) {
            $notice = 'Already imported: every record in this file matches what is saved. Nothing was changed.';
            $noticeType = 'warning';
        } else {
            $notice = count($validated) . ' employees imported for ' . implode(', ', $monthNames) . " $year: "
                . "$inserted new, $updated replaced, $unchanged already saved." . $unmatchedNote;
        }
    } catch (RuntimeException $exception) {
        if ($transactionStarted) {
            $conn->rollback();
        }
        $notice = $exception->getMessage();
        $noticeType = 'danger';
    } catch (Throwable $exception) {
        if ($transactionStarted) {
            $conn->rollback();
        }
        error_log('Leave summary import failed: ' . $exception->getMessage());
        $notice = 'The spreadsheet could not be imported. Check that it matches the required format and try again.';
        $noticeType = 'danger';
    }

    $_SESSION['leave_summary_flash'] = [$notice, $noticeType];
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'), true, 303);
    exit;
}

/* ─────────────────────────── page data ─────────────────────────── */

$recentSummaries = [];
$summaryTotals = ['total' => 0, 'employees' => 0];
try {
    $summaryResult = $conn->query("
        SELECT emp_id, employee_name, leave_year, leave_month, vacation_leave, sick_leave, total_leave, leave_amount, imported_at
        FROM employee_leave_summaries
        ORDER BY leave_year DESC, leave_month DESC, employee_name, emp_id
    ");
    $recentSummaries = $summaryResult->fetch_all(MYSQLI_ASSOC);
    $summaryResult->free();

    $countResult = $conn->query('SELECT COUNT(*) AS total, COUNT(DISTINCT emp_id) AS employees FROM employee_leave_summaries');
    $summaryTotals = $countResult->fetch_assoc();
} catch (Throwable $exception) {
    error_log('Leave summary page load failed: ' . $exception->getMessage());
    if ($notice === null) {
        $notice = 'Could not read the employee_leave_summaries table. Run leave_summary_migration.sql first.';
        $noticeType = 'danger';
    }
}
?>
<?php
/* ---- view-only grouping: one table per month ---- */
$months = [];
foreach ($recentSummaries as $row) {
    $key = sprintf('%04d-%02d', (int) $row['leave_year'], (int) $row['leave_month']);
    if (!isset($months[$key])) {
        $months[$key] = [
            'label' => date('F Y', mktime(0, 0, 0, (int) $row['leave_month'], 1, (int) $row['leave_year'])),
            'rows' => [],
            'imported' => $row['imported_at'],
        ];
    }
    $months[$key]['rows'][] = $row;
    if ($row['imported_at'] > $months[$key]['imported']) {
        $months[$key]['imported'] = $row['imported_at'];
    }
}
$firstMonthKey = array_key_first($months);
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leave Summary Import</title>
    <link rel="icon" type="image/png" href="../assets/img/favicon/districtone.png">
    <link rel="stylesheet" href="../assets/vendor/css/core.css">
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css">
    <link rel="stylesheet" href="../assets/css/demo.css">
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css">
    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>
    <style>
        :root {
            --lsu-border: #e8eaee;
            --lsu-text: #1f2430;
            --lsu-muted: #767e8c;
            --lsu-primary: #4e96f0;
            --lsu-primary-soft: #eaf3ff;
            --lsu-surface: #fff;
            --lsu-head: #f3f6fb;
        }
        .lsu-header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 18px; }
        .lsu-header h2 { margin: 0; font-size: 22px; font-weight: 800; color: var(--lsu-text); }
        .lsu-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 18px; }
        .lsu-stat { position: relative; overflow: hidden; padding: 14px 18px 14px 20px; background: var(--lsu-surface); border: 1px solid var(--lsu-border); border-radius: 12px; }
        .lsu-stat::before { position: absolute; inset: 0 auto 0 0; width: 4px; background: var(--lsu-primary); content: ""; }
        .lsu-stat-label { color: var(--lsu-muted); font-size: 11px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; }
        .lsu-stat-value { margin-top: 4px; color: var(--lsu-text); font-size: 20px; font-weight: 800; }
        .lsu-card { margin-bottom: 18px; background: var(--lsu-surface); border: 1px solid var(--lsu-border); border-radius: 14px; box-shadow: 0 1px 2px rgba(20,20,43,.04); }
        .lsu-card-head { padding: 14px 20px; border-bottom: 1px solid var(--lsu-border); color: var(--lsu-text); font-size: 14px; font-weight: 800; }
        .lsu-card-body { padding: 18px 20px; }
        .lsu-upload { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 14px; }
        .lsu-upload .lsu-file { flex: 1 1 280px; }
        .lsu-upload label.lsu-label { display: block; margin-bottom: 4px; color: var(--lsu-muted); font-size: 11px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; }
        .lsu-check { display: flex; align-items: center; gap: 8px; padding-bottom: 8px; color: var(--lsu-text); font-size: 13px; }
        .lsu-check input { margin: 0; }
        .lsu-toolbar { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
        .lsu-range { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; }
        .lsu-range label { display: block; margin-bottom: 3px; color: var(--lsu-muted); font-size: 11px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; }
        .lsu-range input[type="month"], .lsu-search { padding: 7px 12px; border: 1.5px solid var(--lsu-border); border-radius: 8px; background: var(--lsu-surface); color: var(--lsu-text); font-size: 13px; }
        .lsu-range input[type="month"]:focus, .lsu-search:focus { outline: none; border-color: var(--lsu-primary); }
        .lsu-range button { padding: 8px 14px; border: 1.5px solid var(--lsu-border); border-radius: 8px; background: var(--lsu-surface); color: var(--lsu-text); font-size: 12.5px; font-weight: 700; cursor: pointer; }
        .lsu-range button:hover { border-color: var(--lsu-primary); background: var(--lsu-primary-soft); }
        .lsu-search { width: 100%; max-width: 240px; }
        .lsu-meta { margin-bottom: 8px; color: var(--lsu-muted); font-size: 12px; }
        .lsu-table-wrap { max-height: 560px; overflow: auto; border: 1px solid var(--lsu-border); border-radius: 10px; }
        .lsu-table { width: 100%; margin: 0; border-collapse: collapse; font-size: 13px; font-variant-numeric: tabular-nums; }
        .lsu-table th, .lsu-table td { padding: 9px 14px; border-bottom: 1px solid var(--lsu-border); white-space: nowrap; text-align: right; }
        .lsu-table th:nth-child(-n+2), .lsu-table td:nth-child(-n+2) { text-align: left; }
        .lsu-table thead th { position: sticky; top: 0; z-index: 2; background: var(--lsu-head); color: var(--lsu-muted); font-size: 11px; font-weight: 800; letter-spacing: .5px; text-transform: uppercase; }
        .lsu-table tr.lsu-group td { background: var(--lsu-primary-soft); color: var(--lsu-text); font-size: 12px; font-weight: 800; text-align: left; }
        .lsu-table tr.lsu-group small { margin-left: 8px; color: var(--lsu-muted); font-weight: 600; }
        .lsu-table tbody tr:not(.lsu-group):hover td { background: #f7faff; }
        .lsu-table td.lsu-strong { font-weight: 800; }
        .lsu-table tr[hidden] { display: none; }
        .lsu-empty { padding: 28px; color: var(--lsu-muted); text-align: center; }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="content-wrapper">
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="lsu-header">
            <h2>Vacation and Sick Leave Summary</h2>
        </div>

        <?php if ($notice !== null): ?>
            <div class="alert alert-<?= htmlspecialchars($noticeType, ENT_QUOTES, 'UTF-8') ?>" role="alert"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="lsu-stats">
            <div class="lsu-stat"><div class="lsu-stat-label">Months</div><div class="lsu-stat-value"><?= count($months) ?></div></div>
            <div class="lsu-stat"><div class="lsu-stat-label">Employees</div><div class="lsu-stat-value"><?= (int) $summaryTotals['employees'] ?></div></div>
            <div class="lsu-stat"><div class="lsu-stat-label">Records</div><div class="lsu-stat-value"><?= (int) $summaryTotals['total'] ?></div></div>
            <div class="lsu-stat"><div class="lsu-stat-label">Latest month</div><div class="lsu-stat-value"><?= $firstMonthKey !== null ? htmlspecialchars($months[$firstMonthKey]['label'], ENT_QUOTES, 'UTF-8') : '—' ?></div></div>
        </div>

        <section class="lsu-card">
            <div class="lsu-card-head">Upload</div>
            <div class="lsu-card-body">
                <form method="post" enctype="multipart/form-data" class="lsu-upload">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['leave_summary_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="lsu-file">
                        <label for="leave_file" class="lsu-label">Workbook (.xlsx, .xls, .csv)</label>
                        <input type="file" class="form-control" id="leave_file" name="leave_file" accept=".xlsx,.xls,.csv" required>
                    </div>
                    <!-- <label class="lsu-check" for="replace_existing">
                        <input type="checkbox" id="replace_existing" name="replace_existing" value="1">
                        Replace existing records
                    </label> -->
                    <button type="submit" class="btn btn-primary" id="lsuImportBtn">Import</button>
                </form>
            </div>
        </section>

        <section class="lsu-card">
            <div class="lsu-card-head">Records</div>
            <div class="lsu-card-body">
                <?php if (!$months): ?>
                    <div class="lsu-empty">No leave summaries have been imported.</div>
                <?php else: ?>
                    <?php
                        $newestKey = array_key_first($months);
                        $oldestKey = array_key_last($months);
                    ?>
                    <div class="lsu-toolbar">
                        <div class="lsu-range">
                            <div>
                                <label for="lsuFrom">From</label>
                                <input type="month" id="lsuFrom" min="<?= $oldestKey ?>" max="<?= $newestKey ?>" value="<?= $newestKey ?>">
                            </div>
                            <div>
                                <label for="lsuTo">To</label>
                                <input type="month" id="lsuTo" min="<?= $oldestKey ?>" max="<?= $newestKey ?>" value="<?= $newestKey ?>">
                            </div>
                            <button type="button" id="lsuAll">All months</button>
                        </div>
                        <input type="search" class="lsu-search" id="lsuSearch" placeholder="Search name or employee #" autocomplete="off">
                    </div>
                    <div class="lsu-meta" id="lsuCount" aria-live="polite"></div>
                    <div class="lsu-table-wrap">
                        <table class="lsu-table" id="lsuTable">
                            <thead>
                                <tr><th>Employee #</th><th>Name</th><th>Vacation Leave</th><th>Sick Leave</th><th>Total Leave</th><th>Amount</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($months as $key => $month): ?>
                                <tr class="lsu-group" data-period="<?= $key ?>">
                                    <td colspan="6"><?= htmlspecialchars($month['label'], ENT_QUOTES, 'UTF-8') ?><small><?= count($month['rows']) ?> employees &middot; imported <?= htmlspecialchars(date('M j, Y g:i A', strtotime($month['imported'])), ENT_QUOTES, 'UTF-8') ?></small></td>
                                </tr>
                                <?php foreach ($month['rows'] as $row): ?>
                                    <tr class="lsu-row" data-period="<?= $key ?>" data-search="<?= htmlspecialchars(strtolower($row['emp_id'] . ' ' . $row['employee_name']), ENT_QUOTES, 'UTF-8') ?>">
                                        <td><?= htmlspecialchars($row['emp_id'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($row['employee_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= number_format((float) $row['vacation_leave'], 3) ?></td>
                                        <td><?= number_format((float) $row['sick_leave'], 3) ?></td>
                                        <td class="lsu-strong"><?= number_format((float) $row['total_leave'], 3) ?></td>
                                        <td>₱<?= number_format((float) $row['leave_amount'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                                <tr id="lsuNoMatch" hidden><td colspan="6" class="lsu-empty" style="text-align:center">No records in this range.</td></tr>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
<script src="../assets/vendor/js/bootstrap.js"></script>
<script src="../assets/vendor/js/menu.js"></script>
<script src="../assets/js/main.js"></script>
<script>
(function () {
    // Block double-clicks: a second submit while the first is processing.
    var form = document.querySelector('form[enctype="multipart/form-data"]');
    if (form) {
        form.addEventListener('submit', function () {
            var btn = document.getElementById('lsuImportBtn');
            btn.disabled = true;
            btn.textContent = 'Importing…';
        });
    }
})();
(function () {
    var table = document.getElementById('lsuTable');
    if (!table) return;
    var from = document.getElementById('lsuFrom');
    var to = document.getElementById('lsuTo');
    var search = document.getElementById('lsuSearch');
    var count = document.getElementById('lsuCount');
    var noMatch = document.getElementById('lsuNoMatch');
    var groups = Array.prototype.slice.call(table.querySelectorAll('tr.lsu-group'));
    var rows = Array.prototype.slice.call(table.querySelectorAll('tr.lsu-row'));
    var newest = to.max, oldest = from.min;

    function apply() {
        var lo = from.value || oldest, hi = to.value || newest;
        if (lo > hi) { var t = lo; lo = hi; hi = t; }   // tolerate From > To
        var q = search.value.trim().toLowerCase();
        var shown = 0, visibleMonths = {};
        rows.forEach(function (row) {
            var p = row.getAttribute('data-period');   // YYYY-MM compares as text
            var ok = p >= lo && p <= hi && (q === '' || row.getAttribute('data-search').indexOf(q) !== -1);
            row.hidden = !ok;
            if (ok) { shown++; visibleMonths[p] = true; }
        });
        groups.forEach(function (g) { g.hidden = !visibleMonths[g.getAttribute('data-period')]; });
        var monthCount = Object.keys(visibleMonths).length;
        noMatch.hidden = shown !== 0;
        count.textContent = shown + ' record' + (shown === 1 ? '' : 's') + ' across ' + monthCount + ' month' + (monthCount === 1 ? '' : 's');
    }

    from.addEventListener('change', apply);
    to.addEventListener('change', apply);
    search.addEventListener('input', apply);
    document.getElementById('lsuAll').addEventListener('click', function () {
        from.value = oldest;
        to.value = newest;
        apply();
    });
    apply();
})();
</script>
</body>
</html>