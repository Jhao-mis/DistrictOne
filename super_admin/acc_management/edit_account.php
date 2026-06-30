<?php
include '../../db.php'; // database connection
include '../login_verification.php'; // session check

// HANDLE FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id         = (int) $_POST['id'];
    $emp_id     = mysqli_real_escape_string($conn, $_POST['emp_id']);
    $firstname  = mysqli_real_escape_string($conn, $_POST['firstname']);
    $middlename = mysqli_real_escape_string($conn, $_POST['middlename']);
    $lastname   = mysqli_real_escape_string($conn, $_POST['lastname']);
    $username   = mysqli_real_escape_string($conn, $_POST['username']);
    $email      = mysqli_real_escape_string($conn, $_POST['email']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $role       = mysqli_real_escape_string($conn, $_POST['role']);

    // CHECK FOR DUPLICATE EMP NO.
    $dup_check = mysqli_query($conn, "SELECT id FROM users WHERE emp_id = '$emp_id' AND id != '$id'");
    if (mysqli_num_rows($dup_check) > 0) {
        header("Location: edit_account.php?id=$id&duplicate_emp=1");
        exit;
    }

    // UPDATE USER
    $update = "
        UPDATE users SET
            emp_id = '$emp_id',
            firstname = '$firstname',
            middlename = '$middlename',
            lastname = '$lastname',
            username = '$username',
            email = '$email',
            department = '$department',
            role = '$role'
        WHERE id = '$id'
    ";

    if (mysqli_query($conn, $update)) {
        header("Location: edit_account.php?id=$id&updated=1");
        exit;
    } else {
        die('Update failed: ' . mysqli_error($conn));
    }
}

// FETCH USER (GET)
if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $query = "SELECT * FROM users WHERE id = $id";
    $result = mysqli_query($conn, $query);
    $user = mysqli_fetch_assoc($result);
}

// Department options
$departments = [
    'Office of the General Manager',
    'Management Information Services Section',
    'Administrative Department',
    'Finance Department',
    'Commercial Department',
    'Technical Services Department',
    'Operations Department'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Account</title>

  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Boxicons -->
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="bg-light">

<div class="container my-5">
  <div class="row justify-content-center">
    <div class="col-lg-9 col-xl-8">

      <div class="card shadow border-0 rounded-4">
        <div class="card-header bg-primary text-white rounded-top-4 py-3">
          <h4 class="mb-0 text-center">
            <i class="bx bx-user-circle me-2"></i>Edit Account
          </h4>
        </div>

        <div class="card-body p-4">
          <form method="POST">
            <input type="hidden" name="id" value="<?= $user['id'] ?>">

            <!-- Personal Info -->
            <h6 class="text-muted mb-3">
              <i class="bx bx-id-card me-1"></i>Personal Information
            </h6>

            <div class="row g-3 mb-4">
              <div class="col-md-4">
                <label class="form-label">First Name</label>
                <input type="text" name="firstname" class="form-control" value="<?= $user['firstname'] ?>" required>
              </div>

              <div class="col-md-4">
                <label class="form-label">Middle Name</label>
                <input type="text" name="middlename" class="form-control" value="<?= $user['middlename'] ?>">
              </div>

              <div class="col-md-4">
                <label class="form-label">Last Name</label>
                <input type="text" name="lastname" class="form-control" value="<?= $user['lastname'] ?>" required>
              </div>
            </div>

            <hr class="my-4">

            <!-- Account Info -->
            <h6 class="text-muted mb-3">
              <i class="bx bx-lock-alt me-1"></i>Account Information
            </h6>

            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control bg-light" value="<?= $user['username'] ?>" readonly>
              </div>

              <div class="col-md-6">
                <label class="form-label">Employee No.</label>
                <input type="text" name="emp_id" class="form-control" value="<?= htmlspecialchars($user['emp_id']) ?>" required>
              </div>
            </div>

            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control bg-light" value="<?= $user['email'] ?>" readonly>
              </div>

              <div class="col-md-6">
                <label class="form-label">Department</label>
                <select name="department" class="form-select" required>
                  <?php foreach ($departments as $dept): ?>
                    <option value="<?= $dept ?>" <?= $user['department'] == $dept ? 'selected' : '' ?>>
                      <?= $dept ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="mb-4">
              <label class="form-label">Role</label>
              <select name="role" class="form-select" required>
                <option value="User" <?= $user['role'] == 'User' ? 'selected' : '' ?>>User</option>
                <option value="mis" <?= $user['role'] == 'mis' ? 'selected' : '' ?>>MIS</option>
                <option value="Admin" <?= $user['role'] == 'Admin' ? 'selected' : '' ?>>Admin</option>
                <option value="Super Admin" <?= $user['role'] == 'Super Admin' ? 'selected' : '' ?>>Super Admin</option>
              </select>
            </div>

            <!-- Actions -->
            <div class="d-flex justify-content-between mt-4">
              <a href="../accountManagement.php" class="btn btn-outline-secondary">
                <i class="bx bx-arrow-back me-1"></i>Back
              </a>
              <button type="submit" class="btn btn-primary px-4">
                <i class="bx bx-save me-1"></i>Update Account
              </button>
            </div>

          </form>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Alerts remain the same -->


<!-- Success Alert -->
<?php if (isset($_GET['updated']) && $_GET['updated'] == 1): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Account Updated',
    text: 'The account information has been successfully updated.',
    confirmButtonColor: '#3085d6'
});
</script>
<?php endif; ?>

<!-- Duplicate Emp No. Alert -->
<?php if (isset($_GET['duplicate_emp']) && $_GET['duplicate_emp'] == 1): ?>
<script>
Swal.fire({
    icon: 'error',
    title: 'Duplicate Emp No.',
    text: 'The Employee Number you entered already exists.',
    confirmButtonColor: '#d33'
});
</script>
<?php endif; ?>

<!-- Bootstrap 5 JS (optional for interactivity) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
