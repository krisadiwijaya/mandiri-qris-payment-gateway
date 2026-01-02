# 🚀 Quick Start Guide - Mandiri QRIS Payment API

This guide will help you get started with Mandiri QRIS Payment API in under 5 minutes.

---

## Choose Your Platform

Click on your platform to jump to the quick start:

- [PHP Native](#-php-native)
- [Laravel](#-laravel)
- [CodeIgniter](#-codeigniter)
- [Python](#-python)
- [Java Spring Boot](#-java-spring-boot)
- [ASP.NET Core](#-aspnet-core)

---

## 🔵 PHP Native

### 1. Clone & Install

```bash
cd php-native
composer install
cp .env.example .env
```

### 2. Configure

Edit `.env`:
```env
MANDIRI_CLIENT_ID=your_client_id
MANDIRI_CLIENT_SECRET=your_client_secret
MANDIRI_MERCHANT_NMID=YOUR_NMID
```

### 3. Test

```bash
php examples/create_qris.php
```

### 4. Integrate

```php
require_once 'src/MandiriQris.php';

$mandiri = new MandiriQris([
    'client_id' => 'your_id',
    'client_secret' => 'your_secret',
    'environment' => 'sandbox',
    'merchant_nmid' => 'YOUR_NMID',
    'merchant_name' => 'YOUR STORE',
    'merchant_city' => 'JAKARTA'
]);

$qris = $mandiri->createQris([
    'amount' => 100000,
    'reference' => 'ORDER-001'
]);
```

**[Full Documentation →](php-native/README.md)**

---

## 🔴 Laravel

### 1. Install Package

```bash
composer require mandiri-qris/laravel
php artisan vendor:publish --provider="MandiriQris\Laravel\MandiriQrisServiceProvider"
```

### 2. Configure

Add to `.env`:
```env
MANDIRI_CLIENT_ID=your_client_id
MANDIRI_CLIENT_SECRET=your_client_secret
MANDIRI_MERCHANT_NMID=YOUR_NMID
```

### 3. Run Migration

```bash
php artisan migrate
```

### 4. Use in Controller

```php
use MandiriQris\Laravel\Facades\MandiriQris;

$qris = MandiriQris::createQris([
    'amount' => 100000,
    'reference' => 'ORDER-001'
]);
```

**[Full Documentation →](laravel/README.md)**

---

## 🟡 CodeIgniter

### 1. Copy Library

```bash
# CI3
cp application/libraries/Mandiri_qris.php your-ci3/application/libraries/

# CI4
cp app/Libraries/MandiriQris.php your-ci4/app/Libraries/
```

### 2. Configure

**CI3** - Create `application/config/mandiri_qris.php`:
```php
$config['mandiri_client_id'] = 'your_client_id';
$config['mandiri_client_secret'] = 'your_client_secret';
```

**CI4** - Add to `.env`:
```env
mandiri.clientId = your_client_id
mandiri.clientSecret = your_client_secret
```

### 3. Use in Controller

```php
// CI3
$this->load->library('mandiri_qris');
$qris = $this->mandiri_qris->create_qris([...]);

// CI4
$mandiriQris = new \App\Libraries\MandiriQris();
$qris = $mandiriQris->createQris([...]);
```

**[Full Documentation →](codeigniter/README.md)**

---

## 🟢 Python

### 1. Install

```bash
cd python
pip install -r requirements.txt
pip install -e .
```

### 2. Configure

Create `.env`:
```env
MANDIRI_CLIENT_ID=your_client_id
MANDIRI_CLIENT_SECRET=your_client_secret
MANDIRI_MERCHANT_NMID=YOUR_NMID
```

### 3. Use

```python
from mandiri_qris import MandiriQrisClient

client = MandiriQrisClient(
    client_id='your_client_id',
    client_secret='your_client_secret',
    environment='sandbox'
)

qris = client.create_qris(
    amount=100000,
    reference='ORDER-001',
    merchant_nmid='YOUR_NMID',
    merchant_name='YOUR STORE',
    merchant_city='JAKARTA'
)
```

### Flask Example

```bash
python examples/flask_app.py
```

Visit: http://localhost:5000

**[Full Documentation →](python/README.md)**

---

## ☕ Java Spring Boot

### 1. Add Dependency

**Maven** - Add to `pom.xml`:
```xml
<dependency>
    <groupId>com.mandiri</groupId>
    <artifactId>qris-payment-sdk</artifactId>
    <version>1.0.0</version>
</dependency>
```

**Gradle** - Add to `build.gradle`:
```gradle
implementation 'com.mandiri:qris-payment-sdk:1.0.0'
```

### 2. Configure

Add to `application.properties`:
```properties
mandiri.qris.client-id=your_client_id
mandiri.qris.client-secret=your_client_secret
mandiri.qris.merchant-nmid=YOUR_NMID
```

### 3. Use in Service

```java
@Autowired
private MandiriQrisClient mandiriQrisClient;

public QrisResponse createPayment(BigDecimal amount, String orderId) {
    QrisRequest request = QrisRequest.builder()
        .amount(amount)
        .reference(orderId)
        .build();
    
    return mandiriQrisClient.createQris(request);
}
```

### 4. Run

```bash
mvn spring-boot:run
```

**[Full Documentation →](java/README.md)**

---

## 🟣 ASP.NET Core

### 1. Install Package

```bash
dotnet add package MandiriQris.AspNetCore
```

### 2. Configure

Add to `appsettings.json`:
```json
{
  "MandiriQris": {
    "ClientId": "your_client_id",
    "ClientSecret": "your_client_secret",
    "MerchantNmid": "YOUR_NMID"
  }
}
```

### 3. Register Service

In `Program.cs`:
```csharp
builder.Services.AddMandiriQris(
    builder.Configuration.GetSection("MandiriQris")
);
```

### 4. Use in Controller

```csharp
[ApiController]
[Route("api/[controller]")]
public class QrisController : ControllerBase
{
    private readonly IMandiriQrisService _qrisService;
    
    public QrisController(IMandiriQrisService qrisService)
    {
        _qrisService = qrisService;
    }
    
    [HttpPost("create")]
    public async Task<IActionResult> Create([FromBody] CreateQrisRequest request)
    {
        var qris = await _qrisService.CreateQrisAsync(request);
        return Ok(qris);
    }
}
```

### 5. Run

```bash
dotnet run
```

Visit: https://localhost:5001/swagger

**[Full Documentation →](aspnet/README.md)**

---

## 📊 Common Workflow

All implementations follow this workflow:

### 1. Create QRIS Payment

```
User initiates payment
    ↓
Create QRIS via API
    ↓
Save to database
    ↓
Display QR code to user
```

### 2. Monitor Payment

```
Frontend polls status every 3 seconds
    ↓
Check status via API
    ↓
If COMPLETED → Update database
    ↓
Redirect to success page
```

### 3. Handle Webhook (Optional)

```
Mandiri sends webhook
    ↓
Verify payload
    ↓
Update payment status
    ↓
Send notification
```

---

## 🔑 Getting Credentials

1. Register at [Mandiri Developer Portal](https://developers.bankmandiri.co.id)
2. Complete merchant onboarding
3. Get sandbox credentials:
   - Client ID
   - Client Secret
   - Merchant NMID

**For detailed steps:** See [IMPLEMENTATION_GUIDE.md](IMPLEMENTATION_GUIDE.md#getting-credentials)

---

## 🗄️ Database Setup

All platforms need a payments table:

```sql
CREATE TABLE payments (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    payment_id VARCHAR(100) UNIQUE,
    order_id VARCHAR(100) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    status ENUM('pending','paid','expired','failed'),
    qr_id VARCHAR(255),
    qr_string TEXT,
    qr_image_url VARCHAR(500),
    expired_at TIMESTAMP,
    paid_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 🧪 Testing

### Test Credentials

Use these for sandbox testing:
```
Environment: sandbox
Base URL: https://sandbox.bankmandiri.co.id
```

### Test Flow

1. Create QR code
2. Verify QR displays correctly
3. Status check returns pending
4. Simulate payment (via Mandiri test tools)
5. Status updates to completed
6. Database updates correctly

---

## 🚀 Production Deployment

Before going live:

- [ ] Get production credentials
- [ ] Update environment to `production`
- [ ] Change base URL to `https://api.bankmandiri.co.id`
- [ ] Enable HTTPS/SSL
- [ ] Test with small amounts
- [ ] Set up monitoring
- [ ] Configure webhook URL

**For detailed checklist:** See [IMPLEMENTATION_GUIDE.md#production-deployment](IMPLEMENTATION_GUIDE.md#production-deployment)

---

## 🆘 Troubleshooting

### "Failed to get access token"
- Check client_id and client_secret
- Verify credentials in Mandiri portal

### "cURL error"
- Enable cURL extension
- Update SSL certificates

### "QR code expired"
- Default expiry is 30 minutes
- Generate new QR code

### "Webhook not working"
- Verify URL is publicly accessible
- Check firewall settings
- Test with webhook.site

**More solutions:** See [IMPLEMENTATION_GUIDE.md#troubleshooting](IMPLEMENTATION_GUIDE.md#troubleshooting)

---

## 📚 Additional Resources

- **Full Documentation**: [QRIS_MANDIRI_PAYMENT_SUMMARY.md](QRIS_MANDIRI_PAYMENT_SUMMARY.md)
- **Implementation Guide**: [IMPLEMENTATION_GUIDE.md](IMPLEMENTATION_GUIDE.md)
- **Platform READMEs**: Check each platform directory
- **Mandiri Docs**: https://developers.bankmandiri.co.id

---

## 💬 Support

- **GitHub Issues**: Report bugs and request features
- **Mandiri Support**: developer.support@bankmandiri.co.id
- **Phone**: 14000 (Business hours: Mon-Fri 9 AM - 5 PM WIB)

---

## 📄 License

MIT License - Free to use in commercial and personal projects

---

**Ready to start?** Choose your platform above and follow the steps! 🚀

**Last Updated:** December 30, 2025
