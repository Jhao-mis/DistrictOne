<?php
require '../vendor/autoload.php';
include '../db.php';
require 'login_verification.php';

// ── Counts for badge pills ──────────────────────────────────────────
$approveCount     = $conn->query("SELECT COUNT(*) AS total FROM tickets WHERE status='Pending' AND admin_approved=0")->fetch_assoc()['total'] ?? 0;
$reservationCount = $conn->query("SELECT COUNT(*) AS total FROM room_reservations WHERE status='Pending'")->fetch_assoc()['total'] ?? 0;
$unverifiedCount  = $conn->query("SELECT COUNT(*) AS total FROM users WHERE isVerified=0")->fetch_assoc()['total'] ?? 0;
$notifTotalCount  = (int)$approveCount + (int)$reservationCount;

// ── Detect current page ────────────────────────────────────────────
$currentPage = basename($_SERVER['PHP_SELF']);

// ── User info (resolved from each page's own query + session) ──────
$sb_username = $_SESSION['username'] ?? '';
$sb_profile  = $_SESSION['profile_picture'] ?? '../assets/img/avatars/default_dp.jpg';
$sb_role     = $_SESSION['role'] ?? '';
$sb_dept     = $department ?? '';
$sb_name     = trim(($firstname ?? '') . ' ' . ($lastname ?? ''));
if (!$sb_name) $sb_name = $sb_username;
?>

<style>
/* ═══════════════════════════════════════════════════════════════════
   SIDEBAR — scoped tightly so nothing bleeds into page content
═══════════════════════════════════════════════════════════════════ */
:root {
  --sb-primary:      #7cb9ff;
  --sb-primary-dark: #4e96f0;
  --sb-primary-soft: #eaf3ff;
  --sb-bg:           #ffffff;
  --sb-border:       #eceef2;
  --sb-text:         #1f2430;
  --sb-muted:        #767e8c;
  --sb-danger:       #c0392b;
  --sb-success:      #1f9d55;
}

/* Remove the gap the template reserves for the detached top navbar */
.layout-page         { padding-top: 0 !important; }
.content-wrapper     { margin-top: 0 !important; }
.layout-navbar       { display: none !important; } /* hide if any page still renders it */

/* ── Mobile menu toggle ───────────────────────────────────────────
   The top navbar (which used to hold the hamburger button) is hidden,
   so this floating button is the only way to open the sidebar on
   mobile/tablet. Uses the same `layout-menu-toggle` class the
   template's menu.js already binds click handlers to. ─────────── */
.sb-mobile-toggle {
  display: flex;
  align-items: center; justify-content: center;
  position: fixed;
  top: 14px; left: 14px;
  z-index: 1071;
  width: 40px; height: 40px;
  border-radius: 10px;
  border: 1px solid var(--sb-border);
  background: var(--sb-bg);
  color: var(--sb-text);
  cursor: pointer;
  box-shadow: 0 4px 14px -6px rgba(20,20,43,.25);
}
.sb-mobile-toggle svg { width: 20px; height: 20px; }
.sb-mobile-toggle:hover { border-color: var(--sb-primary); color: var(--sb-primary-dark); }
/* Hide once the sidebar is permanently visible (desktop, >=1200px) */
@media (min-width: 1200px) {
  .sb-mobile-toggle { display: none !important; }
}

/* ── Sidebar shell ─────────────────────────────────────────────── */
#layout-menu.layout-menu {
  background: var(--sb-bg) !important;
  border-right: 1px solid var(--sb-border);
  display: flex !important;
  flex-direction: column;
  overflow: hidden;
}

/* ── Brand row ─────────────────────────────────────────────────── */
#layout-menu .app-brand.demo {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 14px 16px 12px;
  border-bottom: 1px solid var(--sb-border);
  flex-shrink: 0;
}
#layout-menu .sb-brand-text {
  font-size: 15px;
  font-weight: 800;
  color: var(--sb-text);
  letter-spacing: -.1px;
  white-space: nowrap;
}

/* ── User profile block ────────────────────────────────────────── */
.sb-user-block {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 14px 12px;
  border-bottom: 1px solid var(--sb-border);
  background: var(--sb-bg);
  flex-shrink: 0;
}
.sb-avatar-wrap { position: relative; flex-shrink: 0; }
.sb-avatar {
  width: 44px; height: 44px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid var(--sb-primary);
  box-shadow: 0 0 0 2.5px var(--sb-primary-soft);
  display: block;
}
.sb-online-dot {
  position: absolute; bottom: 1px; right: 1px;
  width: 10px; height: 10px; border-radius: 50%;
  background: var(--sb-success);
  border: 2px solid var(--sb-bg);
}
.sb-user-info { flex: 1; min-width: 0; }
.sb-user-name {
  font-size: 13px; font-weight: 700; color: var(--sb-text);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  line-height: 1.3;
}
.sb-user-role {
  font-size: 11px; color: var(--sb-muted);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  margin-top: 1px;
}
.sb-bell-btn {
  flex-shrink: 0;
  position: relative;
  background: var(--sb-primary-soft);
  border: none; border-radius: 9px;
  width: 34px; height: 34px;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; color: #2563a8;
  transition: background .12s ease, color .12s ease;
}
.sb-bell-btn:hover { background: var(--sb-primary); color: #fff; }
.sb-bell-btn svg { width: 17px; height: 17px; }
.sb-bell-badge {
  position: absolute; top: -4px; right: -4px;
  background: var(--sb-danger); color: #fff;
  font-size: 9px; font-weight: 800;
  min-width: 16px; height: 16px; border-radius: 999px;
  display: none; align-items: center; justify-content: center;
  padding: 0 3px; border: 2px solid var(--sb-bg);
}

/* ── Menu inner (scrollable) ───────────────────────────────────── */
#layout-menu .menu-inner {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 8px 12px !important;
}
#layout-menu .menu-inner::-webkit-scrollbar { width: 4px; }
#layout-menu .menu-inner::-webkit-scrollbar-thumb { background: var(--sb-border); border-radius: 4px; }

#layout-menu .menu-item { margin-bottom: 2px; }
#layout-menu .menu-link {
  border-radius: 9px !important;
  color: var(--sb-muted) !important;
  font-size: 13.5px !important;
  font-weight: 600 !important;
  padding: 9px 12px !important;
  transition: background .12s ease, color .12s ease;
  display: flex; align-items: center;
}
#layout-menu .menu-link .menu-icon {
  color: var(--sb-muted) !important;
  font-size: 18px !important;
  margin-right: 10px; flex-shrink: 0;
  transition: color .12s ease;
}
#layout-menu .menu-link:hover {
  background: var(--sb-primary-soft) !important;
  color: #2563a8 !important;
}
#layout-menu .menu-link:hover .menu-icon { color: #2563a8 !important; }
#layout-menu .menu-item.active > .menu-link {
  background: var(--sb-primary) !important;
  color: #fff !important;
  box-shadow: 0 4px 10px -4px rgba(124,185,255,.55);
}
#layout-menu .menu-item.active > .menu-link .menu-icon { color: #fff !important; }

/* Submenu */
#layout-menu .menu-sub { padding-left: 6px; }
#layout-menu .menu-sub .menu-link {
  font-size: 13px !important;
  font-weight: 500 !important;
  padding: 7px 12px 7px 38px !important;
}
#layout-menu .menu-sub .menu-item.active > .menu-link {
  background: var(--sb-primary-soft) !important;
  color: #2563a8 !important;
  font-weight: 700 !important;
  box-shadow: none;
}
#layout-menu .menu-toggle::after { opacity: .45; }
#layout-menu .menu-item.open > .menu-toggle {
  background: var(--sb-primary-soft) !important;
  color: #2563a8 !important;
}
#layout-menu .menu-item.open > .menu-toggle .menu-icon { color: #2563a8 !important; }
#layout-menu .menu-sub .menu-link div { display: inline-flex; align-items: center; gap: 5px; }

/* Badge pills */
.sb-pill {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 18px; height: 18px; border-radius: 999px;
  font-size: 10px; font-weight: 800; padding: 0 5px;
}
.sb-pill.is-blue { background: var(--sb-primary); color: #fff; }
.sb-pill.is-red  { background: var(--sb-danger); color: #fff; }

/* ── Logout footer ─────────────────────────────────────────────── */
.sb-footer {
  padding: 12px;
  border-top: 1px solid var(--sb-border);
  flex-shrink: 0;
  background: var(--sb-bg);
}
.sb-logout {
  width: 100%;
  display: flex; align-items: center; gap: 10px;
  padding: 10px 14px;
  border: 1.5px solid #fbe9e7;
  background: #fff8f8;
  color: var(--sb-danger);
  border-radius: 9px;
  font-size: 13.5px; font-weight: 700;
  cursor: pointer; text-decoration: none;
  transition: background .12s ease, border-color .12s ease;
}
.sb-logout:hover { background: #fbe9e7; border-color: var(--sb-danger); color: var(--sb-danger); }
.sb-logout svg { width: 17px; height: 17px; flex-shrink: 0; }

/* ── Notification pop-out window ─────────────────────────────────
   Anchored just under the bell button via JS-computed coordinates
   (see script at bottom), rather than a full-height slide-in panel.
─────────────────────────────────────────────────────────────────*/
.sb-notif-popover {
  position: fixed;
  width: 320px;
  max-width: 88vw;
  max-height: min(420px, 70vh);
  background: var(--sb-bg);
  border: 1px solid var(--sb-border);
  border-radius: 14px;
  box-shadow: 0 20px 50px -15px rgba(20,20,43,.28), 0 4px 14px -6px rgba(20,20,43,.14);
  z-index: 1071;
  display: flex; flex-direction: column;
  overflow: hidden;
  opacity: 0;
  visibility: hidden;
  transform: translateY(-8px) scale(.97);
  transform-origin: top left;
  transition: opacity .15s ease, transform .15s ease, visibility .15s;
}
.sb-notif-popover.open {
  opacity: 1;
  visibility: visible;
  transform: translateY(0) scale(1);
}
.sb-notif-head {
  background: linear-gradient(135deg, var(--sb-primary) 0%, var(--sb-primary-dark) 100%);
  padding: 14px 16px;
  display: flex; align-items: center; justify-content: space-between;
  flex-shrink: 0;
}
.sb-notif-head h6 { color: #fff; font-size: 14px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 7px; }
.sb-notif-head h6 svg { width: 16px; height: 16px; }
.sb-notif-close-btn {
  background: rgba(255,255,255,.18); border: none;
  width: 26px; height: 26px; border-radius: 7px;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; color: #fff;
  transition: background .12s ease;
}
.sb-notif-close-btn:hover { background: rgba(255,255,255,.32); }
.sb-notif-close-btn svg { width: 13px; height: 13px; }
.sb-notif-list { flex: 1; overflow-y: auto; }
.sb-notif-item {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 12px 16px;
  border-bottom: 1px solid var(--sb-border);
  text-decoration: none; color: inherit;
  transition: background .1s ease;
}
.sb-notif-item:last-child { border-bottom: none; }
.sb-notif-item:hover { background: var(--sb-primary-soft); }
.sb-notif-icon {
  width: 32px; height: 32px; border-radius: 8px;
  background: var(--sb-primary-soft); color: #2563a8;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.sb-notif-icon svg { width: 15px; height: 15px; }
.sb-notif-type { font-size: 12.5px; font-weight: 700; color: var(--sb-text); }
.sb-notif-subject { font-size: 11.5px; color: var(--sb-muted); margin-top: 2px; word-break: break-word; }
.sb-notif-by, .sb-notif-time { font-size: 10.5px; color: #adb5bd; margin-top: 2px; }
.sb-notif-empty { text-align: center; padding: 32px 20px; color: var(--sb-muted); font-size: 13px; }
.sb-notif-empty svg { width: 28px; height: 28px; opacity: .35; display: block; margin: 0 auto 8px; }
</style>

<!-- Mobile menu toggle (only visible below xl breakpoint) -->
<button type="button" class="sb-mobile-toggle layout-menu-toggle d-xl-none" aria-label="Toggle menu">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
</button>

<!-- Notification pop-out -->
<div class="sb-notif-popover" id="sbNotifPanel">
  <div class="sb-notif-head">
    <h6>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      Notifications
    </h6>
    <button class="sb-notif-close-btn" onclick="closeSbNotif()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="sb-notif-list" id="sbNotifList">
    <div class="sb-notif-empty">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      No new notifications
    </div>
  </div>
</div>

<!-- Layout wrapper -->
<div class="layout-wrapper layout-content-navbar">
  <div class="layout-container">

    <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">

      <!-- Brand -->
      <div class="app-brand demo">
        <a href="../modules/profile.php" class="app-brand-link" style="display:flex;align-items:center;gap:9px;text-decoration:none;flex:1;min-width:0;">
          <img src="../assets/img/backgrounds/districtone.png" alt="Logo" width="34" style="border-radius:8px;flex-shrink:0;">
          <span class="sb-brand-text">District One</span>
        </a>
        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none" style="color:var(--sb-muted);flex-shrink:0;">
          <i class="bx bx-chevron-left bx-sm align-middle"></i>
        </a>
      </div>

      <!-- User block -->
      <div class="sb-user-block">
        <div class="sb-avatar-wrap">
          <img src="<?= htmlspecialchars($sb_profile) ?>" alt="Profile" class="sb-avatar">
          <span class="sb-online-dot"></span>
        </div>
        <div class="sb-user-info">
          <div class="sb-user-name"><?= htmlspecialchars($sb_name) ?></div>
          <div class="sb-user-role"><?= htmlspecialchars($sb_dept ?: $sb_role) ?></div>
        </div>
        <button class="sb-bell-btn" id="sbBellBtn" onclick="toggleSbNotif(event)" title="Notifications">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          <span class="sb-bell-badge" id="sbNotifBadge" <?= $notifTotalCount > 0 ? 'style="display:flex;"' : '' ?>><?= $notifTotalCount ?: '' ?></span>
        </button>
      </div>

      <div class="menu-inner-shadow"></div>

      <ul class="menu-inner py-1">

        <li class="menu-item <?= in_array($currentPage, ['profile.php','profileTeams.php','tell.php']) ? 'active' : '' ?>">
          <a href="../modules/profile.php" class="menu-link">
            <i class="menu-icon tf-icons bx bx-home-circle"></i><div>My Profile</div>
          </a>
        </li>

        <li class="menu-item <?= in_array($currentPage, ['accountSettings.php','personaldataSheet.php','payroll.php']) ? 'active open' : '' ?>">
          <a href="javascript:void(0);" class="menu-link menu-toggle">
            <i class="menu-icon tf-icons bx bx-dock-top"></i><div>Account</div>
          </a>
          <ul class="menu-sub">
            <li class="menu-item <?= $currentPage=='accountSettings.php'   ? 'active' : '' ?>"><a href="../modules/accountSettings.php"   class="menu-link"><div>Account Settings</div></a></li>
            <li class="menu-item <?= $currentPage=='personaldataSheet.php' ? 'active' : '' ?>"><a href="../modules/personaldataSheet.php" class="menu-link"><div>Personal Data Sheet</div></a></li>
            <li class="menu-item <?= $currentPage=='payroll.php'           ? 'active' : '' ?>"><a href="../modules/payroll.php"           class="menu-link"><div>My Payslip</div></a></li>
          </ul>
        </li>

        <li class="menu-item <?= $currentPage=='announcement.php'    ? 'active' : '' ?>"><a href="../modules/announcement.php"    class="menu-link"><i class="menu-icon tf-icons bx bx-bell"></i><div>Announcements</div></a></li>
        <li class="menu-item <?= $currentPage=='calendar.php'        ? 'active' : '' ?>"><a href="../modules/calendar.php"        class="menu-link"><i class="menu-icon tf-icons bx bx-calendar"></i><div>Calendar Activity</div></a></li>
        <li class="menu-item <?= $currentPage=='roomReservation.php' ? 'active' : '' ?>"><a href="../modules/roomReservation.php" class="menu-link"><i class="menu-icon tf-icons bx bx-calendar-event"></i><div>Room Reservation</div></a></li>
        <li class="menu-item <?= $currentPage=='fileSaln.php'        ? 'active' : '' ?>"><a href="../modules/fileSaln.php"        class="menu-link"><i class="menu-icon bx bx-detail"></i><div>File SALN</div></a></li>
        <li class="menu-item <?= $currentPage=='leaveRequest.php'    ? 'active' : '' ?>"><a href="../modules/leaveRequest.php"    class="menu-link"><i class="menu-icon bx bx-receipt"></i><div>Leave Request</div></a></li>
        <li class="menu-item <?= $currentPage=='noticeofMeeting.php' ? 'active' : '' ?>"><a href="../modules/noticeofMeeting.php" class="menu-link"><i class="menu-icon bx bx-group"></i><div>Notice of Meeting</div></a></li>
        <li class="menu-item <?= $currentPage=='serviceRequest.php'  ? 'active' : '' ?>"><a href="../modules/serviceRequest.php"  class="menu-link"><i class="menu-icon tf-icons bx bx-support"></i><div>IT Service Request</div></a></li>

        <!-- Admin Panel -->
        <li class="menu-item <?= in_array($currentPage, ['monitoring.php','ticketRequest.php','approveTicket.php','addAnnouncement.php','addEvents.php','approveRoom.php','accountManagement.php','payrollmng.php']) ? 'active open' : '' ?>">
          <a href="javascript:void(0);" class="menu-link menu-toggle">
            <i class="menu-icon tf-icons bx bx-shield-quarter"></i><div>Admin Panel</div>
          </a>
          <ul class="menu-sub">
            <li class="menu-item <?= $currentPage=='monitoring.php'       ? 'active' : '' ?>"><a href="../super_admin/monitoring.php"       class="menu-link"><div>Live Report</div></a></li>
            <li class="menu-item <?= $currentPage=='ticketRequest.php'    ? 'active' : '' ?>"><a href="../super_admin/ticketRequest.php"    class="menu-link"><div>Ticket Request</div></a></li>
            <li class="menu-item <?= $currentPage=='approveTicket.php'    ? 'active' : '' ?>"><a href="../super_admin/approveTicket.php"    class="menu-link"><div>Ticket Approval<?php if($approveCount>0) echo " <span class='sb-pill is-blue'>$approveCount</span>"; ?></div></a></li>
            <li class="menu-item <?= $currentPage=='addAnnouncement.php'  ? 'active' : '' ?>"><a href="../super_admin/addAnnouncement.php"  class="menu-link"><div>Add Announcements</div></a></li>
            <li class="menu-item <?= $currentPage=='addEvents.php'        ? 'active' : '' ?>"><a href="../super_admin/addEvents.php"        class="menu-link"><div>Add Calendar Activity</div></a></li>
            <li class="menu-item <?= $currentPage=='approveRoom.php'      ? 'active' : '' ?>"><a href="../super_admin/approveRoom.php"      class="menu-link"><div>Room Reservations<?php if($reservationCount>0) echo " <span class='sb-pill is-blue'>$reservationCount</span>"; ?></div></a></li>
            <li class="menu-item <?= $currentPage=='payrollmng.php'       ? 'active' : '' ?>"><a href="../super_admin/payrollmng.php"       class="menu-link"><div>Payroll Management</div></a></li>
            <li class="menu-item <?= $currentPage=='accountManagement.php'? 'active' : '' ?>"><a href="../super_admin/accountManagement.php" class="menu-link"><div>Account Management<?php if($unverifiedCount>0) echo " <span class='sb-pill is-red'>$unverifiedCount</span>"; ?></div></a></li>
          </ul>
        </li>

      </ul>

      <!-- Logout -->
      <div class="sb-footer">
        <a href="../logout.php" class="sb-logout">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
          Log Out
        </a>
      </div>

    </aside>
    <!-- /aside -->

    <div class="layout-page">
      <!-- content starts directly — no top navbar -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function positionSbNotif() {
  var btn = document.getElementById('sbBellBtn');
  var pop = document.getElementById('sbNotifPanel');
  var menu = document.getElementById('layout-menu');
  var rect = btn.getBoundingClientRect();
  var menuRect = menu.getBoundingClientRect();
  var popWidth = pop.offsetWidth || 320;
  var offsetX = 12; // gap between the sidebar's edge and the popup — raise to push further right

  var left = menuRect.right + offsetX; // starts just past the sidebar, never behind it
  if (left + popWidth > window.innerWidth - 8) left = window.innerWidth - popWidth - 8;
  if (left < 8) left = 8;

  var top = rect.bottom + 8;
  var maxHeight = parseFloat(getComputedStyle(pop).maxHeight) || 420;
  if (top + maxHeight > window.innerHeight - 8) top = Math.max(8, rect.top - maxHeight - 8);

  pop.style.left = left + 'px';
  pop.style.top = top + 'px';
}

function toggleSbNotif(e) {
  e.stopPropagation();
  var pop = document.getElementById('sbNotifPanel');
  if (pop.classList.contains('open')) { closeSbNotif(); return; }
  positionSbNotif();
  pop.classList.add('open');
  loadSbNotifs();
  document.addEventListener('click', sbNotifOutsideClick);
  window.addEventListener('resize', positionSbNotif);
  window.addEventListener('scroll', sbNotifOutsideScroll, true);
}

function closeSbNotif() {
  document.getElementById('sbNotifPanel').classList.remove('open');
  document.removeEventListener('click', sbNotifOutsideClick);
  window.removeEventListener('resize', positionSbNotif);
  window.removeEventListener('scroll', sbNotifOutsideScroll, true);
}

function sbNotifOutsideClick(e) {
  var pop = document.getElementById('sbNotifPanel');
  var btn = document.getElementById('sbBellBtn');
  if (!pop.contains(e.target) && !btn.contains(e.target)) closeSbNotif();
}

function sbNotifOutsideScroll(e) {
  var pop = document.getElementById('sbNotifPanel');
  // Ignore scrolling that happens inside the popup itself (e.g. the notif list)
  if (pop.contains(e.target)) return;
  closeSbNotif();
}

function loadSbNotifs() {
  $.ajax({
    url: "../super_admin/get_notifications.php",
    method: "GET", dataType: "json",
    success: function(data) {
      updateBadge(data.count);
      const list = document.getElementById('sbNotifList');
      if (!data.items || !data.items.length) {
        list.innerHTML = '<div class="sb-notif-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>No new notifications</div>';
        if (document.getElementById('sbNotifPanel').classList.contains('open')) positionSbNotif();
        return;
      }
      list.innerHTML = data.items.map(function(item) {
        const isTicket = item.type === "Ticket Request";
        const icon = isTicket
          ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 12h6M9 16h6M9 8h6M5 4h14v16l-3-2-3 2-3-2-3 2V4z"/></svg>'
          : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>';
        return '<a href="'+item.link+'" class="sb-notif-item"><div class="sb-notif-icon">'+icon+'</div><div><div class="sb-notif-type">'+item.type+'</div><div class="sb-notif-subject">'+item.subject+'</div><div class="sb-notif-by">By: '+item.firstname+' '+item.lastname+'</div><div class="sb-notif-time">'+item.time+'</div></div></a>';
      }).join('');
      if (document.getElementById('sbNotifPanel').classList.contains('open')) positionSbNotif();
    },
    error: function() {}
  });
}

function updateBadge(count) {
  var b = document.getElementById('sbNotifBadge');
  if (!b) return;
  if (count > 0) { b.textContent = count; b.style.display = 'flex'; }
  else { b.style.display = 'none'; }
}

// Refresh badge count every 30s without opening the panel
setInterval(function() {
  $.ajax({ url: "../super_admin/get_notifications.php", method: "GET", dataType: "json",
    success: function(d) { updateBadge(d.count); }, error: function(){} });
}, 30000);
</script>