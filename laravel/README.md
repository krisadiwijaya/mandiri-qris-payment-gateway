# Laravel Integration for Mandiri QRIS Payment Gateway

Laravel package for integrating Mandiri QRIS Payment Gateway with OAuth 2.0 authentication.

## Installation

```bash
composer require mandiri/qris-laravel
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="MandiriQris\Laravel\ServiceProvider"
```

Add to your `.env`:

```env
MANDIRI_CLIENT_ID=your_client_id
MANDIRI_CLIENT_SECRET=your_client_secret
MANDIRI_BASE_URL=https://api.mandiri.co.id
MANDIRI_SANDBOX=true
MANDIRI_MERCHANT_ID=MERCHANT123
MANDIRI_TERMINAL_ID=TERM001
MANDIRI_WEBHOOK_PATH=/webhook/mandiri-qris
```

## Usage

### Using Facade

```php
use MandiriQris\Laravel\Facades\MandiriQris;

// Generate QR Code
$qr = MandiriQris::generateQR([
    'amount' => 100000,
    'customer_name' => 'John Doe',
    'customer_phone' => '081234567890'
]);

echo $qr['transaction_id'];
echo $qr['qr_string'];

// Check payment status
$status = MandiriQris::checkPaymentStatus($qr['transaction_id']);
echo $status['status'];
```

### Using Dependency Injection

```php
use MandiriQris\Laravel\Client;

class PaymentController extends Controller
{
    protected $mandiriQris;
    
    public function __construct(Client $mandiriQris)
    {
        $this->mandiriQris = $mandiriQris;
    }
    
    public function generateQR()
    {
        $qr = $this->mandiriQris->generateQR([
            'amount' => 100000,
            'customer_name' => 'John Doe'
        ]);
        
        return response()->json($qr);
    }
}
```

### Webhook Handler

The package automatically registers a webhook route at the path specified in `MANDIRI_WEBHOOK_PATH`.

Listen for payment events in your `EventServiceProvider`:

```php
use MandiriQris\Laravel\Events\PaymentReceived;

protected $listen = [
    PaymentReceived::class => [
        'App\Listeners\HandlePaymentReceived',
    ],
];
```

Create a listener:

```php
php artisan make:listener HandlePaymentReceived
```

```php
namespace App\Listeners;

use MandiriQris\Laravel\Events\PaymentReceived;

class HandlePaymentReceived
{
    public function handle(PaymentReceived $event)
    {
        $payload = $event->payload;
        
        if ($payload['status'] === 'SUCCESS') {
            // Update your database
            // Send confirmation email
            // etc.
        }
    }
}
```

### Custom Webhook Route

If you want to handle webhooks manually, set `MANDIRI_WEBHOOK_PATH` to null and create your own route:

```php
Route::post('/custom-webhook', function (Request $request) {
    $client = app('mandiri-qris');
    
    try {
        $payload = $client->handleWebhook(
            $request->getContent(),
            $request->header('X-Signature', '')
        );
        
        // Process payload
        
        return response()->json(['status' => 'ok']);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 400);
    }
});
```

### Payment Polling

```php
// Poll every 5 seconds for up to 5 minutes
$finalStatus = MandiriQris::pollPaymentStatus($transactionId, 60, 5);

if ($finalStatus['status'] === 'SUCCESS') {
    // Payment completed
}
```

## Configuration Options

All configuration options from `config/mandiri-qris.php`:

```php
return [
    'client_id' => env('MANDIRI_CLIENT_ID', ''),
    'client_secret' => env('MANDIRI_CLIENT_SECRET', ''),
    'base_url' => env('MANDIRI_BASE_URL', 'https://api.mandiri.co.id'),
    'sandbox' => env('MANDIRI_SANDBOX', false),
    'merchant_id' => env('MANDIRI_MERCHANT_ID', ''),
    'terminal_id' => env('MANDIRI_TERMINAL_ID', ''),
    'webhook' => [
        'path' => env('MANDIRI_WEBHOOK_PATH', '/webhook/mandiri-qris'),
        'verify_signature' => env('MANDIRI_WEBHOOK_VERIFY_SIGNATURE', true),
    ],
];
```

## Requirements

- PHP >= 7.4
- Laravel >= 8.0

## License

MIT
