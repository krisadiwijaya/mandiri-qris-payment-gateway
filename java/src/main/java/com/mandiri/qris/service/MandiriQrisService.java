package com.mandiri.qris.service;

import com.fasterxml.jackson.databind.JsonNode;
import com.fasterxml.jackson.databind.ObjectMapper;
import com.mandiri.qris.config.MandiriQrisConfig;
import com.mandiri.qris.dto.CreateQrisRequest;
import com.mandiri.qris.dto.QrisResponse;
import com.mandiri.qris.dto.PaymentStatusResponse;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.cache.annotation.Cacheable;
import org.springframework.http.*;
import org.springframework.stereotype.Service;
import org.springframework.web.client.RestTemplate;

import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.text.SimpleDateFormat;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.*;

@Slf4j
@Service
@RequiredArgsConstructor
public class MandiriQrisService {
    
    private final MandiriQrisConfig config;
    private final RestTemplate restTemplate;
    private final ObjectMapper objectMapper;
    
    private static final String DATE_FORMAT = "yyyy-MM-dd'T'HH:mm:ss.SSS'Z'";
    
    /**
     * Get access token (with caching)
     */
    @Cacheable(value = "mandiriTokenCache", key = "'token'")
    public String getAccessToken() {
        try {
            String timestamp = getCurrentTimestamp();
            String signature = generateSignature(config.getClientId() + "|" + timestamp, config.getClientSecret());
            
            HttpHeaders headers = new HttpHeaders();
            headers.setContentType(MediaType.APPLICATION_JSON);
            headers.set("X-TIMESTAMP", timestamp);
            headers.set("X-CLIENT-KEY", config.getClientId());
            headers.set("X-SIGNATURE", signature);
            
            Map<String, String> body = new HashMap<>();
            body.put("grantType", "client_credentials");
            
            HttpEntity<Map<String, String>> request = new HttpEntity<>(body, headers);
            
            ResponseEntity<String> response = restTemplate.postForEntity(
                config.getBaseUrl() + "/openapi/auth/v2.0/access-token/b2b",
                request,
                String.class
            );
            
            if (response.getStatusCode() == HttpStatus.OK) {
                JsonNode jsonNode = objectMapper.readTree(response.getBody());
                return jsonNode.get("accessToken").asText();
            } else {
                throw new RuntimeException("Failed to get access token: HTTP " + response.getStatusCode());
            }
            
        } catch (Exception e) {
            log.error("Failed to get access token", e);
            throw new RuntimeException("Failed to get access token: " + e.getMessage(), e);
        }
    }
    
    /**
     * Create QRIS payment
     */
    public QrisResponse createQris(CreateQrisRequest request) {
        try {
            String token = getAccessToken();
            String timestamp = getCurrentTimestamp();
            String expiryTime = getExpiryTimestamp(config.getQrisExpiryMinutes());
            
            HttpHeaders headers = new HttpHeaders();
            headers.setContentType(MediaType.APPLICATION_JSON);
            headers.setBearerAuth(token);
            headers.set("X-TIMESTAMP", timestamp);
            headers.set("X-PARTNER-ID", config.getMerchantNmid());
            headers.set("X-EXTERNAL-ID", request.getReference());
            
            Map<String, Object> body = new HashMap<>();
            body.put("partnerReferenceNo", request.getReference());
            
            Map<String, String> amount = new HashMap<>();
            amount.put("value", String.format("%.2f", request.getAmount()));
            amount.put("currency", "IDR");
            body.put("amount", amount);
            
            body.put("merchantId", config.getMerchantNmid());
            body.put("storeLabel", config.getMerchantName());
            body.put("terminalLabel", config.getMerchantCity());
            body.put("validityPeriod", expiryTime);
            
            if (request.getCallbackUrl() != null && !request.getCallbackUrl().isEmpty()) {
                Map<String, String> additionalInfo = new HashMap<>();
                additionalInfo.put("callbackUrl", request.getCallbackUrl());
                body.put("additionalInfo", additionalInfo);
            }
            
            HttpEntity<Map<String, Object>> httpRequest = new HttpEntity<>(body, headers);
            
            ResponseEntity<String> response = restTemplate.postForEntity(
                config.getBaseUrl() + "/openapi/qris/v1.0/qr-code-dynamic",
                httpRequest,
                String.class
            );
            
            if (response.getStatusCode() == HttpStatus.OK) {
                JsonNode jsonNode = objectMapper.readTree(response.getBody());
                String qrContent = jsonNode.get("qrContent").asText();
                String qrId = jsonNode.has("qrId") ? jsonNode.get("qrId").asText() : request.getReference();
                
                QrisResponse qrisResponse = new QrisResponse();
                qrisResponse.setQrId(qrId);
                qrisResponse.setQrString(qrContent);
                qrisResponse.setQrImageUrl("https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" + 
                    URLEncoder.encode(qrContent, StandardCharsets.UTF_8));
                qrisResponse.setStatus("PENDING");
                qrisResponse.setAmount(request.getAmount());
                qrisResponse.setReference(request.getReference());
                qrisResponse.setExpiredAt(expiryTime);
                
                return qrisResponse;
            } else {
                throw new RuntimeException("Failed to create QRIS: HTTP " + response.getStatusCode());
            }
            
        } catch (Exception e) {
            log.error("Failed to create QRIS", e);
            throw new RuntimeException("Failed to create QRIS: " + e.getMessage(), e);
        }
    }
    
    /**
     * Check payment status
     */
    public PaymentStatusResponse checkStatus(String qrId, String reference) {
        try {
            String token = getAccessToken();
            String timestamp = getCurrentTimestamp();
            
            HttpHeaders headers = new HttpHeaders();
            headers.setContentType(MediaType.APPLICATION_JSON);
            headers.setBearerAuth(token);
            headers.set("X-TIMESTAMP", timestamp);
            headers.set("X-PARTNER-ID", config.getMerchantNmid());
            headers.set("X-EXTERNAL-ID", qrId);
            
            Map<String, String> body = new HashMap<>();
            body.put("originalPartnerReferenceNo", reference);
            body.put("originalReferenceNo", qrId);
            body.put("serviceCode", "47");
            
            HttpEntity<Map<String, String>> request = new HttpEntity<>(body, headers);
            
            ResponseEntity<String> response = restTemplate.postForEntity(
                config.getBaseUrl() + "/openapi/qris/v1.0/qr-code-dynamic/status",
                request,
                String.class
            );
            
            if (response.getStatusCode() == HttpStatus.OK) {
                JsonNode jsonNode = objectMapper.readTree(response.getBody());
                
                PaymentStatusResponse statusResponse = new PaymentStatusResponse();
                statusResponse.setQrId(qrId);
                
                if (jsonNode.has("transactionStatusCode")) {
                    String statusCode = jsonNode.get("transactionStatusCode").asText();
                    switch (statusCode) {
                        case "00":
                            statusResponse.setStatus("COMPLETED");
                            statusResponse.setPaidAt(jsonNode.has("transactionDate") ? 
                                jsonNode.get("transactionDate").asText() : getCurrentTimestamp());
                            statusResponse.setTransactionId(jsonNode.has("referenceNo") ? 
                                jsonNode.get("referenceNo").asText() : null);
                            break;
                        case "03":
                            statusResponse.setStatus("PENDING");
                            break;
                        case "05":
                            statusResponse.setStatus("EXPIRED");
                            break;
                        default:
                            statusResponse.setStatus("FAILED");
                            break;
                    }
                } else {
                    statusResponse.setStatus("UNKNOWN");
                }
                
                if (jsonNode.has("amount") && jsonNode.get("amount").has("value")) {
                    statusResponse.setAmount(jsonNode.get("amount").get("value").asDouble());
                }
                
                return statusResponse;
            } else {
                throw new RuntimeException("Failed to check status: HTTP " + response.getStatusCode());
            }
            
        } catch (Exception e) {
            log.error("Failed to check payment status", e);
            throw new RuntimeException("Failed to check status: " + e.getMessage(), e);
        }
    }
    
    /**
     * Generate HMAC SHA256 signature
     */
    private String generateSignature(String data, String secret) {
        try {
            Mac sha256Hmac = Mac.getInstance("HmacSHA256");
            SecretKeySpec secretKey = new SecretKeySpec(secret.getBytes(StandardCharsets.UTF_8), "HmacSHA256");
            sha256Hmac.init(secretKey);
            byte[] hash = sha256Hmac.doFinal(data.getBytes(StandardCharsets.UTF_8));
            return Base64.getEncoder().encodeToString(hash);
        } catch (Exception e) {
            throw new RuntimeException("Failed to generate signature", e);
        }
    }
    
    /**
     * Get current timestamp in ISO 8601 format
     */
    private String getCurrentTimestamp() {
        return LocalDateTime.now().format(DateTimeFormatter.ofPattern(DATE_FORMAT));
    }
    
    /**
     * Get expiry timestamp
     */
    private String getExpiryTimestamp(int minutes) {
        return LocalDateTime.now().plusMinutes(minutes).format(DateTimeFormatter.ofPattern(DATE_FORMAT));
    }
}
