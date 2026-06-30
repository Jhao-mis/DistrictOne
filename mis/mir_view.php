<?php
require '../db.php';

$conn = new mysqli($host, $user, $pass, $db);

$id = $_GET['id'];

$report = $conn->query("SELECT * FROM mir_reports WHERE id=$id")->fetch_assoc();
$items = $conn->query("SELECT * FROM mir_items WHERE mir_id=$id");
?>

<h6>Report #: <?= $report['report_no'] ?></h6>
<p>
    End User: <?= $report['end_user'] ?><br>
    Department: <?= $report['department'] ?><br>
    Date: <?= $report['report_date'] ?>
</p>

<table class="table table-bordered">
    <tr>
        <th>Asset</th>
        <th>Description</th>
        <th>Remarks</th>
        <th>Image</th>
    </tr>

    <?php while ($row = $items->fetch_assoc()): ?>
        <tr>
            <td><?= $row['asset_code'] ?></td>
            <td><?= $row['description'] ?></td>
            <td><?= $row['remarks'] ?></td>
            <td>
                <?php
                $imgs = $conn->query("SELECT * FROM mir_item_images WHERE item_id=" . $row['id']);
                while ($img = $imgs->fetch_assoc()):
                    ?>
                    <img src="../uploads/mir/<?= $img['image_path'] ?>" class="img-thumbnail me-1 mb-1" style="width:80px;">
                <?php endwhile; ?>
            </td>
        </tr>
    <?php endwhile; ?>
</table>