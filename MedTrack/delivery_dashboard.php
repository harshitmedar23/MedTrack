<?php
require_once 'includes/functions.php';
requireEmployee();

$pageTitle = 'Delivery Dashboard';

$totalDeliveries = getCount($conn, 'delivery_orders');
$pendingDeliveries = getCount($conn, 'delivery_orders', "WHERE delivery_status IN ('Order Placed', 'Processing', 'Packed')");
$outForDelivery = getCount($conn, 'delivery_orders', "WHERE delivery_status = 'Out for Delivery'");
$deliveredCount = getCount($conn, 'delivery_orders', "WHERE delivery_status = 'Delivered'");

$recentDeliveries = $conn->query(
    "SELECT d.delivery_id, d.bill_id, d.order_id, d.customer_name, d.delivery_status, d.estimated_delivery_date, d.created_at, a.agent_name
     FROM delivery_orders d
     LEFT JOIN delivery_agents a ON a.delivery_agent_id = d.agent_id
     ORDER BY d.delivery_id DESC
     LIMIT 10"
);

include 'includes/header.php';
?>
<div class="summary-grid">
    <div class="stats-card stats-blue">
        <div class="label">Total Deliveries</div>
        <div class="value"><?php echo (int) $totalDeliveries; ?></div>
        <div class="hint">All delivery orders</div>
    </div>
    <div class="stats-card stats-warning">
        <div class="label">Pending</div>
        <div class="value"><?php echo (int) $pendingDeliveries; ?></div>
        <div class="hint">Placed, processing, packed</div>
    </div>
    <div class="stats-card stats-blue">
        <div class="label">Out for Delivery</div>
        <div class="value"><?php echo (int) $outForDelivery; ?></div>
        <div class="hint">On route to customer</div>
    </div>
    <div class="stats-card stats-success">
        <div class="label">Delivered</div>
        <div class="value"><?php echo (int) $deliveredCount; ?></div>
        <div class="hint">Completed deliveries</div>
    </div>
</div>

<div class="card">
    <h3 class="section-title">Quick Links</h3>
    <div class="btn-row">
        <a class="btn btn-primary" href="deliveries.php">Delivery Orders</a>
        <a class="btn btn-secondary" href="delivery_agents.php">Delivery Agents</a>
        <a class="btn btn-secondary" href="delivery_reports.php">Delivery Reports</a>
    </div>
</div>

<div class="table-card">
    <h3 class="section-title">Recent Deliveries</h3>
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
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recentDeliveries && $recentDeliveries->num_rows > 0): ?>
                    <?php while ($row = $recentDeliveries->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo (int) $row['delivery_id']; ?></td>
                            <td><?php echo !empty($row['bill_id']) ? '#' . (int) $row['bill_id'] : 'Order #' . (int) $row['order_id']; ?></td>
                            <td><?php echo h($row['customer_name']); ?></td>
                            <td><span class="badge badge-warning"><?php echo h($row['delivery_status']); ?></span></td>
                            <td><?php echo h($row['agent_name'] ?: 'Unassigned'); ?></td>
                            <td><?php echo h(date('d M Y', strtotime($row['estimated_delivery_date']))); ?></td>
                            <td><?php echo h(date('d M Y, h:i A', strtotime($row['created_at']))); ?></td>
                            <td><a class="btn btn-secondary" href="delivery_status.php?id=<?php echo (int) $row['delivery_id']; ?>">Update</a></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8">No delivery orders found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'includes/footer.php'; ?>