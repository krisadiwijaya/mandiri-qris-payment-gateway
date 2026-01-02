namespace MandiriQris.Services
{
    public class MandiriQrisConfig
    {
        public string Environment { get; set; } = "sandbox";
        public string ClientId { get; set; } = string.Empty;
        public string ClientSecret { get; set; } = string.Empty;
        public string MerchantNmid { get; set; } = string.Empty;
        public string MerchantName { get; set; } = "Toko Online";
        public string MerchantCity { get; set; } = "Jakarta";
        public int QrisExpiryMinutes { get; set; } = 5;
        public int Timeout { get; set; } = 30;

        public string BaseUrl => Environment == "production"
            ? "https://api.bankmandiri.co.id"
            : "https://sandbox.bankmandiri.co.id";
    }
}
