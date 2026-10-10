<?php
require_once 'includes/functions.php';
requireAdmin();

$pageTitle = 'Sales Records';
$search = trim($_GET['search'] ?? '');

$where = '';
$params = [];
$types = '';
if ($search !== '') {
    $where = 'WHERE CONCAT(b.Bill_ID, c.Customer_Name, e.Employee_Name, m.Medicine_Name) LIKE ?';
    $like = '%' . $search . '%';
    $params = [$like];
    $types = 's';
}

$sql = "SELECT s.Sale_ID, s.Quantity_Sold, s.Sub_Total, b.Bill_ID, b.Bill_Date, c.Customer_Name, e.Employee_Name, m.Medicine_Name
        FROM sales s
        INNER JOIN bill b ON b.Bill_ID = s.Bill_ID
        INNER JOIN customer c ON c.Customer_ID = b.Customer_ID
        INNER JOIN employee e ON e.Employee_ID = b.Employee_ID
        INNER JOIN medicine m ON m.Medicine_ID = s.Medicine_ID
        {$where}
        ORDER BY s.Sale_ID DESC";
$stmt = $conn->prepare($sql);
if ($search !== '') {
    $like = $params[0];
    $stmt->bind_param($types, $like);
}
$stmt->execute();
$sales = $stmt->get_result();
$stmt->close();

$totalSales = fetchRow($conn, 'SELECT COALESCE(SUM(Total_Amount),0) AS total_amount FROM bill');
$totalBills = getCount($conn, 'bill');
$totalItems = getCount($conn, 'sales');

include 'includes/header.php';
?>
<div class="summary-grid">
    <div class="stats-card">
        <div class="label">Total Bills</div>
        <div class="value"><?php echo (int) $totalBills; ?></div>
        <div class="hint">All generated bills</div>
    </div>
    <div class="stats-card">
        <div class="label">Total Sales Items</div>
        <div class="value"><?php echo (int) $totalItems; ?></div>
        <div class="hint">Line items sold</div>
    </div>
    <div class="stats-card">
        <div class="label">Revenue</div>
        <div class="value"><?php echo money($totalSales['total_amount'] ?? 0); ?></div>
        <div class="hint">Sales value</div>
    </div>
</div>

<div class="card">
    <form method="get" class="page-toolbar">
        <input type="search" name="search" value="<?php echo h($search); ?>" placeholder="Search by bill, customer, employee, or medicine">
        <button class="btn btn-secondary" type="submit">Search</button>
        <a class="btn btn-primary" href="sales.php">Reset</a>
    </form>
</div>

<div class="table-card">
    <h3 class="section-title">Sales Ledger</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Sale ID</th>
                    <th>Bill ID</th>
                    <th>Medicine</th>
                    <th>Quantity</th>
                    <th>Sub Total</th>
                    <th>Customer</th>
                    <th>Employee</th>
                    <th>Bill Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($sales && $sales->num_rows > 0): ?>
                    <?php while ($row = $sales->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo h($row['Sale_ID']); ?></td>
                            <td>#<?php echo h($row['Bill_ID']); ?></td>
                            <td><?php echo h($row['Medicine_Name']); ?></td>
                            <td><?php echo h($row['Quantity_Sold']); ?></td>
                            <td><?php echo money($row['Sub_Total']); ?></td>
                            <td><?php echo h($row['Customer_Name']); ?></td>
                            <td><?php echo h($row['Employee_Name']); ?></td>
                            <td><?php echo h(date('d M Y, h:i A', strtotime($row['Bill_Date']))); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8">No sales records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'includes/footer.php'; ?>