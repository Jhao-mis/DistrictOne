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

if (isset($_GET['batch_search'])) {
    header('Content-Type: application/json; charset=utf-8');

    $searchBatchId = filter_var($_GET['batch_id'] ?? null, FILTER_VALIDATE_INT);
    $searchFrom = ($_GET['payroll_from_null'] ?? '') === '1'
        ? null
        : (string) ($_GET['payroll_from'] ?? '');
    $searchTo = ($_GET['payroll_to_null'] ?? '') === '1'
        ? null
        : (string) ($_GET['payroll_to'] ?? '');
    $searchTerm = trim((string) ($_GET['q'] ?? ''));
    $searchPage = filter_var($_GET['search_page'] ?? 1, FILTER_VALIDATE_INT);
    $searchPage = $searchPage && $searchPage > 0 ? $searchPage : 1;
    $searchPageSize = 100;

    if (!$searchBatchId || $searchBatchId < 1 || $searchTerm === '' || strlen($searchTerm) > 200) {
        http_response_code(400);
        echo json_encode(['error' => 'Enter a search term of up to 200 characters.']);
        exit;
    }

    try {
        $searchPattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $searchTerm) . '%';
        $searchWhere = "
            FROM payroll
            WHERE batch_id = ? AND payroll_from <=> ? AND payroll_to <=> ?
              AND CONCAT_WS(' ', emp_id, account_code, account_type, amount, pay_date,
                            payroll_from, payroll_to, week, remarks) LIKE ? ESCAPE '!'
        ";

        $countStatement = $conn->prepare('SELECT COUNT(*) ' . $searchWhere);
        if (!$countStatement) {
            throw new RuntimeException($conn->error);
        }
        $countStatement->bind_param('isss', $searchBatchId, $searchFrom, $searchTo, $searchPattern);
        $countStatement->execute();
        $matchedRecords = (int) $countStatement->get_result()->fetch_row()[0];
        $countStatement->close();

        $searchTotalPages = max(1, (int) ceil($matchedRecords / $searchPageSize));
        $searchPage = min($searchPage, $searchTotalPages);
        $searchOffset = ($searchPage - 1) * $searchPageSize;
        $searchStatement = $conn->prepare('
            SELECT emp_id, account_code, account_type, amount, pay_date, week,
                   payroll_from, payroll_to, remarks
            ' . $searchWhere . '
            ORDER BY emp_id, account_code, id
            LIMIT ? OFFSET ?
        ');
        if (!$searchStatement) {
            throw new RuntimeException($conn->error);
        }
        $searchStatement->bind_param(
            'isssii',
            $searchBatchId,
            $searchFrom,
            $searchTo,
            $searchPattern,
            $searchPageSize,
            $searchOffset
        );
        $searchStatement->execute();
        $searchRows = $searchStatement->get_result()->fetch_all(MYSQLI_ASSOC);
        $searchStatement->close();

        echo json_encode([
            'rows' => $searchRows,
            'total' => $matchedRecords,
            'page' => $searchPage,
            'page_size' => $searchPageSize,
            'total_pages' => $searchTotalPages,
        ], JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        error_log('Payroll batch search failed: ' . $exception->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Payroll batch search failed. Please try again.']);
    }
    exit;
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
                                <label for="payrollSearch">Search entire batch</label>
                                <input type="search" id="payrollSearch"
                                       data-batch-id="<?= $escape($selectedBatch['batch_id']) ?>"
                                       data-payroll-from="<?= $escape($selectedBatch['payroll_from']) ?>"
                                       data-payroll-from-null="<?= $selectedBatch['payroll_from'] === null ? '1' : '0' ?>"
                                       data-payroll-to="<?= $escape($selectedBatch['payroll_to']) ?>"
                                       data-payroll-to-null="<?= $selectedBatch['payroll_to'] === null ? '1' : '0' ?>"
                                       placeholder="Employee, account, amount, dates, remarks…" autocomplete="off">
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
                                <tr id="payrollNoMatch" hidden><td colspan="9" class="payroll-empty">No records in this batch match your search.</td></tr>
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
                        <nav id="payrollPagination" class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3" aria-label="Payroll record pages">
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
    var originalRows = Array.prototype.slice.call(body.querySelectorAll('tr.payroll-row'));
    var rows = originalRows.slice();
    var search = document.getElementById('payrollSearch');
    var count = document.getElementById('payrollCount');
    var noMatch = document.getElementById('payrollNoMatch');
    var tableWrap = table.closest('.payroll-table-wrap');
    var pagination = document.getElementById('payrollPagination');
    var originalPagination = pagination ? pagination.innerHTML : '';
    var paginationCreated = false;
    var debounceTimer;
    var activeRequest;
    var activeTerm = '';
    var sortDirections = {};
    var columns = [
        ['Employee #', 'emp_id', 'number'],
        ['Account Code', 'account_code', 'text'],
        ['Account Type', 'account_type', 'text'],
        ['Amount', 'amount', 'number'],
        ['Payroll From', 'payroll_from', 'date'],
        ['Payroll To', 'payroll_to', 'date'],
        ['Pay Date', 'pay_date', 'date'],
        ['Week', 'week', 'text'],
        ['Remarks', 'remarks', 'text']
    ];

    function applyLocalFilter() {
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

    function ensurePagination() {
        if (pagination) return pagination;
        pagination = document.createElement('nav');
        pagination.id = 'payrollPagination';
        pagination.className = 'd-flex flex-wrap justify-content-between align-items-center gap-2 mt-3';
        pagination.setAttribute('aria-label', 'Payroll record pages');
        tableWrap.insertAdjacentElement('afterend', pagination);
        paginationCreated = true;
        return pagination;
    }

    function renderSearchPagination(result) {
        if (result.total_pages <= 1) {
            if (pagination) pagination.hidden = true;
            return;
        }

        var nav = ensurePagination();
        nav.hidden = false;
        nav.replaceChildren();

        var summary = document.createElement('span');
        summary.className = 'small text-muted';
        summary.textContent = 'Page ' + result.page + ' of ' + result.total_pages
            + ' · ' + result.total.toLocaleString() + ' matching records';
        var controls = document.createElement('div');
        controls.className = 'btn-group';

        [['Previous', result.page - 1], ['Next', result.page + 1]].forEach(function (item) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline-primary btn-sm';
            button.textContent = item[0];
            button.disabled = item[1] < 1 || item[1] > result.total_pages;
            button.addEventListener('click', function () {
                loadBatchSearch(activeTerm, item[1]);
            });
            controls.appendChild(button);
        });

        nav.append(summary, controls);
    }

    function renderSearchRows(result) {
        rows = result.rows.map(function (record) {
            var row = document.createElement('tr');
            row.className = 'payroll-row';
            row.dataset.search = [
                record.emp_id, record.account_code, record.account_type, record.amount,
                record.pay_date, record.payroll_from, record.payroll_to, record.week, record.remarks
            ].join(' ').toLowerCase();

            columns.forEach(function (column) {
                var value = record[column[1]] == null ? '' : String(record[column[1]]);
                var cell = document.createElement('td');
                cell.dataset.sort = value;
                if (column[1] === 'amount' && value !== '') {
                    cell.textContent = Number(value).toLocaleString(undefined, {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                } else {
                    cell.textContent = value;
                }
                row.appendChild(cell);
            });
            return row;
        });

        body.replaceChildren();
        rows.forEach(function (row) { body.appendChild(row); });
        body.appendChild(noMatch);
        noMatch.hidden = rows.length !== 0;
        count.textContent = result.total === 0
            ? 'No matching records in this batch.'
            : 'Showing ' + ((result.page - 1) * result.page_size + 1).toLocaleString()
                + '–' + Math.min(result.page * result.page_size, result.total).toLocaleString()
                + ' of ' + result.total.toLocaleString() + ' matching records in this batch.';
        renderSearchPagination(result);
        applySort();
    }

    function loadBatchSearch(term, page) {
        if (activeRequest) activeRequest.abort();
        activeRequest = new AbortController();
        activeTerm = term;
        count.textContent = 'Searching entire batch…';
        noMatch.hidden = true;

        var params = new URLSearchParams({
            batch_search: '1',
            batch_id: search.dataset.batchId,
            payroll_from: search.dataset.payrollFrom,
            payroll_from_null: search.dataset.payrollFromNull,
            payroll_to: search.dataset.payrollTo,
            payroll_to_null: search.dataset.payrollToNull,
            q: term,
            search_page: String(page)
        });

        fetch('payrollmng.php?' + params.toString(), { signal: activeRequest.signal })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) throw new Error(data.error || 'Payroll batch search failed.');
                    return data;
                });
            })
            .then(renderSearchRows)
            .catch(function (error) {
                if (error.name === 'AbortError') return;
                count.textContent = error.message || 'Payroll batch search failed. Please try again.';
                console.error('Payroll batch search failed:', error);
            });
    }

    function resetSearch() {
        if (activeRequest) activeRequest.abort();
        activeTerm = '';
        rows = originalRows.slice();
        rows.forEach(function (row) { body.insertBefore(row, noMatch); });
        noMatch.hidden = true;
        if (paginationCreated && pagination) {
            pagination.remove();
            pagination = null;
            paginationCreated = false;
        } else if (pagination) {
            pagination.innerHTML = originalPagination;
            pagination.hidden = false;
        }
        applyLocalFilter();
        applySort();
    }

    search.addEventListener('input', function () {
        window.clearTimeout(debounceTimer);
        if (activeRequest) activeRequest.abort();
        var term = search.value.trim();
        if (!term) {
            resetSearch();
            return;
        }
        debounceTimer = window.setTimeout(function () {
            loadBatchSearch(term, 1);
        }, 300);
    });

    function applySort() {
        rows.forEach(function (row) { body.insertBefore(row, noMatch); });
        if (!activeTerm) applyLocalFilter();
    }

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
            applySort();
        });
    });
    applyLocalFilter();
})();
</script>
</body>
</html>
