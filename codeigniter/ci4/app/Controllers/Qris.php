<?php

namespace App\Controllers;

use App\Libraries\MandiriQris;
use CodeIgniter\RESTful\ResourceController;

class Qris extends ResourceController
{
    protected $modelName = 'App\Models\MandiriQrisPaymentModel';
    protected $format = 'json';
    protected $mandiriQris;
    
    public function __construct()
    {
        $this->mandiriQris = new MandiriQris();
    }
    
    /**
     * Create QRIS payment
     */
    public function create()
    {
        $rules = [
            'amount' => 'required|numeric|greater_than[0]',
            'reference' => 'required|max_length[255]|is_unique[mandiri_qris_payments.reference]',
            'callback_url' => 'permit_empty|valid_url',
        ];
        
        if (!$this->validate($rules)) {
            return $this->fail($this->validator->getErrors(), 400);
        }
        
        $amount = $this->request->getPost('amount');
        $reference = $this->request->getPost('reference');
        $callbackUrl = $this->request->getPost('callback_url');
        
        try {
            // Create QRIS
            $qrisData = $this->mandiriQris->createQris($amount, $reference, $callbackUrl);
            
            // Save to database
            $this->model->insert([
                'qr_id' => $qrisData['qr_id'],
                'reference' => $qrisData['reference'],
                'qr_string' => $qrisData['qr_string'],
                'qr_image_url' => $qrisData['qr_image_url'],
                'amount' => $qrisData['amount'],
                'status' => $qrisData['status'],
                'expired_at' => $qrisData['expired_at'],
            ]);
            
            return $this->respond([
                'success' => true,
                'data' => $qrisData,
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'QRIS Create Error: ' . $e->getMessage());
            return $this->fail($e->getMessage(), 500);
        }
    }
    
    /**
     * Check payment status
     */
    public function status($qrId = null)
    {
        if (!$qrId) {
            return $this->fail('QR ID is required', 400);
        }
        
        try {
            // Get payment from database
            $payment = $this->model->where('qr_id', $qrId)->first();
            
            if (!$payment) {
                return $this->failNotFound('Payment not found');
            }
            
            // If already completed, return cached status
            if ($payment['status'] === 'COMPLETED') {
                return $this->respond([
                    'success' => true,
                    'data' => [
                        'qr_id' => $payment['qr_id'],
                        'status' => $payment['status'],
                        'amount' => $payment['amount'],
                        'paid_at' => $payment['paid_at'],
                        'transaction_id' => $payment['transaction_id'],
                    ],
                ]);
            }
            
            // Check status from API
            $statusData = $this->mandiriQris->checkStatus($qrId, $payment['reference']);
            
            // Update database if status changed
            if ($statusData['status'] !== $payment['status']) {
                $this->model->update($payment['id'], [
                    'status' => $statusData['status'],
                    'transaction_id' => $statusData['transaction_id'],
                    'paid_at' => $statusData['paid_at'],
                ]);
            }
            
            return $this->respond([
                'success' => true,
                'data' => $statusData,
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'QRIS Status Error: ' . $e->getMessage());
            return $this->fail($e->getMessage(), 500);
        }
    }
    
    /**
     * Handle webhook notification
     */
    public function webhook()
    {
        try {
            $json = $this->request->getJSON(true);
            
            $qrId = $json['qrId'] ?? $json['originalReferenceNo'] ?? null;
            $statusCode = $json['transactionStatusCode'] ?? null;
            
            if (!$qrId) {
                return $this->fail('Missing qrId', 400);
            }
            
            // Get payment from database
            $payment = $this->model->where('qr_id', $qrId)->first();
            
            if (!$payment) {
                return $this->failNotFound('Payment not found');
            }
            
            // Map status code
            $statusMap = [
                '00' => 'COMPLETED',
                '03' => 'PENDING',
                '05' => 'EXPIRED',
            ];
            $newStatus = $statusMap[$statusCode] ?? 'FAILED';
            
            // Update payment status
            $updateData = [
                'status' => $newStatus,
            ];
            
            if ($newStatus === 'COMPLETED') {
                $updateData['transaction_id'] = $json['referenceNo'] ?? null;
                $updateData['paid_at'] = date('Y-m-d H:i:s');
            }
            
            $this->model->update($payment['id'], $updateData);
            
            return $this->respond([
                'success' => true,
                'message' => 'Webhook processed',
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'QRIS Webhook Error: ' . $e->getMessage());
            return $this->fail($e->getMessage(), 500);
        }
    }
}
