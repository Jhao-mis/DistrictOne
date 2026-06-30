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
        :root {
            --pr-bg: #f7f8fa;
            --pr-surface: #ffffff;
            --pr-border: #e8eaee;
            --pr-text: #1f2430;
            --pr-text-muted: #767e8c;
            --pr-primary: #7cb9ff;
            --pr-primary-soft: #eaf3ff;
            --pr-income: #1f9d55;
            --pr-income-bg: #e4f7ea;
            --pr-deduction: #c0392b;
            --pr-deduction-bg: #fbe9e7;
            --pr-radius: 12px;
            --pr-shadow: 0 1px 2px rgba(20,20,43,.04), 0 8px 24px -12px rgba(20,20,43,.10);
        }

        .pr-wrap { font-family: inherit; color: var(--pr-text); }

        /* ── Summary header ───────────────────────────────────────────── */
        .pr-header-card {
            background: linear-gradient(135deg, var(--pr-primary) 0%, #4e96f0 100%);
            border-radius: var(--pr-radius);
            padding: 22px 26px;
            color: #fff;
            margin-bottom: 18px;
            box-shadow: var(--pr-shadow);
        }
        .pr-header-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 18px;
            align-items: center;
        }
        .pr-header-eyebrow {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .6px;
            text-transform: uppercase;
            opacity: .75;
            margin-bottom: 4px;
        }
        .pr-header-value {
            font-size: 16px;
            font-weight: 700;
        }
        .pr-header-grid > div:last-child { text-align: right; }
        @media (max-width: 700px) {
            .pr-header-grid > div:last-child { text-align: left; }
        }

        /* ── Cards ─────────────────────────────────────────────────────── */
        .pr-card {
            background: var(--pr-surface);
            border: 1px solid var(--pr-border);
            border-radius: var(--pr-radius);
            box-shadow: var(--pr-shadow);
            overflow: hidden;
        }
        .pr-card-head {
            padding: 16px 20px;
            border-bottom: 1px solid var(--pr-border);
            font-weight: 700;
            font-size: 14.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .pr-card-head svg { width: 16px; height: 16px; color: var(--pr-text-muted); }
        .pr-card-body { padding: 18px 20px; }

        /* ── Period list ───────────────────────────────────────────────── */
        .pr-period-list {
            max-height: 580px;
            overflow-y: auto;
            padding-right: 4px;
        }
        .pr-period-list::-webkit-scrollbar { width: 6px; }
        .pr-period-list::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 6px; }

        .pr-period {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 13px;
            border-radius: 10px;
            margin-bottom: 8px;
            border: 1.5px solid var(--pr-border);
            background: var(--pr-surface);
            text-decoration: none;
            color: inherit;
            transition: border-color .12s ease, background .12s ease, transform .12s ease;
        }
        .pr-period:hover { border-color: var(--pr-primary); background: var(--pr-primary-soft); transform: translateX(2px); }
        .pr-period.is-active {
            background: var(--pr-primary-soft);
            border-color: var(--pr-primary);
        }
        .pr-period-icon {
            width: 34px; height: 34px; border-radius: 9px;
            background: var(--pr-bg);
            color: var(--pr-text-muted);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .pr-period.is-active .pr-period-icon { background: var(--pr-primary); color: #fff; }
        .pr-period-icon svg { width: 16px; height: 16px; }
        .pr-period-text { flex: 1; min-width: 0; }
        .pr-period-range { font-size: 13px; font-weight: 700; color: var(--pr-text); }
        .pr-period-days { font-size: 11.5px; color: var(--pr-text-muted); margin-top: 1px; }
        .pr-period-check { width: 16px; height: 16px; color: var(--pr-primary); flex-shrink: 0; }

        .pr-empty-periods { text-align: center; padding: 30px 10px; color: var(--pr-text-muted); font-size: 13px; }

        /* ── Payslip sections ─────────────────────────────────────────── */
        .pr-section { margin-bottom: 22px; }
        .pr-section-title {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--pr-text-muted);
            margin-bottom: 10px;
        }
        .pr-section-title svg { width: 14px; height: 14px; }
        .pr-section-title.is-income svg { color: var(--pr-income); }
        .pr-section-title.is-deduction svg { color: var(--pr-deduction); }

        .pr-line {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 12px;
            padding: 9px 0;
            border-bottom: 1px dashed var(--pr-border);
            font-size: 13.5px;
        }
        .pr-line:last-of-type { border-bottom: none; }
        .pr-line-label { color: var(--pr-text); }
        .pr-line-amount { font-weight: 600; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .pr-line-amount.is-income { color: var(--pr-income); }
        .pr-line-amount.is-deduction { color: var(--pr-deduction); }

        .pr-section-total {
            display: flex;
            justify-content: space-between;
            padding-top: 10px;
            margin-top: 4px;
            border-top: 1.5px solid var(--pr-border);
            font-weight: 700;
            font-size: 13.5px;
        }
        .pr-section-total.is-income { color: var(--pr-income); }
        .pr-section-total.is-deduction { color: var(--pr-deduction); }

        .pr-no-lines { font-size: 12.5px; color: var(--pr-text-muted); padding: 6px 0; }

        /* ── Net pay ───────────────────────────────────────────────────── */
        .pr-netpay {
            background: linear-gradient(135deg, var(--pr-primary) 0%, #4e96f0 100%);
            border-radius: 12px;
            padding: 18px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #fff;
            margin-top: 6px;
        }
        .pr-netpay-label {
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: .5px;
            font-weight: 700;
            opacity: .8;
            margin-bottom: 3px;
        }
        .pr-netpay-amount { font-size: 26px; font-weight: 800; letter-spacing: -.3px; }

        /* ── Print button ──────────────────────────────────────────────── */
        .pr-print-row { text-align: right; margin-top: 16px; }
        .pr-btn-print {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: var(--pr-primary);
            color: #fff;
            border: none;
            font-weight: 600;
            font-size: 13.5px;
            padding: 10px 18px;
            border-radius: 9px;
            text-decoration: none;
            cursor: pointer;
            box-shadow: 0 4px 10px -4px rgba(124,185,255,.55);
            transition: background .12s ease, transform .12s ease;
        }
        .pr-btn-print:hover { background: #4e96f0; color: #fff; transform: translateY(-1px); }
        .pr-btn-print:disabled, .pr-btn-print.is-disabled {
            background: #d3d6dc;
            color: #8c919c;
            box-shadow: none;
            cursor: not-allowed;
        }
        .pr-btn-print svg { width: 15px; height: 15px; }
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
<div class="pr-wrap">

    <!-- HEADER -->
    <div class="pr-header-card">
        <div class="pr-header-grid">
            <div>
                <div class="pr-header-eyebrow">Employee ID</div>
                <div class="pr-header-value">#<?= htmlspecialchars($empID) ?></div>
            </div>
            <div>
                <div class="pr-header-eyebrow">Department</div>
                <div class="pr-header-value"><?= htmlspecialchars($department ?: '—') ?></div>
            </div>
            <div>
                <div class="pr-header-eyebrow">Payroll Period</div>
                <div class="pr-header-value">
                    <?= $currentPayrollFrom && $currentPayrollTo
                        ? date('M d, Y', strtotime($currentPayrollFrom)) . ' – ' . date('M d, Y', strtotime($currentPayrollTo))
                        : 'N/A'
                    ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- LEFT: PERIOD LIST -->
        <div class="col-md-4">
            <div class="pr-card h-100">
                <div class="pr-card-head">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    Payroll Periods
                </div>
                <div class="pr-card-body">
                    <div class="pr-period-list">
                        <?php if (empty($batches)): ?>
                            <div class="pr-empty-periods">No payroll periods found yet.</div>
                        <?php endif; ?>

                        <?php foreach ($batches as $b): ?>
                            <?php $active = ((string)$b['batch_id'] === (string)$selectedBatch); ?>
                            <a href="?batch=<?= urlencode($b['batch_id']) ?>"
                               class="pr-period <?= $active ? 'is-active' : '' ?>"
                               <?= $active ? 'id="active-period-item"' : '' ?>>
                                <div class="pr-period-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                                </div>
                                <div class="pr-period-text">
                                    <div class="pr-period-range">
                                        <?= date('M d, Y', strtotime($b['payroll_from'])) ?>
                                        –
                                        <?= date('M d, Y', strtotime($b['payroll_to'])) ?>
                                    </div>
                                    <div class="pr-period-days">
                                        <?= date('l', strtotime($b['payroll_from'])) ?>
                                        to
                                        <?= date('l', strtotime($b['payroll_to'])) ?>
                                    </div>
                                </div>
                                <?php if ($active): ?>
                                    <svg class="pr-period-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: PAYSLIP -->
        <div class="col-md-8">
            <div class="pr-card h-100">
                <div class="pr-card-head">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6M9 16h6M9 8h6M5 4h14v16l-3-2-3 2-3-2-3 2V4z"/></svg>
                    Payslip Breakdown
                </div>
                <div class="pr-card-body">

                    <!-- INCOME -->
                    <div class="pr-section">
                        <div class="pr-section-title is-income">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                            Income
                        </div>

                        <?php $totalIncome = 0; $hasIncome = false; ?>
                        <?php foreach ($details as $d): ?>
                            <?php if ($d['account_type'] === 'INCOME'): ?>
                                <?php
                                $hasIncome = true;
                                $totalIncome += $d['amount'];
                                $formattedRemarks = formatRemarks($d['code'], $d['remarks']);
                                ?>
                                <div class="pr-line">
                                    <span class="pr-line-label"><?= htmlspecialchars($d['name'] . $formattedRemarks) ?></span>
                                    <span class="pr-line-amount is-income">₱<?= number_format($d['amount'], 2) ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <?php if (!$hasIncome): ?>
                            <div class="pr-no-lines">No income entries for this period.</div>
                        <?php endif; ?>

                        <div class="pr-section-total is-income">
                            <span>Total Earnings</span>
                            <span>₱<?= number_format($totalIncome, 2) ?></span>
                        </div>
                    </div>

                    <!-- DEDUCTIONS -->
                    <div class="pr-section">
                        <div class="pr-section-title is-deduction">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                            Deductions
                        </div>

                        <?php $totalDeduction = 0; $hasDeduction = false; ?>
                        <?php foreach ($details as $d): ?>
                            <?php if ($d['account_type'] === 'DEDUCTIONS'): ?>
                                <?php
                                $hasDeduction = true;
                                $amt = abs($d['amount']);
                                $totalDeduction += $amt;
                                $formattedRemarks = formatRemarks($d['code'], $d['remarks']);
                                ?>
                                <div class="pr-line">
                                    <span class="pr-line-label"><?= htmlspecialchars($d['name'] . $formattedRemarks) ?></span>
                                    <span class="pr-line-amount is-deduction">- ₱<?= number_format($amt, 2) ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <?php if (!$hasDeduction): ?>
                            <div class="pr-no-lines">No deductions for this period.</div>
                        <?php endif; ?>

                        <div class="pr-section-total is-deduction">
                            <span>Total Deductions</span>
                            <span>₱<?= number_format($totalDeduction, 2) ?></span>
                        </div>
                    </div>

                    <!-- NET PAY -->
                    <div class="pr-netpay">
                        <div>
                            <div class="pr-netpay-label">Net Pay</div>
                            <div class="pr-netpay-amount">₱<?= number_format($netPay, 2) ?></div>
                        </div>
                        <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.6" opacity="0.7"><rect x="2" y="6" width="20" height="13" rx="2"/><circle cx="12" cy="12.5" r="3"/><path d="M6 6V4h12v2"/></svg>
                    </div>

                    <!-- PRINT -->
                    <div class="pr-print-row">
                        <?php if ($selectedBatch && $netPay > 0): ?>
                            <a href="print_payslip.php?batch=<?= urlencode($selectedBatch) ?>
                                &payroll_from=<?= urlencode($currentPayrollFrom) ?>
                                &payroll_to=<?= urlencode($currentPayrollTo) ?>"
                               target="_blank"
                               class="pr-btn-print">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                Print Payslip
                            </a>
                        <?php else: ?>
                            <button class="pr-btn-print is-disabled" disabled>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                Print Payslip
                            </button>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>

    </div>
</div>
</div>

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