package com.mandiri.qris.entity;

import jakarta.persistence.*;
import lombok.Data;
import java.time.LocalDateTime;

@Data
@Entity
@Table(name = "mandiri_qris_payments")
public class MandiriQrisPayment {
    
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;
    
    @Column(unique = true, nullable = false)
    private String qrId;
    
    @Column(unique = true, nullable = false)
    private String reference;
    
    @Column(columnDefinition = "TEXT", nullable = false)
    private String qrString;
    
    @Column(length = 500, nullable = false)
    private String qrImageUrl;
    
    @Column(nullable = false)
    private Double amount;
    
    @Column(nullable = false)
    private String status = "PENDING";
    
    @Column
    private String transactionId;
    
    @Column
    private LocalDateTime paidAt;
    
    @Column(nullable = false)
    private LocalDateTime expiredAt;
    
    @Column(columnDefinition = "TEXT")
    private String metadata;
    
    @Column(nullable = false, updatable = false)
    private LocalDateTime createdAt = LocalDateTime.now();
    
    @Column
    private LocalDateTime updatedAt;
    
    @PrePersist
    protected void onCreate() {
        createdAt = LocalDateTime.now();
    }
    
    @PreUpdate
    protected void onUpdate() {
        updatedAt = LocalDateTime.now();
    }
}
