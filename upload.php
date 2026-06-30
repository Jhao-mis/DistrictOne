<?php
session_start();

/* ============================
   DATABASE + LIBRARY
============================ */
require 'db.php';               // adjust if needed
require 'vendor/autoload.php';  // PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;



/* ============================
   INITIAL VALUES
============================ */
$updated = 0;
$skipped = 0;
$message = '';

/* ============================
   HANDLE UPLOAD
============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_FILES['excel']) || $_FILES['excel']['error'] !== UPLOAD_ERR_OK) {
        $message = "No file uploaded or upload error.";
    } else {

        $filename = $_FILES['excel']['name'];
        $tmpPath  = $_FILES['excel']['tmp_name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($ext, ['xls', 'xlsx'])) {
            $message = "Invalid file type. Upload XLS or XLSX only.";
        } else {

            /* ============================
               LOAD EXCEL
            ============================ */
            $spreadsheet = IOFactory::load($tmpPath);
            $rows = $spreadsheet->getActiveSheet()->toArray();

            foreach ($rows as $index => $row) {

                // Skip header row
                if ($index === 0) continue;

                $emp_id    = trim($row[0] ?? '');
                $firstname = trim($row[1] ?? '');
                $lastname  = trim($row[2] ?? '');

                if ($emp_id === '' || $firstname === '' || $lastname === '') {
                    $skipped++;
                    continue;
                }

                /* ============================
                   RELAXED MATCHING UPDATE
                   lastname + firstname only
                ============================ */
                $sql = "
                    UPDATE users
                    SET emp_id = ?
                    WHERE (emp_id IS NULL OR emp_id = 0)
                      AND LOWER(TRIM(firstname)) = LOWER(TRIM(?))
                      AND LOWER(REPLACE(TRIM(lastname), ' ', ''))
                          = LOWER(REPLACE(TRIM(?), ' ', ''))
                    LIMIT 1
                ";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iss", $emp_id, $firstname, $lastname);
                $stmt->execute();

                if ($stmt->affected_rows > 0) {
                    $updated++;
                } else {
                    $skipped++;
                }

                $stmt->close();
            }

            $message = "Upload completed successfully.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload EMP ID</title>
    <style>
        body { font-family: Arial; padding: 30px; }
        .box { max-width: 520px; margin: auto; }
        .result { margin-top: 20px; padding: 15px; background: #f2f2f2; }
    </style>
</head>
<body>

<div class="box">
    <h2>Upload EMP ID (Lastname + Firstname Matching)</h2>

    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="excel" accept=".xls,.xlsx" required>
        <br><br>
        <button type="submit">Upload & Assign</button>
    </form>

    <?php if ($message): ?>
        <div class="result">
            <p><strong><?= htmlspecialchars($message) ?></strong></p>
            <p>EMP ID Assigned: <b><?= $updated ?></b></p>
            <p>Skipped: <b><?= $skipped ?></b></p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
