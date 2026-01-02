using Microsoft.EntityFrameworkCore;
using MandiriQris.Data;
using MandiriQris.Services;

var builder = WebApplication.CreateBuilder(args);

// Add services to the container
builder.Services.AddControllers();
builder.Services.AddEndpointsApiExplorer();
builder.Services.AddSwaggerGen();

// Add Memory Cache
builder.Services.AddMemoryCache();

// Add HttpClient
builder.Services.AddHttpClient();

// Configure Mandiri QRIS
builder.Services.Configure<MandiriQrisConfig>(
    builder.Configuration.GetSection("MandiriQris"));

// Register Mandiri QRIS Service
builder.Services.AddScoped<MandiriQrisService>();

// Add Database Context
builder.Services.AddDbContext<ApplicationDbContext>(options =>
{
    var connectionString = builder.Configuration.GetConnectionString("DefaultConnection");
    options.UseMySql(connectionString, ServerVersion.AutoDetect(connectionString));
});

var app = builder.Build();

// Configure the HTTP request pipeline
if (app.Environment.IsDevelopment())
{
    app.UseSwagger();
    app.UseSwaggerUI();
}

app.UseHttpsRedirection();
app.UseAuthorization();
app.MapControllers();

app.Run();
