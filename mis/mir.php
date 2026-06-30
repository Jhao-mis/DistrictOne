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
    /* 🔥 SweetAlert overlay fix */
    .swal2-container {
      z-index: 99999 !important;
    }

    .swal2-popup {
      z-index: 100000 !important;
    }
  </style>
</head>

<body>

  <?php
  // ALERTS
  if (isset($_SESSION['error'])) {
    echo "<script>Swal.fire('Error','" . addslashes($_SESSION['error']) . "','error')</script>";
    unset($_SESSION['error']);
  }
  if (isset($_SESSION['success'])) {
    echo "<script>Swal.fire('Success','" . addslashes($_SESSION['success']) . "','success')</script>";
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



      <form method="POST" enctype="multipart/form-data">

        <div class="card mb-4">
          <h5 class="card-header">
            <?= $edit_mode ? 'Edit MIR' : 'Machine Inspection Report' ?>
          </h5>

          <div class="card-body">

            <div class="row">
              <div class="col-md-3 mb-3">
                <label>Report No</label>
                <input type="text" class="form-control"
                  value="<?= $edit_mode ? $edit_data['report_no'] : $report_no_auto ?>" readonly>
              </div>

              <div class="col-md-3 mb-3">
                <label>End User</label>
                <input list="userList" name="end_user" id="end_user" class="form-control"
                  value="<?= $edit_mode ? $edit_data['end_user'] : '' ?>" required>

                <datalist id="userList">
                  <?php while ($u = $users->fetch_assoc()): ?>
                    <option value="<?= $u['firstname'] . ' ' . $u['lastname'] ?>">
                    <?php endwhile; ?>
                </datalist>
              </div>


              <div class="col-md-3 mb-3">
                <label>Department</label>
                <select name="department" class="form-control" required>
                  <?php
                  $departments = [
                    'Office of the General Manager',
                    'Management Information Servidces Section',
                    'Administrative Department',
                    'Finance Department',
                    'Commercial Department',
                    'Technical Services Department',
                    'Operations Department'
                  ];

                  foreach ($departments as $dept):
                    ?>
                    <option value="<?= $dept ?>" <?= ($edit_mode && $edit_data['department'] == $dept) ? 'selected' : '' ?>>
                      <?= $dept ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-3 mb-3">
                <label>Date</label>
                <input type="date" name="report_date" class="form-control"
                  value="<?= $edit_mode ? $edit_data['report_date'] : '' ?>" required>
              </div>
            </div>

            <hr>
            <h6>Inspection Items</h6>

            <!-- HEADER LABELS -->
            <div class="row mb-2 fw-bold text-center">
              <div class="col-md-2">Asset Code</div>
              <div class="col-md-3">Description</div>
              <div class="col-md-2">Acquisition Date</div>
              <div class="col-md-3">Remarks</div>
              <div class="col-md-2">Images</div>
            </div>

            <div id="items">


              <?php if ($edit_mode && !empty($edit_items)): ?>
                <?php foreach ($edit_items as $i => $item): ?>
                  <div class="row mb-3 align-items-center">

                    <div class="col-md-2">
                      <div class="d-flex align-items-center gap-1">
                        <input type="checkbox" class="na-asset">
                        <input type="text" name="asset_code[]" class="form-control asset-code"
                          value="<?= $item['asset_code'] ?>">
                      </div>
                      <input type="hidden" name="item_id[]" value="<?= $item['id'] ?>">
                    </div>

                    <div class="col-md-3">
                      <input type="text" name="description[]" class="form-control" value="<?= $item['description'] ?>"
                        placeholder="Description">
                    </div>

                    <div class="col-md-2">
                      <div class="d-flex align-items-center gap-1">
                        <input type="checkbox" class="na-date">
                        <input type="date" name="acquisition_date[]" class="form-control acquisition-date"
                          value="<?= $item['acquisition_date'] ?>">
                      </div>
                    </div>

                    <div class="col-md-3">
                      <input type="text" name="remarks[]" class="form-control" value="<?= $item['remarks'] ?>"
                        placeholder="Remarks">
                    </div>

                    <div class="col-md-2">
                      <input type="file" name="image[<?= $i ?>][]" multiple class="form-control">
                    </div>

                  </div>
                  <div class="mt-2 d-flex flex-wrap gap-2">
                    <style>
                      .img-thumb:hover {
                        transform: scale(1.1);
                        transition: 0.2s;
                      }
                    </style>

                    <?php
                    $imgs = $conn->query("SELECT * FROM mir_item_images WHERE item_id=" . $item['id']);
                    while ($img = $imgs->fetch_assoc()):
                      ?>

                      <div style="position:relative;">
                        <img src="../uploads/mir/<?= $img['image_path'] ?>"
                          style="width:80px;height:80px;object-fit:cover;border-radius:5px;">

                        <button type="button" onclick="deleteImage(<?= $img['id'] ?>)" style="
        position:absolute;
        top:-5px;
        right:-5px;
        background:red;
        color:white;
        border:none;
        border-radius:50%;
        width:20px;
        height:20px;
        font-size:12px;
      ">
                          ×
                        </button>
                      </div>

                    <?php endwhile; ?>

                  </div>

                  <script>
                    function deleteImage(id) {
                      Swal.fire({
                        title: 'Delete this image?',
                        icon: 'warning',
                        showCancelButton: true
                      }).then(res => {
                        if (res.isConfirmed) {
                          window.location = 'mir.php?edit_id=<?= $edit_data['id'] ?? '' ?>&delete_img=' + id;
                        }
                      });
                    }
                  </script>

                  <br><br>
                <?php endforeach; ?>

              <?php else: ?>

                <div class="row mb-3 align-items-center">

                  <div class="col-md-2">
                    <div class="d-flex align-items-center gap-1">
                      <input type="checkbox" class="na-asset">
                      <input type="text" name="asset_code[]" class="form-control asset-code">
                    </div>
                  </div>

                  <div class="col-md-3">
                    <input type="text" name="description[]" class="form-control" placeholder="Description">
                  </div>

                  <div class="col-md-2">
                    <div class="d-flex align-items-center gap-1">
                      <input type="checkbox" class="na-date">
                      <input type="date" name="acquisition_date[]" class="form-control acquisition-date">
                    </div>
                  </div>

                  <div class="col-md-3">
                    <input type="text" name="remarks[]" class="form-control" placeholder="Remarks">
                  </div>

                  <div class="col-md-2">
                    <input type="file" name="image[0][]" multiple class="form-control">
                  </div>

                </div>

              <?php endif; ?>

            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addItem()">+ Add Row</button>

            <hr>

            <div class="row">

              <!-- PREPARED BY -->
              <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Prepared By</label>
                <input list="misList" name="prepared_by" id="prepared_by" class="form-control"
                  value="<?= $edit_mode ? $edit_data['prepared_by'] : $firstname . ' ' . $lastname ?>">

                <datalist id="misList">
                  <?php while ($m = $mis_users->fetch_assoc()): ?>
                    <option value="<?= $m['firstname'] . ' ' . $m['lastname'] ?>"
                      data-position="<?= htmlspecialchars($m['position']) ?>">
                    <?php endwhile; ?>
                </datalist>
              </div>

              <!-- POSITION -->
              <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Position</label>
                <input type="text" id="prepared_position" class="form-control bg-light" readonly>
              </div>

            </div>

            <div class="row">

              <!-- CERTIFIED BY -->
              <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Certified By</label>
                <input type="text" name="certified_by" class="form-control"
                  value="<?= $edit_mode ? $edit_data['certified_by'] : 'Engr. Jonathan Dave Fajarda' ?>">
              </div>

            </div>

            <div class="d-flex align-items-center gap-2">

              <?php if ($edit_mode): ?>
                <input type="hidden" name="mir_id" value="<?= $edit_data['id'] ?>">

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

                <a href="mir_list.php" class="btn btn-primary">
                  <i class="bx bx-list-ul me-1"></i> View List MIR
                </a>

              <?php endif; ?>

            </div>

      </form>


      <script src="../assets/vendor/libs/jquery/jquery.js"></script>
      <script src="../assets/vendor/js/bootstrap.js"></script>

      <!-- VIEW MODAL -->
      <div class="modal fade" id="mirModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
          <div class="modal-content">

            <div class="modal-header">
              <h5 class="modal-title">MIR Details</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body" id="mirContent">
              Loading...
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                Close
              </button>
            </div>

          </div>
        </div>
      </div>

      <script>
        window.addEventListener('DOMContentLoaded', function () {
          let input = document.getElementById('prepared_by');
          if (input && input.value) {
            input.dispatchEvent(new Event('input'));
          }
        });
      </script>
      <script>
        document.getElementById('prepared_by').addEventListener('input', function () {
          let input = this.value;
          let options = document.querySelectorAll('#misList option');
          let posField = document.getElementById('prepared_position');

          posField.value = '';

          options.forEach(option => {
            if (option.value === input) {
              posField.value = option.dataset.position;
            }
          });
        });
      </script>

      <script>
        let index = 1;

        function addItem() {
          document.getElementById('items').insertAdjacentHTML('beforeend', `
<div class="row mb-3">

<div class="col-md-2">
  <div class="d-flex align-items-center gap-1">
    <input type="checkbox" class="na-asset">
    <input type="text" name="asset_code[]" class="form-control asset-code">
    <input type="hidden" name="item_id[]" value="">
  </div>
</div>

<div class="col-md-3">
  <input type="text" name="description[]" class="form-control">
</div>

<div class="col-md-2">
  <div class="d-flex align-items-center gap-1">
    <input type="checkbox" class="na-date">
    <input type="date" name="acquisition_date[]" class="form-control acquisition-date">
  </div>
</div>

<div class="col-md-3">
  <input type="text" name="remarks[]" class="form-control">
</div>

<div class="col-md-2">
  <input type="file" name="image[${index}][]" multiple class="form-control">
</div>

</div>`);
          index++;
        }

        function deleteMIR(id) {
          Swal.fire({
            title: 'Delete?',
            icon: 'warning',
            showCancelButton: true
          }).then(res => {
            if (res.isConfirmed) location = 'mir.php?delete_id=' + id;
          });
        }


        function viewMIR(id) {
          fetch('mir_view.php?id=' + id)
            .then(res => res.text())
            .then(data => {
              document.getElementById('mirContent').innerHTML = data;

              let modal = new bootstrap.Modal(document.getElementById('mirModal'));
              modal.show();
            });
        }


        document.getElementById('search').addEventListener('keyup', function () {
          let v = this.value.toLowerCase();
          document.querySelectorAll("tbody tr").forEach(r => {
            r.style.display = r.innerText.toLowerCase().includes(v) ? '' : 'none';
          });
        });
      </script>
      <script>
        document.addEventListener('change', function (e) {

          let row = e.target.closest('.row');
          if (!row) return;

          // ✅ Asset Code checkbox
          if (e.target.classList.contains('na-asset')) {

            let assetInput = row.querySelector('.asset-code');

            if (e.target.checked) {
              assetInput.value = 'N/A';
              assetInput.setAttribute('readonly', true);
            } else {
              assetInput.value = '';
              assetInput.removeAttribute('readonly');
            }
          }

          if (e.target.classList.contains('na-date')) {

            let dateInput = row.querySelector('.acquisition-date');

            if (e.target.checked) {
              dateInput.type = 'text';        // 🔥 switch to text so "N/A" is allowed
              dateInput.value = 'N/A';
              dateInput.setAttribute('readonly', true);
            } else {
              dateInput.type = 'date';        // 🔥 switch back to date
              dateInput.value = '';
              dateInput.removeAttribute('readonly');
            }
          }

        });
      </script>

</body>

</html>