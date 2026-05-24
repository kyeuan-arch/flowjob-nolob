<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
require '../db.php';

$id          = (int)($_POST['id'] ?? 0);
$title       = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$due_date    = $_POST['due_date'] ?? null;
$priority    = $_POST['priority'] ?? 'medium';
$filter      = $_POST['filter']   ?? 'all';
$search      = $_POST['search']   ?? '';
$pri         = $_POST['priority_filter'] ?? 'all'; // if you pass it separately

if ($title && $id) {
    $stmt = $pdo->prepare("UPDATE tasks SET title=?, description=?, due_date=?, priority=? WHERE id=? AND user_id=?");
    $stmt->execute([$title, $description, $due_date ?: null, $priority, $id, $_SESSION['user_id']]);
}
header("Location: ../dashboard.php?filter=" . urlencode($filter) . "&priority=" . urlencode($pri) . "&search=" . urlencode($search));
exit();
?>