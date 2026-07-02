<?php
// Always respond as JSON, even if something goes wrong below.
header('Content-Type: application/json');

// Catch PHP warnings/notices so they don't leak into the JSON output.
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    error_log("PHP error [$errno]: $errstr in $errfile on line $errline");
    return true; // suppress default HTML output of the warning
});

try {
    include '../../db.php';           // database connection
    include '../login_verification.php'; // session check

    // If login_verification.php redirects unauthenticated users with header(),
    // it will have already sent output before we get here in some setups.
    // Make sure this file is only reachable by an authenticated session.
    if (!isset($_SESSION['username'])) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated. Please log in again.']);
        exit;
    }

    // ── GET: fetch a single user's data to populate the modal ──────────
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
        $id = (int) $_GET['id'];
        $query = "SELECT * FROM users WHERE id = $id";
        $result = mysqli_query($conn, $query);

        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'Query failed: ' . mysqli_error($conn)]);
            exit;
        }

        $user = mysqli_fetch_assoc($result);

        if ($user) {
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
        }
        exit;
    }

    // ── POST: update the user ───────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_POST['id'])) {
            echo json_encode(['success' => false, 'message' => 'Missing account id.']);
            exit;
        }

        $id         = (int) $_POST['id'];
        $emp_id     = mysqli_real_escape_string($conn, $_POST['emp_id'] ?? '');
        $firstname  = mysqli_real_escape_string($conn, $_POST['firstname'] ?? '');
        $middlename = mysqli_real_escape_string($conn, $_POST['middlename'] ?? '');
        $lastname   = mysqli_real_escape_string($conn, $_POST['lastname'] ?? '');
        $username   = mysqli_real_escape_string($conn, $_POST['username'] ?? '');
        $email      = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
        $department = mysqli_real_escape_string($conn, $_POST['department'] ?? '');
        $role       = mysqli_real_escape_string($conn, $_POST['role'] ?? '');

        // Duplicate emp_id check
        $dup_check = mysqli_query($conn, "SELECT id FROM users WHERE emp_id = '$emp_id' AND id != '$id'");
        if ($dup_check && mysqli_num_rows($dup_check) > 0) {
            echo json_encode([
                'success' => false,
                'duplicate_emp' => true,
                'message' => 'The Employee Number you entered already exists.'
            ]);
            exit;
        }

        $update = "
            UPDATE users SET
                emp_id = '$emp_id',
                firstname = '$firstname',
                middlename = '$middlename',
                lastname = '$lastname',
                username = '$username',
                email = '$email',
                department = '$department',
                role = '$role'
            WHERE id = '$id'
        ";

        if (mysqli_query($conn, $update)) {
            echo json_encode([
                'success' => true,
                'message' => 'The account information has been successfully updated.',
                'user' => [
                    'id'         => $id,
                    'firstname'  => $firstname,
                    'middlename' => $middlename,
                    'lastname'   => $lastname,
                    'fullname'   => trim("$firstname $middlename $lastname"),
                    'username'   => $username,
                    'email'      => $email,
                    'department' => $department,
                    'role'       => $role,
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed: ' . mysqli_error($conn)]);
        }
        exit;
    }

    // Anything else (wrong method, missing id, etc.)
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);

} catch (Throwable $e) {
    error_log('edit_account.php exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}