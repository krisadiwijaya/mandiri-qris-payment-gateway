# Python SDK for Mandiri QRIS Payment Gateway

Python SDK for integrating Mandiri QRIS Payment Gateway with OAuth 2.0 authentication.

## Installation

```bash
pip install mandiri-qris
```

## Requirements

- Python >= 3.7
- requests

## Quick Start

```python
from mandiri_qris import MandiriQrisClient

# Initialize client
client = MandiriQrisClient(
    client_id='your_client_id',
    client_secret='your_client_secret',
    base_url='https://api.mandiri.co.id',
    sandbox=True
)

# Generate QR Code
qr = client.generate_qr(
    amount=100000,
    merchant_id='MERCHANT123',
    terminal_id='TERM001',
    customer_name='John Doe',
    customer_phone='081234567890'
)

print(f"Transaction ID: {qr['transaction_id']}")
print(f"QR String: {qr['qr_string']}")

# Check payment status
status = client.check_payment_status(qr['transaction_id'])
print(f"Payment Status: {status['status']}")
```

## Webhook Handler (Flask Example)

```python
from flask import Flask, request, jsonify
from mandiri_qris import MandiriQrisClient

app = Flask(__name__)
client = MandiriQrisClient(...)

@app.route('/webhook/mandiri-qris', methods=['POST'])
def webhook():
    try:
        payload = client.handle_webhook(
            request.data.decode('utf-8'),
            request.headers.get('X-Signature', '')
        )
        
        if payload['status'] == 'SUCCESS':
            # Payment successful
            # Update database, send confirmation, etc.
            pass
        
        return jsonify({'status': 'ok'})
    except Exception as e:
        return jsonify({'error': str(e)}), 400
```

## Payment Polling

```python
# Poll every 5 seconds for up to 5 minutes
final_status = client.poll_payment_status(transaction_id, 60, 5)

if final_status['status'] == 'SUCCESS':
    print("Payment completed!")
```

## Examples

See the [examples](examples/) directory for complete examples.

## License

MIT
