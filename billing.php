<?php
$pageTitle = 'Billing';
$basePath = dirname(__DIR__);

require_once $basePath . '/config/session.php';
require_once $basePath . '/config/security.php';
require_once $basePath . '/models/Order.php';

requireLogin();
requireRole(['admin', 'manager', 'server']);

$orderModel = new Order();

$error = '';
$success = '';
$order = null;
$billGenerated = false;

// Get order ID from URL
$orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

// Handle payment
if (isPost()) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request.';
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        
        if ($action === 'process_payment') {
            $orderId = intval($_POST['order_id']);
            $paymentMethod = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'cash';
            $paidAmount = floatval($_POST['paid_amount']);
            $discount = floatval($_POST['discount']);
            
            // Get order
            $order = $orderModel->getById($orderId);
            
            if ($order) {
                // Calculate final total
                $subtotal = $order['subtotal'];
                $discountAmount = ($subtotal * $discount) / 100;
                $afterDiscount = $subtotal - $discountAmount;
                $tax = $afterDiscount * 0.05;
                $finalTotal = $afterDiscount + $tax;
                $changeAmount = $paidAmount - $finalTotal;
                
                if ($paidAmount < $finalTotal) {
                    $error = 'Paid amount is less than total';
                } else {
                    // Update order payment status
                    $conn = getConnection();
                    $sql = "UPDATE orders SET 
                            payment_method = ?, 
                            payment_status = 'paid',
                            discount = ?,
                            status = 'completed',
                            completed_at = NOW()
                            WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sdi", $paymentMethod, $discountAmount, $orderId);
                    
                    if ($stmt->execute()) {
                        // Update table status
                        if ($order['table_id']) {
                            $tableSql = "UPDATE tables SET status = 'available' WHERE id = ?";
                            $tableStmt = $conn->prepare($tableSql);
                            $tableStmt->bind_param("i", $order['table_id']);
                            $tableStmt->execute();
                        }
                        
                        $success = 'Payment successful! Change: $' . number_format($changeAmount, 2);
                        $billGenerated = true;
                    } else {
                        $error = 'Payment failed';
                    }
                    $conn->close();
                }
            }
        }
    }
}

// Get order details
if ($orderId > 0) {
    $order = $orderModel->getById($orderId);
}

// Get completed orders for billing
$completedOrders = $orderModel->getByStatus('ready');
$paidOrders = $orderModel->getByStatus('completed');

$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Restaurant Management</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background-color: #f0f2f5; min-height: 100vh; }
        .sidebar { width: 260px; background-color: #012754; color: white; position: fixed; top: 0; left: 0; height: 100vh; overflow-y: auto; }
        .sidebar-logo { display: flex; align-items: center; padding: 25px 20px; gap: 12px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-logo-icon { width: 45px; height: 45px; background-color: white; border-radius: 10px; display: flex; justify-content: center; align-items: center; color: #012754; font-size: 20px; font-weight: bold; }
        .sidebar-logo h2 { font-size: 18px; }
        .sidebar-menu { padding: 20px 0; }
        .sidebar-menu a { display: flex; align-items: center; gap: 12px; color: rgba(255,255,255,0.7); text-decoration: none; padding: 14px 20px; font-size: 15px; border-left: 3px solid transparent; }
        .sidebar-menu a:hover { background-color: rgba(255,255,255,0.08); color: white; }
        .sidebar-menu a.active { background-color: rgba(255,255,255,0.12); color: white; border-left-color: white; font-weight: bold; }
        .sidebar-menu a .icon { width: 22px; text-align: center; }
        .main-content { margin-left: 260px; background-color: #f0f2f5; min-height: 100vh; }
        .top-header { background-color: white; padding: 20px 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .welcome-text { font-size: 18px; color: #333; }
        .welcome-text span { font-weight: bold; color: #012754; }
        .top-header-right { display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 40px; height: 40px; background-color: #012754; border-radius: 50%; display: flex; justify-content: center; align-items: center; color: white; font-weight: bold; }
        .content-area { padding: 30px; }
        
        .billing-grid { display: grid; grid-template-columns: 1fr 400px; gap: 30px; }
        
        .card { background-color: white; padding: 25px; border-radius: 12px; margin-bottom: 30px; border: 1px solid #eee; }
        .card h2 { margin-bottom: 20px; color: #012754; font-size: 18px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        
        .btn { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: bold; text-decoration: none; display: inline-block; }
        .btn-primary { background-color: #012754; color: white; }
        .btn-success { background-color: #2e7d32; color: white; }
        .btn-warning { background-color: #ff9800; color: white; }
        .btn-info { background-color: #1976D2; color: white; }
        .btn-sm { padding: 8px 16px; font-size: 13px; }
        .btn:hover { opacity: 0.9; }
        
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background-color: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .alert-error { background-color: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #333; font-weight: bold; font-size: 14px; }
        .form-group input, .form-group select { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #012754; }
        
        /* Bill Styles */
        .bill-container { background-color: white; border: 2px dashed #ddd; border-radius: 12px; padding: 30px; }
        .bill-header { text-align: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 2px dashed #eee; }
        .bill-header h1 { font-size: 24px; color: #012754; margin-bottom: 5px; }
        .bill-header p { color: #666; font-size: 13px; }
        
        .bill-info { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .bill-info-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; }
        .bill-info-row span:first-child { color: #666; }
        .bill-info-row span:last-child { font-weight: bold; color: #333; }
        
        .bill-items { margin-bottom: 20px; }
        .bill-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .bill-item:last-child { border-bottom: none; }
        .bill-item-name { flex: 1; }
        .bill-item-qty { width: 60px; text-align: center; color: #666; }
        .bill-item-price { width: 80px; text-align: right; font-weight: bold; }
        
        .bill-totals { background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 20px; }
        .bill-total-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 15px; }
        .bill-total-row.discount { color: #d32f2f; }
        .bill-total-row.grand-total { font-size: 20px; font-weight: bold; color: #2e7d32; border-top: 2px solid #ddd; padding-top: 15px; margin-top: 10px; }
        
        .bill-footer { text-align: center; margin-top: 25px; padding-top: 20px; border-top: 2px dashed #eee; color: #666; font-size: 13px; }
        
        /* Payment Form */
        .payment-methods { display: flex; gap: 10px; margin-bottom: 20px; }
        .payment-method { flex: 1; padding: 15px; border: 2px solid #ddd; border-radius: 8px; text-align: center; cursor: pointer; transition: all 0.2s; }
        .payment-method:hover { border-color: #012754; }
        .payment-method.selected { border-color: #012754; background-color: #e8eaf6; }
        .payment-method .method-icon { font-size: 24px; margin-bottom: 5px; display: block; }
        .payment-method .method-name { font-size: 13px; font-weight: bold; }
        
        /* Orders List */
        .order-list { max-height: 400px; overflow-y: auto; }
        .order-list-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; border: 1px solid #eee; border-radius: 8px; margin-bottom: 10px; cursor: pointer; transition: all 0.2s; }
        .order-list-item:hover { border-color: #012754; background-color: #f8f9fa; }
        .order-list-item.selected { border-color: #012754; background-color: #e8eaf6; }
        .order-list-info h4 { font-size: 15px; color: #333; margin-bottom: 4px; }
        .order-list-info p { font-size: 12px; color: #666; }
        .order-list-total { font-size: 18px; font-weight: bold; color: #2e7d32; }
        
        .empty-message { text-align: center; color: #666; padding: 40px; }
        .empty-message .empty-icon { font-size: 48px; margin-bottom: 15px; }
        
        .print-btn { display: none; }
        
        @media print {
            .sidebar, .top-header, .no-print { display: none !important; }
            .main-content { margin-left: 0 !important; }
            .billing-grid { grid-template-columns: 1fr !important; }
            .bill-container { border: none !important; }
            .print-btn { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="sidebar no-print">
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon">R</div>
            <h2>Restaurant</h2>
        </div>
        <div class="sidebar-menu">
            <a href="dashboard.php"><span class="icon">📊</span> Dashboard</a>
            <a href="staff.php"><span class="icon">👥</span> Staff</a>
            <a href="tables.php"><span class="icon">🪑</span> Tables</a>
            <a href="categories.php"><span class="icon">📁</span> Categories</a>
            <a href="menu_items.php"><span class="icon">🍔</span> Menu Items</a>
            <a href="new_order.php"><span class="icon">➕</span> New Order</a>
            <a href="orders.php"><span class="icon">📋</span> Orders</a>
            <a href="kitchen.php"><span class="icon">👨‍🍳</span> Kitchen</a>
            <a href="billing.php" class="active"><span class="icon">💰</span> Billing</a>
            <a href="customers.php"><span class="icon">👤</span> Customers</a>
            <a href="reservations.php"><span class="icon">🎫</span> Reservations</a>
            <a href="reports.php"><span class="icon">📈</span> Reports</a>
            <a href="logout.php"><span class="icon">🚪</span> Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="top-header no-print">
            <div class="welcome-text">💰 Billing & Payments</div>
            <div class="top-header-right">
                <div class="user-avatar"><?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?></div>
            </div>
        </div>

        <div class="content-area">
            <?php if ($success != ''): ?>
                <div class="alert alert-success no-print"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if ($error != ''): ?>
                <div class="alert alert-error no-print"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($order): ?>
            <!-- Billing View -->
            <div class="billing-grid">
                <!-- Bill -->
                <div class="bill-container">
                    <div class="bill-header">
                        <h1>🍽️ Restaurant</h1>
                        <p>123 Food Street, City</p>
                        <p>Tel: (123) 456-7890</p>
                    </div>
                    
                    <div class="bill-info">
                        <div class="bill-info-row">
                            <span>Bill No:</span>
                            <span>#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></span>
                        </div>
                        <div class="bill-info-row">
                            <span>Date:</span>
                            <span><?php echo date('M d, Y h:i A'); ?></span>
                        </div>
                        <div class="bill-info-row">
                            <span>Table:</span>
                            <span><?php echo $order['table_number'] ? 'Table ' . $order['table_number'] : 'N/A'; ?></span>
                        </div>
                        <div class="bill-info-row">
                            <span>Server:</span>
                            <span><?php echo htmlspecialchars($order['server_name']); ?></span>
                        </div>
                        <div class="bill-info-row">
                            <span>Order Type:</span>
                            <span><?php echo ucfirst($order['order_type']); ?></span>
                        </div>
                    </div>
                    
                    <div class="bill-items">
                        <div class="bill-item" style="font-weight: bold; border-bottom: 2px solid #ddd;">
                            <span class="bill-item-name">Item</span>
                            <span class="bill-item-qty">Qty</span>
                            <span class="bill-item-price">Amount</span>
                        </div>
                        <?php foreach ($order['items'] as $item): ?>
                        <div class="bill-item">
                            <span class="bill-item-name"><?php echo htmlspecialchars($item['item_name']); ?></span>
                            <span class="bill-item-qty"><?php echo $item['quantity']; ?></span>
                            <span class="bill-item-price">$<?php echo number_format($item['subtotal'], 2); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="bill-totals">
                        <div class="bill-total-row">
                            <span>Subtotal:</span>
                            <span>$<?php echo number_format($order['subtotal'], 2); ?></span>
                        </div>
                        <div class="bill-total-row discount" id="discountRow" style="display: none;">
                            <span>Discount (<span id="discountPercent">0</span>%):</span>
                            <span>-$<span id="discountAmount">0.00</span></span>
                        </div>
                        <div class="bill-total-row">
                            <span>Tax (5%):</span>
                            <span>$<span id="taxAmount"><?php echo number_format($order['tax'], 2); ?></span></span>
                        </div>
                        <div class="bill-total-row grand-total">
                            <span>Total:</span>
                            <span>$<span id="grandTotal"><?php echo number_format($order['total'], 2); ?></span></span>
                        </div>
                    </div>
                    
                    <div class="bill-footer">
                        <p>Thank you for dining with us!</p>
                        <p>Please visit again 🙏</p>
                    </div>
                </div>
                
                <!-- Payment Form -->
                <div class="no-print">
                    <?php if ($order['payment_status'] != 'paid' && !$billGenerated): ?>
                    <div class="card">
                        <h2>💳 Payment</h2>
                        <form method="POST" action="" id="paymentForm">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="process_payment">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            
                            <div class="form-group">
                                <label>Payment Method</label>
                                <div class="payment-methods">
                                    <div class="payment-method selected" data-method="cash" onclick="selectPaymentMethod('cash')">
                                        <span class="method-icon">💵</span>
                                        <span class="method-name">Cash</span>
                                    </div>
                                    <div class="payment-method" data-method="card" onclick="selectPaymentMethod('card')">
                                        <span class="method-icon">💳</span>
                                        <span class="method-name">Card</span>
                                    </div>
                                    <div class="payment-method" data-method="online" onclick="selectPaymentMethod('online')">
                                        <span class="method-icon">📱</span>
                                        <span class="method-name">Online</span>
                                    </div>
                                </div>
                                <input type="hidden" name="payment_method" id="paymentMethod" value="cash">
                            </div>
                            
                            <div class="form-group">
                                <label>Discount (%)</label>
                                <input type="number" name="discount" id="discountInput" value="0" min="0" max="100" onchange="calculateTotal()">
                            </div>
                            
                            <div class="form-group">
                                <label>Amount to Pay</label>
                                <input type="text" id="amountToPay" value="$<?php echo number_format($order['total'], 2); ?>" readonly style="background-color: #e8f5e9; font-weight: bold; font-size: 18px; color: #2e7d32;">
                            </div>
                            
                            <div class="form-group">
                                <label>Paid Amount ($)</label>
                                <input type="number" name="paid_amount" id="paidAmount" step="0.01" min="0" value="<?php echo number_format($order['total'], 2); ?>" onchange="calculateChange()">
                            </div>
                            
                            <div class="form-group">
                                <label>Change</label>
                                <input type="text" id="changeAmount" value="$0.00" readonly style="background-color: #fff8e1; font-weight: bold; font-size: 18px;">
                            </div>
                            
                            <button type="submit" class="btn btn-success" style="width: 100%; padding: 15px; font-size: 16px;">
                                ✅ Complete Payment
                            </button>
                        </form>
                    </div>
                    <?php else: ?>
                    <div class="card">
                        <h2>✅ Payment Complete</h2>
                        <div style="text-align: center; padding: 20px;">
                            <div style="font-size: 64px; margin-bottom: 15px;">🎉</div>
                            <p style="font-size: 18px; color: #2e7d32; font-weight: bold;">Payment Successful!</p>
                            <p style="color: #666; margin: 15px 0;">Order has been completed.</p>
                            <button onclick="window.print();" class="btn btn-primary" style="margin-top: 10px;">🖨️ Print Bill</button>
                            <a href="new_order.php" class="btn btn-success" style="margin-top: 10px;">➕ New Order</a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php else: ?>
            <!-- Orders List for Billing -->
            <div class="card">
                <h2>📋 Select Order for Billing</h2>
                
                <?php if (count($completedOrders) > 0): ?>
                <h3 style="margin-bottom: 15px; color: #666; font-size: 14px;">Ready Orders</h3>
                <div class="order-list">
                    <?php foreach ($completedOrders as $o): ?>
                    <a href="billing.php?order_id=<?php echo $o['id']; ?>" style="text-decoration: none;">
                        <div class="order-list-item">
                            <div class="order-list-info">
                                <h4>Order #<?php echo $o['id']; ?> - Table <?php echo $o['table_number'] ? $o['table_number'] : 'N/A'; ?></h4>
                                <p><?php echo ucfirst($o['order_type']); ?> | <?php echo date('h:i A', strtotime($o['created_at'])); ?></p>
                            </div>
                            <div class="order-list-total">$<?php echo number_format($o['total'], 2); ?></div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-message">
                    <div class="empty-icon">💰</div>
                    <p>No orders ready for billing</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Recent Paid Bills -->
            <?php if (count($paidOrders) > 0): ?>
            <div class="card">
                <h2>✅ Recent Paid Bills</h2>
                <div class="order-list">
                    <?php 
                    $recentPaid = array_slice($paidOrders, 0, 5);
                    foreach ($recentPaid as $o): 
                    ?>
                    <a href="billing.php?order_id=<?php echo $o['id']; ?>" style="text-decoration: none;">
                        <div class="order-list-item">
                            <div class="order-list-info">
                                <h4>Order #<?php echo $o['id']; ?> - Table <?php echo $o['table_number'] ? $o['table_number'] : 'N/A'; ?></h4>
                                <p><?php echo ucfirst($o['order_type']); ?> | <?php echo date('M d, h:i A', strtotime($o['created_at'])); ?></p>
                            </div>
                            <div class="order-list-total" style="color: #666;">$<?php echo number_format($o['total'], 2); ?> ✓</div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
    var originalSubtotal = <?php echo $order ? $order['subtotal'] : 0; ?>;
    
    function selectPaymentMethod(method) {
        var methods = document.querySelectorAll('.payment-method');
        for (var i = 0; i < methods.length; i++) {
            methods[i].classList.remove('selected');
            if (methods[i].getAttribute('data-method') === method) {
                methods[i].classList.add('selected');
            }
        }
        document.getElementById('paymentMethod').value = method;
    }
    
    function calculateTotal() {
        var discount = parseFloat(document.getElementById('discountInput').value) || 0;
        var discountAmount = (originalSubtotal * discount) / 100;
        var afterDiscount = originalSubtotal - discountAmount;
        var tax = afterDiscount * 0.05;
        var total = afterDiscount + tax;
        
        if (discount > 0) {
            document.getElementById('discountRow').style.display = 'flex';
            document.getElementById('discountPercent').textContent = discount;
            document.getElementById('discountAmount').textContent = discountAmount.toFixed(2);
        } else {
            document.getElementById('discountRow').style.display = 'none';
        }
        
        document.getElementById('taxAmount').textContent = tax.toFixed(2);
        document.getElementById('grandTotal').textContent = total.toFixed(2);
        document.getElementById('amountToPay').value = '$' + total.toFixed(2);
        document.getElementById('paidAmount').value = total.toFixed(2);
        
        calculateChange();
    }
    
    function calculateChange() {
        var total = parseFloat(document.getElementById('grandTotal').textContent);
        var paid = parseFloat(document.getElementById('paidAmount').value) || 0;
        var change = paid - total;
        
        if (change < 0) {
            document.getElementById('changeAmount').value = 'Insufficient';
            document.getElementById('changeAmount').style.backgroundColor = '#ffebee';
            document.getElementById('changeAmount').style.color = '#c62828';
        } else {
            document.getElementById('changeAmount').value = '$' + change.toFixed(2);
            document.getElementById('changeAmount').style.backgroundColor = '#e8f5e9';
            document.getElementById('changeAmount').style.color = '#2e7d32';
        }
    }
    
    // Validate form
    if (document.getElementById('paymentForm')) {
        document.getElementById('paymentForm').onsubmit = function(e) {
            var total = parseFloat(document.getElementById('grandTotal').textContent);
            var paid = parseFloat(document.getElementById('paidAmount').value) || 0;
            
            if (paid < total) {
                alert('Paid amount is less than total!');
                e.preventDefault();
                return false;
            }
            
            return confirm('Confirm payment of $' + total.toFixed(2) + '?');
        };
    }
    </script>
</body>
</html>