<<<<<<< HEAD
# 🏦 Mandiri QRIS Payment API - Multi-Platform SDK

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP](https://img.shields.io/badge/PHP-7.4+-blue.svg)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-8.x+-red.svg)](https://laravel.com/)
[![Python](https://img.shields.io/badge/Python-3.8+-blue.svg)](https://www.python.org/)
[![Java](https://img.shields.io/badge/Java-11+-orange.svg)](https://www.java.com/)
[![.NET](https://img.shields.io/badge/.NET-6.0+-purple.svg)](https://dotnet.microsoft.com/)

Complete implementation of Mandiri QRIS Payment Gateway for multiple platforms and frameworks.

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Supported Platforms](#supported-platforms)
- [Quick Start](#quick-start)
- [Project Structure](#project-structure)
- [Documentation](#documentation)
- [Environment Configuration](#environment-configuration)
- [Contributing](#contributing)
- [License](#license)

## 🌟 Overview

This repository provides ready-to-use implementations of Mandiri QRIS (Quick Response Code Indonesian Standard) Payment API for various programming languages and frameworks. Each implementation includes:

- ✅ B2B Authentication with automatic token management
- ✅ Dynamic QRIS code generation
- ✅ Payment status checking
- ✅ Webhook handling (optional)
- ✅ Sandbox & Production environment support
- ✅ Comprehensive error handling
- ✅ Complete documentation and examples

## 🚀 Features

### Core Functionality
- **B2B OAuth 2.0 Authentication**: Secure token-based authentication with auto-refresh
- **Dynamic QRIS Generation**: Create customized QR codes for each transaction
- **Payment Polling**: Real-time payment status monitoring
- **Webhook Support**: Asynchronous payment notifications
- **Environment Switching**: Easy toggle between sandbox and production

### Technical Features
- **Token Caching**: Reduces API calls and improves performance
- **Retry Logic**: Automatic retry for failed requests
- **Logging**: Comprehensive logging for debugging
- **Input Validation**: Robust data validation
- **Error Handling**: User-friendly error messages

## 💻 Supported Platforms

| Platform | Version | Status | Directory |
|----------|---------|--------|-----------|
| **PHP Native** | 7.4+ | ✅ Complete | `/php-native/` |
| **Laravel** | 8.x, 9.x, 10.x | ✅ Complete | `/laravel/` |
| **CodeIgniter** | 3.x, 4.x | ✅ Complete | `/codeigniter/` |
| **Python** | 3.8+ | ✅ Complete | `/python/` |
| **Java** | 11+ (Spring Boot) | ✅ Complete | `/java/` |
| **ASP.NET Core** | 6.0+ | ✅ Complete | `/aspnet/` |

## ⚡ Quick Start

### 1. Choose Your Platform

```bash
# PHP Native
cd php-native
composer install

# Laravel
cd laravel
composer install

# CodeIgniter 4
cd codeigniter
composer install

# Python
cd python
pip install -r requirements.txt

# Java (Spring Boot)
cd java
mvn clean install

# ASP.NET Core
cd aspnet
dotnet restore
```

### 2. Configure Environment

Copy the `.env.example` file and configure your credentials:

```env
# Mandiri API Configuration
MANDIRI_ENV=sandbox                    # or production
MANDIRI_CLIENT_ID=your_client_id
MANDIRI_CLIENT_SECRET=your_client_secret
MANDIRI_MERCHANT_NMID=YOUR_NMID
MANDIRI_MERCHANT_NAME=YOUR_MERCHANT_NAME
MANDIRI_MERCHANT_CITY=YOUR_CITY

# Sandbox URLs (default)
MANDIRI_BASE_URL=https://sandbox.bankmandiri.co.id
MANDIRI_AUTH_URL=/openapi/auth/v2.0/access-token/b2b
MANDIRI_QRIS_CREATE_URL=/openapi/qris/v2.0/qr-code
MANDIRI_QRIS_STATUS_URL=/openapi/qris/v2.0/qr-code/status

# Production URLs (uncomment for production)
# MANDIRI_BASE_URL=https://api.bankmandiri.co.id

# QR Code Settings
QRIS_EXPIRY_MINUTES=30                 # Default expiry time (5-120 minutes)
```

### 3. Run Examples

Each platform includes working examples:

```bash
# PHP Native
php examples/create_qris.php

# Laravel
php artisan serve
# Visit: http://localhost:8000/api/qris/create

# Python (Flask)
python app.py

# Java (Spring Boot)
mvn spring-boot:run

# ASP.NET Core
dotnet run
```

## 📁 Project Structure

```
mandiri-qris-api/
├── README.md                          # This file
├── QRIS_MANDIRI_PAYMENT_SUMMARY.md   # API documentation
├── LICENSE
│
├── php-native/                        # PHP Native implementation
│   ├── src/
│   │   ├── MandiriQris.php           # Main SDK class
│   │   └── config.php                # Configuration
│   ├── examples/
│   │   ├── create_qris.php
│   │   ├── check_status.php
│   │   └── webhook.php
│   ├── .env.example
│   ├── composer.json
│   └── README.md
│
├── laravel/                           # Laravel package
│   ├── src/
│   │   ├── MandiriQrisServiceProvider.php
│   │   ├── Services/
│   │   │   └── MandiriQrisService.php
│   │   ├── Controllers/
│   │   │   └── QrisController.php
│   │   ├── Models/
│   │   │   └── Payment.php
│   │   └── config/
│   │       └── mandiri-qris.php
│   ├── routes/
│   │   └── api.php
│   ├── database/
│   │   └── migrations/
│   ├── composer.json
│   └── README.md
│
├── codeigniter/                       # CodeIgniter 3 & 4
│   ├── application/                   # CI3
│   │   ├── libraries/
│   │   │   └── Mandiri_qris.php
│   │   └── controllers/
│   │       └── Qris.php
│   ├── app/                          # CI4
│   │   ├── Libraries/
│   │   │   └── MandiriQris.php
│   │   └── Controllers/
│   │       └── QrisController.php
│   └── README.md
│
├── python/                            # Python SDK
│   ├── mandiri_qris/
│   │   ├── __init__.py
│   │   ├── client.py                 # Main SDK
│   │   └── exceptions.py
│   ├── examples/
│   │   ├── flask_app.py              # Flask example
│   │   └── django_views.py           # Django example
│   ├── requirements.txt
│   ├── setup.py
│   └── README.md
│
├── java/                              # Java Spring Boot
│   ├── src/
│   │   └── main/
│   │       └── java/
│   │           └── com/mandiri/qris/
│   │               ├── MandiriQrisClient.java
│   │               ├── models/
│   │               ├── controllers/
│   │               └── services/
│   ├── pom.xml
│   └── README.md
│
└── aspnet/                            # ASP.NET Core
    ├── MandiriQris/
    │   ├── Services/
    │   │   └── MandiriQrisService.cs
    │   ├── Controllers/
    │   │   └── QrisController.cs
    │   ├── Models/
    │   └── appsettings.json
    ├── MandiriQris.csproj
    └── README.md
```

## 📚 Documentation

### API Endpoints

#### 1. Get Access Token (B2B)
```
POST /openapi/auth/v2.0/access-token/b2b
```

#### 2. Create QRIS Code
```
POST /openapi/qris/v2.0/qr-code
```

#### 3. Check Payment Status
```
GET /openapi/qris/v2.0/qr-code/status/{qr_id}
```

### Usage Examples

<details>
<summary><strong>PHP Native</strong></summary>

```php
<?php
require_once 'vendor/autoload.php';

use MandiriQris\Client;

$client = new Client([
    'client_id' => 'your_client_id',
    'client_secret' => 'your_client_secret',
    'environment' => 'sandbox'
]);

// Create QRIS
$qris = $client->createQris([
    'amount' => 100000,
    'reference' => 'ORDER-001',
    'merchant_nmid' => 'YOUR_NMID',
    'merchant_name' => 'YOUR STORE',
    'merchant_city' => 'JAKARTA'
]);

echo "QR String: " . $qris['qr_string'];
echo "QR ID: " . $qris['qr_id'];

// Check status
$status = $client->checkStatus($qris['qr_id']);
echo "Payment Status: " . $status['status'];
```
</details>

<details>
<summary><strong>Laravel</strong></summary>

```php
<?php
use App\Services\MandiriQrisService;

class PaymentController extends Controller
{
    protected $qrisService;
    
    public function __construct(MandiriQrisService $qrisService)
    {
        $this->qrisService = $qrisService;
    }
    
    public function createQris(Request $request)
    {
        $qris = $this->qrisService->createQris([
            'amount' => $request->amount,
            'reference' => 'ORDER-' . time(),
        ]);
        
        return response()->json($qris);
    }
    
    public function checkStatus($qrId)
    {
        $status = $this->qrisService->checkStatus($qrId);
        return response()->json($status);
    }
}
```
</details>

<details>
<summary><strong>Python</strong></summary>

```python
from mandiri_qris import MandiriQrisClient

client = MandiriQrisClient(
    client_id='your_client_id',
    client_secret='your_client_secret',
    environment='sandbox'
)

# Create QRIS
qris = client.create_qris(
    amount=100000,
    reference='ORDER-001',
    merchant_nmid='YOUR_NMID',
    merchant_name='YOUR STORE',
    merchant_city='JAKARTA'
)

print(f"QR ID: {qris['qr_id']}")
print(f"QR String: {qris['qr_string']}")

# Check status
status = client.check_status(qris['qr_id'])
print(f"Status: {status['status']}")
```
</details>

<details>
<summary><strong>Java</strong></summary>

```java
import com.mandiri.qris.MandiriQrisClient;
import com.mandiri.qris.models.QrisRequest;
import com.mandiri.qris.models.QrisResponse;

MandiriQrisClient client = new MandiriQrisClient(
    "your_client_id",
    "your_client_secret",
    "sandbox"
);

// Create QRIS
QrisRequest request = QrisRequest.builder()
    .amount(100000.0)
    .reference("ORDER-001")
    .merchantNmid("YOUR_NMID")
    .merchantName("YOUR STORE")
    .merchantCity("JAKARTA")
    .build();

QrisResponse qris = client.createQris(request);
System.out.println("QR ID: " + qris.getQrId());

// Check status
PaymentStatus status = client.checkStatus(qris.getQrId());
System.out.println("Status: " + status.getStatus());
```
</details>

<details>
<summary><strong>ASP.NET Core</strong></summary>

```csharp
using MandiriQris.Services;
using MandiriQris.Models;

public class QrisController : ControllerBase
{
    private readonly IMandiriQrisService _qrisService;
    
    public QrisController(IMandiriQrisService qrisService)
    {
        _qrisService = qrisService;
    }
    
    [HttpPost("create")]
    public async Task<IActionResult> CreateQris([FromBody] QrisRequest request)
    {
        var qris = await _qrisService.CreateQrisAsync(request);
        return Ok(qris);
    }
    
    [HttpGet("status/{qrId}")]
    public async Task<IActionResult> CheckStatus(string qrId)
    {
        var status = await _qrisService.CheckStatusAsync(qrId);
        return Ok(status);
    }
}
```
</details>

## 🔐 Environment Configuration

### Sandbox (Testing)

```env
MANDIRI_ENV=sandbox
MANDIRI_BASE_URL=https://sandbox.bankmandiri.co.id
MANDIRI_CLIENT_ID=sandbox_client_id
MANDIRI_CLIENT_SECRET=sandbox_secret
```

**Test Credentials:**
- Client ID: Available from Mandiri Developer Portal
- Client Secret: Available from Mandiri Developer Portal
- Merchant NMID: Test merchant ID

### Production

```env
MANDIRI_ENV=production
MANDIRI_BASE_URL=https://api.bankmandiri.co.id
MANDIRI_CLIENT_ID=prod_client_id
MANDIRI_CLIENT_SECRET=prod_secret
MANDIRI_MERCHANT_NMID=YOUR_PROD_NMID
```

## 🧪 Testing

Each implementation includes test files:

```bash
# PHP
composer test

# Laravel
php artisan test

# Python
pytest

# Java
mvn test

# ASP.NET
dotnet test
```

## 🔒 Security Best Practices

1. **Never commit credentials**: Use `.env` files and add them to `.gitignore`
2. **Use HTTPS**: Always use HTTPS in production
3. **Validate inputs**: Always validate and sanitize user inputs
4. **Token security**: Store tokens securely (session, cache, database)
5. **Webhook signature**: Verify webhook signatures if enabled
6. **Rate limiting**: Implement rate limiting for API calls
7. **Logging**: Log all transactions but never log sensitive data

## 📊 Error Handling

All implementations include comprehensive error handling:

| Error Code | Description | Solution |
|------------|-------------|----------|
| `AUTH_FAILED` | Authentication failed | Check client_id and client_secret |
| `INVALID_TOKEN` | Access token invalid or expired | Token will auto-refresh |
| `QRIS_CREATE_FAILED` | Failed to create QRIS | Check merchant configuration |
| `INVALID_AMOUNT` | Invalid payment amount | Amount must be > 0 |
| `QR_EXPIRED` | QR code has expired | Generate new QR code |
| `PAYMENT_COMPLETED` | Payment already completed | No action needed |

## 🤝 Contributing

Contributions are welcome! Please read our [Contributing Guidelines](CONTRIBUTING.md) first.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 📞 Support

- **Documentation**: [QRIS_MANDIRI_PAYMENT_SUMMARY.md](QRIS_MANDIRI_PAYMENT_SUMMARY.md)
- **Issues**: [GitHub Issues](https://github.com/yourusername/mandiri-qris-api/issues)
- **Email**: support@yourcompany.com

### Mandiri Developer Support
- Email: developer.support@bankmandiri.co.id
- Phone: 14000
- Portal: https://developers.bankmandiri.co.id

## 🙏 Acknowledgments

- Bank Mandiri for providing the QRIS API
- All contributors to this project
- Open source community

## 📅 Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

---

**Made with ❤️ by the Community**

**Last Updated:** December 30, 2025
=======
# mandiri-qris-payment-gateway
🏦 Mandiri QRIS Payment Gateway SDK - Multi-platform implementation (PHP, Laravel, CodeIgniter, Python, Java Spring Boot, ASP.NET Core, Node.js) with OAuth 2.0, dynamic QR generation, payment polling, and webhook support. Ready for production! 🚀
>>>>>>> 2996b9aa9a41c84d2f8a6ac0240e01c927c93c88
