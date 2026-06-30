<?php
session_start();
include '../db.php';

/* =====================
   AUTH CHECK
===================== */
if (!isset($_SESSION['username'])) {
    die("Unauthorized access");
}

$username = $_SESSION['username'];

/* =====================
   EMPLOYEE INFO
===================== */
$stmt = $conn->prepare("
    SELECT emp_ID, department, firstname, middlename, lastname
    FROM users
    WHERE username = ?
");
$stmt->bind_param("s", $username);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    die("Employee record not found.");
}

$user = $res->fetch_assoc();

$empID = (int)$user['emp_ID'];
$department = $user['department'] ?? '';
$fullName = trim(
    $user['firstname'] . ' ' .
    ($user['middlename'] ?? '') . ' ' .
    $user['lastname']
);

/* =====================
   GET SELECTED BATCH
===================== */
$batchId     = $_GET['batch'] ?? null;
$payrollFrom = $_GET['payroll_from'] ?? null;
$payrollTo   = $_GET['payroll_to'] ?? null;

if (!$batchId) {
    die("Invalid payroll batch.");
}

/* =====================
   FETCH PAYROLL DATA
===================== */
$sql = "
    SELECT 
        p.amount,
        p.remarks,
        p.account_code,
        a.account_name,
        a.account_type
    FROM payroll p
    INNER JOIN accounts a
        ON UPPER(TRIM(a.account_code)) = UPPER(TRIM(p.account_code))
    WHERE p.emp_id = ?
      AND p.batch_id = ?
    ORDER BY p.id ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $empID, $batchId);
$stmt->execute();
$result = $stmt->get_result();

$earnings   = [];
$deductions = [];

$totalIncome    = 0;
$totalDeduction = 0;

/* =====================
   PROCESS DATA
===================== */
/* =====================
   PROCESS DATA
===================== */
while ($row = $result->fetch_assoc()) {

    $amount  = (float)$row['amount'];
    $name    = $row['account_name'];
    $type    = strtoupper($row['account_type']);
    $code    = strtoupper(trim($row['account_code']));
    $remarks = trim((string)$row['remarks']);

    $formattedRemarks = '';

    /* =====================
       BASIC PAY → DAYS
    ===================== */
    if ($code === 'BASIC PAY' && $remarks !== '') {

        $r = preg_replace('/\s+/', ' ', $remarks);

        if (!preg_match('/day/i', $r)) {
            $r .= ' days';
        }

        $formattedRemarks = " ($r)";
    }

    /* =====================
       OT / ND → HOURS
    ===================== */
    elseif (
        (strpos($code, 'OT') !== false ||
         strpos($code, 'ND') === 0 ||
         strpos($code, 'ND:') !== false)
        && $remarks !== ''
    ) {

        $r = preg_replace('/\s+/', ' ', $remarks);

        if (!preg_match('/\b(hr|hrs|hour|hours)\b/i', $r)) {
            $r .= ' hrs';
        }

        $formattedRemarks = " ($r)";
    }

    $displayName = $name . $formattedRemarks;

    if ($type === 'INCOME') {
        $earnings[] = [
            'name' => $displayName,
            'amount' => $amount
        ];
        $totalIncome += $amount;

    } elseif ($type === 'DEDUCTIONS') {
        $deductions[] = [
            'name' => $displayName,
            'amount' => abs($amount)
        ];
        $totalDeduction += abs($amount);
    }
}

$netPay = $totalIncome - $totalDeduction;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Print Payslip</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/favicon/districtone.png">

    <style>
        @page {
            size: A4 portrait;
            margin: 0mm; /* Zero margins to allow precise alignment to the top of the sheet */
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 8mm 10mm 0mm 10mm; /* Extra padding on top, clean breathing room on sides */
            line-height: 1.2;
            background-color: #fff;
        }

        .confidential-note {
            text-align: right;
            font-size: 7px;
            color: #777;
            font-style: italic;
            margin-bottom: 4px;
        }

        /* Centered Header Stack */
        .print-header-container {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }

        .print-header img {
            max-height: 35px;
            width: auto;
            margin-bottom: 4px;
        }

        .print-header-text .title {
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .print-header-text .subtitle {
            font-size: 8px;
            color: #444;
        }

        .section {
            margin-bottom: 6px;
        }

        .section-title {
            font-weight: bold;
            background: #e6e6e6;
            padding: 2px 4px;
            margin-bottom: 3px;
            font-size: 9px;
            text-transform: uppercase;
        }

        /* 2-Column Grid for Employee Details */
        .emp-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2px 15px;
            margin-bottom: 6px;
        }

        .row {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px dashed #eee;
        }

        /* Side-by-Side Table Layout */
        .financial-container {
            display: flex;
            gap: 10px;
        }

        .financial-col {
            flex: 1;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            border: 1px solid #aaa;
            padding: 3px 5px;
            font-size: 9px;
        }

        .table th {
            background: #f5f5f5;
            text-align: left;
        }

        .amount-col {
            width: 80px;
            text-align: right;
            white-space: nowrap;
        }

        /* Summary & Footer Alignment */
        .summary-footer-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 6px;
            padding-top: 4px;
            border-top: 1px solid #000;
        }

        .total {
            font-size: 11px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .netpay-value {
            border-bottom: 3px double #000;
            font-size: 12px;
            font-weight: bold;
            padding-left: 5px;
        }

        .footer {
            font-size: 8px;
            color: #555;
        }

        /* Perforated Cut-Line Style */
        .cut-here-line {
            margin-top: 25px;
            border-top: 1px dashed #bbb;
            text-align: center;
            font-size: 8px;
            color: #999;
            letter-spacing: 2px;
            line-height: 0.1;
        }

        .cut-here-line span {
            background: #fff;
            padding: 0 10px;
        }
    </style>
</head>

<body>

    <div class="confidential-note">
        <strong>STRICTLY CONFIDENTIAL</strong> - For Authorized Recipient Only
    </div>

    <!-- CENTERED LOGO & HEADER STACK -->
    <div class="print-header-container">
        <div class="print-header">
            <img src="letter_head.png" alt="Company Header">
        </div>
        <div class="print-header-text">
            <div class="title">PAYSLIP</div>
            <div class="subtitle">Automated Payslip by DistrictOne</div>
        </div>
    </div>

    <!-- EMPLOYEE INFO -->
    <div class="section">
        <div class="emp-grid">
            <div class="row"><span><strong>Name:</strong></span><span><?= htmlspecialchars($fullName) ?></span></div>
            <div class="row"><span><strong>Department:</strong></span><span><?= htmlspecialchars($department) ?></span></div>
            <div class="row"><span><strong>Employee ID:</strong></span><span><?= $empID ?></span></div>
            <div class="row">
                <span><strong>Pay Period:</strong></span>
                <span>
                    <?= $payrollFrom && $payrollTo
                        ? date('M d, Y', strtotime($payrollFrom)) . ' – ' . date('M d, Y', strtotime($payrollTo))
                        : 'N/A'; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- SIDE-BY-SIDE TABLES Framework -->
    <div class="financial-container">
        
        <!-- EARNINGS -->
        <div class="financial-col">
            <div class="section-title">Earnings</div>
            <table class="table">
                <tr>
                    <th>Description</th>
                    <th class="amount-col">Amount</th>
                </tr>

                <?php foreach ($earnings as $e): ?>
                    <tr>
                        <td><?= htmlspecialchars($e['name']) ?></td>
                        <td class="amount-col"><?= number_format($e['amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>

                <tr>
                    <td><strong>Total Earnings</strong></td>
                    <td class="amount-col"><strong><?= number_format($totalIncome, 2) ?></strong></td>
                </tr>
            </table>
        </div>

        <!-- DEDUCTIONS -->
        <div class="financial-col">
            <div class="section-title">Deductions</div>
            <table class="table">
                <tr>
                    <th>Description</th>
                    <th class="amount-col">Amount</th>
                </tr>

                <?php foreach ($deductions as $d): ?>
                    <tr>
                        <td><?= htmlspecialchars($d['name']) ?></td>
                        <td class="amount-col"><?= number_format($d['amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>

                <tr>
                    <td><strong>Total Deductions</strong></td>
                    <td class="amount-col"><strong><?= number_format($totalDeduction, 2) ?></strong></td>
                </tr>
            </table>
        </div>

    </div>

    <!-- SUMMARY & FOOTER ROW -->
    <div class="summary-footer-container">
        <div class="footer">
            This is a system-generated payslip. No signature required.
        </div>
        <div class="total">
            <span>NET PAY:</span>
            <span class="netpay-value"><?= number_format($netPay, 2) ?></span>
        </div>
    </div>

    <!-- CUT GUIDE LINE -->
    <div class="cut-here-line">
        <span>✂ Content ends here — Cut along line ✂</span>
    </div>

    <script>
        window.onload = function () {
            window.print();
            window.onafterprint = () => window.close();
        };
    </script>

</body>

</html>