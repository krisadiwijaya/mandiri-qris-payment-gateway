# Laravel - Mandiri QRIS Payment Package

Laravel package for Mandiri QRIS Payment Gateway integration.

## 📋 Requirements

- PHP 7.4 or higher
- Laravel 8.x, 9.x, or 10.x
- cURL extension
- OpenSSL extension

## 🚀 Installation

### 1. Install via Composer

```bash
composer require mandiri-qris/laravel
```

Or add to your `composer.json`:

```json
{
    "require": {
        "mandiri-qris/laravel": "^1.0"
    }
}
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --provider="MandiriQris\Laravel\MandiriQrisServiceProvider"
```

This will create `config/mandiri-qris.php` configuration file.

### 3. Configure Environment

Add these variables to your `.env` file:

```env
MANDIRI_ENV=sandbox
MANDIRI_CLIENT_ID=your_client_id
MANDIRI_CLIENT_SECRET=your_client_secret
MANDIRI_MERCHANT_NMID=YOUR_NMID
MANDIRI_MERCHANT_NAME=YOUR_MERCHANT_NAME
MANDIRI_MERCHANT_CITY=JAKARTA
QRIS_EXPIRY_MINUTES=30
```

### 4. Run Migrations (Optional)

If you want to use the included payments table:

```bash
php artisan migrate
```

## 📝 Usage

### Basic Usage

```php
use MandiriQris\Laravel\Facades\MandiriQris;

// Create QRIS
$qris = MandiriQris::createQris([
    'amount' => 100000,
    'reference' => 'ORDER-' . time()
]);

// Check status
$status = MandiriQris::checkStatus($qris['qr_id']);
```

### In Controller

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use MandiriQris\Laravel\Facades\MandiriQris;
use App\Models\Payment;

class QrisController extends Controller
{
    public function create(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:10000',
            'order_id' => 'required|string'
        ]);

        try {
            $qris = MandiriQris::createQris([
                'amount' => $request->amount,
                'reference' => $request->order_id,
                'callback_url' => route('qris.webhook')
            ]);

            // Save to database
            Payment::create([
                'order_id' => $request->order_id,
                'amount' => $request->amount,
                'qr_id' => $qris['qr_id'],
                'qr_string' => $qris['qr_string'],
                'qr_image_url' => $qris['qr_image_url'],
                'status' => 'pending',
                'expired_at' => $qris['expired_at']
            ]);

            return response()->json([
                'success' => true,
                'data' => $qris
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function checkStatus($qrId)
    {
        try {
            $status = MandiriQris::checkStatus($qrId);

            // Update database if paid
            if ($status['status'] === 'COMPLETED') {
                Payment::where('qr_id', $qrId)
                    ->update([
                        'status' => 'paid',
                        'paid_at' => now()
                    ]);
            }

            return response()->json([
                'success' => true,
                'data' => $status
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function webhook(Request $request)
    {
        $payload = $request->all();
        
        \Log::info('QRIS Webhook received', $payload);

        if (isset($payload['status']) && $payload['status'] === 'COMPLETED') {
            $payment = Payment::where('qr_id', $payload['qr_id'])->first();
            
            if ($payment && $payment->status === 'pending') {
                $payment->update([
                    'status' => 'paid',
                    'paid_at' => now()
                ]);

                // Trigger event
                event(new \App\Events\PaymentCompleted($payment));
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
```

### Using Service Class

```php
<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;

class PaymentController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function createPayment(Request $request)
    {
        $payment = $this->paymentService->createQrisPayment(
            $request->user(),
            $request->order_id,
            $request->amount
        );

        return view('payment.qris', compact('payment'));
    }
}
```

## 🎯 Routes

Add these routes to your `routes/api.php`:

```php
use App\Http\Controllers\QrisController;

Route::prefix('qris')->group(function () {
    Route::post('/create', [QrisController::class, 'create']);
    Route::get('/status/{qrId}', [QrisController::class, 'checkStatus']);
    Route::post('/webhook', [QrisController::class, 'webhook'])->name('qris.webhook');
});
```

## 📊 Database Schema

The package includes a migration for the payments table:

```php
Schema::create('payments', function (Blueprint $table) {
    $table->id();
    $table->string('payment_id')->unique();
    $table->string('order_id');
    $table->foreignId('user_id')->nullable()->constrained();
    $table->decimal('amount', 15, 2);
    $table->string('payment_method')->default('qris');
    $table->enum('status', ['pending', 'paid', 'expired', 'failed'])->default('pending');
    $table->string('qr_id')->nullable();
    $table->text('qr_string')->nullable();
    $table->string('qr_image_url')->nullable();
    $table->timestamp('expired_at')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->timestamps();
    
    $table->index(['order_id', 'status']);
    $table->index('qr_id');
});
```

## 🎨 Blade Templates

Create a payment view `resources/views/payment/qris.blade.php`:

```blade
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4>Scan QR Code to Pay</h4>
                    <div id="timer" class="text-center text-primary fs-3">30:00</div>
                </div>
                
                <div class="card-body text-center">
                    <img src="{{ $payment->qr_image_url }}" alt="QR Code" class="img-fluid mb-3" style="max-width: 300px;">
                    
                    <div class="payment-info">
                        <p><strong>Amount:</strong> Rp {{ number_format($payment->amount, 0, ',', '.') }}</p>
                        <p><strong>Order ID:</strong> {{ $payment->order_id }}</p>
                        <p><strong>Status:</strong> <span id="status-text" class="badge bg-warning">Pending</span></p>
                    </div>
                    
                    <div id="loading" class="mt-3">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p>Waiting for payment...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
let qrId = '{{ $payment->qr_id }}';
let remainingSeconds = 30 * 60;

// Timer
setInterval(() => {
    remainingSeconds--;
    let minutes = Math.floor(remainingSeconds / 60);
    let seconds = remainingSeconds % 60;
    document.getElementById('timer').textContent = 
        `${minutes}:${seconds.toString().padStart(2, '0')}`;
    
    if (remainingSeconds <= 0) {
        document.getElementById('status-text').textContent = 'Expired';
        document.getElementById('status-text').className = 'badge bg-danger';
    }
}, 1000);

// Polling
setInterval(() => {
    fetch(`/api/qris/status/${qrId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data.status === 'COMPLETED') {
                document.getElementById('status-text').textContent = 'Paid';
                document.getElementById('status-text').className = 'badge bg-success';
                document.getElementById('loading').innerHTML = '<p class="text-success">✓ Payment Successful!</p>';
                setTimeout(() => window.location.href = '/payment/success', 2000);
            }
        });
}, 3000);
</script>
@endpush
@endsection
```

## 🧪 Testing

Run tests:

```bash
php artisan test
```

## 📚 API Reference

### Facade Methods

#### createQris(array $data)

Create a new QRIS payment.

```php
MandiriQris::createQris([
    'amount' => 100000,
    'reference' => 'ORDER-123',
    'callback_url' => 'https://...'
]);
```

#### checkStatus(string $qrId)

Check payment status.

```php
MandiriQris::checkStatus('QR123456789');
```

#### setExpiryMinutes(int $minutes)

Set QR code expiry time.

```php
MandiriQris::setExpiryMinutes(45);
```

## 🔒 Security

- Always use HTTPS in production
- Validate webhook signatures
- Sanitize user inputs
- Use Laravel's built-in CSRF protection

## 📞 Support

- Documentation: [Full Documentation](docs/README.md)
- Issues: [GitHub Issues](https://github.com/yourusername/mandiri-qris-laravel/issues)

## 📄 License

MIT License
