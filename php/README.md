# PHP SDK for Mandiri QRIS Payment Gateway

PHP SDK for integrating Mandiri QRIS Payment Gateway with OAuth 2.0 authentication.

## Installation

```bash
composer require mandiri/qris-php
```

## Requirements

- PHP >= 7.4
- ext-json
- ext-curl

## Usage

### Basic Usage

```php
<?php

require 'vendor/autoload.php';

use MandiriQris\Client;

// Initialize client
$client = new Client([
    'client_id' => 'your_client_id',
    'client_secret' => 'your_client_secret',
    'base_url' => 'https://api.mandiri.co.id',
    'sandbox' => true
]);

// Generate QR Code
$qr = $client->generateQR([
    'amount' => 100000,
    'merchant_id' => 'MERCHANT123',
    'terminal_id' => 'TERM001',
    'customer_name' => 'John Doe',
    'customer_phone' => '081234567890'
]);

echo "Transaction ID: " . $qr['transaction_id'] . "\n";
echo "QR String: " . $qr['qr_string'] . "\n";

// Check payment status
$status = $client->checkPaymentStatus($qr['transaction_id']);
echo "Payment Status: " . $status['status'] . "\n";
```

### Webhook Handler

```php
<?php

$client = new Client([...]);

$rawPayload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';

try {
    $payload = $client->handleWebhook($rawPayload, $signature);
    
    if ($payload['status'] === 'SUCCESS') {
        // Payment successful
        // Update database, send confirmation, etc.
    }
    
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
```

### Payment Polling

```php
// Poll every 5 seconds for up to 5 minutes
$finalStatus = $client->pollPaymentStatus($transactionId, 60, 5);

if ($finalStatus['status'] === 'SUCCESS') {
    echo "Payment completed!\n";
}
```

## Configuration

Set environment variables:

```bash
MANDIRI_CLIENT_ID=your_client_id
MANDIRI_CLIENT_SECRET=your_client_secret
MANDIRI_BASE_URL=https://api.mandiri.co.id
```

## Examples

See the [examples](examples/) directory for complete examples.

## License

MIT
