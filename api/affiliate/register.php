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

if (!isset($input['name']) || !isset($input['email']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Name, email and password required']);
    exit;
}

$affiliate = new Affiliate();
$affiliate_id = $affiliate->create($input['name'], $input['email'], $input['password']);

if ($affiliate_id) {
    // Send notification to admin
    $admin_email = 'your-admin@email.com'; // Change this
    $subject = 'New Affiliate Application';
    $message = "New affiliate registered:\n\n";
    $message .= "ID: {$affiliate_id}\n";
    $message .= "Name: {$input['name']}\n";
    $message .= "Email: {$input['email']}\n\n";
    $message .= "Please review and approve this application.";
    
    mail($admin_email, $subject, $message);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Application submitted successfully. You will be notified when approved.',
        'affiliate_id' => $affiliate_id
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Registration failed']);
}
?>