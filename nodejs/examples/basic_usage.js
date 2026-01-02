const { MandiriQrisClient } = require('../src');

// Initialize client
const client = new MandiriQrisClient({
    clientId: process.env.MANDIRI_CLIENT_ID || 'your_client_id',
    clientSecret: process.env.MANDIRI_CLIENT_SECRET || 'your_client_secret',
    baseUrl: process.env.MANDIRI_BASE_URL || 'https://api.mandiri.co.id',
    sandbox: true
});

async function main() {
    try {
        // Generate QR Code
        console.log('Generating QR Code...');
        const qr = await client.generateQR({
            amount: 100000,
            merchantId: 'MERCHANT123',
            terminalId: 'TERM001',
            customerName: 'John Doe',
            customerPhone: '081234567890'
        });
        
        console.log('QR Generated Successfully!');
        console.log('Transaction ID:', qr.transaction_id);
        console.log('QR String:', qr.qr_string);
        console.log('');
        
        // Check payment status
        console.log('Checking payment status...');
        const status = await client.checkPaymentStatus(qr.transaction_id);
        console.log('Status:', status.status);
        console.log('Amount:', status.amount);
        console.log('');
        
        // Poll payment status (optional)
        console.log('Polling payment status (will wait up to 5 minutes)...');
        const finalStatus = await client.pollPaymentStatus(qr.transaction_id, 60, 5);
        console.log('Final Status:', finalStatus.status);
        
    } catch (error) {
        console.error('Error:', error.message);
    }
}

main();
