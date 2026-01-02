using Microsoft.Extensions.DependencyInjection;

namespace Mandiri.Qris;

public static class ServiceCollectionExtensions
{
    public static IServiceCollection AddMandiriQris(
        this IServiceCollection services,
        Action<MandiriQrisOptions> configure)
    {
        services.Configure(configure);
        services.AddHttpClient<IMandiriQrisService, MandiriQrisService>();
        
        return services;
    }
}
