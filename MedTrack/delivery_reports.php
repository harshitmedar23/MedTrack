<?php
require_once 'includes/functions.php';
requireEmployee();

$pageTitle = 'Delivery Reports';
$reportDate = $_GET['report_date'] ?? date('Y-m-d');
$reportMonth = $_GET['report_month'] ?? date('Y-m');

$monthDate = DateTime::createFromFormat('Y-m', $reportMonth);
if (!$monthDate) {
    $monthDate = new DateTime('first day of this month');
}
$monthStart = $monthDate->format('Y-m-01');
$monthEnd = (clone $monthDate)->modify('first day of next month')->format('Y-m-01');

function deliveryCsvDownload(string $filename, array $header, mysqli_result $result): void
{
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $handle = fopen('php://output', 'w');
    fputcsv($handle, $header);
    while ($row = $result->fetch_assoc()) {
        fputcsv($handle, $row);
    }
    fclose($handle);
    exit;
}

$dailyQuery = $conn->prepare(
    'SELECT d.delivery_id, d.bill_id, d.order_id, d.customer_name, d.customer_phone, d.delivery_status, d.estimated_delivery_date, d.created_at, a.agent_name
     FROM delivery_orders d
     LEFT JOIN delivery_agents a ON a.delivery_agent_id = d.agent_id
     WHERE DATE(d.created_at) = ?
     ORDER BY d.delivery_id DESC'
);
$dailyQuery->bind_param('s', $reportDate);
$dailyQuery->execute();
$dailyDeliveries = $dailyQuery->get_result();
$dailyQuery->close();

$monthlyQuery = $conn->prepare(
    'SELECT d.delivery_id, d.bill_id, d.order_id, d.customer_name, d.customer_phone, d.delivery_status, d.estimated_delivery_date, d.created_at, a.agent_name
     FROM delivery_orders d
     LEFT JOIN delivery_agents a ON a.delivery_agent_id = d.agent_id
     WHERE d.created_at >= ? AND d.created_at < ?
     ORDER BY d.delivery_id DESC'
);
$monthlyQuery->bind_param('ss', $monthStart, $monthEnd);
$monthlyQuery->execute();
$monthlyDeliveries = $monthlyQuery->get_result();
$monthlyQuery->close();

$dailyTotals = fetchRow($conn, "SELECT COUNT(*) AS total_orders, SUM(delivery_status = 'Delivered') AS delivered_count FROM delivery_orders WHERE DATE(created_at) = '" . $conn->real_escape_string($reportDate) . "'");
$monthlyTotals = fetchRow($conn, "SELECT COUNT(*) AS total_orders, SUM(delivery_status = 'Delivered') AS delivered_count FROM delivery_orders WHERE created_at >= '" . $conn->real_escape_string($monthStart) . "' AND created_at < '" . $conn->real_escape_string($monthEnd) . "'");

if (isset($_GET['export']) && $_GET['export'] === 'daily') {
    $exportStmt = $conn->prepare(
        'SELECT d.delivery_id AS `Delivery ID`, COALESCE(d.bill_id, d.order_id) AS `Reference`, d.customer_name AS `Customer`, d.customer_phone AS `Phone`, d.delivery_status AS `Status`, d.estimated_delivery_date AS `ETA`, d.created_at AS `Created At`, COALESCE(a.agent_name, "Unassigned") AS `Agent`
         FROM delivery_orders d
         LEFT JOIN delivery_agents a ON a.delivery_agent_id = d.agent_id
         WHERE DATE(d.created_at) = ?
         ORDER BY d.delivery_id DESC'
    );
    $exportStmt->bind_param('s', $reportDate);
    $exportStmt->execute();
    deliveryCsvDownload('delivery-report-' . $reportDate . '.csv', ['Delivery ID', 'Reference', 'Customer', 'Phone', 'Status', 'ETA', 'Created At', 'Agent'], $exportStmt->get_result());
}

if (isset($_GET['export']) && $_GET['export'] === 'monthly') {
    $exportStmt = $conn->prepare(
        'SELECT d.delivery_id AS `Delivery ID`, COALESCE(d.bill_id, d.order_id) AS `Reference`, d.customer_name AS `Customer`, d.customer_phone AS `Phone`, d.delivery_status AS `Status`, d.estimated_delivery_date AS `ETA`, d.created_at AS `Created At`, COALESCE(a.agent_name, "Unassigned") AS `Agent`
         FROM delivery_orders d
         LEFT JOIN delivery_agents a ON a.delivery_agent_id = d.agent_id
         WHERE d.created_at >= ? AND d.created_at < ?
         ORDER BY d.delivery_id DESC'
    );
    $exportStmt->bind_param('ss', $monthStart, $monthEnd);
    $exportStmt->execute();
    deliveryCsvDownload('delivery-report-' . $monthDate->format('Y-m') . '.csv', ['Delivery ID', 'Reference', 'Customer', 'Phone', 'Status', 'ETA', 'Created At', 'Agent'], $exportStmt->get_result());
}

include 'includes/header.php';
?>
<div class="summary-grid">
    <div class="stats-card stats-blue">
        <div class="label">Daily Orders</div>
        <div class="value"><?php echo (int) ($dailyTotals['total_orders'] ?? 0); ?></div>
        <div class="hint">Delivered: <?php echo (int) ($dailyTotals['delivered_count'] ?? 0); ?></div>
    </div>
    <div class="stats-card stats-blue">
        <div class="label">Monthly Orders</div>
        <div class="value"><?php echo (int) ($monthlyTotals['total_orders'] ?? 0); ?></div>
        <div class="hint">Delivered: <?php echo (int) ($monthlyTotals['delivered_count'] ?? 0); ?></div>
    </div>
</div>

<div class="panel-grid">
    <div class="report-card">
        <h3 class="section-title">Daily Delivery Report</h3>
        <form method="get" class="page-toolbar" style="margin-bottom: 14px;">
            <input type="date" name="report_date" value="<?php echo h($reportDate); ?>">
            <button class="btn btn-secondary" type="submit">Show Day</button>
            <a class="btn btn-primary" href="delivery_reports.php?export=daily&report_date=<?php echo h($reportDate); ?>">Export CSV</a>
        </form>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Delivery ID</th>
                        <th>Bill ID</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Agent</th>
                        <th>ETA</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($dailyDeliveries && $dailyDeliveries->num_rows > 0): ?>
                        <?php while ($row = $dailyDeliveries->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo (int) $row['delivery_id']; ?></td>
                                <td><?php echo !empty($row['bill_id']) ? '#' . (int) $row['bill_id'] : (!empty($row['order_id']) ? 'Order #' . h($row['order_id']) : '-'); ?></td>
                                <td><?php echo h($row['customer_name']); ?></td>
                                <td><?php echo h($row['delivery_status']); ?></td>
                                <td><?php echo h($row['agent_name'] ?: 'Unassigned'); ?></td>
                                <td><?php echo h(date('d M Y', strtotime($row['estimated_delivery_date']))); ?></td>
                                <td><?php echo h(date('d M Y, h:i A', strtotime($row['created_at']))); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7">No delivery orders found for this day.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="report-card">
        <h3 class="section-title">Monthly Delivery Report</h3>
        <form method="get" class="page-toolbar" style="margin-bottom: 14px;">
            <input type="month" name="report_month" value="<?php echo h($reportMonth); ?>">
            <button class="btn btn-secondary" type="submit">Show Month</button>
            <a class="btn btn-primary" href="delivery_reports.php?export=monthly&report_month=<?php echo h($reportMonth); ?>">Export CSV</a>
        </form>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Delivery ID</th>
                        <th>Bill ID</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Agent</th>
                        <th>ETA</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($monthlyDeliveries && $monthlyDeliveries->num_rows > 0): ?>
                        <?php while ($row = $monthlyDeliveries->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo (int) $row['delivery_id']; ?></td>
                                <td><?php echo !empty($row['bill_id']) ? '#' . (int) $row['bill_id'] : (!empty($row['order_id']) ? 'Order #' . h($row['order_id']) : '-'); ?></td>
                                <td><?php echo h($row['customer_name']); ?></td>
                                <td><?php echo h($row['delivery_status']); ?></td>
                                <td><?php echo h($row['agent_name'] ?: 'Unassigned'); ?></td>
                                <td><?php echo h(date('d M Y', strtotime($row['estimated_delivery_date']))); ?></td>
                                <td><?php echo h(date('d M Y, h:i A', strtotime($row['created_at']))); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7">No delivery orders found for this month.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>