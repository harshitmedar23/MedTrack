<?php
require_once 'includes/functions.php';
requireEmployee();

$pageTitle = 'Update Delivery Status';
$deliveryId = (int) ($_GET['id'] ?? 0);

if ($deliveryId <= 0) {
    setFlash('error', 'Invalid delivery ID.');
    header('Location: deliveries.php');
    exit;
}

$agents = $conn->query('SELECT delivery_agent_id, agent_name FROM delivery_agents WHERE is_active = 1 ORDER BY agent_name ASC');

$stmt = $conn->prepare(
    'SELECT d.*, b.Bill_ID, b.Total_Amount, o.Order_ID, o.Order_Number, o.Total_Amount AS Order_Total, o.Payment_Status
     FROM delivery_orders d
     LEFT JOIN bill b ON b.Bill_ID = d.bill_id
     LEFT JOIN user_orders o ON o.Order_ID = d.order_id
     WHERE d.delivery_id = ?
     LIMIT 1'
);
$stmt->bind_param('i', $deliveryId);
$stmt->execute();
$result = $stmt->get_result();
$delivery = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$delivery) {
    setFlash('error', 'Delivery order not found.');
    header('Location: deliveries.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_payment_status'])) {
        $newPaymentStatus = trim($_POST['payment_status'] ?? 'Unpaid');
        $allowedPaymentStatuses = ['Unpaid', 'Paid', 'Refunded'];

        if (empty($delivery['Order_ID'])) {
            setFlash('error', 'Payment status is available only for order-linked deliveries.');
        } elseif (!in_array($newPaymentStatus, $allowedPaymentStatuses, true)) {
            setFlash('error', 'Invalid payment status selected.');
        } else {
            $paymentStmt = $conn->prepare('UPDATE user_orders SET Payment_Status = ? WHERE Order_ID = ?');
            if ($paymentStmt) {
                $paymentStmt->bind_param('si', $newPaymentStatus, $delivery['Order_ID']);
                if ($paymentStmt->execute()) {
                    $paymentStmt->close();
                    setFlash('success', 'Payment status updated successfully.');
                    header('Location: delivery_status.php?id=' . $deliveryId);
                    exit;
                }
                $paymentStmt->close();
            }

            setFlash('error', 'Could not update payment status.');
        }
    } elseif (isset($_POST['update_delivery_status'])) {
    $newStatus = trim($_POST['delivery_status'] ?? 'Order Placed');
    $agentId = (int) ($_POST['agent_id'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');
    $allowedStatuses = ['Order Placed', 'Processing', 'Packed', 'Out for Delivery', 'Delivered'];

    if (!in_array($newStatus, $allowedStatuses, true)) {
        setFlash('error', 'Invalid status selected.');
    } else {
        $oldStatus = $delivery['delivery_status'];
        if ($agentId > 0) {
            $updateStmt = $conn->prepare('UPDATE delivery_orders SET delivery_status = ?, agent_id = ? WHERE delivery_id = ?');
            if ($updateStmt) {
                $updateStmt->bind_param('sii', $newStatus, $agentId, $deliveryId);
            }
        } else {
            $updateStmt = $conn->prepare('UPDATE delivery_orders SET delivery_status = ?, agent_id = NULL WHERE delivery_id = ?');
            if ($updateStmt) {
                $updateStmt->bind_param('si', $newStatus, $deliveryId);
            }
        }

        if ($updateStmt) {
            if ($updateStmt->execute()) {
                $updateStmt->close();

                $trackingStmt = $conn->prepare('INSERT INTO delivery_tracking (delivery_id, old_status, new_status, remarks) VALUES (?, ?, ?, ?)');
                if ($trackingStmt) {
                    $trackingStmt->bind_param('isss', $deliveryId, $oldStatus, $newStatus, $remarks);
                    $trackingStmt->execute();
                    $trackingStmt->close();
                }

                setFlash('success', 'Delivery status updated successfully.');
                header('Location: delivery_status.php?id=' . $deliveryId);
                exit;
            }
            $updateStmt->close();
        }
        setFlash('error', 'Could not update delivery status.');
    }
    }
}

$tracking = $conn->prepare('SELECT * FROM delivery_tracking WHERE delivery_id = ? ORDER BY tracking_id DESC');
$tracking->bind_param('i', $deliveryId);
$tracking->execute();
$trackingResult = $tracking->get_result();
$tracking->close();

include 'includes/header.php';
?>
<div class="panel-grid">
    <div class="form-card">
        <h3 class="section-title">Delivery #<?php echo (int) $delivery['delivery_id']; ?> Status</h3>
        <p class="muted">
            <?php if (!empty($delivery['Bill_ID'])): ?>
                Bill #<?php echo (int) $delivery['Bill_ID']; ?>
            <?php elseif (!empty($delivery['Order_ID'])): ?>
                Order #<?php echo h($delivery['Order_Number']); ?>
            <?php else: ?>
                Delivery reference unavailable
            <?php endif; ?>
            | Customer: <?php echo h($delivery['customer_name']); ?>
            <?php if (!empty($delivery['Order_ID'])): ?>
                | Payment: <?php echo h($delivery['Payment_Status'] ?: 'Unpaid'); ?>
            <?php endif; ?>
        </p>

        <form method="post" class="needs-validation">
            <input type="hidden" name="update_delivery_status" value="1">
            <div class="form-grid">
                <div class="form-group full">
                    <label for="agent_id">Delivery Agent</label>
                    <select id="agent_id" name="agent_id">
                        <option value="">Unassigned</option>
                        <?php if ($agents && $agents->num_rows > 0): ?>
                            <?php while ($agentRow = $agents->fetch_assoc()): ?>
                                <option value="<?php echo (int) $agentRow['delivery_agent_id']; ?>" <?php echo ((int) $delivery['agent_id'] === (int) $agentRow['delivery_agent_id']) ? 'selected' : ''; ?>><?php echo h($agentRow['agent_name']); ?></option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group full">
                    <label for="delivery_status">Status</label>
                    <select id="delivery_status" name="delivery_status" required>
                        <?php foreach (['Order Placed', 'Processing', 'Packed', 'Out for Delivery', 'Delivered'] as $status): ?>
                            <option value="<?php echo h($status); ?>" <?php echo $delivery['delivery_status'] === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group full">
                    <label for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" placeholder="Optional status notes"></textarea>
                </div>
            </div>
            <div class="form-actions" style="margin-top: 16px;">
                <button type="submit" class="btn btn-primary">Save Status</button>
                <a class="btn btn-secondary" href="deliveries.php">Back to Deliveries</a>
            </div>
        </form>

        <div class="status-note-card">
            <div class="status-note-header">
                <h4>Payment Status</h4>
                <span class="muted">Order-linked delivery</span>
            </div>

            <?php if (!empty($delivery['Order_ID'])): ?>
                <form method="post" class="needs-validation status-inline-form">
                    <input type="hidden" name="update_payment_status" value="1">
                    <div class="form-grid">
                        <div class="form-group full">
                            <label for="payment_status">Current Payment</label>
                            <select id="payment_status" name="payment_status" required>
                                <?php foreach (['Unpaid', 'Paid', 'Refunded'] as $paymentStatus): ?>
                                    <option value="<?php echo h($paymentStatus); ?>" <?php echo (($delivery['Payment_Status'] ?? 'Unpaid') === $paymentStatus) ? 'selected' : ''; ?>><?php echo h($paymentStatus); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-actions" style="margin-top: 12px;">
                        <button type="submit" class="btn btn-success">Update Payment Status</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-info" style="margin-bottom: 0;">Payment status is only available for order-linked deliveries.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="table-card">
        <h3 class="section-title">Tracking History</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Old Status</th>
                        <th>New Status</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($trackingResult && $trackingResult->num_rows > 0): ?>
                        <?php while ($row = $trackingResult->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo h(date('d M Y, h:i A', strtotime($row['changed_at']))); ?></td>
                                <td><?php echo h($row['old_status'] ?: '-'); ?></td>
                                <td><span class="badge badge-warning"><?php echo h($row['new_status']); ?></span></td>
                                <td><?php echo h($row['remarks'] ?: '-'); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4">No tracking history yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>