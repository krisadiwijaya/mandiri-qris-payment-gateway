using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace MandiriQris.Models
{
    [Table("mandiri_qris_payments")]
    public class MandiriQrisPayment
    {
        [Key]
        public long Id { get; set; }

        [Required]
        [MaxLength(255)]
        public string QrId { get; set; } = string.Empty;

        [Required]
        [MaxLength(255)]
        public string Reference { get; set; } = string.Empty;

        [Required]
        [Column(TypeName = "TEXT")]
        public string QrString { get; set; } = string.Empty;

        [Required]
        [MaxLength(500)]
        public string QrImageUrl { get; set; } = string.Empty;

        [Required]
        public double Amount { get; set; }

        [Required]
        [MaxLength(50)]
        public string Status { get; set; } = "PENDING";

        [MaxLength(255)]
        public string? TransactionId { get; set; }

        public DateTime? PaidAt { get; set; }

        [Required]
        public DateTime ExpiredAt { get; set; }

        [Column(TypeName = "TEXT")]
        public string? Metadata { get; set; }

        [Required]
        public DateTime CreatedAt { get; set; } = DateTime.UtcNow;

        public DateTime? UpdatedAt { get; set; }
    }
}
