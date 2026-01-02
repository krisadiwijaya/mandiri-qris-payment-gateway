"""
Mandiri QRIS Payment Gateway SDK for Python
"""

import requests
import time
import hmac
import hashlib
import json
from typing import Dict, Optional
from datetime import datetime, timedelta


class MandiriQrisClient:
    """
    Mandiri QRIS Payment Gateway Client
    
    Handles OAuth 2.0 authentication, QR generation, payment polling, and webhooks
    """
    
    def __init__(self, client_id: str, client_secret: str, 
                 base_url: str = 'https://api.mandiri.co.id', 
                 sandbox: bool = False):
        """
        Initialize the Mandiri QRIS client
        
        Args:
            client_id: OAuth client ID
            client_secret: OAuth client secret
            base_url: API base URL
            sandbox: Enable sandbox mode
        """
        self.client_id = client_id
        self.client_secret = client_secret
        self.base_url = 'https://sandbox-api.mandiri.co.id' if sandbox else base_url
        self.sandbox = sandbox
        self._access_token = None
        self._token_expiry = None
    
    def _get_access_token(self) -> str:
        """Get OAuth 2.0 access token"""
        # Return cached token if still valid
        if self._access_token and self._token_expiry and datetime.now() < self._token_expiry:
            return self._access_token
        
        url = f"{self.base_url}/oauth/token"
        data = {
            'grant_type': 'client_credentials',
            'client_id': self.client_id,
            'client_secret': self.client_secret
        }
        
        response = requests.post(url, json=data, verify=not self.sandbox)
        response.raise_for_status()
        
        token_data = response.json()
        self._access_token = token_data['access_token']
        expires_in = token_data.get('expires_in', 3600)
        self._token_expiry = datetime.now() + timedelta(seconds=expires_in - 60)
        
        return self._access_token
    
    def generate_qr(self, amount: int, merchant_id: str, terminal_id: str,
                    invoice_number: Optional[str] = None,
                    customer_name: Optional[str] = None,
                    customer_phone: Optional[str] = None) -> Dict:
        """
        Generate dynamic QRIS QR code
        
        Args:
            amount: Payment amount in IDR
            merchant_id: Merchant ID
            terminal_id: Terminal ID
            invoice_number: Custom invoice number (optional)
            customer_name: Customer name (optional)
            customer_phone: Customer phone (optional)
        
        Returns:
            Dict with transaction_id, qr_string, qr_image
        """
        token = self._get_access_token()
        url = f"{self.base_url}/api/v1/qris/generate"
        
        data = {
            'amount': amount,
            'merchant_id': merchant_id,
            'terminal_id': terminal_id,
            'invoice_number': invoice_number or self._generate_invoice_number(),
            'customer_name': customer_name or '',
            'customer_phone': customer_phone or '',
            'timestamp': datetime.utcnow().isoformat() + 'Z'
        }
        
        headers = {
            'Authorization': f'Bearer {token}',
            'Content-Type': 'application/json'
        }
        
        response = requests.post(url, json=data, headers=headers, verify=not self.sandbox)
        response.raise_for_status()
        
        return response.json()
    
    def check_payment_status(self, transaction_id: str) -> Dict:
        """
        Check payment status
        
        Args:
            transaction_id: Transaction ID from QR generation
        
        Returns:
            Dict with payment status data
        """
        token = self._get_access_token()
        url = f"{self.base_url}/api/v1/qris/status/{transaction_id}"
        
        headers = {
            'Authorization': f'Bearer {token}',
            'Content-Type': 'application/json'
        }
        
        response = requests.get(url, headers=headers, verify=not self.sandbox)
        response.raise_for_status()
        
        return response.json()
    
    def verify_webhook_signature(self, payload: Dict, signature: str) -> bool:
        """
        Verify webhook signature
        
        Args:
            payload: Webhook payload
            signature: Signature from webhook header
        
        Returns:
            True if signature is valid
        """
        data = json.dumps(payload, separators=(',', ':'))
        calculated_signature = hmac.new(
            self.client_secret.encode(),
            data.encode(),
            hashlib.sha256
        ).hexdigest()
        
        return hmac.compare_digest(calculated_signature, signature)
    
    def handle_webhook(self, raw_payload: str, signature: str) -> Dict:
        """
        Handle webhook request
        
        Args:
            raw_payload: Raw POST body
            signature: Signature from X-Signature header
        
        Returns:
            Parsed webhook data
        """
        try:
            payload = json.loads(raw_payload)
        except json.JSONDecodeError:
            raise ValueError('Invalid webhook payload')
        
        if not self.verify_webhook_signature(payload, signature):
            raise ValueError('Invalid webhook signature')
        
        return payload
    
    def poll_payment_status(self, transaction_id: str, 
                           max_attempts: int = 60,
                           interval_seconds: int = 5) -> Dict:
        """
        Poll payment status until completed or timeout
        
        Args:
            transaction_id: Transaction ID
            max_attempts: Maximum polling attempts
            interval_seconds: Seconds between polls
        
        Returns:
            Final payment status
        """
        attempts = 0
        
        while attempts < max_attempts:
            status = self.check_payment_status(transaction_id)
            
            if status.get('status') in ['SUCCESS', 'FAILED', 'EXPIRED']:
                return status
            
            time.sleep(interval_seconds)
            attempts += 1
        
        raise TimeoutError('Payment status polling timeout')
    
    def _generate_invoice_number(self) -> str:
        """Generate unique invoice number"""
        import random
        timestamp = datetime.now().strftime('%Y%m%d%H%M%S')
        random_num = random.randint(1000, 9999)
        return f"INV-{timestamp}-{random_num}"


__version__ = '1.0.0'
__all__ = ['MandiriQrisClient']
