<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Qris extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('mandiri_qris');
        $this->load->database();
    }
    
    /**
     * Display payment form
     */
    public function index()
    {
        $this->load->view('qris/payment_form');
    }
    
    /**
     * Create QRIS payment
     */
    public function create()
    {
        $this->load->library('form_validation');
        
        $this->form_validation->set_rules('amount', 'Amount', 'required|numeric|greater_than[0]');
        $this->form_validation->set_rules('reference', 'Reference', 'required|max_length[255]');
        
        if ($this->form_validation->run() === FALSE) {
            $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(array(
                    'success' => false,
                    'errors' => validation_errors(),
                )));
            return;
        }
        
        $amount = $this->input->post('amount');
        $reference = $this->input->post('reference');
        $callback_url = $this->input->post('callback_url');
        
        try {
            // Create QRIS
            $qris_data = $this->mandiri_qris->create_qris($amount, $reference, $callback_url);
            
            // Save to database
            $this->db->insert('mandiri_qris_payments', array(
                'qr_id' => $qris_data['qr_id'],
                'reference' => $qris_data['reference'],
                'qr_string' => $qris_data['qr_string'],
                'qr_image_url' => $qris_data['qr_image_url'],
                'amount' => $qris_data['amount'],
                'status' => $qris_data['status'],
                'expired_at' => $qris_data['expired_at'],
                'created_at' => date('Y-m-d H:i:s'),
            ));
            
            $this->output
                ->set_status_header(200)
                ->set_content_type('application/json')
                ->set_output(json_encode(array(
                    'success' => true,
                    'data' => $qris_data,
                )));
                
        } catch (Exception $e) {
            log_message('error', 'QRIS Create Error: ' . $e->getMessage());
            
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode(array(
                    'success' => false,
                    'message' => $e->getMessage(),
                )));
        }
    }
    
    /**
     * Check payment status
     */
    public function status($qr_id)
    {
        try {
            // Get payment from database
            $payment = $this->db->get_where('mandiri_qris_payments', array('qr_id' => $qr_id))->row();
            
            if (!$payment) {
                $this->output
                    ->set_status_header(404)
                    ->set_content_type('application/json')
                    ->set_output(json_encode(array(
                        'success' => false,
                        'message' => 'Payment not found',
                    )));
                return;
            }
            
            // If already completed, return cached status
            if ($payment->status === 'COMPLETED') {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(array(
                        'success' => true,
                        'data' => array(
                            'qr_id' => $payment->qr_id,
                            'status' => $payment->status,
                            'amount' => $payment->amount,
                            'paid_at' => $payment->paid_at,
                            'transaction_id' => $payment->transaction_id,
                        ),
                    )));
                return;
            }
            
            // Check status from API
            $status_data = $this->mandiri_qris->check_status($qr_id, $payment->reference);
            
            // Update database if status changed
            if ($status_data['status'] !== $payment->status) {
                $this->db->where('qr_id', $qr_id);
                $this->db->update('mandiri_qris_payments', array(
                    'status' => $status_data['status'],
                    'transaction_id' => $status_data['transaction_id'],
                    'paid_at' => $status_data['paid_at'],
                    'updated_at' => date('Y-m-d H:i:s'),
                ));
            }
            
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(array(
                    'success' => true,
                    'data' => $status_data,
                )));
                
        } catch (Exception $e) {
            log_message('error', 'QRIS Status Error: ' . $e->getMessage());
            
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode(array(
                    'success' => false,
                    'message' => $e->getMessage(),
                )));
        }
    }
    
    /**
     * Handle webhook notification
     */
    public function webhook()
    {
        try {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            
            $qr_id = isset($data['qrId']) ? $data['qrId'] : (isset($data['originalReferenceNo']) ? $data['originalReferenceNo'] : null);
            $status_code = isset($data['transactionStatusCode']) ? $data['transactionStatusCode'] : null;
            
            if (!$qr_id) {
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode(array(
                        'success' => false,
                        'message' => 'Missing qrId',
                    )));
                return;
            }
            
            // Get payment from database
            $payment = $this->db->get_where('mandiri_qris_payments', array('qr_id' => $qr_id))->row();
            
            if (!$payment) {
                $this->output
                    ->set_status_header(404)
                    ->set_content_type('application/json')
                    ->set_output(json_encode(array(
                        'success' => false,
                        'message' => 'Payment not found',
                    )));
                return;
            }
            
            // Map status code
            $status_map = array(
                '00' => 'COMPLETED',
                '03' => 'PENDING',
                '05' => 'EXPIRED',
            );
            $new_status = isset($status_map[$status_code]) ? $status_map[$status_code] : 'FAILED';
            
            // Update payment status
            $update_data = array(
                'status' => $new_status,
                'updated_at' => date('Y-m-d H:i:s'),
            );
            
            if ($new_status === 'COMPLETED') {
                $update_data['transaction_id'] = isset($data['referenceNo']) ? $data['referenceNo'] : null;
                $update_data['paid_at'] = date('Y-m-d H:i:s');
            }
            
            $this->db->where('qr_id', $qr_id);
            $this->db->update('mandiri_qris_payments', $update_data);
            
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(array(
                    'success' => true,
                    'message' => 'Webhook processed',
                )));
                
        } catch (Exception $e) {
            log_message('error', 'QRIS Webhook Error: ' . $e->getMessage());
            
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode(array(
                    'success' => false,
                    'message' => $e->getMessage(),
                )));
        }
    }
    
    /**
     * Display payment page
     */
    public function payment($qr_id)
    {
        $payment = $this->db->get_where('mandiri_qris_payments', array('qr_id' => $qr_id))->row();
        
        if (!$payment) {
            show_404();
            return;
        }
        
        $data['payment'] = $payment;
        $this->load->view('qris/payment_page', $data);
    }
}
