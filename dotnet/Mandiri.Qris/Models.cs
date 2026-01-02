using System.Text.Json.Serialization;

namespace Mandiri.Qris.Models;

public class QRRequest
{
    public int Amount { get; set; }
    
    [JsonPropertyName("merchant_id")]
    public string? MerchantId { get; set; }
    
    [JsonPropertyName("terminal_id")]
    public string? TerminalId { get; set; }
    
    [JsonPropertyName("invoice_number")]
    public string? InvoiceNumber { get; set; }
    
    [JsonPropertyName("customer_name")]
    public string? CustomerName { get; set; }
    
    [JsonPropertyName("customer_phone")]
    public string? CustomerPhone { get; set; }
}

public class QRResponse
{
    [JsonPropertyName("transaction_id")]
    public string TransactionId { get; set; } = string.Empty;
    
    [JsonPropertyName("qr_string")]
    public string QrString { get; set; } = string.Empty;
    
    [JsonPropertyName("qr_image")]
    public string QrImage { get; set; } = string.Empty;
    
    public int Amount { get; set; }
    
    [JsonPropertyName("merchant_id")]
    public string MerchantId { get; set; } = string.Empty;
    
    [JsonPropertyName("terminal_id")]
    public string TerminalId { get; set; } = string.Empty;
}

public class PaymentStatus
{
    [JsonPropertyName("transaction_id")]
    public string TransactionId { get; set; } = string.Empty;
    
    public string Status { get; set; } = string.Empty;
    public int Amount { get; set; }
    
    [JsonPropertyName("merchant_id")]
    public string MerchantId { get; set; } = string.Empty;
    
    [JsonPropertyName("paid_at")]
    public string? PaidAt { get; set; }
}

public class TokenResponse
{
    [JsonPropertyName("access_token")]
    public string AccessToken { get; set; } = string.Empty;
    
    [JsonPropertyName("expires_in")]
    public int ExpiresIn { get; set; }
}
