# Java Spring Boot SDK for Mandiri QRIS Payment Gateway

Spring Boot starter for integrating Mandiri QRIS Payment Gateway.

## Installation

Add to your `pom.xml`:

```xml
<dependency>
    <groupId>id.co.mandiri</groupId>
    <artifactId>qris-spring-boot-starter</artifactId>
    <version>1.0.0</version>
</dependency>
```

Or for Gradle:

```gradle
implementation 'id.co.mandiri:qris-spring-boot-starter:1.0.0'
```

## Configuration

Add to `application.properties`:

```properties
mandiri.qris.client-id=your_client_id
mandiri.qris.client-secret=your_client_secret
mandiri.qris.base-url=https://api.mandiri.co.id
mandiri.qris.sandbox=true
mandiri.qris.merchant-id=MERCHANT123
mandiri.qris.terminal-id=TERM001
```

Or `application.yml`:

```yaml
mandiri:
  qris:
    client-id: your_client_id
    client-secret: your_client_secret
    base-url: https://api.mandiri.co.id
    sandbox: true
    merchant-id: MERCHANT123
    terminal-id: TERM001
```

## Usage

### Inject the Service

```java
@Autowired
private MandiriQrisService qrisService;
```

### Generate QR Code

```java
@GetMapping("/generate-qr")
public ResponseEntity<QRResponse> generateQR() {
    try {
        QRRequest request = QRRequest.builder()
            .amount(100000)
            .merchantId("MERCHANT123")
            .terminalId("TERM001")
            .customerName("John Doe")
            .customerPhone("081234567890")
            .build();
        
        QRResponse response = qrisService.generateQR(request);
        return ResponseEntity.ok(response);
    } catch (Exception e) {
        return ResponseEntity.status(HttpStatus.INTERNAL_SERVER_ERROR).build();
    }
}
```

### Check Payment Status

```java
@GetMapping("/status/{transactionId}")
public ResponseEntity<PaymentStatus> checkStatus(@PathVariable String transactionId) {
    try {
        PaymentStatus status = qrisService.checkPaymentStatus(transactionId);
        return ResponseEntity.ok(status);
    } catch (Exception e) {
        return ResponseEntity.status(HttpStatus.INTERNAL_SERVER_ERROR).build();
    }
}
```

### Webhook Handler

```java
@PostMapping("/webhook/mandiri-qris")
public ResponseEntity<Map<String, String>> webhook(
        @RequestBody String rawPayload,
        @RequestHeader("X-Signature") String signature) {
    try {
        Map<String, Object> payload = qrisService.handleWebhook(rawPayload, signature);
        
        if ("SUCCESS".equals(payload.get("status"))) {
            // Payment successful
            // Update database, send confirmation, etc.
        }
        
        return ResponseEntity.ok(Map.of("status", "ok"));
    } catch (Exception e) {
        return ResponseEntity.badRequest()
            .body(Map.of("error", e.getMessage()));
    }
}
```

### Payment Polling

```java
// Poll every 5 seconds for up to 5 minutes
PaymentStatus finalStatus = qrisService.pollPaymentStatus(transactionId, 60, 5);

if ("SUCCESS".equals(finalStatus.getStatus())) {
    System.out.println("Payment completed!");
}
```

## License

MIT
