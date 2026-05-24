<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
require '../db.php';

$id = (int)($_POST['id'] ?? 0);
$stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $_SESSION['user_id']]);

header("Location: ../dashboard.php");
exit();
?>