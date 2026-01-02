/**
 * Mandiri QRIS Payment SDK for Node.js
 * 
 * @author Your Name
 * @license MIT
 * @version 1.0.0
 */

const axios = require('axios');

/**
 * Custom error class for Mandiri QRIS
 */
class MandiriQrisError extends Error {
    constructor(message, statusCode = 500, response = null) {
        super(message);
        this.name = 'MandiriQrisError';
        this.statusCode = statusCode;
        this.response = response;
    }
}

/**
 * Mandiri QRIS Payment Client
 */
class MandiriQris {
    /**
     * @param {Object} options - Configuration options
     * @param {string} options.clientId - Mandiri API client ID
     * @param {string} options.clientSecret - Mandiri API client secret
     * @param {string} [options.environment='sandbox'] - Environment (sandbox or production)
     * @param {string} options.merchantNmid - Merchant NMID
     * @param {string} options.merchantName - Merchant name
     * @param {string} options.merchantCity - Merchant city
     * @param {number} [options.qrisExpiryMinutes=30] - QR code expiry time in minutes
     * @param {number} [options.timeout=30000] - Request timeout in milliseconds
     */
    constructor(options = {}) {
        this.clientId = options.clientId;
        this.clientSecret = options.clientSecret;
        this.environment = options.environment || 'sandbox';
        this.merchantNmid = options.merchantNmid;
        this.merchantName = options.merchantName;
        this.merchantCity = options.merchantCity;
        this.qrisExpiryMinutes = options.qrisExpiryMinutes || 30;
        this.timeout = options.timeout || 30000;

        // Validate required config
        this._validateConfig();

        // API URLs
        this.config = {
            sandbox: {
                baseUrl: 'https://sandbox.bankmandiri.co.id',
                authUrl: '/openapi/auth/v2.0/access-token/b2b',
                qrisCreateUrl: '/openapi/qris/v2.0/qr-code',
                qrisStatusUrl: '/openapi/qris/v2.0/qr-code/status'
            },
            production: {
                baseUrl: 'https://api.bankmandiri.co.id',
                authUrl: '/openapi/auth/v2.0/access-token/b2b',
                qrisCreateUrl: '/openapi/qris/v2.0/qr-code',
                qrisStatusUrl: '/openapi/qris/v2.0/qr-code/status'
            }
        };

        // Token cache
        this.accessToken = null;
        this.tokenExpiry = null;

        // Axios instance
        this.axios = axios.create({
            timeout: this.timeout,
            headers: {
                'Content-Type': 'application/json'
            }
        });
    }

    /**
     * Validate configuration
     * @private
     */
    _validateConfig() {
        const required = ['clientId', 'clientSecret', 'merchantNmid', 'merchantName', 'merchantCity'];
        
        for (const field of required) {
            if (!this[field]) {
                throw new MandiriQrisError(`Missing required configuration: ${field}`);
            }
        }

        if (!['sandbox', 'production'].includes(this.environment)) {
            throw new MandiriQrisError("Environment must be 'sandbox' or 'production'");
        }
    }

    /**
     * Get base URL for current environment
     * @private
     * @returns {string}
     */
    _getBaseUrl() {
        return this.config[this.environment].baseUrl;
    }

    /**
     * Get B2B access token with automatic refresh
     * @returns {Promise<string>} Access token
     */
    async getAccessToken() {
        // Check if token is still valid (with 60 second safety margin)
        if (this.accessToken && this.tokenExpiry && Date.now() < (this.tokenExpiry - 60000)) {
            return this.accessToken;
        }

        try {
            const url = this._getBaseUrl() + this.config[this.environment].authUrl;
            
            // Create Basic Auth header
            const authString = Buffer.from(`${this.clientId}:${this.clientSecret}`).toString('base64');

            const response = await this.axios.post(
                url,
                'grant_type=client_credentials',
                {
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Authorization': `Basic ${authString}`
                    }
                }
            );

            if (!response.data.access_token) {
                throw new MandiriQrisError('Failed to get access token: Invalid response');
            }

            // Store token and expiry
            this.accessToken = response.data.access_token;
            const expiresIn = response.data.expires_in || 1800;
            this.tokenExpiry = Date.now() + (expiresIn * 1000);

            return this.accessToken;

        } catch (error) {
            if (error instanceof MandiriQrisError) {
                throw error;
            }

            const message = error.response?.data?.error_description || 
                           error.response?.data?.message || 
                           error.message || 
                           'Failed to get access token';

            throw new MandiriQrisError(
                message,
                error.response?.status || 500,
                error.response?.data
            );
        }
    }

    /**
     * Create QRIS dynamic code
     * @param {Object} options - Payment options
     * @param {number} options.amount - Payment amount
     * @param {string} options.reference - Unique reference/order ID
     * @param {string} [options.callbackUrl] - Webhook callback URL
     * @returns {Promise<Object>} QRIS data
     */
    async createQris(options = {}) {
        const { amount, reference, callbackUrl } = options;

        // Validate input
        if (!amount || amount <= 0) {
            throw new MandiriQrisError('Amount must be greater than 0');
        }

        if (!reference) {
            throw new MandiriQrisError('Reference is required');
        }

        try {
            // Get access token
            const token = await this.getAccessToken();

            // Prepare payload
            const payload = {
                type: 'DYNAMIC',
                amount: parseFloat(amount),
                currency: 'IDR',
                reference: reference,
                merchant_nmid: this.merchantNmid,
                merchant_name: this.merchantName,
                merchant_city: this.merchantCity
            };

            if (callbackUrl) {
                payload.callback_url = callbackUrl;
            }

            const url = this._getBaseUrl() + this.config[this.environment].qrisCreateUrl;

            const response = await this.axios.post(url, payload, {
                headers: {
                    'Authorization': `Bearer ${token}`
                }
            });

            const data = response.data;

            if (!data.qr_string || !data.qr_id) {
                throw new MandiriQrisError('Invalid response from Mandiri API: Missing QR data');
            }

            // Generate QR image URL
            const qrImageUrl = this._generateQrImageUrl(data.qr_string);

            // Calculate expiry time
            const expiredAt = new Date(Date.now() + (this.qrisExpiryMinutes * 60 * 1000));

            return {
                qrId: data.qr_id,
                qrString: data.qr_string,
                qrImageUrl: qrImageUrl,
                status: data.status || 'ACTIVE',
                amount: amount,
                reference: reference,
                expiredAt: expiredAt.toISOString()
            };

        } catch (error) {
            if (error instanceof MandiriQrisError) {
                throw error;
            }

            const message = error.response?.data?.error_description || 
                           error.response?.data?.message || 
                           error.message || 
                           'Failed to create QRIS';

            throw new MandiriQrisError(
                message,
                error.response?.status || 500,
                error.response?.data
            );
        }
    }

    /**
     * Check QRIS payment status
     * @param {string} qrId - QR code ID
     * @returns {Promise<Object>} Payment status
     */
    async checkStatus(qrId) {
        if (!qrId) {
            throw new MandiriQrisError('QR ID is required');
        }

        try {
            // Get access token
            const token = await this.getAccessToken();

            const url = this._getBaseUrl() + this.config[this.environment].qrisStatusUrl + '/' + qrId;

            const response = await this.axios.get(url, {
                headers: {
                    'Authorization': `Bearer ${token}`
                }
            });

            const data = response.data;

            return {
                qrId: qrId,
                status: data.status || 'UNKNOWN',
                amount: data.amount || null,
                paidAt: data.paid_at || null,
                transactionId: data.transaction_id || null
            };

        } catch (error) {
            if (error instanceof MandiriQrisError) {
                throw error;
            }

            const message = error.response?.data?.error_description || 
                           error.response?.data?.message || 
                           error.message || 
                           'Failed to check status';

            throw new MandiriQrisError(
                message,
                error.response?.status || 500,
                error.response?.data
            );
        }
    }

    /**
     * Generate QR code image URL
     * @private
     * @param {string} qrString - QR code string
     * @returns {string} Image URL
     */
    _generateQrImageUrl(qrString) {
        const size = '300x300';
        const encodedString = encodeURIComponent(qrString);
        return `https://api.qrserver.com/v1/create-qr-code/?size=${size}&data=${encodedString}`;
    }

    /**
     * Clear cached access token
     */
    clearToken() {
        this.accessToken = null;
        this.tokenExpiry = null;
    }

    /**
     * Set QR code expiry time
     * @param {number} minutes - Expiry time in minutes (5-120)
     */
    setQrisExpiryMinutes(minutes) {
        if (minutes < 5 || minutes > 120) {
            throw new MandiriQrisError('QRIS expiry must be between 5 and 120 minutes');
        }
        this.qrisExpiryMinutes = minutes;
    }

    /**
     * Get current environment
     * @returns {string}
     */
    getEnvironment() {
        return this.environment;
    }
}

// Export
module.exports = {
    MandiriQris,
    MandiriQrisError
};

// ES6 export (for TypeScript/modern Node.js)
module.exports.default = MandiriQris;
