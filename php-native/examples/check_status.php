<?php

/**
 * Example: Check QRIS Payment Status
 * 
 * This example demonstrates how to check the payment status of a QRIS code
 */

require_once __DIR__ . '/../src/MandiriQris.php';

// Start session for token caching
session_start();

// Get QR ID from command line or GET parameter
$qrId = $argv[1] ?? $_GET['qr_id'] ?? null;

if (!$qrId) {
    echo "Usage: php check_status.php QR_ID\n";
    echo "   or: check_status.php?qr_id=QR_ID\n";
    exit(1);
}

try {
    // Initialize Mandiri QRIS client
    $mandiri = new MandiriQris([
        'client_id' => 'your_client_id_here',
        'client_secret' => 'your_client_secret_here',
        'environment' => 'sandbox',
        'merchant_nmid' => 'YOUR_NMID',
        'merchant_name' => 'YOUR MERCHANT NAME',
        'merchant_city' => 'JAKARTA'
    ]);

    echo "Checking payment status...\n";
    echo "QR ID: " . $qrId . "\n\n";

    // Check status
    $status = $mandiri->checkStatus($qrId);

    // Display result
    echo "Payment Status: " . $status['status'] . "\n";
    
    if ($status['status'] === 'COMPLETED' || $status['status'] === 'PAID') {
        echo "✓ Payment successful!\n";
        echo "Amount: Rp " . number_format($status['amount'], 0, ',', '.') . "\n";
        echo "Paid At: " . ($status['paid_at'] ?? 'N/A') . "\n";
        
        if (isset($status['transaction_id'])) {
            echo "Transaction ID: " . $status['transaction_id'] . "\n";
        }

        // Update database
        /*
        $db = new PDO('mysql:host=localhost;dbname=yourdb', 'user', 'pass');
        $stmt = $db->prepare("UPDATE payments SET status = 'paid', paid_at = NOW() WHERE qr_id = ?");
        $stmt->execute([$qrId]);
        
        // Update booking/order status
        $stmt = $db->prepare("UPDATE bookings SET payment_status = 'paid', status = 'confirmed' WHERE payment_qr_id = ?");
        $stmt->execute([$qrId]);
        
        // Send confirmation email
        // mail($customerEmail, 'Payment Confirmed', 'Your payment has been received...');
        */
        
    } elseif ($status['status'] === 'PENDING') {
        echo "⏳ Waiting for payment...\n";
        
    } elseif ($status['status'] === 'EXPIRED') {
        echo "⏱ QR code has expired\n";
        echo "Please generate a new QR code\n";
        
        // Update database
        /*
        $db = new PDO('mysql:host=localhost;dbname=yourdb', 'user', 'pass');
        $stmt = $db->prepare("UPDATE payments SET status = 'expired' WHERE qr_id = ?");
        $stmt->execute([$qrId]);
        */
        
    } elseif ($status['status'] === 'FAILED') {
        echo "✗ Payment failed\n";
        
    } else {
        echo "Status: " . $status['status'] . "\n";
    }

    // Return JSON response (for AJAX polling)
    if (isset($_GET['json']) || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $status
        ]);
    }

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    
    if (isset($_GET['json']) || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
