<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Email Verification</title>
    <link rel="icon" type="image/x-icon" href="assets/img/favicon/districtone.png" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

        .swal2-popup {
            font-family: 'Poppins', sans-serif !important;
        }
    </style>
</head>

<body>

    <?php
    if (isset($_SESSION['alertType']) && isset($_SESSION['alertText'])):
        $alertType = $_SESSION['alertType'];
        $alertText = $_SESSION['alertText'];
        $redirectURL = isset($_SESSION['redirectURL']) ? $_SESSION['redirectURL'] : 'after_verified.php'; // Default to 'index.php' if no redirect URL is set
        ?>

        <script>
            Swal.fire({
                title: '<?php echo ucfirst($alertType); ?>',
                text: '<?php echo $alertText; ?>',
                icon: '<?php echo $alertType; ?>',
                confirmButtonText: 'OK',
                confirmButtonColor: '#28a745',
                customClass: {
                    popup: 'poppins-font'
                }
            }).then(() => {
                window.location.href = '<?php echo $redirectURL; ?>';
            });
        </script>

        <?php
        // Clear session data after message is shown
        unset($_SESSION['alertType']);
        unset($_SESSION['alertText']);
        unset($_SESSION['redirectURL']);
    endif;
    ?>

</body>

</html>