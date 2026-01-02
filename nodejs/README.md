# Node.js SDK for Mandiri QRIS Payment Gateway

Node.js SDK for integrating Mandiri QRIS Payment Gateway with OAuth 2.0 authentication.

## Installation

```bash
npm install mandiri-qris
```

## Requirements

- Node.js >= 14.0.0
- axios

## Quick Start

```javascript
const { MandiriQrisClient } = require('mandiri-qris');

// Initialize client
const client = new MandiriQrisClient({
    clientId: 'your_client_id',
    clientSecret: 'your_client_secret',
    baseUrl: 'https://api.mandiri.co.id',
    sandbox: true
});

// Generate QR Code
const qr = await client.generateQR({
    amount: 100000,
    merchantId: 'MERCHANT123',
    terminalId: 'TERM001',
    customerName: 'John Doe',
    customerPhone: '081234567890'
});

console.log('Transaction ID:', qr.transaction_id);
console.log('QR String:', qr.qr_string);

// Check payment status
const status = await client.checkPaymentStatus(qr.transaction_id);
console.log('Payment Status:', status.status);
```

## TypeScript Support

```typescript
import { MandiriQrisClient, QRParams } from 'mandiri-qris';

const client = new MandiriQrisClient({
    clientId: 'your_client_id',
    clientSecret: 'your_client_secret',
    sandbox: true
});

const params: QRParams = {
    amount: 100000,
    merchantId: 'MERCHANT123',
    terminalId: 'TERM001'
};

const qr = await client.generateQR(params);
```

## Webhook Handler (Express)

```javascript
const express = require('express');
const { MandiriQrisClient } = require('mandiri-qris');

const app = express();
app.use(express.raw({ type: 'application/json' }));

const client = new MandiriQrisClient({...});

app.post('/webhook/mandiri-qris', (req, res) => {
    try {
        const rawPayload = req.body.toString('utf-8');
        const signature = req.headers['x-signature'] || '';
        
        const payload = client.handleWebhook(rawPayload, signature);
        
        if (payload.status === 'SUCCESS') {
            // Payment successful
            // Update database, send confirmation, etc.
        }
        
        res.json({ status: 'ok' });
    } catch (error) {
        res.status(400).json({ error: error.message });
    }
});

app.listen(3000);
```

## Payment Polling

```javascript
// Poll every 5 seconds for up to 5 minutes
const finalStatus = await client.pollPaymentStatus(transactionId, 60, 5);

if (finalStatus.status === 'SUCCESS') {
    console.log('Payment completed!');
}
```

## Examples

See the [examples](examples/) directory for complete examples.

## License

MIT
