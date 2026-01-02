# ASP.NET Core - Mandiri QRIS Payment SDK

ASP.NET Core implementation for Mandiri QRIS Payment Gateway.

## 📋 Requirements

- .NET 6.0 or higher
- Visual Studio 2022 or VS Code
- SQL Server or PostgreSQL

## 🚀 Installation

### NuGet Package

```bash
dotnet add package MandiriQris.AspNetCore
```

Or via Package Manager Console:

```powershell
Install-Package MandiriQris.AspNetCore
```

## ⚙️ Configuration

Add to `appsettings.json`:

```json
{
  "MandiriQris": {
    "Environment": "sandbox",
    "ClientId": "your_client_id",
    "ClientSecret": "your_client_secret",
    "MerchantNmid": "YOUR_NMID",
    "MerchantName": "YOUR MERCHANT NAME",
    "MerchantCity": "JAKARTA",
    "QrisExpiryMinutes": 30,
    "SandboxBaseUrl": "https://sandbox.bankmandiri.co.id",
    "ProductionBaseUrl": "https://api.bankmandiri.co.id"
  },
  "ConnectionStrings": {
    "DefaultConnection": "Server=localhost;Database=MandiriQris;Trusted_Connection=True;MultipleActiveResultSets=true"
  }
}
```

## 📝 Setup

### 1. Register Services

In `Program.cs`:

```csharp
using MandiriQris.AspNetCore;

var builder = WebApplication.CreateBuilder(args);

// Add services
builder.Services.AddControllers();
builder.Services.AddEndpointsApiExplorer();
builder.Services.AddSwaggerGen();

// Add DbContext
builder.Services.AddDbContext<ApplicationDbContext>(options =>
    options.UseSqlServer(builder.Configuration.GetConnectionString("DefaultConnection")));

// Add Mandiri QRIS Service
builder.Services.AddMandiriQris(builder.Configuration.GetSection("MandiriQris"));

var app = builder.Build();

// Configure pipeline
if (app.Environment.IsDevelopment())
{
    app.UseSwagger();
    app.UseSwaggerUI();
}

app.UseHttpsRedirection();
app.UseAuthorization();
app.MapControllers();

app.Run();
```

### 2. Create Models

```csharp
public class Payment
{
    [Key]
    public int Id { get; set; }
    
    [Required]
    [StringLength(100)]
    public string PaymentId { get; set; }
    
    [Required]
    [StringLength(100)]
    public string OrderId { get; set; }
    
    public int? UserId { get; set; }
    
    [Column(TypeName = "decimal(15,2)")]
    public decimal Amount { get; set; }
    
    [StringLength(50)]
    public string PaymentMethod { get; set; } = "qris";
    
    public PaymentStatus Status { get; set; } = PaymentStatus.Pending;
    
    [StringLength(255)]
    public string QrId { get; set; }
    
    public string QrString { get; set; }
    
    [StringLength(500)]
    public string QrImageUrl { get; set; }
    
    [StringLength(255)]
    public string TransactionId { get; set; }
    
    public DateTime? ExpiredAt { get; set; }
    
    public DateTime? PaidAt { get; set; }
    
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    
    public DateTime UpdatedAt { get; set; } = DateTime.UtcNow;
}

public enum PaymentStatus
{
    Pending,
    Paid,
    Expired,
    Failed
}
```

### 3. Create Service

```csharp
public interface IPaymentService
{
    Task<QrisResponse> CreatePaymentAsync(decimal amount, string orderId);
    Task<PaymentStatusResponse> CheckStatusAsync(string qrId);
    Task ProcessWebhookAsync(WebhookPayload payload);
}

public class PaymentService : IPaymentService
{
    private readonly IMandiriQrisService _mandiriService;
    private readonly ApplicationDbContext _context;
    private readonly ILogger<PaymentService> _logger;
    
    public PaymentService(
        IMandiriQrisService mandiriService,
        ApplicationDbContext context,
        ILogger<PaymentService> logger)
    {
        _mandiriService = mandiriService;
        _context = context;
        _logger = logger;
    }
    
    public async Task<QrisResponse> CreatePaymentAsync(decimal amount, string orderId)
    {
        try
        {
            var request = new QrisRequest
            {
                Amount = amount,
                Reference = orderId
            };
            
            var qris = await _mandiriService.CreateQrisAsync(request);
            
            var payment = new Payment
            {
                PaymentId = $"PAY-{orderId}",
                OrderId = orderId,
                Amount = amount,
                QrId = qris.QrId,
                QrString = qris.QrString,
                QrImageUrl = qris.QrImageUrl,
                Status = PaymentStatus.Pending,
                ExpiredAt = qris.ExpiredAt
            };
            
            _context.Payments.Add(payment);
            await _context.SaveChangesAsync();
            
            return qris;
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Error creating payment");
            throw;
        }
    }
    
    public async Task<PaymentStatusResponse> CheckStatusAsync(string qrId)
    {
        var status = await _mandiriService.CheckStatusAsync(qrId);
        
        if (status.Status == "COMPLETED")
        {
            var payment = await _context.Payments
                .FirstOrDefaultAsync(p => p.QrId == qrId);
            
            if (payment != null && payment.Status == PaymentStatus.Pending)
            {
                payment.Status = PaymentStatus.Paid;
                payment.PaidAt = DateTime.UtcNow;
                payment.TransactionId = status.TransactionId;
                payment.UpdatedAt = DateTime.UtcNow;
                
                await _context.SaveChangesAsync();
            }
        }
        
        return status;
    }
    
    public async Task ProcessWebhookAsync(WebhookPayload payload)
    {
        _logger.LogInformation("Processing webhook: {@Payload}", payload);
        
        if (payload.Status == "COMPLETED")
        {
            var payment = await _context.Payments
                .FirstOrDefaultAsync(p => p.QrId == payload.QrId);
            
            if (payment != null && payment.Status == PaymentStatus.Pending)
            {
                payment.Status = PaymentStatus.Paid;
                payment.PaidAt = DateTime.UtcNow;
                payment.UpdatedAt = DateTime.UtcNow;
                
                await _context.SaveChangesAsync();
                
                // Trigger event or notification
                // await _notificationService.SendPaymentConfirmationAsync(payment);
            }
        }
    }
}
```

### 4. Create Controller

```csharp
[ApiController]
[Route("api/[controller]")]
public class QrisController : ControllerBase
{
    private readonly IPaymentService _paymentService;
    private readonly ILogger<QrisController> _logger;
    
    public QrisController(
        IPaymentService paymentService,
        ILogger<QrisController> logger)
    {
        _paymentService = paymentService;
        _logger = logger;
    }
    
    [HttpPost("create")]
    public async Task<ActionResult<ApiResponse<QrisResponse>>> CreateQris(
        [FromBody] CreateQrisRequest request)
    {
        try
        {
            var qris = await _paymentService.CreatePaymentAsync(
                request.Amount,
                request.OrderId
            );
            
            return Ok(new ApiResponse<QrisResponse>
            {
                Success = true,
                Data = qris
            });
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Error creating QRIS");
            return StatusCode(500, new ApiResponse<QrisResponse>
            {
                Success = false,
                Error = ex.Message
            });
        }
    }
    
    [HttpGet("status/{qrId}")]
    public async Task<ActionResult<ApiResponse<PaymentStatusResponse>>> CheckStatus(
        string qrId)
    {
        try
        {
            var status = await _paymentService.CheckStatusAsync(qrId);
            
            return Ok(new ApiResponse<PaymentStatusResponse>
            {
                Success = true,
                Data = status
            });
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Error checking status");
            return StatusCode(500, new ApiResponse<PaymentStatusResponse>
            {
                Success = false,
                Error = ex.Message
            });
        }
    }
    
    [HttpPost("webhook")]
    public async Task<ActionResult> Webhook([FromBody] WebhookPayload payload)
    {
        try
        {
            await _paymentService.ProcessWebhookAsync(payload);
            return Ok(new { status = "ok" });
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Error processing webhook");
            return StatusCode(500, new { error = ex.Message });
        }
    }
}

public class CreateQrisRequest
{
    [Required]
    [Range(10000, double.MaxValue, ErrorMessage = "Amount must be at least 10,000")]
    public decimal Amount { get; set; }
    
    [Required]
    [StringLength(100)]
    public string OrderId { get; set; }
}

public class ApiResponse<T>
{
    public bool Success { get; set; }
    public T Data { get; set; }
    public string Error { get; set; }
}
```

### 5. Create View (Razor Page)

```cshtml
@page "/payment/{orderId}"
@model PaymentModel
@{
    ViewData["Title"] = "QRIS Payment";
}

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Scan QR Code untuk Pembayaran</h4>
                </div>
                <div class="card-body text-center">
                    <div id="timer" class="fs-2 fw-bold text-primary mb-3">30:00</div>
                    
                    <div class="qr-container mb-4">
                        <img src="@Model.Payment.QrImageUrl" 
                             alt="QR Code" 
                             class="img-fluid" 
                             style="max-width: 300px;">
                    </div>
                    
                    <div class="payment-info bg-light p-3 rounded mb-3">
                        <p><strong>Amount:</strong> Rp @Model.Payment.Amount.ToString("N0")</p>
                        <p><strong>Order ID:</strong> @Model.Payment.OrderId</p>
                        <p><strong>Status:</strong> 
                            <span id="status-badge" class="badge bg-warning">Pending</span>
                        </p>
                    </div>
                    
                    <div id="loading" class="mb-3">
                        <div class="spinner-border text-primary"></div>
                        <p>Waiting for payment...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@section Scripts {
    <script>
        const qrId = '@Model.Payment.QrId';
        let remainingSeconds = 30 * 60;
        
        // Timer
        setInterval(() => {
            remainingSeconds--;
            const minutes = Math.floor(remainingSeconds / 60);
            const seconds = remainingSeconds % 60;
            document.getElementById('timer').textContent = 
                `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            if (remainingSeconds <= 0) {
                showExpired();
            }
        }, 1000);
        
        // Polling
        setInterval(async () => {
            try {
                const response = await fetch(`/api/qris/status/${qrId}`);
                const data = await response.json();
                
                if (data.success && data.data.status === 'COMPLETED') {
                    showSuccess();
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }, 3000);
        
        function showSuccess() {
            document.getElementById('status-badge').textContent = 'Paid';
            document.getElementById('status-badge').className = 'badge bg-success';
            document.getElementById('loading').innerHTML = 
                '<div class="alert alert-success">✓ Payment Successful!</div>';
            setTimeout(() => window.location.href = '/payment/success', 2000);
        }
        
        function showExpired() {
            document.getElementById('status-badge').textContent = 'Expired';
            document.getElementById('status-badge').className = 'badge bg-danger';
            document.getElementById('loading').innerHTML = 
                '<div class="alert alert-danger">QR Code Expired</div>';
        }
    </script>
}
```

## 🧪 Testing

```csharp
public class QrisControllerTests
{
    private readonly Mock<IPaymentService> _paymentServiceMock;
    private readonly QrisController _controller;
    
    public QrisControllerTests()
    {
        _paymentServiceMock = new Mock<IPaymentService>();
        var loggerMock = new Mock<ILogger<QrisController>>();
        _controller = new QrisController(_paymentServiceMock.Object, loggerMock.Object);
    }
    
    [Fact]
    public async Task CreateQris_ReturnsOk_WhenSuccessful()
    {
        // Arrange
        var request = new CreateQrisRequest
        {
            Amount = 100000,
            OrderId = "ORDER-001"
        };
        
        var expectedResponse = new QrisResponse
        {
            QrId = "QR123456789",
            QrString = "00020101...",
            Status = "ACTIVE"
        };
        
        _paymentServiceMock
            .Setup(x => x.CreatePaymentAsync(It.IsAny<decimal>(), It.IsAny<string>()))
            .ReturnsAsync(expectedResponse);
        
        // Act
        var result = await _controller.CreateQris(request);
        
        // Assert
        var okResult = Assert.IsType<OkObjectResult>(result.Result);
        var response = Assert.IsType<ApiResponse<QrisResponse>>(okResult.Value);
        Assert.True(response.Success);
        Assert.Equal("QR123456789", response.Data.QrId);
    }
}
```

## 🚀 Build & Run

```bash
# Restore packages
dotnet restore

# Build
dotnet build

# Run
dotnet run

# Or with watch (auto-reload)
dotnet watch run
```

Visit: https://localhost:5001/swagger

## 📄 License

MIT License
