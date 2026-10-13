<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/ttt-common.php';

$user = tttRequireUser();
$db = tttDb();

$payload = json_decode(file_get_contents('php://input'), true);
$sessionId = intval($payload['sessionId'] ?? 0);
$index = intval($payload['index'] ?? -1);

if ($sessionId <= 0 || $index < 0 || $index > 8) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid move request.']);
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
      AND s.status = 'active'
      AND (s.host_user_id = ? OR s.guest_user_id = ?)
    LIMIT 1
");
$stmt->execute([$sessionId, $user['id'], $user['id']]);
$session = $stmt->fetch();

if (!$session) {
    http_response_code(404);
    echo json_encode(['error' => 'Active session not found.']);
    exit;
}

if (intval($session['current_turn_user_id']) !== $user['id']) {
    http_response_code(409);
    echo json_encode(['error' => 'It is not your turn yet.']);
    exit;
}

$board = str_split($session['board_state'] ?: '---------');
if ($board[$index] !== '-') {
    http_response_code(409);
    echo json_encode(['error' => 'That space is already taken.']);
    exit;
}

$symbol = intval($session['host_user_id']) === $user['id'] ? $session['host_symbol'] : $session['guest_symbol'];
$nextTurnUserId = intval($session['host_user_id']) === $user['id']
    ? intval($session['guest_user_id'])
    : intval($session['host_user_id']);

$board[$index] = $symbol;
$boardState = implode('', $board);
$winningLine = tttCheckWinner($boardState, $symbol);
$isDraw = !$winningLine && strpos($boardState, '-') === false;

if ($winningLine) {
    $result = intval($session['host_user_id']) === $user['id'] ? 'host_win' : 'guest_win';
    $db->prepare("
        UPDATE ttt_sessions
        SET
            board_state = ?,
            status = 'completed',
            winner_user_id = ?,
            result = ?,
            completed_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ")->execute([$boardState, $user['id'], $result, $sessionId]);
} elseif ($isDraw) {
    $db->prepare("
        UPDATE ttt_sessions
        SET
            board_state = ?,
            status = 'completed',
            winner_user_id = NULL,
            result = 'draw',
            completed_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ")->execute([$boardState, $sessionId]);
} else {
    $db->prepare("
        UPDATE ttt_sessions
        SET
            board_state = ?,
            current_turn_user_id = ?,
            updated_at = NOW()
        WHERE id = ?
    ")->execute([$boardState, $nextTurnUserId, $sessionId]);
}

$refresh = $db->prepare("
    SELECT
        s.*,
        host.phone AS host_phone,
        guest.phone AS guest_phone
    FROM ttt_sessions s
    JOIN users host ON host.id = s.host_user_id
    JOIN users guest ON guest.id = s.guest_user_id
    WHERE s.id = ?
    LIMIT 1
");
$refresh->execute([$sessionId]);
$updated = $refresh->fetch();

echo json_encode([
    'success' => true,
    'session' => tttFormatSession($updated, $user['id']),
    'winningLine' => $winningLine,
]);
