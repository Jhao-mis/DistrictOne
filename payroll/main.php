<?php
include 'login_verification.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Upload Payroll Excel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-light">

    <div class="container py-5">

        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-6">

                <div class="card shadow-sm border-0">

                    <div class="card-header bg-white border-0 text-center">
                        <i class="fa-solid fa-file-excel fa-2x text-success mb-2"></i>
                        <h5 class="mb-0">Upload Payroll File</h5>
                        <small class="text-muted">Excel-based payroll import</small>
                    </div>

                    <div class="card-body">

                        <form action="upload.php" method="post" enctype="multipart/form-data"
                            onsubmit="disableBtn(this)">

                            <div class="mb-3">
                                <label for="excel_file" class="form-label fw-semibold">
                                    Payroll Excel File
                                </label>

                                <input type="file" name="excel_file" class="form-control" id="excel_file"
                                    accept=".xlsx,.xls" required>

                                <div class="form-text">
                                    Accepted formats: <strong>.xlsx</strong>, <strong>.xls</strong>
                                </div>
                            </div>

                            <button type="submit" name="submit" class="btn btn-primary w-100">
                                Upload Payroll
                            </button>

                        </form>

                    </div>
                    <style>
                        .card-footer .btn {
                            transition: all 0.2s ease-in-out;
                        }

                        .card-footer .btn:hover {
                            transform: translateY(-1px);
                        }
                    </style>


                    <div class="card-footer bg-white border-0">
                        <div class="d-flex justify-content-center gap-2">

                            <a href="payrollData.php" class="btn btn-outline-success btn-sm px-3">
                                <i class="fa fa-table me-1"></i>
                                View Payroll Data
                            </a>

                            <a href="logout.php" class="btn btn-outline-success btn-sm px-3">
                                <i class="fa fa-sign-out-alt me-1"></i>
                                Logout
                            </a>

                        </div>
                    </div>


                </div>

                <div class="text-center mt-3">
                    <small class="text-muted">
                        Ensure the Excel file follows the required payroll template.
                    </small>
                </div>

            </div>
        </div>

    </div>

    <!-- SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- SweetAlert Trigger -->
    <?php if (isset($_GET['status'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {

                <?php if ($_GET['status'] === 'success'): ?>
                    Swal.fire({
                        icon: 'success',
                        title: 'Upload Successful',
                        text: 'Payroll data has been uploaded successfully.',
                        confirmButtonColor: '#3085d6'
                    }).then(() => {
                        window.history.replaceState({}, document.title, window.location.pathname);
                    });

                <?php elseif ($_GET['status'] === 'error'): ?>
                    Swal.fire({
                        icon: 'error',
                        title: 'Upload Failed',
                        text: <?= json_encode($_GET['msg'] ?? 'An error occurred during upload.'); ?>,
                        confirmButtonColor: '#d33'
                    }).then(() => {
                        window.history.replaceState({}, document.title, window.location.pathname);
                    });
                <?php endif; ?>

            });
        </script>
    <?php endif; ?>

    <!-- Disable Submit -->
    <script>
        function disableBtn(form) {
            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerText = 'Uploading...';
        }
    </script>

    <?php if (isset($_SESSION['success'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                Swal.fire({
                    icon: 'success',
                    title: 'Login Successful',
                    text: <?= json_encode($_SESSION['success']); ?>,
                    timer: 2000,
                    showConfirmButton: false
                });
            });
        </script>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>


</body>

</html>