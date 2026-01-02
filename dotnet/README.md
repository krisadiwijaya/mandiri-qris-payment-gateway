# ASP.NET Core SDK for Mandiri QRIS Payment Gateway

ASP.NET Core SDK for integrating Mandiri QRIS Payment Gateway.

## Installation

```bash
dotnet add package Mandiri.Qris
```

Or via NuGet Package Manager:

```
Install-Package Mandiri.Qris
```

## Configuration

In `Startup.cs` or `Program.cs`:

```csharp
services.AddMandiriQris(options =>
{
    options.ClientId = Configuration["Mandiri:ClientId"];
    options.ClientSecret = Configuration["Mandiri:ClientSecret"];
    options.BaseUrl = "https://api.mandiri.co.id";
    options.Sandbox = true;
    options.MerchantId = "MERCHANT123";
    options.TerminalId = "TERM001";
});
```

In `appsettings.json`:

```json
{
  "Mandiri": {
    "ClientId": "your_client_id",
    "ClientSecret": "your_client_secret"
  }
}
```

## Usage

### Inject the Service

```csharp
public class PaymentController : Controller
{
    private readonly IMandiriQrisService _qrisService;
    
    public PaymentController(IMandiriQrisService qrisService)
    {
        _qrisService = qrisService;
    }
}
```

### Generate QR Code

```csharp
[HttpGet("generate-qr")]
public async Task<IActionResult> GenerateQR()
{
    try
    {
        var request = new QRRequest
        {
            Amount = 100000,
            MerchantId = "MERCHANT123",
            TerminalId = "TERM001",
            CustomerName = "John Doe",
            CustomerPhone = "081234567890"
        };
        
        var qr = await _qrisService.GenerateQRAsync(request);
        return Ok(qr);
    }
    catch (Exception ex)
    {
        return StatusCode(500, new { error = ex.Message });
    }
}
```

### Check Payment Status

```csharp
[HttpGet("status/{transactionId}")]
public async Task<IActionResult> CheckStatus(string transactionId)
{
    try
    {
        var status = await _qrisService.CheckPaymentStatusAsync(transactionId);
        return Ok(status);
    }
    catch (Exception ex)
    {
        return StatusCode(500, new { error = ex.Message });
    }
}
```

### Webhook Handler

```csharp
[HttpPost("webhook/mandiri-qris")]
public async Task<IActionResult> Webhook()
{
    try
    {
        using var reader = new StreamReader(Request.Body);
        var rawPayload = await reader.ReadToEndAsync();
        var signature = Request.Headers["X-Signature"].ToString();
        
        if (!_qrisService.VerifyWebhookSignature(rawPayload, signature))
        {
            return BadRequest(new { error = "Invalid signature" });
        }
        
        var payload = JsonSerializer.Deserialize<Dictionary<string, object>>(rawPayload);
        
        if (payload["status"].ToString() == "SUCCESS")
        {
            // Payment successful
            // Update database, send confirmation, etc.
        }
        
        return Ok(new { status = "ok" });
    }
    catch (Exception ex)
    {
        return BadRequest(new { error = ex.Message });
    }
}
```

### Payment Polling

```csharp
// Poll every 5 seconds for up to 5 minutes
var finalStatus = await _qrisService.PollPaymentStatusAsync(transactionId, 60, 5);

if (finalStatus.Status == "SUCCESS")
{
    Console.WriteLine("Payment completed!");
}
```

## Minimal API Example (.NET 6+)

```csharp
var builder = WebApplication.CreateBuilder(args);

builder.Services.AddMandiriQris(options =>
{
    options.ClientId = builder.Configuration["Mandiri:ClientId"];
    options.ClientSecret = builder.Configuration["Mandiri:ClientSecret"];
    options.Sandbox = true;
});

var app = builder.Build();

app.MapPost("/webhook/mandiri-qris", async (
    HttpRequest request,
    IMandiriQrisService qrisService) =>
{
    using var reader = new StreamReader(request.Body);
    var rawPayload = await reader.ReadToEndAsync();
    var signature = request.Headers["X-Signature"].ToString();
    
    if (!qrisService.VerifyWebhookSignature(rawPayload, signature))
    {
        return Results.BadRequest(new { error = "Invalid signature" });
    }
    
    return Results.Ok(new { status = "ok" });
});

app.Run();
```

## License

MIT
