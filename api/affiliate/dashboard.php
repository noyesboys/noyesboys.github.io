<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

require_once '../../classes/Affiliate.php';

// Get authorization header
$headers = apache_request_headers();
$token = null;

if (isset($headers['Authorization'])) {
    $token = str_replace('Bearer ', '', $headers['Authorization']);
}

if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'No token provided']);
    exit;
}

$affiliate = new Affiliate();
$user = $affiliate->verifyToken($token);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

$dashboardData = $affiliate->getDashboardData($user['id']);

if ($dashboardData) {
    echo json_encode($dashboardData);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load dashboard data']);
}
?>