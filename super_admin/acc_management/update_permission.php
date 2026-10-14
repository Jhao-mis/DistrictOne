<?php
header('Content-Type: application/json; charset=utf-8');

$transactionStarted = false;

try {
    include '../../db.php';
    include '../login_verification.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'POST requests only.']);
        exit;
    }

    if (!access_is_super_admin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only Super Admins can change account permissions.']);
        exit;
    }

    $csrfToken = $_POST['csrf_token'] ?? '';
    if (
        !is_string($csrfToken)
        || empty($_SESSION['permission_matrix_csrf'])
        || !hash_equals($_SESSION['permission_matrix_csrf'], $csrfToken)
    ) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'The request could not be verified. Refresh the page and try again.']);
        exit;
    }

    $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    $permissions = $_POST['permissions'] ?? [];
    if (!$userId || $userId < 1 || !is_array($permissions)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'The account permission request is invalid.']);
        exit;
    }

    foreach ($permissions as $permission) {
        if (!is_string($permission) || $permission === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'The selected permissions are invalid.']);
            exit;
        }
    }
    $permissions = array_values(array_unique($permissions));

    $accountStmt = $conn->prepare('SELECT department FROM users WHERE id = ? LIMIT 1');
    $accountStmt->bind_param('i', $userId);
    $accountStmt->execute();
    $account = $accountStmt->get_result()->fetch_assoc();
    $accountStmt->close();
    if (!$account) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'The selected account was not found.']);
        exit;
    }

    $validPermissionKeys = [];
    $permissionResult = $conn->query('SELECT permission_key FROM permissions');
    while ($permission = $permissionResult->fetch_assoc()) {
        $validPermissionKeys[] = $permission['permission_key'];
    }
    if (array_diff($permissions, $validPermissionKeys)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'One or more selected permissions are invalid. Refresh and try again.']);
        exit;
    }

    if ($account['department'] === 'Management Information Services Section') {
        $requiredMisPermissions = [];
        $misPermissionResult = $conn->query(
            "SELECT permission_key
             FROM permissions
             WHERE group_name = 'MIS Access'
               AND permission_key <> 'mis.personnel'
               AND LOWER(label) <> 'it personnel'"
        );
        while ($misPermission = $misPermissionResult->fetch_assoc()) {
            $requiredMisPermissions[] = $misPermission['permission_key'];
        }
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
        $misPermissionKeys = [];
        $misPermissionResult = $conn->query(
            "SELECT permission_key
             FROM permissions
             WHERE group_name = 'MIS Access'
               AND permission_key <> 'mis.personnel'
               AND LOWER(label) <> 'it personnel'"
        );
        while ($misPermission = $misPermissionResult->fetch_assoc()) {
            $misPermissionKeys[] = $misPermission['permission_key'];
        }
        $permissions = array_values(array_diff($permissions, $misPermissionKeys));
    }

    $conn->begin_transaction();
    $transactionStarted = true;

    $deleteStmt = $conn->prepare('DELETE FROM user_permissions WHERE user_id = ?');
    $deleteStmt->bind_param('i', $userId);
    $deleteStmt->execute();
    $deleteStmt->close();

    $insertStmt = $conn->prepare(
        'INSERT INTO user_permissions (user_id, permission_id)
         SELECT ?, id FROM permissions WHERE permission_key = ?'
    );
    foreach ($permissions as $permissionKey) {
        $insertStmt->bind_param('is', $userId, $permissionKey);
        $insertStmt->execute();
    }
    $insertStmt->close();

    $conn->commit();
    $transactionStarted = false;

    echo json_encode([
        'success' => true,
        'saved_count' => count($permissions)
    ]);
} catch (Throwable $e) {
    if ($transactionStarted && isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            error_log('Permission update rollback failed: ' . $rollbackError->getMessage());
        }
    }
    error_log('Account permission update failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'The account permissions could not be saved due to a server error.']);
}
