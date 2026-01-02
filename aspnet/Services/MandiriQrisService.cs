using System.Security.Cryptography;
using System.Text;
using Microsoft.Extensions.Caching.Memory;
using Microsoft.Extensions.Options;
using Newtonsoft.Json;

namespace MandiriQris.Services
{
    public class MandiriQrisService
    {
        private readonly MandiriQrisConfig _config;
        private readonly IMemoryCache _cache;
        private readonly HttpClient _httpClient;
        private readonly ILogger<MandiriQrisService> _logger;

        public MandiriQrisService(
            IOptions<MandiriQrisConfig> config, 
            IMemoryCache cache,
            HttpClient httpClient,
            ILogger<MandiriQrisService> logger)
        {
            _config = config.Value;
            _cache = cache;
            _httpClient = httpClient;
            _logger = logger;
            _httpClient.Timeout = TimeSpan.FromSeconds(_config.Timeout);
        }

        /// <summary>
        /// Get access token (with caching)
        /// </summary>
        public async Task<string> GetAccessTokenAsync()
        {
            var cacheKey = $"mandiri_qris_token_{_config.ClientId}";

            // Try to get from cache
            if (_cache.TryGetValue(cacheKey, out string? cachedToken) && cachedToken != null)
            {
                return cachedToken;
            }

            // Request new token
            var timestamp = DateTime.UtcNow.ToString("yyyy-MM-dd'T'HH:mm:ss.fff'Z'");
            var signature = GenerateSignature($"{_config.ClientId}|{timestamp}", _config.ClientSecret);

            var request = new HttpRequestMessage(HttpMethod.Post, $"{_config.BaseUrl}/openapi/auth/v2.0/access-token/b2b");
            request.Headers.Add("X-TIMESTAMP", timestamp);
            request.Headers.Add("X-CLIENT-KEY", _config.ClientId);
            request.Headers.Add("X-SIGNATURE", signature);

            var body = new { grantType = "client_credentials" };
            request.Content = new StringContent(JsonConvert.SerializeObject(body), Encoding.UTF8, "application/json");

            var response = await _httpClient.SendAsync(request);
            
            if (!response.IsSuccessStatusCode)
            {
                var errorContent = await response.Content.ReadAsStringAsync();
                _logger.LogError("Failed to get access token: {Error}", errorContent);
                throw new Exception($"Failed to get access token: HTTP {response.StatusCode}");
            }

            var content = await response.Content.ReadAsStringAsync();
            var data = JsonConvert.DeserializeObject<dynamic>(content);

            if (data?.accessToken == null)
            {
                throw new Exception("Failed to get access token: Invalid response");
            }

            string token = data.accessToken;
            int expiresIn = data.expiresIn ?? 3600;

            // Cache token (with 60 seconds safety margin)
            _cache.Set(cacheKey, token, TimeSpan.FromSeconds(expiresIn - 60));

            return token;
        }

        /// <summary>
        /// Create QRIS payment
        /// </summary>
        public async Task<QrisResponse> CreateQrisAsync(CreateQrisRequest qrisRequest)
        {
            var token = await GetAccessTokenAsync();
            var timestamp = DateTime.UtcNow.ToString("yyyy-MM-dd'T'HH:mm:ss.fff'Z'");
            var expiryTime = DateTime.UtcNow.AddMinutes(_config.QrisExpiryMinutes).ToString("yyyy-MM-dd'T'HH:mm:ss.fff'Z'");

            var request = new HttpRequestMessage(HttpMethod.Post, $"{_config.BaseUrl}/openapi/qris/v1.0/qr-code-dynamic");
            request.Headers.Add("Authorization", $"Bearer {token}");
            request.Headers.Add("X-TIMESTAMP", timestamp);
            request.Headers.Add("X-PARTNER-ID", _config.MerchantNmid);
            request.Headers.Add("X-EXTERNAL-ID", qrisRequest.Reference);

            var body = new
            {
                partnerReferenceNo = qrisRequest.Reference,
                amount = new
                {
                    value = qrisRequest.Amount.ToString("F2"),
                    currency = "IDR"
                },
                merchantId = _config.MerchantNmid,
                storeLabel = _config.MerchantName,
                terminalLabel = _config.MerchantCity,
                validityPeriod = expiryTime,
                additionalInfo = !string.IsNullOrEmpty(qrisRequest.CallbackUrl) 
                    ? new { callbackUrl = qrisRequest.CallbackUrl }
                    : null
            };

            request.Content = new StringContent(JsonConvert.SerializeObject(body), Encoding.UTF8, "application/json");

            var response = await _httpClient.SendAsync(request);

            if (!response.IsSuccessStatusCode)
            {
                var errorContent = await response.Content.ReadAsStringAsync();
                _logger.LogError("Failed to create QRIS: {Error}", errorContent);
                throw new Exception($"Failed to create QRIS: HTTP {response.StatusCode}");
            }

            var content = await response.Content.ReadAsStringAsync();
            var data = JsonConvert.DeserializeObject<dynamic>(content);

            if (data?.qrContent == null)
            {
                throw new Exception("Failed to create QRIS: Invalid response");
            }

            string qrContent = data.qrContent;
            string qrId = data.qrId ?? qrisRequest.Reference;

            return new QrisResponse
            {
                QrId = qrId,
                QrString = qrContent,
                QrImageUrl = $"https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={Uri.EscapeDataString(qrContent)}",
                Status = "PENDING",
                Amount = qrisRequest.Amount,
                Reference = qrisRequest.Reference,
                ExpiredAt = expiryTime
            };
        }

        /// <summary>
        /// Check payment status
        /// </summary>
        public async Task<PaymentStatusResponse> CheckStatusAsync(string qrId, string reference)
        {
            var token = await GetAccessTokenAsync();
            var timestamp = DateTime.UtcNow.ToString("yyyy-MM-dd'T'HH:mm:ss.fff'Z'");

            var request = new HttpRequestMessage(HttpMethod.Post, $"{_config.BaseUrl}/openapi/qris/v1.0/qr-code-dynamic/status");
            request.Headers.Add("Authorization", $"Bearer {token}");
            request.Headers.Add("X-TIMESTAMP", timestamp);
            request.Headers.Add("X-PARTNER-ID", _config.MerchantNmid);
            request.Headers.Add("X-EXTERNAL-ID", qrId);

            var body = new
            {
                originalPartnerReferenceNo = reference,
                originalReferenceNo = qrId,
                serviceCode = "47"
            };

            request.Content = new StringContent(JsonConvert.SerializeObject(body), Encoding.UTF8, "application/json");

            var response = await _httpClient.SendAsync(request);

            if (!response.IsSuccessStatusCode)
            {
                var errorContent = await response.Content.ReadAsStringAsync();
                _logger.LogError("Failed to check status: {Error}", errorContent);
                throw new Exception($"Failed to check status: HTTP {response.StatusCode}");
            }

            var content = await response.Content.ReadAsStringAsync();
            var data = JsonConvert.DeserializeObject<dynamic>(content);

            var statusResponse = new PaymentStatusResponse
            {
                QrId = qrId,
                Status = "UNKNOWN"
            };

            if (data?.transactionStatusCode != null)
            {
                string statusCode = data.transactionStatusCode;
                statusResponse.Status = statusCode switch
                {
                    "00" => "COMPLETED",
                    "03" => "PENDING",
                    "05" => "EXPIRED",
                    _ => "FAILED"
                };

                if (statusResponse.Status == "COMPLETED")
                {
                    statusResponse.PaidAt = data.transactionDate ?? DateTime.UtcNow.ToString("yyyy-MM-dd'T'HH:mm:ss.fff'Z'");
                    statusResponse.TransactionId = data.referenceNo;
                }
            }

            if (data?.amount?.value != null)
            {
                statusResponse.Amount = (double)data.amount.value;
            }

            return statusResponse;
        }

        /// <summary>
        /// Clear cached token
        /// </summary>
        public void ClearToken()
        {
            var cacheKey = $"mandiri_qris_token_{_config.ClientId}";
            _cache.Remove(cacheKey);
        }

        /// <summary>
        /// Generate HMAC SHA256 signature
        /// </summary>
        private string GenerateSignature(string data, string secret)
        {
            using var hmac = new HMACSHA256(Encoding.UTF8.GetBytes(secret));
            var hash = hmac.ComputeHash(Encoding.UTF8.GetBytes(data));
            return Convert.ToBase64String(hash);
        }
    }

    // DTOs
    public class CreateQrisRequest
    {
        public double Amount { get; set; }
        public string Reference { get; set; } = string.Empty;
        public string? CallbackUrl { get; set; }
    }

    public class QrisResponse
    {
        public string QrId { get; set; } = string.Empty;
        public string QrString { get; set; } = string.Empty;
        public string QrImageUrl { get; set; } = string.Empty;
        public string Status { get; set; } = string.Empty;
        public double Amount { get; set; }
        public string Reference { get; set; } = string.Empty;
        public string ExpiredAt { get; set; } = string.Empty;
    }

    public class PaymentStatusResponse
    {
        public string QrId { get; set; } = string.Empty;
        public string Status { get; set; } = string.Empty;
        public double? Amount { get; set; }
        public string? PaidAt { get; set; }
        public string? TransactionId { get; set; }
    }
}
