<?php
session_start();
include '../db.php';

/* =====================
   AUTH CHECK
===================== */
if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    die("Unauthorized access");
}

$username = $_SESSION['username'];
$normalizedRole = strtolower($_SESSION['role']);

/* =====================
   EMPLOYEE INFO
===================== */
$stmt = $conn->prepare("
    SELECT emp_ID, department 
    FROM users 
    WHERE username = ?
");
$stmt->bind_param("s", $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    die("Employee not found");
}

$empID = (int)$user['emp_ID'];
$department = $user['department'] ?? '';

/* =====================
   SELECTED BATCH
===================== */
$selectedBatch = $_GET['batch'] ?? null;

/* =====================
   PAYROLL BATCHES
===================== */
$stmt = $conn->prepare("
    SELECT 
        batch_id,
        payroll_from,
        payroll_to,
        MAX(pay_date) AS upload_date
    FROM payroll
    WHERE emp_id = ?
    GROUP BY batch_id, payroll_from, payroll_to
    ORDER BY payroll_to DESC, upload_date DESC
");
$stmt->bind_param("i", $empID);
$stmt->execute();
$res = $stmt->get_result();

$batches = [];
while ($row = $res->fetch_assoc()) {
    $batches[] = $row;
}

if (!$selectedBatch && !empty($batches)) {
    $selectedBatch = $batches[0]['batch_id'];
}

/* =====================
   CURRENT PAYROLL PERIOD
===================== */
$currentPayrollFrom = '';
$currentPayrollTo   = '';

foreach ($batches as $b) {
    if ((string)$b['batch_id'] === (string)$selectedBatch) {
        $currentPayrollFrom = $b['payroll_from'];
        $currentPayrollTo   = $b['payroll_to'];
        break;
    }
}

/* =====================
   HELPER: FORMAT REMARKS
===================== */
function formatRemarks($code, $remarks) {
    $code = strtoupper(trim($code));
    $remarks = trim((string)$remarks);

    if ($remarks === '') return '';

    // Normalize spacing
    $r = preg_replace('/\s+/', ' ', $remarks);

    /* =====================
       BASIC PAY → DAYS
    ===================== */
    if ($code === 'BASICPAY' || $code === 'BASICPAY') {

        if (!preg_match('/day/i', $r)) {
            $r .= ' days';
        }

        return " ($r)";
    }

    /* =====================
       OT / ND → HOURS
    ===================== */
    $isOTorND = (
        strpos($code, 'OT') !== false ||
        strpos($code, 'ND') === 0 ||
        strpos($code, 'ND:') !== false
    );

    if ($isOTorND) {

        if (!preg_match('/\b(hr|hrs|hour|hours)\b/i', $r)) {
            $r .= ' hrs';
        }

        return " ($r)";
    }

    return '';
}

/* =====================
   PAYROLL DETAILS
===================== */
$details   = [];
$income    = 0;
$deduction = 0;
$fundTotal = 0;

if ($selectedBatch) {

    $sql = "
        SELECT 
            REPLACE(REPLACE(UPPER(TRIM(p.account_code)), ' ', ''), '_', '') AS code,
            p.amount,
            p.remarks,
            a.account_type,
            a.account_name
        FROM payroll p
        INNER JOIN accounts a
            ON UPPER(TRIM(a.account_code)) = UPPER(TRIM(p.account_code))
        WHERE p.emp_id = ?
          AND p.batch_id = ?
        ORDER BY p.id ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $empID, $selectedBatch);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {

        $amount = (float)$row['amount'];
        $type   = strtoupper($row['account_type']);

        $details[] = [
            'code'         => $row['code'],
            'name'         => $row['account_name'],
            'amount'       => $amount,
            'remarks'      => $row['remarks'] ?? '',
            'account_type' => $type
        ];

        if ($type === 'INCOME') {
            $income += $amount;
        } elseif ($type === 'DEDUCTIONS') {
            $deduction += abs($amount);
        } elseif ($type === 'FUND') {
            $fundTotal += abs($amount);
        }
    }
}

$netPay = $income - $deduction;
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed">

<head>
    <meta charset="utf-8">
    <title>My Payslip</title>
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <meta name="description" content="">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/img/favicon/districtone.png">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Core CSS -->
    <link rel="stylesheet" href="../assets/vendor/css/core.css">
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css">
    <link rel="stylesheet" href="../assets/css/demo.css">
    <link rel="stylesheet" href="./css/profileTeams.css">

    <!-- Vendor CSS -->
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="../assets/vendor/libs/apex-charts/apex-charts.css">

    <!-- Calendar CSS -->
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/qtip2/3.0.3/jquery.qtip.min.css">

    <!-- Required Scripts -->
    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>

    <!-- Calendar Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qtip2/3.0.3/jquery.qtip.min.js"></script>

    <style>
        .payroll-item {
            border-bottom: 1px dashed #e5e7eb;
            padding: .45rem 0;
        }

        .section-title {
            font-size: .75rem;
            text-transform: uppercase;
            color: #6c757d;
            margin-bottom: .5rem;
        }

        .net-pay {
            font-size: 1.4rem;
            font-weight: 700;
        }

        .payroll-period {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 10px;
            border: 1px solid #e5e7eb;
            background: #fff;
            text-decoration: none;
            color: inherit;
            display: block;
            transition: all .2s ease;
        }

        .payroll-period:hover {
            background: #f8f9fb;
        }

        .active-period {
            background: #e7f1ff;
            border-left: 4px solid #0270ff;
            font-weight: 600;
        }

        .payroll-period-list {
            max-height: 580px;
            overflow-y: auto;
            padding-right: 6px;
        }

        .payroll-period-list::-webkit-scrollbar {
            width: 6px;
        }

        .payroll-period-list::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 6px;
        }
        /* Custom Payroll Colors */
.income-color {
    color: #00811c;   /* Blue (example) */
}

.deduction-color {
    color: #B91C1C;   /* Dark Red (example) */
}
    </style>

</head>

<body>

<!-- SIDEBAR (ROLE BASED) -->
<?php
switch ($normalizedRole) {
    case 'user': include '../user/sidebar.php'; break;
    case 'mis': include '../mis/sidebar.php'; break;
    case 'admin': include '../admin/sidebar.php'; break;
    case 'super admin': include '../super_admin/sidebar.php'; break;
    default: exit('Unauthorized');
}
?>

<div class="container-xxl container-p-y">

    <!-- HEADER -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">

                <div class="col-md-4">
                    <small class="text-muted">Employee ID</small><br>
                    <strong><?= htmlspecialchars($empID) ?></strong>
                </div>

                <div class="col-md-4">
                    <small class="text-muted">Department</small><br>
                    <strong><?= htmlspecialchars($department) ?></strong>
                </div>

                <div class="col-md-4 text-md-end">
                    <small class="text-muted">Payroll Period</small><br>
                    <strong>
                        <?= $currentPayrollFrom && $currentPayrollTo
                            ? date('M d, Y', strtotime($currentPayrollFrom)) . ' – ' . date('M d, Y', strtotime($currentPayrollTo))
                            : 'N/A'
                        ?>
                    </strong>
                </div>

            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- LEFT: PERIOD LIST -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="mb-3">Payroll Period</h6>

                    <div class="payroll-period-list">
                        <?php foreach ($batches as $b): ?>
                            <?php $active = ((string)$b['batch_id'] === (string)$selectedBatch); ?>
                            <a href="?batch=<?= urlencode($b['batch_id']) ?>"
                               class="payroll-period <?= $active ? 'active-period' : '' ?>"
                               <?= $active ? 'id="active-period-item"' : '' ?>>
                                <strong>
                                    <?= date('M d, Y', strtotime($b['payroll_from'])) ?>
                                    –
                                    <?= date('M d, Y', strtotime($b['payroll_to'])) ?>
                                </strong><br>
                                <small class="text-muted">
                                    <?= date('l', strtotime($b['payroll_from'])) ?>
                                    to
                                    <?= date('l', strtotime($b['payroll_to'])) ?>
                                </small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: PAYSLIP -->
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-body">

                    <!-- INCOME -->
                    <div class="mb-4">
                        <div class="section-title">Income</div>
                        <?php $totalIncome = 0; ?>

                                            <?php foreach ($details as $d): ?>
                            <?php if ($d['account_type'] === 'INCOME'): ?>
                                <?php $totalIncome += $d['amount']; ?>

                                <?php
                                $formattedRemarks = formatRemarks($d['code'], $d['remarks']);
                                ?>

                                <div class="d-flex justify-content-between payroll-item">
                                    
                                    <span>
                                        <?= htmlspecialchars($d['name'] . $formattedRemarks) ?>
                                    </span>

                                    <span class="income-color fw-semibold">
                                        ₱<?= number_format($d['amount'], 2) ?>
                                    </span>

                                </div>

                            <?php endif; ?>
                        <?php endforeach; ?>

                        <div class="d-flex justify-content-between pt-2 fw-bold">
                            <span>Total Earnings</span>
                            <span class="income-color">₱<?= number_format($totalIncome, 2) ?></span>
                        </div>
                    </div>

                    <!-- DEDUCTIONS -->
                    <div class="mb-4">
                        <div class="section-title">Deductions</div>
                        <?php $totalDeduction = 0; ?>

                       <?php foreach ($details as $d): ?>
                            <?php if ($d['account_type'] === 'DEDUCTIONS'): ?>
                                <?php
                                    $amt = abs($d['amount']);
                                    $totalDeduction += $amt;

                                    $formattedRemarks = formatRemarks($d['code'], $d['remarks']);
                                ?>

                                <div class="d-flex justify-content-between payroll-item">

                                    <span>
                                        <?= htmlspecialchars($d['name'] . $formattedRemarks) ?>
                                    </span>

                                    <span class="deduction-color fw-semibold">
                                        - ₱<?= number_format($amt, 2) ?>
                                    </span>

                                </div>

                            <?php endif; ?>
                        <?php endforeach; ?>

                        <div class="d-flex justify-content-between pt-2 fw-bold">
                            <span>Total Deductions</span>
                            <span class="deduction-color">₱<?= number_format($totalDeduction, 2) ?></span>
                        </div>
                    </div>

                    <!-- NET PAY -->
                    <div class="pt-3 border-top d-flex justify-content-between">
                        <strong>Net Pay</strong>
                        <span class="net-pay text-primary">
                            ₱<?= number_format($netPay, 2) ?>
                        </span>
                    </div>

                    <!-- PRINT -->
                    <div class="text-end mt-3">
                        <?php if ($selectedBatch && $netPay > 0): ?>
                            <a href="print_payslip.php?batch=<?= urlencode($selectedBatch) ?>
                                &payroll_from=<?= urlencode($currentPayrollFrom) ?>
                                &payroll_to=<?= urlencode($currentPayrollTo) ?>"
                               target="_blank"
                               class="btn btn-sm btn-primary">
                                <i class="fa fa-print me-1"></i> Print Payslip
                            </a>
                        <?php else: ?>
                            <button class="btn btn-sm btn-secondary" disabled>
                                <i class="fa fa-print me-1"></i> Print Payslip
                            </button>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>

    </div>
</div>

<style>
.payroll-item span {
    display: inline-block;
}
</style>

<!-- JS -->
<script src="../assets/vendor/libs/jquery/jquery.js"></script>
<script src="../assets/vendor/js/bootstrap.js"></script>
<script src="../assets/vendor/js/menu.js"></script>
<script src="../assets/js/main.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const activeItem = document.getElementById('active-period-item');
    if (activeItem) {
        activeItem.scrollIntoView({ block: 'center', behavior: 'auto' });
    }
});
</script>

</body>
</html>

