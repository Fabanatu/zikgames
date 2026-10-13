<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/subscriptions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST only']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$phone = preg_replace('/\s+/', '', trim(strval($payload['phone'] ?? '')));
$planName = trim(strval($payload['plan'] ?? $payload['plan_name'] ?? ''));
$shortcode = trim(strval($payload['shortcode'] ?? ''));
$keyword = trim(strval($payload['keyword'] ?? ''));
$active = intval($payload['active'] ?? 1) === 1 ? 1 : 0;
$startAt = normalizeSubscriptionDate($payload['start_date'] ?? $payload['start_at'] ?? 'now');
$endAt = normalizeSubscriptionDate($payload['end_date'] ?? $payload['end_at'] ?? '');
$durationDays = max(1, intval($payload['duration_days'] ?? 30));

if (!preg_match('/^0[789][01]\d{8}$/', $phone)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid phone number.']);
    exit;
}

if ($startAt === null) {
    $startAt = date('Y-m-d H:i:s');
}

if ($endAt === null) {
    $endAt = date('Y-m-d H:i:s', strtotime($startAt . " +$durationDays days"));
}

if (strtotime($endAt) <= strtotime($startAt)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'End date must be after start date.']);
    exit;
}

$record = upsertUserSubscription($phone, $active, $startAt, $endAt, $shortcode, $keyword, $planName);
$status = getPhoneSubscriptionStatus($phone);
$amount = getKeywordAmount($keyword, $shortcode, $planName);
logSubscriptionTransaction(
    intval($record['user_id'] ?? 0) ?: null,
    $phone,
    $planName,
    $shortcode,
    $keyword,
    $amount,
    $active,
    $record['subscription_start_at'],
    $record['subscription_end_at']
);

echo json_encode([
    'success' => true,
    'message' => $active === 1 ? 'Subscription saved and activated.' : 'Subscription saved as inactive.',
    'subscription' => [
        'phone' => $record['phone'],
        'active' => $status['active'],
        'expired' => $status['expired'],
        'start_at' => $record['subscription_start_at'],
        'end_at' => $record['subscription_end_at'],
        'plan' => $record['subscription_plan'],
        'shortcode' => $record['subscription_shortcode'],
        'keyword' => $record['subscription_keyword'],
        'amount' => $amount,
    ]
]);
