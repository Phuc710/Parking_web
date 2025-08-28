<?php
// api/checkout.php - Handle vehicle check-out via API
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
$rfid = $_POST['rfid'] ?? '';
$image_path = $_POST['image_path'] ?? null;

// Validate required fields
if (empty($rfid)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing RFID tag']);
    exit;
}

// Get vehicle by RFID
$vehicle = get_vehicle_by_rfid($rfid);
if (!$vehicle) {
    http_response_code(404);
    echo json_encode(['error' => 'Vehicle not found or already checked out']);
    exit;
}

// Check if vehicle has a booking
$booking = check_vehicle_booking($vehicle['license_plate']);

// Calculate parking fee
$now = date('Y-m-d H:i:s');
$fee = calculate_parking_fee($vehicle['entry_time'], $now);

// If there's a booking, check if it covers the parking time
if ($booking) {
    $booking_end = new DateTime($booking['end_time']);
    $exit_time = new DateTime($now);
    
    if ($exit_time <= $booking_end) {
        // Booking covers the parking time, no fee
        $fee = 0;
    } else {
        // Booking doesn't cover the full parking time, calculate fee for the extra time
        $fee = calculate_parking_fee($booking['end_time'], $now);
    }
}

if ($fee > 0) {
    // Create payment
    $payment = create_exit_payment($vehicle['id'], $fee);
    
    if (!$payment['success']) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create payment']);
        exit;
    }
    
    // Generate QR code for payment
    $qr_data = generate_payment_qr($payment['payment_id']);
    
    if (!$qr_data['success']) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate QR code']);
        exit;
    }
    
    // Log checkout with pending payment
    log_activity('vehicle_checkout_pending', "Vehicle {$vehicle['license_plate']} checkout pending payment");
    
    echo json_encode([
        'success' => true,
        'vehicle_id' => $vehicle['id'],
        'license_plate' => $vehicle['license_plate'],
        'entry_time' => $vehicle['entry_time'],
        'exit_time' => $now,
        'fee' => $fee,
        'payment_required' => true,
        'payment_id' => $payment['payment_id'],
        'payment_ref' => $payment['payment_ref'],
        'qr_code' => $qr_data['qr_code']
    ]);
} else {
    // No fee, complete checkout immediately
    $result = record_vehicle_exit($vehicle['id'], $image_path);
    
    if ($result) {
        // Log free checkout
        log_activity('vehicle_checkout_free', "Vehicle {$vehicle['license_plate']} checked out with no fee");
        
        echo json_encode([
            'success' => true,
            'vehicle_id' => $vehicle['id'],
            'license_plate' => $vehicle['license_plate'],
            'entry_time' => $vehicle['entry_time'],
            'exit_time' => $now,
            'fee' => 0,
            'payment_required' => false,
            'message' => 'Vehicle checkout successful with no payment required'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to record vehicle exit']);
    }
}