<?php
session_start();
require '../db.php';
require '../lib/access_control.php';
require 'login_verification.php';

access_require('special.payroll_management');

if (empty($_SESSION['payroll_csrf'])) {
    $_SESSION['payroll_csrf'] = bin2hex(random_bytes(32));
}

$notice = null;
$noticeType = 'success';
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        $notice = 'Payroll data has been uploaded successfully.';
    } elseif ($_GET['status'] === 'error') {
        $notice = (string) ($_GET['msg'] ?? 'An error occurred during upload.');
        $noticeType = 'danger';
    }
}

$batches = [];
$rows = [];
$recordsError = null;
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
foreach (['dateFrom', 'dateTo'] as $dateField) {
    $dateFilter = $dateField === 'dateFrom' ? $dateFrom : $dateTo;
    $parsedDate = DateTime::createFromFormat('!Y-m-d', $dateFilter);
    $dateErrors = DateTime::getLastErrors();
    if ($dateFilter !== '' && (
        !$parsedDate
        || ($dateErrors !== false && ($dateErrors['warning_count'] || $dateErrors['error_count']))
        || $parsedDate->format('Y-m-d') !== $dateFilter
    )) {
        if ($dateField === 'dateFrom') {
            $dateFrom = '';
        } else {
            $dateTo = '';
        }
    }
}
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

try {
    $batchResult = $conn->query('
        SELECT batch_id, payroll_from, payroll_to, COUNT(*) AS total_records, MAX(pay_date) AS pay_date
        FROM payroll
        GROUP BY batch_id, payroll_from, payroll_to
        ORDER BY payroll_to DESC, batch_id DESC
    ');
    if ($batchResult === false) {
        throw new RuntimeException($conn->error);
    }

    while ($batch = $batchResult->fetch_assoc()) {
        $batches[] = $batch;
    }
    if ($dateFrom !== '' || $dateTo !== '') {
        $batches = array_values(array_filter($batches, static function ($batch) use ($dateFrom, $dateTo) {
            $batchFrom = (string) ($batch['payroll_from'] ?? '');
            $batchTo = (string) ($batch['payroll_to'] ?? '');
            if ($dateFrom !== '' && $batchTo !== '' && $batchTo < $dateFrom) {
                return false;
            }
            if ($dateTo !== '' && $batchFrom !== '' && $batchFrom > $dateTo) {
                return false;
            }
            return true;
        }));
    }
} catch (Throwable $exception) {
    error_log('Payroll batch summary query failed: ' . $exception->getMessage());
    $recordsError = 'Payroll batches could not be loaded.';
}

$requestedBatchId = filter_var($_GET['batch_id'] ?? null, FILTER_VALIDATE_INT);
$requestedFrom = (string) ($_GET['payroll_from'] ?? '');
$requestedTo = (string) ($_GET['payroll_to'] ?? '');
$selectedBatch = null;
foreach ($batches as $batch) {
    if ((int) $batch['batch_id'] === $requestedBatchId
        && (string) ($batch['payroll_from'] ?? '') === $requestedFrom
        && (string) ($batch['payroll_to'] ?? '') === $requestedTo) {
        $selectedBatch = $batch;
        break;
    }
}

$pageSize = 100;
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$page = $page && $page > 0 ? $page : 1;
$totalPages = $selectedBatch ? max(1, (int) ceil((int) $selectedBatch['total_records'] / $pageSize)) : 0;
if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
}

if ($selectedBatch !== null && $recordsError === null) {
    $offset = ($page - 1) * $pageSize;
    try {
        $recordsStmt = $conn->prepare('
            SELECT batch_id, emp_id, account_code, account_type, amount, pay_date, week,
                   payroll_from, payroll_to, remarks
            FROM payroll
            WHERE batch_id = ? AND payroll_from <=> ? AND payroll_to <=> ?
            ORDER BY emp_id, account_code, id
            LIMIT ? OFFSET ?
        ');
        if (!$recordsStmt) {
            throw new RuntimeException($conn->error);
        }
        $recordsStmt->bind_param(
            'issii',
            $selectedBatch['batch_id'],
            $selectedBatch['payroll_from'],
            $selectedBatch['payroll_to'],
            $pageSize,
            $offset
        );
        if (!$recordsStmt->execute()) {
            throw new RuntimeException($recordsStmt->error);
        }
        $rows = $recordsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $recordsStmt->close();
    } catch (Throwable $exception) {
        error_log('Payroll records query failed: ' . $exception->getMessage());
        $recordsError = 'Payroll records for this batch could not be loaded.';
    }
}
$escape = static function ($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
};
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
      data-assets-path="../assets/" data-template="vertical-menu-template-free">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payroll Management</title>
    <link rel="icon" type="image/png" href="../assets/img/favicon/districtone.png">
    <link rel="stylesheet" href="../assets/vendor/css/core.css">
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css">
    <link rel="stylesheet" href="../assets/css/demo.css">
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css">
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css">
    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>
    <style>
        .payroll-card { margin-bottom: 18px; background: #fff; border: 1px solid #e8eaee; border-radius: 14px; box-shadow: 0 1px 2px rgba(20,20,43,.04); }
        .payroll-card-head { padding: 14px 20px; border-bottom: 1px solid #e8eaee; color: #1f2430; font-size: 14px; font-weight: 800; }
        .payroll-card-body { padding: 18px 20px; }
        .payroll-toolbar { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
        .payroll-filter label { display: block; margin-bottom: 4px; color: #767e8c; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .payroll-filter input, .payroll-filter select { min-width: 220px; padding: 7px 12px; border: 1.5px solid #e8eaee; border-radius: 8px; }
        .payroll-date-filter { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; }
        .payroll-date-filter .payroll-filter input { min-width: 180px; }
        .payroll-batch-list { max-height: 320px; overflow-y: auto; }
        .payroll-table-wrap { max-height: 340px; overflow: auto; border: 1px solid #e8eaee; border-radius: 10px; }
        .payroll-table { width: 100%; margin: 0; border-collapse: collapse; font-size: 13px; font-variant-numeric: tabular-nums; }
        .payroll-table th, .payroll-table td { padding: 9px 12px; border-bottom: 1px solid #e8eaee; white-space: nowrap; }
        .payroll-table thead th { position: sticky; top: 0; z-index: 2; background: #f3f6fb; color: #767e8c; font-size: 11px; text-transform: uppercase; }
        .payroll-table th button { padding: 0; border: 0; background: transparent; color: inherit; font: inherit; cursor: pointer; }
        .payroll-table tbody tr:hover td { background: #f7faff; }
        .payroll-table tr[hidden] { display: none; }
        .payroll-empty { padding: 28px; color: #767e8c; text-align: center; }
    </style>
</head>
<body>
<?php
$normalizedRole = access_normalize_role((string) ($_SESSION['role'] ?? ''));
switch ($normalizedRole) {
    case 'User':
        include '../user/sidebar.php';
        break;
    case 'mis':
        include '../mis/sidebar.php';
        break;
    case 'Admin':
        include '../admin/sidebar.php';
        break;
    case 'Super Admin':
        include '../super_admin/sidebar.php';
        break;
    default:
        http_response_code(403);
        exit('Unauthorized');
}
?>
<div class="content-wrapper">
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h2 class="mb-1">Payroll Management</h2>
                <!-- <div class="text-muted">Upload payroll files, review records by batch, and sort or search within a batch.</div> -->
            </div>
            <!-- <a href="#payroll-upload" class="btn btn-primary">Upload payroll</a> -->
        </div>

        <?php if ($notice !== null): ?>
            <div class="alert alert-<?= $escape($noticeType) ?>" role="alert"><?= $escape($notice) ?></div>
        <?php endif; ?>

        <section class="payroll-card" id="payroll-upload">
            <div class="payroll-card-head">Upload payroll file</div>
            <div class="payroll-card-body">
                <form action="payroll/upload.php" method="post" enctype="multipart/form-data" class="row g-3 align-items-end">
                    <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['payroll_csrf']) ?>">
                    <div class="col-md-8">
                        <label for="excel_file" class="form-label">Payroll Excel File (.xlsx or .xls)</label>
                        <input type="file" id="excel_file" name="excel_file" class="form-control" accept=".xlsx,.xls" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">Upload Payroll</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="payroll-card" id="records">
            <div class="payroll-card-head">Payroll batches <span class="text-muted fw-normal">(<?= count($batches) ?>)</span></div>
            <div class="payroll-card-body">
                <form method="get" action="payrollmng.php" class="payroll-date-filter mb-3">
                    <div class="payroll-filter">
                        <label for="dateFrom">Payroll from</label>
                        <input type="date" id="dateFrom" name="date_from" value="<?= $escape($dateFrom) ?>">
                    </div>
                    <div class="payroll-filter">
                        <label for="dateTo">Payroll to</label>
                        <input type="date" id="dateTo" name="date_to" value="<?= $escape($dateTo) ?>">
                    </div>
                    <button type="submit" class="btn btn-outline-primary">Filter batches</button>
                    <?php if ($dateFrom !== '' || $dateTo !== ''): ?>
                        <a class="btn btn-outline-secondary" href="payrollmng.php#records">Clear dates</a>
                    <?php endif; ?>
                </form>
                <?php if ($recordsError !== null): ?>
                    <div class="alert alert-danger mb-0"><?= $escape($recordsError) ?></div>
                <?php elseif (!$batches && ($dateFrom !== '' || $dateTo !== '')): ?>
                    <div class="payroll-empty">No payroll batches match this date range.</div>
                <?php elseif (!$batches): ?>
                    <div class="payroll-empty">No payroll batches have been uploaded.</div>
                <?php else: ?>
                    <div class="list-group payroll-batch-list mb-4">
                        <?php foreach ($batches as $batch):
                            $batchParams = [
                                'batch_id' => $batch['batch_id'],
                                'payroll_from' => $batch['payroll_from'] ?? '',
                                'payroll_to' => $batch['payroll_to'] ?? '',
                            ];
                            if ($dateFrom !== '') {
                                $batchParams['date_from'] = $dateFrom;
                            }
                            if ($dateTo !== '') {
                                $batchParams['date_to'] = $dateTo;
                            }
                            $batchUrl = 'payrollmng.php?' . http_build_query($batchParams) . '#batch-records';
                            $isSelected = $selectedBatch !== null
                                && (int) $selectedBatch['batch_id'] === (int) $batch['batch_id']
                                && (string) ($selectedBatch['payroll_from'] ?? '') === (string) ($batch['payroll_from'] ?? '')
                                && (string) ($selectedBatch['payroll_to'] ?? '') === (string) ($batch['payroll_to'] ?? '');
                            if ($isSelected) {
                                $closeParams = [];
                                if ($dateFrom !== '') {
                                    $closeParams['date_from'] = $dateFrom;
                                }
                                if ($dateTo !== '') {
                                    $closeParams['date_to'] = $dateTo;
                                }
                                $batchUrl = 'payrollmng.php' . ($closeParams ? '?' . http_build_query($closeParams) : '') . '#records';
                            }
                        ?>
                            <a class="list-group-item list-group-item-action d-flex flex-wrap justify-content-between align-items-center gap-2 <?= $isSelected ? 'active' : '' ?>"
                               href="<?= $escape($batchUrl) ?>">
                                <span>
                                    <strong>Batch #<?= $escape($batch['batch_id']) ?></strong>
                                    <span class="ms-2"><?= $escape($batch['payroll_from'] ?: 'No start date') ?> – <?= $escape($batch['payroll_to'] ?: 'No end date') ?></span>
                                </span>
                                <span class="small"><?= number_format((int) $batch['total_records']) ?> records<?= $batch['pay_date'] ? ' · Pay date ' . $escape($batch['pay_date']) : '' ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

            <?php if ($selectedBatch !== null): ?>
            <section class="payroll-card" id="batch-records">
                <div class="payroll-card-head d-flex justify-content-between align-items-center gap-2">
                    <span>
                        Batch #<?= $escape($selectedBatch['batch_id']) ?> records
                        <span class="text-muted fw-normal">(<?= number_format((int) $selectedBatch['total_records']) ?> total)</span>
                    </span>
                    <?php
                    $closeParams = [];
                    if ($dateFrom !== '') {
                        $closeParams['date_from'] = $dateFrom;
                    }
                    if ($dateTo !== '') {
                        $closeParams['date_to'] = $dateTo;
                    }
                    ?>
                    <a class="btn btn-sm btn-outline-secondary" href="payrollmng.php<?= $closeParams ? '?' . $escape(http_build_query($closeParams)) : '' ?>#records">Close records</a>
                </div>
                <div class="payroll-card-body">
                    <?php if ($recordsError !== null): ?>
                        <div class="alert alert-danger mb-0"><?= $escape($recordsError) ?></div>
                    <?php elseif (!$rows): ?>
                        <div class="payroll-empty">No records were found for this batch.</div>
                    <?php else: ?>
                        <div class="payroll-toolbar">
                            <div class="small text-muted">
                                Showing <?= number_format($offset + 1) ?>–<?= number_format($offset + count($rows)) ?>
                                of <?= number_format((int) $selectedBatch['total_records']) ?> records
                            </div>
                            <div class="payroll-filter">
                                <label for="payrollSearch">Search this page</label>
                                <input type="search" id="payrollSearch" placeholder="Employee, account, remarks…" autocomplete="off">
                            </div>
                        </div>
                        <div class="text-muted small mb-2" id="payrollCount" aria-live="polite"></div>
                        <div class="payroll-table-wrap">
                            <table class="payroll-table" id="payrollTable">
                                <thead><tr>
                                    <th><button type="button" data-sort="number">Employee # ↕</button></th>
                                    <th><button type="button" data-sort="text">Account Code ↕</button></th>
                                    <th><button type="button" data-sort="text">Account Type ↕</button></th>
                                    <th><button type="button" data-sort="number">Amount ↕</button></th>
                                    <th><button type="button" data-sort="date">Payroll From ↕</button></th>
                                    <th><button type="button" data-sort="date">Payroll To ↕</button></th>
                                    <th><button type="button" data-sort="date">Pay Date ↕</button></th>
                                    <th><button type="button" data-sort="text">Week ↕</button></th>
                                    <th><button type="button" data-sort="text">Remarks ↕</button></th>
                                </tr></thead>
                            <tbody>
                            <?php foreach ($rows as $row):
                                $searchText = strtolower(implode(' ', [
                                    $row['emp_id'], $row['account_code'], $row['account_type'], $row['remarks'], $row['week'],
                                ]));
                            ?>
                                <tr class="payroll-row" data-search="<?= $escape($searchText) ?>">
                                    <td data-sort="<?= $escape($row['emp_id']) ?>"><?= $escape($row['emp_id']) ?></td>
                                    <td data-sort="<?= $escape($row['account_code']) ?>"><?= $escape($row['account_code']) ?></td>
                                    <td data-sort="<?= $escape($row['account_type']) ?>"><?= $escape($row['account_type']) ?></td>
                                    <td data-sort="<?= $escape($row['amount']) ?>"><?= number_format((float) $row['amount'], 2) ?></td>
                                    <td data-sort="<?= $escape($row['payroll_from']) ?>"><?= $escape($row['payroll_from']) ?></td>
                                    <td data-sort="<?= $escape($row['payroll_to']) ?>"><?= $escape($row['payroll_to']) ?></td>
                                    <td data-sort="<?= $escape($row['pay_date']) ?>"><?= $escape($row['pay_date']) ?></td>
                                    <td data-sort="<?= $escape($row['week']) ?>"><?= $escape($row['week']) ?></td>
                                    <td data-sort="<?= $escape($row['remarks']) ?>"><?= $escape($row['remarks']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                                <tr id="payrollNoMatch" hidden><td colspan="9" class="payroll-empty">No records on this page match your search.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($totalPages > 1):
                        $pageQuery = [
                            'batch_id' => $selectedBatch['batch_id'],
                            'payroll_from' => $selectedBatch['payroll_from'] ?? '',
                            'payroll_to' => $selectedBatch['payroll_to'] ?? '',
                        ];
                    ?>
                        <nav class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3" aria-label="Payroll record pages">
                            <span class="small text-muted">Page <?= number_format($page) ?> of <?= number_format($totalPages) ?></span>
                            <div class="btn-group">
                                <?php if ($page > 1): ?>
                                    <?php $pageQuery['page'] = $page - 1; ?>
                                    <?php if ($dateFrom !== '') $pageQuery['date_from'] = $dateFrom; ?>
                                    <?php if ($dateTo !== '') $pageQuery['date_to'] = $dateTo; ?>
                                    <a class="btn btn-outline-primary btn-sm" href="payrollmng.php?<?= $escape(http_build_query($pageQuery)) ?>#batch-records">Previous</a>
                                <?php endif; ?>
                                <?php if ($page < $totalPages): ?>
                                    <?php $pageQuery['page'] = $page + 1; ?>
                                    <?php if ($dateFrom !== '') $pageQuery['date_from'] = $dateFrom; ?>
                                    <?php if ($dateTo !== '') $pageQuery['date_to'] = $dateTo; ?>
                                    <a class="btn btn-outline-primary btn-sm" href="payrollmng.php?<?= $escape(http_build_query($pageQuery)) ?>#batch-records">Next</a>
                                <?php endif; ?>
                            </div>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
</div>
<div class="content-backdrop fade"></div>
<div class="layout-overlay layout-menu-toggle"></div>

<script src="../assets/vendor/libs/jquery/jquery.js"></script>
<script src="../assets/vendor/libs/popper/popper.js"></script>
<script src="../assets/vendor/js/bootstrap.js"></script>
<script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="../assets/vendor/js/menu.js"></script>
<script src="../assets/js/main.js"></script>
<script>
(function () {
    var table = document.getElementById('payrollTable');
    if (!table) return;
    var body = table.tBodies[0];
    var rows = Array.prototype.slice.call(body.querySelectorAll('tr.payroll-row'));
    var search = document.getElementById('payrollSearch');
    var count = document.getElementById('payrollCount');
    var noMatch = document.getElementById('payrollNoMatch');

    function applyFilters() {
        var term = search.value.trim().toLowerCase();
        var visible = 0;
        rows.forEach(function (row) {
            var matches = !term || row.dataset.search.indexOf(term) !== -1;
            row.hidden = !matches;
            if (matches) visible++;
        });
        noMatch.hidden = visible !== 0;
        count.textContent = visible + ' of ' + rows.length + ' records on this page';
    }
    search.addEventListener('input', applyFilters);

    var sortDirections = {};
    Array.prototype.forEach.call(table.querySelectorAll('thead button[data-sort]'), function (button, column) {
        button.addEventListener('click', function () {
            sortDirections[column] = !sortDirections[column];
            var direction = sortDirections[column] ? 1 : -1;
            var type = button.dataset.sort;
            rows.sort(function (a, b) {
                var left = a.cells[column].dataset.sort || '';
                var right = b.cells[column].dataset.sort || '';
                if (type === 'number') return (parseFloat(left || '0') - parseFloat(right || '0')) * direction;
                if (type === 'date') return left.localeCompare(right) * direction;
                return left.localeCompare(right, undefined, {numeric: true, sensitivity: 'base'}) * direction;
            });
            rows.forEach(function (row) { body.insertBefore(row, noMatch); });
            applyFilters();
        });
    });
    applyFilters();
})();
</script>
</body>
</html>
