<?php
$conn = new mysqli("localhost", "root", "", "districtone");

if (isset($_POST['upload'])) {
    $files = $_FILES['image'];

    foreach ($files['tmp_name'] as $key => $tmp_name) {
        $imageName = time() . "_" . basename($files['name'][$key]);
        $targetPath = "uploads/" . $imageName;

        if (move_uploaded_file($tmp_name, $targetPath)) {
            $conn->query("INSERT INTO image_slider (image_url) VALUES ('$targetPath')");
        }
    }
    echo "Images uploaded successfully!";
}
?>