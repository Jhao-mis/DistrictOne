<?php
require 'db.php';

/* ============================
   HANDLE MANUAL UPDATE
============================ */
if (isset($_POST['update_emp'])) {
    $user_id = $_POST['user_id'];
    $emp_id  = ($_POST['emp_id'] !== '') ? $_POST['emp_id'] : NULL;

    $stmt = $conn->prepare("UPDATE users SET emp_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $emp_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

/* ============================
   FILTER LOGIC
============================ */
$filter = $_GET['filter'] ?? 'all';

$where = '';
if ($filter === 'with') {
    $where = "WHERE emp_id IS NOT NULL AND emp_id != 0";
} elseif ($filter === 'without') {
    $where = "WHERE emp_id IS NULL OR emp_id = 0";
}

/* ============================
   FETCH USERS
============================ */
$users = $conn->query("
    SELECT id, emp_id, firstname, lastname, department
    FROM users
    $where
    ORDER BY lastname, firstname
");

/* ============================
   COUNTS
============================ */
$count = $conn->query("
    SELECT
        SUM(emp_id IS NOT NULL AND emp_id != 0) AS with_emp,
        SUM(emp_id IS NULL OR emp_id = 0) AS without_emp
    FROM users
")->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
<title>EMP ID Dashboard</title>

<style>
body {
    font-family: Arial, sans-serif;
    background: #f5f6fa;
    padding: 30px;
}
h1 { margin-bottom: 20px; }

.cards {
    display: flex;
    gap: 20px;
    margin-bottom: 25px;
}
.card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    width: 200px;
    box-shadow: 0 2px 5px rgba(0,0,0,.1);
}
.card h2 {
    margin: 0;
    font-size: 28px;
}
.card p {
    margin: 5px 0 0;
    color: #555;
}

.filters {
    margin-bottom: 20px;
}
.filters a {
    padding: 10px 15px;
    background: #ddd;
    color: #000;
    text-decoration: none;
    border-radius: 5px;
    margin-right: 10px;
}
.filters a.active {
    background: #007bff;
    color: #fff;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
}
th, td {
    padding: 12px;
    border-bottom: 1px solid #eee;
    text-align: left;
}
th {
    background: #f0f0f0;
}

input[type="number"] {
    width: 90px;
    padding: 5px;
}

button {
    padding: 6px 10px;
    background: #28a745;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}
button:hover {
    background: #218838;
}

.no-emp {
    color: #c00;
    font-weight: bold;
}
</style>

</head>
<body>

<h1>EMP ID Dashboard</h1>

<!-- SUMMARY CARDS -->
<div class="cards">
    <div class="card">
        <h2><?= $count['with_emp'] ?></h2>
        <p>With EMP ID</p>
    </div>
    <div class="card">
        <h2><?= $count['without_emp'] ?></h2>
        <p>Without EMP ID</p>
    </div>
</div>

<!-- FILTER BUTTONS -->
<div class="filters">
    <a href="?filter=all" class="<?= $filter=='all'?'active':'' ?>">All</a>
    <a href="?filter=with" class="<?= $filter=='with'?'active':'' ?>">With EMP ID</a>
    <a href="?filter=without" class="<?= $filter=='without'?'active':'' ?>">Without EMP ID</a>
</div>

<!-- USERS TABLE -->
<table>
<thead>
<tr>
    <th>Name</th>
    <th>Department</th>
    <th>EMP ID</th>
    <th>Action</th>
</tr>
</thead>
<tbody>

<?php while ($row = $users->fetch_assoc()): ?>
<tr>
    <td><?= htmlspecialchars($row['lastname'] . ', ' . $row['firstname']) ?></td>
    <td><?= htmlspecialchars($row['department']) ?></td>
    <td class="<?= (!$row['emp_id'] ? 'no-emp' : '') ?>">
        <?= $row['emp_id'] ?: '—' ?>
    </td>
    <td>
        <form method="POST" style="display:flex; gap:5px;">
            <input type="hidden" name="user_id" value="<?= $row['id'] ?>">
            <input type="number" name="emp_id" value="<?= $row['emp_id'] ?>" placeholder="EMP ID">
            <button name="update_emp">Save</button>
        </form>
    </td>
</tr>
<?php endwhile; ?>

</tbody>
</table>

</body>
</html>
