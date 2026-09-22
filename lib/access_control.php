<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function access_refresh_session(mysqli $conn, int $userId): array
{
    $permissions = [];
    $stmt = $conn->prepare('SELECT p.permission_key FROM user_permissions up JOIN permissions p ON p.id = up.permission_id WHERE up.user_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $permissions[] = $row['permission_key'];
    }

    $stmt->close();
    $_SESSION['permissions'] = $permissions;
    return $permissions;
}

function access_normalize_role(string $role): string
{
    return strtolower(trim($role)) === 'superadmin' || strtolower(trim($role)) === 'super admin'
        ? 'Super Admin'
        : 'User';
}

function access_has_valid_role(): bool
{
    $role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
    if ($role === 'superadmin' || $role === 'super admin') {
        $_SESSION['role'] = 'Super Admin';
        return true;
    }
    if ($role === 'user') {
        $_SESSION['role'] = 'User';
        return true;
    }

    return false;
}

function access_is_super_admin(): bool
{
    return in_array(strtolower(trim((string) ($_SESSION['role'] ?? ''))), ['superadmin', 'super admin'], true);
}

function access_can(string $permission): bool
{
    return access_is_super_admin() || in_array($permission, $_SESSION['permissions'] ?? [], true);
}

function access_can_any(array $permissions): bool
{
    foreach ($permissions as $permission) {
        if (access_can($permission)) {
            return true;
        }
    }

    return false;
}

function access_require(string $permission, string $redirect = '../sessionExpired.php'): void
{
    if (!access_can($permission)) {
        header('Location: ' . $redirect);
        exit();
    }
}

function access_permission_for_request(): ?string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $file = basename($script);

    $modulePermissions = [
        'profile.php' => 'module.profile',
        'accountSettings.php' => 'module.account_settings',
        'personaldataSheet.php' => 'module.personaldata_sheet',
        'payroll.php' => 'module.payroll',
        'announcement.php' => 'module.announcements',
        'calendar.php' => 'module.calendar',
        'roomReservation.php' => 'module.room_reservation',
        'fileSaln.php' => 'module.saln',
        'leaverequest.php' => 'module.leave',
        'noticeofMeeting.php' => 'module.notice_of_meeting',
        'serviceRequest.php' => 'module.service_request'
    ];
    if (strpos($script, '/modules/') !== false && isset($modulePermissions[$file])) {
        return $modulePermissions[$file];
    }

    $misPermissions = [
        'ticketAssign.php' => 'mis.ticket_assignment',
        'mir.php' => 'mis.machine_inspection'
    ];
    if (strpos($script, '/mis/') !== false && isset($misPermissions[$file])) {
        return $misPermissions[$file];
    }

    $specialPermissions = [
        'monitoring.php' => 'special.live_report',
        'ticketRequest.php' => 'special.ticket_request',
        'approveTicket.php' => 'special.ticket_approval',
        'addAnnouncement.php' => 'special.add_announcements',
        'addEvents.php' => 'special.add_calendar_activity',
        'payrollmng.php' => 'special.payroll_management',
        'accountManagement.php' => 'special.account_management'
    ];
    if (strpos($script, '/super_admin/') !== false && isset($specialPermissions[$file])) {
        return $specialPermissions[$file];
    }
    if (strpos($script, '/super_admin/acc_management/') !== false) {
        return 'special.account_management';
    }

    return null;
}

function access_require_current_request(string $redirect = '../sessionExpired.php'): void
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (basename($script) === 'approveRoom.php' && access_can('special.room_reservation')) {
        return;
    }

    $permission = access_permission_for_request();
    if ($permission !== null) {
        access_require($permission, $redirect);
    }
}
