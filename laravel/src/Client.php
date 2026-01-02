<?php

namespace MandiriQris\Laravel;

/**
 * Laravel wrapper for Mandiri QRIS Client
 */
class Client
{
    private $config;
    private $accessToken;
    private $tokenExpiry;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    private function getBaseUrl()
    {
        return $this->config['sandbox'] ? 'https://sandbox-api.mandiri.co.id' : $this->config['base_url'];
    }

    private function getAccessToken()
    {
        if ($this->accessToken && $this->tokenExpiry > time()) {
            return $this->accessToken;
        }

        $url = $this->getBaseUrl() . '/oauth/token';
        $data = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret']
        ];

        $response = $this->makeRequest('POST', $url, $data, false);

        if (isset($response['access_token'])) {
            $this->accessToken = $response['access_token'];
            $this->tokenExpiry = time() + ($response['expires_in'] ?? 3600) - 60;
            return $this->accessToken;
        }

        throw new \Exception('Failed to obtain access token');
    }

    public function generateQR(array $params)
    {
        $token = $this->getAccessToken();
        $url = $this->getBaseUrl() . '/api/v1/qris/generate';

        $data = [
            'amount' => $params['amount'] ?? 0,
            'merchant_id' => $params['merchant_id'] ?? $this->config['merchant_id'],
            'terminal_id' => $params['terminal_id'] ?? $this->config['terminal_id'],
            'invoice_number' => $params['invoice_number'] ?? $this->generateInvoiceNumber(),
            'customer_name' => $params['customer_name'] ?? '',
            'customer_phone' => $params['customer_phone'] ?? '',
            'timestamp' => now()->toIso8601String()
        ];

        return $this->makeRequest('POST', $url, $data, true);
    }

    public function checkPaymentStatus($transactionId)
    {
        $token = $this->getAccessToken();
        $url = $this->getBaseUrl() . '/api/v1/qris/status/' . $transactionId;

        return $this->makeRequest('GET', $url, null, true);
    }

    public function verifyWebhookSignature(array $payload, $signature)
    {
        $data = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $calculatedSignature = hash_hmac('sha256', $data, $this->config['client_secret']);
        
        return hash_equals($calculatedSignature, $signature);
    }

    public function handleWebhook($rawPayload, $signature)
    {
        $payload = json_decode($rawPayload, true);
        
        if (!$payload) {
            throw new \Exception('Invalid webhook payload');
        }

        if ($this->config['webhook']['verify_signature'] && !$this->verifyWebhookSignature($payload, $signature)) {
            throw new \Exception('Invalid webhook signature');
        }

        return $payload;
    }

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

    private function generateInvoiceNumber()
    {
        return 'INV-' . date('YmdHis') . '-' . mt_rand(1000, 9999);
    }

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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$this->config['sandbox']);

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
}
