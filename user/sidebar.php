<?php
require '../vendor/autoload.php';
include '../db.php';
require 'login_verification.php';

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
</style>

<!-- Mobile menu toggle (only visible below xl breakpoint) -->
<button type="button" class="sb-mobile-toggle layout-menu-toggle d-xl-none" aria-label="Toggle menu">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
</button>

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