<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Mandiri QRIS Library for CodeIgniter 3
 * 
 * @package    CodeIgniter
 * @subpackage Libraries
 * @category   Payment Gateway
 * @author     Your Name
 */
class Mandiri_qris
{
    private $CI;
    private $client_id;
    private $client_secret;
    private $merchant_nmid;
    private $merchant_name;
    private $merchant_city;
    private $base_url;
    private $qris_expiry_minutes;
    private $timeout;
    
    /**
     * Constructor
     */
    public function __construct($config = array())
    {
        $this->CI =& get_instance();
        
        // Load configuration
        if (empty($config)) {
            $this->CI->load->config('mandiri_qris');
            $config = $this->CI->config->item('mandiri_qris');
        }
        
        $this->client_id = $config['client_id'];
        $this->client_secret = $config['client_secret'];
        $this->merchant_nmid = $config['merchant_nmid'];
        $this->merchant_name = $config['merchant_name'];
        $this->merchant_city = $config['merchant_city'];
        $this->qris_expiry_minutes = isset($config['qris_expiry_minutes']) ? $config['qris_expiry_minutes'] : 5;
        $this->timeout = isset($config['timeout']) ? $config['timeout'] : 30;
        
        // Set base URL
        $environment = isset($config['environment']) ? $config['environment'] : 'sandbox';
        $this->base_url = $environment === 'production' 
            ? 'https://api.bankmandiri.co.id' 
            : 'https://sandbox.bankmandiri.co.id';
        
        // Load cache library
        $this->CI->load->driver('cache', array('adapter' => 'file'));
    }
    
    /**
     * Get access token
     *
     * @return string
     */
    public function get_access_token()
    {
        $cache_key = 'mandiri_qris_token_' . md5($this->client_id);
        
        // Try to get from cache
        $token = $this->CI->cache->get($cache_key);
        if ($token) {
            return $token;
        }
        
        // Request new token
        $timestamp = gmdate('Y-m-d\TH:i:s.000\Z');
        $signature = base64_encode(hash_hmac(
            'sha256',
            $this->client_id . '|' . $timestamp,
            $this->client_secret,
            true
        ));
        
        $ch = curl_init($this->base_url . '/openapi/auth/v2.0/access-token/b2b');
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'X-TIMESTAMP: ' . $timestamp,
                'X-CLIENT-KEY: ' . $this->client_id,
                'X-SIGNATURE: ' . $signature,
            ),
            CURLOPT_POSTFIELDS => json_encode(array(
                'grantType' => 'client_credentials',
            )),
        ));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            log_message('error', 'Mandiri QRIS: Failed to get access token - ' . $response);
            throw new Exception('Failed to get access token: HTTP ' . $http_code);
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['accessToken'])) {
            throw new Exception('Failed to get access token: Invalid response');
        }
        
        $token = $data['accessToken'];
        $expires_in = isset($data['expiresIn']) ? $data['expiresIn'] : 3600;
        
        // Cache token (with 60 seconds safety margin)
        $this->CI->cache->save($cache_key, $token, $expires_in - 60);
        
        return $token;
    }
    
    /**
     * Create QRIS payment
     *
     * @param float $amount
     * @param string $reference
     * @param string $callback_url
     * @return array
     */
    public function create_qris($amount, $reference, $callback_url = null)
    {
        $token = $this->get_access_token();
        $timestamp = gmdate('Y-m-d\TH:i:s.000\Z');
        $expiry_time = gmdate('Y-m-d\TH:i:s.000\Z', strtotime('+' . $this->qris_expiry_minutes . ' minutes'));
        
        $payload = array(
            'partnerReferenceNo' => $reference,
            'amount' => array(
                'value' => number_format($amount, 2, '.', ''),
                'currency' => 'IDR',
            ),
            'merchantId' => $this->merchant_nmid,
            'storeLabel' => $this->merchant_name,
            'terminalLabel' => $this->merchant_city,
            'validityPeriod' => $expiry_time,
        );
        
        if ($callback_url) {
            $payload['additionalInfo'] = array(
                'callbackUrl' => $callback_url,
            );
        }
        
        $ch = curl_init($this->base_url . '/openapi/qris/v1.0/qr-code-dynamic');
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
                'X-TIMESTAMP: ' . $timestamp,
                'X-PARTNER-ID: ' . $this->merchant_nmid,
                'X-EXTERNAL-ID: ' . $reference,
            ),
            CURLOPT_POSTFIELDS => json_encode($payload),
        ));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            log_message('error', 'Mandiri QRIS: Failed to create QRIS - ' . $response);
            throw new Exception('Failed to create QRIS: HTTP ' . $http_code);
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['qrContent'])) {
            throw new Exception('Failed to create QRIS: Invalid response');
        }
        
        return array(
            'qr_id' => isset($data['qrId']) ? $data['qrId'] : $reference,
            'qr_string' => $data['qrContent'],
            'qr_image_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($data['qrContent']),
            'status' => 'PENDING',
            'amount' => $amount,
            'reference' => $reference,
            'expired_at' => $expiry_time,
        );
    }
    
    /**
     * Check payment status
     *
     * @param string $qr_id
     * @param string $reference
     * @return array
     */
    public function check_status($qr_id, $reference)
    {
        $token = $this->get_access_token();
        $timestamp = gmdate('Y-m-d\TH:i:s.000\Z');
        
        $payload = array(
            'originalPartnerReferenceNo' => $reference,
            'originalReferenceNo' => $qr_id,
            'serviceCode' => '47',
        );
        
        $ch = curl_init($this->base_url . '/openapi/qris/v1.0/qr-code-dynamic/status');
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
                'X-TIMESTAMP: ' . $timestamp,
                'X-PARTNER-ID: ' . $this->merchant_nmid,
                'X-EXTERNAL-ID: ' . $qr_id,
            ),
            CURLOPT_POSTFIELDS => json_encode($payload),
        ));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            log_message('error', 'Mandiri QRIS: Failed to check status - ' . $response);
            throw new Exception('Failed to check status: HTTP ' . $http_code);
        }
        
        $data = json_decode($response, true);
        
        $status = 'UNKNOWN';
        $paid_at = null;
        $transaction_id = null;
        
        if (isset($data['transactionStatusCode'])) {
            switch ($data['transactionStatusCode']) {
                case '00':
                    $status = 'COMPLETED';
                    $paid_at = isset($data['transactionDate']) ? $data['transactionDate'] : date('Y-m-d H:i:s');
                    $transaction_id = isset($data['referenceNo']) ? $data['referenceNo'] : null;
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
        
        return array(
            'qr_id' => $qr_id,
            'status' => $status,
            'amount' => isset($data['amount']['value']) ? (float)$data['amount']['value'] : null,
            'paid_at' => $paid_at,
            'transaction_id' => $transaction_id,
        );
    }
    
    /**
     * Clear cached token
     *
     * @return void
     */
    public function clear_token()
    {
        $cache_key = 'mandiri_qris_token_' . md5($this->client_id);
        $this->CI->cache->delete($cache_key);
    }
}
