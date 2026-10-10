<?php
require_once 'includes/functions.php';
requireEmployee();

$pageTitle = 'Delivery Orders';

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$whereParts = [];
$params = [];
$types = '';

if ($search !== '') {
    $whereParts[] = '(d.customer_name LIKE ? OR d.customer_phone LIKE ? OR d.delivery_id LIKE ? OR d.bill_id LIKE ?)';
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like]);
    $types .= 'ssss';
}

if ($statusFilter !== '') {
    $whereParts[] = 'd.delivery_status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}

$where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

$sql = "SELECT d.delivery_id, d.bill_id, d.order_id, d.customer_name, d.customer_phone, d.delivery_address, d.estimated_delivery_date, d.delivery_status, d.created_at, a.agent_name
        FROM delivery_orders d
        LEFT JOIN delivery_agents a ON a.delivery_agent_id = d.agent_id
        {$where}
        ORDER BY d.delivery_id DESC";

$stmt = $conn->prepare($sql);
if ($search !== '' && $statusFilter !== '') {
    $stmt->bind_param($types, $params[0], $params[1], $params[2], $params[3], $params[4]);
} elseif ($search !== '') {
    $stmt->bind_param($types, $params[0], $params[1], $params[2], $params[3]);
} elseif ($statusFilter !== '') {
    $stmt->bind_param($types, $params[0]);
}
$stmt->execute();
$deliveries = $stmt->get_result();
$stmt->close();

include 'includes/header.php';
?>
<div class="card">
    <form method="get" class="page-toolbar">
        <input type="search" name="search" value="<?php echo h($search); ?>" placeholder="Search by customer, phone, bill or delivery ID...">
        <select name="status">
            <option value="">All Statuses</option>
            <?php foreach (['Order Placed', 'Processing', 'Packed', 'Out for Delivery', 'Delivered'] as $status): ?>
                <option value="<?php echo h($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-secondary" type="submit">Search</button>
        <a class="btn btn-primary" href="deliveries.php">Reset</a>
    </form>
</div>

<div class="table-card">
    <h3 class="section-title">Delivery Orders</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Bill</th>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Agent</th>
                    <th>ETA</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($deliveries && $deliveries->num_rows > 0): ?>
                    <?php while ($row = $deliveries->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo (int) $row['delivery_id']; ?></td>
                            <td><?php echo !empty($row['bill_id']) ? '#' . (int) $row['bill_id'] : (!empty($row['order_id']) ? 'Order #' . h($row['order_id']) : '-'); ?></td>
                            <td><?php echo h($row['customer_name']); ?></td>
                            <td><?php echo h($row['customer_phone']); ?></td>
                            <td><span class="badge badge-warning"><?php echo h($row['delivery_status']); ?></span></td>
                            <td><?php echo h($row['agent_name'] ?: 'Unassigned'); ?></td>
                            <td><?php echo h(date('d M Y', strtotime($row['estimated_delivery_date']))); ?></td>
                            <td><a class="btn btn-secondary" href="delivery_status.php?id=<?php echo (int) $row['delivery_id']; ?>">Update Status</a></td>
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