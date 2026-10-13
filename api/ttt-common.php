<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

function tttRequireUser(): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isUserLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    return [
        'id' => intval($_SESSION['user_id']),
        'phone' => strval($_SESSION['phone']),
    ];
}

function tttDb(): PDO {
    return getDB();
}

function tttMaskPhone(string $phone): string {
    if (strlen($phone) < 7) {
        return $phone;
    }

    return substr($phone, 0, 4) . '****' . substr($phone, -3);
}

function tttCleanupExpired(PDO $db): void {
    $db->prepare("
        UPDATE ttt_sessions
        SET status = 'expired', updated_at = NOW()
        WHERE status = 'pending' AND created_at < (NOW() - INTERVAL 2 MINUTE)
    ")->execute();
}

function tttFindOpenSession(PDO $db, int $userId): ?array {
    $stmt = $db->prepare("
        SELECT
            s.*,
            host.phone AS host_phone,
            guest.phone AS guest_phone
        FROM ttt_sessions s
        JOIN users host ON host.id = s.host_user_id
        JOIN users guest ON guest.id = s.guest_user_id
        WHERE (s.host_user_id = ? OR s.guest_user_id = ?)
          AND s.status IN ('pending', 'active')
        ORDER BY s.id DESC
        LIMIT 1
    ");
    $stmt->execute([$userId, $userId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function tttFormatSession(array $session, int $currentUserId): array {
    $isHost = intval($session['host_user_id']) === $currentUserId;
    $yourSymbol = $isHost ? $session['host_symbol'] : $session['guest_symbol'];
    $opponentId = $isHost ? intval($session['guest_user_id']) : intval($session['host_user_id']);
    $opponentPhone = $isHost ? $session['guest_phone'] : $session['host_phone'];

    return [
        'id' => intval($session['id']),
        'status' => $session['status'],
        'board' => str_split($session['board_state'] ?: '---------'),
        'hostUserId' => intval($session['host_user_id']),
        'guestUserId' => intval($session['guest_user_id']),
        'currentTurnUserId' => $session['current_turn_user_id'] !== null ? intval($session['current_turn_user_id']) : null,
        'winnerUserId' => $session['winner_user_id'] !== null ? intval($session['winner_user_id']) : null,
        'result' => $session['result'] ?: null,
        'yourSymbol' => $yourSymbol,
        'opponentUserId' => $opponentId,
        'opponentPhone' => tttMaskPhone($opponentPhone),
        'isHost' => $isHost,
        'createdAt' => $session['created_at'],
        'updatedAt' => $session['updated_at'],
    ];
}

function tttCheckWinner(string $boardState, string $symbol): ?array {
    $cells = str_split($boardState);
    $lines = [
        [0, 1, 2],
        [3, 4, 5],
        [6, 7, 8],
        [0, 3, 6],
        [1, 4, 7],
        [2, 5, 8],
        [0, 4, 8],
        [2, 4, 6],
    ];

    foreach ($lines as $line) {
        if ($cells[$line[0]] === $symbol && $cells[$line[1]] === $symbol && $cells[$line[2]] === $symbol) {
            return $line;
        }
    }

    return null;
}
