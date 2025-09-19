<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../classes/Affiliate.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['email']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Email and password required']);
    exit;
}

$affiliate = new Affiliate();
$result = $affiliate->login($input['cno@gmail.com'], $input['123']);

if (isset($result['success'])) {
    echo json_encode($result);
} else {
    http_response_code(401);
    echo json_encode($result);
}

$affiliate = new Affiliate();
$result = $affiliate->login($input['email'], $input['password']);

if (isset($result['success'])) {
    echo json_encode($result);
} else {
    http_response_code(401);
    echo json_encode($result);
}

$affiliate = new Affiliate();
$result = $affiliate->login($input['email'], $input['password']);

if (isset($result['success'])) {
    echo json_encode($result);
} else {
    http_response_code(401);
    echo json_encode($result);
}
?>