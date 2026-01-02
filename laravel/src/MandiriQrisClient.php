<?php

namespace Mandiri\Qris;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MandiriQrisClient
{
    private $clientId;
    private $clientSecret;
    private $merchantNmid;
    private $merchantName;
    private $merchantCity;
    private $baseUrl;
    private $qrisExpiryMinutes;
    private $timeout;
    private $httpClient;

    /**
     * Constructor
     *
     * @param array $config Configuration array
     */
    public function __construct(array $config)
    {
        $this->clientId = $config['client_id'];
        $this->clientSecret = $config['client_secret'];
        $this->merchantNmid = $config['merchant_nmid'];
        $this->merchantName = $config['merchant_name'];
        $this->merchantCity = $config['merchant_city'];
        $this->qrisExpiryMinutes = $config['qris_expiry_minutes'] ?? 5;
        $this->timeout = $config['timeout'] ?? 30;

        // Set base URL based on environment
        $environment = $config['environment'] ?? 'sandbox';
        $this->baseUrl = $environment === 'production'
            ? 'https://api.bankmandiri.co.id'
            : 'https://sandbox.bankmandiri.co.id';

        $this->httpClient = new Client([
            'timeout' => $this->timeout,
            'verify' => true,
        ]);
    }

    /**
     * Get access token (with caching)
     *
     * @return string Access token
     * @throws \Exception
     */
    public function getAccessToken()
    {
        $cacheKey = 'mandiri_qris_token_' . md5($this->clientId);

        // Try to get token from cache
        $token = Cache::get($cacheKey);
        if ($token) {
            return $token;
        }

        // Request new token
        try {
            $timestamp = now()->format('Y-m-d\TH:i:s.000\Z');
            $signature = base64_encode(hash_hmac(
                'sha256',
                $this->clientId . '|' . $timestamp,
                $this->clientSecret,
                true
            ));

            $response = $this->httpClient->post($this->baseUrl . '/openapi/auth/v2.0/access-token/b2b', [
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

            $data = json_decode($response->getBody(), true);

            if (!isset($data['accessToken'])) {
                throw new \Exception('Failed to get access token: Invalid response');
            }

            $token = $data['accessToken'];
            $expiresIn = $data['expiresIn'] ?? 3600;

            // Cache token (with 60 seconds safety margin)
            Cache::put($cacheKey, $token, now()->addSeconds($expiresIn - 60));

            return $token;

        } catch (RequestException $e) {
            $response = $e->getResponse();
            $message = $response ? $response->getBody()->getContents() : $e->getMessage();
            Log::error('Mandiri QRIS: Failed to get access token', [
                'error' => $message,
            ]);
            throw new \Exception('Failed to get access token: ' . $message);
        }
    }

    /**
     * Create QRIS payment
     *
     * @param float $amount Payment amount
     * @param string $reference Unique reference
     * @param string|null $callbackUrl Callback URL for webhook
     * @return array QRIS data
     * @throws \Exception
     */
    public function createQris($amount, $reference, $callbackUrl = null)
    {
        try {
            $token = $this->getAccessToken();
            $timestamp = now()->format('Y-m-d\TH:i:s.000\Z');
            $expiryTime = now()->addMinutes($this->qrisExpiryMinutes)->format('Y-m-d\TH:i:s.000\Z');

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

            $response = $this->httpClient->post($this->baseUrl . '/openapi/qris/v1.0/qr-code-dynamic', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                    'X-TIMESTAMP' => $timestamp,
                    'X-PARTNER-ID' => $this->merchantNmid,
                    'X-EXTERNAL-ID' => $reference,
                ],
                'json' => $payload,
            ]);

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

        } catch (RequestException $e) {
            $response = $e->getResponse();
            $message = $response ? $response->getBody()->getContents() : $e->getMessage();
            Log::error('Mandiri QRIS: Failed to create QRIS', [
                'error' => $message,
                'reference' => $reference,
            ]);
            throw new \Exception('Failed to create QRIS: ' . $message);
        }
    }

    /**
     * Check payment status
     *
     * @param string $qrId QR ID
     * @param string $reference Original reference
     * @return array Status data
     * @throws \Exception
     */
    public function checkStatus($qrId, $reference)
    {
        try {
            $token = $this->getAccessToken();
            $timestamp = now()->format('Y-m-d\TH:i:s.000\Z');

            $response = $this->httpClient->post($this->baseUrl . '/openapi/qris/v1.0/qr-code-dynamic/status', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                    'X-TIMESTAMP' => $timestamp,
                    'X-PARTNER-ID' => $this->merchantNmid,
                    'X-EXTERNAL-ID' => $qrId,
                ],
                'json' => [
                    'originalPartnerReferenceNo' => $reference,
                    'originalReferenceNo' => $qrId,
                    'serviceCode' => '47',
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            $status = 'UNKNOWN';
            $paidAt = null;
            $transactionId = null;

            if (isset($data['transactionStatusCode'])) {
                switch ($data['transactionStatusCode']) {
                    case '00':
                        $status = 'COMPLETED';
                        $paidAt = $data['transactionDate'] ?? now()->toIso8601String();
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

        } catch (RequestException $e) {
            $response = $e->getResponse();
            $message = $response ? $response->getBody()->getContents() : $e->getMessage();
            Log::error('Mandiri QRIS: Failed to check status', [
                'error' => $message,
                'qr_id' => $qrId,
            ]);
            throw new \Exception('Failed to check status: ' . $message);
        }
    }

    /**
     * Clear cached token
     *
     * @return void
     */
    public function clearToken()
    {
        $cacheKey = 'mandiri_qris_token_' . md5($this->clientId);
        Cache::forget($cacheKey);
    }
}
