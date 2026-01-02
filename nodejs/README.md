# Node.js - Mandiri QRIS Payment SDK

Node.js SDK for Mandiri QRIS Payment Gateway integration with Express.js examples.

## 📋 Requirements

- Node.js 14.x or higher
- npm or yarn
- Express.js (for examples)

## 🚀 Installation

### Using npm

```bash
npm install mandiri-qris
```

### Using yarn

```bash
yarn add mandiri-qris
```

### From source

```bash
git clone https://github.com/yourusername/mandiri-qris-api.git
cd mandiri-qris-api/nodejs
npm install
```

## ⚙️ Configuration

Create a `.env` file:

```env
# Mandiri QRIS Configuration
MANDIRI_ENV=sandbox
MANDIRI_CLIENT_ID=your_client_id
MANDIRI_CLIENT_SECRET=your_client_secret
MANDIRI_MERCHANT_NMID=YOUR_NMID
MANDIRI_MERCHANT_NAME=YOUR MERCHANT NAME
MANDIRI_MERCHANT_CITY=JAKARTA
QRIS_EXPIRY_MINUTES=30

# Server Configuration
PORT=3000
NODE_ENV=development

# Database (optional)
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=
DB_NAME=mandiri_qris
```

## 📝 Basic Usage

### CommonJS (require)

```javascript
const { MandiriQris } = require('mandiri-qris');

const client = new MandiriQris({
    clientId: process.env.MANDIRI_CLIENT_ID,
    clientSecret: process.env.MANDIRI_CLIENT_SECRET,
    environment: 'sandbox',
    merchantNmid: process.env.MANDIRI_MERCHANT_NMID,
    merchantName: process.env.MANDIRI_MERCHANT_NAME,
    merchantCity: process.env.MANDIRI_MERCHANT_CITY
});

// Create QRIS
async function createPayment() {
    try {
        const qris = await client.createQris({
            amount: 100000,
            reference: 'ORDER-' + Date.now()
        });
        
        console.log('QR ID:', qris.qrId);
        console.log('QR Image:', qris.qrImageUrl);
        return qris;
    } catch (error) {
        console.error('Error:', error.message);
    }
}

// Check status
async function checkPayment(qrId) {
    try {
        const status = await client.checkStatus(qrId);
        console.log('Status:', status.status);
        return status;
    } catch (error) {
        console.error('Error:', error.message);
    }
}

createPayment();
```

### ES6 (import)

```javascript
import { MandiriQris } from 'mandiri-qris';

const client = new MandiriQris({
    clientId: process.env.MANDIRI_CLIENT_ID,
    clientSecret: process.env.MANDIRI_CLIENT_SECRET,
    environment: 'sandbox'
});

// Use async/await
const qris = await client.createQris({
    amount: 100000,
    reference: 'ORDER-001'
});
```

## 🌐 Express.js Example

### Basic Server

```javascript
const express = require('express');
const { MandiriQris } = require('mandiri-qris');
require('dotenv').config();

const app = express();
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Initialize Mandiri QRIS client
const mandiriClient = new MandiriQris({
    clientId: process.env.MANDIRI_CLIENT_ID,
    clientSecret: process.env.MANDIRI_CLIENT_SECRET,
    environment: process.env.MANDIRI_ENV,
    merchantNmid: process.env.MANDIRI_MERCHANT_NMID,
    merchantName: process.env.MANDIRI_MERCHANT_NAME,
    merchantCity: process.env.MANDIRI_MERCHANT_CITY
});

// Create QRIS endpoint
app.post('/api/qris/create', async (req, res) => {
    try {
        const { amount, orderId } = req.body;
        
        if (!amount || !orderId) {
            return res.status(400).json({
                success: false,
                error: 'Amount and orderId are required'
            });
        }
        
        const qris = await mandiriClient.createQris({
            amount: parseFloat(amount),
            reference: orderId,
            callbackUrl: `${req.protocol}://${req.get('host')}/api/qris/webhook`
        });
        
        // Save to database (optional)
        // await db.payments.create({ orderId, qrId: qris.qrId, ... });
        
        res.json({
            success: true,
            data: qris
        });
        
    } catch (error) {
        console.error('Create QRIS error:', error);
        res.status(500).json({
            success: false,
            error: error.message
        });
    }
});

// Check status endpoint
app.get('/api/qris/status/:qrId', async (req, res) => {
    try {
        const { qrId } = req.params;
        
        const status = await mandiriClient.checkStatus(qrId);
        
        // Update database if paid
        if (status.status === 'COMPLETED') {
            // await db.payments.update({ qrId }, { status: 'paid', paidAt: new Date() });
        }
        
        res.json({
            success: true,
            data: status
        });
        
    } catch (error) {
        console.error('Check status error:', error);
        res.status(500).json({
            success: false,
            error: error.message
        });
    }
});

// Webhook endpoint
app.post('/api/qris/webhook', async (req, res) => {
    try {
        const payload = req.body;
        
        console.log('Webhook received:', payload);
        
        if (payload.status === 'COMPLETED') {
            // Update database
            // await db.payments.update({ qrId: payload.qrId }, { 
            //     status: 'paid', 
            //     paidAt: new Date() 
            // });
            
            // Send notification
            // await sendPaymentNotification(payload);
        }
        
        res.json({ status: 'ok' });
        
    } catch (error) {
        console.error('Webhook error:', error);
        res.status(500).json({ error: error.message });
    }
});

// Payment page
app.get('/payment/:orderId', async (req, res) => {
    try {
        const { orderId } = req.params;
        
        // Get payment from database
        // const payment = await db.payments.findOne({ orderId });
        
        res.render('payment', { 
            payment: {
                orderId,
                amount: 100000,
                qrImageUrl: 'https://...',
                qrId: 'QR123'
            }
        });
        
    } catch (error) {
        res.status(500).send('Error loading payment page');
    }
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`Server running on port ${PORT}`);
    console.log(`Environment: ${process.env.MANDIRI_ENV}`);
});
```

### With TypeScript

```typescript
import express, { Request, Response } from 'express';
import { MandiriQris, QrisRequest, QrisResponse } from 'mandiri-qris';

const app = express();
app.use(express.json());

const client = new MandiriQris({
    clientId: process.env.MANDIRI_CLIENT_ID!,
    clientSecret: process.env.MANDIRI_CLIENT_SECRET!,
    environment: 'sandbox',
    merchantNmid: process.env.MANDIRI_MERCHANT_NMID!,
    merchantName: process.env.MANDIRI_MERCHANT_NAME!,
    merchantCity: process.env.MANDIRI_MERCHANT_CITY!
});

interface CreateQrisBody {
    amount: number;
    orderId: string;
}

app.post('/api/qris/create', async (req: Request<{}, {}, CreateQrisBody>, res: Response) => {
    try {
        const { amount, orderId } = req.body;
        
        const qris: QrisResponse = await client.createQris({
            amount,
            reference: orderId
        });
        
        res.json({ success: true, data: qris });
        
    } catch (error) {
        res.status(500).json({ 
            success: false, 
            error: (error as Error).message 
        });
    }
});
```

## 🗄️ Database Integration

### MongoDB (Mongoose)

```javascript
const mongoose = require('mongoose');

const paymentSchema = new mongoose.Schema({
    paymentId: { type: String, required: true, unique: true },
    orderId: { type: String, required: true },
    userId: { type: mongoose.Schema.Types.ObjectId, ref: 'User' },
    amount: { type: Number, required: true },
    paymentMethod: { type: String, default: 'qris' },
    status: { 
        type: String, 
        enum: ['pending', 'paid', 'expired', 'failed'],
        default: 'pending'
    },
    qrId: String,
    qrString: String,
    qrImageUrl: String,
    transactionId: String,
    expiredAt: Date,
    paidAt: Date
}, { timestamps: true });

const Payment = mongoose.model('Payment', paymentSchema);

// Usage
app.post('/api/qris/create', async (req, res) => {
    const { amount, orderId } = req.body;
    
    const qris = await mandiriClient.createQris({ amount, reference: orderId });
    
    const payment = new Payment({
        paymentId: `PAY-${orderId}`,
        orderId,
        userId: req.user.id,
        amount,
        qrId: qris.qrId,
        qrString: qris.qrString,
        qrImageUrl: qris.qrImageUrl,
        expiredAt: qris.expiredAt
    });
    
    await payment.save();
    
    res.json({ success: true, data: payment });
});
```

### MySQL (Sequelize)

```javascript
const { Sequelize, DataTypes } = require('sequelize');

const sequelize = new Sequelize(
    process.env.DB_NAME,
    process.env.DB_USER,
    process.env.DB_PASSWORD,
    {
        host: process.env.DB_HOST,
        dialect: 'mysql'
    }
);

const Payment = sequelize.define('Payment', {
    id: {
        type: DataTypes.INTEGER,
        primaryKey: true,
        autoIncrement: true
    },
    paymentId: {
        type: DataTypes.STRING(100),
        unique: true,
        allowNull: false
    },
    orderId: {
        type: DataTypes.STRING(100),
        allowNull: false
    },
    userId: {
        type: DataTypes.INTEGER,
        allowNull: true
    },
    amount: {
        type: DataTypes.DECIMAL(15, 2),
        allowNull: false
    },
    paymentMethod: {
        type: DataTypes.STRING(50),
        defaultValue: 'qris'
    },
    status: {
        type: DataTypes.ENUM('pending', 'paid', 'expired', 'failed'),
        defaultValue: 'pending'
    },
    qrId: DataTypes.STRING(255),
    qrString: DataTypes.TEXT,
    qrImageUrl: DataTypes.STRING(500),
    transactionId: DataTypes.STRING(255),
    expiredAt: DataTypes.DATE,
    paidAt: DataTypes.DATE
}, {
    tableName: 'payments',
    timestamps: true
});

// Usage
app.post('/api/qris/create', async (req, res) => {
    const { amount, orderId } = req.body;
    
    const qris = await mandiriClient.createQris({ amount, reference: orderId });
    
    const payment = await Payment.create({
        paymentId: `PAY-${orderId}`,
        orderId,
        amount,
        qrId: qris.qrId,
        qrString: qris.qrString,
        qrImageUrl: qris.qrImageUrl,
        expiredAt: qris.expiredAt
    });
    
    res.json({ success: true, data: payment });
});
```

### PostgreSQL (Prisma)

```javascript
// schema.prisma
model Payment {
  id            Int       @id @default(autoincrement())
  paymentId     String    @unique @db.VarChar(100)
  orderId       String    @db.VarChar(100)
  userId        Int?
  amount        Decimal   @db.Decimal(15, 2)
  paymentMethod String    @default("qris") @db.VarChar(50)
  status        Status    @default(pending)
  qrId          String?   @db.VarChar(255)
  qrString      String?   @db.Text
  qrImageUrl    String?   @db.VarChar(500)
  transactionId String?   @db.VarChar(255)
  expiredAt     DateTime?
  paidAt        DateTime?
  createdAt     DateTime  @default(now())
  updatedAt     DateTime  @updatedAt

  @@map("payments")
}

enum Status {
  pending
  paid
  expired
  failed
}

// Usage
const { PrismaClient } = require('@prisma/client');
const prisma = new PrismaClient();

app.post('/api/qris/create', async (req, res) => {
    const { amount, orderId } = req.body;
    
    const qris = await mandiriClient.createQris({ amount, reference: orderId });
    
    const payment = await prisma.payment.create({
        data: {
            paymentId: `PAY-${orderId}`,
            orderId,
            amount,
            qrId: qris.qrId,
            qrString: qris.qrString,
            qrImageUrl: qris.qrImageUrl,
            expiredAt: new Date(qris.expiredAt)
        }
    });
    
    res.json({ success: true, data: payment });
});
```

## 🎨 Frontend Integration

### HTML + Vanilla JavaScript

```html
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QRIS Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Scan QR Code</h4>
                        <div id="timer" class="text-center fs-3">30:00</div>
                    </div>
                    <div class="card-body text-center">
                        <img id="qr-image" src="" alt="QR Code" class="img-fluid mb-3" style="max-width: 300px;">
                        
                        <div class="payment-info bg-light p-3 rounded mb-3">
                            <p><strong>Amount:</strong> <span id="amount"></span></p>
                            <p><strong>Order ID:</strong> <span id="order-id"></span></p>
                            <p><strong>Status:</strong> <span id="status-badge" class="badge bg-warning">Pending</span></p>
                        </div>
                        
                        <div id="loading" class="mb-3">
                            <div class="spinner-border text-primary"></div>
                            <p>Waiting for payment...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const urlParams = new URLSearchParams(window.location.search);
        const qrId = urlParams.get('qrId');
        let remainingSeconds = 30 * 60;
        
        // Load payment data
        fetch(`/api/qris/status/${qrId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('qr-image').src = data.data.qrImageUrl;
                    document.getElementById('amount').textContent = 'Rp ' + data.data.amount.toLocaleString('id-ID');
                    document.getElementById('order-id').textContent = data.data.orderId;
                }
            });
        
        // Timer countdown
        const timerInterval = setInterval(() => {
            remainingSeconds--;
            const minutes = Math.floor(remainingSeconds / 60);
            const seconds = remainingSeconds % 60;
            document.getElementById('timer').textContent = 
                `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            if (remainingSeconds <= 0) {
                clearInterval(timerInterval);
                clearInterval(pollInterval);
                showExpired();
            }
        }, 1000);
        
        // Poll payment status
        const pollInterval = setInterval(() => {
            fetch(`/api/qris/status/${qrId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.data.status === 'COMPLETED') {
                        clearInterval(pollInterval);
                        clearInterval(timerInterval);
                        showSuccess();
                    }
                });
        }, 3000);
        
        function showSuccess() {
            document.getElementById('status-badge').textContent = 'Paid';
            document.getElementById('status-badge').className = 'badge bg-success';
            document.getElementById('loading').innerHTML = 
                '<div class="alert alert-success">✓ Payment Successful!</div>';
            setTimeout(() => window.location.href = '/payment/success', 2000);
        }
        
        function showExpired() {
            document.getElementById('status-badge').textContent = 'Expired';
            document.getElementById('status-badge').className = 'badge bg-danger';
            document.getElementById('loading').innerHTML = 
                '<div class="alert alert-danger">QR Code Expired</div>';
        }
    </script>
</body>
</html>
```

### React.js

```jsx
import React, { useState, useEffect } from 'react';
import axios from 'axios';

function QrisPayment({ orderId }) {
    const [payment, setPayment] = useState(null);
    const [status, setStatus] = useState('pending');
    const [timer, setTimer] = useState(30 * 60);

    useEffect(() => {
        loadPayment();
        const timerInterval = setInterval(() => {
            setTimer(prev => prev > 0 ? prev - 1 : 0);
        }, 1000);
        
        const pollInterval = setInterval(checkStatus, 3000);
        
        return () => {
            clearInterval(timerInterval);
            clearInterval(pollInterval);
        };
    }, []);

    const loadPayment = async () => {
        try {
            const { data } = await axios.get(`/api/qris/status/${orderId}`);
            setPayment(data.data);
        } catch (error) {
            console.error('Error loading payment:', error);
        }
    };

    const checkStatus = async () => {
        if (!payment) return;
        
        try {
            const { data } = await axios.get(`/api/qris/status/${payment.qrId}`);
            setStatus(data.data.status);
            
            if (data.data.status === 'COMPLETED') {
                setTimeout(() => window.location.href = '/success', 2000);
            }
        } catch (error) {
            console.error('Error checking status:', error);
        }
    };

    const formatTime = (seconds) => {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    };

    if (!payment) return <div>Loading...</div>;

    return (
        <div className="card">
            <div className="card-header">
                <h4>Scan QR Code</h4>
                <div className="timer">{formatTime(timer)}</div>
            </div>
            <div className="card-body text-center">
                <img src={payment.qrImageUrl} alt="QR Code" className="qr-code" />
                <div className="payment-info">
                    <p>Amount: Rp {payment.amount.toLocaleString('id-ID')}</p>
                    <p>Order ID: {payment.orderId}</p>
                    <p>Status: <span className={`badge ${status === 'COMPLETED' ? 'bg-success' : 'bg-warning'}`}>
                        {status}
                    </span></p>
                </div>
                {status === 'pending' && (
                    <div className="spinner-border text-primary"></div>
                )}
                {status === 'COMPLETED' && (
                    <div className="alert alert-success">Payment Successful!</div>
                )}
            </div>
        </div>
    );
}

export default QrisPayment;
```

## 📚 API Reference

### Class: MandiriQris

#### Constructor

```javascript
new MandiriQris(options)
```

**Parameters:**
- `clientId` (string, required): Mandiri API client ID
- `clientSecret` (string, required): Mandiri API client secret
- `environment` (string, optional): 'sandbox' or 'production' (default: 'sandbox')
- `merchantNmid` (string, required): Merchant NMID
- `merchantName` (string, required): Merchant name
- `merchantCity` (string, required): Merchant city
- `qrisExpiryMinutes` (number, optional): QR expiry time (default: 30)

#### Methods

##### createQris(options)

Create a new QRIS payment code.

```javascript
const qris = await client.createQris({
    amount: 100000,
    reference: 'ORDER-001',
    callbackUrl: 'https://yoursite.com/webhook'
});
```

**Returns:** Promise<QrisResponse>

##### checkStatus(qrId)

Check payment status.

```javascript
const status = await client.checkStatus('QR123456789');
```

**Returns:** Promise<PaymentStatus>

##### getAccessToken()

Get or refresh access token (called automatically).

```javascript
const token = await client.getAccessToken();
```

**Returns:** Promise<string>

## 🧪 Testing

### Unit Tests (Jest)

```javascript
const { MandiriQris } = require('mandiri-qris');

describe('MandiriQris', () => {
    let client;

    beforeEach(() => {
        client = new MandiriQris({
            clientId: 'test_client_id',
            clientSecret: 'test_secret',
            environment: 'sandbox',
            merchantNmid: 'TEST123',
            merchantName: 'Test Store',
            merchantCity: 'Jakarta'
        });
    });

    test('should create QRIS', async () => {
        const qris = await client.createQris({
            amount: 100000,
            reference: 'TEST-001'
        });

        expect(qris).toHaveProperty('qrId');
        expect(qris).toHaveProperty('qrString');
        expect(qris).toHaveProperty('qrImageUrl');
    });

    test('should check status', async () => {
        const status = await client.checkStatus('QR123');
        expect(status).toHaveProperty('status');
    });
});
```

Run tests:
```bash
npm test
```

## 🚀 Build & Run

```bash
# Install dependencies
npm install

# Development mode
npm run dev

# Production mode
npm start

# Run with nodemon (auto-reload)
npm run watch

# Run tests
npm test

# Build TypeScript (if using TS)
npm run build
```

## 📦 Package Scripts

```json
{
  "scripts": {
    "start": "node src/index.js",
    "dev": "nodemon src/index.js",
    "watch": "nodemon --watch src src/index.js",
    "test": "jest",
    "test:watch": "jest --watch",
    "build": "tsc",
    "lint": "eslint src/**/*.js"
  }
}
```

## 🔒 Security

```javascript
// Use helmet for security headers
const helmet = require('helmet');
app.use(helmet());

// Use rate limiting
const rateLimit = require('express-rate-limit');
const limiter = rateLimit({
    windowMs: 15 * 60 * 1000,
    max: 100
});
app.use('/api/', limiter);

// Input validation
const { body, validationResult } = require('express-validator');

app.post('/api/qris/create', [
    body('amount').isFloat({ min: 10000 }),
    body('orderId').isString().notEmpty()
], async (req, res) => {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
        return res.status(400).json({ errors: errors.array() });
    }
    // ... rest of code
});
```

## 📄 License

MIT License
