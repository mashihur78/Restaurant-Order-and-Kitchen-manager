<?php
$pageTitle = 'Reservations';
$basePath = dirname(__DIR__);

require_once $basePath . '/config/session.php';
require_once $basePath . '/config/security.php';
require_once $basePath . '/models/Reservation.php';
require_once $basePath . '/models/Table.php';

requireLogin();
requireRole(['admin', 'manager']);

$reservationModel = new Reservation();
$tableModel = new Table();

$error = '';
$success = '';

if (isPost()) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request.';
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        
        if ($action === 'create') {
            $data = array(
                'table_id' => isset($_POST['table_id']) ? $_POST['table_id'] : null,
                'customer_name' => isset($_POST['customer_name']) ? $_POST['customer_name'] : '',
                'customer_phone' => isset($_POST['customer_phone']) ? $_POST['customer_phone'] : '',
                'guest_count' => isset($_POST['guest_count']) ? $_POST['guest_count'] : 2,
                'reservation_date' => isset($_POST['reservation_date']) ? $_POST['reservation_date'] : '',
                'reservation_time' => isset($_POST['reservation_time']) ? $_POST['reservation_time'] : '',
                'notes' => isset($_POST['notes']) ? $_POST['notes'] : ''
            );
            if (empty($data['customer_name']) || empty($data['customer_phone'])) {
                $error = 'Customer name and phone are required';
            } elseif (empty($data['reservation_date']) || empty($data['reservation_time'])) {
                $error = 'Date and time are required';
            } else {
                $result = $reservationModel->create($data);
                $success = $result['success'] ? $result['message'] : '';
                $error = !$result['success'] ? $result['error'] : '';
            }
        }
        
        if ($action === 'update_status') {
            $id = intval($_POST['id']);
            $status = $_POST['status'];
            $result = $reservationModel->updateStatus($id, $status);
            $success = $result['success'] ? $result['message'] : '';
            $error = !$result['success'] ? $result['error'] : '';
        }
        
        if ($action === 'delete') {
            $id = intval($_POST['id']);
            $result = $reservationModel->delete($id);
            $success = $result['success'] ? $result['message'] : '';
            $error = !$result['success'] ? $result['error'] : '';
        }
    }
}

$reservations = $reservationModel->getAll();
$todayReservations = $reservationModel->getToday();
$tables = $tableModel->getAvailable();
$counts = $reservationModel->countByStatus();
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
        .stat-card { background-color: white; padding: 20px; border-radius: 12px; text-align: center; border: 1px solid #eee; }
        .stat-card .stat-icon { font-size: 28px; margin-bottom: 10px; }
        .stat-card h3 { font-size: 24px; margin-bottom: 5px; color: #012754; }
        .stat-card p { color: #666; font-size: 13px; }
        .card { background-color: white; padding: 25px; border-radius: 12px; margin-bottom: 30px; border: 1px solid #eee; }
        .card h2 { margin-bottom: 20px; color: #012754; font-size: 18px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 6px; color: #333; font-weight: bold; font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; background-color: #f9f9f9; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #012754; background-color: white; }
        .form-group textarea { height: 60px; resize: none; }
        .form-row { display: flex; gap: 20px; }
        .form-row .form-group { flex: 1; }
        .form-buttons { display: flex; gap: 12px; margin-top: 25px; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: bold; text-decoration: none; display: inline-block; }
        .btn-primary { background-color: #012754; color: white; }
        .btn-success { background-color: #2e7d32; color: white; }
        .btn-danger { background-color: #d32f2f; color: white; }
        .btn-warning { background-color: #ff9800; color: white; }
        .btn-sm { padding: 8px 16px; font-size: 13px; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background-color: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .alert-error { background-color: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        table { width: 100%; border-collapse: collapse; }
        table th, table td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        table th { background-color: #f8f9fa; color: #012754; font-size: 14px; }
        table tr:hover { background-color: #f8f9fa; }
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; }
        .badge-pending { background-color: #fff3e0; color: #ef6c00; }
        .badge-confirmed { background-color: #e8f5e9; color: #2e7d32; }
        .badge-cancelled { background-color: #ffebee; color: #c62828; }
        .badge-completed { background-color: #e3f2fd; color: #1565c0; }
        .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
        .empty-message { text-align: center; color: #666; padding: 40px; }
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
            <a href="reservations.php" class="active"><span class="icon">🎫</span> Reservations</a>
            <a href="reports.php"><span class="icon">📈</span> Reports</a>
            <a href="logout.php"><span class="icon">🚪</span> Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="top-header">
            <div class="welcome-text">🎫 Reservations</div>
            <div class="user-avatar"><?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?></div>
        </div>

        <div class="content-area">
            <?php if ($success != ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if ($error != ''): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon">📅</div>
                    <h3><?php echo count($todayReservations); ?></h3>
                    <p>Today</p>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">⏳</div>
                    <h3><?php echo $counts['pending']; ?></h3>
                    <p>Pending</p>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <h3><?php echo $counts['confirmed']; ?></h3>
                    <p>Confirmed</p>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🎉</div>
                    <h3><?php echo $counts['completed']; ?></h3>
                    <p>Completed</p>
                </div>
            </div>

            <div class="card">
                <h2>➕ New Reservation</h2>
                <form method="POST" action="">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Customer Name *</label>
                            <input type="text" name="customer_name" placeholder="Enter name" required>
                        </div>
                        <div class="form-group">
                            <label>Phone *</label>
                            <input type="text" name="customer_phone" placeholder="Enter phone" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date *</label>
                            <input type="date" name="reservation_date" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Time *</label>
                            <input type="time" name="reservation_time" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Guests</label>
                            <input type="number" name="guest_count" value="2" min="1" max="20">
                        </div>
                        <div class="form-group">
                            <label>Table (Optional)</label>
                            <select name="table_id">
                                <option value="">-- Auto Assign --</option>
                                <?php foreach ($tables as $table): ?>
                                <option value="<?php echo $table['id']; ?>">Table <?php echo $table['table_number']; ?> (<?php echo $table['capacity']; ?> seats)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" placeholder="Special requests..."></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="submit" class="btn btn-primary">Create Reservation</button>
                    </div>
                </form>
            </div>

            <div class="card">
                <h2>📋 All Reservations</h2>
                <?php if (count($reservations) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Date & Time</th>
                            <th>Guests</th>
                            <th>Table</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $res): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($res['customer_name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($res['customer_phone']); ?></small>
                            </td>
                            <td>
                                <?php echo date('M d, Y', strtotime($res['reservation_date'])); ?><br>
                                <small><?php echo date('h:i A', strtotime($res['reservation_time'])); ?></small>
                            </td>
                            <td><?php echo $res['guest_count']; ?> guests</td>
                            <td><?php echo $res['table_number'] ? 'Table ' . $res['table_number'] : 'Not assigned'; ?></td>
                            <td><span class="badge badge-<?php echo $res['status']; ?>"><?php echo ucfirst($res['status']); ?></span></td>
                            <td class="action-buttons">
                                <?php if ($res['status'] == 'pending'): ?>
                                <form method="POST" style="display:inline;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="id" value="<?php echo $res['id']; ?>">
                                    <input type="hidden" name="status" value="confirmed">
                                    <button type="submit" class="btn btn-success btn-sm">Confirm</button>
                                </form>
                                <?php endif; ?>
                                <?php if ($res['status'] == 'confirmed'): ?>
                                <form method="POST" style="display:inline;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="id" value="<?php echo $res['id']; ?>">
                                    <input type="hidden" name="status" value="completed">
                                    <button type="submit" class="btn btn-primary btn-sm">Complete</button>
                                </form>
                                <?php endif; ?>
                                <?php if (in_array($res['status'], ['pending', 'confirmed'])): ?>
                                <form method="POST" style="display:inline;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="id" value="<?php echo $res['id']; ?>">
                                    <input type="hidden" name="status" value="cancelled">
                                    <button type="submit" class="btn btn-danger btn-sm">Cancel</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-message">
                    <p>No reservations found.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>