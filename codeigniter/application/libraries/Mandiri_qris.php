<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Mandiri QRIS Library for CodeIgniter
 */
class Mandiri_qris
{
    private $CI;
    private $config;
    private $access_token;
    private $token_expiry;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->config('mandiri_qris');
        $this->config = $this->CI->config->item('mandiri_qris');
    }

    /**
     * Get OAuth 2.0 access token
     */
    private function get_access_token()
    {
        // Return cached token if still valid
        if ($this->access_token && $this->token_expiry > time()) {
            return $this->access_token;
        }

        $url = $this->get_base_url() . '/oauth/token';
        $data = array(
            'grant_type' => 'client_credentials',
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret']
        );

        $response = $this->make_request('POST', $url, $data, false);

        if (isset($response['access_token'])) {
            $this->access_token = $response['access_token'];
            $this->token_expiry = time() + ($response['expires_in'] ?? 3600) - 60;
            return $this->access_token;
        }

        throw new Exception('Failed to obtain access token');
    }

    /**
     * Get base URL
     */
    private function get_base_url()
    {
        return $this->config['sandbox'] ? 'https://sandbox-api.mandiri.co.id' : $this->config['base_url'];
    }

    /**
     * Generate QR code
     */
    public function generate_qr($params)
    {
        $token = $this->get_access_token();
        $url = $this->get_base_url() . '/api/v1/qris/generate';

        $data = array(
            'amount' => $params['amount'] ?? 0,
            'merchant_id' => $params['merchant_id'] ?? $this->config['merchant_id'],
            'terminal_id' => $params['terminal_id'] ?? $this->config['terminal_id'],
            'invoice_number' => $params['invoice_number'] ?? $this->generate_invoice_number(),
            'customer_name' => $params['customer_name'] ?? '',
            'customer_phone' => $params['customer_phone'] ?? '',
            'timestamp' => date('Y-m-d\TH:i:s\Z')
        );

        return $this->make_request('POST', $url, $data, true);
    }

    /**
     * Check payment status
     */
    public function check_payment_status($transaction_id)
    {
        $token = $this->get_access_token();
        $url = $this->get_base_url() . '/api/v1/qris/status/' . $transaction_id;

        return $this->make_request('GET', $url, null, true);
    }

    /**
     * Verify webhook signature
     */
    public function verify_webhook_signature($payload, $signature)
    {
        $data = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $calculated_signature = hash_hmac('sha256', $data, $this->config['client_secret']);
        
        return hash_equals($calculated_signature, $signature);
    }

    /**
     * Handle webhook
     */
    public function handle_webhook($raw_payload, $signature)
    {
        $payload = json_decode($raw_payload, true);
        
        if (!$payload) {
            throw new Exception('Invalid webhook payload');
        }

        if (!$this->verify_webhook_signature($payload, $signature)) {
            throw new Exception('Invalid webhook signature');
        }

        return $payload;
    }

    /**
     * Poll payment status
     */
    public function poll_payment_status($transaction_id, $max_attempts = 60, $interval_seconds = 5)
    {
        $attempts = 0;
        
        while ($attempts < $max_attempts) {
            $status = $this->check_payment_status($transaction_id);
            
            if (in_array($status['status'] ?? '', ['SUCCESS', 'FAILED', 'EXPIRED'])) {
                return $status;
            }
            
            sleep($interval_seconds);
            $attempts++;
        }
        
        throw new Exception('Payment status polling timeout');
    }

    /**
     * Generate invoice number
     */
    private function generate_invoice_number()
    {
        return 'INV-' . date('YmdHis') . '-' . mt_rand(1000, 9999);
    }

    /**
     * Make HTTP request
     */
    private function make_request($method, $url, $data = null, $authenticate = false)
    {
        $ch = curl_init();
        
        $headers = array(
            'Content-Type: application/json',
            'Accept: application/json'
        );

        if ($authenticate) {
            $headers[] = 'Authorization: Bearer ' . $this->access_token;
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
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('cURL error: ' . $error);
        }

        $decoded = json_decode($response, true);

        if ($http_code >= 400) {
            throw new Exception('API error: ' . ($decoded['message'] ?? $response));
        }

        return $decoded ?? array();
    }
}
