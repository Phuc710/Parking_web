<?php
// api/checkin.php - Handle vehicle check-in via API
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
$license_plate = $_POST['license_plate'] ?? '';
$image_path = $_POST['image_path'] ?? null;

// Validate required fields
if (empty($license_plate)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing license plate']);
    exit;
}

// Check if there are available slots
$available_slots = get_available_slots();
if (empty($available_slots)) {
    http_response_code(409);
    echo json_encode(['error' => 'No available parking slots']);
    exit;
}

// Get the first available slot
$slot = $available_slots[0];

// Get an available RFID
$rfid = get_available_rfid();
if (!$rfid) {
    http_response_code(409);
    echo json_encode(['error' => 'No available RFID tags']);
    exit;
}

// Check if vehicle has a booking
$booking = check_vehicle_booking($license_plate);
$has_booking = $booking ? true : false;

// Record vehicle entry
$vehicle_id = record_vehicle_entry($license_plate, $slot['id'], $rfid, $image_path);

if ($vehicle_id) {
    // Log successful checkin
    log_activity('vehicle_checkin', "Vehicle $license_plate checked in at slot {$slot['id']}");
    
    echo json_encode([
        'success' => true,
        'vehicle_id' => $vehicle_id,
        'slot_id' => $slot['id'],
        'rfid' => $rfid,
        'has_booking' => $has_booking,
        'message' => 'Vehicle check-in successful'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to record vehicle entry']);
}