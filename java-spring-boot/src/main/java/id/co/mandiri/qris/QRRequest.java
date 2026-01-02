package id.co.mandiri.qris;

public class QRRequest {
    private Integer amount;
    private String merchantId;
    private String terminalId;
    private String invoiceNumber;
    private String customerName;
    private String customerPhone;

    public static QRRequestBuilder builder() {
        return new QRRequestBuilder();
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

    public String getInvoiceNumber() {
        return invoiceNumber;
    }

    public void setInvoiceNumber(String invoiceNumber) {
        this.invoiceNumber = invoiceNumber;
    }

    public String getCustomerName() {
        return customerName;
    }

    public void setCustomerName(String customerName) {
        this.customerName = customerName;
    }

    public String getCustomerPhone() {
        return customerPhone;
    }

    public void setCustomerPhone(String customerPhone) {
        this.customerPhone = customerPhone;
    }

    public static class QRRequestBuilder {
        private Integer amount;
        private String merchantId;
        private String terminalId;
        private String invoiceNumber;
        private String customerName;
        private String customerPhone;

        public QRRequestBuilder amount(Integer amount) {
            this.amount = amount;
            return this;
        }

        public QRRequestBuilder merchantId(String merchantId) {
            this.merchantId = merchantId;
            return this;
        }

        public QRRequestBuilder terminalId(String terminalId) {
            this.terminalId = terminalId;
            return this;
        }

        public QRRequestBuilder invoiceNumber(String invoiceNumber) {
            this.invoiceNumber = invoiceNumber;
            return this;
        }

        public QRRequestBuilder customerName(String customerName) {
            this.customerName = customerName;
            return this;
        }

        public QRRequestBuilder customerPhone(String customerPhone) {
            this.customerPhone = customerPhone;
            return this;
        }

        public QRRequest build() {
            QRRequest request = new QRRequest();
            request.amount = this.amount;
            request.merchantId = this.merchantId;
            request.terminalId = this.terminalId;
            request.invoiceNumber = this.invoiceNumber;
            request.customerName = this.customerName;
            request.customerPhone = this.customerPhone;
            return request;
        }
    }
}
