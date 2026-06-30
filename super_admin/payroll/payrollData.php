<?php
include '../../db.php';

/* =====================
   GET SELECTED BATCH
===================== */
$selectedBatch = $_GET['batch_id'] ?? '';

/* =====================
   FETCH PAYROLL PERIOD LIST
===================== */
$batchSql = "
    SELECT 
        batch_id,
        COUNT(*) AS total_records,
        payroll_from,
        payroll_to,
        MAX(pay_date) AS upload_date
    FROM payroll
    GROUP BY batch_id, payroll_from, payroll_to
    ORDER BY batch_id DESC, upload_date DESC
";
$batchResult = $conn->query($batchSql);

/* =====================
   FETCH PAYROLL DETAILS
===================== */
$detailsResult = null;
if ($selectedBatch !== '') {
    $detailSql = "
        SELECT *
        FROM payroll
        WHERE batch_id = ?
        ORDER BY emp_id, account_code
    ";
    $stmt = $conn->prepare($detailSql);
    $stmt->bind_param("i", $selectedBatch);
    $stmt->execute();
    $detailsResult = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Payroll Data</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/favicon/districtone.png">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        .payroll-layout {
            height: calc(100vh - 160px);
        }

        .batch-list {
            overflow-y: auto;
            height: 100%;
        }

        .batch-details {
            overflow-y: auto;
            height: 100%;
        }

        .sticky-header {
            position: sticky;
            top: 0;
            background: #fff;
            z-index: 10;
        }

        .table thead th {
            position: sticky;
            top: 0;
            background-color: #212529;
            color: #fff;
            z-index: 20;
        }
    </style>
</head>

<body class="bg-light">

    <div class="container py-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0">Payroll Data</h3>

            <a href="../payrollmng.php" class="btn btn-outline-primary btn-sm">
                ← Back to Upload
            </a>
        </div>

        <div class="row payroll-layout g-3">

            <!-- LEFT PANEL -->
            <div class="col-md-4 h-100">
                <div class="card h-100 shadow-sm">

                    <div class="card-header fw-semibold sticky-header">
                        Payroll Periods
                    </div>

                    <div class="batch-list list-group list-group-flush">
                        <?php if ($batchResult && $batchResult->num_rows > 0): ?>
                            <?php while ($batch = $batchResult->fetch_assoc()): ?>
                                <a href="?batch_id=<?= $batch['batch_id']; ?>" class="list-group-item list-group-item-action
                               <?= ($batch['batch_id'] == $selectedBatch) ? 'active' : ''; ?>">

                                    <div class="fw-semibold">
                                        Batch #<?= $batch['batch_id']; ?>
                                    </div>

                                    <div>
                                        <?= date('M d, Y', strtotime($batch['payroll_from'])); ?>
                                        –
                                        <?= date('M d, Y', strtotime($batch['payroll_to'])); ?>
                                    </div>

                                    <small class="text-muted">
                                        <?= $batch['total_records']; ?> records
                                    </small>
                                </a>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="p-3 text-muted">
                                No payroll periods found.
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

            <!-- RIGHT PANEL -->
            <div class="col-md-8 h-100">
                <div class="card h-100 shadow-sm">

                    <div class="card-header fw-semibold sticky-header">
                        <?= $selectedBatch
                            ? "Payroll Records - Batch #{$selectedBatch}"
                            : "Select a Payroll Period" ?>
                    </div>

                    <div class="batch-details">
                        <?php if ($selectedBatch && $detailsResult && $detailsResult->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Batch</th>
                                            <th>Emp ID</th>
                                            <th>Account Code</th>
                                            <th>Account Type</th>
                                            <th>Amount</th>
                                            <th>Payroll From</th>
                                            <th>Payroll To</th>
                                            <th>Pay Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $detailsResult->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= $row['batch_id']; ?></td>
                                                <td><?= $row['emp_id']; ?></td>
                                                <td><?= htmlspecialchars($row['account_code']); ?></td>
                                                <td><?= htmlspecialchars($row['account_type']); ?></td>
                                                <td><?= number_format((float) $row['amount'], 2); ?></td>
                                                <td><?= $row['payroll_from']; ?></td>
                                                <td><?= $row['payroll_to']; ?></td>
                                                <td><?= $row['pay_date']; ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php elseif ($selectedBatch): ?>
                            <div class="p-4 text-muted">
                                No records found for this payroll period.
                            </div>
                        <?php else: ?>
                            <div class="p-4 text-muted">
                                Please select a payroll period to view records.
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

        </div>

    </div>

</body>

</html>