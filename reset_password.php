<?php
$token = $_GET['token'] ?? '';

if (is_string($token) && preg_match('/\A[a-f0-9]{64}\z/i', $token)) {
  header('Location: index?panel=reset&token=' . rawurlencode($token));
} else {
  header('Location: index?panel=forgot');
}
exit();
