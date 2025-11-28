<?php
header('Content-Type: application/json');
session_start();

function gen_code(int $n = 6): string {
  $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  $s = '';
  for ($i = 0; $i < $n; $i++) {
    $s .= $chars[random_int(0, strlen($chars) - 1)];
  }
  $_SESSION['captcha_code'] = strtoupper($s);
  return $_SESSION['captcha_code'];
}

if (isset($_GET['renew'])) {
  echo json_encode(['code' => gen_code()]);
} else {
  $code = $_SESSION['captcha_code'] ?? gen_code();
  echo json_encode(['code' => $code]);
}