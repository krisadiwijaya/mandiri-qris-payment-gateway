<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Example Controller for Mandiri QRIS
 */
class Payment extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('mandiri_qris');
    }

    /**
     * Generate QR Code
     */
    public function generate_qr()
    {
        try {
            $qr = $this->mandiri_qris->generate_qr(array(
                'amount' => 100000,
                'customer_name' => 'John Doe',
                'customer_phone' => '081234567890'
            ));

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(array(
                    'success' => true,
                    'data' => $qr
                )));
        } catch (Exception $e) {
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode(array(
                    'success' => false,
                    'error' => $e->getMessage()
                )));
        }
    }

    /**
     * Check Payment Status
     */
    public function check_status($transaction_id)
    {
        try {
            $status = $this->mandiri_qris->check_payment_status($transaction_id);

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(array(
                    'success' => true,
                    'data' => $status
                )));
        } catch (Exception $e) {
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode(array(
                    'success' => false,
                    'error' => $e->getMessage()
                )));
        }
    }

    /**
     * Webhook Handler
     */
    public function webhook()
    {
        try {
            $raw_payload = file_get_contents('php://input');
            $signature = $this->input->get_request_header('X-Signature', TRUE);

            $payload = $this->mandiri_qris->handle_webhook($raw_payload, $signature);

            // Process payment based on status
            if ($payload['status'] === 'SUCCESS') {
                // Update database, send confirmation, etc.
                log_message('info', 'Payment successful: ' . $payload['transaction_id']);
            }

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(array('status' => 'ok')));
        } catch (Exception $e) {
            log_message('error', 'Webhook error: ' . $e->getMessage());
            
            $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(array('error' => $e->getMessage())));
        }
    }
}
