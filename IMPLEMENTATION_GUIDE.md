# 📖 Complete Implementation Guide - Mandiri QRIS Payment API

This guide provides step-by-step instructions for implementing Mandiri QRIS Payment API across all supported platforms.

---

## 📋 Table of Contents

1. [Prerequisites](#prerequisites)
2. [Getting Mandiri API Credentials](#getting-credentials)
3. [Platform-Specific Implementation](#platform-implementations)
   - [PHP Native](#php-native-implementation)
   - [Laravel](#laravel-implementation)
   - [CodeIgniter](#codeigniter-implementation)
   - [Python](#python-implementation)
   - [Java Spring Boot](#java-implementation)
   - [ASP.NET Core](#aspnet-implementation)
4. [Database Setup](#database-setup)
5. [Testing](#testing)
6. [Production Deployment](#production-deployment)
7. [Troubleshooting](#troubleshooting)

---

## 🔑 Prerequisites

### 1. Mandiri Developer Account
- Register at [Mandiri Developer Portal](https://developers.bankmandiri.co.id)
- Complete merchant onboarding
- Obtain sandbox credentials

### 2. System Requirements

**For PHP (Native/Laravel/CodeIgniter):**
- PHP 7.4 or higher
- cURL extension
- OpenSSL extension
- Composer
- MySQL/PostgreSQL

**For Python:**
- Python 3.8+
- pip package manager
- PostgreSQL/MySQL

**For Java:**
- Java 11 or higher
- Maven or Gradle
- Spring Boot 2.5+

**For ASP.NET:**
- .NET 6.0 or higher
- Visual Studio 2022 or VS Code
- SQL Server/PostgreSQL

---

## 🎯 Getting Mandiri API Credentials

### Step 1: Register Account

1. Visit https://developers.bankmandiri.co.id
2. Click "Register" and fill in your details:
   - Business name
   - Business type
   - Contact information
   - Tax ID (NPWP)

### Step 2: Submit Documents

Upload required documents:
- Company registration certificate
- Tax ID (NPWP)
- Bank account statement
- Director's ID card

### Step 3: Get Credentials

After approval (3-5 business days):

1. Login to Developer Portal
2. Go to **My Apps** → **Create New App**
3. Select **QRIS Payment**
4. You'll receive:
   - **Client ID** (Sandbox)
   - **Client Secret** (Sandbox)
   - **Merchant NMID** (Sandbox)

### Step 4: Test in Sandbox

Use sandbox credentials to test integration:
```
Base URL: https://sandbox.bankmandiri.co.id
Client ID: sandbox_xxxxxxxxxxxxx
Client Secret: sandbox_secret_xxxxxxxxxxxxx
Merchant NMID: TEST936000
```

### Step 5: Go Live

After successful testing:
1. Request production credentials
2. Submit final integration test results
3. Receive production credentials within 2-3 days

---

## 💻 Platform-Specific Implementation

---

## 🔵 PHP Native Implementation

### Step 1: Clone and Setup

```bash
cd /your/project/directory
git clone https://github.com/yourusername/mandiri-qris-api.git
cd mandiri-qris-api/php-native
```

### Step 2: Install Dependencies

```bash
composer install
```

### Step 3: Configure Environment

```bash
cp .env.example .env
nano .env
```

Update with your credentials:
```env
MANDIRI_ENV=sandbox
MANDIRI_CLIENT_ID=your_client_id
MANDIRI_CLIENT_SECRET=your_client_secret
MANDIRI_MERCHANT_NMID=YOUR_NMID
MANDIRI_MERCHANT_NAME=YOUR STORE NAME
MANDIRI_MERCHANT_CITY=JAKARTA
```

### Step 4: Test Connection

```bash
php examples/create_qris.php
```

Expected output:
```
✓ QRIS created successfully!

QR ID: QR123456789
Status: ACTIVE
Expired At: 2025-12-30 11:30:00
QR Image URL: https://api.qrserver.com/v1/create-qr-code/...
```

### Step 5: Integrate in Your Project

```php
<?php
require_once 'vendor/autoload.php';
require_once 'src/MandiriQris.php';

session_start();

$mandiri = new MandiriQris([
    'client_id' => $_ENV['MANDIRI_CLIENT_ID'],
    'client_secret' => $_ENV['MANDIRI_CLIENT_SECRET'],
    'environment' => 'sandbox',
    'merchant_nmid' => $_ENV['MANDIRI_MERCHANT_NMID'],
    'merchant_name' => $_ENV['MANDIRI_MERCHANT_NAME'],
    'merchant_city' => $_ENV['MANDIRI_MERCHANT_CITY']
]);

// Create payment
$qris = $mandiri->createQris([
    'amount' => 100000,
    'reference' => 'ORDER-' . time()
]);

// Save to database
$db = new PDO('mysql:host=localhost;dbname=yourdb', 'user', 'pass');
$stmt = $db->prepare("
    INSERT INTO payments (order_id, qr_id, qr_string, qr_image_url, amount, status, expired_at)
    VALUES (?, ?, ?, ?, ?, 'pending', ?)
");
$stmt->execute([
    $qris['reference'],
    $qris['qr_id'],
    $qris['qr_string'],
    $qris['qr_image_url'],
    $qris['amount'],
    $qris['expired_at']
]);

// Display QR to user
echo '<img src="' . $qris['qr_image_url'] . '" />';
```

### Step 6: Implement Status Polling

Create `check_status_ajax.php`:

```php
<?php
require_once 'src/MandiriQris.php';
session_start();

header('Content-Type: application/json');

$qrId = $_GET['qr_id'] ?? null;

if (!$qrId) {
    http_response_code(400);
    echo json_encode(['error' => 'QR ID required']);
    exit;
}

$mandiri = new MandiriQris([/* config */]);

try {
    $status = $mandiri->checkStatus($qrId);
    
    // Update database if paid
    if ($status['status'] === 'COMPLETED') {
        $db = new PDO('mysql:host=localhost;dbname=yourdb', 'user', 'pass');
        $stmt = $db->prepare("UPDATE payments SET status = 'paid', paid_at = NOW() WHERE qr_id = ?");
        $stmt->execute([$qrId]);
    }
    
    echo json_encode(['success' => true, 'data' => $status]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
```

Frontend JavaScript:
```javascript
<script>
let qrId = '<?= $qris['qr_id'] ?>';

setInterval(() => {
    fetch('check_status_ajax.php?qr_id=' + qrId)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data.status === 'COMPLETED') {
                alert('Payment successful!');
                window.location.href = 'success.php';
            }
        });
}, 3000); // Poll every 3 seconds
</script>
```

---

## 🔴 Laravel Implementation

### Step 1: Install Package

```bash
composer require mandiri-qris/laravel
```

Or add to existing Laravel project:
```bash
cd /your/laravel/project
cp -r /path/to/mandiri-qris-api/laravel/src app/Services/MandiriQris
```

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --tag=mandiri-qris-config
```

### Step 3: Configure Environment

Add to `.env`:
```env
MANDIRI_ENV=sandbox
MANDIRI_CLIENT_ID=your_client_id
MANDIRI_CLIENT_SECRET=your_client_secret
MANDIRI_MERCHANT_NMID=YOUR_NMID
MANDIRI_MERCHANT_NAME=YOUR STORE NAME
MANDIRI_MERCHANT_CITY=JAKARTA
QRIS_EXPIRY_MINUTES=30
```

### Step 4: Run Migrations

```bash
php artisan migrate
```

This creates the `payments` table.

### Step 5: Create Controller

```bash
php artisan make:controller Api/QrisController
```

Edit `app/Http/Controllers/Api/QrisController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\MandiriQrisService;
use App\Models\Payment;

class QrisController extends Controller
{
    protected $qrisService;

    public function __construct(MandiriQrisService $qrisService)
    {
        $this->qrisService = $qrisService;
    }

    public function create(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:10000',
            'order_id' => 'required|string|unique:payments,order_id'
        ]);

        try {
            $qris = $this->qrisService->createQris([
                'amount' => $validated['amount'],
                'reference' => $validated['order_id'],
                'callback_url' => route('qris.webhook')
            ]);

            // Save to database
            $payment = Payment::create([
                'payment_id' => 'PAY-' . $validated['order_id'],
                'order_id' => $validated['order_id'],
                'user_id' => auth()->id(),
                'amount' => $validated['amount'],
                'payment_method' => 'qris',
                'status' => 'pending',
                'qr_id' => $qris['qr_id'],
                'qr_string' => $qris['qr_string'],
                'qr_image_url' => $qris['qr_image_url'],
                'expired_at' => $qris['expired_at']
            ]);

            return response()->json([
                'success' => true,
                'data' => $payment
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
            $status = $this->qrisService->checkStatus($qrId);

            // Update database
            if ($status['status'] === 'COMPLETED') {
                $payment = Payment::where('qr_id', $qrId)->first();
                if ($payment && $payment->status === 'pending') {
                    $payment->update([
                        'status' => 'paid',
                        'paid_at' => now()
                    ]);
                    
                    // Fire event
                    event(new \App\Events\PaymentCompleted($payment));
                }
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
        \Log::info('QRIS Webhook', $request->all());

        $qrId = $request->input('qr_id');
        $status = $request->input('status');

        if ($status === 'COMPLETED') {
            $payment = Payment::where('qr_id', $qrId)->first();
            
            if ($payment && $payment->status === 'pending') {
                $payment->update([
                    'status' => 'paid',
                    'paid_at' => now()
                ]);
                
                event(new \App\Events\PaymentCompleted($payment));
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
```

### Step 6: Add Routes

Edit `routes/api.php`:

```php
use App\Http\Controllers\Api\QrisController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/qris/create', [QrisController::class, 'create']);
    Route::get('/qris/status/{qrId}', [QrisController::class, 'checkStatus']);
});

Route::post('/qris/webhook', [QrisController::class, 'webhook'])->name('qris.webhook');
```

### Step 7: Create Blade View

Create `resources/views/payment/qris.blade.php`:

```blade
@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Scan QR Code untuk Pembayaran</h4>
                </div>
                <div class="card-body text-center">
                    <div id="timer" class="fs-2 fw-bold text-primary mb-3">30:00</div>
                    
                    <div class="qr-code-container mb-4">
                        <img src="{{ $payment->qr_image_url }}" 
                             alt="QR Code" 
                             class="img-fluid" 
                             style="max-width: 300px;">
                    </div>
                    
                    <div class="payment-details bg-light p-3 rounded mb-3">
                        <p class="mb-2"><strong>Total:</strong> Rp {{ number_format($payment->amount, 0, ',', '.') }}</p>
                        <p class="mb-2"><strong>Order ID:</strong> {{ $payment->order_id }}</p>
                        <p class="mb-0"><strong>Status:</strong> 
                            <span id="status-badge" class="badge bg-warning">Menunggu Pembayaran</span>
                        </p>
                    </div>
                    
                    <div id="loading-indicator" class="mb-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Menunggu pembayaran...</p>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>📱 Cara Pembayaran:</strong>
                        <ol class="text-start mt-2 mb-0">
                            <li>Buka aplikasi mobile banking atau e-wallet</li>
                            <li>Pilih menu QRIS / Scan QR</li>
                            <li>Scan kode QR di atas</li>
                            <li>Konfirmasi pembayaran</li>
                            <li>Tunggu notifikasi pembayaran berhasil</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
const qrId = '{{ $payment->qr_id }}';
let remainingSeconds = 30 * 60;
let pollInterval, timerInterval;

// Timer countdown
timerInterval = setInterval(() => {
    remainingSeconds--;
    const minutes = Math.floor(remainingSeconds / 60);
    const seconds = remainingSeconds % 60;
    document.getElementById('timer').textContent = 
        `${minutes}:${seconds.toString().padStart(2, '0')}`;
    
    if (remainingSeconds <= 0) {
        clearInterval(timerInterval);
        clearInterval(pollInterval);
        showExpired();
    }
}, 1000);

// Poll payment status
pollInterval = setInterval(checkPaymentStatus, 3000);

function checkPaymentStatus() {
    fetch(`/api/qris/status/${qrId}`, {
        headers: {
            'Authorization': 'Bearer ' + '{{ auth()->user()->api_token }}',
            'Accept': 'application/json'
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.data.status === 'COMPLETED') {
            clearInterval(pollInterval);
            clearInterval(timerInterval);
            showSuccess();
        }
    })
    .catch(err => console.error('Error:', err));
}

function showSuccess() {
    document.getElementById('status-badge').textContent = 'Lunas';
    document.getElementById('status-badge').className = 'badge bg-success';
    document.getElementById('loading-indicator').innerHTML = 
        '<div class="alert alert-success"><strong>✅ Pembayaran Berhasil!</strong></div>';
    setTimeout(() => window.location.href = '/payment/success', 2000);
}

function showExpired() {
    document.getElementById('status-badge').textContent = 'Expired';
    document.getElementById('status-badge').className = 'badge bg-danger';
    document.getElementById('loading-indicator').innerHTML = 
        '<div class="alert alert-danger"><strong>⏱ QR Code Expired</strong><br>Silakan buat pembayaran baru.</div>';
}
</script>
@endpush
@endsection
```

### Step 8: Test

```bash
php artisan serve

# In another terminal
curl -X POST http://localhost:8000/api/qris/create \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"amount": 100000, "order_id": "ORDER-001"}'
```

---

## 🟢 Python Implementation

### Step 1: Install Package

```bash
pip install mandiri-qris
```

Or from source:
```bash
cd python
pip install -r requirements.txt
pip install -e .
```

### Step 2: Flask Application

Create `app.py`:

```python
from flask import Flask, request, jsonify, render_template, session
from mandiri_qris import MandiriQrisClient
import os
from datetime import datetime
from dotenv import load_dotenv

load_dotenv()

app = Flask(__name__)
app.secret_key = os.getenv('SECRET_KEY', 'your-secret-key')

# Initialize client
client = MandiriQrisClient(
    client_id=os.getenv('MANDIRI_CLIENT_ID'),
    client_secret=os.getenv('MANDIRI_CLIENT_SECRET'),
    environment=os.getenv('MANDIRI_ENV', 'sandbox'),
    merchant_nmid=os.getenv('MANDIRI_MERCHANT_NMID'),
    merchant_name=os.getenv('MANDIRI_MERCHANT_NAME'),
    merchant_city=os.getenv('MANDIRI_MERCHANT_CITY')
)

@app.route('/')
def index():
    return render_template('index.html')

@app.route('/api/qris/create', methods=['POST'])
def create_qris():
    try:
        data = request.json
        amount = data.get('amount')
        order_id = data.get('order_id')
        
        if not amount or not order_id:
            return jsonify({'error': 'Amount and order_id required'}), 400
        
        qris = client.create_qris(
            amount=float(amount),
            reference=order_id
        )
        
        # Save to database (implement your DB logic here)
        # db.payments.insert_one({...})
        
        # Store in session
        session['qr_id'] = qris['qr_id']
        session['order_id'] = order_id
        session['amount'] = amount
        
        return jsonify({
            'success': True,
            'data': qris
        })
        
    except Exception as e:
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500

@app.route('/api/qris/status/<qr_id>')
def check_status(qr_id):
    try:
        status = client.check_status(qr_id)
        
        # Update database if paid
        if status['status'] == 'COMPLETED':
            # db.payments.update_one({'qr_id': qr_id}, {'$set': {'status': 'paid'}})
            pass
        
        return jsonify({
            'success': True,
            'data': status
        })
        
    except Exception as e:
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500

@app.route('/api/qris/webhook', methods=['POST'])
def webhook():
    payload = request.json
    
    # Log webhook
    app.logger.info(f'Webhook received: {payload}')
    
    if payload.get('status') == 'COMPLETED':
        qr_id = payload.get('qr_id')
        # Update database
        # db.payments.update_one({'qr_id': qr_id}, {'$set': {'status': 'paid'}})
    
    return jsonify({'status': 'ok'})

@app.route('/payment/<order_id>')
def payment_page(order_id):
    # Get payment from database
    # payment = db.payments.find_one({'order_id': order_id})
    # return render_template('payment.html', payment=payment)
    pass

if __name__ == '__main__':
    app.run(debug=True, port=5000)
```

### Step 3: Create Templates

Create `templates/payment.html`:

```html
<!DOCTYPE html>
<html>
<head>
    <title>QRIS Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>Scan QR Code</h4>
                        <div id="timer" class="text-center fs-3">30:00</div>
                    </div>
                    <div class="card-body text-center">
                        <img src="{{ payment.qr_image_url }}" class="img-fluid mb-3" style="max-width: 300px;">
                        <p><strong>Amount:</strong> Rp {{ "{:,.0f}".format(payment.amount) }}</p>
                        <p><strong>Order ID:</strong> {{ payment.order_id }}</p>
                        <div id="status" class="alert alert-warning">
                            Waiting for payment...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let qrId = '{{ payment.qr_id }}';
        let remainingSeconds = 30 * 60;
        
        // Timer
        setInterval(() => {
            remainingSeconds--;
            let minutes = Math.floor(remainingSeconds / 60);
            let seconds = remainingSeconds % 60;
            document.getElementById('timer').textContent = 
                `${minutes}:${seconds.toString().padStart(2, '0')}`;
        }, 1000);
        
        // Polling
        setInterval(() => {
            fetch(`/api/qris/status/${qrId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.data.status === 'COMPLETED') {
                        document.getElementById('status').className = 'alert alert-success';
                        document.getElementById('status').textContent = '✓ Payment Successful!';
                        setTimeout(() => window.location.href = '/success', 2000);
                    }
                });
        }, 3000);
    </script>
</body>
</html>
```

### Step 4: Run Application

```bash
python app.py
```

Visit: http://localhost:5000

---

## 🗄️ Database Setup

### MySQL Schema

```sql
CREATE DATABASE mandiri_qris CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE mandiri_qris;

CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id VARCHAR(100) UNIQUE NOT NULL,
    order_id VARCHAR(100) NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'qris',
    status ENUM('pending','paid','expired','failed') DEFAULT 'pending',
    qr_id VARCHAR(255) NULL,
    qr_string TEXT NULL,
    qr_image_url VARCHAR(500) NULL,
    transaction_id VARCHAR(255) NULL,
    expired_at TIMESTAMP NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_order_id (order_id),
    INDEX idx_qr_id (qr_id),
    INDEX idx_status (status),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id BIGINT UNSIGNED,
    action VARCHAR(100) NOT NULL,
    status VARCHAR(50),
    request_data TEXT,
    response_data TEXT,
    error_message TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_payment_id (payment_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 🧪 Testing

### Testing Checklist

- [ ] Can obtain access token
- [ ] Can create QRIS code
- [ ] QR image displays correctly
- [ ] Can check payment status
- [ ] Polling updates status correctly
- [ ] Timer counts down properly
- [ ] Payment completes successfully
- [ ] Database updates on payment
- [ ] Webhook receives notifications
- [ ] Error handling works
- [ ] Expired QR codes handled
- [ ] Concurrent requests work

### Automated Testing

**PHP (PHPUnit):**
```bash
cd php-native
composer test
```

**Laravel:**
```bash
php artisan test
```

**Python (pytest):**
```bash
cd python
pytest tests/
```

---

## 🚀 Production Deployment

### Pre-Deployment Checklist

- [ ] Obtain production credentials from Mandiri
- [ ] Update environment variables
- [ ] Change base URL to production
- [ ] Enable HTTPS/SSL
- [ ] Configure firewall rules
- [ ] Set up monitoring
- [ ] Configure webhook URL
- [ ] Test with small amounts
- [ ] Prepare rollback plan
- [ ] Document deployment steps

### Environment Variables (Production)

```env
# Production Configuration
MANDIRI_ENV=production
MANDIRI_BASE_URL=https://api.bankmandiri.co.id
MANDIRI_CLIENT_ID=prod_xxxxxxxxxxxxxxxx
MANDIRI_CLIENT_SECRET=prod_secret_xxxxxxxxxxxxxxxx
MANDIRI_MERCHANT_NMID=PROD_NMID_HERE
MANDIRI_MERCHANT_NAME=YOUR PRODUCTION NAME
MANDIRI_MERCHANT_CITY=JAKARTA

# Security
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=error

# Database
DB_CONNECTION=mysql
DB_HOST=your-prod-db-host
DB_DATABASE=production_db
DB_USERNAME=prod_user
DB_PASSWORD=strong_password_here
```

### Deployment Steps

1. **Backup Current System**
```bash
# Backup database
mysqldump -u user -p database > backup_$(date +%Y%m%d).sql

# Backup files
tar -czf backup_files_$(date +%Y%m%d).tar.gz /path/to/app
```

2. **Deploy Code**
```bash
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

3. **Run Migrations**
```bash
php artisan migrate --force
```

4. **Update Permissions**
```bash
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

5. **Restart Services**
```bash
sudo systemctl restart php8.1-fpm
sudo systemctl restart nginx
```

### Monitoring

**Set up logging:**
```php
// Log all QRIS transactions
Log::channel('qris')->info('Payment created', [
    'qr_id' => $qrId,
    'amount' => $amount,
    'user_id' => auth()->id()
]);
```

**Monitor metrics:**
- Payment success rate
- Average payment time
- API response times
- Error rates
- Failed transactions

---

## 🔧 Troubleshooting

### Common Issues

#### 1. "Failed to get access token"

**Cause:** Invalid client_id or client_secret

**Solution:**
- Verify credentials in Mandiri Portal
- Check environment variables
- Ensure no extra spaces in credentials
- Try regenerating credentials

#### 2. "cURL error: SSL certificate problem"

**Cause:** SSL verification issues

**Solution:**
```php
// Temporary fix (NOT for production)
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

// Proper fix: Update CA certificates
sudo apt-get install ca-certificates
sudo update-ca-certificates
```

#### 3. "QR code not scanning"

**Cause:** QR string format issues

**Solution:**
- Verify QR string is complete
- Check image generation service is working
- Try different QR code sizes
- Test with multiple banking apps

#### 4. "Webhook not receiving calls"

**Cause:** Firewall or routing issues

**Solution:**
- Verify webhook URL is publicly accessible
- Check firewall allows POST requests
- Test with tools like webhook.site
- Verify URL in Mandiri dashboard

#### 5. "Database connection failed"

**Cause:** Database credentials or connection issues

**Solution:**
```bash
# Test connection
mysql -h host -u user -p database

# Check Laravel config
php artisan config:clear
php artisan config:cache
```

### Debug Mode

Enable detailed logging:

```php
// PHP
$mandiri->setLogEnabled(true);

// Laravel
config(['mandiri-qris.debug' => true]);

// Python
import logging
logging.basicConfig(level=logging.DEBUG)
```

### Support Contacts

**Mandiri Developer Support:**
- Email: developer.support@bankmandiri.co.id
- Phone: 14000 (ext. 2)
- Portal: https://developers.bankmandiri.co.id/support

**Business Hours:** Monday-Friday, 9 AM - 5 PM WIB

---

## 📞 Getting Help

1. Check documentation: [QRIS_MANDIRI_PAYMENT_SUMMARY.md](../QRIS_MANDIRI_PAYMENT_SUMMARY.md)
2. Review examples in each platform directory
3. Search GitHub issues
4. Contact Mandiri support
5. Community forum (if available)

---

**Last Updated:** December 30, 2025  
**Version:** 1.0.0  
**License:** MIT
