<?php
session_start();
require 'db.php';

// Delete token from DB if cookie exists
if (isset($_COOKIE['remember_token'])) {
  $token = $_COOKIE['remember_token'];
  $pdo->prepare("DELETE FROM remember_tokens WHERE token = ?")->execute([$token]);
  // Clear the cookie
  setcookie('remember_token', '', [
    'expires'  => time() - 3600,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
}

session_destroy();
header("Location: index.php");
exit();