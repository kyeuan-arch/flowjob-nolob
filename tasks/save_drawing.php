<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'not logged in']);
    exit();
}

require '../db.php';

$id           = (int)($_POST['id'] ?? 0);
$drawing_data = $_POST['drawing_data'] ?? '';
$user_id      = $_SESSION['user_id'];

if (!$id) {
    echo json_encode(['ok' => false, 'error' => 'invalid id']);
    exit();
}

// Only allow saving to tasks that belong to this user
$stmt = $pdo->prepare("UPDATE tasks SET drawing_data = ? WHERE id = ? AND user_id = ?");
$ok = $stmt->execute([$drawing_data ?: null, $id, $user_id]);

echo json_encode(['ok' => (bool)$ok]);
?>