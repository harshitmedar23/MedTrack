<?php
require_once 'includes/functions.php';
requireAdmin();

$pageTitle = 'Dashboard';

$totalMedicines = getCount($conn, 'medicine');
$totalCustomers = getCount($conn, 'customer');
$totalSuppliers = getCount($conn, 'supplier');
$totalEmployees = getCount($conn, 'employee');
$activeEmployeeLogins = getCount($conn, 'employee_login', "WHERE Status = 'Active'");
$totalBills = getCount($conn, 'bill');
$totalSalesValue = fetchRow($conn, 'SELECT COALESCE(SUM(Total_Amount), 0) AS total_amount FROM bill');
$lowStock = getCount($conn, 'medicine', 'WHERE Quantity <= 10 AND Is_Active = 1');
$expired = getCount($conn, 'medicine', 'WHERE Expiry_Date < CURDATE() AND Is_Active = 1');
$expiringSoon = getCount($conn, 'medicine', 'WHERE Expiry_Date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND Is_Active = 1');

$topMedicines = $conn->query(
    "SELECT m.Medicine_Name, SUM(s.Quantity_Sold) AS total_qty, SUM(s.Sub_Total) AS total_revenue
     FROM sales s
     INNER JOIN medicine m ON m.Medicine_ID = s.Medicine_ID
     GROUP BY m.Medicine_ID, m.Medicine_Name
     ORDER BY total_qty DESC
     LIMIT 5"
);

$bestCustomers = $conn->query(
    "SELECT c.Customer_Name, SUM(b.Total_Amount) AS total_spent, COUNT(b.Bill_ID) AS bill_count
     FROM bill b
     INNER JOIN customer c ON c.Customer_ID = b.Customer_ID
     GROUP BY c.Customer_ID, c.Customer_Name
     ORDER BY total_spent DESC
     LIMIT 5"
);

$recentBills = $conn->query(
    "SELECT b.Bill_ID, b.Total_Amount, b.Bill_Date, c.Customer_Name, e.Employee_Name
     FROM bill b
     INNER JOIN customer c ON c.Customer_ID = b.Customer_ID
     INNER JOIN employee e ON e.Employee_ID = b.Employee_ID
     ORDER BY b.Bill_Date DESC
     LIMIT 5"
);

$lowStockMedicines = $conn->query(
    "SELECT Medicine_Name, Quantity
     FROM medicine
     WHERE Quantity <= 10
     ORDER BY Quantity ASC
     LIMIT 5"
);

$expiredMedicines = $conn->query(
    "SELECT Medicine_Name, Expiry_Date
     FROM medicine
     WHERE Expiry_Date < CURDATE()
     ORDER BY Expiry_Date ASC
     LIMIT 5"
);

$employeeSnapshot = $conn->query(
    "SELECT e.Employee_Name, e.Role, e.Phone, l.Username, l.Status
     FROM employee_login l
     INNER JOIN employee e ON e.Employee_ID = l.Employee_ID
     ORDER BY l.Created_At DESC, e.Employee_Name ASC
     LIMIT 5"
);

include 'includes/header.php';
?>
<div class="hero-strip dashboard-hero compact glass-card">
    <div>
        <h2>Pharmacy control center</h2>
        <p>Track medicines, suppliers, billing, and stock alerts from one clean workspace.</p>
        <div class="btn-row" style="margin-top: 14px;">
            <a class="btn btn-primary" href="add_medicine.php">Add Medicine</a>
            <a class="btn btn-secondary" href="billing.php">Open Billing</a>
        </div>
    </div>
    <div class="dashboard-hero-metrics">
        <div>
            <span class="muted">Bills today</span>
            <strong><?php echo (int) $totalBills; ?></strong>
        </div>
        <div>
            <span class="muted">Revenue</span>
            <strong><?php echo money($totalSalesValue['total_amount'] ?? 0); ?></strong>
        </div>
    </div>
</div>

<div class="summary-grid">
    <div class="stats-card stats-blue">
        <div class="label">Total Medicines</div>
        <div class="value"><?php echo (int) $totalMedicines; ?></div>
        <div class="hint">All medicine records</div>
    </div>
    <div class="stats-card stats-blue">
        <div class="label">Total Customers</div>
        <div class="value"><?php echo (int) $totalCustomers; ?></div>
        <div class="hint">Registered customers</div>
    </div>
    <div class="stats-card stats-blue">
        <div class="label">Total Suppliers</div>
        <div class="value"><?php echo (int) $totalSuppliers; ?></div>
        <div class="hint">Active suppliers</div>
    </div>
    <div class="stats-card stats-blue">
        <div class="label">Employees</div>
        <div class="value"><?php echo (int) $totalEmployees; ?></div>
        <div class="hint"><?php echo (int) $activeEmployeeLogins; ?> active logins</div>
    </div>
    <div class="stats-card stats-blue">
        <div class="label">Total Sales</div>
        <div class="value"><?php echo money($totalSalesValue['total_amount'] ?? 0); ?></div>
        <div class="hint">Revenue generated</div>
    </div>
    <div class="stats-card stats-warning">
        <div class="label">Low Stock Alerts</div>
        <div class="value"><?php echo (int) $lowStock; ?></div>
        <div class="hint">Quantity 10 or below</div>
    </div>
    <div class="stats-card stats-danger">
        <div class="label">Expired Medicines</div>
        <div class="value"><?php echo (int) $expired; ?></div>
        <div class="hint">Needs immediate action</div>
    </div>
    <div class="stats-card stats-warning">
        <div class="label">Expiring in 30 Days</div>
        <div class="value"><?php echo (int) $expiringSoon; ?></div>
        <div class="hint">Soon-to-expire stock</div>
    </div>
</div>

<div class="hero-strip glass-card">
    <div>
        <h2>Welcome back, <?php echo h(currentAdminName()); ?></h2>
        <p>This dashboard gives a quick view of stock, billing, suppliers, and alerts.</p>
    </div>
    <div class="inline-kpi">
        <strong><?php echo (int) $totalBills; ?></strong>
        <span class="muted">Bills generated</span>
    </div>
</div>

<div class="card">
    <h3 class="section-title">Quick Actions</h3>
    <div class="btn-row">
        <a class="btn btn-primary" href="medicines.php">Manage Medicines</a>
        <a class="btn btn-secondary" href="suppliers.php">Manage Suppliers</a>
        <a class="btn btn-secondary" href="customers.php">Manage Customers</a>
        <a class="btn btn-secondary" href="employees.php">Manage Employees</a>
        <a class="btn btn-secondary" href="reports.php">View Reports</a>
    </div>
</div>

<div class="panel-grid">
    <div class="table-card">
        <h3 class="section-title">Recent Bills</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Bill ID</th>
                        <th>Customer</th>
                        <th>Employee</th>
                        <th>Total</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recentBills && $recentBills->num_rows > 0): ?>
                        <?php while ($row = $recentBills->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo h($row['Bill_ID']); ?></td>
                                <td><?php echo h($row['Customer_Name']); ?></td>
                                <td><?php echo h($row['Employee_Name']); ?></td>
                                <td><?php echo money($row['Total_Amount']); ?></td>
                                <td><?php echo h(date('d M Y, h:i A', strtotime($row['Bill_Date']))); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5">No bills found yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h3 class="section-title">Alerts</h3>
        <div class="quick-list">
            <div class="quick-item">
                <span>Low Stock Medicines</span>
                <strong><?php echo (int) $lowStock; ?></strong>
            </div>
            <div class="quick-item">
                <span>Expired Medicines</span>
                <strong><?php echo (int) $expired; ?></strong>
            </div>
            <div class="quick-item">
                <span>Total Bills</span>
                <strong><?php echo (int) $totalBills; ?></strong>
            </div>
        </div>
    </div>
</div>

<div class="panel-grid">
    <div class="table-card">
        <h3 class="section-title">Low Stock Medicines</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Medicine</th><th>Quantity</th></tr>
                </thead>
                <tbody>
                    <?php if ($lowStockMedicines && $lowStockMedicines->num_rows > 0): ?>
                        <?php while ($row = $lowStockMedicines->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo h($row['Medicine_Name']); ?></td>
                                <td><span class="badge badge-warning"><?php echo h($row['Quantity']); ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="2">No low stock medicines right now.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="table-card">
        <h3 class="section-title">Expired Medicines</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Medicine</th><th>Expiry Date</th></tr>
                </thead>
                <tbody>
                    <?php if ($expiredMedicines && $expiredMedicines->num_rows > 0): ?>
                        <?php while ($row = $expiredMedicines->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo h($row['Medicine_Name']); ?></td>
                                <td><span class="badge badge-danger"><?php echo h(date('d M Y', strtotime($row['Expiry_Date']))); ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="2">No expired medicines found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="panel-grid">
    <div class="table-card">
        <h3 class="section-title">Top Selling Medicines</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Medicine</th>
                        <th>Quantity Sold</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($topMedicines && $topMedicines->num_rows > 0): ?>
                        <?php while ($row = $topMedicines->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo h($row['Medicine_Name']); ?></td>
                                <td><?php echo (int) $row['total_qty']; ?></td>
                                <td><?php echo money($row['total_revenue']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3">No sales yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="table-card">
        <h3 class="section-title">Best Customers</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Bills</th>
                        <th>Total Spent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($bestCustomers && $bestCustomers->num_rows > 0): ?>
                        <?php while ($row = $bestCustomers->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo h($row['Customer_Name']); ?></td>
                                <td><?php echo (int) $row['bill_count']; ?></td>
                                <td><?php echo money($row['total_spent']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3">No customer sales yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="table-card">
    <h3 class="section-title">Recent Employee Accounts</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Username</th>
                    <th>Status</th>
                    <th>Phone</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($employeeSnapshot && $employeeSnapshot->num_rows > 0): ?>
                    <?php while ($row = $employeeSnapshot->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo h($row['Employee_Name']); ?></td>
                            <td><?php echo h($row['Role']); ?></td>
                            <td><?php echo h($row['Username']); ?></td>
                            <td><span class="order-status-badge"><?php echo h($row['Status']); ?></span></td>
                            <td><?php echo h($row['Phone']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5">No employee accounts found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'includes/footer.php'; ?>