<?php
// Always respond as JSON, even if something goes wrong below.
header('Content-Type: application/json');
$transactionStarted = false;

// Catch PHP warnings/notices so they don't leak into the JSON output.
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    error_log("PHP error [$errno]: $errstr in $errfile on line $errline");
    return true; // suppress default HTML output of the warning
});

try {
    include '../../db.php';           // database connection
    include '../../lib/access_control.php';
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
            $permissionStmt = $conn->prepare('SELECT p.permission_key FROM user_permissions up JOIN permissions p ON p.id = up.permission_id WHERE up.user_id = ?');
            $permissionStmt->bind_param('i', $id);
            $permissionStmt->execute();
            $permissionResult = $permissionStmt->get_result();
            $user['permissions'] = [];
            while ($permission = $permissionResult->fetch_assoc()) {
                $user['permissions'][] = $permission['permission_key'];
            }
            $permissionStmt->close();
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
        }
        exit;
    }

    // ── POST: update the user ───────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $stringFields = ['emp_id', 'firstname', 'middlename', 'lastname', 'username', 'email', 'department'];
        $invalidFieldType = false;
        foreach ($stringFields as $field) {
            if (isset($_POST[$field]) && !is_string($_POST[$field])) {
                $invalidFieldType = true;
                break;
            }
        }
        if ($invalidFieldType) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'One or more account fields have an invalid format.']);
            exit;
        }

        $emp_id = trim((string) ($_POST['emp_id'] ?? ''));
        $firstname = trim((string) ($_POST['firstname'] ?? ''));
        $middlename = trim((string) ($_POST['middlename'] ?? ''));
        $lastname = trim((string) ($_POST['lastname'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $department = trim((string) ($_POST['department'] ?? ''));
        $permissions = $_POST['permissions'] ?? [];
        $validDepartments = [
            'Office of the General Manager',
            'Management Information Services Section',
            'Manage Information Services Section',
            'Administrative Department',
            'Finance Department',
            'Commercial Department',
            'Technical Services Department',
            'Operations Department'
        ];

        $validationErrors = [];
        if (!$id || $id < 1) {
            $validationErrors[] = 'A valid account must be selected.';
        }
        if ($firstname === '') {
            $validationErrors[] = 'First name is required.';
        }
        if ($lastname === '') {
            $validationErrors[] = 'Last name is required.';
        }
        if ($emp_id === '') {
            $validationErrors[] = 'Employee Number is required.';
        }
        if ($username === '') {
            $validationErrors[] = 'Username is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $validationErrors[] = 'Enter a valid email address.';
        }
        if (!in_array($department, $validDepartments, true)) {
            $validationErrors[] = 'Select a valid department.';
        }
        if (!is_array($permissions)) {
            $validationErrors[] = 'The selected permissions are invalid.';
            $permissions = [];
        }
        foreach ($permissions as $permission) {
            if (!is_string($permission) || $permission === '') {
                $validationErrors[] = 'The selected permissions are invalid.';
                break;
            }
        }

        if (!empty($validationErrors)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => implode(' ', array_unique($validationErrors))
            ]);
            exit;
        }

        $permissions = array_values(array_unique($permissions));
        $validPermissionKeys = [];
        $permissionResult = $conn->query('SELECT permission_key FROM permissions');
        while ($permissionRow = $permissionResult->fetch_assoc()) {
            $validPermissionKeys[] = $permissionRow['permission_key'];
        }
        if (array_diff($permissions, $validPermissionKeys)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'One or more selected permissions are invalid. Refresh and try again.']);
            exit;
        }

        $accountCheck = $conn->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
        $accountCheck->bind_param('i', $id);
        $accountCheck->execute();
        $existingAccount = $accountCheck->get_result()->fetch_assoc();
        $accountCheck->close();
        if (!$existingAccount) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'The selected account was not found.']);
            exit;
        }

        $duplicateCheck = $conn->prepare('SELECT id FROM users WHERE emp_id = ? AND id != ? LIMIT 1');
        $duplicateCheck->bind_param('si', $emp_id, $id);
        $duplicateCheck->execute();
        $hasDuplicateEmployeeId = $duplicateCheck->get_result()->num_rows > 0;
        $duplicateCheck->close();
        if ($hasDuplicateEmployeeId) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'duplicate_emp' => true,
                'message' => 'The Employee Number you entered already exists.'
            ]);
            exit;
        }

        $isMisDepartment = $department === 'Management Information Services Section';
        $misPermissionKeys = [];
        $misPermissionResult = $conn->query(
            "SELECT permission_key
             FROM permissions
             WHERE group_name = 'MIS Access'
               AND permission_key <> 'mis.personnel'
               AND LOWER(label) <> 'it personnel'"
        );
        while ($misPermissionRow = $misPermissionResult->fetch_assoc()) {
            $misPermissionKeys[] = $misPermissionRow['permission_key'];
        }

        if ($isMisDepartment) {
            $requiredMisPermissions = $misPermissionKeys;
            if (empty($requiredMisPermissions)) {
                throw new RuntimeException('The MIS Access permission group is not configured.');
            }
            if ($missingPermissions = array_diff($requiredMisPermissions, $validPermissionKeys)) {
                throw new RuntimeException('Required MIS permissions are not configured: ' . implode(', ', $missingPermissions));
            }
            $itPersonnelPermissionKeys = [];
            $itPersonnelResult = $conn->query(
                "SELECT permission_key FROM permissions
                 WHERE permission_key = 'mis.personnel' OR LOWER(label) = 'it personnel'"
            );
            while ($itPersonnelPermission = $itPersonnelResult->fetch_assoc()) {
                $itPersonnelPermissionKeys[] = $itPersonnelPermission['permission_key'];
            }
            $permissions = array_values(array_diff($permissions, $itPersonnelPermissionKeys));
            $permissions = array_values(array_unique(array_merge($permissions, $requiredMisPermissions)));
        } else {
            $permissions = array_values(array_diff($permissions, $misPermissionKeys));
            $existingItPersonnel = $conn->prepare(
                'SELECT p.permission_key
                 FROM user_permissions up
                 JOIN permissions p ON p.id = up.permission_id
                 WHERE up.user_id = ? AND (p.permission_key = ? OR LOWER(p.label) = ?)'
            );
            $itPersonnelKey = 'mis.personnel';
            $itPersonnelLabel = 'it personnel';
            $existingItPersonnel->bind_param('iss', $id, $itPersonnelKey, $itPersonnelLabel);
            $existingItPersonnel->execute();
            $existingItPersonnelResult = $existingItPersonnel->get_result();
            $existingItPersonnelKeys = [];
            while ($existingItPersonnelPermission = $existingItPersonnelResult->fetch_assoc()) {
                $existingItPersonnelKeys[] = $existingItPersonnelPermission['permission_key'];
            }
            $existingItPersonnel->close();
            $permissions = array_values(array_unique(array_merge($permissions, $existingItPersonnelKeys)));
        }

        $conn->begin_transaction();
        $transactionStarted = true;

        $update = $conn->prepare(
            'UPDATE users
             SET emp_id = ?, firstname = ?, middlename = ?, lastname = ?, username = ?, email = ?, department = ?
             WHERE id = ?'
        );
        $update->bind_param('sssssssi', $emp_id, $firstname, $middlename, $lastname, $username, $email, $department, $id);
        $update->execute();
        $update->close();

        $deletePermissions = $conn->prepare('DELETE FROM user_permissions WHERE user_id = ?');
        $deletePermissions->bind_param('i', $id);
        $deletePermissions->execute();
        $deletePermissions->close();

        $permissionStmt = $conn->prepare(
            'INSERT INTO user_permissions (user_id, permission_id)
             SELECT ?, id FROM permissions WHERE permission_key = ?'
        );
        foreach ($permissions as $permission) {
            $permissionStmt->bind_param('is', $id, $permission);
            $permissionStmt->execute();
        }
        $permissionStmt->close();
        $conn->commit();
        $transactionStarted = false;

        echo json_encode([
            'success' => true,
            'message' => 'The account information has been successfully updated.',
            'user' => [
                'id' => $id,
                'firstname' => $firstname,
                'middlename' => $middlename,
                'lastname' => $lastname,
                'fullname' => trim("$firstname $middlename $lastname"),
                'username' => $username,
                'email' => $email,
                'department' => $department,
                'permissions' => $permissions,
            ]
        ]);
        exit;
    }

    // Anything else (wrong method, missing id, etc.)
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);

} catch (Throwable $e) {
    if (!empty($transactionStarted) && isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            error_log('Account update rollback failed: ' . $rollbackError->getMessage());
        }
    }
    error_log('edit_account.php exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}