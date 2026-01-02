/**
 * Express.js Example - Mandiri QRIS Payment
 */

const express = require('express');
const { MandiriQris } = require('./MandiriQris');
require('dotenv').config();

const app = express();

// Middleware
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Serve static files
app.use(express.static('public'));

// View engine (optional)
app.set('view engine', 'ejs');
app.set('views', './views');

// Initialize Mandiri QRIS client
const mandiriClient = new MandiriQris({
    clientId: process.env.MANDIRI_CLIENT_ID,
    clientSecret: process.env.MANDIRI_CLIENT_SECRET,
    environment: process.env.MANDIRI_ENV || 'sandbox',
    merchantNmid: process.env.MANDIRI_MERCHANT_NMID,
    merchantName: process.env.MANDIRI_MERCHANT_NAME,
    merchantCity: process.env.MANDIRI_MERCHANT_CITY
});

// In-memory storage (replace with database in production)
const payments = new Map();

// Routes

/**
 * Home page
 */
app.get('/', (req, res) => {
    res.send(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Mandiri QRIS Payment Demo</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body>
            <div class="container py-5">
                <h1 class="text-center mb-4">Mandiri QRIS Payment Demo</h1>
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Create Payment</h5>
                                <form id="payment-form">
                                    <div class="mb-3">
                                        <label class="form-label">Amount (Rp)</label>
                                        <input type="number" class="form-control" name="amount" value="100000" required min="10000">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Order ID</label>
                                        <input type="text" class="form-control" name="orderId" value="ORDER-${Date.now()}" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">Create QR Code</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
                document.getElementById('payment-form').addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const formData = new FormData(e.target);
                    const data = Object.fromEntries(formData);
                    
                    try {
                        const response = await fetch('/api/qris/create', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(data)
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            window.location.href = '/payment/' + result.data.reference;
                        } else {
                            alert('Error: ' + result.error);
                        }
                    } catch (error) {
                        alert('Error: ' + error.message);
                    }
                });
            </script>
        </body>
        </html>
    `);
});

/**
 * Create QRIS endpoint
 */
app.post('/api/qris/create', async (req, res) => {
    try {
        const { amount, orderId } = req.body;

        // Validate input
        if (!amount || !orderId) {
            return res.status(400).json({
                success: false,
                error: 'Amount and orderId are required'
            });
        }

        if (parseFloat(amount) < 10000) {
            return res.status(400).json({
                success: false,
                error: 'Minimum amount is Rp 10,000'
            });
        }

        // Create QRIS
        const qris = await mandiriClient.createQris({
            amount: parseFloat(amount),
            reference: orderId,
            callbackUrl: `${req.protocol}://${req.get('host')}/api/qris/webhook`
        });

        // Store payment data (replace with database)
        payments.set(orderId, {
            ...qris,
            status: 'pending',
            createdAt: new Date()
        });

        console.log(`[QRIS Created] Order ID: ${orderId}, QR ID: ${qris.qrId}`);

        res.json({
            success: true,
            data: qris
        });

    } catch (error) {
        console.error('Create QRIS error:', error);
        res.status(error.statusCode || 500).json({
            success: false,
            error: error.message
        });
    }
});

/**
 * Check payment status endpoint
 */
app.get('/api/qris/status/:qrId', async (req, res) => {
    try {
        const { qrId } = req.params;

        const status = await mandiriClient.checkStatus(qrId);

        // Update local storage if paid
        if (status.status === 'COMPLETED') {
            for (const [orderId, payment] of payments.entries()) {
                if (payment.qrId === qrId) {
                    payments.set(orderId, {
                        ...payment,
                        status: 'paid',
                        paidAt: new Date()
                    });
                    console.log(`[Payment Completed] Order ID: ${orderId}, QR ID: ${qrId}`);
                    break;
                }
            }
        }

        res.json({
            success: true,
            data: status
        });

    } catch (error) {
        console.error('Check status error:', error);
        res.status(error.statusCode || 500).json({
            success: false,
            error: error.message
        });
    }
});

/**
 * Webhook endpoint
 */
app.post('/api/qris/webhook', async (req, res) => {
    try {
        const payload = req.body;

        console.log('[Webhook Received]', JSON.stringify(payload, null, 2));

        if (payload.status === 'COMPLETED') {
            const qrId = payload.qr_id;

            // Find and update payment
            for (const [orderId, payment] of payments.entries()) {
                if (payment.qrId === qrId && payment.status === 'pending') {
                    payments.set(orderId, {
                        ...payment,
                        status: 'paid',
                        paidAt: new Date(),
                        transactionId: payload.transaction_id
                    });

                    console.log(`[Webhook Processed] Order ID: ${orderId}, Status: PAID`);

                    // Here you can:
                    // - Send email notification
                    // - Update order status
                    // - Trigger fulfillment
                    // - etc.

                    break;
                }
            }
        }

        res.json({ status: 'ok' });

    } catch (error) {
        console.error('Webhook error:', error);
        res.status(500).json({ error: error.message });
    }
});

/**
 * Payment page
 */
app.get('/payment/:orderId', (req, res) => {
    const { orderId } = req.params;
    const payment = payments.get(orderId);

    if (!payment) {
        return res.status(404).send('Payment not found');
    }

    res.send(`
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>QRIS Payment - ${orderId}</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                .timer { font-size: 2rem; font-weight: bold; color: #0d6efd; }
                .qr-code { max-width: 300px; border: 2px solid #dee2e6; padding: 20px; border-radius: 10px; }
            </style>
        </head>
        <body>
            <div class="container py-5">
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="card shadow">
                            <div class="card-header bg-primary text-white">
                                <h4 class="mb-0 text-center">Scan QR Code untuk Pembayaran</h4>
                                <div id="timer" class="timer text-center mt-2">30:00</div>
                            </div>
                            <div class="card-body text-center">
                                <img src="${payment.qrImageUrl}" alt="QR Code" class="qr-code mb-4">
                                
                                <div class="payment-info bg-light p-3 rounded mb-3">
                                    <p class="mb-2"><strong>Total Pembayaran:</strong> <span class="fs-5">Rp ${parseFloat(payment.amount).toLocaleString('id-ID')}</span></p>
                                    <p class="mb-2"><strong>Order ID:</strong> ${orderId}</p>
                                    <p class="mb-0"><strong>Status:</strong> <span id="status-badge" class="badge bg-warning">Menunggu Pembayaran</span></p>
                                </div>
                                
                                <div id="loading" class="mb-3">
                                    <div class="spinner-border text-primary mb-2" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="text-muted">Menunggu pembayaran...</p>
                                </div>
                                
                                <div class="alert alert-info text-start">
                                    <strong>📱 Cara Pembayaran:</strong>
                                    <ol class="mt-2 mb-0">
                                        <li>Buka aplikasi mobile banking atau e-wallet Anda</li>
                                        <li>Pilih menu <strong>QRIS / Scan QR</strong></li>
                                        <li>Scan kode QR di atas</li>
                                        <li>Konfirmasi pembayaran</li>
                                        <li>Tunggu notifikasi pembayaran berhasil</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                const qrId = '${payment.qrId}';
                let remainingSeconds = 30 * 60;
                let pollInterval, timerInterval;

                // Timer countdown
                timerInterval = setInterval(() => {
                    remainingSeconds--;
                    const minutes = Math.floor(remainingSeconds / 60);
                    const seconds = remainingSeconds % 60;
                    document.getElementById('timer').textContent = 
                        minutes + ':' + seconds.toString().padStart(2, '0');
                    
                    if (remainingSeconds <= 0) {
                        clearInterval(timerInterval);
                        clearInterval(pollInterval);
                        showExpired();
                    }
                }, 1000);

                // Poll payment status
                pollInterval = setInterval(checkPaymentStatus, 3000);

                async function checkPaymentStatus() {
                    try {
                        const response = await fetch('/api/qris/status/' + qrId);
                        const data = await response.json();
                        
                        if (data.success && data.data.status === 'COMPLETED') {
                            clearInterval(pollInterval);
                            clearInterval(timerInterval);
                            showSuccess();
                        }
                    } catch (error) {
                        console.error('Error checking status:', error);
                    }
                }

                function showSuccess() {
                    document.getElementById('status-badge').textContent = 'Lunas';
                    document.getElementById('status-badge').className = 'badge bg-success';
                    document.getElementById('loading').innerHTML = 
                        '<div class="alert alert-success"><strong>✅ Pembayaran Berhasil!</strong><br>Terima kasih atas pembayaran Anda.</div>';
                    setTimeout(() => {
                        window.location.href = '/payment/success?orderId=${orderId}';
                    }, 2000);
                }

                function showExpired() {
                    document.getElementById('status-badge').textContent = 'Expired';
                    document.getElementById('status-badge').className = 'badge bg-danger';
                    document.getElementById('loading').innerHTML = 
                        '<div class="alert alert-danger"><strong>⏱ QR Code Expired</strong><br>Silakan buat pembayaran baru.</div>';
                }
            </script>
        </body>
        </html>
    `);
});

/**
 * Success page
 */
app.get('/payment/success', (req, res) => {
    const { orderId } = req.query;
    const payment = orderId ? payments.get(orderId) : null;

    res.send(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Payment Success</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body>
            <div class="container py-5">
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="card text-center">
                            <div class="card-body py-5">
                                <div class="mb-4" style="font-size: 80px;">✅</div>
                                <h2 class="text-success mb-3">Pembayaran Berhasil!</h2>
                                ${payment ? `
                                    <div class="payment-details bg-light p-3 rounded mb-3">
                                        <p><strong>Order ID:</strong> ${orderId}</p>
                                        <p><strong>Jumlah:</strong> Rp ${parseFloat(payment.amount).toLocaleString('id-ID')}</p>
                                        <p><strong>Status:</strong> <span class="badge bg-success">LUNAS</span></p>
                                    </div>
                                ` : ''}
                                <p class="text-muted">Terima kasih atas pembayaran Anda.</p>
                                <a href="/" class="btn btn-primary mt-3">Kembali ke Beranda</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
    `);
});

// Error handler
app.use((error, req, res, next) => {
    console.error('Error:', error);
    res.status(error.statusCode || 500).json({
        success: false,
        error: error.message || 'Internal server error'
    });
});

// Start server
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`Server running on http://localhost:${PORT}`);
    console.log(`Environment: ${process.env.MANDIRI_ENV || 'sandbox'}`);
    console.log(`Press Ctrl+C to stop\n`);
});

module.exports = app;
