<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$other_user_id = $_GET['user_id'];

$stmt = $db->prepare("
    SELECT * FROM messages 
    WHERE (sender_id = ? AND receiver_id = ?)
    OR (sender_id = ? AND receiver_id = ?)
    ORDER BY created_at ASC
");

$stmt->execute([$current_user_id, $other_user_id, $other_user_id, $current_user_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($messages);
?>
