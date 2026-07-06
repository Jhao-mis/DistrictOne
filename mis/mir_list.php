<?php
session_start();
require '../vendor/autoload.php';
require 'login_verification.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require '../db.php';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error)
  die("DB Error");

// =======================
// FETCH USER
// =======================
$username = $_SESSION['username'] ?? '';

$query = $conn->prepare("
  SELECT id, firstname, lastname, department 
  FROM users 
  WHERE username=?
");

$query->bind_param("s", $username);
$query->execute();
$query->bind_result($user_id, $firstname, $lastname, $department);
$query->fetch();
$query->close();

$mir_list = $conn->query("SELECT * FROM mir_reports ORDER BY id DESC");
$total_reports = $mir_list->num_rows;

?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/"
  data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>MIR List</title>

  <!-- Favicon -->
  <link rel="icon" type="image/png" href="../assets/img/favicon/districtone.png">

  <!-- Fonts & Icons -->
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css">

  <!-- Core CSS -->
  <link rel="stylesheet" href="../assets/vendor/css/core.css">
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css">
  <link rel="stylesheet" href="../assets/css/demo.css">
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css">

  <!-- SweetAlert -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <!-- Helpers -->
  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>

  <style>
    :root {
      --mir-primary: #007bff;
      --mir-border: #e4e6ef;
      --mir-muted: #6c757d;
      --mir-hover: #f7f9fc;
    }

    .swal2-container {
      z-index: 99999 !important;
    }

    .swal2-popup {
      z-index: 100000 !important;
    }

    /* =======================
       HEADER / TOOLBAR
       ======================= */
    .mir-card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .75rem;
      flex-wrap: wrap;
    }

    .mir-title-wrap {
      display: flex;
      align-items: center;
      gap: .6rem;
    }

    .mir-count-badge {
      font-size: .72rem;
      font-weight: 600;
      padding: .25rem .55rem;
      border-radius: 20px;
      background: #eef2ff;
      color: #4338ca;
      letter-spacing: .02em;
    }

    .mir-toolbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      flex-wrap: wrap;
      margin-bottom: 1rem;
    }

    .mir-search-wrap {
      position: relative;
      flex: 1 1 280px;
      max-width: 420px;
    }

    .mir-search-wrap i {
      position: absolute;
      left: .75rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--mir-muted);
      pointer-events: none;
    }

    .mir-search-wrap input {
      padding-left: 2.2rem;
      padding-right: 2.4rem;
    }

    .mir-search-wrap kbd {
      position: absolute;
      right: .5rem;
      top: 50%;
      transform: translateY(-50%);
      font-size: .68rem;
      padding: .1rem .35rem;
      border-radius: 4px;
      background: #f1f3f5;
      color: var(--mir-muted);
      border: 1px solid var(--mir-border);
      pointer-events: none;
    }

    .mir-search-wrap input:focus + kbd {
      display: none;
    }

    .mir-result-count {
      font-size: .8rem;
      color: var(--mir-muted);
      white-space: nowrap;
    }

    /* =======================
       TABLE
       ======================= */
    .mir-table-scroll {
      max-height: 65vh;
      overflow-y: auto;
      border: 1px solid var(--mir-border);
      border-radius: 8px;
    }

    .mir-table-scroll thead th {
      position: sticky;
      top: 0;
      z-index: 1;
      font-size: .74rem;
      text-transform: uppercase;
      letter-spacing: .03em;
      color: var(--mir-muted);
    }

    .mir-table-scroll table {
      margin-bottom: 0;
    }

    .mir-row {
      cursor: pointer;
      transition: background-color .12s ease;
    }

    .mir-row:hover {
      background: var(--mir-hover);
    }

    .mir-report-no {
      font-family: "SFMono-Regular", Consolas, monospace;
      font-size: .85rem;
      letter-spacing: .01em;
    }

    .mir-dept-pill {
      display: inline-block;
      font-size: .74rem;
      font-weight: 600;
      padding: .2rem .6rem;
      border-radius: 20px;
      white-space: nowrap;
    }

    .mir-actions {
      display: flex;
      gap: .35rem;
      flex-wrap: wrap;
    }

    .mir-actions .btn {
      min-width: 36px;
    }

    .mir-empty {
      text-align: center;
      padding: 2.5rem 1rem;
      color: var(--mir-muted);
    }

    .mir-empty i {
      font-size: 2rem;
      display: block;
      margin-bottom: .5rem;
    }

    .mir-empty .btn {
      margin-top: .5rem;
    }

    #mirContent .spinner-border {
      width: 2rem;
      height: 2rem;
    }

    /* =======================
       AVATAR (desktop: hidden, mobile: shown in card view)
       ======================= */
    .mir-avatar {
      display: none;
      align-items: center;
      justify-content: center;
      width: 28px;
      height: 28px;
      border-radius: 50%;
      font-size: .7rem;
      font-weight: 700;
      color: #fff;
      flex-shrink: 0;
    }

    .mir-action-label {
      display: none;
    }

    /* =======================
       FLOATING ACTION BUTTON (mobile only)
       ======================= */
    .mir-fab {
      display: none;
    }

    /* Focus visibility */
    .mir-actions .btn:focus-visible,
    #search:focus-visible {
      outline: 2px solid var(--mir-primary);
      outline-offset: 1px;
    }

    @media (prefers-reduced-motion: reduce) {
      .mir-row {
        transition: none;
      }
    }

    /* =======================
       MOBILE RESPONSIVE FIXES
       ======================= */
    @media (max-width: 767.98px) {
      .container-xxl {
        padding-left: .75rem;
        padding-right: .75rem;
      }

      .card-header.mir-card-header {
        flex-direction: column;
        align-items: stretch;
      }

      .card-header.mir-card-header .btn {
        width: 100%;
        justify-content: center;
      }

      .mir-toolbar {
        flex-direction: column;
        align-items: stretch;
      }

      .mir-search-wrap {
        flex: 0 0 auto;
        width: 100%;
        max-width: none;
      }

      .mir-search-wrap kbd {
        display: none;
      }

      .mir-result-count {
        text-align: right;
      }
    }

    /* Below md: convert the table into a stacked card list */
    @media (max-width: 767.98px) {
      .mir-table-scroll {
        max-height: none;
        border: none;
        overflow: visible;
      }

      .mir-table-scroll table,
      .mir-table-scroll thead,
      .mir-table-scroll tbody,
      .mir-table-scroll tr,
      .mir-table-scroll td {
        display: block;
        width: 100%;
      }

      .mir-table-scroll thead {
        display: none;
      }

      .mir-table-scroll tbody tr {
        border: 1px solid var(--mir-border);
        border-radius: 10px;
        margin-bottom: .75rem;
        padding: .75rem;
        background: #fff;
      }

      .mir-table-scroll tbody tr:hover {
        background: #fff;
      }

      .mir-table-scroll td {
        border: none !important;
        padding: .3rem 0 !important;
      }

      .mir-table-scroll td::before {
        content: attr(data-label);
        display: block;
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: var(--mir-muted);
        margin-bottom: .1rem;
      }

      .mir-table-scroll td.mir-actions-cell {
        padding-top: .5rem !important;
        border-top: 1px solid var(--mir-border) !important;
        margin-top: .4rem;
      }

      .mir-table-scroll td.mir-actions-cell::before {
        content: '';
        margin: 0;
      }

      .mir-actions .btn {
        flex: 1;
        min-height: 40px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .3rem;
        font-size: .78rem;
      }

      .mir-action-label {
        display: inline;
      }

      /* Card layout: header row (report no + date), body row (avatar + user + dept) */
      .mir-table-scroll tbody tr {
        display: flex;
        flex-direction: column;
        border-left-width: 4px;
        border-left-color: var(--dept-color, var(--mir-border));
        box-shadow: 0 1px 3px rgba(0, 0, 0, .04);
      }

      td[data-label="#"] {
        display: none;
      }

      td[data-label="Report No"] {
        display: flex;
        align-items: center;
        justify-content: space-between;
        order: 1;
      }

      td[data-label="Report No"]::after {
        content: attr(data-date);
        font-size: .72rem;
        font-weight: 400;
        color: var(--mir-muted);
      }

      td[data-label="End User"] {
        order: 2;
        display: flex;
        align-items: center;
        gap: .5rem;
        padding-top: .5rem !important;
      }

      td[data-label="Department"] {
        order: 3;
        padding-top: .35rem !important;
      }

      td[data-label="Date"] {
        display: none;
      }

      .mir-actions-cell {
        order: 4;
      }

      .mir-avatar {
        display: inline-flex;
      }
    }

    @media (max-width: 575.98px) {
      .modal-dialog {
        margin: .5rem;
      }

      .mir-fab {
        display: flex;
        position: fixed;
        bottom: 1.25rem;
        right: 1.25rem;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        box-shadow: 0 4px 14px rgba(0, 0, 0, .25);
        z-index: 1030;
      }

      .card-header.mir-card-header .mir-new-btn {
        display: none;
      }
    }
  </style>
</head>

<body>

  <?php
  // ALERTS
  if (isset($_SESSION['error'])) {
    echo "<script>document.addEventListener('DOMContentLoaded', () => Swal.fire('Error','" . addslashes($_SESSION['error']) . "','error'));</script>";
    unset($_SESSION['error']);
  }
  if (isset($_SESSION['success'])) {
    echo "<script>document.addEventListener('DOMContentLoaded', () => Swal.fire('Success','" . addslashes($_SESSION['success']) . "','success'));</script>";
    unset($_SESSION['success']);
  }
  ?>

  <?php
  switch ($_SESSION['role']) {
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
  }
  ?>

  <!-- CONTENT -->
  <div class="content-wrapper">
    <div class="container-xxl flex-grow-1 container-p-y">

      <div class="card">
        <div class="card-header mir-card-header">
          <div class="mir-title-wrap">
            <h5 class="mb-0">MIR List</h5>
            <span class="mir-count-badge"><?= (int) $total_reports ?> total</span>
          </div>

          <a href="mir.php" class="btn btn-primary btn-sm mir-new-btn">
            <i class="bx bx-plus"></i> New MIR
          </a>
        </div>

        <div class="card-body">

          <div class="mir-toolbar">
            <div class="mir-search-wrap">
              <i class='bx bx-search'></i>
              <input type="text" id="search" class="form-control" placeholder="Search by report no, end user, department...">
              <kbd>/</kbd>
            </div>
            <span class="mir-result-count" id="resultCount"></span>
          </div>

          <div class="mir-table-scroll">
            <table class="table table-hover table-bordered align-middle">
              <thead class="table-light">
                <tr>
                  <th>#</th>
                  <th>Report No</th>
                  <th>End User</th>
                  <th>Department</th>
                  <th>Date</th>
                  <th width="220">Actions</th>
                </tr>
              </thead>

              <tbody>
                <?php if ($mir_list->num_rows === 0): ?>
                  <tr>
                    <td colspan="6">
                      <div class="mir-empty">
                        <i class='bx bx-file-blank'></i>
                        No MIR reports yet.
                        <div>
                          <a href="mir.php" class="btn btn-primary btn-sm">
                            <i class="bx bx-plus"></i> Create your first MIR
                          </a>
                        </div>
                      </div>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php $i = 1;
                  while ($row = $mir_list->fetch_assoc()): ?>
                    <tr class="mir-row" onclick="viewMIR(<?= (int) $row['id'] ?>)">
                      <td data-label="#"><?= $i++ ?></td>
                      <td data-label="Report No" data-date="<?= htmlspecialchars($row['report_date']) ?>"><span class="mir-report-no"><?= htmlspecialchars($row['report_no']) ?></span></td>
                      <td data-label="End User">
                        <span class="mir-avatar" data-name="<?= htmlspecialchars($row['end_user']) ?>" aria-hidden="true"></span>
                        <?= htmlspecialchars($row['end_user']) ?>
                      </td>
                      <td data-label="Department">
                        <span class="mir-dept-pill" data-dept="<?= htmlspecialchars($row['department']) ?>">
                          <?= htmlspecialchars($row['department']) ?>
                        </span>
                      </td>
                      <td data-label="Date"><?= htmlspecialchars($row['report_date']) ?></td>

                      <td class="mir-actions-cell" onclick="event.stopPropagation()">
                        <div class="mir-actions">
                          <button class="btn btn-info btn-sm" onclick="viewMIR(<?= (int) $row['id'] ?>)" title="View" aria-label="View MIR <?= (int) $row['id'] ?>">
                            <i class="bx bx-show"></i><span class="mir-action-label">View</span>
                          </button>

                          <a href="mir_print.php?id=<?= (int) $row['id'] ?>" target="_blank" class="btn btn-primary btn-sm" title="Print" aria-label="Print MIR <?= (int) $row['id'] ?>">
                            <i class="bx bx-printer"></i><span class="mir-action-label">Print</span>
                          </a>

                          <a href="mir.php?edit_id=<?= (int) $row['id'] ?>" class="btn btn-warning btn-sm" title="Edit" aria-label="Edit MIR <?= (int) $row['id'] ?>">
                            <i class="bx bx-edit"></i><span class="mir-action-label">Edit</span>
                          </a>

                          <!-- <button onclick="deleteMIR(<?= (int) $row['id'] ?>)" class="btn btn-danger btn-sm" title="Delete">
                            <i class="bx bx-trash"></i>
                          </button> -->
                        </div>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php endif; ?>
              </tbody>

            </table>
          </div>

          <div id="noResults" class="mir-empty d-none">
            <i class='bx bx-search-alt'></i>
            No matching reports found.
          </div>

        </div>
      </div>

    </div>
  </div>

  <div class="layout-overlay layout-menu-toggle"></div>

  <!-- MOBILE FAB: New MIR -->
  <a href="mir.php" class="btn btn-primary mir-fab" aria-label="Create new MIR">
    <i class="bx bx-plus"></i>
  </a>

  <!-- MODAL -->
  <div class="modal fade" id="mirModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
      <div class="modal-content">

        <div class="modal-header">
          <h5 class="modal-title">MIR Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body" id="mirContent">
          <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status"></div>
            <div class="mt-2 text-muted">Loading...</div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>

      </div>
    </div>
  </div>

  <!-- CORE JS -->
  <script src="../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../assets/vendor/libs/popper/popper.js"></script>
  <script src="../assets/vendor/js/bootstrap.js"></script>
  <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="../assets/vendor/js/menu.js"></script>
  <script src="../assets/js/main.js"></script>

  <script>
    // =======================
    // DEPARTMENT PILL COLORS
    // (consistent color per department name, no server change needed)
    // =======================
    const DEPT_PALETTE = [
      { bg: '#eef2ff', fg: '#4338ca' }, // indigo
      { bg: '#ecfdf5', fg: '#047857' }, // green
      { bg: '#fff7ed', fg: '#c2410c' }, // orange
      { bg: '#fdf2f8', fg: '#be185d' }, // pink
      { bg: '#eff6ff', fg: '#1d4ed8' }, // blue
      { bg: '#f5f3ff', fg: '#6d28d9' }, // violet
      { bg: '#fefce8', fg: '#a16207' }, // amber
    ];

    function hashString(str) {
      let hash = 0;
      for (let i = 0; i < str.length; i++) {
        hash = (hash << 5) - hash + str.charCodeAt(i);
        hash |= 0;
      }
      return Math.abs(hash);
    }

    document.querySelectorAll('.mir-dept-pill').forEach(el => {
      const dept = el.dataset.dept || '';
      const color = DEPT_PALETTE[hashString(dept) % DEPT_PALETTE.length];
      el.style.backgroundColor = color.bg;
      el.style.color = color.fg;

      // Also tint the mobile card's left accent border to match the department
      const row = el.closest('tr.mir-row');
      if (row) row.style.setProperty('--dept-color', color.fg);
    });

    // =======================
    // AVATAR INITIALS (mobile card view)
    // =======================
    const AVATAR_BG = ['#6366f1', '#059669', '#ea580c', '#db2777', '#2563eb', '#7c3aed', '#ca8a04'];

    function initialsOf(name) {
      const parts = name.trim().split(/\s+/).filter(Boolean);
      if (parts.length === 0) return '?';
      if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
      return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    document.querySelectorAll('.mir-avatar').forEach(el => {
      const name = el.dataset.name || '';
      el.textContent = initialsOf(name);
      el.style.backgroundColor = AVATAR_BG[hashString(name) % AVATAR_BG.length];
    });

    // =======================
    // TOOLTIPS
    // =======================
    document.querySelectorAll('[title]').forEach(el => {
      new bootstrap.Tooltip(el);
    });

    // =======================
    // MODAL: VIEW MIR
    // =======================
    function viewMIR(id) {
      const content = document.getElementById('mirContent');
      content.innerHTML = `
        <div class="text-center py-4">
          <div class="spinner-border text-primary" role="status"></div>
          <div class="mt-2 text-muted">Loading...</div>
        </div>`;

      new bootstrap.Modal(document.getElementById('mirModal')).show();

      fetch('mir_view.php?id=' + id)
        .then(res => res.text())
        .then(data => {
          content.innerHTML = data;
        })
        .catch(() => {
          content.innerHTML = '<div class="text-danger text-center py-4">Failed to load MIR details.</div>';
        });
    }

    function deleteMIR(id) {
      Swal.fire({
        title: 'Delete this MIR?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33'
      }).then(res => {
        if (res.isConfirmed) {
          window.location = 'mir.php?delete_id=' + id;
        }
      });
    }

    // =======================
    // SEARCH (debounced) + result count
    // =======================
    const searchInput = document.getElementById('search');
    const resultCountEl = document.getElementById('resultCount');
    const allRows = document.querySelectorAll(".mir-table-scroll tbody tr.mir-row");
    const totalRows = allRows.length;

    function updateResultCount(visible) {
      if (!searchInput.value) {
        resultCountEl.textContent = totalRows ? `${totalRows} report${totalRows === 1 ? '' : 's'}` : '';
        return;
      }
      resultCountEl.textContent = `${visible} of ${totalRows} match${visible === 1 ? '' : 'es'}`;
    }

    function runSearch() {
      const v = searchInput.value.toLowerCase();
      let visibleCount = 0;

      allRows.forEach(r => {
        const match = r.innerText.toLowerCase().includes(v);
        r.style.display = match ? '' : 'none';
        if (match) visibleCount++;
      });

      document.getElementById('noResults').classList.toggle('d-none', visibleCount !== 0 || totalRows === 0);
      updateResultCount(visibleCount);
    }

    let searchDebounce;
    searchInput.addEventListener('keyup', function () {
      clearTimeout(searchDebounce);
      searchDebounce = setTimeout(runSearch, 150);
    });

    // "/" focuses search, unless already typing somewhere
    document.addEventListener('keydown', e => {
      if (e.key === '/' && document.activeElement !== searchInput &&
          !['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
        e.preventDefault();
        searchInput.focus();
      }
    });

    updateResultCount(totalRows);
  </script>

</body>

</html>