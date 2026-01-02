# Java Spring Boot - Mandiri QRIS Payment SDK

Java implementation for Mandiri QRIS Payment Gateway using Spring Boot.

## 📋 Requirements

- Java 11 or higher
- Maven 3.6+ or Gradle 7+
- Spring Boot 2.5+

## 🚀 Installation

### Maven

Add to `pom.xml`:

```xml
<dependency>
    <groupId>com.mandiri</groupId>
    <artifactId>qris-payment-sdk</artifactId>
    <version>1.0.0</version>
</dependency>
```

### Gradle

Add to `build.gradle`:

```gradle
implementation 'com.mandiri:qris-payment-sdk:1.0.0'
```

## ⚙️ Configuration

Add to `application.properties`:

```properties
# Mandiri QRIS Configuration
mandiri.qris.environment=sandbox
mandiri.qris.client-id=your_client_id
mandiri.qris.client-secret=your_client_secret
mandiri.qris.merchant-nmid=YOUR_NMID
mandiri.qris.merchant-name=YOUR MERCHANT NAME
mandiri.qris.merchant-city=JAKARTA
mandiri.qris.expiry-minutes=30

# Base URLs
mandiri.qris.sandbox-url=https://sandbox.bankmandiri.co.id
mandiri.qris.production-url=https://api.bankmandiri.co.id
```

Or use `application.yml`:

```yaml
mandiri:
  qris:
    environment: sandbox
    client-id: your_client_id
    client-secret: your_client_secret
    merchant-nmid: YOUR_NMID
    merchant-name: YOUR MERCHANT NAME
    merchant-city: JAKARTA
    expiry-minutes: 30
    sandbox-url: https://sandbox.bankmandiri.co.id
    production-url: https://api.bankmandiri.co.id
```

## 📝 Usage

### Service Class

```java
@Service
public class PaymentService {
    
    @Autowired
    private MandiriQrisClient mandiriQrisClient;
    
    @Autowired
    private PaymentRepository paymentRepository;
    
    public QrisResponse createPayment(BigDecimal amount, String orderId) {
        QrisRequest request = QrisRequest.builder()
            .amount(amount)
            .reference(orderId)
            .build();
        
        QrisResponse qris = mandiriQrisClient.createQris(request);
        
        // Save to database
        Payment payment = new Payment();
        payment.setOrderId(orderId);
        payment.setQrId(qris.getQrId());
        payment.setQrString(qris.getQrString());
        payment.setQrImageUrl(qris.getQrImageUrl());
        payment.setAmount(amount);
        payment.setStatus(PaymentStatus.PENDING);
        payment.setExpiredAt(qris.getExpiredAt());
        
        paymentRepository.save(payment);
        
        return qris;
    }
    
    public PaymentStatusResponse checkPaymentStatus(String qrId) {
        PaymentStatusResponse status = mandiriQrisClient.checkStatus(qrId);
        
        if ("COMPLETED".equals(status.getStatus())) {
            Payment payment = paymentRepository.findByQrId(qrId)
                .orElseThrow(() -> new RuntimeException("Payment not found"));
            
            payment.setStatus(PaymentStatus.PAID);
            payment.setPaidAt(LocalDateTime.now());
            paymentRepository.save(payment);
        }
        
        return status;
    }
}
```

### Controller

```java
@RestController
@RequestMapping("/api/qris")
public class QrisController {
    
    @Autowired
    private PaymentService paymentService;
    
    @PostMapping("/create")
    public ResponseEntity<ApiResponse<QrisResponse>> createQris(
            @Valid @RequestBody CreateQrisRequest request) {
        try {
            QrisResponse qris = paymentService.createPayment(
                request.getAmount(),
                request.getOrderId()
            );
            
            return ResponseEntity.ok(
                ApiResponse.<QrisResponse>builder()
                    .success(true)
                    .data(qris)
                    .build()
            );
        } catch (Exception e) {
            return ResponseEntity.status(HttpStatus.INTERNAL_SERVER_ERROR)
                .body(ApiResponse.<QrisResponse>builder()
                    .success(false)
                    .error(e.getMessage())
                    .build()
                );
        }
    }
    
    @GetMapping("/status/{qrId}")
    public ResponseEntity<ApiResponse<PaymentStatusResponse>> checkStatus(
            @PathVariable String qrId) {
        try {
            PaymentStatusResponse status = paymentService.checkPaymentStatus(qrId);
            
            return ResponseEntity.ok(
                ApiResponse.<PaymentStatusResponse>builder()
                    .success(true)
                    .data(status)
                    .build()
            );
        } catch (Exception e) {
            return ResponseEntity.status(HttpStatus.INTERNAL_SERVER_ERROR)
                .body(ApiResponse.<PaymentStatusResponse>builder()
                    .success(false)
                    .error(e.getMessage())
                    .build()
                );
        }
    }
    
    @PostMapping("/webhook")
    public ResponseEntity<Map<String, String>> webhook(
            @RequestBody WebhookPayload payload) {
        
        log.info("Webhook received: {}", payload);
        
        if ("COMPLETED".equals(payload.getStatus())) {
            paymentService.processWebhook(payload);
        }
        
        return ResponseEntity.ok(Map.of("status", "ok"));
    }
}
```

### Entity

```java
@Entity
@Table(name = "payments")
@Data
@Builder
@NoArgsConstructor
@AllArgsConstructor
public class Payment {
    
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;
    
    @Column(unique = true, nullable = false)
    private String paymentId;
    
    @Column(nullable = false)
    private String orderId;
    
    @ManyToOne
    @JoinColumn(name = "user_id")
    private User user;
    
    @Column(nullable = false, precision = 15, scale = 2)
    private BigDecimal amount;
    
    @Column(name = "payment_method")
    private String paymentMethod = "qris";
    
    @Enumerated(EnumType.STRING)
    @Column(nullable = false)
    private PaymentStatus status = PaymentStatus.PENDING;
    
    @Column(name = "qr_id")
    private String qrId;
    
    @Column(name = "qr_string", columnDefinition = "TEXT")
    private String qrString;
    
    @Column(name = "qr_image_url", length = 500)
    private String qrImageUrl;
    
    @Column(name = "transaction_id")
    private String transactionId;
    
    @Column(name = "expired_at")
    private LocalDateTime expiredAt;
    
    @Column(name = "paid_at")
    private LocalDateTime paidAt;
    
    @CreatedDate
    @Column(name = "created_at", nullable = false, updatable = false)
    private LocalDateTime createdAt;
    
    @LastModifiedDate
    @Column(name = "updated_at")
    private LocalDateTime updatedAt;
}

public enum PaymentStatus {
    PENDING, PAID, EXPIRED, FAILED
}
```

## 🧪 Testing

```java
@SpringBootTest
@AutoConfigureMockMvc
class QrisControllerTest {
    
    @Autowired
    private MockMvc mockMvc;
    
    @MockBean
    private MandiriQrisClient mandiriQrisClient;
    
    @Test
    void testCreateQris() throws Exception {
        QrisResponse mockResponse = QrisResponse.builder()
            .qrId("QR123456789")
            .qrString("00020101...")
            .status("ACTIVE")
            .build();
        
        when(mandiriQrisClient.createQris(any()))
            .thenReturn(mockResponse);
        
        mockMvc.perform(post("/api/qris/create")
            .contentType(MediaType.APPLICATION_JSON)
            .content("{\"amount\": 100000, \"orderId\": \"ORDER-001\"}"))
            .andExpect(status().isOk())
            .andExpect(jsonPath("$.success").value(true))
            .andExpect(jsonPath("$.data.qrId").value("QR123456789"));
    }
}
```

## 🚀 Build & Run

```bash
# Build
mvn clean package

# Run
java -jar target/qris-payment-0.0.1-SNAPSHOT.jar

# Or with Maven
mvn spring-boot:run
```

## 📄 License

MIT License
