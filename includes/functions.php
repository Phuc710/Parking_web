<?php
// includes/functions.php
require_once 'config.php';

// Parking Slot Functions
// ---------------------

// Get all parking slots
function get_all_slots() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT * FROM parking_slots");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get slots error: " . $e->getMessage());
        return [];
    }
}

// Get available parking slots
function get_available_slots() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT * FROM parking_slots WHERE status = 'empty'");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get available slots error: " . $e->getMessage());
        return [];
    }
}

// Get slot by ID
function get_slot($slot_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM parking_slots WHERE id = :slot_id");
        $stmt->bindParam(':slot_id', $slot_id);
        $stmt->execute();
        
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Get slot error: " . $e->getMessage());
        return false;
    }
}

// Update slot status
function update_slot_status($slot_id, $status, $rfid = null) {
    global $pdo;
    
    try {
        if ($rfid) {
            $stmt = $pdo->prepare("UPDATE parking_slots SET status = :status, rfid_assigned = :rfid WHERE id = :slot_id");
            $stmt->bindParam(':rfid', $rfid);
        } else {
            $stmt = $pdo->prepare("UPDATE parking_slots SET status = :status WHERE id = :slot_id");
        }
        
        $stmt->bindParam(':slot_id', $slot_id);
        $stmt->bindParam(':status', $status);
        $stmt->execute();
        
        return true;
    } catch (PDOException $e) {
        error_log("Update slot error: " . $e->getMessage());
        return false;
    }
}

// Vehicle Functions
// ----------------

// Get available RFID from pool
function get_available_rfid() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT uid FROM rfid_pool WHERE status = 'available' LIMIT 1");
        $rfid = $stmt->fetch();
        
        if ($rfid) {
            // Mark as assigned
            $stmt = $pdo->prepare("UPDATE rfid_pool SET status = 'assigned' WHERE uid = :uid");
            $stmt->bindParam(':uid', $rfid['uid']);
            $stmt->execute();
            
            return $rfid['uid'];
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Get RFID error: " . $e->getMessage());
        return false;
    }
}

// Release RFID back to pool
function release_rfid($uid) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE rfid_pool SET status = 'available' WHERE uid = :uid");
        $stmt->bindParam(':uid', $uid);
        $stmt->execute();
        
        return true;
    } catch (PDOException $e) {
        error_log("Release RFID error: " . $e->getMessage());
        return false;
    }
}

// Record vehicle entry
function record_vehicle_entry($license_plate, $slot_id, $rfid, $image_path = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO vehicles (license_plate, slot_id, rfid_tag, entry_time, entry_image, status) 
                              VALUES (:license_plate, :slot_id, :rfid, NOW(), :image, 'in_parking')");
        $stmt->bindParam(':license_plate', $license_plate);
        $stmt->bindParam(':slot_id', $slot_id);
        $stmt->bindParam(':rfid', $rfid);
        $stmt->bindParam(':image', $image_path);
        $stmt->execute();
        
        $vehicle_id = $pdo->lastInsertId();
        
        // Update slot status
        update_slot_status($slot_id, 'occupied', $rfid);
        
        // Log entry
        log_activity('vehicle_entry', "Vehicle $license_plate entered parking at slot $slot_id");
        
        return $vehicle_id;
    } catch (PDOException $e) {
        error_log("Record entry error: " . $e->getMessage());
        return false;
    }
}

// Record vehicle exit
function record_vehicle_exit($vehicle_id, $image_path = null) {
    global $pdo;
    
    try {
        // Get vehicle info
        $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = :id AND status = 'in_parking'");
        $stmt->bindParam(':id', $vehicle_id);
        $stmt->execute();
        
        $vehicle = $stmt->fetch();
        
        if (!$vehicle) {
            return false;
        }
        
        // Update vehicle record
        $stmt = $pdo->prepare("UPDATE vehicles SET exit_time = NOW(), exit_image = :image, status = 'exited' WHERE id = :id");
        $stmt->bindParam(':id', $vehicle_id);
        $stmt->bindParam(':image', $image_path);
        $stmt->execute();
        
        // Release RFID
        release_rfid($vehicle['rfid_tag']);
        
        // Update slot status
        update_slot_status($vehicle['slot_id'], 'empty');
        
        // Log exit
        log_activity('vehicle_exit', "Vehicle {$vehicle['license_plate']} exited from slot {$vehicle['slot_id']}");
        
        return $vehicle;
    } catch (PDOException $e) {
        error_log("Record exit error: " . $e->getMessage());
        return false;
    }
}

// Get vehicle by license plate
function get_vehicle_by_license($license_plate) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE license_plate = :license_plate AND status = 'in_parking'");
        $stmt->bindParam(':license_plate', $license_plate);
        $stmt->execute();
        
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Get vehicle error: " . $e->getMessage());
        return false;
    }
}

// Get vehicle by RFID
function get_vehicle_by_rfid($rfid) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE rfid_tag = :rfid AND status = 'in_parking'");
        $stmt->bindParam(':rfid', $rfid);
        $stmt->execute();
        
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Get vehicle by RFID error: " . $e->getMessage());
        return false;
    }
}

// Calculate parking fee
function calculate_parking_fee($entry_time, $exit_time) {
    $entry = new DateTime($entry_time);
    $exit = new DateTime($exit_time);
    
    $diff = $exit->diff($entry);
    $hours = $diff->h + ($diff->days * 24);
    
    // If less than 1 hour, count as 1 hour
    if ($hours < 1 && ($diff->i > 0 || $diff->s > 0)) {
        $hours = 1;
    }
    
    return $hours * HOURLY_RATE;
}

// Booking Functions
// ----------------

// Create booking
function create_booking($user_id, $slot_id, $license_plate, $start_time, $end_time) {
    global $pdo;
    
    try {
        // Validate inputs
        if (empty($user_id) || empty($slot_id) || empty($license_plate) || empty($start_time) || empty($end_time)) {
            return ['success' => false, 'message' => 'Thiếu thông tin cần thiết!'];
        }
        
        // Validate time format
        $start_datetime = DateTime::createFromFormat('Y-m-d H:i:s', $start_time);
        $end_datetime = DateTime::createFromFormat('Y-m-d H:i:s', $end_time);
        
        if (!$start_datetime || !$end_datetime) {
            return ['success' => false, 'message' => 'Định dạng thời gian không hợp lệ!'];
        }
        
        // Check if end time is after start time
        if ($end_datetime <= $start_datetime) {
            return ['success' => false, 'message' => 'Thời gian kết thúc phải sau thời gian bắt đầu!'];
        }
        
        // Check if the selected slot exists and is available
        $stmt = $pdo->prepare("SELECT id, status FROM parking_slots WHERE id = ?");
        $stmt->execute([$slot_id]);
        $slot = $stmt->fetch();
        
        if (!$slot) {
            return ['success' => false, 'message' => 'Vị trí đỗ xe không tồn tại!'];
        }
        
        if ($slot['status'] !== 'empty') {
            return ['success' => false, 'message' => 'Vị trí đỗ xe đã được sử dụng hoặc bảo trì!'];
        }
        
        // Check if slot is available for the time period
        // FIX: Đổi tên parameter để tránh conflict
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings 
                              WHERE slot_id = ? 
                              AND status IN ('pending', 'confirmed') 
                              AND (
                                  (start_time <= ? AND end_time >= ?) OR
                                  (start_time <= ? AND end_time >= ?) OR
                                  (start_time >= ? AND end_time <= ?)
                              )");
        $stmt->execute([$slot_id, $start_time, $start_time, $end_time, $end_time, $start_time, $end_time]);
        
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Slot đã được đặt trong khoảng thời gian này!'];
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        try {
            // Insert booking
            $stmt = $pdo->prepare("INSERT INTO bookings (user_id, slot_id, license_plate, start_time, end_time, status) 
                                  VALUES (?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$user_id, $slot_id, $license_plate, $start_time, $end_time]);
            
            $booking_id = $pdo->lastInsertId();
            
            // Calculate booking fee (hours * rate)
            $diff = $end_datetime->diff($start_datetime);
            $hours = $diff->h + ($diff->days * 24);
            
            // Add minutes as fractional hour if needed
            if ($diff->i > 0) {
                $hours += 1; // Round up to next hour
            }
            
            // Minimum 1 hour
            if ($hours < 1) {
                $hours = 1;
            }
            
            $amount = $hours * HOURLY_RATE;
            
            // Create payment entry
            $payment_ref = 'BOOK-' . time() . '-' . $booking_id;
            $stmt = $pdo->prepare("INSERT INTO payments (user_id, booking_id, amount, payment_ref, status) 
                                  VALUES (?, ?, ?, ?, 'pending')");
            $stmt->execute([$user_id, $booking_id, $amount, $payment_ref]);
            
            $payment_id = $pdo->lastInsertId();
            
            // Commit transaction
            $pdo->commit();
            
            // Log booking
            log_activity('booking_created', "Booking created for slot $slot_id by user $user_id", $user_id);
            
            return [
                'success' => true, 
                'booking_id' => $booking_id,
                'payment_id' => $payment_id,
                'payment_ref' => $payment_ref,
                'amount' => $amount
            ];
            
        } catch (Exception $e) {
            // Rollback transaction
            $pdo->rollback();
            throw $e;
        }
        
    } catch (PDOException $e) {
        error_log("Create booking error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Lỗi database: ' . $e->getMessage()];
    } catch (Exception $e) {
        error_log("Create booking error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()];
    }
}

// Get user bookings
function get_user_bookings($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT b.*, p.status as payment_status, p.amount 
                              FROM bookings b 
                              LEFT JOIN payments p ON b.id = p.booking_id 
                              WHERE b.user_id = :user_id 
                              ORDER BY b.created_at DESC");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get bookings error: " . $e->getMessage());
        return [];
    }
}

// Get booking by ID
function get_booking($booking_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT b.*, u.username, u.email, u.full_name, p.status as payment_status, p.amount, p.payment_ref 
                              FROM bookings b 
                              JOIN users u ON b.user_id = u.id 
                              LEFT JOIN payments p ON b.id = p.booking_id 
                              WHERE b.id = :booking_id");
        $stmt->bindParam(':booking_id', $booking_id);
        $stmt->execute();
        
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Get booking error: " . $e->getMessage());
        return false;
    }
}

// Cancel booking
function cancel_booking($booking_id, $user_id) {
    global $pdo;
    
    try {
        // Check if booking belongs to user
        $stmt = $pdo->prepare("SELECT status FROM bookings WHERE id = :booking_id AND user_id = :user_id");
        $stmt->bindParam(':booking_id', $booking_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $booking = $stmt->fetch();
        
        if (!$booking) {
            return ['success' => false, 'message' => 'Booking không tồn tại hoặc không thuộc về bạn!'];
        }
        
        if ($booking['status'] === 'completed') {
            return ['success' => false, 'message' => 'Không thể hủy booking đã hoàn thành!'];
        }
        
        // Update booking status
        $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = :booking_id");
        $stmt->bindParam(':booking_id', $booking_id);
        $stmt->execute();
        
        // Log cancellation
        log_activity('booking_cancelled', "Booking #$booking_id cancelled", $user_id);
        
        return ['success' => true, 'message' => 'Hủy booking thành công!'];
    } catch (PDOException $e) {
        error_log("Cancel booking error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Lỗi hệ thống. Vui lòng thử lại sau!'];
    }
}

// Payment Functions
// ----------------

// Generate QR code for payment
function generate_payment_qr($payment_id) {
    global $pdo;

    try {
        // Get payment info
        $stmt = $pdo->prepare("SELECT p.*, v.license_plate, b.slot_id, u.full_name, u.email
                              FROM payments p 
                              LEFT JOIN vehicles v ON p.vehicle_id = v.id 
                              LEFT JOIN bookings b ON p.booking_id = b.id
                              LEFT JOIN users u ON p.user_id = u.id
                              WHERE p.id = ? AND p.status = 'pending'");
        $stmt->execute([$payment_id]);
        
        $payment = $stmt->fetch();
        
        if (!$payment) {
            return ['success' => false, 'message' => 'Thanh toán không tồn tại hoặc đã hoàn thành!'];
        }
        
        // Create reference if not exists
        $reference = $payment['payment_ref'];
        if (empty($reference)) {
            $reference = 'XPARK-' . time() . '-' . $payment_id;
            
            $stmt = $pdo->prepare("UPDATE payments SET payment_ref = ? WHERE id = ?");
            $stmt->execute([$reference, $payment_id]);
        }
        
        // Tạo QR bằng SePay QR API
        $amount = intval($payment['amount']);
        $description = urlencode("XParking " . $reference);
        
        $qr_url = sprintf(
            "%s?acc=%s&bank=%s&amount=%d&des=%s&template=%s",
            SEPAY_QR_API,
            VIETQR_ACCOUNT_NO,
            VIETQR_BANK_ID,
            $amount,
            $description,
            VIETQR_TEMPLATE
        );
        
        // Update QR code URL and transaction ID in database
        $transaction_id = 'SEPAY-QR-' . time() . '-' . $payment_id;
        $stmt = $pdo->prepare("UPDATE payments SET qr_code = ?, sepay_ref = ? WHERE id = ?");
        $stmt->execute([$qr_url, $transaction_id, $payment_id]);

        // Log for debugging
        error_log("Generated SePay QR URL: " . $qr_url);
        error_log("Payment reference: " . $reference);
        
        return [
            'success' => true,
            'qr_code' => $qr_url,
            'reference' => $reference,
            'amount' => $payment['amount'],
            'transaction_id' => $transaction_id,
            'bank_info' => [
                'bank' => VIETQR_BANK_ID,
                'account' => VIETQR_ACCOUNT_NO,
                'name' => VIETQR_ACCOUNT_NAME
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Generate QR error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()];
    }
}

// Process payment webhook
function process_payment_webhook($transaction_id, $status, $reference) {
    global $pdo;
    
    try {
        // Log webhook data
        error_log("Processing SePay webhook - TxnID: $transaction_id, Status: $status, Ref: $reference");
        
        // Find payment by reference
        $stmt = $pdo->prepare("SELECT p.*, u.full_name, u.email, b.slot_id, v.license_plate
                              FROM payments p 
                              LEFT JOIN users u ON p.user_id = u.id
                              LEFT JOIN bookings b ON p.booking_id = b.id
                              LEFT JOIN vehicles v ON p.vehicle_id = v.id
                              WHERE p.payment_ref = ?");
        $stmt->execute([$reference]);
        
        $payment = $stmt->fetch();
        
        if (!$payment) {
            error_log("Payment not found for reference: $reference");
            return false;
        }
        
        // Check if already processed
        if ($payment['status'] === 'completed') {
            error_log("Payment already completed: $reference");
            return true;
        }
        
        $pdo->beginTransaction();
        
        try {
            // Update payment status
            $payment_status = ($status === 'success' || $status === 'completed') ? 'completed' : 'failed';
            $payment_time = date('Y-m-d H:i:s');
            
            $stmt = $pdo->prepare("UPDATE payments SET 
                                  status = ?, 
                                  payment_time = ?, 
                                  sepay_ref = ? 
                                  WHERE id = ?");
            $stmt->execute([$payment_status, $payment_time, $transaction_id, $payment['id']]);
            
            if ($payment_status === 'completed') {
                // Process booking if exists
                if ($payment['booking_id']) {
                    // Update booking status
                    $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?");
                    $stmt->execute([$payment['booking_id']]);
                    
                    // Update slot status to reserved
                    $stmt = $pdo->prepare("UPDATE parking_slots ps 
                                          JOIN bookings b ON ps.id = b.slot_id 
                                          SET ps.status = 'reserved' 
                                          WHERE b.id = ?");
                    $stmt->execute([$payment['booking_id']]);
                }
                
                // Process vehicle exit if exists
                if ($payment['vehicle_id']) {
                    $stmt = $pdo->prepare("UPDATE vehicles SET status = 'exited', exit_time = ? WHERE id = ?");
                    $stmt->execute([$payment_time, $payment['vehicle_id']]);
                    
                    // Release slot and RFID
                    $stmt = $pdo->prepare("SELECT slot_id, rfid_tag FROM vehicles WHERE id = ?");
                    $stmt->execute([$payment['vehicle_id']]);
                    $vehicle = $stmt->fetch();
                    
                    if ($vehicle && $vehicle['slot_id']) {
                        $stmt = $pdo->prepare("UPDATE parking_slots SET status = 'empty', rfid_assigned = 'empty' WHERE id = ?");
                        $stmt->execute([$vehicle['slot_id']]);
                    }
                    
                    if ($vehicle && $vehicle['rfid_tag']) {
                        $stmt = $pdo->prepare("UPDATE rfid_pool SET status = 'available' WHERE uid = ?");
                        $stmt->execute([$vehicle['rfid_tag']]);
                    }
                }
                
                $pdo->commit();
                
                // Send notification emails
                send_payment_notifications($payment, $payment_status);
                
                // Log success
                log_activity('payment_completed', "SePay payment completed: $reference, TxnID: $transaction_id");
                
            } else {
                $pdo->commit();
                error_log("Payment failed: $reference - Status: $status");
            }
            
            return true;
            
        } catch (Exception $e) {
            $pdo->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Process SePay webhook error: " . $e->getMessage());
        return false;
    }
}

// Hàm gửi email thông báo
function send_payment_notifications($payment, $status) {
    try {
        $amount_formatted = number_format($payment['amount'], 0, ',', '.');
        
        if ($status === 'completed') {
            // Email cho khách hàng (nếu có)
            if ($payment['email']) {
                $customer_subject = "XParking - Xác nhận thanh toán thành công";
                $customer_message = generate_customer_email_template($payment, $amount_formatted);
                send_email($payment['email'], $customer_subject, $customer_message);
            }
            
            // Email cho admin
            $admin_subject = "XParking - Thanh toán mới: {$payment['payment_ref']}";
            $admin_message = generate_admin_email_template($payment, $amount_formatted);
            send_email(ADMIN_EMAIL, $admin_subject, $admin_message);
            
            error_log("Payment notification emails sent for: " . $payment['payment_ref']);
        }
        
    } catch (Exception $e) {
        error_log("Send notification error: " . $e->getMessage());
    }
}

// Template email cho khách hàng
function generate_customer_email_template($payment, $amount_formatted) {
    $payment_time = date('d/m/Y H:i:s', strtotime($payment['payment_time'] ?? 'now'));
    $customer_name = $payment['full_name'] ?: 'Quý khách';
    
    $details = '';
    if ($payment['slot_id']) {
        $details = "<tr><td><strong>Vị trí đỗ xe:</strong></td><td>{$payment['slot_id']}</td></tr>";
    }
    if ($payment['license_plate']) {
        $details .= "<tr><td><strong>Biển số xe:</strong></td><td>{$payment['license_plate']}</td></tr>";
    }
    
    return "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: #2563eb; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
                <h1 style='margin: 0; font-size: 24px;'>🅿️ XParking</h1>
                <p style='margin: 5px 0 0 0;'>Xác nhận thanh toán</p>
            </div>
            
            <div style='background: #f9fafb; padding: 20px; border: 1px solid #e5e7eb;'>
                <h2 style='color: #059669; margin-top: 0;'>✅ Thanh toán thành công!</h2>
                <p>Xin chào <strong>{$customer_name}</strong>,</p>
                <p>Cảm ơn bạn đã sử dụng dịch vụ XParking. Thanh toán của bạn đã được xử lý thành công.</p>
                
                <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                    <tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Mã thanh toán:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$payment['payment_ref']}</td></tr>
                    <tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Số tiền:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd; color: #2563eb; font-weight: bold;'>{$amount_formatted}₫</td></tr>
                    <tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Thời gian:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$payment_time}</td></tr>
                    {$details}
                </table>
                
                <p style='color: #059669;'><strong>Trạng thái: Đã thanh toán thành công</strong></p>
            </div>
            
            <div style='background: white; padding: 20px; border: 1px solid #e5e7eb; border-top: 0; border-radius: 0 0 8px 8px;'>
                <p style='margin: 0; color: #6b7280; font-size: 14px;'>
                    Nếu có bất kỳ thắc mắc nào, vui lòng liên hệ: <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a>
                </p>
            </div>
        </div>
    </body>
    </html>";
}

// Template email cho admin
function generate_admin_email_template($payment, $amount_formatted) {
    $payment_time = date('d/m/Y H:i:s', strtotime($payment['payment_time'] ?? 'now'));
    $customer_name = $payment['full_name'] ?: 'Khách vãng lai';
    $customer_email = $payment['email'] ?: 'Không có';
    
    $type = $payment['booking_id'] ? 'Đặt chỗ trước' : 'Thanh toán ra bãi';
    
    $details = '';
    if ($payment['slot_id']) {
        $details = "<tr><td><strong>Vị trí đỗ xe:</strong></td><td>{$payment['slot_id']}</td></tr>";
    }
    if ($payment['license_plate']) {
        $details .= "<tr><td><strong>Biển số xe:</strong></td><td>{$payment['license_plate']}</td></tr>";
    }
    
    return "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: #dc2626; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
                <h1 style='margin: 0; font-size: 24px;'>🅿️ XParking Admin</h1>
                <p style='margin: 5px 0 0 0;'>Thông báo thanh toán mới</p>
            </div>
            
            <div style='background: #f9fafb; padding: 20px; border: 1px solid #e5e7eb;'>
                <h2 style='color: #dc2626; margin-top: 0;'>💰 Thanh toán thành công!</h2>
                <p>Có một thanh toán mới đã được xử lý thành công.</p>
                
                <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                    <tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Loại giao dịch:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$type}</td></tr>
                    <tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Mã thanh toán:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$payment['payment_ref']}</td></tr>
                    <tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Số tiền:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd; color: #059669; font-weight: bold;'>{$amount_formatted}₫</td></tr>
                    <tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Thời gian:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$payment_time}</td></tr>
                    <tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Khách hàng:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$customer_name}</td></tr>
                    <tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Email:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$customer_email}</td></tr>
                    {$details}
                </table>
                
                <p style='color: #059669;'><strong>Trạng thái: Đã xử lý thành công</strong></p>
            </div>
            
            <div style='background: white; padding: 20px; border: 1px solid #e5e7eb; border-top: 0; border-radius: 0 0 8px 8px;'>
                <p style='margin: 0; color: #6b7280; font-size: 14px;'>
                    Đăng nhập <a href='" . SITE_URL . "/admin.php'>Admin Panel</a> để xem chi tiết.
                </p>
            </div>
        </div>
    </body>
    </html>";
}
// Check payment status
function check_payment_status($payment_ref) {
    global $pdo;
    
    try {
        // Validate payment reference
        if (empty($payment_ref)) {
            return 'unknown';
        }
        
        // FIX: Lấy created_at thay vì dựa vào payment_time
        $stmt = $pdo->prepare("SELECT status, created_at FROM payments WHERE payment_ref = :reference");
        $stmt->bindParam(':reference', $payment_ref);
        $stmt->execute();
        
        $payment = $stmt->fetch();
        
        if (!$payment) {
            return 'not_found';
        }
        
        // FIX: Check if payment has expired dựa trên created_at
        if ($payment['status'] === 'pending') {
            $created = new DateTime($payment['created_at']);
            $now = new DateTime();
            $diff = $now->getTimestamp() - $created->getTimestamp();
            
            // If more than QR_EXPIRE_MINUTES minutes passed
            if ($diff > (QR_EXPIRE_MINUTES * 60)) {
                // Update status to expired
                $stmt = $pdo->prepare("UPDATE payments SET status = 'expired' WHERE payment_ref = :reference");
                $stmt->bindParam(':reference', $payment_ref);
                $stmt->execute();
                
                return 'expired';
            }
        }
        
        return $payment['status'];
    } catch (PDOException $e) {
        error_log("Check payment status error: " . $e->getMessage());
        return 'error';
    }
}

// Create exit payment
function create_exit_payment($vehicle_id, $amount) {
    global $pdo;
    
    try {
        // Tạo mã tham chiếu thanh toán
        $payment_ref = 'EXIT-' . time() . '-' . $vehicle_id;
        
        // Lấy thông tin người dùng từ xe (nếu có)
        $stmt = $pdo->prepare("SELECT user_id FROM vehicles WHERE id = :vehicle_id");
        $stmt->bindParam(':vehicle_id', $vehicle_id);
        $stmt->execute();
        $user_id = $stmt->fetchColumn();
        
        // Tạo bản ghi thanh toán
        $stmt = $pdo->prepare("INSERT INTO payments (vehicle_id, user_id, amount, payment_ref, status) 
                              VALUES (:vehicle_id, :user_id, :amount, :payment_ref, 'pending')");
        $stmt->bindParam(':vehicle_id', $vehicle_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':amount', $amount);
        $stmt->bindParam(':payment_ref', $payment_ref);
        $stmt->execute();
        
        $payment_id = $pdo->lastInsertId();
        
        return [
            'success' => true,
            'payment_id' => $payment_id,
            'payment_ref' => $payment_ref
        ];
    } catch (PDOException $e) {
        error_log("Create exit payment error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Lỗi hệ thống. Vui lòng thử lại sau!'];
    }
}


// Mock license plate recognition (for demonstration)
function recognize_license_plate($image_path) {
    // In a real system, this would call an OCR API or ML model
    // For demo, we'll randomly select from a list of plates
    $sample_plates = ['51F-123.45', '59A-789.12', '30E-567.89', '29H-234.56', '43B-876.54'];
    return $sample_plates[array_rand($sample_plates)];
}

// Check if vehicle has a booking
function check_vehicle_booking($license_plate) {
    global $pdo;
    
    try {
        $now = date('Y-m-d H:i:s');
        
        $stmt = $pdo->prepare("SELECT b.*, u.full_name, u.email 
                              FROM bookings b 
                              JOIN users u ON b.user_id = u.id 
                              WHERE b.license_plate = :license_plate 
                              AND b.status = 'confirmed' 
                              AND :now BETWEEN b.start_time AND b.end_time");
        $stmt->bindParam(':license_plate', $license_plate);
        $stmt->bindParam(':now', $now);
        $stmt->execute();
        
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Check booking error: " . $e->getMessage());
        return false;
    }
}
?>