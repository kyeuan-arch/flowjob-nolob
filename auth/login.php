<?php
session_start();
require '../db.php';

$email    = trim($_POST['email']    ?? '');
$password = trim($_POST['password'] ?? '');
$remember = isset($_POST['remember']) && $_POST['remember'] === '1';

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && password_verify($password, $user['password'])) {
  $_SESSION['user_id']   = $user['id'];
  $_SESSION['user_name'] = $user['name'];

  // Remember me — 30 days
  if ($remember) {
    $token   = bin2hex(random_bytes(32));
    $expires = time() + (30 * 24 * 60 * 60);

    $pdo->prepare("INSERT INTO remember_tokens (user_id, token, expires_at)
      VALUES (?, ?, FROM_UNIXTIME(?))
      ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)")
      ->execute([$user['id'], $token, $expires]);

    setcookie('remember_token', $token, [
      'expires'  => $expires,
      'path'     => '/',
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
  }

  header("Location: ../dashboard.php");
} else {
  header("Location: ../index.php?err=invalid");
}
exit();