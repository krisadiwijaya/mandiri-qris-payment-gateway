package com.mandiri.qris.dto;

import lombok.Data;

@Data
public class PaymentStatusResponse {
    private String qrId;
    private String status;
    private Double amount;
    private String paidAt;
    private String transactionId;
}
