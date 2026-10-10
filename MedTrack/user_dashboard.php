<?php
require_once 'includes/functions.php';
requireCustomer();

$customerId = getCustomerIdFromSession();
$cartCount = getCustomerCartItemCount($conn, $customerId);
$pageTitle = 'Customer Portal';

$recentOrders = [];
$recentOrdersStmt = $conn->prepare(
    'SELECT o.Order_ID, o.Order_Number, o.Order_Status, o.Payment_Status, o.Total_Amount, o.Placed_At, d.delivery_status
     FROM user_orders o
     LEFT JOIN delivery_orders d ON d.order_id = o.Order_ID
     WHERE o.User_ID = ?
     ORDER BY o.Order_ID DESC
     LIMIT 3'
);
if ($recentOrdersStmt) {
    $recentOrdersStmt->bind_param('i', $customerId);
    $recentOrdersStmt->execute();
    $recentOrdersResult = $recentOrdersStmt->get_result();
    while ($recentOrdersResult && ($row = $recentOrdersResult->fetch_assoc())) {
        $recentOrders[] = $row;
    }
    $recentOrdersStmt->close();
}

$featuredMedicines = [];
$featuredStmt = $conn->query(
    'SELECT Medicine_ID, Medicine_Name, Category, Price, Quantity, Medicine_Image
     FROM medicine
     WHERE Is_Active = 1
     ORDER BY CASE WHEN Quantity <= 5 THEN 0 ELSE 1 END, Medicine_ID DESC
     LIMIT 4'
);
if ($featuredStmt) {
    while ($featuredStmt && ($row = $featuredStmt->fetch_assoc())) {
        $featuredMedicines[] = $row;
    }
}

$quickSummary = [
    'orders' => count($recentOrders),
    'cart' => $cartCount,
    'status' => 'Active',
    'next' => !empty($recentOrders) ? normalizeOrderStatus($recentOrders[0]['Order_Status']) : 'Shop now',
];
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
<div class="customer-shell customer-shell-hero customer-home-shell">
    <div class="customer-page">
        <?php echo renderCustomerNavbar('', $cartCount); ?>

        <section class="customer-home-hero card compact" style="padding:16px;margin-bottom:12px;">
            <div class="home-hero-copy">
                <span class="hero-kicker">Online medicine ordering</span>
                <h2 style="margin:6px 0 0;font-size:20px;">Fast medicine shopping, clean checkout, and live order tracking.</h2>
                <p style="margin-top:6px;">Browse compact medicine cards, jump into your cart, and keep track of orders from one lightweight customer home screen.</p>
                <div class="hero-action-row compact" style="margin-top:8px;">
                    <a class="btn btn-primary" href="shop.php">Quick Shop</a>
                    <a class="btn btn-secondary" href="my_orders.php">Recent Orders</a>
                    <a class="btn btn-secondary" href="track_order.php">Track Order</a>
                </div>
            </div>
            <div class="home-summary-strip">
                <div class="home-summary-item">
                    <span>Orders</span>
                    <strong><?php echo (int) $quickSummary['orders']; ?></strong>
                </div>
                <div class="home-summary-item">
                    <span>Cart</span>
                    <strong><?php echo (int) $quickSummary['cart']; ?></strong>
                </div>
                <div class="home-summary-item">
                    <span>Status</span>
                    <strong><?php echo h($quickSummary['status']); ?></strong>
                </div>
                <div class="home-summary-item">
                    <span>Next</span>
                    <strong><?php echo h($quickSummary['next']); ?></strong>
                </div>
            </div>
        </section>

        <?php echo renderFlash(); ?>

        <section class="customer-home-grid">
            <div class="home-panel card">
                <div class="section-heading compact">
                    <div>
                        <span class="section-kicker">Featured</span>
                        <h2>Medicines to shop now</h2>
                    </div>
                    <a class="link-chip" href="shop.php">View all</a>
                </div>
                <div class="home-product-row" style="display:flex;gap:10px;">
                    <?php foreach ($featuredMedicines as $medicine): ?>
                        <?php
                        $imageUrl = medicineImageUrl($medicine['Medicine_Image']);
                        if ($imageUrl === '') {
                            $imageUrl = medicinePlaceholderImageUrl();
                        }
                        $quantity = (int) $medicine['Quantity'];
                        ?>
                        <article class="home-product-card compact" style="display:flex;gap:10px;align-items:center;padding:8px;border-radius:12px;">
                            <img class="medicine-thumb" src="<?php echo h($imageUrl); ?>" alt="<?php echo h($medicine['Medicine_Name']); ?>">
                            <div style="flex:1;min-width:0;">
                                <span class="mini-pill"><?php echo h($medicine['Category']); ?></span>
                                <h4 style="margin:4px 0 0;font-size:14px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;"><?php echo h($medicine['Medicine_Name']); ?></h4>
                                <div class="home-product-meta" style="margin-top:6px;display:flex;justify-content:space-between;align-items:center;gap:8px;">
                                    <strong><?php echo money($medicine['Price']); ?></strong>
                                    <span class="badge <?php echo h(medicineStockBadgeClass($quantity)); ?>"><?php echo $quantity > 0 ? $quantity . ' left' : 'Out'; ?></span>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="home-panel card">
                <div class="section-heading compact">
                    <div>
                        <span class="section-kicker">Orders</span>
                        <h2>Recent orders</h2>
                    </div>
                    <a class="link-chip" href="my_orders.php">Open history</a>
                </div>
                <div class="home-order-list" style="display:grid;gap:8px;">
                    <?php if (!empty($recentOrders)): ?>
                        <?php foreach ($recentOrders as $order): ?>
                            <div class="home-order-item">
                                <div>
                                    <strong><?php echo h($order['Order_Number']); ?></strong>
                                    <div class="muted"><?php echo h(date('d M, h:i A', strtotime($order['Placed_At']))); ?></div>
                                </div>
                                <div class="home-order-meta">
                                    <span class="order-status-badge status-<?php echo strtolower(str_replace(' ', '-', normalizeOrderStatus($order['Order_Status']))); ?>"><?php echo h(normalizeOrderStatus($order['Order_Status'])); ?></span>
                                    <strong><?php echo money($order['Total_Amount']); ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-summary">No orders yet. Start shopping to see them here.</div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
</div>
</body>
</html>