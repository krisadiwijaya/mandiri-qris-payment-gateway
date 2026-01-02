# PHP Native - Mandiri QRIS Payment SDK

Simple PHP implementation for Mandiri QRIS Payment API without any framework dependencies.

## 📋 Requirements

- PHP 7.4 or higher
- cURL extension enabled
- OpenSSL extension enabled
- Composer (optional, for autoloading)

## 🚀 Installation

### Option 1: With Composer

```bash
composer require guzzlehttp/guzzle
```

### Option 2: Manual Installation

Simply download and include the files in your project.

## ⚙️ Configuration

1. Copy `.env.example` to `.env`:
```bash
cp .env.example .env
```

2. Edit `.env` with your credentials:
```env
MANDIRI_ENV=sandbox
MANDIRI_CLIENT_ID=your_client_id
MANDIRI_CLIENT_SECRET=your_client_secret
MANDIRI_MERCHANT_NMID=YOUR_NMID
MANDIRI_MERCHANT_NAME=YOUR MERCHANT NAME
MANDIRI_MERCHANT_CITY=JAKARTA
```

## 📝 Usage

### Basic Example

```php
<?php
require_once 'vendor/autoload.php';
require_once 'src/MandiriQris.php';

// Initialize client
$mandiri = new MandiriQris([
    'client_id' => 'your_client_id',
    'client_secret' => 'your_client_secret',
    'environment' => 'sandbox', // or 'production'
    'merchant_nmid' => 'YOUR_NMID',
    'merchant_name' => 'YOUR MERCHANT',
    'merchant_city' => 'JAKARTA'
]);

// Create QRIS
try {
    $result = $mandiri->createQris([
        'amount' => 100000,
        'reference' => 'ORDER-' . time()
    ]);
    
    echo "QR ID: " . $result['qr_id'] . "\n";
    echo "QR String: " . $result['qr_string'] . "\n";
    echo "QR Image URL: " . $result['qr_image_url'] . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Check payment status
try {
    $status = $mandiri->checkStatus($result['qr_id']);
    echo "Payment Status: " . $status['status'] . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

## 📁 File Structure

```
php-native/
├── src/
│   ├── MandiriQris.php        # Main SDK class
│   └── config.php             # Configuration loader
├── examples/
│   ├── create_qris.php        # Create QR example
│   ├── check_status.php       # Check status example
│   ├── webhook.php            # Webhook handler example
│   └── full_flow.php          # Complete payment flow
├── .env.example               # Environment template
├── .gitignore
├── composer.json
└── README.md
```

## 📚 API Reference

### Constructor

```php
$mandiri = new MandiriQris(array $config);
```

**Parameters:**
- `client_id` (string, required): Mandiri API client ID
- `client_secret` (string, required): Mandiri API client secret
- `environment` (string, optional): 'sandbox' or 'production' (default: 'sandbox')
- `merchant_nmid` (string, required): Merchant NMID
- `merchant_name` (string, required): Merchant name
- `merchant_city` (string, required): Merchant city

### Methods

#### createQris(array $data)

Create a new QRIS payment code.

```php
$result = $mandiri->createQris([
    'amount' => 100000,              // Required: Payment amount
    'reference' => 'ORDER-123',      // Required: Unique reference
    'callback_url' => 'https://...'  // Optional: Webhook URL
]);
```

**Returns:**
```php
[
    'qr_id' => 'QR123456789',
    'qr_string' => '00020101021226660016COM.MANDIRI.WWW...',
    'qr_image_url' => 'https://api.qrserver.com/v1/create-qr-code/...',
    'status' => 'ACTIVE',
    'expired_at' => '2025-12-30 11:30:00'
]
```

#### checkStatus(string $qrId)

Check payment status for a QR code.

```php
$status = $mandiri->checkStatus('QR123456789');
```

**Returns:**
```php
[
    'status' => 'COMPLETED',  // PENDING, COMPLETED, EXPIRED, FAILED
    'amount' => 100000,
    'paid_at' => '2025-12-30 10:45:00',
    'qr_id' => 'QR123456789'
]
```

#### getAccessToken()

Get or refresh B2B access token (called automatically).

```php
$token = $mandiri->getAccessToken();
```

## 🔄 Complete Payment Flow

```php
<?php
session_start();
require_once 'src/MandiriQris.php';

// Initialize
$mandiri = new MandiriQris([
    'client_id' => $_ENV['MANDIRI_CLIENT_ID'],
    'client_secret' => $_ENV['MANDIRI_CLIENT_SECRET'],
    'environment' => 'sandbox',
    'merchant_nmid' => $_ENV['MANDIRI_MERCHANT_NMID'],
    'merchant_name' => $_ENV['MANDIRI_MERCHANT_NAME'],
    'merchant_city' => $_ENV['MANDIRI_MERCHANT_CITY']
]);

// Step 1: Create Payment
$orderId = 'ORDER-' . time();
$amount = 150000;

try {
    $qris = $mandiri->createQris([
        'amount' => $amount,
        'reference' => $orderId
    ]);
    
    // Save to session or database
    $_SESSION['qr_id'] = $qris['qr_id'];
    $_SESSION['order_id'] = $orderId;
    $_SESSION['amount'] = $amount;
    
    // Display QR code to user
    echo '<img src="' . $qris['qr_image_url'] . '" />';
    echo '<p>Scan QR code to pay: Rp ' . number_format($amount, 0, ',', '.') . '</p>';
    
} catch (Exception $e) {
    die('Error creating QRIS: ' . $e->getMessage());
}

// Step 2: Check Status (via AJAX polling)
if (isset($_GET['check_status'])) {
    header('Content-Type: application/json');
    
    try {
        $qrId = $_SESSION['qr_id'];
        $status = $mandiri->checkStatus($qrId);
        
        if ($status['status'] === 'COMPLETED') {
            // Update order status in database
            // Send confirmation email
            // etc.
        }
        
        echo json_encode([
            'success' => true,
            'status' => $status['status'],
            'paid_at' => $status['paid_at'] ?? null
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}
?>

<!-- JavaScript for polling -->
<script>
let pollInterval = setInterval(function() {
    fetch('?check_status=1')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'COMPLETED') {
                clearInterval(pollInterval);
                alert('Payment successful!');
                window.location.href = 'success.php';
            } else if (data.status === 'EXPIRED') {
                clearInterval(pollInterval);
                alert('QR code expired. Please try again.');
            }
        });
}, 3000); // Check every 3 seconds
</script>
```

## 🎣 Webhook Handler

```php
<?php
// webhook.php
require_once 'src/MandiriQris.php';

// Get webhook payload
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

// Log the webhook (recommended)
file_put_contents('webhook.log', date('Y-m-d H:i:s') . ' - ' . $payload . "\n", FILE_APPEND);

// Verify signature (if implemented by Mandiri)
// $signature = $_SERVER['HTTP_X_MANDIRI_SIGNATURE'] ?? '';
// if (!verifySignature($payload, $signature)) {
//     http_response_code(401);
//     exit;
// }

// Process payment
if (isset($data['status']) && $data['status'] === 'COMPLETED') {
    $qrId = $data['qr_id'];
    $amount = $data['amount'];
    
    // Update database
    // $db->query("UPDATE payments SET status = 'paid' WHERE qr_id = ?", [$qrId]);
    
    // Send notification to customer
    // sendEmail($customerEmail, 'Payment Confirmed');
    
    // Return success response
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
}
```

## 🔒 Security Considerations

1. **Environment Variables**: Never hardcode credentials
2. **HTTPS**: Always use HTTPS in production
3. **Input Validation**: Validate all user inputs
4. **SQL Injection**: Use prepared statements
5. **Session Security**: Use secure session settings

```php
// Secure session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);  // Only if using HTTPS
ini_set('session.use_strict_mode', 1);
```

## 🧪 Testing

Run the example files:

```bash
# Create QRIS
php examples/create_qris.php

# Check status
php examples/check_status.php QR123456789

# Test webhook (use ngrok or similar for testing)
php -S localhost:8000
# Then send POST to http://localhost:8000/examples/webhook.php
```

## 🐛 Troubleshooting

### Issue: "Failed to get access token"
**Solution**: Check your client_id and client_secret are correct

### Issue: "cURL error"
**Solution**: Ensure cURL and OpenSSL extensions are enabled in php.ini

### Issue: "Invalid merchant NMID"
**Solution**: Verify your merchant NMID is correctly configured

### Issue: "QR expired"
**Solution**: Generate a new QR code. Default expiry is 30 minutes.

## 📝 Database Schema (Optional)

If you want to store payments in database:

```sql
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id VARCHAR(100) UNIQUE NOT NULL,
    order_id VARCHAR(100) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'qris',
    status ENUM('pending','paid','expired','failed') DEFAULT 'pending',
    qr_id VARCHAR(255),
    qr_string TEXT,
    qr_image_url VARCHAR(500),
    expired_at DATETIME,
    paid_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order_id (order_id),
    INDEX idx_qr_id (qr_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 📞 Support

- Issues: [GitHub Issues](https://github.com/yourusername/mandiri-qris-api/issues)
- Email: support@yourcompany.com
- Mandiri Support: developer.support@bankmandiri.co.id

## 📄 License

MIT License - see LICENSE file for details
