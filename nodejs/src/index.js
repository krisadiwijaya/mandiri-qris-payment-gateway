const axios = require('axios');
const crypto = require('crypto');

/**
 * Mandiri QRIS Payment Gateway Client
 * Handles OAuth 2.0 authentication, QR generation, payment polling, and webhooks
 */
class MandiriQrisClient {
    /**
     * Initialize the Mandiri QRIS client
     * @param {Object} config - Configuration object
     * @param {string} config.clientId - OAuth client ID
     * @param {string} config.clientSecret - OAuth client secret
     * @param {string} config.baseUrl - API base URL
     * @param {boolean} config.sandbox - Enable sandbox mode
     */
    constructor(config) {
        this.clientId = config.clientId;
        this.clientSecret = config.clientSecret;
        this.baseUrl = config.sandbox ? 'https://sandbox-api.mandiri.co.id' : (config.baseUrl || 'https://api.mandiri.co.id');
        this.sandbox = config.sandbox || false;
        this.accessToken = null;
        this.tokenExpiry = null;
    }

    /**
     * Get OAuth 2.0 access token
     * @private
     * @returns {Promise<string>} Access token
     */
    async getAccessToken() {
        // Return cached token if still valid
        if (this.accessToken && this.tokenExpiry && Date.now() < this.tokenExpiry) {
            return this.accessToken;
        }

        const url = `${this.baseUrl}/oauth/token`;
        const data = {
            grant_type: 'client_credentials',
            client_id: this.clientId,
            client_secret: this.clientSecret
        };

        try {
            const response = await axios.post(url, data, {
                headers: { 'Content-Type': 'application/json' },
                httpsAgent: this.sandbox ? new (require('https').Agent)({ rejectUnauthorized: false }) : undefined
            });

            this.accessToken = response.data.access_token;
            const expiresIn = response.data.expires_in || 3600;
            this.tokenExpiry = Date.now() + (expiresIn - 60) * 1000;

            return this.accessToken;
        } catch (error) {
            throw new Error(`Failed to obtain access token: ${error.message}`);
        }
    }

    /**
     * Generate dynamic QRIS QR code
     * @param {Object} params - QR generation parameters
     * @param {number} params.amount - Payment amount
     * @param {string} params.merchantId - Merchant ID
     * @param {string} params.terminalId - Terminal ID
     * @param {string} [params.invoiceNumber] - Custom invoice number
     * @param {string} [params.customerName] - Customer name
     * @param {string} [params.customerPhone] - Customer phone
     * @returns {Promise<Object>} QR code data
     */
    async generateQR(params) {
        const token = await this.getAccessToken();
        const url = `${this.baseUrl}/api/v1/qris/generate`;

        const data = {
            amount: params.amount,
            merchant_id: params.merchantId,
            terminal_id: params.terminalId,
            invoice_number: params.invoiceNumber || this.generateInvoiceNumber(),
            customer_name: params.customerName || '',
            customer_phone: params.customerPhone || '',
            timestamp: new Date().toISOString()
        };

        try {
            const response = await axios.post(url, data, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                httpsAgent: this.sandbox ? new (require('https').Agent)({ rejectUnauthorized: false }) : undefined
            });

            return response.data;
        } catch (error) {
            throw new Error(`Failed to generate QR: ${error.message}`);
        }
    }

    /**
     * Check payment status
     * @param {string} transactionId - Transaction ID from QR generation
     * @returns {Promise<Object>} Payment status data
     */
    async checkPaymentStatus(transactionId) {
        const token = await this.getAccessToken();
        const url = `${this.baseUrl}/api/v1/qris/status/${transactionId}`;

        try {
            const response = await axios.get(url, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                httpsAgent: this.sandbox ? new (require('https').Agent)({ rejectUnauthorized: false }) : undefined
            });

            return response.data;
        } catch (error) {
            throw new Error(`Failed to check payment status: ${error.message}`);
        }
    }

    /**
     * Verify webhook signature
     * @param {Object} payload - Webhook payload
     * @param {string} signature - Signature from webhook header
     * @returns {boolean} True if signature is valid
     */
    verifyWebhookSignature(payload, signature) {
        const data = JSON.stringify(payload);
        const calculatedSignature = crypto
            .createHmac('sha256', this.clientSecret)
            .update(data)
            .digest('hex');

        return crypto.timingSafeEqual(
            Buffer.from(calculatedSignature),
            Buffer.from(signature)
        );
    }

    /**
     * Handle webhook request
     * @param {string} rawPayload - Raw POST body
     * @param {string} signature - Signature from X-Signature header
     * @returns {Object} Parsed webhook data
     */
    handleWebhook(rawPayload, signature) {
        let payload;
        
        try {
            payload = JSON.parse(rawPayload);
        } catch (error) {
            throw new Error('Invalid webhook payload');
        }

        if (!this.verifyWebhookSignature(payload, signature)) {
            throw new Error('Invalid webhook signature');
        }

        return payload;
    }

    /**
     * Poll payment status until completed or timeout
     * @param {string} transactionId - Transaction ID
     * @param {number} maxAttempts - Maximum polling attempts
     * @param {number} intervalSeconds - Seconds between polls
     * @returns {Promise<Object>} Final payment status
     */
    async pollPaymentStatus(transactionId, maxAttempts = 60, intervalSeconds = 5) {
        let attempts = 0;

        while (attempts < maxAttempts) {
            const status = await this.checkPaymentStatus(transactionId);

            if (['SUCCESS', 'FAILED', 'EXPIRED'].includes(status.status)) {
                return status;
            }

            await new Promise(resolve => setTimeout(resolve, intervalSeconds * 1000));
            attempts++;
        }

        throw new Error('Payment status polling timeout');
    }

    /**
     * Generate unique invoice number
     * @private
     * @returns {string} Invoice number
     */
    generateInvoiceNumber() {
        const timestamp = new Date().toISOString().replace(/[-:T.]/g, '').slice(0, 14);
        const random = Math.floor(Math.random() * 9000) + 1000;
        return `INV-${timestamp}-${random}`;
    }
}

module.exports = { MandiriQrisClient };
