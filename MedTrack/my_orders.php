<?php
require_once 'includes/functions.php';
requireCustomer();

$customerId = getCustomerIdFromSession();
$cartCount = getCustomerCartItemCount($conn, $customerId);
$pageTitle = 'My Orders';
$selectedOrderId = (int) ($_GET['view'] ?? 0);

$ordersStmt = $conn->prepare(
    'SELECT o.Order_ID, o.Order_Number, o.Order_Status, o.Payment_Status, o.Total_Amount, o.Placed_At, d.delivery_id, d.delivery_status, d.estimated_delivery_date
     FROM user_orders o
     LEFT JOIN delivery_orders d ON d.order_id = o.Order_ID
     WHERE o.User_ID = ?
     ORDER BY o.Order_ID DESC'
);
$ordersStmt->bind_param('i', $customerId);
$ordersStmt->execute();
$ordersResult = $ordersStmt->get_result();
$orders = [];
while ($ordersResult && ($row = $ordersResult->fetch_assoc())) {
    $orders[] = $row;
}
$ordersStmt->close();

if ($selectedOrderId <= 0 && !empty($orders)) {
    $selectedOrderId = (int) $orders[0]['Order_ID'];
}

$selectedOrder = null;
$selectedItems = [];
if ($selectedOrderId > 0) {
    $orderStmt = $conn->prepare(
        'SELECT o.*, d.delivery_id, d.delivery_status, d.estimated_delivery_date, d.agent_id, a.agent_name, a.phone AS agent_phone
         FROM user_orders o
         LEFT JOIN delivery_orders d ON d.order_id = o.Order_ID
         LEFT JOIN delivery_agents a ON a.delivery_agent_id = d.agent_id
         WHERE o.Order_ID = ? AND o.User_ID = ?
         LIMIT 1'
    );
    if ($orderStmt) {
        $orderStmt->bind_param('ii', $selectedOrderId, $customerId);
        $orderStmt->execute();
        $orderResult = $orderStmt->get_result();
        $selectedOrder = $orderResult ? $orderResult->fetch_assoc() : null;
        $orderStmt->close();

        if ($selectedOrder) {
            $itemStmt = $conn->prepare(
                'SELECT oi.Quantity, oi.Unit_Price, oi.Line_Total, m.Medicine_Name
                 FROM user_order_items oi
                 INNER JOIN medicine m ON m.Medicine_ID = oi.Medicine_ID
                 WHERE oi.Order_ID = ?
                 ORDER BY oi.Order_Item_ID ASC'
            );
            if ($itemStmt) {
                $itemStmt->bind_param('i', $selectedOrderId);
                $itemStmt->execute();
                $itemResult = $itemStmt->get_result();
                while ($itemResult && ($itemRow = $itemResult->fetch_assoc())) {
                    $selectedItems[] = $itemRow;
                }
                $itemStmt->close();
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
        <?php echo renderCustomerNavbar('orders', $cartCount); ?>

        <section class="customer-hero card">
            <div class="customer-hero-copy">
                <span class="hero-kicker">Order history</span>
                <h1>Track every order, review invoice details, and open delivery progress from one place.</h1>
                <p>Each order card keeps payment, delivery, and invoice information visible so customers can move quickly between history and tracking.</p>
                <div class="hero-action-row">
                    <a class="btn btn-primary" href="shop.php">Shop More</a>
                    <a class="btn btn-secondary" href="track_order.php">Track Latest</a>
                </div>
            </div>
            <div class="customer-hero-card">
                <div class="hero-stat">
                    <span>Total orders</span>
                    <strong><?php echo count($orders); ?></strong>
                </div>
                <div class="hero-stat">
                    <span>Selected order</span>
                    <strong><?php echo $selectedOrder ? h($selectedOrder['Order_Number']) : 'None'; ?></strong>
                </div>
            </div>
        </section>

        <div class="toast-stack">
            <?php echo renderFlash(); ?>
        </div>

        <section class="customer-section customer-section-grid orders-grid">
            <div class="orders-list">
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $order): ?>
                        <?php $progressIndex = customerProgressIndex($order['delivery_status'] ?? $order['Order_Status']); ?>
                        <article class="order-card compact <?php echo $selectedOrderId === (int) $order['Order_ID'] ? 'selected' : ''; ?>" style="padding:12px;border-radius:14px;">
                            <div class="order-card-header compact" style="gap:8px;">
                                <div>
                                    <span class="order-number">#<?php echo h($order['Order_Number']); ?></span>
                                    <h4 style="margin:6px 0 0;font-size:18px;font-weight:800;"><?php echo money($order['Total_Amount']); ?></h4>
                                </div>
                                <span class="order-status-badge status-<?php echo strtolower(str_replace(' ', '-', normalizeOrderStatus($order['Order_Status']))); ?>"><?php echo h(normalizeOrderStatus($order['Order_Status'])); ?></span>
                            </div>
                            <div class="order-card-meta" style="grid-template-columns: repeat(2, minmax(0,1fr)); gap:8px; font-size:13px;">
                                <span>Payment: <strong><?php echo h($order['Payment_Status']); ?></strong></span>
                                <span>Date: <strong><?php echo h(date('d M Y, h:i A', strtotime($order['Placed_At']))); ?></strong></span>
                                <span>Delivery: <strong><?php echo h($order['delivery_status'] ?: 'Pending'); ?></strong></span>
                                <span style="display:flex;align-items:center;gap:8px;">Progress:
                                    <div style="display:flex;gap:6px;align-items:center;margin-left:6px;">
                                        <?php foreach (customerOrderProgressSteps() as $stepIndex => $step): ?>
                                            <?php if ($stepIndex <= $progressIndex): ?>
                                                <span title="<?php echo h($step); ?>" style="width:8px;height:8px;border-radius:50%;background:var(--primary-dark);display:inline-block;"></span>
                                            <?php else: ?>
                                                <span title="<?php echo h($step); ?>" style="width:8px;height:8px;border-radius:50%;background:#e6eefc;display:inline-block;"></span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </span>
                            </div>
                            <div class="hero-action-row compact" style="margin-top:8px;">
                                <a class="btn btn-secondary" href="my_orders.php?view=<?php echo (int) $order['Order_ID']; ?>">Details</a>
                                <a class="btn btn-primary" href="track_order.php?order_id=<?php echo (int) $order['Order_ID']; ?>">Track</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state card">
                        <img src="<?php echo h(medicinePlaceholderImageUrl()); ?>" alt="No orders">
                        <h3>No orders yet</h3>
                        <p>Your completed purchases will appear here.</p>
                        <a class="btn btn-primary" href="shop.php">Start Shopping</a>
                    </div>
                <?php endif; ?>
            </div>

            <aside class="table-card order-detail-panel">
                <div class="section-heading compact">
                    <div>
                        <span class="section-kicker">Details</span>
                        <h2>Selected order</h2>
                    </div>
                </div>
                <?php if ($selectedOrder): ?>
                    <div class="order-summary-list">
                        <div class="order-summary-row"><span>Order Number</span><strong><?php echo h($selectedOrder['Order_Number']); ?></strong></div>
                        <div class="order-summary-row"><span>Status</span><strong><?php echo h(normalizeOrderStatus($selectedOrder['Order_Status'])); ?></strong></div>
                        <div class="order-summary-row"><span>Payment</span><strong><?php echo h($selectedOrder['Payment_Status']); ?></strong></div>
                        <div class="order-summary-row"><span>Total</span><strong><?php echo money($selectedOrder['Total_Amount']); ?></strong></div>
                        <div class="order-summary-row"><span>Date</span><strong><?php echo h(date('d M Y, h:i A', strtotime($selectedOrder['Placed_At']))); ?></strong></div>
                    </div>
                    <div class="table-wrap" style="margin-top: 14px;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Medicine</th>
                                    <th>Qty</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($selectedItems as $item): ?>
                                    <tr>
                                        <td><?php echo h($item['Medicine_Name']); ?></td>
                                        <td><?php echo (int) $item['Quantity']; ?></td>
                                        <td><?php echo money($item['Unit_Price']); ?></td>
                                        <td><?php echo money($item['Line_Total']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="hero-action-row compact" style="margin-top: 14px;">
                        <a class="btn btn-primary" href="track_order.php?order_id=<?php echo (int) $selectedOrder['Order_ID']; ?>">Open Tracking</a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">Select an order to view details.</div>
                <?php endif; ?>
            </aside>
        </section>

        <div class="order-modal" id="orderModal" <?php echo $selectedOrder ? 'data-autoshow="1"' : ''; ?> hidden aria-hidden="true">
            <div class="order-modal-backdrop" data-modal-close></div>
            <div class="order-modal-panel" role="dialog" aria-modal="true" aria-labelledby="orderModalTitle">
                <?php if ($selectedOrder): ?>
                    <div class="modal-header">
                        <div>
                            <span class="section-kicker">Order modal</span>
                            <h2 id="orderModalTitle"><?php echo h($selectedOrder['Order_Number']); ?></h2>
                        </div>
                        <button type="button" class="btn btn-secondary modal-close-btn" data-modal-close aria-label="Close order details">Close</button>
                    </div>
                    <div class="order-summary-list">
                        <div class="order-summary-row"><span>Status</span><strong><?php echo h(normalizeOrderStatus($selectedOrder['Order_Status'])); ?></strong></div>
                        <div class="order-summary-row"><span>Delivery</span><strong><?php echo h($selectedOrder['delivery_status'] ?: 'Pending'); ?></strong></div>
                        <div class="order-summary-row"><span>Payment</span><strong><?php echo h($selectedOrder['Payment_Status']); ?></strong></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>