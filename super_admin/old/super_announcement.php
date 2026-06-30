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

$sql = "SELECT * FROM announcements ORDER BY created_at DESC";
$result = $conn->query($sql);
$client_department = $department; // This is fetched from the logged-in user

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
  <title>Announcements - District One</title>
  <link rel="stylesheet" href="../css/style.css">
  <link href='https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css' rel='stylesheet' />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/qtip2/3.0.3/jquery.qtip.min.css" />
  <script src='https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js'></script>
  <script src='https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js'></script>
  <script src='https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js'></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qtip2/3.0.3/jquery.qtip.min.js"></script>
  <meta name="description" content="" />

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="../assets/img/favicon/districtone.png" />

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
    rel="stylesheet" />

  <!-- Icons. Uncomment required icon fonts -->
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />

  <!-- Core CSS -->
  <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />

  <!-- Vendors CSS -->
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/config.js"></script>

  <style>
    .app-brand-text {
      font-size: 20px !important;
      font-weight: bold;
      margin-left: 5px;
      margin-top: 10px;
    }

    .logo {
      margin-left: -30px;
      margin-top: 5px;
    }

    body {
      font-family: Arial, sans-serif;
      background-color: #f9f9f9;
      margin: 0;
      padding: 20px;
    }

    #calendar {
      max-width: 700px;
      margin: 20px auto;
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      padding: 20px;
    }

    .bg-menu-theme .menu-inner>.menu-item.active>.menu-link {
      color: #fff;
      background-color: rgba(47, 144, 255, 0.63) !important;
    }

    .bg-menu-theme .menu-inner>.menu-item.active:before {
      background-color: #2793eb;
    }

    /* PDF Modal Enhancements */
    #pdfModal .modal-dialog {
      transition: all 0.3s ease-in-out;
    }

    #pdfModal.show .modal-dialog {
      transform: scale(1.02);
    }

    #pdfModal .modal-content {
      animation: fadeInUp 0.3s ease;
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
     #pdfModal .modal-dialog {
      transition: transform 0.25s ease, opacity 0.25s ease;
    }

    #pdfModal.show .modal-dialog {
      transform: scale(1.02);
    }
  </style>
</head>

<body>
  <?php include 'sidebar.php' ?>

  <div class="content-wrapper">
    <div class="container-xxl flex-grow-1 container-p-y">

      <!-- ANNOUNCEMENT SECTION -->
      <div class="card app-calendar-wrapper">
        <div class="row g-0">
          <div class="col border-end" id="app-calendar-sidebar">

            <div class="px-3 pt-2">
              <div class="container mt-4">
                <div class="row">

                  <!-- LEFT SIDE: ANNOUNCEMENTS -->
                  <div class="col-md-8">
                    <div class="border-bottom p-3 mb-4">
                      <div class="d-flex align-items-center gap-3">

                        <!-- SEARCH BOX -->
                        <div class="mb-3 flex-grow-1">
                          <input type="text" id="searchInput" class="form-control" placeholder="Search announcements">
                        </div>

                        <!-- DROPDOWN FILTER -->
                        <!-- <div class="dropdown">
                          <button class="btn btn-light dropdown-toggle" type="button" id="departmentDropdown"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            All Announcements
                          </button>
                          <ul class="dropdown-menu" aria-labelledby="departmentDropdown">
                            <li><a class="dropdown-item department-filter active" href="#"
                                data-department="All Departments">All Departments</a></li>
                            <?php
                            $client_department = $department;
                            if ($client_department != "All Departments"): ?>
                              <li>
                                <a class="dropdown-item department-filter" href="#"
                                  data-department="<?php echo htmlspecialchars($client_department); ?>">
                                  <?php echo htmlspecialchars($client_department); ?>
                                </a>
                              </li>
                            <?php endif; ?>
                          </ul>
                        </div> -->
                      </div>
                    </div>

                    <!-- PAGINATION TOP -->
                    <nav aria-label="Page navigation" class="mb-3">
                      <ul class="pagination justify-content-center" id="paginationTop"></ul>
                    </nav>

                    <!-- POSTS FEED -->
                    <div id="announcementsFeed">
                      <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                          <div class="card mb-3 announcement-card">
                            <div class="card-body">
                              <div class="d-flex justify-content-between align-items-start">
                                <h3 class="card-title"><?php echo htmlspecialchars($row['title']); ?></h3>
                              </div>
                              <p class="card-text"><?php echo nl2br(htmlspecialchars($row['message'])); ?></p>
                              <small class="text-muted">Posted by:
                                <?php echo htmlspecialchars($row['firstname'] . " " . $row['middlename'] . " " . $row['lastname']); ?>
                              </small><br>
                              <small class="text-muted">Posted on: <?php echo $row['created_at']; ?></small>

                              <!-- FILE ATTACHMENTS -->
                              <?php if (!empty($row['file_path'])): ?>
                                <?php
                                $file_path = '../uploads/' . basename($row['file_path']);
                                $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                                ?>
                                <?php if (file_exists($file_path)): ?>

                                  <?php if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                    <!-- IMAGE FILES -->
                                    <div class="mt-3">
                                      <img src="<?php echo htmlspecialchars($file_path); ?>" alt="Post Image"
                                        class="img-fluid rounded shadow-sm">
                                    </div>

                                  <?php elseif ($file_extension === 'pdf'): ?>
                                    <!-- PDF FILES -->
                                    <div class="mt-3">
                                      <button class="btn btn-sm btn-primary view-pdf-btn"
                                        data-pdf="<?php echo htmlspecialchars($file_path); ?>">
                                        <i class="bx bx-file"></i> View PDF
                                      </button>
                                      <a href="<?php echo htmlspecialchars($file_path); ?>" class="btn btn-sm btn-danger"
                                        download>
                                        <i class="bx bx-download"></i> Download PDF
                                      </a>
                                    </div>

                                  <?php else: ?>
                                    <!-- OTHER FILES -->
                                    <div class="mt-3">
                                      <a href="<?php echo htmlspecialchars($file_path); ?>" class="btn btn-sm btn-secondary"
                                        download>
                                        <i class="bx bx-download"></i> Download File
                                      </a>
                                    </div>
                                  <?php endif; ?>

                                <?php else: ?>
                                  <p class="text-danger">File not found.</p>
                                <?php endif; ?>
                              <?php endif; ?>

                            </div>
                          </div>
                        <?php endwhile; ?>
                      <?php else: ?>
                        <p class="text-center">No announcements available.</p>
                      <?php endif; ?>
                    </div>


                    <!-- FILE ATTACHMENTS -->
                    <?php if (!empty($row['file_path'])): ?>
                      <?php
                      $file_path = '../uploads/' . basename($row['file_path']);
                      $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                      ?>
                      <?php if (file_exists($file_path)): ?>

                        <?php if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                          <!-- IMAGE FILES -->
                          <div class="mt-3">
                            <img src="<?php echo htmlspecialchars($file_path); ?>" alt="Post Image"
                              class="img-fluid rounded shadow-sm">
                          </div>

                        <?php elseif ($file_extension === 'pdf'): ?>
                          <!-- PDF FILES -->
                          <div class="mt-3">
                            <button class="btn btn-sm btn-primary view-pdf-btn"
                              data-pdf="<?php echo htmlspecialchars($file_path); ?>">
                              <i class="bx bx-file"></i> View PDF
                            </button>
                            <a href="<?php echo htmlspecialchars($file_path); ?>" class="btn btn-sm btn-danger" download>
                              <i class="bx bx-download"></i> Download PDF
                            </a>
                          </div>

                        <?php else: ?>
                          <!-- OTHER FILES -->
                          <div class="mt-3">
                            <a href="<?php echo htmlspecialchars($file_path); ?>" class="btn btn-sm btn-secondary" download>
                              <i class="bx bx-download"></i> Download File
                            </a>
                          </div>
                        <?php endif; ?>

                      <?php else: ?>
                        <p class="text-danger">File not found.</p>
                      <?php endif; ?>
                    <?php endif; ?>


                    <!-- PAGINATION BOTTOM -->
                    <nav aria-label="Page navigation" class="mt-3">
                      <ul class="pagination justify-content-center" id="paginationBottom"></ul>
                    </nav>
                  </div>

                  <!-- RIGHT SIDE: RECENT POSTS -->
                  <div class="col-md-4 mt-4">
                    <div class="card">
                      <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 text-uppercase text-muted">Recent Posts</h6>
                      </div>
                      <div class="card-body">
                        <ul class="list-group list-group-flush">
                          <?php
                          $recent_query = "SELECT title, department, created_at FROM announcements ORDER BY created_at DESC LIMIT 5";
                          $recent_result = $conn->query($recent_query);
                          if ($recent_result->num_rows > 0):
                            while ($recent = $recent_result->fetch_assoc()):
                              ?>
                              <li class="list-group-item">
                                <div class="d-flex align-items-start">
                                  <div>
                                    <p class="mb-1 text-muted small"><?php echo htmlspecialchars($recent['department']); ?>
                                    </p>
                                    <a href="#" class="fw-bold text-dark text-decoration-none">
                                      <?php echo htmlspecialchars($recent['title']); ?>
                                    </a>
                                    <p class="mb-0 text-muted small">
                                      <?php echo date("F j, Y g:i A", strtotime($recent['created_at'])); ?>
                                    </p>
                                  </div>
                                </div>
                              </li>
                            <?php endwhile; else: ?>
                            <li class="list-group-item text-muted text-center">No recent posts</li>
                          <?php endif; ?>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="content-backdrop fade"></div>
  </div>
  </div>
  </div>
  <div class="layout-overlay layout-menu-toggle"></div>
  </div>

  <div class="content-backdrop fade"></div>
  </div>

  <!-- Success Modal -->
  <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title" id="successModalLabel">Success</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
  <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="errorModalLabel">Error</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
  <div class="modal fade" id="pdfModal" tabindex="-1" aria-labelledby="pdfModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 90%;">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="pdfModalLabel"><i class="bx bx-file"></i> View</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-0" style="height: 85vh;">
          <iframe id="pdfViewerFrame" src="" style="width:100%; height:100%; border:none;" allowfullscreen></iframe>
        </div>
      </div>
    </div>
  </div>



  <script src="../assets/vendor/js/menu.js"></script>
  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/pages-account-settings-account.js"></script>
  <script async defer src="https://buttons.github.io/buttons.js"></script>

  <!-- PAGINATION + SEARCH + FILTER SCRIPT -->
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const cardsPerPage = 5;
      const paginationTop = document.getElementById("paginationTop");
      const paginationBottom = document.getElementById("paginationBottom");
      const announcementsFeed = document.getElementById("announcementsFeed");
      const searchInput = document.getElementById("searchInput");
      let currentPage = 1;

      function applyPagination() {
        const cards = Array.from(announcementsFeed.querySelectorAll(".announcement-card"));
        const totalPages = Math.ceil(cards.length / cardsPerPage);

        function showPage(page) {
          const start = (page - 1) * cardsPerPage;
          const end = start + cardsPerPage;
          cards.forEach((card, index) => {
            card.style.display = index >= start && index < end ? "" : "none";
          });
        }

        function renderPagination(container) {
          container.innerHTML = "";

          // Previous Arrow
          const prev = document.createElement("li");
          prev.classList.add("page-item");
          const prevLink = document.createElement("a");
          prevLink.classList.add("page-link");
          prevLink.href = "#";
          prevLink.textContent = "‹";
          prevLink.addEventListener("click", (e) => {
            e.preventDefault();
            if (currentPage > 1) {
              currentPage--;
              updateAllPaginations();
            }
          });
          prev.appendChild(prevLink);
          container.appendChild(prev);

          // Page numbers with ellipsis
          for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
              const li = document.createElement("li");
              li.classList.add("page-item");
              if (i === currentPage) li.classList.add("active");
              const link = document.createElement("a");
              link.classList.add("page-link");
              link.href = "#";
              link.textContent = i;
              link.addEventListener("click", (e) => {
                e.preventDefault();
                currentPage = i;
                updateAllPaginations();
              });
              li.appendChild(link);
              container.appendChild(li);
            } else if (i === currentPage - 2 || i === currentPage + 2) {
              const span = document.createElement("li");
              span.classList.add("page-item", "disabled");
              const ellipsis = document.createElement("a");
              ellipsis.classList.add("page-link");
              ellipsis.textContent = "...";
              span.appendChild(ellipsis);
              container.appendChild(span);
            }
          }

          // Next Arrow
          const next = document.createElement("li");
          next.classList.add("page-item");
          const nextLink = document.createElement("a");
          nextLink.classList.add("page-link");
          nextLink.href = "#";
          nextLink.textContent = "›";
          nextLink.addEventListener("click", (e) => {
            e.preventDefault();
            if (currentPage < totalPages) {
              currentPage++;
              updateAllPaginations();
            }
          });
          next.appendChild(nextLink);
          container.appendChild(next);
        }

        function updateAllPaginations() {
          showPage(currentPage);
          renderPagination(paginationTop);
          renderPagination(paginationBottom);
        }

        // Initialize pagination
        if (cards.length > 0) {
          currentPage = 1;
          updateAllPaginations();
        } else {
          paginationTop.innerHTML = "";
          paginationBottom.innerHTML = "";
        }
      }

      // Apply pagination initially
      applyPagination();

      // Search filter
      searchInput.addEventListener("input", function () {
        const query = searchInput.value.toLowerCase();
        const cards = announcementsFeed.querySelectorAll(".announcement-card");
        cards.forEach(card => {
          const title = card.querySelector(".card-title").textContent.toLowerCase();
          const message = card.querySelector(".card-text").textContent.toLowerCase();
          card.style.display = (title.includes(query) || message.includes(query)) ? "" : "none";
        });
      });

      // Department Filter (AJAX)
      document.querySelectorAll(".department-filter").forEach(item => {
        item.addEventListener("click", function (event) {
          event.preventDefault();
          let selectedDepartment = this.getAttribute("data-department");
          document.getElementById("departmentDropdown").innerText = selectedDepartment;

          fetch("../fetch_announcements.php?department=" + encodeURIComponent(selectedDepartment))
            .then(response => response.text())
            .then(data => {
              announcementsFeed.innerHTML = data;
              applyPagination(); // reapply pagination after reload
            })
            .catch(error => console.error("Error fetching announcements:", error));
        });
      });
    });
  </script>

  <script>
    document.addEventListener("DOMContentLoaded", function () {
      <?php if (isset($_SESSION['success'])) { ?>
        var successModal = new bootstrap.Modal(document.getElementById('successModal'));
        successModal.show();
      <?php } ?>

      <?php if (isset($_SESSION['error'])) { ?>
        var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
        errorModal.show();
      <?php } ?>
    });
  </script>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      document.addEventListener('click', function (e) {
        const btn = e.target.closest('.view-pdf-btn');
        if (!btn) return;
        const pdfUrl = btn.getAttribute('data-pdf');
        openPdfModal(pdfUrl); // ✅ Use our Google PDF viewer loader
      });
    });
  </script>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const pdfModal = new bootstrap.Modal(document.getElementById('pdfModal'));
      const pdfFrame = document.getElementById('pdfViewerFrame');

      document.addEventListener('click', function (e) {
        const btn = e.target.closest('.view-pdf-btn');
        if (!btn) return;

        const pdfUrl = btn.getAttribute('data-pdf');
        if (!pdfUrl) return;

        // ✅ Use direct link to the PDF (local or server)
        pdfFrame.src = pdfUrl;
        pdfModal.show();
      });

      // Clear iframe when modal is closed
      document.getElementById('pdfModal').addEventListener('hidden.bs.modal', function () {
        pdfFrame.src = '';
      });
    });
  </script>


</body>

</html>