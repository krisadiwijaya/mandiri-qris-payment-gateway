<?php

namespace MandiriQris;

/**
 * Mandiri QRIS Payment Gateway Client
 * 
 * Handles OAuth 2.0 authentication, QR generation, payment polling, and webhooks
 */
class Client
{
    private $clientId;
    private $clientSecret;
    private $baseUrl;
    private $sandbox;
    private $accessToken;
    private $tokenExpiry;

    /**
     * Initialize the Mandiri QRIS client
     *
     * @param array $config Configuration array with keys:
     *                      - client_id: OAuth client ID
     *                      - client_secret: OAuth client secret
     *                      - base_url: API base URL
     *                      - sandbox: Boolean for sandbox mode
     */
    public function __construct(array $config)
    {
        $this->clientId = $config['client_id'] ?? '';
        $this->clientSecret = $config['client_secret'] ?? '';
        $this->baseUrl = $config['base_url'] ?? 'https://api.mandiri.co.id';
        $this->sandbox = $config['sandbox'] ?? false;

        if ($this->sandbox) {
            $this->baseUrl = 'https://sandbox-api.mandiri.co.id';
        }
    }

    /**
     * Get OAuth 2.0 access token
     *
     * @return string Access token
     * @throws \Exception
     */
    private function getAccessToken()
    {
        // Return cached token if still valid
        if ($this->accessToken && $this->tokenExpiry > time()) {
            return $this->accessToken;
        }

        $url = $this->baseUrl . '/oauth/token';
        $data = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret
        ];

        $response = $this->makeRequest('POST', $url, $data, false);

        if (isset($response['access_token'])) {
            $this->accessToken = $response['access_token'];
            $this->tokenExpiry = time() + ($response['expires_in'] ?? 3600) - 60;
            return $this->accessToken;
        }

        throw new \Exception('Failed to obtain access token: ' . json_encode($response));
    }

    /**
     * Generate dynamic QRIS QR code
     *
     * @param array $params QR generation parameters:
     *                      - amount: Payment amount (required)
     *                      - merchant_id: Merchant ID (required)
     *                      - terminal_id: Terminal ID (required)
     *                      - invoice_number: Custom invoice number (optional)
     *                      - customer_name: Customer name (optional)
     *                      - customer_phone: Customer phone (optional)
     * @return array QR code data with transaction_id, qr_string, qr_image
     * @throws \Exception
     */
    public function generateQR(array $params)
    {
        $token = $this->getAccessToken();
        $url = $this->baseUrl . '/api/v1/qris/generate';

        $data = [
            'amount' => $params['amount'] ?? 0,
            'merchant_id' => $params['merchant_id'] ?? '',
            'terminal_id' => $params['terminal_id'] ?? '',
            'invoice_number' => $params['invoice_number'] ?? $this->generateInvoiceNumber(),
            'customer_name' => $params['customer_name'] ?? '',
            'customer_phone' => $params['customer_phone'] ?? '',
            'timestamp' => date('Y-m-d\TH:i:s\Z')
        ];

        return $this->makeRequest('POST', $url, $data, true);
    }

    /**
     * Check payment status
     *
     * @param string $transactionId Transaction ID from QR generation
     * @return array Payment status data
     * @throws \Exception
     */
    public function checkPaymentStatus($transactionId)
    {
        $token = $this->getAccessToken();
        $url = $this->baseUrl . '/api/v1/qris/status/' . $transactionId;

        return $this->makeRequest('GET', $url, null, true);
    }

    /**
     * Verify webhook signature
     *
     * @param array $payload Webhook payload
     * @param string $signature Signature from webhook header
     * @return bool True if signature is valid
     */
    public function verifyWebhookSignature(array $payload, $signature)
    {
        $data = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $calculatedSignature = hash_hmac('sha256', $data, $this->clientSecret);
        
        return hash_equals($calculatedSignature, $signature);
    }

    /**
     * Handle webhook request
     *
     * @param string $rawPayload Raw POST body
     * @param string $signature Signature from X-Signature header
     * @return array Parsed webhook data
     * @throws \Exception
     */
    public function handleWebhook($rawPayload, $signature)
    {
        $payload = json_decode($rawPayload, true);
        
        if (!$payload) {
            throw new \Exception('Invalid webhook payload');
        }

        if (!$this->verifyWebhookSignature($payload, $signature)) {
            throw new \Exception('Invalid webhook signature');
        }

        return $payload;
    }

    /**
     * Generate unique invoice number
     *
     * @return string Invoice number
     */
    private function generateInvoiceNumber()
    {
        return 'INV-' . date('YmdHis') . '-' . mt_rand(1000, 9999);
    }

    /**
     * Make HTTP request
     *
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param array|null $data Request data
     * @param bool $authenticate Whether to include authentication
     * @return array Response data
     * @throws \Exception
     */
    private function makeRequest($method, $url, $data = null, $authenticate = false)
    {
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        if ($authenticate) {
            $headers[] = 'Authorization: Bearer ' . $this->accessToken;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$this->sandbox);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('cURL error: ' . $error);
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            throw new \Exception('API error: ' . ($decoded['message'] ?? $response));
        }

        return $decoded ?? [];
    }

    /**
     * Poll payment status until completed or timeout
     *
     * @param string $transactionId Transaction ID
     * @param int $maxAttempts Maximum polling attempts
     * @param int $intervalSeconds Seconds between polls
     * @return array Final payment status
     * @throws \Exception
     */
    public function pollPaymentStatus($transactionId, $maxAttempts = 60, $intervalSeconds = 5)
    {
        $attempts = 0;
        
        while ($attempts < $maxAttempts) {
            $status = $this->checkPaymentStatus($transactionId);
            
            if (in_array($status['status'] ?? '', ['SUCCESS', 'FAILED', 'EXPIRED'])) {
                return $status;
            }
            
            sleep($intervalSeconds);
            $attempts++;
        }
        
        throw new \Exception('Payment status polling timeout');
    }
}
