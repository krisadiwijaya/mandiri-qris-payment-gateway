# 🏦 Mandiri QRIS Payment Gateway SDK

Multi-platform implementation for Mandiri QRIS Payment Gateway with OAuth 2.0, dynamic QR generation, payment polling, and webhook support. Ready for production! 🚀

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

## 🌟 Features

- **OAuth 2.0 Authentication** - Secure token-based authentication
- **Dynamic QR Code Generation** - Generate QR codes for payments
- **Payment Status Polling** - Check payment status in real-time
- **Webhook Support** - Receive payment notifications
- **Multi-platform** - PHP, Laravel, CodeIgniter, Python, Java Spring Boot, ASP.NET Core, Node.js

## 📦 Supported Platforms

| Platform | Status | Documentation |
|----------|--------|---------------|
| PHP | ✅ Ready | [PHP SDK](php/) |
| Laravel | ✅ Ready | [Laravel Integration](laravel/) |
| CodeIgniter | ✅ Ready | [CodeIgniter Integration](codeigniter/) |
| Python | ✅ Ready | [Python SDK](python/) |
| Java Spring Boot | ✅ Ready | [Spring Boot SDK](java-spring-boot/) |
| ASP.NET Core | ✅ Ready | [ASP.NET Core SDK](dotnet/) |
| Node.js | ✅ Ready | [Node.js SDK](nodejs/) |

## 🚀 Quick Start

### PHP

```php
require 'vendor/autoload.php';

use MandiriQris\Client;

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
    'terminal_id' => 'TERM001'
]);

// Check payment status
$status = $client->checkPaymentStatus($qr['transaction_id']);
```

### Laravel

```bash
composer require mandiri/qris-laravel
php artisan vendor:publish --provider="MandiriQris\Laravel\ServiceProvider"
```

```php
use MandiriQris\Laravel\Facades\MandiriQris;

// Generate QR Code
$qr = MandiriQris::generateQR([
    'amount' => 100000,
    'merchant_id' => config('mandiri-qris.merchant_id'),
    'terminal_id' => config('mandiri-qris.terminal_id')
]);
```

### Python

```bash
pip install mandiri-qris
```

```python
from mandiri_qris import MandiriQrisClient

client = MandiriQrisClient(
    client_id='your_client_id',
    client_secret='your_client_secret',
    base_url='https://api.mandiri.co.id',
    sandbox=True
)

# Generate QR Code
qr = client.generate_qr(
    amount=100000,
    merchant_id='MERCHANT123',
    terminal_id='TERM001'
)

# Check payment status
status = client.check_payment_status(qr['transaction_id'])
```

### Java Spring Boot

```xml
<dependency>
    <groupId>id.co.mandiri</groupId>
    <artifactId>qris-spring-boot-starter</artifactId>
    <version>1.0.0</version>
</dependency>
```

```java
@Autowired
private MandiriQrisService qrisService;

public void generateQR() {
    QRRequest request = QRRequest.builder()
        .amount(100000)
        .merchantId("MERCHANT123")
        .terminalId("TERM001")
        .build();
    
    QRResponse response = qrisService.generateQR(request);
}
```

### ASP.NET Core

```bash
dotnet add package Mandiri.Qris
```

```csharp
services.AddMandiriQris(options =>
{
    options.ClientId = "your_client_id";
    options.ClientSecret = "your_client_secret";
    options.BaseUrl = "https://api.mandiri.co.id";
    options.Sandbox = true;
});

// In your controller
public class PaymentController : Controller
{
    private readonly IMandiriQrisService _qrisService;
    
    public PaymentController(IMandiriQrisService qrisService)
    {
        _qrisService = qrisService;
    }
    
    public async Task<IActionResult> GenerateQR()
    {
        var request = new QRRequest
        {
            Amount = 100000,
            MerchantId = "MERCHANT123",
            TerminalId = "TERM001"
        };
        
        var qr = await _qrisService.GenerateQRAsync(request);
        return Ok(qr);
    }
}
```

### Node.js

```bash
npm install mandiri-qris
```

```javascript
const { MandiriQrisClient } = require('mandiri-qris');

const client = new MandiriQrisClient({
    clientId: 'your_client_id',
    clientSecret: 'your_client_secret',
    baseUrl: 'https://api.mandiri.co.id',
    sandbox: true
});

// Generate QR Code
const qr = await client.generateQR({
    amount: 100000,
    merchantId: 'MERCHANT123',
    terminalId: 'TERM001'
});

// Check payment status
const status = await client.checkPaymentStatus(qr.transactionId);
```

## 🔐 Configuration

### Environment Variables

```env
MANDIRI_CLIENT_ID=your_client_id
MANDIRI_CLIENT_SECRET=your_client_secret
MANDIRI_BASE_URL=https://api.mandiri.co.id
MANDIRI_SANDBOX=true
MANDIRI_MERCHANT_ID=MERCHANT123
MANDIRI_TERMINAL_ID=TERM001
```

## 📖 API Documentation

### Authentication

All requests require OAuth 2.0 authentication. The SDK handles token generation and refresh automatically.

### Generate QR Code

Generate a dynamic QRIS code for payment:

**Parameters:**
- `amount` (required): Payment amount in IDR
- `merchant_id` (required): Your merchant ID
- `terminal_id` (required): Terminal identifier
- `invoice_number` (optional): Custom invoice number
- `customer_name` (optional): Customer name
- `customer_phone` (optional): Customer phone number

### Check Payment Status

Poll payment status using transaction ID:

**Parameters:**
- `transaction_id` (required): Transaction ID from QR generation

**Returns:**
- `status`: Payment status (PENDING, SUCCESS, FAILED, EXPIRED)
- `amount`: Transaction amount
- `paid_at`: Payment timestamp (if paid)

### Webhook Handler

Receive real-time payment notifications:

**Webhook Payload:**
```json
{
    "transaction_id": "TXN123456",
    "status": "SUCCESS",
    "amount": 100000,
    "merchant_id": "MERCHANT123",
    "paid_at": "2026-01-02T10:30:00Z",
    "signature": "signature_hash"
}
```

## 🧪 Testing

### Sandbox Mode

All SDKs support sandbox mode for testing:

```
Sandbox URL: https://sandbox-api.mandiri.co.id
Production URL: https://api.mandiri.co.id
```

### Test Cards

Use these test scenarios in sandbox mode:

| Scenario | Amount | Expected Result |
|----------|--------|-----------------|
| Success | Any | Payment successful |
| Failed | 999 | Payment failed |
| Timeout | 888 | Payment timeout |

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Mandiri Bank for the QRIS API
- Contributors and maintainers

## 📞 Support

For support and questions:
- Create an issue on GitHub
- Email: support@example.com

## 🔄 Changelog

### Version 1.0.0 (2026-01-02)
- Initial release
- Multi-platform SDK support
- OAuth 2.0 authentication
- Dynamic QR generation
- Payment polling
- Webhook support
