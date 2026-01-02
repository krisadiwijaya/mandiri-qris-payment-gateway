package com.mandiri.qris.dto;

import lombok.Data;

@Data
public class QrisResponse {
    private String qrId;
    private String qrString;
    private String qrImageUrl;
    private String status;
    private Double amount;
    private String reference;
    private String expiredAt;
}
