namespace Mandiri.Qris;

public class MandiriQrisOptions
{
    public string ClientId { get; set; } = string.Empty;
    public string ClientSecret { get; set; } = string.Empty;
    public string BaseUrl { get; set; } = "https://api.mandiri.co.id";
    public bool Sandbox { get; set; } = false;
    public string MerchantId { get; set; } = string.Empty;
    public string TerminalId { get; set; } = string.Empty;

    public string GetBaseUrl()
    {
        return Sandbox ? "https://sandbox-api.mandiri.co.id" : BaseUrl;
    }
}
