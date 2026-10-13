<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!isUserLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$game  = trim($data['game'] ?? '');
$score = intval($data['score'] ?? 0);
$difficulty = min(5, max(1, intval($data['difficulty'] ?? 1)));
$multiplier = max(1, floatval($data['multiplier'] ?? 1));
$gameMode = trim($data['gameMode'] ?? 'single');
$survivalRun = !empty($data['survivalRun']) ? 1 : 0;
$duration = isset($data['duration']) ? max(0, intval($data['duration'])) : null;

$allowed = ['Tic Tac Toe','Bubble Shooter','Fruit Slice','Space Shooter','Danfo Dodge'];
if (!in_array($game, $allowed, true) || $score < 0 || !in_array($gameMode, ['single', 'multiplayer'], true)) {
    echo json_encode(['error' => 'Invalid data']); exit;
}

$userId = $_SESSION['user_id'];
$db = getDB();

// Save score with richer metadata for survival runs and future multiplayer.
$db->prepare("
    INSERT INTO game_scores (
        user_id,
        game_name,
        score,
        duration,
        difficulty_level,
        multiplier,
        game_mode,
        survival_run,
        created_at
    ) VALUES (?,?,?,?,?,?,?,?,NOW())
")->execute([
    $userId,
    $game,
    $score,
    $duration,
    $difficulty,
    $multiplier,
    $gameMode,
    $survivalRun
]);

// Update user totals
$db->prepare("UPDATE users SET total_games_played = total_games_played + 1, updated_at = NOW() WHERE id = ?")
   ->execute([$userId]);

// Log
$db->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?,?,?,NOW())")
   ->execute([
       $userId,
       'game_played',
       sprintf(
           'Game: %s, Score: %d, Level: %d, Mode: %s, Survival: %s',
           $game,
           $score,
           $difficulty,
           $gameMode,
           $survivalRun ? 'yes' : 'no'
       )
   ]);

echo json_encode([
    'success' => true,
    'score' => $score,
    'meta' => [
        'difficulty' => $difficulty,
        'multiplier' => $multiplier,
        'gameMode' => $gameMode,
        'survivalRun' => (bool) $survivalRun,
        'duration' => $duration,
    ],
]);
