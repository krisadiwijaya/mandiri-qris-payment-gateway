<?php

require __DIR__ . '/../vendor/autoload.php';

use MandiriQris\Client;

// Initialize client
$client = new Client([
    'client_id' => getenv('MANDIRI_CLIENT_ID') ?: 'your_client_id',
    'client_secret' => getenv('MANDIRI_CLIENT_SECRET') ?: 'your_client_secret',
    'base_url' => getenv('MANDIRI_BASE_URL') ?: 'https://api.mandiri.co.id',
    'sandbox' => true
]);

try {
    // Generate QR Code
    echo "Generating QR Code...\n";
    $qr = $client->generateQR([
        'amount' => 100000,
        'merchant_id' => 'MERCHANT123',
        'terminal_id' => 'TERM001',
        'customer_name' => 'John Doe',
        'customer_phone' => '081234567890'
    ]);
    
    echo "QR Generated Successfully!\n";
    echo "Transaction ID: " . $qr['transaction_id'] . "\n";
    echo "QR String: " . $qr['qr_string'] . "\n\n";
    
    // Check payment status
    echo "Checking payment status...\n";
    $status = $client->checkPaymentStatus($qr['transaction_id']);
    echo "Status: " . $status['status'] . "\n";
    echo "Amount: " . $status['amount'] . "\n\n";
    
    // Poll payment status (optional)
    echo "Polling payment status (will wait up to 5 minutes)...\n";
    $finalStatus = $client->pollPaymentStatus($qr['transaction_id'], 60, 5);
    echo "Final Status: " . $finalStatus['status'] . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
