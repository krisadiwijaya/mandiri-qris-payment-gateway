const express = require('express');
const { MandiriQrisClient } = require('../src');

const app = express();
app.use(express.raw({ type: 'application/json' }));

// Initialize client
const client = new MandiriQrisClient({
    clientId: process.env.MANDIRI_CLIENT_ID || 'your_client_id',
    clientSecret: process.env.MANDIRI_CLIENT_SECRET || 'your_client_secret',
    sandbox: true
});

// Webhook endpoint
app.post('/webhook/mandiri-qris', (req, res) => {
    try {
        const rawPayload = req.body.toString('utf-8');
        const signature = req.headers['x-signature'] || '';
        
        const payload = client.handleWebhook(rawPayload, signature);
        
        console.log('Webhook received successfully!');
        console.log('Transaction ID:', payload.transaction_id);
        console.log('Status:', payload.status);
        console.log('Amount:', payload.amount);
        
        // Process payment based on status
        if (payload.status === 'SUCCESS') {
            // Update database, send confirmation email, etc.
            console.log('Payment successful - updating database...');
        } else if (payload.status === 'FAILED') {
            // Handle failed payment
            console.log('Payment failed - notifying customer...');
        }
        
        res.json({ status: 'ok' });
    } catch (error) {
        console.error('Webhook error:', error.message);
        res.status(400).json({ error: error.message });
    }
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`Webhook server listening on port ${PORT}`);
});
