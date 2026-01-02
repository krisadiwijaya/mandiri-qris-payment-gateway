<?php

require __DIR__ . '/../vendor/autoload.php';

use MandiriQris\Client;

// Initialize client
$client = new Client([
    'client_id' => getenv('MANDIRI_CLIENT_ID') ?: 'your_client_id',
    'client_secret' => getenv('MANDIRI_CLIENT_SECRET') ?: 'your_client_secret',
    'sandbox' => true
]);

// Get raw POST body
$rawPayload = file_get_contents('php://input');

// Get signature from header
$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';

try {
    // Handle webhook
    $payload = $client->handleWebhook($rawPayload, $signature);
    
    echo "Webhook received successfully!\n";
    echo "Transaction ID: " . $payload['transaction_id'] . "\n";
    echo "Status: " . $payload['status'] . "\n";
    echo "Amount: " . $payload['amount'] . "\n";
    
    // Process payment based on status
    if ($payload['status'] === 'SUCCESS') {
        // Update your database, send confirmation email, etc.
        echo "Payment successful - updating database...\n";
    } elseif ($payload['status'] === 'FAILED') {
        // Handle failed payment
        echo "Payment failed - notifying customer...\n";
    }
    
    // Return 200 OK
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    
} catch (Exception $e) {
    error_log('Webhook error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
