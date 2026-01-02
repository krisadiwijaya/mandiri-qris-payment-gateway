"""
Basic usage example for Mandiri QRIS Python SDK
"""

import os
from mandiri_qris import MandiriQrisClient

# Initialize client
client = MandiriQrisClient(
    client_id=os.getenv('MANDIRI_CLIENT_ID', 'your_client_id'),
    client_secret=os.getenv('MANDIRI_CLIENT_SECRET', 'your_client_secret'),
    base_url=os.getenv('MANDIRI_BASE_URL', 'https://api.mandiri.co.id'),
    sandbox=True
)

try:
    # Generate QR Code
    print("Generating QR Code...")
    qr = client.generate_qr(
        amount=100000,
        merchant_id='MERCHANT123',
        terminal_id='TERM001',
        customer_name='John Doe',
        customer_phone='081234567890'
    )
    
    print("QR Generated Successfully!")
    print(f"Transaction ID: {qr['transaction_id']}")
    print(f"QR String: {qr['qr_string']}\n")
    
    # Check payment status
    print("Checking payment status...")
    status = client.check_payment_status(qr['transaction_id'])
    print(f"Status: {status['status']}")
    print(f"Amount: {status['amount']}\n")
    
    # Poll payment status (optional)
    print("Polling payment status (will wait up to 5 minutes)...")
    final_status = client.poll_payment_status(qr['transaction_id'], 60, 5)
    print(f"Final Status: {final_status['status']}")
    
except Exception as e:
    print(f"Error: {e}")
