<?php
require_once 'includes/functions.php';
requireEmployee();

$pageTitle = 'Sales Reports';

$reportDate = $_GET['report_date'] ?? date('Y-m-d');
$reportMonth = $_GET['report_month'] ?? date('Y-m');

$monthDate = DateTime::createFromFormat('Y-m', $reportMonth);
if (!$monthDate) {
    $monthDate = new DateTime('first day of this month');
}
$monthStart = $monthDate->format('Y-m-01');
$monthEnd = (clone $monthDate)->modify('first day of next month')->format('Y-m-01');

$dailyStmt = $conn->prepare(
    "SELECT
        b.Bill_ID,
        b.Bill_Date,
        c.Customer_Name,
        m.Medicine_Name,
        s.Quantity_Sold,
        s.Sub_Total
     FROM sales s
     INNER JOIN bill b ON b.Bill_ID = s.Bill_ID
     INNER JOIN customer c ON c.Customer_ID = b.Customer_ID
     INNER JOIN medicine m ON m.Medicine_ID = s.Medicine_ID
     WHERE DATE(b.Bill_Date) = ?
     ORDER BY b.Bill_Date DESC, s.Sale_ID DESC"
);
$dailyStmt->bind_param('s', $reportDate);
$dailyStmt->execute();
$dailySales = $dailyStmt->get_result();
$dailyStmt->close();

$dailyTotalStmt = $conn->prepare(
    "SELECT
        COUNT(DISTINCT b.Bill_ID) AS bill_count,
        COALESCE(SUM(s.Quantity_Sold), 0) AS qty_sold,
        COALESCE(SUM(s.Sub_Total), 0) AS total_amount
     FROM sales s
     INNER JOIN bill b ON b.Bill_ID = s.Bill_ID
     WHERE DATE(b.Bill_Date) = ?"
);
$dailyTotalStmt->bind_param('s', $reportDate);
$dailyTotalStmt->execute();
$dailySummary = $dailyTotalStmt->get_result()->fetch_assoc();
$dailyTotalStmt->close();

$monthlyStmt = $conn->prepare(
    "SELECT
        b.Bill_ID,
        b.Bill_Date,
        c.Customer_Name,
        m.Medicine_Name,
        s.Quantity_Sold,
        s.Sub_Total
     FROM sales s
     INNER JOIN bill b ON b.Bill_ID = s.Bill_ID
     INNER JOIN customer c ON c.Customer_ID = b.Customer_ID
     INNER JOIN medicine m ON m.Medicine_ID = s.Medicine_ID
     WHERE b.Bill_Date >= ? AND b.Bill_Date < ?
     ORDER BY b.Bill_Date DESC, s.Sale_ID DESC"
);
$monthlyStmt->bind_param('ss', $monthStart, $monthEnd);
$monthlyStmt->execute();
$monthlySales = $monthlyStmt->get_result();
$monthlyStmt->close();

$monthlyTotalStmt = $conn->prepare(
    "SELECT
        COUNT(DISTINCT b.Bill_ID) AS bill_count,
        COALESCE(SUM(s.Quantity_Sold), 0) AS qty_sold,
        COALESCE(SUM(s.Sub_Total), 0) AS total_amount
     FROM sales s
     INNER JOIN bill b ON b.Bill_ID = s.Bill_ID
     WHERE b.Bill_Date >= ? AND b.Bill_Date < ?"
);
$monthlyTotalStmt->bind_param('ss', $monthStart, $monthEnd);
$monthlyTotalStmt->execute();
$monthlySummary = $monthlyTotalStmt->get_result()->fetch_assoc();
$monthlyTotalStmt->close();

function exportSalesCsv(string $filename, array $rows): void
{
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $handle = fopen('php://output', 'w');
    if (!empty($rows)) {
        fputcsv($handle, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
    }
    fclose($handle);
    exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'daily') {
    $exportRows = [];
    if ($dailySales) {
        $dailySales->data_seek(0);
        while ($row = $dailySales->fetch_assoc()) {
            $exportRows[] = $row;
        }
        $dailySales->data_seek(0);
    }
    exportSalesCsv('daily-sales-' . $reportDate . '.csv', $exportRows);
}

if (isset($_GET['export']) && $_GET['export'] === 'monthly') {
    $exportRows = [];
    if ($monthlySales) {
        $monthlySales->data_seek(0);
        while ($row = $monthlySales->fetch_assoc()) {
            $exportRows[] = $row;
        }
        $monthlySales->data_seek(0);
    }
    exportSalesCsv('monthly-sales-' . $monthDate->format('Y-m') . '.csv', $exportRows);
}

include 'includes/header.php';
?>
<div class="summary-grid">
    <div class="stats-card">
        <div class="label">Daily Revenue</div>
        <div class="value"><?php echo money($dailySummary['total_amount'] ?? 0); ?></div>
        <div class="hint"><?php echo (int) ($dailySummary['bill_count'] ?? 0); ?> bills | <?php echo (int) ($dailySummary['qty_sold'] ?? 0); ?> items on <?php echo h($reportDate); ?></div>
    </div>
    <div class="stats-card">
        <div class="label">Monthly Revenue</div>
        <div class="value"><?php echo money($monthlySummary['total_amount'] ?? 0); ?></div>
        <div class="hint"><?php echo (int) ($monthlySummary['bill_count'] ?? 0); ?> bills | <?php echo (int) ($monthlySummary['qty_sold'] ?? 0); ?> items in <?php echo h($monthDate->format('F Y')); ?></div>
    </div>
</div>

<div class="panel-grid">
    <div class="report-card">
        <h3 class="section-title">Daily Sales Report</h3>
        <form method="get" class="page-toolbar" style="margin-bottom: 14px;">
            <input type="month" name="report_month" value="<?php echo h($reportMonth); ?>">
            <input type="date" name="report_date" value="<?php echo h($reportDate); ?>">
            <button class="btn btn-secondary" type="submit">Show Day</button>
            <a class="btn btn-primary" href="reports.php?export=daily&amp;report_date=<?php echo h($reportDate); ?>&amp;report_month=<?php echo h($reportMonth); ?>">Export CSV</a>
        </form>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Bill ID</th>
                        <th>Customer</th>
                        <th>Medicine</th>
                        <th>Quantity Sold</th>
                        <th>Revenue</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($dailySales && $dailySales->num_rows > 0): ?>
                        <?php while ($row = $dailySales->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo h($row['Bill_ID']); ?></td>
                                <td><?php echo h($row['Customer_Name']); ?></td>
                                <td><?php echo h($row['Medicine_Name']); ?></td>
                                <td><?php echo (int) $row['Quantity_Sold']; ?></td>
                                <td><?php echo money($row['Sub_Total']); ?></td>
                                <td><?php echo h(date('d M Y, h:i A', strtotime($row['Bill_Date']))); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6">No sales found for this day.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="report-card">
        <h3 class="section-title">Monthly Sales Report</h3>
        <form method="get" class="page-toolbar" style="margin-bottom: 14px;">
            <input type="month" name="report_month" value="<?php echo h($reportMonth); ?>">
            <input type="date" name="report_date" value="<?php echo h($reportDate); ?>">
            <button class="btn btn-secondary" type="submit">Show Month</button>
            <a class="btn btn-primary" href="reports.php?export=monthly&amp;report_month=<?php echo h($reportMonth); ?>&amp;report_date=<?php echo h($reportDate); ?>">Export CSV</a>
        </form>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Bill ID</th>
                        <th>Customer</th>
                        <th>Medicine</th>
                        <th>Quantity Sold</th>
                        <th>Revenue</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($monthlySales && $monthlySales->num_rows > 0): ?>
                        <?php while ($row = $monthlySales->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo h($row['Bill_ID']); ?></td>
                                <td><?php echo h($row['Customer_Name']); ?></td>
                                <td><?php echo h($row['Medicine_Name']); ?></td>
                                <td><?php echo (int) $row['Quantity_Sold']; ?></td>
                                <td><?php echo money($row['Sub_Total']); ?></td>
                                <td><?php echo h(date('d M Y, h:i A', strtotime($row['Bill_Date']))); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6">No sales found for this month.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>