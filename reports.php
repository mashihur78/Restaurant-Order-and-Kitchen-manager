<?php
$pageTitle = 'Reports';
$basePath = dirname(__DIR__);

require_once $basePath . '/config/session.php';
require_once $basePath . '/config/security.php';
require_once $basePath . '/config/database.php';

requireLogin();
requireRole(['admin', 'manager']);

$conn = getConnection();

// Today's Stats
$todayOrders = 0;
$todayRevenue = 0;
$todayItems = 0;

$sql = "SELECT COUNT(*) as orders, SUM(total) as revenue FROM orders WHERE DATE(created_at) = CURDATE() AND status = 'completed'";
$result = $conn->query($sql);
if ($result) {
    $row = $result->fetch_assoc();
    $todayOrders = $row['orders'];
    $todayRevenue = $row['revenue'] ? $row['revenue'] : 0;
}

$sql = "SELECT SUM(oi.quantity) as items FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE DATE(o.created_at) = CURDATE() AND o.status = 'completed'";
$result = $conn->query($sql);
if ($result) {
    $todayItems = $result->fetch_assoc()['items'] ? $result->fetch_assoc()['items'] : 0;
}

// This Week
$weekRevenue = 0;
$sql = "SELECT SUM(total) as revenue FROM orders WHERE YEARWEEK(created_at) = YEARWEEK(NOW()) AND status = 'completed'";
$result = $conn->query($sql);
if ($result) {
    $weekRevenue = $result->fetch_assoc()['revenue'] ? $result->fetch_assoc()['revenue'] : 0;
}

// This Month
$monthRevenue = 0;
$monthOrders = 0;
$sql = "SELECT COUNT(*) as orders, SUM(total) as revenue FROM orders WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW()) AND status = 'completed'";
$result = $conn->query($sql);
if ($result) {
    $row = $result->fetch_assoc();
    $monthOrders = $row['orders'];
    $monthRevenue = $row['revenue'] ? $row['revenue'] : 0;
}

// Top Selling Items
$topItems = array();
$sql = "SELECT oi.item_name, SUM(oi.quantity) as total_qty, SUM(oi.subtotal) as total_sales 
        FROM order_items oi 
        JOIN orders o ON oi.order_id = o.id 
        WHERE o.status = 'completed' 
        GROUP BY oi.item_name 
        ORDER BY total_qty DESC 
        LIMIT 10";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $topItems[] = $row;
    }
}

// Recent Orders
$recentOrders = array();
$sql = "SELECT o.*, t.table_number FROM orders o LEFT JOIN tables t ON o.table_id = t.id WHERE o.status = 'completed' ORDER BY o.created_at DESC LIMIT 10";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentOrders[] = $row;
    }
}

// Daily Revenue (Last 7 Days)
$dailyRevenue = array();
$sql = "SELECT DATE(created_at) as date, SUM(total) as revenue, COUNT(*) as orders 
        FROM orders 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND status = 'completed'
        GROUP BY DATE(created_at) 
        ORDER BY date ASC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $dailyRevenue[] = $row;
    }
}

$conn->close();
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
        .user-avatar { width: 40px; height: 40px; background-color: #012754; border-radius: 50%; display: flex; justify-content: center; align-items: center; color: white; font-weight: bold; }
        .content-area { padding: 30px; }
        
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background-color: white; padding: 25px; border-radius: 12px; text-align: center; border: 1px solid #eee; }
        .stat-card .stat-icon { font-size: 32px; margin-bottom: 10px; }
        .stat-card h3 { font-size: 28px; margin-bottom: 5px; color: #012754; }
        .stat-card p { color: #666; font-size: 13px; }
        .stat-card.revenue h3 { color: #2e7d32; }
        
        .card { background-color: white; padding: 25px; border-radius: 12px; margin-bottom: 30px; border: 1px solid #eee; }
        .card h2 { margin-bottom: 20px; color: #012754; font-size: 18px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        
        .report-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        
        table { width: 100%; border-collapse: collapse; }
        table th, table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        table th { background-color: #f8f9fa; color: #012754; font-size: 13px; }
        table tr:hover { background-color: #f8f9fa; }
        
        .price { color: #2e7d32; font-weight: bold; }
        .rank { background-color: #012754; color: white; width: 24px; height: 24px; border-radius: 50%; display: inline-flex; justify-content: center; align-items: center; font-size: 12px; font-weight: bold; }
        
        .daily-chart { display: flex; align-items: flex-end; gap: 10px; height: 200px; padding: 20px 0; }
        .chart-bar { flex: 1; background-color: #012754; border-radius: 8px 8px 0 0; min-height: 20px; position: relative; }
        .chart-bar:hover { background-color: #1976D2; }
        .chart-label { position: absolute; bottom: -25px; left: 50%; transform: translateX(-50%); font-size: 11px; color: #666; white-space: nowrap; }
        .chart-value { position: absolute; top: -25px; left: 50%; transform: translateX(-50%); font-size: 11px; font-weight: bold; color: #012754; }
    </style>
</head>
<body>
    <div class="sidebar">
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
            <a href="billing.php"><span class="icon">💰</span> Billing</a>
            <a href="customers.php"><span class="icon">👤</span> Customers</a>
            <a href="reservations.php"><span class="icon">🎫</span> Reservations</a>
            <a href="reports.php" class="active"><span class="icon">📈</span> Reports</a>
            <a href="logout.php"><span class="icon">🚪</span> Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="top-header">
            <div class="welcome-text">📈 Reports & Analytics</div>
            <div class="user-avatar"><?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?></div>
        </div>

        <div class="content-area">
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon">📦</div>
                    <h3><?php echo $todayOrders; ?></h3>
                    <p>Today's Orders</p>
                </div>
                <div class="stat-card revenue">
                    <div class="stat-icon">💰</div>
                    <h3>$<?php echo number_format($todayRevenue, 2); ?></h3>
                    <p>Today's Revenue</p>
                </div>
                <div class="stat-card revenue">
                    <div class="stat-icon">📅</div>
                    <h3>$<?php echo number_format($weekRevenue, 2); ?></h3>
                    <p>This Week</p>
                </div>
                <div class="stat-card revenue">
                    <div class="stat-icon">📆</div>
                    <h3>$<?php echo number_format($monthRevenue, 2); ?></h3>
                    <p>This Month (<?php echo $monthOrders; ?> orders)</p>
                </div>
            </div>

            <!-- Daily Revenue Chart -->
            <?php if (count($dailyRevenue) > 0): ?>
            <div class="card">
                <h2>📊 Last 7 Days Revenue</h2>
                <?php
                $maxRevenue = 0;
                foreach ($dailyRevenue as $day) {
                    if ($day['revenue'] > $maxRevenue) $maxRevenue = $day['revenue'];
                }
                ?>
                <div class="daily-chart">
                    <?php foreach ($dailyRevenue as $day): ?>
                    <?php $height = $maxRevenue > 0 ? ($day['revenue'] / $maxRevenue) * 150 + 20 : 20; ?>
                    <div class="chart-bar" style="height: <?php echo $height; ?>px;">
                        <span class="chart-value">$<?php echo number_format($day['revenue'], 0); ?></span>
                        <span class="chart-label"><?php echo date('M d', strtotime($day['date'])); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="report-grid">
                <!-- Top Selling Items -->
                <div class="card">
                    <h2>🏆 Top Selling Items</h2>
                    <?php if (count($topItems) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Item</th>
                                <th>Qty Sold</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rank = 1; foreach ($topItems as $item): ?>
                            <tr>
                                <td><span class="rank"><?php echo $rank++; ?></span></td>
                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td><?php echo $item['total_qty']; ?></td>
                                <td class="price">$<?php echo number_format($item['total_sales'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p style="text-align:center; color:#666; padding:20px;">No sales data yet</p>
                    <?php endif; ?>
                </div>

                <!-- Recent Orders -->
                <div class="card">
                    <h2>📋 Recent Completed Orders</h2>
                    <?php if (count($recentOrders) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Table</th>
                                <th>Total</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['id']; ?></td>
                                <td><?php echo $order['table_number'] ? 'Table ' . $order['table_number'] : 'N/A'; ?></td>
                                <td class="price">$<?php echo number_format($order['total'], 2); ?></td>
                                <td><?php echo date('M d, h:i A', strtotime($order['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p style="text-align:center; color:#666; padding:20px;">No completed orders yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>