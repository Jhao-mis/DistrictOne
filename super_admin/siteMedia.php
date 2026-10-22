<?php
require_once '../vendor/autoload.php';
require_once '../db.php';
require_once '../lib/site_media.php';
require_once 'login_verification.php';

if (!access_is_super_admin()) {
    http_response_code(403);
    exit('Only Super Admins can manage site images.');
}

$uploadDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'site_media' . DIRECTORY_SEPARATOR;
$tableAvailable = site_media_table_available($conn);

function site_media_upload_files(array $uploadedFiles): array
{
    if (!isset($uploadedFiles['name']) || !is_array($uploadedFiles['name'])) {
        throw new RuntimeException('Select at least one image to upload.');
    }

    $imageCount = count($uploadedFiles['name']);
    if ($imageCount < 1 || $imageCount > 10) {
        throw new RuntimeException('Select between 1 and 10 images at a time.');
    }

    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $validatedFiles = [];
    $finfo = new finfo(FILEINFO_MIME_TYPE);

    foreach (array_keys($uploadedFiles['name']) as $index) {
        $uploadError = $uploadedFiles['error'][$index] ?? UPLOAD_ERR_NO_FILE;
        if ($uploadError !== UPLOAD_ERR_OK) {
            throw new RuntimeException('An image could not be uploaded. Check the file size and try again.');
        }

        $temporaryPath = $uploadedFiles['tmp_name'][$index] ?? '';
        $fileSize = (int) ($uploadedFiles['size'][$index] ?? 0);
        if (!is_uploaded_file($temporaryPath) || $fileSize < 1 || $fileSize > 50 * 1024 * 1024) {
            throw new RuntimeException('Each image must be a valid upload no larger than 50 MB.');
        }

        $mimeType = $finfo->file($temporaryPath);
        $imageInfo = getimagesize($temporaryPath);
        if (
            !isset($allowedMimeTypes[$mimeType]) ||
            $imageInfo === false ||
            $imageInfo['mime'] !== $mimeType ||
            $imageInfo[0] > 10000 ||
            $imageInfo[1] > 10000 ||
            $imageInfo[0] * $imageInfo[1] > 40000000
        ) {
            throw new RuntimeException('Only valid JPG, PNG, and WebP images are allowed.');
        }

        $validatedFiles[] = [
            'temporary_path' => $temporaryPath,
            'extension' => $allowedMimeTypes[$mimeType],
        ];
    }

    return $validatedFiles;
}

function site_media_ensure_upload_directory(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('The image upload folder could not be created. Check folder permissions.');
    }
}

function site_media_remove_file(string $directory, string $fileName): bool
{
    if (!preg_match('/\A[a-f0-9]{32}\.(jpg|png|webp)\z/', $fileName)) {
        return false;
    }

    $path = $directory . $fileName;
    return !is_file($path) || unlink($path);
}

function site_media_save_carousel(mysqli $conn, string $directory, array $files): void
{
    $conn->begin_transaction();
    $savedFiles = [];

    try {
        $result = $conn->query("SELECT position FROM site_media WHERE media_type = 'carousel' FOR UPDATE");
        $occupiedPositions = [];
        while ($row = $result->fetch_assoc()) {
            $occupiedPositions[] = (int) $row['position'];
        }

        $availablePositions = array_values(array_diff(range(1, 10), $occupiedPositions));
        if (count($files) > count($availablePositions)) {
            throw new RuntimeException(
                'The carousel has room for only ' . count($availablePositions) . ' more image(s). Remove an image or select fewer files.'
            );
        }

        site_media_ensure_upload_directory($directory);
        $insert = $conn->prepare(
            "INSERT INTO site_media (media_type, position, file_name) VALUES ('carousel', ?, ?)"
        );

        foreach ($files as $index => $file) {
            $fileName = bin2hex(random_bytes(16)) . '.' . $file['extension'];
            if (!move_uploaded_file($file['temporary_path'], $directory . $fileName)) {
                throw new RuntimeException('An image could not be saved. Check folder permissions and try again.');
            }

            $savedFiles[] = $fileName;
            $position = $availablePositions[$index];
            $insert->bind_param('is', $position, $fileName);
            $insert->execute();
        }

        $insert->close();
        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        foreach ($savedFiles as $fileName) {
            site_media_remove_file($directory, $fileName);
        }
        throw $exception;
    }
}

function site_media_save_login_background(mysqli $conn, string $directory, array $file): ?string
{
    site_media_ensure_upload_directory($directory);
    $fileName = bin2hex(random_bytes(16)) . '.' . $file['extension'];
    if (!move_uploaded_file($file['temporary_path'], $directory . $fileName)) {
        throw new RuntimeException('The image could not be saved. Check folder permissions and try again.');
    }

    try {
        $result = $conn->query(
            "SELECT id, file_name FROM site_media WHERE media_type = 'login_background' LIMIT 1"
        );
        $existing = $result->fetch_assoc();

        if ($existing) {
            $stmt = $conn->prepare('UPDATE site_media SET file_name = ? WHERE id = ?');
            $stmt->bind_param('si', $fileName, $existing['id']);
        } else {
            $position = 0;
            $stmt = $conn->prepare(
                "INSERT INTO site_media (media_type, position, file_name) VALUES ('login_background', ?, ?)"
            );
            $stmt->bind_param('is', $position, $fileName);
        }

        $stmt->execute();
        $stmt->close();
    } catch (Throwable $exception) {
        site_media_remove_file($directory, $fileName);
        throw $exception;
    }

    return $existing['file_name'] ?? null;
}

function site_media_delete_record(mysqli $conn, int $mediaId, string $mediaType): ?string
{
    $stmt = $conn->prepare('SELECT file_name FROM site_media WHERE id = ? AND media_type = ?');
    $stmt->bind_param('is', $mediaId, $mediaType);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$record) {
        throw new RuntimeException('That image was not found. Refresh the page and try again.');
    }

    $stmt = $conn->prepare('DELETE FROM site_media WHERE id = ? AND media_type = ?');
    $stmt->bind_param('is', $mediaId, $mediaType);
    $stmt->execute();
    $stmt->close();

    return $record['file_name'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $savedFileToClean = null;

    try {
        if (!$tableAvailable) {
            throw new RuntimeException('Run database/site_media_migration.sql before managing site images.');
        }

        $csrfToken = (string) ($_POST['csrf_token'] ?? '');
        if (empty($_SESSION['site_media_csrf']) || !hash_equals($_SESSION['site_media_csrf'], $csrfToken)) {
            throw new RuntimeException('Your session expired. Refresh this page and try again.');
        }

        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'upload_carousel') {
            $files = site_media_upload_files($_FILES['carousel_images'] ?? []);
            site_media_save_carousel($conn, $uploadDirectory, $files);
            $_SESSION['site_media_flash'] = ['success', 'Carousel images uploaded successfully.'];
        } elseif ($action === 'upload_login_background') {
            $files = site_media_upload_files($_FILES['login_background'] ?? []);
            if (count($files) !== 1) {
                throw new RuntimeException('Select one login background image.');
            }
            $savedFileToClean = site_media_save_login_background($conn, $uploadDirectory, $files[0]);
            if ($savedFileToClean !== null && !site_media_remove_file($uploadDirectory, $savedFileToClean)) {
                error_log('Site media: unable to remove replaced login background ' . $savedFileToClean);
                $_SESSION['site_media_flash'] = ['error', 'The new background was saved, but the previous image file could not be removed.'];
            } else {
                $_SESSION['site_media_flash'] = ['success', 'Login background updated successfully.'];
            }
        } elseif ($action === 'delete_carousel') {
            $fileName = site_media_delete_record($conn, (int) ($_POST['media_id'] ?? 0), 'carousel');
            if (!site_media_remove_file($uploadDirectory, $fileName)) {
                error_log('Site media: unable to remove deleted carousel image ' . $fileName);
                $_SESSION['site_media_flash'] = ['error', 'The image was removed from the carousel, but its file could not be deleted.'];
            } else {
                $_SESSION['site_media_flash'] = ['success', 'Carousel image removed.'];
            }
        } elseif ($action === 'reset_login_background') {
            $fileName = site_media_delete_record($conn, (int) ($_POST['media_id'] ?? 0), 'login_background');
            if (!site_media_remove_file($uploadDirectory, $fileName)) {
                error_log('Site media: unable to remove reset login background ' . $fileName);
                $_SESSION['site_media_flash'] = ['error', 'The default background was restored, but the previous image file could not be deleted.'];
            } else {
                $_SESSION['site_media_flash'] = ['success', 'Login background reset to the default image.'];
            }
        } else {
            throw new RuntimeException('Choose a valid image action.');
        }
    } catch (Throwable $exception) {
        if (!$exception instanceof RuntimeException) {
            error_log('Site media management failed: ' . $exception->getMessage());
        }

        $message = $exception instanceof RuntimeException
            ? $exception->getMessage()
            : 'The image could not be saved. Check the server error log for details.';
        $_SESSION['site_media_flash'] = ['error', $message];
    }

    header('Location: siteMedia.php');
    exit();
}

if (empty($_SESSION['site_media_csrf'])) {
    $_SESSION['site_media_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['site_media_csrf'];
$flash = $_SESSION['site_media_flash'] ?? null;
unset($_SESSION['site_media_flash']);

$carouselMedia = [];
$loginBackground = null;
if ($tableAvailable) {
    $result = $conn->query(
        "SELECT id, media_type, position, file_name
         FROM site_media
         ORDER BY media_type, position"
    );
    while ($media = $result->fetch_assoc()) {
        if ($media['media_type'] === 'carousel') {
            $carouselMedia[] = $media;
        } else {
            $loginBackground = $media;
        }
    }
}

$defaultLoginBackground = '../assets/img/backgrounds/oct.jpg';
$currentLoginBackground = $loginBackground
    ? '../uploads/site_media/' . rawurlencode($loginBackground['file_name'])
    : $defaultLoginBackground;
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="../assets/" data-template="vertical-menu-template-free">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Site Images | DistrictOne</title>
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/districtone.png">
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css">
    <link rel="stylesheet" href="../assets/vendor/css/core.css">
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css">
    <link rel="stylesheet" href="../assets/css/demo.css">
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css">
    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>
    <style>
        body { background: #f7f8fa; }
        .media-page { max-width: 1120px; margin: 32px auto; padding: 0 20px 40px; }
        .media-heading { margin-bottom: 22px; }
        .media-heading h1 { font-size: 25px; font-weight: 700; margin-bottom: 5px; }
        .media-heading p, .media-muted { color: #767e8c; margin: 0; }
        .media-card { background: #fff; border: 1px solid #e8eaee; border-radius: 14px; padding: 22px; margin-bottom: 20px; box-shadow: 0 8px 24px -12px rgba(20,20,43,.1); }
        .media-card h2 { font-size: 17px; font-weight: 700; margin-bottom: 6px; }
        .media-form { display: flex; align-items: end; gap: 12px; flex-wrap: wrap; margin-top: 18px; }
        .media-form .form-control { max-width: 520px; }
        .media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 14px; margin-top: 20px; }
        .media-item { border: 1px solid #e8eaee; border-radius: 10px; overflow: hidden; background: #fff; }
        .media-item img { display: block; width: 100%; height: 125px; object-fit: cover; }
        .media-item-footer { padding: 10px; display: flex; justify-content: space-between; align-items: center; gap: 8px; }
        .media-item-footer span { font-size: 12px; color: #767e8c; }
        .login-preview { min-height: 180px; border-radius: 10px; background-position: center; background-size: cover; background-color: #eaf3ff; margin-top: 16px; }
        .media-alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; }
        .media-alert.success { background: #e8f7ee; color: #176b37; }
        .media-alert.error { background: #fff0f0; color: #a12626; }
        @media (max-width: 575px) { .media-page { padding: 0 12px 30px; } .media-card { padding: 16px; } }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <main class="content-wrapper">
        <div class="media-page">
            <header class="media-heading">
                <h1>Site Images</h1>
                <p>Manage the shared profile carousel and the login page background.</p>
            </header>

            <?php if ($flash): ?>
                <div class="media-alert <?php echo htmlspecialchars($flash[0], ENT_QUOTES, 'UTF-8'); ?>" role="status">
                    <?php echo htmlspecialchars($flash[1], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (!$tableAvailable): ?>
                <div class="media-alert error">
                    Image settings are not initialized. Run
                    <code>database/site_media_migration.sql</code> against the DistrictOne database, then reload this page.
                </div>
            <?php else: ?>
                <section class="media-card">
                    <h2>Profile Carousel</h2>
                    <p class="media-muted">
                        Shared by Profile, Profile Teams, Communications, and Telephone Directory.
                        Upload JPG, PNG, or WebP images up to 50 MB each. Maximum: 10 images
                        (<?php echo count($carouselMedia); ?>/10 currently).
                    </p>
                    <form class="media-form" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="upload_carousel">
                        <div>
                            <label class="form-label" for="carouselImages">Add carousel images</label>
                            <input class="form-control" id="carouselImages" data-remaining="<?php echo 10 - count($carouselMedia); ?>" type="file" name="carousel_images[]"
                                accept="image/jpeg,image/png,image/webp" multiple required>
                        </div>
                        <button class="btn btn-primary" type="submit" <?php echo count($carouselMedia) >= 10 ? 'disabled' : ''; ?>>
                            Upload Images
                        </button>
                    </form>

                    <?php if ($carouselMedia): ?>
                        <div class="media-grid">
                            <?php foreach ($carouselMedia as $media): ?>
                                <article class="media-item">
                                    <img src="../uploads/site_media/<?php echo rawurlencode($media['file_name']); ?>"
                                        alt="Carousel image <?php echo (int) $media['position']; ?>">
                                    <div class="media-item-footer">
                                        <span>Slide <?php echo (int) $media['position']; ?></span>
                                        <form method="post" onsubmit="return confirm('Remove this carousel image?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="action" value="delete_carousel">
                                            <input type="hidden" name="media_id" value="<?php echo (int) $media['id']; ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="media-muted mt-3">No custom carousel images yet. The current default carousel will remain visible until you add images.</p>
                    <?php endif; ?>
                </section>

                <section class="media-card">
                    <h2>Login Background</h2>
                    <p class="media-muted">Upload one JPG, PNG, or WebP image up to 50 MB. Uploading a new image replaces the current custom background.</p>
                    <div class="login-preview" style="background-image: url('<?php echo htmlspecialchars($currentLoginBackground, ENT_QUOTES, 'UTF-8'); ?>');"></div>
                    <form class="media-form" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="upload_login_background">
                        <div>
                            <label class="form-label" for="loginBackground">Choose background image</label>
                            <input class="form-control" id="loginBackground" type="file" name="login_background[]"
                                accept="image/jpeg,image/png,image/webp" required>
                        </div>
                        <button class="btn btn-primary" type="submit">Save Background</button>
                    </form>
                    <?php if ($loginBackground): ?>
                        <form method="post" class="mt-2" onsubmit="return confirm('Reset the login background to the default image?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="reset_login_background">
                            <input type="hidden" name="media_id" value="<?php echo (int) $loginBackground['id']; ?>">
                            <button class="btn btn-outline-secondary" type="submit">Reset to Default</button>
                        </form>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>
    </main>

    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        const carouselInput = document.getElementById('carouselImages');
        if (carouselInput) carouselInput.addEventListener('change', function () {
            const remainingSlots = Number(this.dataset.remaining);
            if (this.files.length > remainingSlots) {
                this.setCustomValidity(`Only ${remainingSlots} carousel slot(s) remain.`);
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
