using Microsoft.EntityFrameworkCore;
using MandiriQris.Models;

namespace MandiriQris.Data
{
    public class ApplicationDbContext : DbContext
    {
        public ApplicationDbContext(DbContextOptions<ApplicationDbContext> options)
            : base(options)
        {
        }

        public DbSet<MandiriQrisPayment> Payments { get; set; }

        protected override void OnModelCreating(ModelBuilder modelBuilder)
        {
            base.OnModelCreating(modelBuilder);

            modelBuilder.Entity<MandiriQrisPayment>(entity =>
            {
                entity.HasIndex(e => e.QrId).IsUnique();
                entity.HasIndex(e => e.Reference).IsUnique();
                entity.HasIndex(e => e.Status);
                entity.HasIndex(e => e.CreatedAt);
            });
        }
    }
}
