<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require '../db.php';

$conn = new mysqli($host, $user, $pass, $db);
$imageId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$imageId || $imageId < 1) {
  http_response_code(400);
  exit('Invalid image.');
}

$statement = $conn->prepare('SELECT image_path FROM mir_item_images WHERE id = ?');
$statement->bind_param('i', $imageId);
$statement->execute();
$image = $statement->get_result()->fetch_assoc();
$statement->close();
$conn->close();

if (!$image || empty($image['image_path'])) {
  http_response_code(404);
  exit('Image not found.');
}

$uploadRoot = realpath(__DIR__ . '/../uploads/mir');
$imageName = $image['image_path'];

if ($uploadRoot === false || basename($imageName) !== $imageName) {
  http_response_code(404);
  exit('Image not found.');
}

$imagePath = realpath($uploadRoot . DIRECTORY_SEPARATOR . $imageName);
$uploadPrefix = $uploadRoot . DIRECTORY_SEPARATOR;
if (
  $imagePath === false
  || strncasecmp($imagePath, $uploadPrefix, strlen($uploadPrefix)) !== 0
  || !is_file($imagePath)
) {
  http_response_code(404);
  exit('Image not found.');
}

$imageInfo = getimagesize($imagePath);
if ($imageInfo === false || $imageInfo['mime'] !== 'image/jpeg' || !function_exists('imagecreatefromjpeg')) {
  header('Location: ../uploads/mir/' . rawurlencode($imageName), true, 302);
  exit();
}

$cacheDirectory = $uploadRoot . DIRECTORY_SEPARATOR . '.print-cache';
$cacheName = hash('sha256', $imagePath . '|' . filemtime($imagePath) . '|' . filesize($imagePath)) . '.jpg';
$thumbnailPath = $cacheDirectory . DIRECTORY_SEPARATOR . $cacheName;

if (!is_file($thumbnailPath)) {
  if (($imageInfo[0] <= 360 && $imageInfo[1] <= 280) || (!is_dir($cacheDirectory) && !mkdir($cacheDirectory, 0755, true))) {
    if ($imageInfo[0] > 360 || $imageInfo[1] > 280) {
      error_log('MIR print thumbnail cache directory could not be created.');
    }
    header('Location: ../uploads/mir/' . rawurlencode($imageName), true, 302);
    exit();
  }

  $scale = min(360 / $imageInfo[0], 280 / $imageInfo[1], 1);
  $width = max(1, (int) round($imageInfo[0] * $scale));
  $height = max(1, (int) round($imageInfo[1] * $scale));
  $source = imagecreatefromjpeg($imagePath);
  $thumbnail = imagecreatetruecolor($width, $height);
  $temporaryPath = tempnam($cacheDirectory, 'mir-');

  if (
    $source === false
    || $thumbnail === false
    || $temporaryPath === false
    || !imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $width, $height, $imageInfo[0], $imageInfo[1])
    || !imagejpeg($thumbnail, $temporaryPath, 80)
  ) {
    error_log('MIR print thumbnail generation failed for image ID ' . $imageId . '.');
    if ($source !== false) {
      imagedestroy($source);
    }
    if ($thumbnail !== false) {
      imagedestroy($thumbnail);
    }
    if ($temporaryPath !== false && is_file($temporaryPath)) {
      unlink($temporaryPath);
    }
    header('Location: ../uploads/mir/' . rawurlencode($imageName), true, 302);
    exit();
  }

  imagedestroy($source);
  imagedestroy($thumbnail);

  if (!rename($temporaryPath, $thumbnailPath) && is_file($temporaryPath)) {
    unlink($temporaryPath);
  }

  if (!is_file($thumbnailPath)) {
    header('Location: ../uploads/mir/' . rawurlencode($imageName), true, 302);
    exit();
  }
}

header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=86400');
header('Content-Length: ' . filesize($thumbnailPath));
readfile($thumbnailPath);
