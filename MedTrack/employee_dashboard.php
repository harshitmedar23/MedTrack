<?php
require_once 'includes/functions.php';
requireEmployee();

$pageTitle = 'Employee Dashboard';

$totalBills = getCount($conn, 'bill');
$todaysRevenue = fetchRow($conn, 'SELECT COALESCE(SUM(Total_Amount), 0) AS total_amount FROM bill WHERE DATE(Bill_Date) = CURDATE()');
$pendingDeliveries = getCount($conn, 'delivery_orders', "WHERE delivery_status IN ('Order Placed', 'Processing', 'Packed')");
$outForDelivery = getCount($conn, 'delivery_orders', "WHERE delivery_status = 'Out for Delivery'");
$deliveredToday = getCount($conn, 'delivery_orders', "WHERE delivery_status = 'Delivered' AND DATE(created_at) = CURDATE()");
$activeMedicines = getCount($conn, 'medicine', 'WHERE Is_Active = 1');
$lowStockMedicines = getCount($conn, 'medicine', 'WHERE Quantity <= 10 AND Is_Active = 1');

$recentBills = $conn->query(
    "SELECT b.Bill_ID, b.Total_Amount, b.Bill_Date, c.Customer_Name, e.Employee_Name
     FROM bill b
     INNER JOIN customer c ON c.Customer_ID = b.Customer_ID
     INNER JOIN employee e ON e.Employee_ID = b.Employee_ID
     ORDER BY b.Bill_Date DESC
     LIMIT 5"
);

$recentDeliveries = $conn->query(
    "SELECT d.delivery_id, d.bill_id, d.order_id, d.customer_name, d.delivery_status, d.estimated_delivery_date, d.created_at, a.agent_name
     FROM delivery_orders d
     LEFT JOIN delivery_agents a ON a.delivery_agent_id = d.agent_id
     ORDER BY d.delivery_id DESC
     LIMIT 5"
);

$lowStockList = $conn->query(
    "SELECT Medicine_Name, Quantity, Expiry_Date
     FROM medicine
     WHERE Is_Active = 1 AND Quantity <= 10
     ORDER BY Quantity ASC, Expiry_Date ASC
     LIMIT 5"
);

$expiringList = $conn->query(
    "SELECT Medicine_Name, Quantity, Expiry_Date
     FROM medicine
     WHERE Is_Active = 1 AND Expiry_Date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY Expiry_Date ASC
     LIMIT 5"
);

$medicineSnapshot = $conn->query(
    "SELECT Medicine_Name, Category, Quantity, Expiry_Date
     FROM medicine
     WHERE Is_Active = 1
     ORDER BY Quantity ASC, Expiry_Date ASC
     LIMIT 5"
);

include 'includes/header.php';
?>
<div class="hero-strip dashboard-hero compact glass-card">
    <div>
        <h2>Welcome back, <?php echo h(currentUserName()); ?></h2>
        <p>Fast access to billing, deliveries, inventory checks, and reports from one workspace.</p>
    </div>
    <div class="btn-row">
        <a class="btn btn-primary" href="billing.php">Create Bill</a>
        <a class="btn btn-secondary" href="deliveries.php">View Deliveries</a>
        <a class="btn btn-secondary" href="inventory.php">Inventory Check</a>
        <a class="btn btn-secondary" href="reports.php">Reports</a>
    </div>
</div>

<div class="summary-grid">
    <div class="stats-card stats-blue">
        <div class="label">Total Bills</div>
        <div class="value"><?php echo (int) $totalBills; ?></div>
        <div class="hint">Billing records</div>
    </div>
    <div class="stats-card stats-success">
        <div class="label">Today's Revenue</div>
        <div class="value"><?php echo money($todaysRevenue['total_amount'] ?? 0); ?></div>
        <div class="hint">Bills created today</div>
    </div>
    <div class="stats-card stats-warning">
        <div class="label">Pending Deliveries</div>
        <div class="value"><?php echo (int) $pendingDeliveries; ?></div>
        <div class="hint">Order placed to packed</div>
    </div>
    <div class="stats-card stats-danger">
        <div class="label">Low Stock Items</div>
        <div class="value"><?php echo (int) $lowStockMedicines; ?></div>
        <div class="hint">Quantity 10 or below</div>
    </div>
    <div class="stats-card stats-blue">
        <div class="label">Active Medicines</div>
        <div class="value"><?php echo (int) $activeMedicines; ?></div>
        <div class="hint">Visible in inventory</div>
    </div>
</div>

<div class="panel-grid">
    <div class="card">
        <h3 class="section-title">Quick Actions</h3>
        <div class="btn-row">
            <a class="btn btn-primary" href="billing.php">Create Bill</a>
            <a class="btn btn-secondary" href="deliveries.php">View Deliveries</a>
            <a class="btn btn-secondary" href="inventory.php">Inventory Check</a>
            <a class="btn btn-secondary" href="reports.php">Reports</a>
        </div>
    </div>

    <div class="card">
        <h3 class="section-title">Employee Profile</h3>
        <div class="quick-list">
            <div class="quick-item"><span>Name</span><strong><?php echo h(currentUserName()); ?></strong></div>
            <div class="quick-item"><span>Role</span><strong class="order-status-badge">Employee</strong></div>
            <div class="quick-item"><span>Session</span><strong>Active</strong></div>
        </div>
        <div class="btn-row" style="margin-top: 14px;">
            <a class="btn btn-secondary" href="logout.php">Logout</a>
        </div>
    </div>
</div>

<div class="table-card">
    <h3 class="section-title">Medicine Snapshot</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Medicine</th>
                    <th>Category</th>
                    <th>Quantity</th>
                    <th>Expiry</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($medicineSnapshot && $medicineSnapshot->num_rows > 0): ?>
                    <?php while ($row = $medicineSnapshot->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo h($row['Medicine_Name']); ?></td>
                            <td><?php echo h($row['Category']); ?></td>
                            <td><?php echo h($row['Quantity']); ?></td>
                            <td><?php echo h(date('d M Y', strtotime($row['Expiry_Date']))); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4">No medicines found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="panel-grid">
    <div class="table-card">
        <h3 class="section-title">Recent Bills</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Bill</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recentBills && $recentBills->num_rows > 0): ?>
                        <?php while ($row = $recentBills->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo (int) $row['Bill_ID']; ?></td>
                                <td><?php echo h($row['Customer_Name']); ?></td>
                                <td><?php echo money($row['Total_Amount']); ?></td>
                                <td><?php echo h(date('d M Y, h:i A', strtotime($row['Bill_Date']))); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4">No bills found yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="table-card">
        <h3 class="section-title">Delivery Summary</h3>
        <div class="quick-list">
            <div class="quick-item"><span>Pending deliveries</span><strong><?php echo (int) $pendingDeliveries; ?></strong></div>
            <div class="quick-item"><span>Out for delivery</span><strong><?php echo (int) $outForDelivery; ?></strong></div>
            <div class="quick-item"><span>Delivered today</span><strong><?php echo (int) $deliveredToday; ?></strong></div>
        </div>
    </div>
</div>

<div class="panel-grid">
    <div class="table-card">
        <h3 class="section-title">Low Stock Medicines</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Medicine</th>
                        <th>Quantity</th>
                        <th>Expiry</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($lowStockList && $lowStockList->num_rows > 0): ?>
                        <?php while ($row = $lowStockList->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo h($row['Medicine_Name']); ?></td>
                                <td><?php echo (int) $row['Quantity']; ?></td>
                                <td><?php echo h(date('d M Y', strtotime($row['Expiry_Date']))); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3">No low stock medicines.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="table-card">
        <h3 class="section-title">Expiring Medicines</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Medicine</th>
                        <th>Quantity</th>
                        <th>Expiry</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($expiringList && $expiringList->num_rows > 0): ?>
                        <?php while ($row = $expiringList->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo h($row['Medicine_Name']); ?></td>
                                <td><?php echo (int) $row['Quantity']; ?></td>
                                <td><?php echo h(date('d M Y', strtotime($row['Expiry_Date']))); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3">No expiring medicines.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="table-card">
    <h3 class="section-title">Recent Delivery Updates</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Ref</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th>Agent</th>
                    <th>ETA</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recentDeliveries && $recentDeliveries->num_rows > 0): ?>
                    <?php while ($row = $recentDeliveries->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo !empty($row['bill_id']) ? '#' . (int) $row['bill_id'] : (!empty($row['order_id']) ? 'Order #' . h($row['order_id']) : '#' . (int) $row['delivery_id']); ?></td>
                            <td><?php echo h($row['customer_name']); ?></td>
                            <td><span class="order-status-badge"><?php echo h($row['delivery_status']); ?></span></td>
                            <td><?php echo h($row['agent_name'] ?: 'Unassigned'); ?></td>
                            <td><?php echo h(date('d M Y', strtotime($row['estimated_delivery_date']))); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5">No delivery updates found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>