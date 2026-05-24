<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
require '../db.php';

$title       = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$due_date    = $_POST['due_date'] ?? null;
$priority    = $_POST['priority'] ?? 'medium';

if ($title) {
  $stmt = $pdo->prepare("INSERT INTO tasks (user_id, title, description, due_date, priority) VALUES (?, ?, ?, ?, ?)");
  $stmt->execute([$_SESSION['user_id'], $title, $description, $due_date ?: null, $priority]);
}
header("Location: ../dashboard.php");
exit();
?>