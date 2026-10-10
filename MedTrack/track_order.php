<?php
require_once 'includes/functions.php';
requireCustomer();

$customerId = getCustomerIdFromSession();
$cartCount = getCustomerCartItemCount($conn, $customerId);
$pageTitle = 'Track Order';
$orderId = (int) ($_GET['order_id'] ?? ($_GET['view'] ?? 0));

$order = null;
$trackingRows = [];

if ($orderId <= 0) {
    $latestStmt = $conn->prepare('SELECT Order_ID FROM user_orders WHERE User_ID = ? ORDER BY Order_ID DESC LIMIT 1');
    $latestStmt->bind_param('i', $customerId);
    $latestStmt->execute();
    $latestResult = $latestStmt->get_result();
    if ($latestResult && ($latestRow = $latestResult->fetch_assoc())) {
        $orderId = (int) $latestRow['Order_ID'];
    }
    $latestStmt->close();
}

if ($orderId > 0) {
    $stmt = $conn->prepare(
        'SELECT o.Order_ID, o.Order_Number, o.Order_Status, o.Total_Amount, o.Placed_At, o.Shipping_Address, d.delivery_id, d.delivery_status, d.estimated_delivery_date, d.customer_name, d.customer_phone, d.customer_email, a.agent_name, a.phone AS agent_phone
         FROM user_orders o
         LEFT JOIN delivery_orders d ON d.order_id = o.Order_ID
         LEFT JOIN delivery_agents a ON a.delivery_agent_id = d.agent_id
         WHERE o.Order_ID = ? AND o.User_ID = ?
         LIMIT 1'
    );
    if ($stmt) {
        $stmt->bind_param('ii', $orderId, $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($order && !empty($order['delivery_id'])) {
            $trackingStmt = $conn->prepare('SELECT old_status, new_status, remarks, changed_at FROM delivery_tracking WHERE delivery_id = ? ORDER BY tracking_id ASC');
            if ($trackingStmt) {
                $deliveryId = (int) $order['delivery_id'];
                $trackingStmt->bind_param('i', $deliveryId);
                $trackingStmt->execute();
                $trackingResult = $trackingStmt->get_result();
                while ($trackingResult && ($row = $trackingResult->fetch_assoc())) {
                    $trackingRows[] = $row;
                }
                $trackingStmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=20260531a">
    <script defer src="assets/js/app.js?v=20260531a"></script>
</head>
<body>
<div class="customer-shell customer-shell-hero">
    <div class="customer-page">
        <?php echo renderCustomerNavbar('track', $cartCount); ?>

        <section class="customer-hero card compact" style="padding:16px;margin-bottom:10px;">
            <div class="customer-hero-copy">
                <span class="hero-kicker">Delivery tracking</span>
                <h2 style="margin:6px 0 0;font-size:20px;">See where your medicine order is and how far delivery has progressed.</h2>
                <p style="margin-top:6px;">Stage indicators, agent details, and live status cards make delivery updates easier to read on any device.</p>
            </div>
            <div class="customer-hero-card">
                <div class="hero-stat">
                    <span>Tracking focus</span>
                    <strong>Delivery progress</strong>
                </div>
                <div class="hero-stat">
                    <span>Current order</span>
                    <strong><?php echo $order ? h($order['Order_Number']) : 'None'; ?></strong>
                </div>
            </div>
        </section>

        <div class="toast-stack">
            <?php echo renderFlash(); ?>
        </div>

        <section class="customer-section customer-section-grid tracking-grid">
            <div class="cart-total-box tracking-summary-card">
                <h3 class="section-title">Delivery overview</h3>
                <?php if ($order): ?>
                    <?php $progressIndex = customerProgressIndex($order['delivery_status'] ?? $order['Order_Status']); ?>
                    <div class="progress-tracker">
                        <?php foreach (customerOrderProgressSteps() as $stepIndex => $step): ?>
                            <div class="progress-step <?php echo $stepIndex <= $progressIndex ? 'complete' : ''; ?> <?php echo $stepIndex === $progressIndex ? 'current' : ''; ?>" style="padding:8px 10px;border-radius:12px;">
                                <span style="width:26px;height:26px;font-size:12px;"><?php echo (int) ($stepIndex + 1); ?></span>
                                <strong style="font-size:13px;"><?php echo h($step); ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="order-summary-list" style="margin-top: 18px;">
                        <div class="order-summary-row"><span>Order Number</span><strong><?php echo h($order['Order_Number']); ?></strong></div>
                        <div class="order-summary-row"><span>Order Status</span><strong class="order-status-badge"><?php echo h(normalizeOrderStatus($order['Order_Status'])); ?></strong></div>
                        <div class="order-summary-row"><span>Delivery Status</span><strong class="order-status-badge"><?php echo h($order['delivery_status'] ?? 'Pending'); ?></strong></div>
                        <div class="order-summary-row"><span>Estimated Delivery</span><strong><?php echo !empty($order['estimated_delivery_date']) ? h(date('d M Y', strtotime($order['estimated_delivery_date']))) : 'Pending'; ?></strong></div>
                        <div class="order-summary-row"><span>Assigned Agent</span><strong><?php echo h($order['agent_name'] ?: 'Unassigned'); ?></strong></div>
                        <div class="order-summary-row"><span>Total</span><strong><?php echo money($order['Total_Amount']); ?></strong></div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No order found to track.</div>
                <?php endif; ?>
            </div>

            <div class="table-card tracking-timeline-card">
                <div class="section-heading compact">
                    <div>
                        <span class="section-kicker">Timeline</span>
                        <h2>Tracking events</h2>
                    </div>
                </div>
                <?php if (!empty($trackingRows)): ?>
                    <div class="track-timeline modern">
                        <?php foreach ($trackingRows as $row): ?>
                            <div class="track-item">
                                <div class="track-item-head">
                                    <strong><?php echo h($row['new_status']); ?></strong>
                                    <span class="muted"><?php echo h(date('d M Y, h:i A', strtotime($row['changed_at']))); ?></span>
                                </div>
                                <p>From: <?php echo h($row['old_status'] ?: '-'); ?></p>
                                <p><?php echo h($row['remarks'] ?: 'Status updated.'); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">Tracking will appear once the delivery is updated.</div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
</body>
</html>