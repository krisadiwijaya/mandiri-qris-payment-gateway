using Microsoft.AspNetCore.Mvc;
using MandiriQris.Data;
using MandiriQris.Models;
using MandiriQris.Services;
using System.ComponentModel.DataAnnotations;

namespace MandiriQris.Controllers
{
    [ApiController]
    [Route("api/[controller]")]
    public class QrisController : ControllerBase
    {
        private readonly MandiriQrisService _mandiriQrisService;
        private readonly ApplicationDbContext _dbContext;
        private readonly ILogger<QrisController> _logger;

        public QrisController(
            MandiriQrisService mandiriQrisService, 
            ApplicationDbContext dbContext,
            ILogger<QrisController> logger)
        {
            _mandiriQrisService = mandiriQrisService;
            _dbContext = dbContext;
            _logger = logger;
        }

        /// <summary>
        /// Create QRIS payment
        /// </summary>
        [HttpPost("create")]
        public async Task<IActionResult> Create([FromBody] CreateQrisRequestDto request)
        {
            try
            {
                // Check if reference already exists
                if (await _dbContext.Payments.AnyAsync(p => p.Reference == request.Reference))
                {
                    return BadRequest(new { success = false, message = "Reference already exists" });
                }

                // Create QRIS
                var qrisRequest = new CreateQrisRequest
                {
                    Amount = request.Amount,
                    Reference = request.Reference,
                    CallbackUrl = request.CallbackUrl
                };

                var qrisResponse = await _mandiriQrisService.CreateQrisAsync(qrisRequest);

                // Save to database
                var payment = new MandiriQrisPayment
                {
                    QrId = qrisResponse.QrId,
                    Reference = qrisResponse.Reference,
                    QrString = qrisResponse.QrString,
                    QrImageUrl = qrisResponse.QrImageUrl,
                    Amount = qrisResponse.Amount,
                    Status = qrisResponse.Status,
                    ExpiredAt = DateTime.UtcNow.AddMinutes(5),
                    CreatedAt = DateTime.UtcNow
                };

                _dbContext.Payments.Add(payment);
                await _dbContext.SaveChangesAsync();

                return Ok(new { success = true, data = qrisResponse });
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Failed to create QRIS");
                return StatusCode(500, new { success = false, message = ex.Message });
            }
        }

        /// <summary>
        /// Check payment status
        /// </summary>
        [HttpGet("status/{qrId}")]
        public async Task<IActionResult> CheckStatus(string qrId)
        {
            try
            {
                // Get payment from database
                var payment = await _dbContext.Payments.FirstOrDefaultAsync(p => p.QrId == qrId);

                if (payment == null)
                {
                    return NotFound(new { success = false, message = "Payment not found" });
                }

                // If already completed, return cached status
                if (payment.Status == "COMPLETED")
                {
                    return Ok(new
                    {
                        success = true,
                        data = new
                        {
                            qrId = payment.QrId,
                            status = payment.Status,
                            amount = payment.Amount,
                            paidAt = payment.PaidAt,
                            transactionId = payment.TransactionId
                        }
                    });
                }

                // Check status from API
                var statusResponse = await _mandiriQrisService.CheckStatusAsync(qrId, payment.Reference);

                // Update database if status changed
                if (statusResponse.Status != payment.Status)
                {
                    payment.Status = statusResponse.Status;
                    payment.TransactionId = statusResponse.TransactionId;
                    if (statusResponse.PaidAt != null)
                    {
                        payment.PaidAt = DateTime.UtcNow;
                    }
                    payment.UpdatedAt = DateTime.UtcNow;
                    await _dbContext.SaveChangesAsync();
                }

                return Ok(new { success = true, data = statusResponse });
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Failed to check status");
                return StatusCode(500, new { success = false, message = ex.Message });
            }
        }

        /// <summary>
        /// Handle webhook notification
        /// </summary>
        [HttpPost("webhook")]
        public async Task<IActionResult> Webhook([FromBody] WebhookPayload payload)
        {
            try
            {
                var qrId = payload.QrId ?? payload.OriginalReferenceNo;
                var statusCode = payload.TransactionStatusCode;

                if (string.IsNullOrEmpty(qrId))
                {
                    return BadRequest(new { success = false, message = "Missing qrId" });
                }

                // Get payment from database
                var payment = await _dbContext.Payments.FirstOrDefaultAsync(p => p.QrId == qrId);

                if (payment == null)
                {
                    return NotFound(new { success = false, message = "Payment not found" });
                }

                // Map status code
                var newStatus = statusCode switch
                {
                    "00" => "COMPLETED",
                    "03" => "PENDING",
                    "05" => "EXPIRED",
                    _ => "FAILED"
                };

                // Update payment status
                payment.Status = newStatus;
                if (newStatus == "COMPLETED")
                {
                    payment.TransactionId = payload.ReferenceNo;
                    payment.PaidAt = DateTime.UtcNow;
                }
                payment.UpdatedAt = DateTime.UtcNow;

                await _dbContext.SaveChangesAsync();

                return Ok(new { success = true, message = "Webhook processed" });
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Failed to process webhook");
                return StatusCode(500, new { success = false, message = ex.Message });
            }
        }
    }

    // DTOs
    public class CreateQrisRequestDto
    {
        [Required]
        [Range(1, double.MaxValue, ErrorMessage = "Amount must be greater than 0")]
        public double Amount { get; set; }

        [Required]
        public string Reference { get; set; } = string.Empty;

        public string? CallbackUrl { get; set; }
    }

    public class WebhookPayload
    {
        public string? QrId { get; set; }
        public string? OriginalReferenceNo { get; set; }
        public string? TransactionStatusCode { get; set; }
        public string? ReferenceNo { get; set; }
    }
}
