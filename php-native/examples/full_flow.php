<?php

/**
 * Example: Complete Payment Flow
 * 
 * This example demonstrates a complete payment flow with UI
 * Run with: php -S localhost:8000
 * Then visit: http://localhost:8000/full_flow.php
 */

require_once __DIR__ . '/../src/MandiriQris.php';

session_start();

// Initialize Mandiri QRIS client
$mandiri = new MandiriQris([
    'client_id' => 'your_client_id_here',
    'client_secret' => 'your_client_secret_here',
    'environment' => 'sandbox',
    'merchant_nmid' => 'YOUR_NMID',
    'merchant_name' => 'YOUR MERCHANT NAME',
    'merchant_city' => 'JAKARTA'
]);

// Handle AJAX status check
if (isset($_GET['check_status']) && isset($_SESSION['qr_id'])) {
    header('Content-Type: application/json');
    
    try {
        $status = $mandiri->checkStatus($_SESSION['qr_id']);
        echo json_encode([
            'success' => true,
            'status' => $status['status'],
            'amount' => $status['amount'],
            'paid_at' => $status['paid_at']
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Handle payment creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount'])) {
    try {
        $amount = (float) $_POST['amount'];
        $orderId = 'ORDER-' . time();
        
        $result = $mandiri->createQris([
            'amount' => $amount,
            'reference' => $orderId
        ]);
        
        // Store in session
        $_SESSION['qr_id'] = $result['qr_id'];
        $_SESSION['qr_image_url'] = $result['qr_image_url'];
        $_SESSION['amount'] = $amount;
        $_SESSION['order_id'] = $orderId;
        $_SESSION['expired_at'] = $result['expired_at'];
        
        header('Location: ?step=payment');
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get current step
$step = $_GET['step'] ?? 'create';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mandiri QRIS Payment Demo</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #003d7a; margin-bottom: 20px; text-align: center; }
        .logo { text-align: center; margin-bottom: 20px; }
        .logo img { height: 50px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; color: #333; }
        input[type="number"] { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; }
        button { width: 100%; padding: 15px; background: #003d7a; color: white; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; font-weight: 600; }
        button:hover { background: #002952; }
        .qr-container { text-align: center; }
        .qr-image { max-width: 300px; margin: 20px auto; border: 2px solid #ddd; border-radius: 10px; padding: 20px; background: white; }
        .qr-image img { width: 100%; }
        .payment-info { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .payment-info p { margin: 8px 0; }
        .status { padding: 15px; border-radius: 5px; margin: 20px 0; text-align: center; font-weight: 600; }
        .status.pending { background: #fff3cd; color: #856404; }
        .status.success { background: #d4edda; color: #155724; }
        .status.expired { background: #f8d7da; color: #721c24; }
        .timer { font-size: 24px; font-weight: 700; color: #003d7a; text-align: center; margin: 15px 0; }
        .instructions { background: #e7f3ff; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .instructions ol { margin-left: 20px; }
        .instructions li { margin: 10px 0; }
        .spinner { border: 4px solid #f3f3f3; border-top: 4px solid #003d7a; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 20px auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>💳 Mandiri QRIS Payment</h1>
        </div>

        <?php if ($step === 'create'): ?>
            <!-- Step 1: Create Payment -->
            <h2 style="margin-bottom: 20px;">Create Payment</h2>
            
            <?php if (isset($error)): ?>
                <div class="error">❌ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="amount">Payment Amount (Rp)</label>
                    <input type="number" id="amount" name="amount" min="10000" step="1000" value="150000" required>
                </div>
                <button type="submit">Generate QR Code</button>
            </form>

            <div style="margin-top: 30px; text-align: center; color: #666;">
                <p><strong>Demo Mode - Sandbox Environment</strong></p>
                <p style="font-size: 14px; margin-top: 10px;">This is a demonstration of Mandiri QRIS payment integration.</p>
            </div>

        <?php elseif ($step === 'payment'): ?>
            <!-- Step 2: Show QR Code & Wait for Payment -->
            <h2 style="margin-bottom: 20px; text-align: center;">Scan QR Code to Pay</h2>

            <div class="timer" id="timer">30:00</div>

            <div class="qr-container">
                <div class="qr-image">
                    <img src="<?= htmlspecialchars($_SESSION['qr_image_url']) ?>" alt="QR Code">
                </div>
            </div>

            <div class="payment-info">
                <p><strong>Amount:</strong> Rp <?= number_format($_SESSION['amount'], 0, ',', '.') ?></p>
                <p><strong>Order ID:</strong> <?= htmlspecialchars($_SESSION['order_id']) ?></p>
                <p><strong>Expires:</strong> <?= $_SESSION['expired_at'] ?></p>
            </div>

            <div class="status pending" id="status">
                <div class="spinner"></div>
                <p>Waiting for payment...</p>
            </div>

            <div class="instructions">
                <h3>📱 How to Pay:</h3>
                <ol>
                    <li>Open your mobile banking or e-wallet app</li>
                    <li>Select <strong>QRIS / Scan QR</strong> menu</li>
                    <li>Scan the QR code above</li>
                    <li>Confirm the payment</li>
                    <li>Wait for confirmation</li>
                </ol>
            </div>

            <button onclick="location.href='?step=create'" style="background: #6c757d; margin-top: 20px;">
                Cancel & Create New Payment
            </button>

            <script>
                // Timer countdown
                let remainingSeconds = 30 * 60; // 30 minutes
                const timerEl = document.getElementById('timer');
                const statusEl = document.getElementById('status');

                function updateTimer() {
                    const minutes = Math.floor(remainingSeconds / 60);
                    const seconds = remainingSeconds % 60;
                    timerEl.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                    
                    if (remainingSeconds <= 0) {
                        clearInterval(timerInterval);
                        clearInterval(pollInterval);
                        statusEl.className = 'status expired';
                        statusEl.innerHTML = '<p>❌ QR Code Expired</p><p style="font-size: 14px; margin-top: 10px;">Please create a new payment</p>';
                    }
                    
                    remainingSeconds--;
                }

                const timerInterval = setInterval(updateTimer, 1000);

                // Poll payment status
                let pollInterval = setInterval(checkStatus, 3000); // Check every 3 seconds

                function checkStatus() {
                    fetch('?check_status=1')
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                if (data.status === 'COMPLETED' || data.status === 'PAID') {
                                    clearInterval(pollInterval);
                                    clearInterval(timerInterval);
                                    statusEl.className = 'status success';
                                    statusEl.innerHTML = '<p>✅ Payment Successful!</p><p style="font-size: 14px; margin-top: 10px;">Redirecting...</p>';
                                    setTimeout(() => {
                                        location.href = '?step=success';
                                    }, 2000);
                                } else if (data.status === 'EXPIRED') {
                                    clearInterval(pollInterval);
                                    clearInterval(timerInterval);
                                    statusEl.className = 'status expired';
                                    statusEl.innerHTML = '<p>❌ Payment Expired</p>';
                                }
                            }
                        })
                        .catch(err => {
                            console.error('Error checking status:', err);
                        });
                }
            </script>

        <?php elseif ($step === 'success'): ?>
            <!-- Step 3: Payment Success -->
            <div style="text-align: center;">
                <div style="font-size: 80px; margin: 20px 0;">✅</div>
                <h2 style="color: #28a745; margin-bottom: 20px;">Payment Successful!</h2>
                
                <div class="payment-info">
                    <p><strong>Amount:</strong> Rp <?= number_format($_SESSION['amount'] ?? 0, 0, ',', '.') ?></p>
                    <p><strong>Order ID:</strong> <?= htmlspecialchars($_SESSION['order_id'] ?? '') ?></p>
                    <p><strong>Status:</strong> <span style="color: #28a745; font-weight: 600;">PAID</span></p>
                </div>

                <p style="margin: 20px 0; color: #666;">Thank you for your payment!</p>

                <button onclick="location.href='?step=create'">Create Another Payment</button>
            </div>

            <?php
            // Clear session
            unset($_SESSION['qr_id']);
            unset($_SESSION['qr_image_url']);
            unset($_SESSION['amount']);
            unset($_SESSION['order_id']);
            unset($_SESSION['expired_at']);
            ?>

        <?php endif; ?>
    </div>
</body>
</html>
