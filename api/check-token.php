<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/subscriptions.php';

$data = json_decode(file_get_contents('php://input'), true);
$phone = trim(strval($data['phone'] ?? $_POST['phone'] ?? ''));
$phone = preg_replace('/\s+/', '', $phone);

if (!preg_match('/^0[789][01]\d{8}$/', $phone)) {
    echo json_encode([
        'status' => false,
        'active' => false,
        'message' => 'Invalid phone number format.'
    ]);
    exit;
}

$subscription = getPhoneSubscriptionStatus($phone);

echo json_encode([
    'status' => true,
    'active' => $subscription['active'],
    'expired' => $subscription['expired'],
    'pending' => $subscription['pending'],
    'message' => $subscription['message'],
    'start_at' => $subscription['start_at'],
    'end_at' => $subscription['end_at'],
]);
