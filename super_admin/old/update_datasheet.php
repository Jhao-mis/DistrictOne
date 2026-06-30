<?php
session_start();
require '../db.php'; // Include database connection
require 'login_verification.php';


$username = $_SESSION['username']; // Retrieve logged-in user's username

// Establish database connection
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Fetch user details including `id`
$query = $conn->prepare("SELECT id, firstname, middlename, lastname, email, department FROM users WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->store_result();

if ($query->num_rows === 0) {
    die("Error: User not found.");
}

$query->bind_result($user_id, $firstname, $middlename, $lastname, $email, $department);
$query->fetch();
$query->close(); // Close query to free up connection



// Now fetch the personal data sheet using the `user_id`
$sql = "SELECT * FROM personal_data_sheet WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close(); // Close statement

// Fetch the personal data sheet using the `user_id`
$sql = "SELECT * FROM personal_data_sheet WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close(); // Close statement

// Check citizenship value
$citizenship = $_POST['citizenship'];
$dual_holder = ($citizenship === "Dual") ? $_POST['dual_holder'] : NULL;

if ($user_data) {
    // User exists, update the record
    $stmt = $conn->prepare("UPDATE personal_data_sheet SET 
        name_extension = ?, personal_date_of_birth = ?, place_of_birth = ?, sex = ?, civil_status = ?,
        height = ?, weight = ?, blood_type = ?, gsis_id_no = ?, pagibig_id_no = ?,
        philhealth_no = ?, sss_no = ?, tin_no = ?, agency_employee_no = ?, citizenship = ?,
        dual_holder = ?, 
        RA_house_block_lot_no = ?, RA_subdivision_village = ?, RA_city_municipality = ?, RA_street = ?,
        RA_barangay = ?, RA_province = ?, RA_zip_code = ?,
        PA_house_block_lot_no = ?, PA_subdivision_village = ?, PA_city_municipality = ?, PA_street = ?,
        PA_barangay = ?, PA_province = ?, PA_zip_code = ?, telephone_no = ?, mobile_no = ?
        WHERE user_id = ?");

    $stmt->bind_param(
        "ssssssssiiiiiissssssssssssssssssi",
        $_POST['name_extension'],
        $_POST['personal_date_of_birth'],
        $_POST['place_of_birth'],
        $_POST['sex'],
        $_POST['civil_status'],
        $_POST['height'],
        $_POST['weight'],
        $_POST['blood_type'],
        $_POST['gsis_id_no'],
        $_POST['pagibig_id_no'],
        $_POST['philhealth_no'],
        $_POST['sss_no'],
        $_POST['tin_no'],
        $_POST['agency_employee_no'],
        $citizenship,
        $dual_holder,
        $_POST['RA_house_block_lot_no'],
        $_POST['RA_subdivision_village'],
        $_POST['RA_city_municipality'],
        $_POST['RA_street'],
        $_POST['RA_barangay'],
        $_POST['RA_province'],
        $_POST['RA_zip_code'],
        $_POST['PA_house_block_lot_no'],
        $_POST['PA_subdivision_village'],
        $_POST['PA_city_municipality'],
        $_POST['PA_street'],
        $_POST['PA_barangay'],
        $_POST['PA_province'],
        $_POST['PA_zip_code'],
        $_POST['telephone_no'],
        $_POST['mobile_no'],
        $user_id
    );

    if (!$stmt->execute()) {
        die("Error updating personal_data_sheet: " . $stmt->error);
    }
} else {
    // User does not exist, insert a new record
    $stmt = $conn->prepare("INSERT INTO personal_data_sheet (
        user_id, name_extension, personal_date_of_birth, place_of_birth, sex, civil_status,
        height, weight, blood_type, gsis_id_no, pagibig_id_no,
        philhealth_no, sss_no, tin_no, agency_employee_no, citizenship,
        dual_holder, 
        RA_house_block_lot_no, RA_subdivision_village, RA_city_municipality, RA_street,
        RA_barangay, RA_province, RA_zip_code,
        PA_house_block_lot_no, PA_subdivision_village, PA_city_municipality, PA_street,
        PA_barangay, PA_province, PA_zip_code, telephone_no, mobile_no
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param(
        "issssssiiiiiissssssssssssssssssi",
        $user_id,
        $_POST['name_extension'],
        $_POST['personal_date_of_birth'],
        $_POST['place_of_birth'],
        $_POST['sex'],
        $_POST['civil_status'],
        $_POST['height'],
        $_POST['weight'],
        $_POST['blood_type'],
        $_POST['gsis_id_no'],
        $_POST['pagibig_id_no'],
        $_POST['philhealth_no'],
        $_POST['sss_no'],
        $_POST['tin_no'],
        $_POST['agency_employee_no'],
        $citizenship,
        $dual_holder,
        $_POST['RA_house_block_lot_no'],
        $_POST['RA_subdivision_village'],
        $_POST['RA_city_municipality'],
        $_POST['RA_street'],
        $_POST['RA_barangay'],
        $_POST['RA_province'],
        $_POST['RA_zip_code'],
        $_POST['PA_house_block_lot_no'],
        $_POST['PA_subdivision_village'],
        $_POST['PA_city_municipality'],
        $_POST['PA_street'],
        $_POST['PA_barangay'],
        $_POST['PA_province'],
        $_POST['PA_zip_code'],
        $_POST['telephone_no'],
        $_POST['mobile_no']
    );

    if (!$stmt->execute()) {
        die("Error inserting into personal_data_sheet: " . $stmt->error);
    }
}

$stmt->close();



// Close database connection
$conn->close();
?>