<?php
include '../db.php';

if (!isset($_GET['q'])) {
    exit;
}

$search = "%" . $_GET['q'] . "%";

$stmt = $conn->prepare("
    SELECT CONCAT(firstname, ' ', lastname) AS fullname
    FROM users
    WHERE firstname LIKE ? OR lastname LIKE ?
    LIMIT 10
");
$stmt->bind_param("ss", $search, $search);
$stmt->execute();
$result = $stmt->get_result();

$suggestions = [];
while ($row = $result->fetch_assoc()) {
    $suggestions[] = $row['fullname'];
}

echo json_encode($suggestions);
