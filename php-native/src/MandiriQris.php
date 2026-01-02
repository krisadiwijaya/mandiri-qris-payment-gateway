<?php

/**
 * Mandiri QRIS Payment SDK
 * 
 * PHP Native implementation for Mandiri QRIS Payment Gateway
 * Supports both sandbox and production environments
 * 
 * @package MandiriQris
 * @version 1.0.0
 * @author Your Name
 * @license MIT
 */

class MandiriQris
{
    /**
     * API Configuration
     */
    private $config = [
        'sandbox' => [
            'base_url' => 'https://sandbox.bankmandiri.co.id',
            'auth_url' => '/openapi/auth/v2.0/access-token/b2b',
            'qris_create_url' => '/openapi/qris/v2.0/qr-code',
            'qris_status_url' => '/openapi/qris/v2.0/qr-code/status'
        ],
        'production' => [
            'base_url' => 'https://api.bankmandiri.co.id',
            'auth_url' => '/openapi/auth/v2.0/access-token/b2b',
            'qris_create_url' => '/openapi/qris/v2.0/qr-code',
            'qris_status_url' => '/openapi/qris/v2.0/qr-code/status'
        ]
    ];

    private $clientId;
    private $clientSecret;
    private $environment;
    private $merchantNmid;
    private $merchantName;
    private $merchantCity;
    private $accessToken = null;
    private $tokenExpiry = null;
    private $qrisExpiryMinutes = 30;
    private $logEnabled = true;

    /**
     * Constructor
     * 
     * @param array $options Configuration options
     * @throws Exception if required config is missing
     */
    public function __construct(array $options = [])
    {
        // Load from array or environment variables
        $this->clientId = $options['client_id'] ?? getenv('MANDIRI_CLIENT_ID');
        $this->clientSecret = $options['client_secret'] ?? getenv('MANDIRI_CLIENT_SECRET');
        $this->environment = $options['environment'] ?? getenv('MANDIRI_ENV') ?? 'sandbox';
        $this->merchantNmid = $options['merchant_nmid'] ?? getenv('MANDIRI_MERCHANT_NMID');
        $this->merchantName = $options['merchant_name'] ?? getenv('MANDIRI_MERCHANT_NAME');
        $this->merchantCity = $options['merchant_city'] ?? getenv('MANDIRI_MERCHANT_CITY');
        $this->qrisExpiryMinutes = $options['qris_expiry_minutes'] ?? 30;
        $this->logEnabled = $options['log_enabled'] ?? true;

        // Validate required config
        $this->validateConfig();

        // Try to restore token from session
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->accessToken = $_SESSION['mandiri_access_token'] ?? null;
            $this->tokenExpiry = $_SESSION['mandiri_token_expiry'] ?? null;
        }
    }

    /**
     * Validate configuration
     * 
     * @throws Exception if config is invalid
     */
    private function validateConfig()
    {
        $required = ['clientId', 'clientSecret', 'merchantNmid', 'merchantName', 'merchantCity'];
        
        foreach ($required as $field) {
            if (empty($this->$field)) {
                throw new Exception("Missing required configuration: {$field}");
            }
        }

        if (!in_array($this->environment, ['sandbox', 'production'])) {
            throw new Exception("Invalid environment. Must be 'sandbox' or 'production'");
        }
    }

    /**
     * Get base URL for current environment
     * 
     * @return string
     */
    private function getBaseUrl()
    {
        return $this->config[$this->environment]['base_url'];
    }

    /**
     * Get B2B Access Token
     * Automatically refreshes if token is expired
     * 
     * @return string Access token
     * @throws Exception if authentication fails
     */
    public function getAccessToken()
    {
        // Check if token is still valid (with 60 second safety margin)
        if ($this->accessToken && $this->tokenExpiry && time() < ($this->tokenExpiry - 60)) {
            $this->log('Using cached access token');
            return $this->accessToken;
        }

        $this->log('Requesting new access token...');

        $url = $this->getBaseUrl() . $this->config[$this->environment]['auth_url'];
        
        // Create Basic Auth header
        $authString = base64_encode($this->clientId . ':' . $this->clientSecret);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . $authString
            ],
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'client_credentials'
            ]),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("cURL Error: {$curlError}");
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200 || !isset($data['access_token'])) {
            $errorMsg = $data['error_description'] ?? $data['message'] ?? 'Unknown error';
            throw new Exception("Failed to get access token: {$errorMsg} (HTTP {$httpCode})");
        }

        // Store token and expiry
        $this->accessToken = $data['access_token'];
        $this->tokenExpiry = time() + ($data['expires_in'] ?? 1800);

        // Save to session if available
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['mandiri_access_token'] = $this->accessToken;
            $_SESSION['mandiri_token_expiry'] = $this->tokenExpiry;
        }

        $this->log('Access token obtained successfully');

        return $this->accessToken;
    }

    /**
     * Create QRIS Dynamic Code
     * 
     * @param array $data Payment data
     * @return array QR code data
     * @throws Exception if creation fails
     */
    public function createQris(array $data)
    {
        $this->log('Creating QRIS code...');

        // Validate input
        if (!isset($data['amount']) || $data['amount'] <= 0) {
            throw new Exception('Invalid amount. Must be greater than 0');
        }

        if (!isset($data['reference']) || empty($data['reference'])) {
            throw new Exception('Reference is required');
        }

        // Get access token
        $token = $this->getAccessToken();

        // Prepare request payload
        $payload = [
            'type' => 'DYNAMIC',
            'amount' => (float) $data['amount'],
            'currency' => 'IDR',
            'reference' => $data['reference'],
            'merchant_nmid' => $this->merchantNmid,
            'merchant_name' => $this->merchantName,
            'merchant_city' => $this->merchantCity
        ];

        // Add optional callback URL
        if (isset($data['callback_url'])) {
            $payload['callback_url'] = $data['callback_url'];
        }

        $url = $this->getBaseUrl() . $this->config[$this->environment]['qris_create_url'];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("cURL Error: {$curlError}");
        }

        $result = json_decode($response, true);

        if ($httpCode !== 200 && $httpCode !== 201) {
            $errorMsg = $result['error_description'] ?? $result['message'] ?? 'Unknown error';
            throw new Exception("Failed to create QRIS: {$errorMsg} (HTTP {$httpCode})");
        }

        if (!isset($result['qr_string']) || !isset($result['qr_id'])) {
            throw new Exception('Invalid response from Mandiri API');
        }

        // Generate QR image URL
        $qrImageUrl = $this->generateQrImageUrl($result['qr_string']);

        // Calculate expiry time
        $expiredAt = date('Y-m-d H:i:s', time() + ($this->qrisExpiryMinutes * 60));

        $this->log('QRIS code created successfully. QR ID: ' . $result['qr_id']);

        return [
            'qr_id' => $result['qr_id'],
            'qr_string' => $result['qr_string'],
            'qr_image_url' => $qrImageUrl,
            'status' => $result['status'] ?? 'ACTIVE',
            'amount' => $data['amount'],
            'reference' => $data['reference'],
            'expired_at' => $expiredAt
        ];
    }

    /**
     * Check QRIS Payment Status
     * 
     * @param string $qrId QR code ID
     * @return array Payment status
     * @throws Exception if check fails
     */
    public function checkStatus($qrId)
    {
        $this->log('Checking payment status for QR ID: ' . $qrId);

        if (empty($qrId)) {
            throw new Exception('QR ID is required');
        }

        // Get access token
        $token = $this->getAccessToken();

        $url = $this->getBaseUrl() . $this->config[$this->environment]['qris_status_url'] . '/' . $qrId;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("cURL Error: {$curlError}");
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200) {
            $errorMsg = $data['error_description'] ?? $data['message'] ?? 'Unknown error';
            throw new Exception("Failed to check status: {$errorMsg} (HTTP {$httpCode})");
        }

        $this->log('Payment status: ' . ($data['status'] ?? 'UNKNOWN'));

        return [
            'qr_id' => $qrId,
            'status' => $data['status'] ?? 'UNKNOWN',
            'amount' => $data['amount'] ?? null,
            'paid_at' => $data['paid_at'] ?? null,
            'transaction_id' => $data['transaction_id'] ?? null
        ];
    }

    /**
     * Generate QR Image URL using external service
     * 
     * @param string $qrString QR code string
     * @return string Image URL
     */
    private function generateQrImageUrl($qrString)
    {
        $size = '300x300';
        $encodedString = urlencode($qrString);
        return "https://api.qrserver.com/v1/create-qr-code/?size={$size}&data={$encodedString}";
    }

    /**
     * Log message
     * 
     * @param string $message
     */
    private function log($message)
    {
        if (!$this->logEnabled) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] MandiriQris: {$message}\n";
        
        // Write to error log
        error_log($logMessage);

        // Optional: Write to file
        // file_put_contents('mandiri-qris.log', $logMessage, FILE_APPEND);
    }

    /**
     * Set QR code expiry time in minutes
     * 
     * @param int $minutes Expiry time (5-120 minutes)
     * @throws Exception if invalid value
     */
    public function setQrisExpiryMinutes($minutes)
    {
        if ($minutes < 5 || $minutes > 120) {
            throw new Exception('QRIS expiry must be between 5 and 120 minutes');
        }
        
        $this->qrisExpiryMinutes = $minutes;
    }

    /**
     * Enable or disable logging
     * 
     * @param bool $enabled
     */
    public function setLogEnabled($enabled)
    {
        $this->logEnabled = (bool) $enabled;
    }

    /**
     * Get current environment
     * 
     * @return string
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Clear cached token (force refresh on next request)
     */
    public function clearToken()
    {
        $this->accessToken = null;
        $this->tokenExpiry = null;
        
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['mandiri_access_token']);
            unset($_SESSION['mandiri_token_expiry']);
        }
        
        $this->log('Access token cleared');
    }
}
