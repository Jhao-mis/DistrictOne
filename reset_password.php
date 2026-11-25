<?php
$token = $_GET['token'] ?? '';

if (is_string($token) && preg_match('/\A[a-f0-9]{64}\z/i', $token)) {
  header('Location: index.php?panel=reset&token=' . rawurlencode($token));
} else {
  header('Location: index.php?panel=forgot');
}
exit();
