<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/ttt-common.php';

$user = tttRequireUser();
$db = tttDb();
tttCleanupExpired($db);

$sessionId = intval($_GET['sessionId'] ?? 0);
if ($sessionId <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Missing session id.']);
    exit;
}

$stmt = $db->prepare("
    SELECT
        s.*,
        host.phone AS host_phone,
        guest.phone AS guest_phone
    FROM ttt_sessions s
    JOIN users host ON host.id = s.host_user_id
    JOIN users guest ON guest.id = s.guest_user_id
    WHERE s.id = ?
      AND (s.host_user_id = ? OR s.guest_user_id = ?)
    LIMIT 1
");
$stmt->execute([$sessionId, $user['id'], $user['id']]);
$session = $stmt->fetch();

if (!$session) {
    http_response_code(404);
    echo json_encode(['error' => 'Session not found.']);
    exit;
}

echo json_encode([
    'success' => true,
    'session' => tttFormatSession($session, $user['id']),
]);
