<?php
require_once 'includes/functions.php';
requireCustomer();

$customerId = getCustomerIdFromSession();
$cartCount = getCustomerCartItemCount($conn, $customerId);
$pageTitle = 'Your Cart';
$error = '';

$cart = getOrCreateActiveCart($conn, $customerId);
$cartId = $cart ? (int) $cart['Cart_ID'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId = (int) ($_POST['cart_item_id'] ?? 0);
    $newQuantity = (int) ($_POST['quantity'] ?? 0);

    if (isset($_POST['remove_item'])) {
        $removeStmt = $conn->prepare('DELETE ci FROM cart_items ci INNER JOIN carts c ON c.Cart_ID = ci.Cart_ID WHERE ci.Cart_Item_ID = ? AND c.User_ID = ? AND c.Cart_Status = "active"');
        if ($removeStmt) {
            $removeStmt->bind_param('ii', $itemId, $customerId);
            $removeStmt->execute();
            $removeStmt->close();
            setFlash('success', 'Item removed from cart.');
            header('Location: cart.php');
            exit;
        }
        $error = 'Could not remove item.';
    }

    if (isset($_POST['update_item'])) {
        if ($itemId <= 0 || $newQuantity <= 0) {
            $error = 'Please enter a valid quantity.';
        } else {
            $stmt = $conn->prepare(
                'SELECT ci.Cart_Item_ID, ci.Medicine_ID, m.Medicine_Name, m.Quantity AS Stock
                 FROM cart_items ci
                 INNER JOIN carts c ON c.Cart_ID = ci.Cart_ID
                 INNER JOIN medicine m ON m.Medicine_ID = ci.Medicine_ID
                 WHERE ci.Cart_Item_ID = ? AND c.User_ID = ? AND c.Cart_Status = "active"
                 LIMIT 1'
            );
            if ($stmt) {
                $stmt->bind_param('ii', $itemId, $customerId);
                $stmt->execute();
                $result = $stmt->get_result();
                $item = $result ? $result->fetch_assoc() : null;
                $stmt->close();

                if (!$item) {
                    $error = 'Cart item not found.';
                } elseif ($newQuantity > (int) $item['Stock']) {
                    $error = 'Quantity cannot be greater than available stock.';
                } else {
                    $updateStmt = $conn->prepare('UPDATE cart_items SET Quantity = ? WHERE Cart_Item_ID = ?');
                    if ($updateStmt) {
                        $updateStmt->bind_param('ii', $newQuantity, $itemId);
                        $updateStmt->execute();
                        $updateStmt->close();
                        setFlash('success', 'Cart updated successfully.');
                        header('Location: cart.php');
                        exit;
                    }
                    $error = 'Could not update the item.';
                }
            }
        }
    }
}

$items = [];
$grandTotal = 0;

if ($cartId > 0) {
    $itemStmt = $conn->prepare(
        'SELECT ci.Cart_Item_ID, ci.Quantity, ci.Unit_Price, m.Medicine_Name, m.Medicine_Image, m.Quantity AS Stock
         FROM cart_items ci
         INNER JOIN medicine m ON m.Medicine_ID = ci.Medicine_ID
         INNER JOIN carts c ON c.Cart_ID = ci.Cart_ID
         WHERE c.User_ID = ? AND c.Cart_Status = "active"
         ORDER BY m.Medicine_Name ASC'
    );
    if ($itemStmt) {
        $itemStmt->bind_param('i', $customerId);
        $itemStmt->execute();
        $result = $itemStmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['Line_Total'] = (float) $row['Unit_Price'] * (int) $row['Quantity'];
                $grandTotal += $row['Line_Total'];
                $items[] = $row;
            }
        }
        $itemStmt->close();
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
        <?php echo renderCustomerNavbar('cart', $cartCount); ?>

        <section class="customer-hero card">
            <div class="customer-hero-copy">
                <span class="hero-kicker">Cart review</span>
                <h1>Review quantities, adjust items, and move to checkout when ready.</h1>
                <p>Your cart is kept in sync with stock, so you always see what is available before completing the order.</p>
                <div class="hero-action-row">
                    <a class="btn btn-primary" href="shop.php">Continue Shopping</a>
                    <a class="btn btn-secondary" href="checkout.php" <?php echo empty($items) ? 'aria-disabled="true" style="pointer-events:none;opacity:.6;"' : ''; ?>>Checkout</a>
                </div>
            </div>
            <div class="customer-hero-card">
                <div class="hero-stat">
                    <span>Cart items</span>
                    <strong><?php echo count($items); ?></strong>
                </div>
                <div class="hero-stat">
                    <span>Grand total</span>
                    <strong><?php echo money($grandTotal); ?></strong>
                </div>
                <div class="hero-stat">
                    <span>Status</span>
                    <strong>Ready for checkout</strong>
                </div>
            </div>
        </section>

        <div class="toast-stack">
            <?php echo renderFlash(); ?>
        </div>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo h($error); ?></div>
        <?php endif; ?>

        <section class="customer-section customer-section-grid">
            <div class="table-card">
                <div class="section-heading compact">
                    <div>
                        <span class="section-kicker">Cart</span>
                        <h2>Cart items</h2>
                    </div>
                </div>
                <?php if (!empty($items)): ?>
                    <div class="cart-item-list">
                            <?php foreach ($items as $item): ?>
                                <?php $imageUrl = medicineImageUrl($item['Medicine_Image']) ?: medicinePlaceholderImageUrl(); ?>
                                <div class="cart-item-card compact" style="display:grid;grid-template-columns:56px 1fr auto;gap:10px;align-items:center;padding:10px;border-radius:12px;">
                                    <img class="medicine-thumb" src="<?php echo h($imageUrl); ?>" alt="<?php echo h($item['Medicine_Name']); ?>">
                                    <div class="cart-item-info" style="min-width:0;">
                                        <strong style="display:block;font-size:14px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;"><?php echo h($item['Medicine_Name']); ?></strong>
                                        <div class="muted" style="font-size:12px;margin-top:6px;">Available: <?php echo (int) $item['Stock']; ?> • Unit: <?php echo money($item['Unit_Price']); ?></div>
                                    </div>
                                    <form method="post" class="cart-item-controls" style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">
                                        <input type="hidden" name="cart_item_id" value="<?php echo (int) $item['Cart_Item_ID']; ?>">
                                        <div class="qty-stepper" style="display:flex;gap:6px;align-items:center;">
                                            <button class="qty-btn" type="button" data-stepper="decrease">-</button>
                                            <input style="width:64px;padding:6px;text-align:center;" type="number" name="quantity" min="1" max="<?php echo (int) $item['Stock']; ?>" value="<?php echo (int) $item['Quantity']; ?>">
                                            <button class="qty-btn" type="button" data-stepper="increase">+</button>
                                        </div>
                                        <div style="display:flex;gap:6px;">
                                            <button class="btn btn-secondary" type="submit" name="update_item" style="padding:6px 8px;font-size:13px;">Update</button>
                                            <button class="btn btn-danger" type="submit" name="remove_item" onclick="return confirm('Remove this item from cart?');" style="padding:6px 8px;font-size:13px;">Remove</button>
                                        </div>
                                        <div class="cart-item-subtotal muted" style="font-weight:700;margin-top:6px;"><?php echo money($item['Line_Total']); ?></div>
                                    </form>
                                </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state card">
                        <img src="<?php echo h(medicinePlaceholderImageUrl()); ?>" alt="Empty cart">
                        <h3>Your cart is empty</h3>
                        <p>Browse the shop to add medicines and build your order.</p>
                        <a class="btn btn-primary" href="shop.php">Start Shopping</a>
                    </div>
                <?php endif; ?>
            </div>

            <aside class="cart-total-box cart-summary-card compact" style="padding:12px;border-radius:14px;">
                <h3 class="section-title" style="font-size:16px;margin-bottom:8px;">Order summary</h3>
                <div class="order-summary-list" style="gap:8px;">
                    <div class="order-summary-row" style="padding:8px 0;">
                        <span style="font-size:13px;">Items</span>
                        <strong><?php echo count($items); ?></strong>
                    </div>
                    <div class="order-summary-row" style="padding:8px 0;">
                        <span style="font-size:13px;">Grand Total</span>
                        <strong style="font-size:16px;"><?php echo money($grandTotal); ?></strong>
                    </div>
                </div>
                <a class="btn btn-primary" href="checkout.php" <?php echo empty($items) ? 'aria-disabled="true" style="pointer-events:none;opacity:.6;"' : ''; ?> style="display:block;margin-top:10px;">Proceed to Checkout</a>
            </aside>
        </section>
    </div>
</div>
</body>
</html>