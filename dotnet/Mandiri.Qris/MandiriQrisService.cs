using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using Mandiri.Qris.Models;
using Microsoft.Extensions.Options;

namespace Mandiri.Qris;

public interface IMandiriQrisService
{
    Task<QRResponse> GenerateQRAsync(QRRequest request);
    Task<PaymentStatus> CheckPaymentStatusAsync(string transactionId);
    bool VerifyWebhookSignature(string payload, string signature);
    Task<PaymentStatus> PollPaymentStatusAsync(string transactionId, int maxAttempts = 60, int intervalSeconds = 5);
}

public class MandiriQrisService : IMandiriQrisService
{
    private readonly MandiriQrisOptions _options;
    private readonly HttpClient _httpClient;
    private string? _accessToken;
    private DateTime? _tokenExpiry;

    public MandiriQrisService(IOptions<MandiriQrisOptions> options, HttpClient httpClient)
    {
        _options = options.Value;
        _httpClient = httpClient;
    }

    private async Task<string> GetAccessTokenAsync()
    {
        if (_accessToken != null && _tokenExpiry != null && DateTime.UtcNow < _tokenExpiry)
        {
            return _accessToken;
        }

        var url = $"{_options.GetBaseUrl()}/oauth/token";
        var data = new
        {
            grant_type = "client_credentials",
            client_id = _options.ClientId,
            client_secret = _options.ClientSecret
        };

        var response = await _httpClient.PostAsJsonAsync(url, data);
        response.EnsureSuccessStatusCode();

        var tokenResponse = await response.Content.ReadFromJsonAsync<TokenResponse>();
        if (tokenResponse == null)
        {
            throw new Exception("Failed to obtain access token");
        }

        _accessToken = tokenResponse.AccessToken;
        _tokenExpiry = DateTime.UtcNow.AddSeconds(tokenResponse.ExpiresIn - 60);

        return _accessToken;
    }

    public async Task<QRResponse> GenerateQRAsync(QRRequest request)
    {
        var token = await GetAccessTokenAsync();
        var url = $"{_options.GetBaseUrl()}/api/v1/qris/generate";

        var data = new
        {
            amount = request.Amount,
            merchant_id = request.MerchantId ?? _options.MerchantId,
            terminal_id = request.TerminalId ?? _options.TerminalId,
            invoice_number = request.InvoiceNumber ?? GenerateInvoiceNumber(),
            customer_name = request.CustomerName ?? "",
            customer_phone = request.CustomerPhone ?? "",
            timestamp = DateTime.UtcNow.ToString("o")
        };

        using var requestMessage = new HttpRequestMessage(HttpMethod.Post, url);
        requestMessage.Headers.Add("Authorization", $"Bearer {token}");
        requestMessage.Content = JsonContent.Create(data);

        var response = await _httpClient.SendAsync(requestMessage);
        response.EnsureSuccessStatusCode();

        var qrResponse = await response.Content.ReadFromJsonAsync<QRResponse>();
        if (qrResponse == null)
        {
            throw new Exception("Failed to generate QR");
        }

        return qrResponse;
    }

    public async Task<PaymentStatus> CheckPaymentStatusAsync(string transactionId)
    {
        var token = await GetAccessTokenAsync();
        var url = $"{_options.GetBaseUrl()}/api/v1/qris/status/{transactionId}";

        using var requestMessage = new HttpRequestMessage(HttpMethod.Get, url);
        requestMessage.Headers.Add("Authorization", $"Bearer {token}");

        var response = await _httpClient.SendAsync(requestMessage);
        response.EnsureSuccessStatusCode();

        var status = await response.Content.ReadFromJsonAsync<PaymentStatus>();
        if (status == null)
        {
            throw new Exception("Failed to check payment status");
        }

        return status;
    }

    public bool VerifyWebhookSignature(string payload, string signature)
    {
        using var hmac = new HMACSHA256(Encoding.UTF8.GetBytes(_options.ClientSecret));
        var hash = hmac.ComputeHash(Encoding.UTF8.GetBytes(payload));
        var calculatedSignature = BitConverter.ToString(hash).Replace("-", "").ToLower();

        return calculatedSignature == signature;
    }

    public async Task<PaymentStatus> PollPaymentStatusAsync(string transactionId, int maxAttempts = 60, int intervalSeconds = 5)
    {
        int attempts = 0;

        while (attempts < maxAttempts)
        {
            var status = await CheckPaymentStatusAsync(transactionId);

            if (status.Status == "SUCCESS" || status.Status == "FAILED" || status.Status == "EXPIRED")
            {
                return status;
            }

            await Task.Delay(intervalSeconds * 1000);
            attempts++;
        }

        throw new TimeoutException("Payment status polling timeout");
    }

    private string GenerateInvoiceNumber()
    {
        var timestamp = DateTime.Now.ToString("yyyyMMddHHmmss");
        var random = new Random().Next(1000, 9999);
        return $"INV-{timestamp}-{random}";
    }
}
