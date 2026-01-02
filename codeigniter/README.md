# CodeIgniter Integration for Mandiri QRIS Payment Gateway

CodeIgniter library for integrating Mandiri QRIS Payment Gateway.

## Installation

1. Copy the library file to your CodeIgniter application:
   ```
   application/libraries/Mandiri_qris.php
   ```

2. Copy the config file:
   ```
   application/config/mandiri_qris.php
   ```

3. Configure your credentials in `config/mandiri_qris.php` or use environment variables.

## Configuration

Edit `application/config/mandiri_qris.php`:

```php
$config['mandiri_qris'] = array(
    'client_id' => 'your_client_id',
    'client_secret' => 'your_client_secret',
    'base_url' => 'https://api.mandiri.co.id',
    'sandbox' => true,
    'merchant_id' => 'MERCHANT123',
    'terminal_id' => 'TERM001',
);
```

## Usage

### Load the Library

```php
$this->load->library('mandiri_qris');
```

### Generate QR Code

```php
$qr = $this->mandiri_qris->generate_qr(array(
    'amount' => 100000,
    'customer_name' => 'John Doe',
    'customer_phone' => '081234567890'
));

echo $qr['transaction_id'];
echo $qr['qr_string'];
```

### Check Payment Status

```php
$status = $this->mandiri_qris->check_payment_status($transaction_id);
echo $status['status'];
```

### Webhook Handler

```php
public function webhook()
{
    $raw_payload = file_get_contents('php://input');
    $signature = $this->input->get_request_header('X-Signature', TRUE);

    try {
        $payload = $this->mandiri_qris->handle_webhook($raw_payload, $signature);
        
        if ($payload['status'] === 'SUCCESS') {
            // Payment successful
        }
        
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(array('status' => 'ok')));
    } catch (Exception $e) {
        $this->output
            ->set_status_header(400)
            ->set_content_type('application/json')
            ->set_output(json_encode(array('error' => $e->getMessage())));
    }
}
```

## Example Controller

See [examples/Payment_controller.php](examples/Payment_controller.php) for a complete example.

## License

MIT
