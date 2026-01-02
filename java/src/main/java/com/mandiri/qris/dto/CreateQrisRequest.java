package com.mandiri.qris.dto;

import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import lombok.Data;

@Data
public class CreateQrisRequest {
    
    @NotNull(message = "Amount is required")
    @Min(value = 1, message = "Amount must be greater than 0")
    private Double amount;
    
    @NotBlank(message = "Reference is required")
    private String reference;
    
    private String callbackUrl;
}
