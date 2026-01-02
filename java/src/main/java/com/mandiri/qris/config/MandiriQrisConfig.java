package com.mandiri.qris.config;

import org.springframework.boot.context.properties.ConfigurationProperties;
import org.springframework.context.annotation.Configuration;
import lombok.Data;

@Data
@Configuration
@ConfigurationProperties(prefix = "mandiri.qris")
public class MandiriQrisConfig {
    
    private String environment = "sandbox";
    private String clientId;
    private String clientSecret;
    private String merchantNmid;
    private String merchantName = "Toko Online";
    private String merchantCity = "Jakarta";
    private Integer qrisExpiryMinutes = 5;
    private Integer timeout = 30;
    
    public String getBaseUrl() {
        return "production".equals(environment) 
            ? "https://api.bankmandiri.co.id" 
            : "https://sandbox.bankmandiri.co.id";
    }
}
