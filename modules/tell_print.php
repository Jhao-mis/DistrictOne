<?php
include '../db.php';
require 'login_verification.php';

/* ==============================
   🎯 CUSTOM ORDER (DEFINE FIRST)
============================== */
$customOrderNumbers = [
    4100,
    4101,
    4111,
    4112,
    4113,
    4114,
    4121,
    4131,
    4132,
    4133,
    1134,
    5100,
    2200,
    2201,
    2211,
    2212,
    2214,
    1213,
    2221,
    2222,
    2223,
    1224,
    2231,
    2232,
    2233,
    2234,
    2235,
    2236,
    2237,
    2300,
    2301,
    2311,
    2312,
    2313,
    1314,
    2315,
    2321,
    2322,
    2323,
    2324,
    3400,
    3401,
    3411,
    3412,
    3413,
    1421,
    1422,
    1423,
    1424,
    1425,
    1411,
    1412,
    4500,
    4501,
    4511,
    4512,
    4513,
    4521,
    4522,
    4523,
    4524,
    3600,
    3601,
    3611,
    3612,
    1400,
    1001,
    1002,
    1003,
    1006,
    3004,
    5005,
    2006,
    1007,
    1008,
    2001
];

/* ==============================
   📞 FETCH DATA
============================== */
$tel_query = $conn->query("
    SELECT *
    FROM telephone_directory
");

$telephone_records = $tel_query->fetch_all(MYSQLI_ASSOC);

/* ==============================
   🧠 SORT USING CUSTOM ORDER
============================== */
$orderMap = array_flip($customOrderNumbers);

usort($telephone_records, function ($a, $b) use ($orderMap) {

    $aLocal = (int) ($a['local_number'] ?? 0);
    $bLocal = (int) ($b['local_number'] ?? 0);

    $aPos = $orderMap[$aLocal] ?? PHP_INT_MAX;
    $bPos = $orderMap[$bLocal] ?? PHP_INT_MAX;

    return $aPos <=> $bPos;
});

foreach ($telephone_records as &$row) {
    if (empty($row['department']) || strtoupper($row['department']) === 'N/A') {
        $row['department'] = 'Facilities and Committees';
    }
}
unset($row);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Telephone Directory</title>
    <style>
        /* --- MANUAL ADJUSTMENT AREA --- */
        :root {
            --td-font-size: 13.4px;      /* Increase/Decrease to fit more/less */
            --td-padding: 2.7px 4px;    /* Increase/Decrease vertical space */
            --print-scale: 0.95;        /* 1.0 is original size, 0.95 shrinks it */
        }
        /* ------------------------------ */

        @page { size: A4; margin: 10mm; }

        body {
            font-family: 'Segoe UI', Calibri, sans-serif;
            margin: 0;
            padding: 0;
            color: #2c3e50;
        }

        #print-wrapper {
            width: 100%;
            transform-origin: top left;
        }

        .print-header-container {
            width: 100%;
            text-align: center;
            margin-bottom: 5px;
            break-after: avoid; 
        }
        
        .print-header img {
            max-width: 100%;
            max-height: 65px;
            height: auto;
        }

        .directory-container {
            column-count: 2;
            column-gap: 20px;
            column-rule: 1px solid #ddd;
            column-fill: balance;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            break-inside: avoid; 
        }
        
        th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #2c3e50;
            padding: 4px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
        }
        
        td {
            padding: var(--td-padding);
            border-bottom: 1px solid #eee;
            font-size: var(--td-font-size);
        }

        @media print {
            #print-wrapper {
                transform: scale(var(--print-scale));
            }
        }
    </style>
</head>
<body>

    <div id="print-wrapper">
        <div class="print-header-container">
            <div class="print-header">
                <img src="letter_head.png" alt="Company Header">
            </div>
        </div>

        <div class="directory-container">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Position</th>
                        <th>No.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($telephone_records as $row): ?>
                    <tr>
                        <td style="font-weight: 500;"><?= htmlspecialchars($row['name']); ?></td>
                        <td><?= htmlspecialchars($row['remarks']); ?></td>
                        <td style="font-weight: bold;"><?= htmlspecialchars($row['local_number']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        window.onload = function () {
            window.print();
            window.onafterprint = () => window.close();
        };
    </script>
</body>
</html>