<?php
$assignedMisLinks = [
    ['mis.ticket_assignment', 'Ticket Assignment', '../mis/ticketAssign.php'],
    ['mis.machine_inspection', 'Machine Inspection Report', '../mis/mir.php']
];
$assignedSpecialLinks = [
    ['special.live_report', 'Live Report', '../super_admin/monitoring.php'],
    ['special.ticket_request', 'Ticket Request', '../super_admin/ticketRequest.php'],
    ['special.ticket_approval', 'Ticket Approval', '../super_admin/approveTicket.php'],
    ['special.add_announcements', 'Add Announcements', '../super_admin/addAnnouncement.php'],
    ['special.add_calendar_activity', 'Add Calendar Activity', '../super_admin/addEvents.php'],
    ['special.room_reservation', 'Room Reservation', '../super_admin/approveRoom.php'],
    ['special.payroll_management', 'Payroll Management', '../super_admin/payrollmng.php'],
    ['special.account_management', 'Account Management', '../super_admin/accountManagement.php'],
    ['special.leave_summary_upload', 'Leave Summary Import', '../super_admin/leaveSummaryUpload.php']
];

$renderAssignedLinks = static function (array $links): void {
    $currentPage = basename($_SERVER['PHP_SELF'] ?? '');
    foreach ($links as [$permission, $label, $href]) {
        if (!access_can($permission)) {
            continue;
        }
        $active = basename(parse_url($href, PHP_URL_PATH) ?: '') === $currentPage;
        echo '<li class="menu-item' . ($active ? ' active' : '') . '"><a href="' . htmlspecialchars($href) . '" class="menu-link"><div>' . htmlspecialchars($label) . '</div></a></li>';
    }
};
?>

<?php if (access_can_any(array_column($assignedMisLinks, 0))): ?>
<li class="menu-item">
  <a href="javascript:void(0);" class="menu-link menu-toggle"><i class="menu-icon tf-icons bx bx-wrench"></i><div>MIS Tools</div></a>
  <ul class="menu-sub"><?php $renderAssignedLinks($assignedMisLinks); ?></ul>
</li>
<?php endif; ?>

<?php if (!access_is_super_admin() && access_can_any(array_column($assignedSpecialLinks, 0))): ?>
<?php $currentPage = basename($_SERVER['PHP_SELF'] ?? ''); ?>
<li class="menu-item <?= in_array($currentPage, array_map(static fn(array $link): string => basename(parse_url($link[2], PHP_URL_PATH) ?: ''), $assignedSpecialLinks), true) ? 'active open' : '' ?>">
  <a href="javascript:void(0);" class="menu-link menu-toggle"><i class="menu-icon tf-icons bx bx-shield-quarter"></i><div>Special Access</div></a>
  <ul class="menu-sub"><?php $renderAssignedLinks($assignedSpecialLinks); ?></ul>
</li>
<?php endif; ?>
