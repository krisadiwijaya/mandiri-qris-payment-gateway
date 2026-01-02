package com.mandiri.qris.controller;

import com.mandiri.qris.dto.CreateQrisRequest;
import com.mandiri.qris.dto.PaymentStatusResponse;
import com.mandiri.qris.dto.QrisResponse;
import com.mandiri.qris.entity.MandiriQrisPayment;
import com.mandiri.qris.repository.MandiriQrisPaymentRepository;
import com.mandiri.qris.service.MandiriQrisService;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import jakarta.validation.Valid;
import java.time.LocalDateTime;
import java.util.HashMap;
import java.util.Map;
import java.util.Optional;

@Slf4j
@RestController
@RequestMapping("/api/qris")
@RequiredArgsConstructor
public class QrisController {
    
    private final MandiriQrisService mandiriQrisService;
    private final MandiriQrisPaymentRepository paymentRepository;
    
    /**
     * Create QRIS payment
     */
    @PostMapping("/create")
    public ResponseEntity<Map<String, Object>> createQris(@Valid @RequestBody CreateQrisRequest request) {
        Map<String, Object> response = new HashMap<>();
        
        try {
            // Check if reference already exists
            if (paymentRepository.existsByReference(request.getReference())) {
                response.put("success", false);
                response.put("message", "Reference already exists");
                return ResponseEntity.badRequest().body(response);
            }
            
            // Create QRIS
            QrisResponse qrisResponse = mandiriQrisService.createQris(request);
            
            // Save to database
            MandiriQrisPayment payment = new MandiriQrisPayment();
            payment.setQrId(qrisResponse.getQrId());
            payment.setReference(qrisResponse.getReference());
            payment.setQrString(qrisResponse.getQrString());
            payment.setQrImageUrl(qrisResponse.getQrImageUrl());
            payment.setAmount(qrisResponse.getAmount());
            payment.setStatus(qrisResponse.getStatus());
            payment.setExpiredAt(LocalDateTime.now().plusMinutes(5));
            payment.setCreatedAt(LocalDateTime.now());
            
            paymentRepository.save(payment);
            
            response.put("success", true);
            response.put("data", qrisResponse);
            
            return ResponseEntity.ok(response);
            
        } catch (Exception e) {
            log.error("Failed to create QRIS", e);
            response.put("success", false);
            response.put("message", e.getMessage());
            return ResponseEntity.status(HttpStatus.INTERNAL_SERVER_ERROR).body(response);
        }
    }
    
    /**
     * Check payment status
     */
    @GetMapping("/status/{qrId}")
    public ResponseEntity<Map<String, Object>> checkStatus(@PathVariable String qrId) {
        Map<String, Object> response = new HashMap<>();
        
        try {
            // Get payment from database
            Optional<MandiriQrisPayment> paymentOpt = paymentRepository.findByQrId(qrId);
            
            if (paymentOpt.isEmpty()) {
                response.put("success", false);
                response.put("message", "Payment not found");
                return ResponseEntity.status(HttpStatus.NOT_FOUND).body(response);
            }
            
            MandiriQrisPayment payment = paymentOpt.get();
            
            // If already completed, return cached status
            if ("COMPLETED".equals(payment.getStatus())) {
                Map<String, Object> data = new HashMap<>();
                data.put("qrId", payment.getQrId());
                data.put("status", payment.getStatus());
                data.put("amount", payment.getAmount());
                data.put("paidAt", payment.getPaidAt());
                data.put("transactionId", payment.getTransactionId());
                
                response.put("success", true);
                response.put("data", data);
                return ResponseEntity.ok(response);
            }
            
            // Check status from API
            PaymentStatusResponse statusResponse = mandiriQrisService.checkStatus(qrId, payment.getReference());
            
            // Update database if status changed
            if (!statusResponse.getStatus().equals(payment.getStatus())) {
                payment.setStatus(statusResponse.getStatus());
                payment.setTransactionId(statusResponse.getTransactionId());
                if (statusResponse.getPaidAt() != null) {
                    payment.setPaidAt(LocalDateTime.now());
                }
                payment.setUpdatedAt(LocalDateTime.now());
                paymentRepository.save(payment);
            }
            
            response.put("success", true);
            response.put("data", statusResponse);
            
            return ResponseEntity.ok(response);
            
        } catch (Exception e) {
            log.error("Failed to check status", e);
            response.put("success", false);
            response.put("message", e.getMessage());
            return ResponseEntity.status(HttpStatus.INTERNAL_SERVER_ERROR).body(response);
        }
    }
    
    /**
     * Handle webhook notification
     */
    @PostMapping("/webhook")
    public ResponseEntity<Map<String, Object>> webhook(@RequestBody Map<String, Object> payload) {
        Map<String, Object> response = new HashMap<>();
        
        try {
            String qrId = (String) payload.getOrDefault("qrId", payload.get("originalReferenceNo"));
            String statusCode = (String) payload.get("transactionStatusCode");
            
            if (qrId == null) {
                response.put("success", false);
                response.put("message", "Missing qrId");
                return ResponseEntity.badRequest().body(response);
            }
            
            // Get payment from database
            Optional<MandiriQrisPayment> paymentOpt = paymentRepository.findByQrId(qrId);
            
            if (paymentOpt.isEmpty()) {
                response.put("success", false);
                response.put("message", "Payment not found");
                return ResponseEntity.status(HttpStatus.NOT_FOUND).body(response);
            }
            
            MandiriQrisPayment payment = paymentOpt.get();
            
            // Map status code
            String newStatus = switch (statusCode) {
                case "00" -> "COMPLETED";
                case "03" -> "PENDING";
                case "05" -> "EXPIRED";
                default -> "FAILED";
            };
            
            // Update payment status
            payment.setStatus(newStatus);
            if ("COMPLETED".equals(newStatus)) {
                payment.setTransactionId((String) payload.get("referenceNo"));
                payment.setPaidAt(LocalDateTime.now());
            }
            payment.setUpdatedAt(LocalDateTime.now());
            
            paymentRepository.save(payment);
            
            response.put("success", true);
            response.put("message", "Webhook processed");
            
            return ResponseEntity.ok(response);
            
        } catch (Exception e) {
            log.error("Failed to process webhook", e);
            response.put("success", false);
            response.put("message", e.getMessage());
            return ResponseEntity.status(HttpStatus.INTERNAL_SERVER_ERROR).body(response);
        }
    }
}
