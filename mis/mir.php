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
// DELETE IMAGE
// =======================
if (isset($_GET['delete_img'])) {

  $img_id = intval($_GET['delete_img']);
  $edit_id = intval($_GET['edit_id'] ?? 0);

  $res = $conn->query("SELECT image_path FROM mir_item_images WHERE id=$img_id");
  $img = $res->fetch_assoc();

  if ($img) {
    $file = "../uploads/mir/" . $img['image_path'];
    if (file_exists($file))
      unlink($file);

    $conn->query("DELETE FROM mir_item_images WHERE id=$img_id");
  }

  $_SESSION['success'] = "Image deleted!";
  header("Location: mir.php?edit_id=" . $edit_id);
  exit();
}


// =======================
// USERS
// =======================
$users = $conn->query("SELECT firstname, lastname FROM users");

$mis_users = $conn->query("
  SELECT u.firstname, u.lastname, p.position
  FROM users u
  LEFT JOIN personal_data_sheet p ON u.id = p.user_id
  WHERE u.role = 'mis'
");

// =======================
// FETCH USER
// =======================
$username = $_SESSION['username'];

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


// =======================
// EDIT MODE
// =======================
$edit_mode = false;
$edit_data = null;
$edit_items = [];

if (isset($_GET['edit_id'])) {
  $edit_mode = true;
  $id = intval($_GET['edit_id']);

  $edit_data = $conn->query("SELECT * FROM mir_reports WHERE id=$id")->fetch_assoc();

  $res = $conn->query("SELECT * FROM mir_items WHERE mir_id=$id");
  while ($row = $res->fetch_assoc()) {
    $edit_items[] = $row;
  }
}


// =======================
// AUTO NUMBER
// =======================
$res = $conn->query("SELECT MAX(id) as last_id FROM mir_reports");
$row = $res->fetch_assoc();

$base = 2615;
$year = date('Y');
$next = $base + ($row['last_id'] ?? 0) + 1;
$report_no_auto = "MIR-$year-" . str_pad($next, 4, '0', STR_PAD_LEFT);


// =======================
// FUNCTION: SAFE IMAGE UPLOAD
// =======================
function uploadImages($conn, $item_id, $i)
{

  if (!isset($_FILES['image']['name'][$i]))
    return;

  foreach ($_FILES['image']['name'][$i] as $k => $img) {

    if (empty($img))
      continue;

    $tmp = $_FILES['image']['tmp_name'][$i][$k];
    if (!is_uploaded_file($tmp))
      continue;

    $file = time() . '_' . basename($img);
    $target = "../uploads/mir/" . $file;

    if (move_uploaded_file($tmp, $target)) {
      $stmt = $conn->prepare("INSERT INTO mir_item_images (item_id, image_path) VALUES (?, ?)");
      $stmt->bind_param("is", $item_id, $file);
      $stmt->execute();
    }
  }
}


// =======================
// SAVE MIR
// =======================
if (isset($_POST['save_mir'])) {

  $conn->begin_transaction();

  $stmt = $conn->prepare("INSERT INTO mir_reports 
  (report_no,end_user,department,report_date,prepared_by,certified_by)
  VALUES (?,?,?,?,?,?)");

  $stmt->bind_param(
    "ssssss",
    $report_no_auto,
    $_POST['end_user'],
    $_POST['department'],
    $_POST['report_date'],
    $_POST['prepared_by'],
    $_POST['certified_by']
  );

  $stmt->execute();
  $mir_id = $stmt->insert_id;

  foreach ($_POST['asset_code'] as $i => $code) {

    if (!$code)
      continue;

    $stmt = $conn->prepare("INSERT INTO mir_items 
    (mir_id,asset_code,description,acquisition_date,remarks)
    VALUES (?,?,?,?,?)");

    $stmt->bind_param(
      "issss",
      $mir_id,
      $code,
      $_POST['description'][$i],
      $_POST['acquisition_date'][$i],
      $_POST['remarks'][$i]
    );

    $stmt->execute();
    $item_id = $stmt->insert_id;

    uploadImages($conn, $item_id, $i);
  }

  $conn->commit();

  $_SESSION['success'] = "Saved!";
  header("Location: mir.php");
  exit();
}


// =======================
// UPDATE MIR
// =======================
if (isset($_POST['update_mir'])) {

  $conn->begin_transaction();

  $mir_id = $_POST['mir_id'];

  $stmt = $conn->prepare("UPDATE mir_reports 
  SET end_user=?,department=?,report_date=?,prepared_by=?,certified_by=?
  WHERE id=?");

  $stmt->bind_param(
    "sssssi",
    $_POST['end_user'],
    $_POST['department'],
    $_POST['report_date'],
    $_POST['prepared_by'],
    $_POST['certified_by'],
    $mir_id
  );

  $stmt->execute();

  $item_ids = $_POST['item_id'] ?? [];

  foreach ($_POST['asset_code'] as $i => $code) {

    if (!$code)
      continue;

    $item_id = $item_ids[$i] ?? null;

    if (!empty($item_id) && is_numeric($item_id)) {

      // UPDATE
      $stmt = $conn->prepare("UPDATE mir_items 
      SET asset_code=?,description=?,acquisition_date=?,remarks=?
      WHERE id=?");

      $stmt->bind_param(
        "ssssi",
        $code,
        $_POST['description'][$i],
        $_POST['acquisition_date'][$i],
        $_POST['remarks'][$i],
        $item_id
      );

      $stmt->execute();

    } else {

      // INSERT NEW
      $stmt = $conn->prepare("INSERT INTO mir_items 
      (mir_id,asset_code,description,acquisition_date,remarks)
      VALUES (?,?,?,?,?)");

      $stmt->bind_param(
        "issss",
        $mir_id,
        $code,
        $_POST['description'][$i],
        $_POST['acquisition_date'][$i],
        $_POST['remarks'][$i]
      );

      $stmt->execute();
      $item_id = $stmt->insert_id;
    }

    uploadImages($conn, $item_id, $i);
  }

  $conn->commit();

  $_SESSION['success'] = "Updated!";
  header("Location: mir.php");
  exit();
}


// =======================
$mir_list = $conn->query("SELECT * FROM mir_reports ORDER BY id DESC");
?>



<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/"
  data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>MIR</title>

  <link rel="icon" type="image/png" href="../assets/img/favicon/districtone.png">
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css">
  <link rel="stylesheet" href="../assets/vendor/css/core.css">
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css">
  <link rel="stylesheet" href="../assets/css/demo.css">
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css">

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>
  <style>
    :root {
      --mir-primary: #007bff;
      --mir-border: #e4e6ef;
      --mir-muted: #6c757d;
    }

    .swal2-container {
      z-index: 99999 !important;
    }

    .swal2-popup {
      z-index: 100000 !important;
    }

    .mir-section {
      border: 1px solid var(--mir-border);
      border-radius: 12px;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
      background: #fff;
    }

    .mir-section-header {
      display: flex;
      align-items: center;
      gap: .6rem;
      margin-bottom: 1.25rem;
      padding-bottom: .75rem;
      border-bottom: 2px solid var(--mir-border);
      font-weight: 600;
      font-size: 1.05rem;
    }

    .mir-section-header i {
      color: var(--mir-primary);
      font-size: 1.15rem;
    }

    .mir-item-row {
      border: 1px solid var(--mir-border);
      border-radius: 10px;
      padding: 1rem;
      margin-bottom: 1rem;
      background: #fbfbfd;
    }

    .mir-item-row .col-form-label-sm {
      font-size: .78rem;
      text-transform: uppercase;
      letter-spacing: .03em;
      color: var(--mir-muted);
      margin-bottom: .25rem;
      display: block;
    }

    .mir-na-toggle {
      display: flex;
      align-items: center;
      gap: .4rem;
      white-space: nowrap;
      font-size: .78rem;
      color: var(--mir-muted);
      margin-top: .3rem;
    }

    .mir-thumbs {
      display: flex;
      flex-wrap: wrap;
      gap: .6rem;
      margin-top: .75rem;
    }

    .mir-thumb {
      position: relative;
      width: 80px;
      height: 80px;
    }

    .mir-thumb img {
      width: 80px;
      height: 80px;
      object-fit: cover;
      border-radius: 8px;
      border: 1px solid var(--mir-border);
      transition: transform .15s ease-in-out;
    }

    .mir-thumb:hover img {
      transform: scale(1.08);
    }

    .mir-thumb .mir-thumb-delete {
      position: absolute;
      top: -6px;
      right: -6px;
      background: #dc3545;
      color: #fff;
      border: 2px solid #fff;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      font-size: 11px;
      line-height: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
    }

    .mir-action-bar {
      display: flex;
      flex-wrap: wrap;
      gap: .6rem;
      border-top: 1px solid var(--mir-border);
      padding-top: 1.25rem;
      margin-top: .5rem;
    }

    .custom-outline-blue {
      border: 1.5px solid var(--mir-primary);
      color: var(--mir-primary);
      background: transparent;
      border-radius: 8px;
      padding: .45rem 1rem;
      font-weight: 500;
      font-size: .9rem;
      transition: all .15s ease-in-out;
    }

    .custom-outline-blue:hover {
      background: var(--mir-primary);
      color: #fff;
    }

    /* =======================
       MOBILE RESPONSIVE FIXES
       ======================= */
    @media (max-width: 767.98px) {
      .container-xxl {
        padding-left: .75rem;
        padding-right: .75rem;
      }

      .mir-section {
        padding: 1rem;
        border-radius: 10px;
        margin-bottom: 1rem;
      }

      .mir-section-header {
        font-size: .95rem;
        margin-bottom: 1rem;
      }

      .mir-item-row {
        padding: .75rem;
      }

      /* Stack action buttons full-width and in a sensible order on phones */
      .mir-action-bar {
        flex-direction: column;
      }

      .mir-action-bar .btn {
        width: 100%;
        margin: 0;
      }
    }

    @media (max-width: 575.98px) {
      .mir-thumb,
      .mir-thumb img {
        width: 64px;
        height: 64px;
      }

      h4.fw-bold {
        font-size: 1.15rem;
      }
    }

    /* Prevent inputs/selects from causing horizontal overflow on small screens */
    input.form-control,
    select.form-select {
      max-width: 100%;
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

  <div class="content-wrapper">
    <div class="container-xxl flex-grow-1 container-p-y">

      <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">Assets /</span>
        <?= $edit_mode ? 'Edit MIR' : 'Machine Inspection Report' ?>
      </h4>

      <form method="POST" enctype="multipart/form-data">

        <!-- Report Details -->
        <div class="mir-section">
          <div class="mir-section-header"><i class='bx bx-file'></i> Report Details</div>

          <div class="row">
            <div class="col-12 col-sm-6 col-md-3 mb-3">
              <label class="form-label">Report No</label>
              <input type="text" class="form-control"
                value="<?= htmlspecialchars($edit_mode ? $edit_data['report_no'] : $report_no_auto) ?>" readonly>
            </div>

            <div class="col-12 col-sm-6 col-md-3 mb-3">
              <label class="form-label">End User</label>
              <input list="userList" name="end_user" id="end_user" class="form-control"
                value="<?= htmlspecialchars($edit_mode ? $edit_data['end_user'] : '') ?>" required>

              <datalist id="userList">
                <?php while ($u = $users->fetch_assoc()): ?>
                  <option value="<?= htmlspecialchars($u['firstname'] . ' ' . $u['lastname']) ?>"></option>
                <?php endwhile; ?>
              </datalist>
            </div>

            <div class="col-12 col-sm-6 col-md-3 mb-3">
              <label class="form-label">Department</label>
              <select name="department" class="form-select" required>
                <option value="" disabled <?= !$edit_mode ? 'selected' : '' ?>>Select Department</option>
                <?php
                $departments = [
                  'Office of the General Manager',
                  'Management Information Services Section',
                  'Administrative Department',
                  'Finance Department',
                  'Commercial Department',
                  'Technical Services Department',
                  'Operations Department'
                ];

                foreach ($departments as $dept):
                  ?>
                  <option value="<?= htmlspecialchars($dept) ?>" <?= ($edit_mode && $edit_data['department'] == $dept) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($dept) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 col-sm-6 col-md-3 mb-3">
              <label class="form-label">Date</label>
              <input type="date" name="report_date" class="form-control"
                value="<?= htmlspecialchars($edit_mode ? $edit_data['report_date'] : '') ?>" required>
            </div>
          </div>
        </div>

        <!-- Inspection Items -->
        <div class="mir-section">
          <div class="mir-section-header"><i class='bx bx-clipboard'></i> Inspection Items</div>

          <div id="items">
            <?php if ($edit_mode && !empty($edit_items)): ?>
              <?php foreach ($edit_items as $i => $item): ?>
                <div class="mir-item-row">
                  <div class="row align-items-start">
                    <div class="col-12 col-sm-6 col-md-2 mb-2">
                      <span class="col-form-label-sm">Asset Code</span>
                      <input type="text" name="asset_code[]" class="form-control asset-code"
                        value="<?= htmlspecialchars($item['asset_code']) ?>">
                      <label class="mir-na-toggle"><input type="checkbox" class="na-asset"> N/A</label>
                      <input type="hidden" name="item_id[]" value="<?= (int) $item['id'] ?>">
                    </div>

                    <div class="col-12 col-sm-6 col-md-3 mb-2">
                      <span class="col-form-label-sm">Description</span>
                      <input type="text" name="description[]" class="form-control"
                        value="<?= htmlspecialchars($item['description']) ?>" placeholder="Description">
                    </div>

                    <div class="col-12 col-sm-6 col-md-2 mb-2">
                      <span class="col-form-label-sm">Acquisition Date</span>
                      <input type="date" name="acquisition_date[]" class="form-control acquisition-date"
                        value="<?= htmlspecialchars($item['acquisition_date']) ?>">
                      <label class="mir-na-toggle"><input type="checkbox" class="na-date"> N/A</label>
                    </div>

                    <div class="col-12 col-sm-6 col-md-3 mb-2">
                      <span class="col-form-label-sm">Remarks</span>
                      <input type="text" name="remarks[]" class="form-control"
                        value="<?= htmlspecialchars($item['remarks']) ?>" placeholder="Remarks">
                    </div>

                    <div class="col-12 col-sm-6 col-md-2 mb-2">
                      <span class="col-form-label-sm">Images</span>
                      <input type="file" name="image[<?= $i ?>][]" multiple class="form-control">
                    </div>
                  </div>

                  <?php
                  $imgs = $conn->query("SELECT * FROM mir_item_images WHERE item_id=" . (int) $item['id']);
                  if ($imgs->num_rows > 0):
                    ?>
                    <div class="mir-thumbs">
                      <?php while ($img = $imgs->fetch_assoc()): ?>
                        <div class="mir-thumb">
                          <img src="../uploads/mir/<?= htmlspecialchars($img['image_path']) ?>" alt="Item photo">
                          <button type="button" class="mir-thumb-delete"
                            onclick="deleteImage(<?= (int) $img['id'] ?>)" title="Remove">&times;</button>
                        </div>
                      <?php endwhile; ?>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>

            <?php else: ?>

              <div class="mir-item-row">
                <div class="row align-items-start">
                  <div class="col-12 col-sm-6 col-md-2 mb-2">
                    <span class="col-form-label-sm">Asset Code</span>
                    <input type="text" name="asset_code[]" class="form-control asset-code">
                    <label class="mir-na-toggle"><input type="checkbox" class="na-asset"> N/A</label>
                  </div>

                  <div class="col-12 col-sm-6 col-md-3 mb-2">
                    <span class="col-form-label-sm">Description</span>
                    <input type="text" name="description[]" class="form-control" placeholder="Description">
                  </div>

                  <div class="col-12 col-sm-6 col-md-2 mb-2">
                    <span class="col-form-label-sm">Acquisition Date</span>
                    <input type="date" name="acquisition_date[]" class="form-control acquisition-date">
                    <label class="mir-na-toggle"><input type="checkbox" class="na-date"> N/A</label>
                  </div>

                  <div class="col-12 col-sm-6 col-md-3 mb-2">
                    <span class="col-form-label-sm">Remarks</span>
                    <input type="text" name="remarks[]" class="form-control" placeholder="Remarks">
                  </div>

                  <div class="col-12 col-sm-6 col-md-2 mb-2">
                    <span class="col-form-label-sm">Images</span>
                    <input type="file" name="image[0][]" multiple class="form-control">
                  </div>
                </div>
              </div>

            <?php endif; ?>
          </div>

          <button type="button" class="custom-outline-blue mt-1" onclick="addItem()">
            <i class='bx bx-plus'></i> Add Row</button>
        </div>

        <!-- Sign-off -->
        <div class="mir-section">
          <div class="mir-section-header"><i class='bx bx-user-check'></i> Sign-off</div>

          <div class="row">
            <div class="col-12 col-sm-6 col-md-6 mb-3">
              <label class="form-label fw-semibold">Prepared By</label>
              <input list="misList" name="prepared_by" id="prepared_by" class="form-control"
                value="<?= htmlspecialchars($edit_mode ? $edit_data['prepared_by'] : $firstname . ' ' . $lastname) ?>">

              <datalist id="misList">
                <?php while ($m = $mis_users->fetch_assoc()): ?>
                  <option value="<?= htmlspecialchars($m['firstname'] . ' ' . $m['lastname']) ?>"
                    data-position="<?= htmlspecialchars($m['position'] ?? '') ?>"></option>
                <?php endwhile; ?>
              </datalist>
            </div>

            <div class="col-12 col-sm-6 col-md-6 mb-3">
              <label class="form-label fw-semibold">Position</label>
              <input type="text" id="prepared_position" class="form-control bg-light" readonly>
            </div>
          </div>

          <div class="row">
            <div class="col-12 col-sm-6 col-md-6 mb-3">
              <label class="form-label fw-semibold">Certified By</label>
              <input type="text" name="certified_by" class="form-control"
                value="<?= htmlspecialchars($edit_mode ? $edit_data['certified_by'] : 'Engr. Jonathan Dave Fajarda') ?>">
            </div>
          </div>

          <div class="mir-action-bar">
            <?php if ($edit_mode): ?>
              <input type="hidden" name="mir_id" value="<?= (int) $edit_data['id'] ?>">

              <button type="submit" name="update_mir" class="btn btn-warning">
                <i class="bx bx-save me-1"></i> Update MIR
              </button>

              <a href="mir_list.php" class="btn btn-primary">
                <i class="bx bx-list-ul me-1"></i> View List MIR
              </a>

              <a href="mir.php" class="btn btn-outline-secondary">
                <i class="bx bx-x me-1"></i> Cancel
              </a>

            <?php else: ?>

              <button type="submit" name="save_mir" class="btn btn-primary">
                <i class="bx bx-save me-1"></i> Save MIR
              </button>

              <a href="mir_list.php" class="btn btn-outline-secondary">
                <i class="bx bx-list-ul me-1"></i> View List MIR
              </a>

            <?php endif; ?>
          </div>
        </div>

      </form>

    </div>
  </div>

  <script src="../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../assets/vendor/libs/popper/popper.js"></script>
  <script src="../assets/vendor/js/bootstrap.js"></script>
  <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="../assets/vendor/js/menu.js"></script>
  <script src="../assets/js/main.js"></script>

  <script>
    // ---- Auto-fill Position from the "Prepared By" datalist ----
    document.addEventListener('DOMContentLoaded', function () {
      const input = document.getElementById('prepared_by');
      if (input && input.value) {
        input.dispatchEvent(new Event('input'));
      }
    });

    document.getElementById('prepared_by').addEventListener('input', function () {
      const value = this.value;
      const options = document.querySelectorAll('#misList option');
      const posField = document.getElementById('prepared_position');

      posField.value = '';
      options.forEach(option => {
        if (option.value === value) {
          posField.value = option.dataset.position;
        }
      });
    });

    // ---- Add inspection item row ----
    let itemIndex = <?= count($edit_items) > 0 ? count($edit_items) : 1 ?>;

    function addItem() {
      document.getElementById('items').insertAdjacentHTML('beforeend', `
        <div class="mir-item-row">
          <div class="row align-items-start">
            <div class="col-12 col-sm-6 col-md-2 mb-2">
              <span class="col-form-label-sm">Asset Code</span>
              <input type="text" name="asset_code[]" class="form-control asset-code">
              <label class="mir-na-toggle"><input type="checkbox" class="na-asset"> N/A</label>
              <input type="hidden" name="item_id[]" value="">
            </div>
            <div class="col-12 col-sm-6 col-md-3 mb-2">
              <span class="col-form-label-sm">Description</span>
              <input type="text" name="description[]" class="form-control" placeholder="Description">
            </div>
            <div class="col-12 col-sm-6 col-md-2 mb-2">
              <span class="col-form-label-sm">Acquisition Date</span>
              <input type="date" name="acquisition_date[]" class="form-control acquisition-date">
              <label class="mir-na-toggle"><input type="checkbox" class="na-date"> N/A</label>
            </div>
            <div class="col-12 col-sm-6 col-md-3 mb-2">
              <span class="col-form-label-sm">Remarks</span>
              <input type="text" name="remarks[]" class="form-control" placeholder="Remarks">
            </div>
            <div class="col-12 col-sm-6 col-md-2 mb-2">
              <span class="col-form-label-sm">Images</span>
              <input type="file" name="image[${itemIndex}][]" multiple class="form-control">
            </div>
          </div>
        </div>`);
      itemIndex++;
    }

    // ---- Delete an uploaded item image ----
    function deleteImage(id) {
      const editId = <?= json_encode($edit_data['id'] ?? '') ?>;
      Swal.fire({
        title: 'Delete this image?',
        icon: 'warning',
        showCancelButton: true
      }).then(res => {
        if (res.isConfirmed) {
          window.location = 'mir.php?edit_id=' + editId + '&delete_img=' + id;
        }
      });
    }

    // ---- N/A checkboxes for Asset Code / Acquisition Date ----
    document.addEventListener('change', function (e) {
      const row = e.target.closest('.mir-item-row');
      if (!row) return;

      if (e.target.classList.contains('na-asset')) {
        const assetInput = row.querySelector('.asset-code');
        if (e.target.checked) {
          assetInput.value = 'N/A';
          assetInput.setAttribute('readonly', true);
        } else {
          assetInput.value = '';
          assetInput.removeAttribute('readonly');
        }
      }

      if (e.target.classList.contains('na-date')) {
        const dateInput = row.querySelector('.acquisition-date');
        if (e.target.checked) {
          dateInput.type = 'text'; // switch to text so "N/A" is allowed
          dateInput.value = 'N/A';
          dateInput.setAttribute('readonly', true);
        } else {
          dateInput.type = 'date'; // switch back to date
          dateInput.value = '';
          dateInput.removeAttribute('readonly');
        }
      }
    });
  </script>

</body>

</html>