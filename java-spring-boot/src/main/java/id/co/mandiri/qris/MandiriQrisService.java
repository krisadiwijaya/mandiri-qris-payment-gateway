package id.co.mandiri.qris;

import com.fasterxml.jackson.databind.ObjectMapper;
import org.springframework.http.*;
import org.springframework.stereotype.Service;
import org.springframework.web.client.RestTemplate;

import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;
import java.nio.charset.StandardCharsets;
import java.security.InvalidKeyException;
import java.security.NoSuchAlgorithmException;
import java.time.Instant;
import java.time.LocalDateTime;
import java.time.ZoneId;
import java.util.Base64;
import java.util.HashMap;
import java.util.Map;
import java.util.Random;

@Service
public class MandiriQrisService {

    private final MandiriQrisProperties properties;
    private final RestTemplate restTemplate;
    private final ObjectMapper objectMapper;
    
    private String accessToken;
    private Long tokenExpiry;

    public MandiriQrisService(MandiriQrisProperties properties) {
        this.properties = properties;
        this.restTemplate = new RestTemplate();
        this.objectMapper = new ObjectMapper();
    }

    private String getAccessToken() throws Exception {
        // Return cached token if still valid
        if (accessToken != null && tokenExpiry != null && System.currentTimeMillis() < tokenExpiry) {
            return accessToken;
        }

        String url = properties.getBaseUrl() + "/oauth/token";
        
        Map<String, String> data = new HashMap<>();
        data.put("grant_type", "client_credentials");
        data.put("client_id", properties.getClientId());
        data.put("client_secret", properties.getClientSecret());

        HttpHeaders headers = new HttpHeaders();
        headers.setContentType(MediaType.APPLICATION_JSON);

        HttpEntity<Map<String, String>> request = new HttpEntity<>(data, headers);
        
        ResponseEntity<Map> response = restTemplate.postForEntity(url, request, Map.class);
        
        if (response.getStatusCode() == HttpStatus.OK && response.getBody() != null) {
            Map<String, Object> body = response.getBody();
            this.accessToken = (String) body.get("access_token");
            Integer expiresIn = (Integer) body.getOrDefault("expires_in", 3600);
            this.tokenExpiry = System.currentTimeMillis() + (expiresIn - 60) * 1000L;
            return this.accessToken;
        }

        throw new RuntimeException("Failed to obtain access token");
    }

    public QRResponse generateQR(QRRequest request) throws Exception {
        String token = getAccessToken();
        String url = properties.getBaseUrl() + "/api/v1/qris/generate";

        Map<String, Object> data = new HashMap<>();
        data.put("amount", request.getAmount());
        data.put("merchant_id", request.getMerchantId() != null ? request.getMerchantId() : properties.getMerchantId());
        data.put("terminal_id", request.getTerminalId() != null ? request.getTerminalId() : properties.getTerminalId());
        data.put("invoice_number", request.getInvoiceNumber() != null ? request.getInvoiceNumber() : generateInvoiceNumber());
        data.put("customer_name", request.getCustomerName() != null ? request.getCustomerName() : "");
        data.put("customer_phone", request.getCustomerPhone() != null ? request.getCustomerPhone() : "");
        data.put("timestamp", Instant.now().toString());

        HttpHeaders headers = new HttpHeaders();
        headers.setContentType(MediaType.APPLICATION_JSON);
        headers.setBearerAuth(token);

        HttpEntity<Map<String, Object>> httpRequest = new HttpEntity<>(data, headers);
        
        ResponseEntity<QRResponse> response = restTemplate.postForEntity(url, httpRequest, QRResponse.class);
        
        if (response.getStatusCode() == HttpStatus.OK) {
            return response.getBody();
        }

        throw new RuntimeException("Failed to generate QR");
    }

    public PaymentStatus checkPaymentStatus(String transactionId) throws Exception {
        String token = getAccessToken();
        String url = properties.getBaseUrl() + "/api/v1/qris/status/" + transactionId;

        HttpHeaders headers = new HttpHeaders();
        headers.setContentType(MediaType.APPLICATION_JSON);
        headers.setBearerAuth(token);

        HttpEntity<Void> request = new HttpEntity<>(headers);
        
        ResponseEntity<PaymentStatus> response = restTemplate.exchange(url, HttpMethod.GET, request, PaymentStatus.class);
        
        if (response.getStatusCode() == HttpStatus.OK) {
            return response.getBody();
        }

        throw new RuntimeException("Failed to check payment status");
    }

    public boolean verifyWebhookSignature(String payload, String signature) {
        try {
            Mac mac = Mac.getInstance("HmacSHA256");
            SecretKeySpec secretKeySpec = new SecretKeySpec(
                properties.getClientSecret().getBytes(StandardCharsets.UTF_8),
                "HmacSHA256"
            );
            mac.init(secretKeySpec);
            
            byte[] hash = mac.doFinal(payload.getBytes(StandardCharsets.UTF_8));
            String calculatedSignature = bytesToHex(hash);
            
            return calculatedSignature.equals(signature);
        } catch (NoSuchAlgorithmException | InvalidKeyException e) {
            throw new RuntimeException("Error verifying webhook signature", e);
        }
    }

    public Map<String, Object> handleWebhook(String rawPayload, String signature) throws Exception {
        Map<String, Object> payload = objectMapper.readValue(rawPayload, Map.class);
        
        if (!verifyWebhookSignature(rawPayload, signature)) {
            throw new RuntimeException("Invalid webhook signature");
        }
        
        return payload;
    }

    public PaymentStatus pollPaymentStatus(String transactionId, int maxAttempts, int intervalSeconds) throws Exception {
        int attempts = 0;
        
        while (attempts < maxAttempts) {
            PaymentStatus status = checkPaymentStatus(transactionId);
            
            String statusValue = status.getStatus();
            if ("SUCCESS".equals(statusValue) || "FAILED".equals(statusValue) || "EXPIRED".equals(statusValue)) {
                return status;
            }
            
            Thread.sleep(intervalSeconds * 1000L);
            attempts++;
        }
        
        throw new RuntimeException("Payment status polling timeout");
    }

    private String generateInvoiceNumber() {
        LocalDateTime now = LocalDateTime.now();
        String timestamp = String.format("%04d%02d%02d%02d%02d%02d",
            now.getYear(), now.getMonthValue(), now.getDayOfMonth(),
            now.getHour(), now.getMinute(), now.getSecond());
        int random = 1000 + new Random().nextInt(9000);
        return "INV-" + timestamp + "-" + random;
    }

    private static String bytesToHex(byte[] bytes) {
        StringBuilder result = new StringBuilder();
        for (byte b : bytes) {
            result.append(String.format("%02x", b));
        }
        return result.toString();
    }
}
