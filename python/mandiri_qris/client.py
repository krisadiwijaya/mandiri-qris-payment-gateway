"""
Mandiri QRIS Payment SDK for Python
"""

import requests
import base64
import time
import json
from datetime import datetime, timedelta
from typing import Dict, Optional
from urllib.parse import urlencode


class MandiriQrisException(Exception):
    """Custom exception for Mandiri QRIS errors"""
    pass


class MandiriQrisClient:
    """
    Mandiri QRIS Payment Client
    
    Example usage:
        client = MandiriQrisClient(
            client_id='your_client_id',
            client_secret='your_client_secret',
            environment='sandbox'
        )
        
        qris = client.create_qris(
            amount=100000,
            reference='ORDER-001',
            merchant_nmid='YOUR_NMID',
            merchant_name='YOUR STORE',
            merchant_city='JAKARTA'
        )
    """
    
    SANDBOX_BASE_URL = 'https://sandbox.bankmandiri.co.id'
    PRODUCTION_BASE_URL = 'https://api.bankmandiri.co.id'
    
    def __init__(
        self,
        client_id: str,
        client_secret: str,
        environment: str = 'sandbox',
        merchant_nmid: str = None,
        merchant_name: str = None,
        merchant_city: str = None,
        qris_expiry_minutes: int = 30,
        timeout: int = 30
    ):
        """
        Initialize Mandiri QRIS Client
        
        Args:
            client_id: Mandiri API client ID
            client_secret: Mandiri API client secret
            environment: 'sandbox' or 'production'
            merchant_nmid: Default merchant NMID
            merchant_name: Default merchant name
            merchant_city: Default merchant city
            qris_expiry_minutes: QR code expiry time (5-120 minutes)
            timeout: Request timeout in seconds
        """
        self.client_id = client_id
        self.client_secret = client_secret
        self.environment = environment
        self.merchant_nmid = merchant_nmid
        self.merchant_name = merchant_name
        self.merchant_city = merchant_city
        self.qris_expiry_minutes = qris_expiry_minutes
        self.timeout = timeout
        
        self.base_url = (
            self.SANDBOX_BASE_URL if environment == 'sandbox'
            else self.PRODUCTION_BASE_URL
        )
        
        self._access_token = None
        self._token_expiry = None
    
    def _get_access_token(self) -> str:
        """
        Get B2B access token with automatic refresh
        
        Returns:
            Access token string
        """
        # Check if token is still valid (with 60 second safety margin)
        if self._access_token and self._token_expiry:
            if time.time() < (self._token_expiry - 60):
                return self._access_token
        
        # Request new token
        url = f"{self.base_url}/openapi/auth/v2.0/access-token/b2b"
        
        # Create Basic Auth header
        auth_string = f"{self.client_id}:{self.client_secret}"
        auth_bytes = auth_string.encode('ascii')
        auth_b64 = base64.b64encode(auth_bytes).decode('ascii')
        
        headers = {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Authorization': f'Basic {auth_b64}'
        }
        
        data = {'grant_type': 'client_credentials'}
        
        try:
            response = requests.post(
                url,
                headers=headers,
                data=data,
                timeout=self.timeout
            )
            response.raise_for_status()
            
            result = response.json()
            
            self._access_token = result['access_token']
            expires_in = result.get('expires_in', 1800)
            self._token_expiry = time.time() + expires_in
            
            return self._access_token
            
        except requests.exceptions.RequestException as e:
            raise MandiriQrisException(f"Failed to get access token: {str(e)}")
    
    def create_qris(
        self,
        amount: float,
        reference: str,
        merchant_nmid: str = None,
        merchant_name: str = None,
        merchant_city: str = None,
        callback_url: str = None
    ) -> Dict:
        """
        Create QRIS dynamic code
        
        Args:
            amount: Payment amount
            reference: Unique reference/order ID
            merchant_nmid: Merchant NMID (uses default if not provided)
            merchant_name: Merchant name (uses default if not provided)
            merchant_city: Merchant city (uses default if not provided)
            callback_url: Webhook callback URL
        
        Returns:
            Dictionary containing QR code data
        """
        if amount <= 0:
            raise MandiriQrisException("Amount must be greater than 0")
        
        if not reference:
            raise MandiriQrisException("Reference is required")
        
        # Use provided values or defaults
        nmid = merchant_nmid or self.merchant_nmid
        name = merchant_name or self.merchant_name
        city = merchant_city or self.merchant_city
        
        if not all([nmid, name, city]):
            raise MandiriQrisException(
                "Merchant NMID, name, and city are required"
            )
        
        # Get access token
        token = self._get_access_token()
        
        # Prepare payload
        payload = {
            'type': 'DYNAMIC',
            'amount': float(amount),
            'currency': 'IDR',
            'reference': reference,
            'merchant_nmid': nmid,
            'merchant_name': name,
            'merchant_city': city
        }
        
        if callback_url:
            payload['callback_url'] = callback_url
        
        # Make request
        url = f"{self.base_url}/openapi/qris/v2.0/qr-code"
        headers = {
            'Content-Type': 'application/json',
            'Authorization': f'Bearer {token}'
        }
        
        try:
            response = requests.post(
                url,
                headers=headers,
                json=payload,
                timeout=self.timeout
            )
            response.raise_for_status()
            
            result = response.json()
            
            # Generate QR image URL
            qr_string = result['qr_string']
            qr_image_url = self._generate_qr_image_url(qr_string)
            
            # Calculate expiry time
            expired_at = datetime.now() + timedelta(minutes=self.qris_expiry_minutes)
            
            return {
                'qr_id': result['qr_id'],
                'qr_string': qr_string,
                'qr_image_url': qr_image_url,
                'status': result.get('status', 'ACTIVE'),
                'amount': amount,
                'reference': reference,
                'expired_at': expired_at.strftime('%Y-%m-%d %H:%M:%S')
            }
            
        except requests.exceptions.RequestException as e:
            raise MandiriQrisException(f"Failed to create QRIS: {str(e)}")
    
    def check_status(self, qr_id: str) -> Dict:
        """
        Check QRIS payment status
        
        Args:
            qr_id: QR code ID
        
        Returns:
            Dictionary containing payment status
        """
        if not qr_id:
            raise MandiriQrisException("QR ID is required")
        
        # Get access token
        token = self._get_access_token()
        
        # Make request
        url = f"{self.base_url}/openapi/qris/v2.0/qr-code/status/{qr_id}"
        headers = {
            'Authorization': f'Bearer {token}'
        }
        
        try:
            response = requests.get(
                url,
                headers=headers,
                timeout=self.timeout
            )
            response.raise_for_status()
            
            result = response.json()
            
            return {
                'qr_id': qr_id,
                'status': result.get('status', 'UNKNOWN'),
                'amount': result.get('amount'),
                'paid_at': result.get('paid_at'),
                'transaction_id': result.get('transaction_id')
            }
            
        except requests.exceptions.RequestException as e:
            raise MandiriQrisException(f"Failed to check status: {str(e)}")
    
    def _generate_qr_image_url(self, qr_string: str) -> str:
        """Generate QR code image URL"""
        size = '300x300'
        return f"https://api.qrserver.com/v1/create-qr-code/?size={size}&data={urlencode({'': qr_string})[1:]}"
    
    def clear_token(self):
        """Clear cached access token"""
        self._access_token = None
        self._token_expiry = None
