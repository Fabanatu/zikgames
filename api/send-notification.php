<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/notifications.php';

if (!isAdminLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$title = strval($data['title'] ?? '');
$message = strval($data['message'] ?? '');
$targetType = strval($data['target_type'] ?? $data['targetType'] ?? 'all');
$targetVal = strval($data['target_value'] ?? $data['targetVal'] ?? '');

$result = queueAdminNotification($title, $message, $targetType, $targetVal);

if (empty($result['success'])) {
    echo json_encode(['error' => $result['error'] ?? 'Unable to queue notification']);
    exit;
}

echo json_encode(['success' => true, 'sent' => intval($result['sent'] ?? 0)]);
