<?php
// api/webhook.php - SePay Webhook Handler
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Set content type
header('Content-Type: application/json');

// Log all incoming data for debugging
$raw_input = file_get_contents('php://input');
$webhook_log = date('Y-m-d H:i:s') . " - Webhook received:\n";
$webhook_log .= "Headers: " . json_encode(getallheaders()) . "\n";
$webhook_log .= "GET: " . json_encode($_GET) . "\n";
$webhook_log .= "POST: " . json_encode($_POST) . "\n";
$webhook_log .= "RAW: " . $raw_input . "\n";
$webhook_log .= "---\n";

file_put_contents('../sepay_webhook.log', $webhook_log, FILE_APPEND | LOCK_EX);

// SePay sends data as JSON in request body
$json_data = json_decode($raw_input, true);

// Handle both JSON and POST data
$transaction_id = '';
$amount = 0;
$content = '';
$bank_brand_name = '';
$account_number = '';

if ($json_data) {
    // Data from JSON body (SePay format)
    $transaction_id = $json_data['id'] ?? '';
    $amount = intval($json_data['amount_in'] ?? 0);
    $content = $json_data['transaction_content'] ?? '';
    $bank_brand_name = $json_data['bank_brand_name'] ?? '';
    $account_number = $json_data['account_number'] ?? '';
} else {
    // Fallback to POST data
    $transaction_id = $_POST['id'] ?? $_POST['transaction_id'] ?? '';
    $amount = intval($_POST['amount_in'] ?? $_POST['amount'] ?? 0);
    $content = $_POST['transaction_content'] ?? $_POST['content'] ?? '';
    $bank_brand_name = $_POST['bank_brand_name'] ?? '';
    $account_number = $_POST['account_number'] ?? '';
}

// Validate required fields
if (empty($transaction_id) || empty($content) || $amount <= 0) {
    error_log("SePay Webhook - Missing required fields: txn=$transaction_id, amount=$amount, content=$content");
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields', 'received' => compact('transaction_id', 'amount', 'content')]);
    exit;
}

// Extract payment reference from content
$payment_ref = extract_payment_reference($content);
if (!$payment_ref) {
    error_log("SePay Webhook - Cannot extract payment reference from content: $content");
    http_response_code(200);
    echo json_encode(['error' => 'Cannot extract payment reference', 'content' => $content]);
    exit;
}

// Process the payment
try {
    $result = process_sepay_webhook($transaction_id, $amount, $payment_ref, $content);
    
    if ($result['success']) {
        error_log("SePay Webhook - Processed successfully: $payment_ref");
        echo json_encode(['status' => 'success', 'message' => 'Payment processed successfully']);
    } else {
        error_log("SePay Webhook - Processing failed: " . $result['message']);
        http_response_code(500);
        echo json_encode(['error' => $result['message']]);
    }
    
} catch (Exception $e) {
    error_log("SePay Webhook - Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Extract payment reference from transaction content
 * SePay content format: "XParking XPARK-1735123456-123" or similar
 */
function extract_payment_reference($content) {
    // Look for patterns like XPARK-, BOOK-, EXIT-
    if (preg_match('/(XPARK-\d+-\d+|BOOK-\d+-\d+|EXIT-\d+-\d+)/', $content, $matches)) {
        return $matches[1];
    }
    
    // Alternative patterns
    if (preg_match('/([A-Z]+-\d+-\d+)/', $content, $matches)) {
        return $matches[1];
    }
    
    return false;
}

/**
 * Process SePay webhook payment
 */
function process_sepay_webhook($transaction_id, $amount, $payment_ref, $content) {
    global $pdo;
    
    try {
        // Find payment record
        $stmt = $pdo->prepare("
            SELECT p.*, u.full_name, u.email, b.slot_id, v.license_plate,
                   b.id as booking_id, v.id as vehicle_id
            FROM payments p 
            LEFT JOIN users u ON p.user_id = u.id
            LEFT JOIN bookings b ON p.booking_id = b.id
            LEFT JOIN vehicles v ON p.vehicle_id = v.id
            WHERE p.payment_ref = ?
        ");
        $stmt->execute([$payment_ref]);
        
        $payment = $stmt->fetch();
        
        if (!$payment) {
            return ['success' => false, 'message' => "Payment not found: $payment_ref"];
        }
        
        // Check if already processed
        if ($payment['status'] === 'completed') {
            return ['success' => true, 'message' => 'Payment already completed'];
        }
        
        // Verify amount matches
        if (abs($payment['amount'] - $amount) > 1) {
            error_log("Amount mismatch - Expected: {$payment['amount']}, Received: $amount");
            return ['success' => false, 'message' => 'Amount mismatch'];
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Update payment status
            $stmt = $pdo->prepare("
                UPDATE payments SET 
                    status = 'completed',
                    payment_time = NOW(),
                    sepay_ref = ?,
                    transaction_content = ?
                WHERE id = ?
            ");
            $stmt->execute([$transaction_id, $content, $payment['id']]);
            
            // Process booking payment
            if ($payment['booking_id']) {
                // Update booking status
                $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?");
                $stmt->execute([$payment['booking_id']]);
                
                // Reserve the slot
                if ($payment['slot_id']) {
                    $stmt = $pdo->prepare("UPDATE parking_slots SET status = 'reserved' WHERE id = ?");
                    $stmt->execute([$payment['slot_id']]);
                }
                
                error_log("Booking confirmed: {$payment['booking_id']}, Slot: {$payment['slot_id']}");
            }
            
            // Process vehicle exit payment
            if ($payment['vehicle_id']) {
                // Update vehicle status
                $stmt = $pdo->prepare("UPDATE vehicles SET status = 'exited', exit_time = NOW() WHERE id = ?");
                $stmt->execute([$payment['vehicle_id']]);
                
                // Get vehicle details for slot release
                $stmt = $pdo->prepare("SELECT slot_id, rfid_tag FROM vehicles WHERE id = ?");
                $stmt->execute([$payment['vehicle_id']]);
                $vehicle = $stmt->fetch();
                
                if ($vehicle) {
                    // Release parking slot
                    if ($vehicle['slot_id']) {
                        $stmt = $pdo->prepare("UPDATE parking_slots SET status = 'empty', rfid_assigned = NULL WHERE id = ?");
                        $stmt->execute([$vehicle['slot_id']]);
                    }
                    
                    // Release RFID tag
                    if ($vehicle['rfid_tag']) {
                        $stmt = $pdo->prepare("UPDATE rfid_pool SET status = 'available' WHERE uid = ?");
                        $stmt->execute([$vehicle['rfid_tag']]);
                    }
                }
                
                error_log("Vehicle exit completed: {$payment['vehicle_id']}");
            }
            
            $pdo->commit();
            
            // Log activity
            log_activity('sepay_payment', "SePay payment completed: $payment_ref, Amount: $amount", $payment['user_id']);
            
            // Send notifications (async if possible)
            try {
                send_payment_notifications($payment, 'completed');
            } catch (Exception $e) {
                error_log("Notification error: " . $e->getMessage());
                // Don't fail the whole process for notification errors
            }
            
            return ['success' => true, 'message' => 'Payment processed successfully'];
            
        } catch (Exception $e) {
            $pdo->rollback();
            throw $e;
        }
        
    } catch (PDOException $e) {
        error_log("Database error in webhook: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error'];
    }
}

/**
 * Log activity
 */
function log_activity($action, $description, $user_id = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, $action, $description]);
    } catch (Exception $e) {
        error_log("Log activity error: " . $e->getMessage());
    }
}
?>