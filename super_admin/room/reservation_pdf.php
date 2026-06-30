<?php
// ✅ Include database connection
include('../../db.php');
require '../login_verification.php';

// ✅ Get reservation ID from URL
$reservation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ✅ Fetch reservation data with updated user table structure
$query = $conn->prepare("SELECT rr.*, 
                                CONCAT(u.firstname, ' ', u.middlename, ' ', u.lastname) AS requester_name, 
                                u.department AS requester_department,
                                u.role AS requester_position,
                                CONCAT(a.firstname, ' ', a.middlename, ' ', a.lastname) AS approver_name
                         FROM room_reservations rr
                         JOIN users u ON rr.user_id = u.id
                         LEFT JOIN users a ON rr.approved_by = a.id
                         WHERE rr.id = ?");
$query->bind_param('i', $reservation_id);
$query->execute();
$result = $query->get_result();
$reservation = $result->fetch_assoc();

// ✅ Check if reservation exists
if (!$reservation) {
    echo "Reservation not found!";
    exit;
}

// ✅ Close the query and connection
$query->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Print Request</title>
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/districtone.png" />
    <style>
        @page {
            margin: 0;
            /* Removes browser headers & footers */
        }

        body {
            font-family: 'Times New Roman', Times, serif;
            margin: 0;
            padding: 20px;
            line-height: 1.5;
            color: #000;
        }

        .container {
            width: 80%;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #000;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header img {
            width: 600px;
            height: 80px;
        }

        .header h1 {
            font-size: 24px;
            margin: 5px 0;
        }

        .reservation-details {
            width: 100%;
            border-collapse: collapse;
        }

        .reservation-details td {
            padding: 5px;
        }

        .approved-section {
            margin-top: 20px;
        }

        .approved-section p {
            font-weight: bold;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
        }

        .reservation-details {
            width: 100%;
            border-collapse: collapse;
        }

        .reservation-details td {
            vertical-align: top;
            padding: 6px 8px;
            word-wrap: break-word;
            white-space: normal;
            /* allow wrapping */
            max-width: 400px;
            /* optional: limit the width for long text */
        }

        .reservation-details td:first-child {
            width: 200px;
            /* keeps labels aligned */
            font-weight: bold;
        }
    </style>
</head>

<body>

    <div class="container" id="printable-area">
        <!-- Header Section -->
        <div class="header">
            <img src="../../assets/img/elements/letter_head.png" alt="Letter Header">
            <h1>OFFICE OF THE GENERAL MANAGER</h1>
            <h2>Reservation Request Form</h2>
        </div>

        <table class="reservation-details">
            <tr>
                <td><strong>Date Requested:</strong></td>
                <td><?= date('F j, Y', strtotime($reservation['date_requested'])) ?></td>
            </tr>
            <tr>
                <td><strong>Department:</strong></td>
                <td><?= $reservation['department'] ?></td>
            </tr>
            <tr>
                <td><strong>Reservation Date:</strong></td>
                <td><?= date('F j, Y', strtotime($reservation['reservation_date'])) ?></td>
            </tr>
            <tr>
                <td><strong>Time:</strong></td>
                <td><?= date('h:i A', strtotime($reservation['start_time'])) ?> -
                    <?= date('h:i A', strtotime($reservation['end_time'])) ?>
                </td>
            </tr>
            <tr>
                <td><strong>Number of Participants:</strong></td>
                <td><?= $reservation['participants'] ?></td>
            </tr>
            <tr>
                <td><strong>Purpose:</strong></td>
                <td><?= $reservation['purpose'] ?></td>
            </tr>
            <tr>
                <td><strong>Requested by:</strong></td>
                <td><?= $reservation['requester_name'] ?></td>
            </tr>

            <tr>
                <td><strong>Room/Location: </strong></td>
                <td><?= $reservation['room'] ?></td>
            </tr>

            <tr>
                <td><strong>Approved By: </strong></td>
                <td><?= $reservation['approved_by'] ? htmlspecialchars($reservation['approved_by']) : "Pending"; ?></td>
            </tr>
        </table>

        <br><br>
        <center>
            <h5>This is a system-generated document, no signature required.</h5>
        </center>
    </div>
    </div>

    <script>
        window.onload = function () {
            // Automatically trigger print
            window.print();

            // Close window after print or cancel
            window.onafterprint = function () {
                window.close();
            };
        };
    </script>

</body>

</html>