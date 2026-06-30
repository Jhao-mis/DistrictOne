<?php
require '../db.php';

$conn = new mysqli($host, $user, $pass, $db);

$id = intval($_GET['id'] ?? 0);

$mir = $conn->query("SELECT * FROM mir_reports WHERE id=$id")->fetch_assoc();


// =======================
// 🔥 FETCH PREPARED BY POSITION
// =======================
$prepared_name = $mir['prepared_by'] ?? '';
$prepared_position = ''; // ✅ ALWAYS initialize

if (!empty($prepared_name)) {

    $stmt = $conn->prepare("
        SELECT p.position
        FROM users u
        LEFT JOIN personal_data_sheet p 
            ON u.id = p.user_id
        WHERE CONCAT(u.firstname, ' ', u.lastname) LIKE ?
        LIMIT 1
    ");

    $search = "%$prepared_name%";

    $stmt->bind_param("s", $search);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $prepared_position = $row['position'] ?? '';
    }

    $stmt->close();
}

// =======================
// 🔥 JOIN QUERY (ITEMS + IMAGES)
// =======================
$query = "
SELECT 
    i.*, 
    img.image_path
FROM mir_items i
LEFT JOIN mir_item_images img 
    ON i.id = img.item_id
WHERE i.mir_id = $id
ORDER BY i.id ASC
";

$result = $conn->query($query);

if (!$result) {
    die("Query failed: " . $conn->error);
}


// =======================
// 🔥 GROUPING ITEMS
// =======================
$items = [];

while ($row = $result->fetch_assoc()) {

    $itemId = $row['id'];

    if (!isset($items[$itemId])) {
        $items[$itemId] = [
            'id' => $row['id'],
            'asset_code' => $row['asset_code'],
            'description' => $row['description'],
            'acquisition_date' => $row['acquisition_date'],
            'remarks' => $row['remarks'],
            'images' => []
        ];
    }

    if (!empty($row['image_path'])) {
        $items[$itemId]['images'][] = $row['image_path'];
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>MIR Print</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }

        .container {
            width: 190mm;
            /* ✅ fit inside A4 */
            margin: auto;
        }

        /* HEADER IMAGE */
        .header {
            text-align: center;
            margin-bottom: 10px;
            /* spacing below header */
        }

        .header img {
            width: 70%;
            display: block;
            margin: 0 auto;
            /* ✅ perfectly centered */
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            /* ✅ prevents overflow */
        }

        td,
        th {
            border: 1px solid #000;
            padding: 6px;
            vertical-align: middle;
            word-wrap: break-word;
            /* ✅ prevents text overflow */
        }

        .no-border td {
            border: none;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .title {
            text-align: center;
            font-weight: bold;
            padding: 8px;
        }

        .img-box img {
            width: 90px;
            height: 70px;
            object-fit: cover;
        }

        .sign {
            height: 60px;
            vertical-align: bottom;
        }

        .page-break {
            page-break-before: always;
        }

        /* 🔥 A4 PORTRAIT SETUP */
        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        @media print {
            body {
                margin: 0;
            }

            .container {
                width: 190mm;
            }
        }
    </style>

</head>

<body onload="window.print()">

    <style>
        .copy {
            transform: scale(0.9);
            transform-origin: top center;
            width: 111%;
            /* compensate scale (100 / 0.9 ≈ 111%) */
            margin-left: -5.5%;
            margin-bottom: 5mm;
        }

        .page-break {
            page-break-before: always;
        }

        .cut-line {
            border-top: 2px dashed #000;
            margin: 5mm 0;
        }

        /* ensure it fits A4 */
        @media print {
            body {
                margin: 0;
            }
        }
    </style>

    <?php
    function renderMIR($mir, $items, $conn, $prepared_position)
    {
        ?>

        <div class="container copy">

            <!-- HEADER -->
            <div class="header">
                <img src="head.png">
            </div>

            <table>

                <tr>
                    <td class="bold"># <?= $mir['report_no'] ?></td>
                    <td colspan="4" class="right bold">
                        Date: <?= date("F d, Y", strtotime($mir['report_date'])) ?>
                    </td>
                </tr>

                <tr>
                    <td colspan="5" class="bold">
                        End User: <?= strtoupper($mir['end_user']) ?>
                    </td>
                </tr>

                <tr>
                    <td colspan="5" class="bold">
                        Department: <?= $mir['department'] ?>
                    </td>
                </tr>

                <tr>
                    <td colspan="5" class="title">
                        MACHINE INSPECTION REPORT
                    </td>
                </tr>

                <tr class="center bold">
                    <td width="20%">Asset/Item Code</td>
                    <td width="35%">Description</td>
                    <td width="15%">Acquisition Date</td>
                    <td width="20%">Remarks</td>
                    <td width="10%">Image</td>
                </tr>
                <?php
                // ❌ REMOVE THIS (not needed anymore)
// $items->data_seek(0);
            
                foreach ($items as $item):
                    ?>
                    <tr>
                        <td class="center"><?= $item['asset_code'] ?></td>
                        <td><?= $item['description'] ?></td>
                        <td class="center">
                            <?= ($item['acquisition_date'] === 'N/A' || empty($item['acquisition_date']))
                                ? 'N/A'
                                : date("n/j/Y", strtotime($item['acquisition_date'])) ?>
                        </td>
                        <td class="center"><?= $item['remarks'] ?></td>
                        <td class="center img-box">
                            <?php foreach ($item['images'] as $img): ?>
                                <img src="../uploads/mir/<?= $img ?>">
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <tr>
                    <td colspan="2" class="bold">Prepared By:</td>
                    <td colspan="3" class="bold">Certified Correct:</td>
                </tr>

                <tr>
                    <td colspan="2" class="sign">
                        <div style="margin-top:40px;">
                            <u><?= strtoupper($mir['prepared_by']) ?></u><br>
                            <small><?= htmlspecialchars($prepared_position) ?></small>
                        </div>
                    </td>

                    <td colspan="3" class="sign">
                        <u><?= $mir['certified_by'] ?></u><br>
                        Management Information Design Specialist
                    </td>
                </tr>

            </table>

        </div>

    <?php } ?>

    <div id="copy1">
        <?php renderMIR($mir, $items, $conn, $prepared_position); ?>
    </div>

    <div id="copy2">
        <?php renderMIR($mir, $items, $conn, $prepared_position); ?>
    </div>

    <div class="page-break"></div>

    <div id="copy3">
        <?php renderMIR($mir, $items, $conn, $prepared_position); ?>
    </div>

    <script>
        window.onload = function () {
            window.print();
            window.onafterprint = () => window.close();
        };
    </script>

    <script>
        window.onload = function () {

            const copy1 = document.getElementById("copy1");
            const copy2 = document.getElementById("copy2");

            // A4 height in px (approx)
            const A4_HEIGHT = 1122;
            const HALF_PAGE = A4_HEIGHT / 2;

            const copy1Height = copy1.offsetHeight;

            // 🔥 IF 1st copy too tall → push 2nd copy
            if (copy1Height > HALF_PAGE) {
                copy2.style.pageBreakBefore = "always";
            }

            window.print();
            window.onafterprint = () => window.close();
        };
    </script>

</body>

</html>