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
    ['special.account_management', 'Account Management', '../super_admin/accountManagement.php']
];

$renderAssignedLinks = static function (array $links): void {
    foreach ($links as [$permission, $label, $href]) {
        if (!access_can($permission)) {
            continue;
        }
        echo '<li class="menu-item"><a href="' . htmlspecialchars($href) . '" class="menu-link"><div>' . htmlspecialchars($label) . '</div></a></li>';
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
<li class="menu-item">
  <a href="javascript:void(0);" class="menu-link menu-toggle"><i class="menu-icon tf-icons bx bx-shield-quarter"></i><div>Special Access</div></a>
  <ul class="menu-sub"><?php $renderAssignedLinks($assignedSpecialLinks); ?></ul>
</li>
<?php endif; ?>
