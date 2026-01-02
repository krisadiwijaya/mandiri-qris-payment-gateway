package id.co.mandiri.qris;

import com.fasterxml.jackson.annotation.JsonProperty;

public class PaymentStatus {
    @JsonProperty("transaction_id")
    private String transactionId;
    
    private String status;
    private Integer amount;
    
    @JsonProperty("merchant_id")
    private String merchantId;
    
    @JsonProperty("paid_at")
    private String paidAt;

    // Getters and Setters
    public String getTransactionId() {
        return transactionId;
    }

    public void setTransactionId(String transactionId) {
        this.transactionId = transactionId;
    }

    public String getStatus() {
        return status;
    }

    public void setStatus(String status) {
        this.status = status;
    }

    public Integer getAmount() {
        return amount;
    }

    public void setAmount(Integer amount) {
        this.amount = amount;
    }

    public String getMerchantId() {
        return merchantId;
    }

    public void setMerchantId(String merchantId) {
        this.merchantId = merchantId;
    }

    public String getPaidAt() {
        return paidAt;
    }

    public void setPaidAt(String paidAt) {
        this.paidAt = paidAt;
    }
}
