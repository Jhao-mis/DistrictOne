<?php
include '../db.php'; // Include your database connection
require 'login_verification.php';
// Fetch the selected department from the user's choice
$client_department = isset($_GET['department']) ? $_GET['department'] : 'All Departments';

// Prepare SQL query based on the selected department
if ($client_department == "All Departments") {
    $sql = "SELECT * FROM announcements WHERE department = 'All Departments' ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
} 
else {
    $sql = "SELECT * FROM announcements WHERE department = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $client_department);
}

$stmt->execute();
$result = $stmt->get_result();

// Display announcements
if ($result->num_rows > 0):
    while ($row = $result->fetch_assoc()): ?>
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <h3 class="card-title"><?php echo htmlspecialchars($row['title']); ?></h3>
                </div>
                <p class="card-text"><?php echo nl2br(htmlspecialchars($row['message'])); ?></p>
                <small class="text-muted">Posted on: <?php echo $row['created_at']; ?></small>

                <!-- File Attachments -->
                <?php if (!empty($row['file_path'])): ?>
                    <?php 
                        $file_path = 'uploads/' . basename($row['file_path']); 
                        $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                    ?>

                    <?php if (file_exists($file_path)): ?>
                        <?php if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                            <div class="mt-3">
                                <img src="<?php echo htmlspecialchars($file_path); ?>" alt="Post Image" class="img-fluid">
                            </div>
                        <?php elseif ($file_extension === 'pdf'): ?>
                            <div class="mt-3">
                                <a href="<?php echo htmlspecialchars($file_path); ?>" class="btn btn-sm btn-danger" download>Download PDF</a>
                            </div>
                        <?php else: ?>
                            <div class="mt-3">
                                <a href="<?php echo htmlspecialchars($file_path); ?>" class="btn btn-sm btn-secondary" download>Download File</a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-danger">File not found.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endwhile;
else: ?>
    <p class="text-center">No announcements available.</p>
<?php endif;

$stmt->close();
$conn->close();
?>
