# CodeIgniter - Mandiri QRIS Payment Library

CodeIgniter implementation for Mandiri QRIS Payment Gateway (supports CI 3.x and CI 4.x).

## 📋 Requirements

- CodeIgniter 3.x or 4.x
- PHP 7.4 or higher
- cURL extension
- OpenSSL extension

## 🚀 Installation

### CodeIgniter 3

1. Copy library file:
```bash
cp -r application/libraries/Mandiri_qris.php /path/to/your/ci3/application/libraries/
```

2. Copy controller example:
```bash
cp -r application/controllers/Qris.php /path/to/your/ci3/application/controllers/
```

### CodeIgniter 4

1. Copy library file:
```bash
cp -r app/Libraries/MandiriQris.php /path/to/your/ci4/app/Libraries/
```

2. Copy controller example:
```bash
cp -r app/Controllers/QrisController.php /path/to/your/ci4/app/Controllers/
```

## ⚙️ Configuration

### CodeIgniter 3

Create `application/config/mandiri_qris.php`:

```php
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['mandiri_env'] = 'sandbox';
$config['mandiri_client_id'] = 'your_client_id';
$config['mandiri_client_secret'] = 'your_client_secret';
$config['mandiri_merchant_nmid'] = 'YOUR_NMID';
$config['mandiri_merchant_name'] = 'YOUR MERCHANT NAME';
$config['mandiri_merchant_city'] = 'JAKARTA';
$config['qris_expiry_minutes'] = 30;
```

### CodeIgniter 4

Create `app/Config/MandiriQris.php`:

```php
<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class MandiriQris extends BaseConfig
{
    public $environment = 'sandbox';
    public $clientId = 'your_client_id';
    public $clientSecret = 'your_client_secret';
    public $merchantNmid = 'YOUR_NMID';
    public $merchantName = 'YOUR MERCHANT NAME';
    public $merchantCity = 'JAKARTA';
    public $qrisExpiryMinutes = 30;
    
    public $sandboxBaseUrl = 'https://sandbox.bankmandiri.co.id';
    public $productionBaseUrl = 'https://api.bankmandiri.co.id';
}
```

Or use `.env`:

```env
mandiri.environment = sandbox
mandiri.clientId = your_client_id
mandiri.clientSecret = your_client_secret
mandiri.merchantNmid = YOUR_NMID
mandiri.merchantName = YOUR MERCHANT NAME
mandiri.merchantCity = JAKARTA
```

## 📝 Usage

### CodeIgniter 3

```php
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Payment extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->library('mandiri_qris');
        $this->load->database();
    }

    public function create_qris()
    {
        $amount = $this->input->post('amount');
        $order_id = $this->input->post('order_id');

        try {
            $qris = $this->mandiri_qris->create_qris([
                'amount' => $amount,
                'reference' => $order_id
            ]);

            // Save to database
            $this->db->insert('payments', [
                'order_id' => $order_id,
                'qr_id' => $qris['qr_id'],
                'qr_string' => $qris['qr_string'],
                'qr_image_url' => $qris['qr_image_url'],
                'amount' => $amount,
                'status' => 'pending',
                'expired_at' => $qris['expired_at']
            ]);

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => TRUE,
                    'data' => $qris
                ]));

        } catch (Exception $e) {
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => FALSE,
                    'error' => $e->getMessage()
                ]));
        }
    }

    public function check_status($qr_id)
    {
        try {
            $status = $this->mandiri_qris->check_status($qr_id);

            // Update database if paid
            if ($status['status'] === 'COMPLETED') {
                $this->db->where('qr_id', $qr_id);
                $this->db->update('payments', [
                    'status' => 'paid',
                    'paid_at' => date('Y-m-d H:i:s')
                ]);
            }

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => TRUE,
                    'data' => $status
                ]));

        } catch (Exception $e) {
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => FALSE,
                    'error' => $e->getMessage()
                ]));
        }
    }

    public function show_qris($order_id)
    {
        $payment = $this->db->get_where('payments', ['order_id' => $order_id])->row();
        
        if (!$payment) {
            show_404();
        }

        $data['payment'] = $payment;
        $this->load->view('payment/qris', $data);
    }
}
```

### CodeIgniter 4

```php
<?php

namespace App\Controllers;

use App\Libraries\MandiriQris;
use CodeIgniter\RESTful\ResourceController;

class QrisController extends ResourceController
{
    protected $mandiriQris;
    protected $paymentModel;

    public function __construct()
    {
        $this->mandiriQris = new MandiriQris();
        $this->paymentModel = model('PaymentModel');
    }

    public function create()
    {
        $rules = [
            'amount' => 'required|decimal|greater_than[10000]',
            'order_id' => 'required|string|is_unique[payments.order_id]'
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        try {
            $amount = $this->request->getPost('amount');
            $orderId = $this->request->getPost('order_id');

            $qris = $this->mandiriQris->createQris([
                'amount' => $amount,
                'reference' => $orderId
            ]);

            // Save to database
            $this->paymentModel->insert([
                'payment_id' => 'PAY-' . $orderId,
                'order_id' => $orderId,
                'user_id' => auth()->id(),
                'amount' => $amount,
                'payment_method' => 'qris',
                'status' => 'pending',
                'qr_id' => $qris['qr_id'],
                'qr_string' => $qris['qr_string'],
                'qr_image_url' => $qris['qr_image_url'],
                'expired_at' => $qris['expired_at']
            ]);

            return $this->respondCreated([
                'success' => true,
                'data' => $qris
            ]);

        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 500);
        }
    }

    public function checkStatus($qrId = null)
    {
        if (!$qrId) {
            return $this->failValidationErrors('QR ID is required');
        }

        try {
            $status = $this->mandiriQris->checkStatus($qrId);

            // Update database if paid
            if ($status['status'] === 'COMPLETED') {
                $payment = $this->paymentModel->where('qr_id', $qrId)->first();
                
                if ($payment && $payment['status'] === 'pending') {
                    $this->paymentModel->update($payment['id'], [
                        'status' => 'paid',
                        'paid_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }

            return $this->respond([
                'success' => true,
                'data' => $status
            ]);

        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 500);
        }
    }

    public function webhook()
    {
        $payload = $this->request->getJSON(true);
        
        log_message('info', 'QRIS Webhook: ' . json_encode($payload));

        if (isset($payload['status']) && $payload['status'] === 'COMPLETED') {
            $qrId = $payload['qr_id'];
            $payment = $this->paymentModel->where('qr_id', $qrId)->first();
            
            if ($payment && $payment['status'] === 'pending') {
                $this->paymentModel->update($payment['id'], [
                    'status' => 'paid',
                    'paid_at' => date('Y-m-d H:i:s')
                ]);
            }
        }

        return $this->respond(['status' => 'ok']);
    }

    public function show($orderId = null)
    {
        $payment = $this->paymentModel->where('order_id', $orderId)->first();
        
        if (!$payment) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('payment/qris', ['payment' => $payment]);
    }
}
```

## 🎨 View Example

Create `application/views/payment/qris.php` (CI3) or `app/Views/payment/qris.php` (CI4):

```php
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QRIS Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Scan QR Code</h4>
                        <div id="timer" class="text-center fs-3">30:00</div>
                    </div>
                    <div class="card-body text-center">
                        <img src="<?= $payment->qr_image_url ?>" alt="QR Code" class="img-fluid mb-3" style="max-width: 300px;">
                        
                        <div class="payment-info bg-light p-3 rounded mb-3">
                            <p><strong>Amount:</strong> Rp <?= number_format($payment->amount, 0, ',', '.') ?></p>
                            <p><strong>Order ID:</strong> <?= $payment->order_id ?></p>
                            <p><strong>Status:</strong> <span id="status-badge" class="badge bg-warning">Pending</span></p>
                        </div>
                        
                        <div id="loading" class="mb-3">
                            <div class="spinner-border text-primary"></div>
                            <p>Waiting for payment...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const qrId = '<?= $payment->qr_id ?>';
        let remainingSeconds = 30 * 60;
        
        // Timer
        setInterval(() => {
            remainingSeconds--;
            const minutes = Math.floor(remainingSeconds / 60);
            const seconds = remainingSeconds % 60;
            document.getElementById('timer').textContent = 
                `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            if (remainingSeconds <= 0) {
                showExpired();
            }
        }, 1000);
        
        // Polling
        setInterval(() => {
            fetch(`<?= base_url('qris/check_status/') ?>${qrId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.data.status === 'COMPLETED') {
                        showSuccess();
                    }
                });
        }, 3000);
        
        function showSuccess() {
            document.getElementById('status-badge').textContent = 'Paid';
            document.getElementById('status-badge').className = 'badge bg-success';
            document.getElementById('loading').innerHTML = 
                '<div class="alert alert-success">✓ Payment Successful!</div>';
            setTimeout(() => window.location.href = '<?= base_url('payment/success') ?>', 2000);
        }
        
        function showExpired() {
            document.getElementById('status-badge').textContent = 'Expired';
            document.getElementById('status-badge').className = 'badge bg-danger';
            document.getElementById('loading').innerHTML = 
                '<div class="alert alert-danger">QR Code Expired</div>';
        }
    </script>
</body>
</html>
```

## 🗺️ Routes

### CodeIgniter 4

Add to `app/Config/Routes.php`:

```php
$routes->group('api/qris', function($routes) {
    $routes->post('create', 'QrisController::create');
    $routes->get('status/(:any)', 'QrisController::checkStatus/$1');
    $routes->post('webhook', 'QrisController::webhook');
});

$routes->get('payment/(:any)', 'QrisController::show/$1');
```

## 📄 License

MIT License
