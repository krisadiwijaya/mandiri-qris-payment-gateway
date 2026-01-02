<?php

/**
 * Example: Webhook Handler for Mandiri QRIS Payment
 * 
 * This endpoint receives payment notifications from Mandiri API
 * Configure this URL in your Mandiri dashboard or pass it as callback_url
 */

require_once __DIR__ . '/../src/MandiriQris.php';

// Log all webhooks for debugging
$logFile = __DIR__ . '/webhook.log';
$payload = file_get_contents('php://input');
$timestamp = date('Y-m-d H:i:s');

file_put_contents($logFile, "\n\n=== Webhook Received at {$timestamp} ===\n", FILE_APPEND);
file_put_contents($logFile, "Headers:\n" . json_encode(getallheaders(), JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
file_put_contents($logFile, "Payload:\n{$payload}\n", FILE_APPEND);

try {
    // Parse payload
    $data = json_decode($payload, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON payload');
    }

    // Verify signature (if Mandiri implements this)
    /*
    $signature = $_SERVER['HTTP_X_MANDIRI_SIGNATURE'] ?? '';
    $expectedSignature = hash_hmac('sha256', $payload, 'your_webhook_secret');
    
    if (!hash_equals($expectedSignature, $signature)) {
        http_response_code(401);
        die(json_encode(['error' => 'Invalid signature']));
    }
    */

    // Extract payment info
    $qrId = $data['qr_id'] ?? null;
    $status = $data['status'] ?? null;
    $amount = $data['amount'] ?? null;
    $transactionId = $data['transaction_id'] ?? null;

    if (!$qrId || !$status) {
        throw new Exception('Missing required fields: qr_id or status');
    }

    file_put_contents($logFile, "Processing: QR ID = {$qrId}, Status = {$status}\n", FILE_APPEND);

    // Process payment based on status
    if ($status === 'COMPLETED' || $status === 'PAID') {
        
        // Connect to database
        /*
        $db = new PDO('mysql:host=localhost;dbname=yourdb', 'username', 'password');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Update payment status
        $stmt = $db->prepare("
            UPDATE payments 
            SET status = 'paid', 
                paid_at = NOW(),
                transaction_id = ?
            WHERE qr_id = ? AND status = 'pending'
        ");
        $stmt->execute([$transactionId, $qrId]);
        $updatedRows = $stmt->rowCount();

        if ($updatedRows > 0) {
            file_put_contents($logFile, "Payment updated successfully\n", FILE_APPEND);

            // Get payment details
            $stmt = $db->prepare("SELECT * FROM payments WHERE qr_id = ?");
            $stmt->execute([$qrId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            // Update related booking/order
            $stmt = $db->prepare("
                UPDATE bookings 
                SET payment_status = 'paid', 
                    status = 'confirmed',
                    confirmed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$payment['booking_id']]);

            // Log activity
            $stmt = $db->prepare("
                INSERT INTO user_activities (user_id, action, description, created_at)
                VALUES (?, 'payment_received', ?, NOW())
            ");
            $stmt->execute([
                $payment['user_id'],
                "Payment received for booking #{$payment['booking_id']} via QRIS"
            ]);

            // Send email notification to customer
            $stmt = $db->prepare("
                SELECT u.email, u.name, b.* 
                FROM users u 
                JOIN bookings b ON b.user_id = u.id 
                WHERE b.id = ?
            ");
            $stmt->execute([$payment['booking_id']]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($booking) {
                $to = $booking['email'];
                $subject = 'Payment Confirmation - Booking #' . $booking['id'];
                $message = "Dear {$booking['name']},\n\n";
                $message .= "Your payment has been confirmed!\n\n";
                $message .= "Booking ID: {$booking['id']}\n";
                $message .= "Amount: Rp " . number_format($amount, 0, ',', '.') . "\n";
                $message .= "Status: Confirmed\n\n";
                $message .= "Thank you for your payment.\n";

                mail($to, $subject, $message);
                file_put_contents($logFile, "Confirmation email sent to {$to}\n", FILE_APPEND);
            }

            // Send notification to admin (optional)
            // mail('admin@yoursite.com', 'New Payment Received', "Payment of Rp " . number_format($amount, 0, ',', '.') . " received");

        } else {
            file_put_contents($logFile, "No payment found or already processed\n", FILE_APPEND);
        }
        */

        // Simplified version without database
        file_put_contents($logFile, "Payment COMPLETED: QR ID = {$qrId}, Amount = {$amount}\n", FILE_APPEND);

    } elseif ($status === 'EXPIRED') {
        
        // Update payment as expired
        /*
        $db = new PDO('mysql:host=localhost;dbname=yourdb', 'username', 'password');
        $stmt = $db->prepare("UPDATE payments SET status = 'expired' WHERE qr_id = ?");
        $stmt->execute([$qrId]);
        */
        
        file_put_contents($logFile, "Payment EXPIRED: QR ID = {$qrId}\n", FILE_APPEND);

    } elseif ($status === 'FAILED') {
        
        // Update payment as failed
        /*
        $db = new PDO('mysql:host=localhost;dbname=yourdb', 'username', 'password');
        $stmt = $db->prepare("UPDATE payments SET status = 'failed' WHERE qr_id = ?");
        $stmt->execute([$qrId]);
        */
        
        file_put_contents($logFile, "Payment FAILED: QR ID = {$qrId}\n", FILE_APPEND);
    }

    // Return success response
    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'message' => 'Webhook processed successfully',
        'qr_id' => $qrId
    ]);

    file_put_contents($logFile, "Response: 200 OK\n", FILE_APPEND);

} catch (Exception $e) {
    // Log error
    file_put_contents($logFile, "ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    
    // Return error response
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
