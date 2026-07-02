<?php
require 'login_verification.php';
require '../db.php'; // Include database connection

$conn = new mysqli($host, $user, $pass, $db);
// Check connection
if ($conn->connect_error) {
  die("Database Connection Failed: " . $conn->connect_error);
}
// Fetch the current user details from the database
$username = $_SESSION['username'];
$query = $conn->prepare("SELECT id, firstname, middlename, lastname, email, department, profile_picture FROM users WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->store_result();
$query->bind_result($user_id, $firstname, $middlename, $lastname, $email, $department, $profile_picture);
$query->fetch();
$query->close();

date_default_timezone_set('Asia/Manila'); // Set timezone to your local
$now = date('Y-m-d H:i:s');
$updateActivity = $conn->prepare("UPDATE users SET last_activity = ? WHERE username = ?");
$updateActivity->bind_param("ss", $now, $username);
$updateActivity->execute();
$updateActivity->close();

$client_department = $department;

if ($client_department == "All Departments") {
  $sql = "SELECT a.id, a.title, a.message, a.file_path, a.department, a.user_id, a.created_at, 
                   u.firstname, u.middlename, u.lastname 
            FROM announcements a 
            JOIN users u ON a.user_id = u.id 
            ORDER BY a.created_at DESC";
  $stmt = $conn->prepare($sql);
} else {
  $sql = "SELECT a.id, a.title, a.message, a.file_path, a.department, a.user_id, a.created_at, 
                   u.firstname, u.middlename, u.lastname 
            FROM announcements a 
            JOIN users u ON a.user_id = u.id 
            WHERE a.department = ? OR a.department = 'All Departments'
            ORDER BY a.created_at DESC";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("s", $client_department);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/"
  data-template="vertical-menu-template-free">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Announcements</title>

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="../assets/img/favicon/districtone.png" />

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />

  <!-- Icons -->
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />

  <!-- Core CSS -->
  <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />
  <link rel="stylesheet" href="./css/announcement.css">

  <!-- Vendors CSS -->
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>

  <style>
    :root {
      --tk-bg: #f7f8fa;
      --tk-surface: #ffffff;
      --tk-border: #e8eaee;
      --tk-text: #1f2430;
      --tk-text-muted: #767e8c;
      --tk-primary: #7cb9ff;
      --tk-primary-soft: #eaf3ff;
      --tk-primary-dark: #4e96f0;
      --tk-radius: 12px;
      --tk-shadow: 0 1px 2px rgba(20,20,43,.04), 0 8px 24px -12px rgba(20,20,43,.10);
    }

    .ann-wrap { font-family: inherit; color: var(--tk-text); }

    /* ── Page header ───────────────────────────────────────────── */
    .ann-page-header {
      background: linear-gradient(135deg, var(--tk-primary) 0%, var(--tk-primary-dark) 100%);
      border-radius: var(--tk-radius);
      padding: 22px 26px;
      color: #fff;
      margin-bottom: 22px;
      box-shadow: var(--tk-shadow);
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 14px;
    }
    .ann-page-header .eyebrow {
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .6px;
      text-transform: uppercase;
      opacity: .8;
      margin-bottom: 3px;
    }
    .ann-page-header h4 { font-size: 19px; font-weight: 700; margin: 0; color: #fff; }
    .ann-page-header p { font-size: 13px; opacity: .85; margin: 4px 0 0; }

    /* ── Search ────────────────────────────────────────────────── */
    .ann-search { position: relative; margin-bottom: 22px; }
    .ann-search svg {
      position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
      width: 17px; height: 17px; color: var(--tk-text-muted); pointer-events: none;
    }
    .ann-search input {
      width: 100%;
      padding: 12px 16px 12px 46px;
      border: 1.5px solid var(--tk-border);
      border-radius: 999px;
      font-size: 14px;
      background: var(--tk-bg);
      outline: none;
      transition: border-color .12s ease, box-shadow .12s ease, background .12s ease;
    }
    .ann-search input:focus {
      border-color: var(--tk-primary);
      background: var(--tk-surface);
      box-shadow: 0 0 0 4px var(--tk-primary-soft);
    }

    /* ── Announcement card ─────────────────────────────────────── */
    .ann-card {
      background: var(--tk-surface);
      border: 1px solid var(--tk-border);
      border-radius: var(--tk-radius);
      box-shadow: var(--tk-shadow);
      padding: 22px 24px;
      margin-bottom: 16px;
      transition: border-color .12s ease, transform .12s ease, box-shadow .12s ease;
    }
    .ann-card:hover {
      border-color: var(--tk-primary);
      transform: translateY(-1px);
      box-shadow: 0 6px 20px -8px rgba(20,20,43,.15);
    }
    .ann-card-dept {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      background: var(--tk-primary-soft);
      color: #2563a8;
      font-size: 11px;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: 999px;
      margin-bottom: 10px;
    }
    .ann-card-dept svg { width: 11px; height: 11px; }
    .ann-card h3 { font-size: 17px; font-weight: 700; color: var(--tk-text); margin-bottom: 10px; }
    .ann-card-body { font-size: 14px; line-height: 1.7; color: var(--tk-text); margin-bottom: 14px; }
    .ann-card-meta {
      display: flex;
      align-items: center;
      gap: 14px;
      flex-wrap: wrap;
      font-size: 12px;
      color: var(--tk-text-muted);
      border-top: 1px dashed var(--tk-border);
      padding-top: 12px;
      margin-top: 4px;
    }
    .ann-card-meta span { display: flex; align-items: center; gap: 5px; }
    .ann-card-meta svg { width: 13px; height: 13px; flex-shrink: 0; }

    /* File attachment */
    .ann-attach { margin-top: 14px; }
    .ann-attach img { border-radius: 10px; border: 1px solid var(--tk-border); max-width: 100%; }
    .ann-btn-pdf {
      display: inline-flex; align-items: center; gap: 6px;
      background: var(--tk-primary-soft); color: #2563a8;
      border: 1.5px solid var(--tk-primary); font-weight: 600;
      font-size: 13px; padding: 7px 14px; border-radius: 8px;
      cursor: pointer; text-decoration: none; margin-right: 8px;
      transition: background .12s ease;
    }
    .ann-btn-pdf:hover { background: var(--tk-primary); color: #fff; }
    .ann-btn-pdf svg { width: 15px; height: 15px; }
    .ann-btn-download {
      display: inline-flex; align-items: center; gap: 6px;
      background: var(--tk-bg); color: var(--tk-text-muted);
      border: 1.5px solid var(--tk-border); font-weight: 600;
      font-size: 13px; padding: 7px 14px; border-radius: 8px;
      cursor: pointer; text-decoration: none;
      transition: border-color .12s ease, color .12s ease;
    }
    .ann-btn-download:hover { border-color: var(--tk-primary); color: var(--tk-primary); }
    .ann-btn-download svg { width: 15px; height: 15px; }

    /* ── Recent posts sidebar ──────────────────────────────────── */
    .ann-recent-card {
      background: var(--tk-surface);
      border: 1px solid var(--tk-border);
      border-radius: var(--tk-radius);
      box-shadow: var(--tk-shadow);
      overflow: hidden;
      position: sticky;
      top: 80px;
    }
    .ann-recent-head {
      padding: 15px 18px;
      border-bottom: 1px solid var(--tk-border);
      font-size: 13px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .4px;
      color: var(--tk-text-muted);
      display: flex;
      align-items: center;
      gap: 7px;
    }
    .ann-recent-head svg { width: 14px; height: 14px; color: var(--tk-primary); }
    .ann-recent-item {
      padding: 12px 18px;
      border-bottom: 1px solid var(--tk-border);
      transition: background .1s ease;
    }
    .ann-recent-item:last-child { border-bottom: none; }
    .ann-recent-item:hover { background: var(--tk-primary-soft); }
    .ann-recent-dept {
      display: inline-block;
      font-size: 10.5px;
      font-weight: 700;
      color: #2563a8;
      background: var(--tk-primary-soft);
      padding: 2px 8px;
      border-radius: 999px;
      margin-bottom: 5px;
    }
    .ann-recent-title {
      display: block;
      font-weight: 700;
      font-size: 13px;
      color: var(--tk-text);
      text-decoration: none;
      line-height: 1.4;
    }
    .ann-recent-title:hover { color: var(--tk-primary); }
    .ann-recent-date { font-size: 11.5px; color: var(--tk-text-muted); margin-top: 3px; }
    .ann-recent-empty { padding: 24px 18px; text-align: center; font-size: 13px; color: var(--tk-text-muted); }

    /* ── Pagination ────────────────────────────────────────────── */
    .ann-pagination { display: flex; justify-content: center; gap: 6px; flex-wrap: wrap; }
    .ann-page-btn {
      border: 1.5px solid var(--tk-border);
      background: var(--tk-surface);
      color: var(--tk-text-muted);
      font-size: 13px;
      font-weight: 600;
      width: 34px; height: 34px;
      border-radius: 9px;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer;
      transition: all .12s ease;
    }
    .ann-page-btn:hover { border-color: var(--tk-primary); color: var(--tk-primary); }
    .ann-page-btn.active { background: var(--tk-primary); border-color: var(--tk-primary); color: #fff; }
    .ann-page-btn.disabled { opacity: .4; cursor: not-allowed; }
    .ann-page-btn.ellipsis { cursor: default; border-color: transparent; background: transparent; }

    /* ── Empty state ───────────────────────────────────────────── */
    .ann-empty {
      text-align: center; padding: 60px 20px; color: var(--tk-text-muted);
    }
    .ann-empty svg { width: 42px; height: 42px; opacity: .35; margin-bottom: 12px; }
    .ann-empty p { font-size: 14.5px; font-weight: 600; color: var(--tk-text); margin: 0; }
    .ann-empty span { font-size: 12.5px; }

    /* ── PDF / Modal ───────────────────────────────────────────── */
    #pdfModal .modal-content { border: none; border-radius: 16px; overflow: hidden; }
    #pdfModal .modal-header {
      background: linear-gradient(135deg, var(--tk-primary) 0%, var(--tk-primary-dark) 100%);
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
      <div class="ann-wrap">
      
        <div class="row g-4">

          <!-- LEFT: FEED -->
          <div class="col-md-8">

            <!-- Search -->
            <div class="ann-search">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
              <input type="text" id="searchInput" placeholder="Search announcements…">
            </div>

            <!-- Pagination top -->
            <div class="ann-pagination mb-4" id="paginationTop"></div>

            <!-- Posts Feed -->
            <div id="announcementsFeed">
              <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()):
                  $file_path = !empty($row['file_path']) ? '../uploads/' . basename($row['file_path']) : '';
                  $file_ext = $file_path ? strtolower(pathinfo($file_path, PATHINFO_EXTENSION)) : '';
                  $poster = trim($row['firstname'] . ' ' . $row['middlename'] . ' ' . $row['lastname']);
                ?>
                  <div class="ann-card announcement-card">
                    <span class="ann-card-dept">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/></svg>
                      <?php echo htmlspecialchars($row['department']); ?>
                    </span>
                    <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                    <div class="ann-card-body"><?php echo nl2br(htmlspecialchars($row['message'])); ?></div>

                    <!-- File attachment -->
                    <?php if ($file_path && file_exists($file_path)): ?>
                      <div class="ann-attach">
                        <?php if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                          <img src="<?php echo htmlspecialchars($file_path); ?>" alt="Attachment">
                        <?php elseif ($file_ext === 'pdf'): ?>
                          <button class="ann-btn-pdf view-pdf-btn" data-pdf="<?php echo htmlspecialchars($file_path); ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            View PDF
                          </button>
                          <a class="ann-btn-download" href="<?php echo htmlspecialchars($file_path); ?>" download>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Download PDF
                          </a>
                        <?php else: ?>
                          <a class="ann-btn-download" href="<?php echo htmlspecialchars($file_path); ?>" download>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Download File
                          </a>
                        <?php endif; ?>
                      </div>
                    <?php elseif ($file_path): ?>
                      <p class="text-danger small mt-2">Attached file not found.</p>
                    <?php endif; ?>

                    <div class="ann-card-meta">
                      <span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <?php echo htmlspecialchars($poster); ?>
                      </span>
                      <span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        <?php echo date("F j, Y g:i A", strtotime($row['created_at'])); ?>
                      </span>
                    </div>
                  </div>
                <?php endwhile; ?>
              <?php else: ?>
                <div class="ann-empty">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 11l18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
                  <p>No announcements yet</p>
                  <span>Check back later for updates from your department.</span>
                </div>
              <?php endif; ?>
            </div>

            <!-- Pagination bottom -->
            <div class="ann-pagination mt-4" id="paginationBottom"></div>
          </div>

          <!-- RIGHT: RECENT POSTS -->
          <div class="col-md-4">
            <div class="ann-recent-card">
              <div class="ann-recent-head">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                Recent Posts
              </div>
              <?php
              $recent_query = "SELECT title, department, created_at FROM announcements ORDER BY created_at DESC LIMIT 5";
              $recent_result = $conn->query($recent_query);
              if ($recent_result->num_rows > 0):
                while ($recent = $recent_result->fetch_assoc()):
              ?>
                <div class="ann-recent-item">
                  <span class="ann-recent-dept"><?php echo htmlspecialchars($recent['department']); ?></span>
                  <a href="#" class="ann-recent-title"><?php echo htmlspecialchars($recent['title']); ?></a>
                  <div class="ann-recent-date"><?php echo date("F j, Y g:i A", strtotime($recent['created_at'])); ?></div>
                </div>
              <?php endwhile; else: ?>
                <div class="ann-recent-empty">No recent posts</div>
              <?php endif; ?>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>

      <div class="content-backdrop fade"></div>
    </div>
    <!-- /layout-page -->
  </div>
  <!-- /layout-container -->
</div>
<!-- /layout-wrapper -->
<div class="layout-overlay layout-menu-toggle"></div>

  <!-- Success Modal -->
  <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content" style="border-radius: 14px; overflow: hidden;">
        <div class="modal-header" style="background: linear-gradient(135deg,#7cb9ff,#4e96f0); color: #fff;">
          <h5 class="modal-title" style="color:#fff;">Success</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <?php if (isset($_SESSION['success'])) {
            echo $_SESSION['success'];
            unset($_SESSION['success']);
          } ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Error Modal -->
  <div class="modal fade" id="errorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content" style="border-radius: 14px; overflow: hidden;">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title">Error</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <?php if (isset($_SESSION['error'])) {
            echo $_SESSION['error'];
            unset($_SESSION['error']);
          } ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- PDF VIEWER MODAL -->
  <div class="modal fade" id="pdfModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 90%;">
      <div class="modal-content">
        <div class="modal-header" style="background: linear-gradient(135deg,#7cb9ff,#4e96f0);">
          <h5 class="modal-title text-white">
            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" class="me-2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Document Viewer
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-0" style="height: 85vh;">
          <iframe id="pdfViewerFrame" src="" style="width:100%; height:100%; border:none;" allowfullscreen></iframe>
        </div>
      </div>
    </div>
  </div>

  <script src="../assets/vendor/js/bootstrap.js"></script>
  <script src="../assets/vendor/js/menu.js"></script>
  <script src="../assets/js/main.js"></script>
  <script async defer src="https://buttons.github.io/buttons.js"></script>

  <!-- PAGINATION + SEARCH SCRIPT -->
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const cardsPerPage = 5;
      const paginationTop = document.getElementById("paginationTop");
      const paginationBottom = document.getElementById("paginationBottom");
      const announcementsFeed = document.getElementById("announcementsFeed");
      const searchInput = document.getElementById("searchInput");
      let currentPage = 1;
      let filteredCards = [];

      function getVisibleCards() {
        return Array.from(announcementsFeed.querySelectorAll(".announcement-card")).filter(c => c.dataset.hidden !== 'true');
      }

      function showPage(page) {
        const cards = getVisibleCards();
        const start = (page - 1) * cardsPerPage;
        const end = start + cardsPerPage;
        Array.from(announcementsFeed.querySelectorAll(".announcement-card")).forEach(c => {
          c.style.display = 'none';
        });
        cards.forEach((card, idx) => {
          card.style.display = idx >= start && idx < end ? '' : 'none';
        });
      }

      function renderPagination(container) {
        container.innerHTML = '';
        const cards = getVisibleCards();
        const totalPages = Math.ceil(cards.length / cardsPerPage);
        if (totalPages <= 1) return;

        const makeBtn = (label, page, cls = '') => {
          const btn = document.createElement('button');
          btn.className = 'ann-page-btn ' + cls;
          btn.innerHTML = label;
          if (cls !== 'disabled' && cls !== 'ellipsis') {
            btn.addEventListener('click', () => {
              currentPage = page;
              update();
            });
          } else { btn.disabled = true; }
          return btn;
        };

        container.appendChild(makeBtn('‹', currentPage - 1, currentPage === 1 ? 'disabled' : ''));

        for (let i = 1; i <= totalPages; i++) {
          if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
            container.appendChild(makeBtn(i, i, i === currentPage ? 'active' : ''));
          } else if (i === currentPage - 2 || i === currentPage + 2) {
            container.appendChild(makeBtn('…', null, 'ellipsis'));
          }
        }

        container.appendChild(makeBtn('›', currentPage + 1, currentPage === totalPages ? 'disabled' : ''));
      }

      function update() {
        showPage(currentPage);
        renderPagination(paginationTop);
        renderPagination(paginationBottom);
      }

      // Search filter
      searchInput.addEventListener("input", function () {
        const q = this.value.toLowerCase();
        Array.from(announcementsFeed.querySelectorAll(".announcement-card")).forEach(card => {
          const match = card.innerText.toLowerCase().includes(q);
          card.dataset.hidden = match ? 'false' : 'true';
          if (!match) card.style.display = 'none';
        });
        currentPage = 1;
        update();
      });

      update();
    });
  </script>

  <!-- Session modals -->
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      <?php if (isset($_SESSION['success'])): ?>
        new bootstrap.Modal(document.getElementById('successModal')).show();
      <?php endif; ?>
      <?php if (isset($_SESSION['error'])): ?>
        new bootstrap.Modal(document.getElementById('errorModal')).show();
      <?php endif; ?>
    });
  </script>

  <!-- PDF viewer -->
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const pdfModal = new bootstrap.Modal(document.getElementById('pdfModal'));
      const pdfFrame = document.getElementById('pdfViewerFrame');

      document.addEventListener('click', function (e) {
        const btn = e.target.closest('.view-pdf-btn');
        if (!btn) return;
        const pdfUrl = btn.getAttribute('data-pdf');
        if (!pdfUrl) return;
        pdfFrame.src = pdfUrl;
        pdfModal.show();
      });

      document.getElementById('pdfModal').addEventListener('hidden.bs.modal', function () {
        pdfFrame.src = '';
      });
    });
  </script>
</body>

</html>