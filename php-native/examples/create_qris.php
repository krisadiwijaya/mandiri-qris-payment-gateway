<?php

/**
 * Example: Create QRIS Payment
 * 
 * This example demonstrates how to create a QRIS dynamic code
 */

require_once __DIR__ . '/../src/MandiriQris.php';

// Load environment variables (if using vlucas/phpdotenv)
// $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
// $dotenv->load();

// Start session for token caching
session_start();

try {
    // Initialize Mandiri QRIS client
    $mandiri = new MandiriQris([
        'client_id' => 'your_client_id_here',
        'client_secret' => 'your_client_secret_here',
        'environment' => 'sandbox', // or 'production'
        'merchant_nmid' => 'YOUR_NMID',
        'merchant_name' => 'YOUR MERCHANT NAME',
        'merchant_city' => 'JAKARTA',
        'qris_expiry_minutes' => 30 // Optional, default 30
    ]);

    // Prepare payment data
    $paymentData = [
        'amount' => 150000, // Rp 150,000
        'reference' => 'ORDER-' . time(), // Unique reference
        'callback_url' => 'https://yourdomain.com/webhook.php' // Optional
    ];

    echo "Creating QRIS payment...\n";
    echo "Amount: Rp " . number_format($paymentData['amount'], 0, ',', '.') . "\n";
    echo "Reference: " . $paymentData['reference'] . "\n\n";

    // Create QRIS
    $result = $mandiri->createQris($paymentData);

    // Display result
    echo "✓ QRIS created successfully!\n\n";
    echo "QR ID: " . $result['qr_id'] . "\n";
    echo "Status: " . $result['status'] . "\n";
    echo "Expired At: " . $result['expired_at'] . "\n";
    echo "QR Image URL: " . $result['qr_image_url'] . "\n\n";
    
    echo "QR String (truncated): " . substr($result['qr_string'], 0, 50) . "...\n\n";

    // In a web application, you would:
    // 1. Save this data to database
    // 2. Display QR code image to user
    // 3. Start polling for payment status

    // Example: Save to database
    /*
    $db = new PDO('mysql:host=localhost;dbname=yourdb', 'user', 'pass');
    $stmt = $db->prepare("
        INSERT INTO payments (payment_id, order_id, amount, qr_id, qr_string, qr_image_url, status, expired_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        'PAY-' . $result['reference'],
        $result['reference'],
        $result['amount'],
        $result['qr_id'],
        $result['qr_string'],
        $result['qr_image_url'],
        'pending',
        $result['expired_at']
    ]);
    */

    // Return JSON response (for API)
    if (isset($_GET['json'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $result
        ]);
    }

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    
    if (isset($_GET['json'])) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
