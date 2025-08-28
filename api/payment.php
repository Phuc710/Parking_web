<?php
// api/payment.php - Handle payment requests
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Get the POST data
$action = $_POST['action'] ?? '';

// Handle different payment actions
switch ($action) {
    case 'generate_qr':
        $payment_id = $_POST['payment_id'] ?? 0;
        
        if (!$payment_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing payment ID']);
            exit;
        }
        
        // Generate QR code
        $result = generate_payment_qr($payment_id);
        
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'qr_code' => $result['qr_code'],
                'amount' => $result['amount'],
                'reference' => $result['reference'],
                'transaction_id' => $result['transaction_id']
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => $result['message']]);
        }
        break;
        
    case 'check_status':
        $reference = $_POST['reference'] ?? '';
        
        if (!$reference) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing payment reference']);
            exit;
        }
        
        // Check payment status
        $status = check_payment_status($reference);
        
        echo json_encode(['status' => $status]);
        break;
        
    case 'create_exit_payment':
        $vehicle_id = $_POST['vehicle_id'] ?? 0;
        $amount = $_POST['amount'] ?? 0;
        
        if (!$vehicle_id || !$amount) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }
        
        // Create exit payment
        $result = create_exit_payment($vehicle_id, $amount);
        
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'payment_id' => $result['payment_id'],
                'payment_ref' => $result['payment_ref']
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => $result['message']]);
        }
        break;
        
    case 'complete_checkout':
        $vehicle_id = $_POST['vehicle_id'] ?? 0;
        $image_path = $_POST['image_path'] ?? null;
        
        if (!$vehicle_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing vehicle ID']);
            exit;
        }
        
        // Complete checkout
        $result = record_vehicle_exit($vehicle_id, $image_path);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'vehicle' => [
                    'id' => $result['id'],
                    'license_plate' => $result['license_plate'],
                    'entry_time' => $result['entry_time'],
                    'exit_time' => $result['exit_time'],
                    'slot_id' => $result['slot_id']
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to complete checkout']);
        }
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>