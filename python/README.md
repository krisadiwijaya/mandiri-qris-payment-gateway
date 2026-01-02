# Python - Mandiri QRIS Payment SDK

Python SDK for Mandiri QRIS Payment Gateway integration.

## 📋 Requirements

- Python 3.8 or higher
- requests library

## 🚀 Installation

```bash
pip install mandiri-qris
```

Or install from source:

```bash
git clone https://github.com/yourusername/mandiri-qris-python.git
cd mandiri-qris-python
pip install -r requirements.txt
pip install -e .
```

## ⚙️ Configuration

Create a `.env` file:

```env
MANDIRI_ENV=sandbox
MANDIRI_CLIENT_ID=your_client_id
MANDIRI_CLIENT_SECRET=your_client_secret
MANDIRI_MERCHANT_NMID=YOUR_NMID
MANDIRI_MERCHANT_NAME=YOUR MERCHANT NAME
MANDIRI_MERCHANT_CITY=JAKARTA
```

## 📝 Basic Usage

```python
from mandiri_qris import MandiriQrisClient

# Initialize client
client = MandiriQrisClient(
    client_id='your_client_id',
    client_secret='your_client_secret',
    environment='sandbox'
)

# Create QRIS
qris = client.create_qris(
    amount=100000,
    reference='ORDER-001',
    merchant_nmid='YOUR_NMID',
    merchant_name='YOUR STORE',
    merchant_city='JAKARTA'
)

print(f"QR ID: {qris['qr_id']}")
print(f"QR Image: {qris['qr_image_url']}")

# Check status
status = client.check_status(qris['qr_id'])
print(f"Status: {status['status']}")
```

## 🌐 Flask Example

```python
from flask import Flask, request, jsonify, render_template
from mandiri_qris import MandiriQrisClient
import os

app = Flask(__name__)

client = MandiriQrisClient(
    client_id=os.getenv('MANDIRI_CLIENT_ID'),
    client_secret=os.getenv('MANDIRI_CLIENT_SECRET'),
    environment='sandbox'
)

@app.route('/api/qris/create', methods=['POST'])
def create_qris():
    try:
        data = request.json
        qris = client.create_qris(
            amount=data['amount'],
            reference=data['reference'],
            merchant_nmid=os.getenv('MANDIRI_MERCHANT_NMID'),
            merchant_name=os.getenv('MANDIRI_MERCHANT_NAME'),
            merchant_city=os.getenv('MANDIRI_MERCHANT_CITY')
        )
        return jsonify({'success': True, 'data': qris})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/qris/status/<qr_id>')
def check_status(qr_id):
    try:
        status = client.check_status(qr_id)
        return jsonify({'success': True, 'data': status})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/qris/webhook', methods=['POST'])
def webhook():
    payload = request.json
    # Process webhook
    if payload.get('status') == 'COMPLETED':
        # Update database
        pass
    return jsonify({'status': 'ok'})

if __name__ == '__main__':
    app.run(debug=True)
```

## 🎯 Django Example

```python
# views.py
from django.http import JsonResponse
from django.views.decorators.csrf import csrf_exempt
from mandiri_qris import MandiriQrisClient
import os
import json

client = MandiriQrisClient(
    client_id=os.getenv('MANDIRI_CLIENT_ID'),
    client_secret=os.getenv('MANDIRI_CLIENT_SECRET'),
    environment='sandbox'
)

def create_qris(request):
    if request.method == 'POST':
        try:
            data = json.loads(request.body)
            qris = client.create_qris(
                amount=data['amount'],
                reference=data['reference'],
                merchant_nmid=os.getenv('MANDIRI_MERCHANT_NMID'),
                merchant_name=os.getenv('MANDIRI_MERCHANT_NAME'),
                merchant_city=os.getenv('MANDIRI_MERCHANT_CITY')
            )
            return JsonResponse({'success': True, 'data': qris})
        except Exception as e:
            return JsonResponse({'success': False, 'error': str(e)}, status=500)

def check_status(request, qr_id):
    try:
        status = client.check_status(qr_id)
        return JsonResponse({'success': True, 'data': status})
    except Exception as e:
        return JsonResponse({'success': False, 'error': str(e)}, status=500)

@csrf_exempt
def webhook(request):
    if request.method == 'POST':
        payload = json.loads(request.body)
        # Process webhook
        return JsonResponse({'status': 'ok'})
```

## 📚 API Reference

### MandiriQrisClient

#### `__init__(client_id, client_secret, environment='sandbox', **kwargs)`

Initialize the client.

#### `create_qris(amount, reference, merchant_nmid, merchant_name, merchant_city, callback_url=None)`

Create a new QRIS payment.

**Returns:**
```python
{
    'qr_id': 'QR123456789',
    'qr_string': '00020101...',
    'qr_image_url': 'https://...',
    'status': 'ACTIVE',
    'expired_at': '2025-12-30 11:30:00'
}
```

#### `check_status(qr_id)`

Check payment status.

**Returns:**
```python
{
    'status': 'COMPLETED',
    'amount': 100000,
    'paid_at': '2025-12-30 10:45:00'
}
```

## 🧪 Testing

```bash
pytest tests/
```

## 📄 License

MIT License
