<?php

require '../vendor/autoload.php';
include '../db.php';
require 'login_verification.php';

$alert = null;

if (isset($_SESSION['alert'])) {
    $alert = $_SESSION['alert'];
    unset($_SESSION['alert']); // Clear after showing once
}

$username = $_SESSION['username'];

// 🔍 Fetch user details
$query = $conn->prepare("SELECT id, profile_picture, cover_photo, department, firstname, middlename, lastname, email, role 
                       FROM users WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$query->store_result();
$query->bind_result($user_id, $profile_picture, $cover_photo, $department, $firstname, $middlename, $lastname, $email, $user_role);
$query->fetch();
$query->close();

// ✅ Ensure user details are properly stored in session
$_SESSION['user_id'] = $user_id;
$_SESSION['name'] = trim("$firstname $middlename $lastname") ?: "Unknown User";

// 🔧 Pre-populate fields
$user_name = $_SESSION["name"];
$date_requested = date('Y-m-d');

// 🔥 Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $department = $_POST["department"];
    $department_other = trim($_POST["department_other"]);

    // ✅ Handle "Other" department input
    if ($department === "Other" && !empty($department_other)) {
        $department = $department_other;
    }

    // 🎯 Capture form data
    $reservation_date = $_POST["reservation_date"];
    $start_time = $_POST["start_time"];
    $end_time = $_POST["end_time"];
    $num_participants = $_POST["participants"];
    $purpose = trim($_POST["purpose"]);
    $room = $_POST["room"];

    // 🛑 Error check: Ensure purpose is filled
    if (empty($purpose)) {
        die("❌ Error: Purpose field is empty.");
    }

    // 🚀 Insert data into `room_reservations` table
    $stmt = $conn->prepare("INSERT INTO room_reservations 
        (user_id, department, reservation_date, start_time, end_time, participants, purpose, room, status, date_requested) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)");

    $stmt->bind_param(
        "issssisss",
        $user_id,
        $department,
        $reservation_date,
        $start_time,
        $end_time,
        $num_participants,
        $purpose,
        $room,
        $date_requested
    );

    // ✅ Handle execution results
    if ($stmt->execute()) {
        $_SESSION['alert'] = [
            'type' => 'success',
            'message' => 'Room reservation submitted successfully!',
        ];
    } else {
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => 'Error: ' . $stmt->error,
        ];
    }

    $stmt->close();
    $conn->close();

    // 🔄 Redirect after setting alert
    header("Location: room_reservation.php");
    exit();
}
// ✅ Fetch user’s reservations — integrated from old script
$sql = "SELECT * FROM room_reservations WHERE user_id = ? ORDER BY date_requested DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$reservations = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>

<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Room Reservation</title>

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
    <link rel="stylesheet" href="../css/user.css" />
    <link rel="stylesheet" href="../css/reservation_dashboard.css" />
    <link rel="stylesheet" href="./css/room_reserve.css">

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="../assets/vendor/libs/apex-charts/apex-charts.css" />

    <!-- Page CSS -->
    <link rel="stylesheet" href="../../assets/vendor/css/pages/app-calendar.css">
    <!-- Helpers -->
    <script src="../assets/vendor/js/helpers.js"></script>

    <script src="../assets/js/config.js"></script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/qtip2/3.0.3/jquery.qtip.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qtip2/3.0.3/jquery.qtip.min.js"></script>
</head>

<body>

    <?php include 'sidebar.php' ?>
    <div class="content-wrapper">
        <div class="container-xxl flex-grow-1 container-p-y">
            <div class="card app-calendar-wrapper">
                <div class="row g-0">
                    <div class="container-xxl flex-grow-1 container-p-y">

                        <!-- Active Tickets Section -->
                        <h2>Room Reservation Dashboard</h2>
                        <button class="btn btn-primary" id="app-calendar-sidebar" data-bs-toggle="offcanvas"
                            data-bs-target="#roomReservationSidebar" aria-controls="roomReservationSidebar"
                            style="background-color:#007bff">
                            + Create Reservation
                        </button>

                        <!-- Room Reservation Offcanvas -->
                        <div class="offcanvas offcanvas-end event-sidebar" tabindex="-1" id="roomReservationSidebar"
                            aria-labelledby="roomReservationSidebarLabel">
                            <div class="offcanvas-header border-bottom">
                                <h5 class="offcanvas-title" id="roomReservationSidebarLabel">Room Reservation Request
                                </h5>
                                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"
                                    aria-label="Close"></button>
                            </div>
                            <div class="offcanvas-body">
                                <?php if (isset($message)): ?>
                                    <p class="message"><?php echo $message; ?></p>
                                <?php endif; ?>

                                <form method="POST" class="reservation-form">
                                    <label>Date Requested</label>
                                    <input type="text" value="<?php echo $date_requested; ?>" readonly
                                        class="form-control">

                                    <label>Requested By</label>
                                    <input type="text" value="<?php echo $user_name; ?>" readonly class="form-control">

                                    <label>Department/Committee Requesting</label>
                                    <select name="department" class="form-select" required>
                                        <option value="Office of the General Manager">Office of the General Manager
                                        </option>
                                        <option value="Administrative Department">Administrative Department</option>
                                        <option value="Finance Department">Finance Department</option>
                                        <option value="Commercial Department">Commercial Department</option>
                                        <option value="Technical Services Department">Technical Services Department
                                        </option>
                                        <option value="Operations Department">Operations Department</option>
                                        <option value="Other">Other (Input Below)</option>
                                    </select>

                                    <!-- Other Department/Committee Input -->
                                    <div id="other-department-container" style="display: none;">
                                        <label>Specify Other Department/Committee</label>
                                        <input type="text" name="department_other" class="form-control"
                                            placeholder="Enter other department">
                                    </div>

                                    <script>
                                        document.querySelector("select[name='department']").addEventListener("change", function () {
                                            const otherContainer = document.getElementById("other-department-container");
                                            if (this.value === "Other") {
                                                otherContainer.style.display = "block";
                                            } else {
                                                otherContainer.style.display = "none";
                                            }
                                        });
                                    </script>

                                    <label>Reservation Date</label>
                                    <input type="date" name="reservation_date" class="form-control" required>

                                    <label>Start Time</label>
                                    <input type="time" name="start_time" class="form-control" required>

                                    <label>End Time</label>
                                    <input type="time" name="end_time" class="form-control" required>

                                    <label>Number of Participants</label>
                                    <input type="number" name="participants" min="1" class="form-control" required>

                                    <label>Purpose of Meeting</label>
                                    <textarea name="purpose" class="form-control" placeholder="Enter meeting purpose"
                                        required></textarea>

                                    <label>Select Room</label>
                                    <select name="room" class="form-select" required>
                                        <option value="Training Room, 3rd Floor, CWD Main Building">Training Room, 3rd
                                            Floor, CWD Main Building</option>
                                        <option value="Multipurpose Hall 5th Floor CWD Main Building">Multipurpose Hall
                                            5th Floor CWD Main Building</option>
                                        <option value="Conference Room, 2nd Floor, CWD Warehouse">Conference Room, 2nd
                                            Floor, CWD Warehouse</option>
                                        <option value="Conference Room, Operations Building, BPS Upper">Conference Room,
                                            Operations Building, BPS Upper</option>
                                        <option value="Roof Deck, Operations Building, BPS Upper">Roof Deck, Operations
                                            Building, BPS Upper</option>
                                    </select>

                                    <div class="d-flex justify-content-sm-between justify-content-start mt-4 gap-2">
                                        <button type="submit" name="submit_reservation"
                                            class="btn btn-primary btn-add-event">Submit</button>
                                        <button type="reset" class="btn btn-primary btn-cancel"
                                            style="background-color:rgb(255, 55, 55); border-color:rgb(179, 60, 60);">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="table-responsive text-nowrap">
                                <?php if (count($reservations) > 0): ?>
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date Requested</th>
                                                <th>Reservation Date</th>
                                                <th>Time</th>
                                                <th>Room</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reservations as $reservation): ?>
                                                <tr style="cursor:pointer;"
                                                    data-reservation='<?= json_encode($reservation, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                                                    <td><?= htmlspecialchars(date('Y-m-d', strtotime($reservation['date_requested']))); ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($reservation['reservation_date']); ?></td>
                                                    <td><?= htmlspecialchars($reservation['start_time'] . " - " . $reservation['end_time']); ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($reservation['room']); ?></td>
                                                    <td>
                                                        <?php if (strtolower($reservation['status']) === 'approved'): ?>
                                                            <span class="badge bg-success">Approved</span>
                                                        <?php elseif (strtolower($reservation['status']) === 'pending'): ?>
                                                            <span class="badge bg-warning text-dark">Pending</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Rejected</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (strtolower($reservation['status']) === 'approved'): ?>
                                                            <a href="../super_admin/room/reservation_pdf.php?id=<?= $reservation['id']; ?>"
                                                                target="_blank" class="btn btn-danger">📄 Generate PDF</a>
                                                        <?php else: ?>
                                                            <button class="btn btn-disabled" disabled>🚫 Locked</button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p class="text-center p-3">No reservations found.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="content-backdrop fade"></div>
            </div>
        </div>
    </div>

    <!-- 📋 Reservation Details Modal -->
    <div class="modal fade" id="reservationModal" tabindex="-1" aria-labelledby="reservationModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="reservationModalLabel">Reservation Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Request #:</strong> <span id="modalReqId"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Status:</strong> <span id="modalStatus" class="badge"></span>
                        </div>
                    </div>

                    <div class="mb-2"><strong>Requested By:</strong> <span id="modalRequested"></span></div>
                    <div class="mb-2"><strong>Department:</strong> <span id="modalDept"></span></div>
                    <div class="mb-2"><strong>Date Requested:</strong> <span id="modalDateRequested"></span></div>
                    <div class="mb-2"><strong>Reservation Date:</strong> <span id="modalReservationDate"></span></div>
                    <div class="mb-2"><strong>Time:</strong> <span id="modalTime"></span></div>
                    <div class="mb-2"><strong>Room:</strong> <span id="modalRoom"></span></div>
                    <div class="mb-2"><strong>Participants:</strong> <span id="modalParticipants"></span></div>

                    <div class="mb-3">
                        <strong>Purpose:</strong>
                        <div class="border rounded p-2 bg-light" id="modalPurpose"
                            style="white-space: normal; word-wrap: break-word; overflow-y: auto; overflow-x: hidden; max-height: 150px; min-height: 60px;">
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/dashboards-analytics.js"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>

    <script> // SCRIPT FOR ROOM RESERVE ROOM AVAILABILITY >> check_room_availability.php
        const dateInput = document.querySelector("input[name='reservation_date']");
        const startTimeInput = document.querySelector("input[name='start_time']");
        const endTimeInput = document.querySelector("input[name='end_time']");
        const roomSelect = document.querySelector("select[name='room']");

        function checkAvailability() {
            const date = dateInput.value;
            const start = startTimeInput.value;
            const end = endTimeInput.value;

            if (date && start && end) {
                fetch('check_room_availability.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `date=${encodeURIComponent(date)}&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`
                })
                    .then(response => response.json())
                    .then(reservedRooms => {
                        Array.from(roomSelect.options).forEach(option => {
                            const roomName = option.value;

                            if (reservedRooms.includes(roomName)) {
                                option.disabled = true;
                                option.textContent = roomName + ' (Slot Not Available)';
                            } else {
                                option.disabled = false;
                                option.textContent = roomName.replace(' (Slot Not Available)', '');
                            }
                        });
                    });
            }
        }
        // Run check when inputs are updated
        dateInput.addEventListener('change', checkAvailability);
        startTimeInput.addEventListener('change', checkAvailability);
        endTimeInput.addEventListener('change', checkAvailability);
    </script>

    <script>
        // ✅ Handle row click to show details
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.table tbody tr').forEach(row => {
                // Prevent clicking on buttons inside the row (PDF buttons)
                row.addEventListener('click', function (e) {
                    if (e.target.closest('a') || e.target.closest('button')) return;

                    const reservation = JSON.parse(this.dataset.reservation);

                    // Fill modal fields
                    document.getElementById('modalReqId').textContent = reservation.id;
                    document.getElementById('modalRequested').textContent = reservation.requested_by;
                    document.getElementById('modalDept').textContent = reservation.department;
                    document.getElementById('modalPurpose').textContent = reservation.purpose;
                    document.getElementById('modalDateRequested').textContent = reservation.date_requested;
                    document.getElementById('modalReservationDate').textContent = reservation.reservation_date;
                    document.getElementById('modalTime').textContent = `${reservation.start_time} - ${reservation.end_time}`;
                    document.getElementById('modalRoom').textContent = reservation.room;
                    document.getElementById('modalParticipants').textContent = reservation.participants;

                    // Status Badge
                    const statusElem = document.getElementById('modalStatus');
                    statusElem.textContent = reservation.status;
                    statusElem.className = 'badge ' +
                        (reservation.status === 'Approved' ? 'bg-success' :
                            reservation.status === 'Pending' ? 'bg-warning text-dark' :
                                'bg-danger');

                    // Show modal
                    const modal = new bootstrap.Modal(document.getElementById('reservationModal'));
                    modal.show();
                });
            });
        });
    </script>

    <!-- SWEET ALERT SHITS -->
    <script>
        Swal.fire({
            icon: '<?= $alert['type'] ?>',
            title: '<?= $alert['type'] === "success" ? "Success!" : "Oops..." ?>',
            text: '<?= $alert['message'] ?>',
            confirmButtonColor: '#007bff'
        });
    </script>
</body>

</html>