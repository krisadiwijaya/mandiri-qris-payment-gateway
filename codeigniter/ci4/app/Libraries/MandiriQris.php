<?php

namespace App\Libraries;

use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\Cache\CacheInterface;

/**
 * Mandiri QRIS Library for CodeIgniter 4
 */
class MandiriQris
{
    protected $clientId;
    protected $clientSecret;
    protected $merchantNmid;
    protected $merchantName;
    protected $merchantCity;
    protected $baseUrl;
    protected $qrisExpiryMinutes;
    protected $timeout;
    protected $cache;
    
    public function __construct()
    {
        $config = config('MandiriQris');
        
        $this->clientId = $config->clientId;
        $this->clientSecret = $config->clientSecret;
        $this->merchantNmid = $config->merchantNmid;
        $this->merchantName = $config->merchantName;
        $this->merchantCity = $config->merchantCity;
        $this->qrisExpiryMinutes = $config->qrisExpiryMinutes ?? 5;
        $this->timeout = $config->timeout ?? 30;
        
        // Set base URL
        $this->baseUrl = $config->environment === 'production'
            ? 'https://api.bankmandiri.co.id'
            : 'https://sandbox.bankmandiri.co.id';
        
        $this->cache = \Config\Services::cache();
    }
    
    /**
     * Get access token
     *
     * @return string
     */
    public function getAccessToken(): string
    {
        $cacheKey = 'mandiri_qris_token_' . md5($this->clientId);
        
        // Try to get from cache
        $token = $this->cache->get($cacheKey);
        if ($token) {
            return $token;
        }
        
        // Request new token
        $timestamp = gmdate('Y-m-d\TH:i:s.000\Z');
        $signature = base64_encode(hash_hmac(
            'sha256',
            $this->clientId . '|' . $timestamp,
            $this->clientSecret,
            true
        ));
        
        $client = \Config\Services::curlrequest([
            'timeout' => $this->timeout,
            'verify' => true,
        ]);
        
        $response = $client->post($this->baseUrl . '/openapi/auth/v2.0/access-token/b2b', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-TIMESTAMP' => $timestamp,
                'X-CLIENT-KEY' => $this->clientId,
                'X-SIGNATURE' => $signature,
            ],
            'json' => [
                'grantType' => 'client_credentials',
            ],
        ]);
        
        if ($response->getStatusCode() !== 200) {
            log_message('error', 'Mandiri QRIS: Failed to get access token - ' . $response->getBody());
            throw new \Exception('Failed to get access token: HTTP ' . $response->getStatusCode());
        }
        
        $data = json_decode($response->getBody(), true);
        
        if (!isset($data['accessToken'])) {
            throw new \Exception('Failed to get access token: Invalid response');
        }
        
        $token = $data['accessToken'];
        $expiresIn = $data['expiresIn'] ?? 3600;
        
        // Cache token (with 60 seconds safety margin)
        $this->cache->save($cacheKey, $token, $expiresIn - 60);
        
        return $token;
    }
    
    /**
     * Create QRIS payment
     *
     * @param float $amount
     * @param string $reference
     * @param string|null $callbackUrl
     * @return array
     */
    public function createQris(float $amount, string $reference, ?string $callbackUrl = null): array
    {
        $token = $this->getAccessToken();
        $timestamp = gmdate('Y-m-d\TH:i:s.000\Z');
        $expiryTime = gmdate('Y-m-d\TH:i:s.000\Z', strtotime('+' . $this->qrisExpiryMinutes . ' minutes'));
        
        $payload = [
            'partnerReferenceNo' => $reference,
            'amount' => [
                'value' => number_format($amount, 2, '.', ''),
                'currency' => 'IDR',
            ],
            'merchantId' => $this->merchantNmid,
            'storeLabel' => $this->merchantName,
            'terminalLabel' => $this->merchantCity,
            'validityPeriod' => $expiryTime,
        ];
        
        if ($callbackUrl) {
            $payload['additionalInfo'] = [
                'callbackUrl' => $callbackUrl,
            ];
        }
        
        $client = \Config\Services::curlrequest([
            'timeout' => $this->timeout,
            'verify' => true,
        ]);
        
        $response = $client->post($this->baseUrl . '/openapi/qris/v1.0/qr-code-dynamic', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
                'X-TIMESTAMP' => $timestamp,
                'X-PARTNER-ID' => $this->merchantNmid,
                'X-EXTERNAL-ID' => $reference,
            ],
            'json' => $payload,
        ]);
        
        if ($response->getStatusCode() !== 200) {
            log_message('error', 'Mandiri QRIS: Failed to create QRIS - ' . $response->getBody());
            throw new \Exception('Failed to create QRIS: HTTP ' . $response->getStatusCode());
        }
        
        $data = json_decode($response->getBody(), true);
        
        if (!isset($data['qrContent'])) {
            throw new \Exception('Failed to create QRIS: Invalid response');
        }
        
        return [
            'qr_id' => $data['qrId'] ?? $reference,
            'qr_string' => $data['qrContent'],
            'qr_image_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($data['qrContent']),
            'status' => 'PENDING',
            'amount' => $amount,
            'reference' => $reference,
            'expired_at' => $expiryTime,
        ];
    }
    
    /**
     * Check payment status
     *
     * @param string $qrId
     * @param string $reference
     * @return array
     */
    public function checkStatus(string $qrId, string $reference): array
    {
        $token = $this->getAccessToken();
        $timestamp = gmdate('Y-m-d\TH:i:s.000\Z');
        
        $payload = [
            'originalPartnerReferenceNo' => $reference,
            'originalReferenceNo' => $qrId,
            'serviceCode' => '47',
        ];
        
        $client = \Config\Services::curlrequest([
            'timeout' => $this->timeout,
            'verify' => true,
        ]);
        
        $response = $client->post($this->baseUrl . '/openapi/qris/v1.0/qr-code-dynamic/status', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
                'X-TIMESTAMP' => $timestamp,
                'X-PARTNER-ID' => $this->merchantNmid,
                'X-EXTERNAL-ID' => $qrId,
            ],
            'json' => $payload,
        ]);
        
        if ($response->getStatusCode() !== 200) {
            log_message('error', 'Mandiri QRIS: Failed to check status - ' . $response->getBody());
            throw new \Exception('Failed to check status: HTTP ' . $response->getStatusCode());
        }
        
        $data = json_decode($response->getBody(), true);
        
        $status = 'UNKNOWN';
        $paidAt = null;
        $transactionId = null;
        
        if (isset($data['transactionStatusCode'])) {
            switch ($data['transactionStatusCode']) {
                case '00':
                    $status = 'COMPLETED';
                    $paidAt = $data['transactionDate'] ?? date('Y-m-d H:i:s');
                    $transactionId = $data['referenceNo'] ?? null;
                    break;
                case '03':
                    $status = 'PENDING';
                    break;
                case '05':
                    $status = 'EXPIRED';
                    break;
                default:
                    $status = 'FAILED';
                    break;
            }
        }
        
        return [
            'qr_id' => $qrId,
            'status' => $status,
            'amount' => isset($data['amount']['value']) ? (float)$data['amount']['value'] : null,
            'paid_at' => $paidAt,
            'transaction_id' => $transactionId,
        ];
    }
    
    /**
     * Clear cached token
     */
    public function clearToken(): void
    {
        $cacheKey = 'mandiri_qris_token_' . md5($this->clientId);
        $this->cache->delete($cacheKey);
    }
}
