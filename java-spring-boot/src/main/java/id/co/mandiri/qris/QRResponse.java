package id.co.mandiri.qris;

import com.fasterxml.jackson.annotation.JsonProperty;

public class QRResponse {
    @JsonProperty("transaction_id")
    private String transactionId;
    
    @JsonProperty("qr_string")
    private String qrString;
    
    @JsonProperty("qr_image")
    private String qrImage;
    
    private Integer amount;
    
    @JsonProperty("merchant_id")
    private String merchantId;
    
    @JsonProperty("terminal_id")
    private String terminalId;

    // Getters and Setters
    public String getTransactionId() {
        return transactionId;
    }

    public void setTransactionId(String transactionId) {
        this.transactionId = transactionId;
    }

    public String getQrString() {
        return qrString;
    }

    public void setQrString(String qrString) {
        this.qrString = qrString;
    }

    public String getQrImage() {
        return qrImage;
    }

    public void setQrImage(String qrImage) {
        this.qrImage = qrImage;
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

    public String getTerminalId() {
        return terminalId;
    }

    public void setTerminalId(String terminalId) {
        this.terminalId = terminalId;
    }
}
