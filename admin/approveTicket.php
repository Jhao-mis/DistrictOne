<?php
session_start();
require '../vendor/autoload.php';
include '../db.php';
require 'login_verification.php';

$username = $_SESSION['username'];

// ✅ Fetch logged-in user info
$query = $conn->prepare("SELECT id, firstname, lastname, department FROM users WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->bind_result($user_id, $firstname, $lastname, $department);
$query->fetch();
$query->close();

if (!$user_id) {
  die("❌ Error: User not found.");
}

$performed_by = "$firstname $lastname";

// ✅ APPROVE Ticket
if (isset($_POST['approve_ticket_id'])) {
  $ticket_id = intval($_POST['approve_ticket_id']);

  // Only mark admin_approved = 1 (status stays 'Pending')
  $update_sql = "UPDATE tickets SET admin_approved = 1 WHERE id = ? AND user_id IN (SELECT id FROM users WHERE department = ?)";
  $stmt = $conn->prepare($update_sql);
  $stmt->bind_param("is", $ticket_id, $department);

  if ($stmt->execute()) {
    // Log history
    $action = "Approved by $performed_by";
    $history_sql = "INSERT INTO ticket_history (ticket_id, action, action_by) VALUES (?, ?, ?)";
    $hstmt = $conn->prepare($history_sql);
    $hstmt->bind_param("isi", $ticket_id, $action, $user_id);
    $hstmt->execute();
    $hstmt->close();

    $msg = "approved";
  } else {
    $error = "❌ Error approving ticket: " . $conn->error;
  }

  $stmt->close();
}

if (isset($_POST['reject_ticket_id'])) {
  $ticket_id = intval($_POST['reject_ticket_id']);

  $update_sql = "UPDATE tickets t
                   JOIN users u ON t.user_id = u.id
                   SET t.status = 'Rejected', t.admin_approved = 0
                   WHERE t.id = ? AND u.department = ?";
  $stmt = $conn->prepare($update_sql);
  $stmt->bind_param("is", $ticket_id, $department);

  if ($stmt->execute()) {
    $action = "Rejected by $performed_by";
    $hstmt = $conn->prepare("INSERT INTO ticket_history (ticket_id, action, action_by) VALUES (?, ?, ?)");
    $hstmt->bind_param("isi", $ticket_id, $action, $user_id);
    $hstmt->execute();
    $hstmt->close();

    $msg = "rejected";
  } else {
    $error = "❌ Error rejecting ticket: " . $conn->error;
  }

  $stmt->close();
}


// ✅ Fetch only pending tickets from user's department
$sql = "SELECT t.id, t.subject, t.description, t.status, t.created_at,
               u.firstname, u.lastname, u.department
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        WHERE u.department = ? AND t.status = 'Pending' AND t.admin_approved = 0
        ORDER BY t.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $department);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/"
  data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>IT Service Request</title>
  <link rel="icon" type="image/x-icon" href="../assets/img/favicon/districtone.png" />
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
  <link rel="stylesheet" href="../assets/vendor/css/core.css" />
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />
  <link rel="stylesheet" href="../css/admin.css" />
  <link rel="stylesheet" href="./css/profile.css">
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <style>
    :root {
      --tk-bg: #f7f8fa;
      --tk-surface: #ffffff;
      --tk-border: #e8eaee;
      --tk-text: #1f2430;
      --tk-text-muted: #767e8c;
      --tk-primary: #7cb9ff;
      --tk-primary-soft: #eaf3ff;
      --tk-pending: #b8860b;
      --tk-pending-bg: #fdf3da;
      --tk-approve: #1f9d55;
      --tk-approve-bg: #e4f7ea;
      --tk-reject: #c0392b;
      --tk-reject-bg: #fbe9e7;
      --tk-radius: 12px;
      --tk-shadow: 0 1px 2px rgba(20,20,43,.04), 0 8px 24px -12px rgba(20,20,43,.10);
    }

    .tk-wrap { font-family: inherit; color: var(--tk-text); }

    /* ── Header ────────────────────────────────────────────────────── */
    .tk-header-card {
      background: linear-gradient(135deg, var(--tk-primary) 0%, #4e96f0 100%);
      border-radius: var(--tk-radius);
      padding: 22px 26px;
      color: #fff;
      margin-bottom: 18px;
      box-shadow: var(--tk-shadow);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 14px;
    }
    .tk-header-eyebrow {
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .6px;
      text-transform: uppercase;
      opacity: .8;
      margin-bottom: 4px;
    }
    .tk-header-title { font-size: 20px; font-weight: 700; }
    .tk-header-sub { font-size: 13px; opacity: .85; margin-top: 4px; }
    .tk-header-count {
      display: flex;
      align-items: center;
      gap: 10px;
      background: rgba(255,255,255,.16);
      padding: 10px 18px;
      border-radius: 12px;
    }
    .tk-header-count .num { font-size: 26px; font-weight: 800; line-height: 1; }
    .tk-header-count .label { font-size: 11px; text-transform: uppercase; letter-spacing: .4px; opacity: .85; }

    /* ── Alerts ────────────────────────────────────────────────────── */
    .tk-alert {
      display: flex;
      align-items: center;
      gap: 10px;
      border-radius: 10px;
      padding: 12px 16px;
      font-size: 13.5px;
      font-weight: 600;
      margin-bottom: 16px;
    }
    .tk-alert.is-success { background: var(--tk-approve-bg); color: var(--tk-approve); }
    .tk-alert.is-error { background: var(--tk-reject-bg); color: var(--tk-reject); }
    .tk-alert svg { width: 17px; height: 17px; flex-shrink: 0; }

    /* ── Table card ────────────────────────────────────────────────── */
    .tk-card {
      background: var(--tk-surface);
      border: 1px solid var(--tk-border);
      border-radius: var(--tk-radius);
      box-shadow: var(--tk-shadow);
      overflow: hidden;
    }
    .tk-card-head {
      padding: 16px 20px;
      border-bottom: 1px solid var(--tk-border);
      font-weight: 700;
      font-size: 15px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .tk-card-head svg { width: 16px; height: 16px; color: var(--tk-text-muted); }

    table.tk-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    table.tk-table thead th {
      background: var(--tk-bg);
      text-align: left;
      font-size: 11.5px;
      text-transform: uppercase;
      letter-spacing: .5px;
      color: var(--tk-text-muted);
      font-weight: 700;
      padding: 11px 20px;
      border-bottom: 1px solid var(--tk-border);
    }
    table.tk-table tbody td {
      padding: 13px 20px;
      border-bottom: 1px solid var(--tk-border);
      vertical-align: middle;
      color: var(--tk-text);
    }
    table.tk-table tbody tr { cursor: pointer; transition: background .1s ease; }
    table.tk-table tbody tr:hover { background: #fafbfd; }
    table.tk-table tbody tr:last-child td { border-bottom: none; }

    .tk-id { font-weight: 700; color: var(--tk-text-muted); font-variant-numeric: tabular-nums; }
    .tk-subject { font-weight: 600; }

    .tk-user { display: flex; align-items: center; gap: 9px; }
    .tk-avatar {
      width: 28px; height: 28px; border-radius: 50%;
      background: var(--tk-primary-soft); color: #2563a8;
      display: flex; align-items: center; justify-content: center;
      font-size: 11px; font-weight: 700; flex-shrink: 0;
    }
    .tk-user-name { font-weight: 600; }
    .tk-user-dept { font-size: 11.5px; color: var(--tk-text-muted); }

    .tk-badge {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 4px 10px; border-radius: 999px;
      font-size: 12px; font-weight: 700;
      background: var(--tk-pending-bg); color: var(--tk-pending);
    }
    .tk-badge .dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

    .tk-time { color: var(--tk-text-muted); font-size: 12.5px; }

    .tk-actions { display: flex; align-items: center; justify-content: center; gap: 8px; }
    .action-form { display: inline-block; margin: 0; }
    .tk-action-btn {
      border: 1.5px solid var(--tk-border);
      background: var(--tk-surface);
      width: 32px; height: 32px;
      border-radius: 9px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all .12s ease;
    }
    .tk-action-btn svg { width: 16px; height: 16px; }
    .tk-action-btn.is-approve { color: var(--tk-approve); }
    .tk-action-btn.is-approve:hover { background: var(--tk-approve-bg); border-color: var(--tk-approve); }
    .tk-action-btn.is-reject { color: var(--tk-reject); }
    .tk-action-btn.is-reject:hover { background: var(--tk-reject-bg); border-color: var(--tk-reject); }

    .tk-empty {
      padding: 60px 20px;
      text-align: center;
      color: var(--tk-text-muted);
    }
    .tk-empty svg { width: 42px; height: 42px; margin-bottom: 12px; opacity: .35; color: var(--tk-approve); }
    .tk-empty p { margin: 0; font-size: 14.5px; font-weight: 600; color: var(--tk-text); }
    .tk-empty span { font-size: 12.5px; }

    /* ── Modal ─────────────────────────────────────────────────────── */
    #ticketModal .modal-content {
      border: none;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 24px 60px -20px rgba(20,20,43,.35);
    }
    #ticketModal .tk-view-header {
      background: linear-gradient(135deg, var(--tk-primary) 0%, #4e96f0 100%);
      padding: 22px 26px;
      color: #fff;
      position: relative;
    }
    #ticketModal .tk-modal-eyebrow {
      font-size: 11.5px;
      font-weight: 700;
      letter-spacing: .6px;
      text-transform: uppercase;
      opacity: .8;
      margin-bottom: 3px;
    }
    #ticketModal .tk-view-header h5 { font-size: 19px; font-weight: 700; margin: 0; color: #fff; }
    #ticketModal .tk-view-header .btn-close { position: absolute; top: 18px; right: 20px; opacity: .9; }
    #ticketModal .tk-view-status {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 5px 12px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 700;
      background: rgba(255,255,255,.18);
      margin-top: 10px;
    }
    #ticketModal .tk-view-status .dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    #ticketModal .modal-body { padding: 24px 26px; background: var(--tk-surface); }
    #ticketModal .tk-view-subject {
      font-size: 16px;
      font-weight: 700;
      color: var(--tk-text);
      margin-bottom: 18px;
      padding-bottom: 16px;
      border-bottom: 1px solid var(--tk-border);
    }
    #ticketModal .tk-view-subject .tk-view-label {
      font-size: 10.5px;
      text-transform: uppercase;
      letter-spacing: .5px;
      font-weight: 700;
      color: var(--tk-text-muted);
      margin-bottom: 5px;
    }
    #ticketModal .tk-view-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 14px;
      margin-bottom: 20px;
    }
    #ticketModal .tk-view-item {
      background: var(--tk-bg);
      border: 1px solid var(--tk-border);
      border-radius: 10px;
      padding: 11px 13px;
    }
    #ticketModal .tk-view-label {
      display: flex; align-items: center; gap: 5px;
      font-size: 10.5px; text-transform: uppercase; letter-spacing: .5px;
      font-weight: 700; color: var(--tk-text-muted); margin-bottom: 4px;
    }
    #ticketModal .tk-view-label svg { width: 12px; height: 12px; flex-shrink: 0; }
    #ticketModal .tk-view-value { font-size: 13.5px; font-weight: 600; color: var(--tk-text); word-break: break-word; }
    #ticketModal .tk-view-desc-label {
      display: flex; align-items: center; gap: 6px;
      font-size: 13px; font-weight: 700; color: var(--tk-text); margin-bottom: 8px;
    }
    #ticketModal .tk-view-desc-label svg { width: 14px; height: 14px; color: var(--tk-text-muted); }
    #ticketModal .tk-view-desc {
      background: var(--tk-bg);
      border: 1px solid var(--tk-border);
      border-radius: 10px;
      padding: 14px;
      font-size: 13.5px;
      line-height: 1.6;
      color: var(--tk-text);
      white-space: pre-wrap;
      min-height: 70px;
    }
    #ticketModal .modal-footer {
      border-top: 1px solid var(--tk-border);
      padding: 16px 26px;
      background: var(--tk-bg);
      display: flex;
      justify-content: flex-end;
      gap: 10px;
    }
    #ticketModal .tk-btn-cancel {
      background: var(--tk-surface);
      border: 1.5px solid var(--tk-border);
      color: var(--tk-text);
      font-weight: 600;
      font-size: 13.5px;
      padding: 10px 18px;
      border-radius: 9px;
      cursor: pointer;
      transition: border-color .12s ease, background .12s ease;
    }
    #ticketModal .tk-btn-cancel:hover { border-color: #c7cbd4; background: #f5f6f8; }
    #ticketModal .tk-btn-approve, #ticketModal .tk-btn-reject {
      border: none;
      color: #fff;
      font-weight: 600;
      font-size: 13.5px;
      padding: 10px 18px;
      border-radius: 9px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 7px;
      transition: background .12s ease, transform .12s ease;
    }
    #ticketModal .tk-btn-approve { background: var(--tk-approve); box-shadow: 0 4px 10px -4px rgba(31,157,85,.5); }
    #ticketModal .tk-btn-approve:hover { background: #198347; transform: translateY(-1px); }
    #ticketModal .tk-btn-reject { background: var(--tk-reject); box-shadow: 0 4px 10px -4px rgba(192,57,43,.5); }
    #ticketModal .tk-btn-reject:hover { background: #a5301f; transform: translateY(-1px); }
    #ticketModal .tk-btn-approve svg, #ticketModal .tk-btn-reject svg { width: 14px; height: 14px; }

    /* Ensure sidebar stays below modals and SweetAlerts */
    .sidebar,
    .layout-menu,
    #layout-menu {
      z-index: 1030 !important;
    }
    .swal2-container {
      z-index: 20000 !important;
    }
  </style>
</head>

<body>
  <?php $role = $_SESSION['role'];

    switch ($role) {
        case 'User':
            include '../user/sidebar.php';
            break;

        case 'mis':
            include '../mis/sidebar.php';
            break;

        case 'Admin':
            include '../admin/sidebar.php';
            break;

        case 'Super Admin':
            include '../super_admin/sidebar.php';
            break;

        default:
            echo "<p>Unauthorized role.</p>";
            exit;
    }
    ?>
  <div class="content-wrapper">
    <div class="container-xxl flex-grow-1 container-p-y">
      <div class="tk-wrap">

        <!-- Header -->
        <!-- <div class="tk-header-card">
          <div>
            <div class="tk-header-eyebrow">Approval queue</div>
            <div class="tk-header-title">Tickets pending approval</div>
            <div class="tk-header-sub">Review newly submitted requests before they move into the support pipeline.</div>
          </div>
          <div class="tk-header-count">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6M9 16h6M9 8h6M5 4h14v16l-3-2-3 2-3-2-3 2V4z"/></svg>
            <div>
              <div class="num"><?= $pending_count ?></div>
              <div class="label">Awaiting review</div>
            </div>
          </div>
        </div> -->

        <?php if (isset($_GET['msg'])): ?>
          <div class="tk-alert is-success">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
            <?= $_GET['msg'] === 'approved' ? 'Ticket approved successfully.' : ($_GET['msg'] === 'rejected' ? 'Ticket rejected successfully.' : htmlspecialchars($_GET['msg'])); ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
          <div class="tk-alert is-error">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            <?= htmlspecialchars($error); ?>
          </div>
        <?php endif; ?>

        <!-- Table -->
        <div class="tk-card">
          <div class="tk-card-head">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg>
            Pending tickets
          </div>

          <div class="table-responsive text-nowrap">
            <table class="tk-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Submitted By</th>
                  <th>Department</th>
                  <th>Subject</th>
                  <th>Status</th>
                  <th>Created At</th>
                  <th class="text-center">Action</th>
                </tr>
              </thead>

              <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                  <?php while ($row = $result->fetch_assoc()):
                    $fullName = trim($row['firstname'] . ' ' . $row['lastname']);
                    $initials = strtoupper(substr($row['firstname'], 0, 1) . substr($row['lastname'], 0, 1));
                  ?>
                    <tr data-ticket-id="<?= $row['id']; ?>"
                      data-ticket-subject="<?= htmlspecialchars($row['subject']); ?>"
                      data-ticket-description="<?= htmlspecialchars($row['description']); ?>"
                      data-ticket-status="<?= htmlspecialchars($row['status']); ?>"
                      data-ticket-department="<?= htmlspecialchars($row['department']); ?>"
                      data-ticket-user="<?= htmlspecialchars($fullName); ?>"
                      data-ticket-date="<?= htmlspecialchars(date("M d, Y h:i A", strtotime($row['created_at']))); ?>"
                      title="Click to view details">
                      <td class="tk-id">#<?= htmlspecialchars($row['id']); ?></td>
                      <td>
                        <div class="tk-user">
                          <div class="tk-avatar"><?= htmlspecialchars($initials) ?></div>
                          <div class="tk-user-name"><?= htmlspecialchars($fullName); ?></div>
                        </div>
                      </td>
                      <td><?= htmlspecialchars($row['department']); ?></td>
                      <td class="tk-subject">
                        <?= htmlspecialchars(strlen($row['subject']) > 30 ? substr($row['subject'], 0, 30) . '…' : $row['subject']); ?>
                      </td>
                      <td><span class="tk-badge"><span class="dot"></span><?= htmlspecialchars($row['status']); ?></span></td>
                      <td class="tk-time"><?= htmlspecialchars(date("M d, Y h:i A", strtotime($row['created_at']))); ?></td>
                      <td class="text-center" onclick="event.stopPropagation()">
                        <div class="tk-actions">
                          <form class="action-form" method="POST" data-action="approve">
                            <input type="hidden" name="approve_ticket_id" value="<?= htmlspecialchars($row['id']); ?>">
                            <button type="submit" class="tk-action-btn is-approve" title="Approve">
                              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                            </button>
                          </form>
                          <form class="action-form" method="POST" data-action="reject">
                            <input type="hidden" name="reject_ticket_id" value="<?= htmlspecialchars($row['id']); ?>">
                            <button type="submit" class="tk-action-btn is-reject" title="Reject">
                              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                            </button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="7">
                      <div class="tk-empty">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg>
                        <p>No pending tickets found</p>
                        <span>You're all caught up — new submissions will show up here.</span>
                      </div>
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>

            </table>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- Ticket Details Modal -->
  <div class="modal fade" id="ticketModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="tk-view-header">
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          <div class="tk-modal-eyebrow">Ticket <span id="modalTicketId"></span></div>
          <h5 class="modal-title">Ticket details</h5>
          <span class="tk-view-status">
            <span class="dot"></span><span id="modalTicketStatus"></span>
          </span>
        </div>

        <div class="modal-body">
          <div class="tk-view-subject">
            <div class="tk-view-label">Subject</div>
            <div id="modalTicketSubject"></div>
          </div>

          <div class="tk-view-grid">
            <div class="tk-view-item">
              <div class="tk-view-label">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Submitted by
              </div>
              <div class="tk-view-value" id="modalTicketUser"></div>
            </div>
            <div class="tk-view-item">
              <div class="tk-view-label">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/></svg>
                Department
              </div>
              <div class="tk-view-value" id="modalTicketDept"></div>
            </div>
            <div class="tk-view-item">
              <div class="tk-view-label">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                Created at
              </div>
              <div class="tk-view-value" id="modalTicketDate"></div>
            </div>
          </div>

          <div class="tk-view-desc-label">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
            Description
          </div>
          <div id="modalTicketDescription" class="tk-view-desc"></div>
        </div>

        <div class="modal-footer">
          <button type="button" class="tk-btn-cancel" data-bs-dismiss="modal">Close</button>
          <button type="button" class="tk-btn-reject" id="modalRejectBtn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            Reject
          </button>
          <button type="button" class="tk-btn-approve" id="modalApproveBtn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
            Approve
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- Core JS -->
  <script src="../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../assets/vendor/js/bootstrap.js"></script>
  <script src="../assets/vendor/js/menu.js"></script>
  <script src="../assets/js/main.js"></script>

  <!-- SWEET ALERT SCRIPT HANDLER -->
  <script>
    $(document).ready(function () {
      // Prevent row click when pressing buttons inside it
      $('.action-form button').on('click', function (e) {
        e.stopPropagation();
      });

      // Handle SweetAlert confirmations
      $('.action-form').on('submit', function (e) {
        e.preventDefault(); // Prevent immediate submission
        e.stopPropagation(); // Prevent triggering modal

        const form = this;
        const actionType = $(this).data('action');
        const isApprove = actionType === 'approve';
        const title = isApprove ? 'Approve Ticket?' : 'Reject Ticket?';
        const text = isApprove
          ? 'This will mark the ticket as APPROVED.'
          : 'This will mark the ticket as REJECTED.';
        const icon = isApprove ? 'success' : 'warning';
        const confirmButton = isApprove ? 'Yes, Approve' : 'Yes, Reject';
        const confirmColor = isApprove ? '#1f9d55' : '#c0392b';

        Swal.fire({
          title: title,
          text: text,
          icon: icon,
          showCancelButton: true,
          confirmButtonText: confirmButton,
          confirmButtonColor: confirmColor,
          cancelButtonText: 'Cancel',
          reverseButtons: true,
        }).then((result) => {
          if (result.isConfirmed) {
            form.submit(); // Proceed with the PHP form
          }
        });
      });
    });
  </script>

  <!-- SCRIPT FOR MODAL VIEW TICKET -->
  <script>
    $(document).ready(function () {
      let currentTicketId = null;

      $('tr[data-ticket-id]').click(function () {
        const row = $(this);
        currentTicketId = row.data('ticket-id');

        $('#modalTicketId').text(currentTicketId);
        $('#modalTicketSubject').text(row.data('ticket-subject'));
        $('#modalTicketDescription').text(row.data('ticket-description'));
        $('#modalTicketStatus').text(row.data('ticket-status'));
        $('#modalTicketDept').text(row.data('ticket-department'));
        $('#modalTicketUser').text(row.data('ticket-user'));
        $('#modalTicketDate').text(row.data('ticket-date'));

        $('#ticketModal').modal('show');
      });

      // Approve / Reject directly from the modal footer
      $('#modalApproveBtn').on('click', function () {
        if (!currentTicketId) return;
        const $form = $('tr[data-ticket-id="' + currentTicketId + '"] form[data-action="approve"]');
        $('#ticketModal').modal('hide');
        setTimeout(() => $form.trigger('submit'), 200);
      });

      $('#modalRejectBtn').on('click', function () {
        if (!currentTicketId) return;
        const $form = $('tr[data-ticket-id="' + currentTicketId + '"] form[data-action="reject"]');
        $('#ticketModal').modal('hide');
        setTimeout(() => $form.trigger('submit'), 200);
      });
    });
  </script>

</body>

</html>