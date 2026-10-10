<?php
require_once 'includes/functions.php';
require_once 'includes/email.php';
requireCustomer();

$customerId = getCustomerIdFromSession();
$cartCount = getCustomerCartItemCount($conn, $customerId);
$pageTitle = 'Checkout';
$error = '';
$success = '';

$customerStmt = $conn->prepare('SELECT Full_Name, Email, Phone, Address FROM users WHERE User_ID = ? LIMIT 1');
$customerStmt->bind_param('i', $customerId);
$customerStmt->execute();
$customerResult = $customerStmt->get_result();
$customer = $customerResult ? $customerResult->fetch_assoc() : null;
$customerStmt->close();

$cart = getOrCreateActiveCart($conn, $customerId);
$cartId = $cart ? (int) $cart['Cart_ID'] : 0;

$cartItems = [];
$grandTotal = 0;

if ($cartId > 0) {
    $itemStmt = $conn->prepare(
        'SELECT ci.Cart_Item_ID, ci.Medicine_ID, ci.Quantity, ci.Unit_Price, m.Medicine_Name, m.Quantity AS Stock
         FROM cart_items ci
         INNER JOIN medicine m ON m.Medicine_ID = ci.Medicine_ID
         INNER JOIN carts c ON c.Cart_ID = ci.Cart_ID
         WHERE c.User_ID = ? AND c.Cart_Status = "active"
         ORDER BY m.Medicine_Name ASC'
    );
    if ($itemStmt) {
        $itemStmt->bind_param('i', $customerId);
        $itemStmt->execute();
        $itemResult = $itemStmt->get_result();
        if ($itemResult) {
            while ($row = $itemResult->fetch_assoc()) {
                $row['Line_Total'] = (float) $row['Unit_Price'] * (int) $row['Quantity'];
                $grandTotal += $row['Line_Total'];
                $cartItems[] = $row;
            }
        }
        $itemStmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $shippingAddress = trim($_POST['shipping_address'] ?? '');
    $shippingName = trim($_POST['shipping_name'] ?? ($customer['Full_Name'] ?? ''));
    $shippingPhone = trim($_POST['shipping_phone'] ?? ($customer['Phone'] ?? ''));
    $shippingEmail = trim($_POST['shipping_email'] ?? ($customer['Email'] ?? ''));

    if ($shippingName === '' || $shippingPhone === '' || $shippingEmail === '' || $shippingAddress === '') {
        $error = 'Please complete the shipping details.';
    } elseif (!filter_var($shippingEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (empty($cartItems)) {
        $error = 'Your cart is empty.';
    } else {
        $conn->begin_transaction();
        try {
            $freshItems = [];
            $refreshStmt = $conn->prepare(
                'SELECT ci.Cart_Item_ID, ci.Medicine_ID, ci.Quantity, ci.Unit_Price, m.Medicine_Name, m.Quantity AS Stock
                 FROM cart_items ci
                 INNER JOIN medicine m ON m.Medicine_ID = ci.Medicine_ID
                 INNER JOIN carts c ON c.Cart_ID = ci.Cart_ID
                 WHERE c.User_ID = ? AND c.Cart_Status = "active"
                 ORDER BY m.Medicine_Name ASC FOR UPDATE'
            );
            if (!$refreshStmt) {
                throw new Exception('Could not load cart for checkout.');
            }

            $refreshStmt->bind_param('i', $customerId);
            $refreshStmt->execute();
            $refreshResult = $refreshStmt->get_result();
            while ($row = $refreshResult->fetch_assoc()) {
                if ((int) $row['Quantity'] > (int) $row['Stock']) {
                    throw new Exception('One of the medicines no longer has enough stock.');
                }
                $freshItems[] = $row;
            }
            $refreshStmt->close();

            if (empty($freshItems)) {
                throw new Exception('Your cart is empty.');
            }

            $totalAmount = 0;
            foreach ($freshItems as $item) {
                $totalAmount += (float) $item['Unit_Price'] * (int) $item['Quantity'];
            }

            $orderNumber = generateOrderNumber();
            $orderInsert = $conn->prepare(
                'INSERT INTO user_orders (User_ID, Cart_ID, Order_Number, Order_Status, Payment_Status, Total_Amount, Shipping_Name, Shipping_Phone, Shipping_Address)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            if (!$orderInsert) {
                throw new Exception('Could not prepare order insert.');
            }

            $orderStatus = 'Pending';
            $paymentStatus = 'Unpaid';
            $orderInsert->bind_param('iisssdsss', $customerId, $cartId, $orderNumber, $orderStatus, $paymentStatus, $totalAmount, $shippingName, $shippingPhone, $shippingAddress);
            if (!$orderInsert->execute()) {
                throw new Exception('Could not create order.');
            }
            $orderId = (int) $conn->insert_id;
            $orderInsert->close();

            $orderItemStmt = $conn->prepare('INSERT INTO user_order_items (Order_ID, Medicine_ID, Quantity, Unit_Price, Line_Total) VALUES (?, ?, ?, ?, ?)');
            $stockStmt = $conn->prepare('UPDATE medicine SET Quantity = Quantity - ? WHERE Medicine_ID = ? AND Quantity >= ?');
            $deliveryDate = date('Y-m-d', strtotime('+3 days'));
            if (!$orderItemStmt || !$stockStmt) {
                throw new Exception('Could not prepare order item statements.');
            }

            foreach ($freshItems as $item) {
                $medicineId = (int) $item['Medicine_ID'];
                $qty = (int) $item['Quantity'];
                $unitPrice = (float) $item['Unit_Price'];
                $lineTotal = $unitPrice * $qty;

                $stockStmt->bind_param('iii', $qty, $medicineId, $qty);
                if (!$stockStmt->execute() || $stockStmt->affected_rows === 0) {
                    throw new Exception('Stock could not be reduced for one of the medicines.');
                }

                $orderItemStmt->bind_param('iiidd', $orderId, $medicineId, $qty, $unitPrice, $lineTotal);
                if (!$orderItemStmt->execute()) {
                    throw new Exception('Could not save order items.');
                }
            }

            $orderItemStmt->close();
            $stockStmt->close();

            $deliveryInsert = $conn->prepare(
                'INSERT INTO delivery_orders (bill_id, order_id, user_id, reference_type, reference_number, customer_name, customer_phone, customer_email, delivery_address, estimated_delivery_date, delivery_status, agent_id)
                 VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)'
            );
            if (!$deliveryInsert) {
                throw new Exception('Could not prepare delivery order insert.');
            }

            $referenceType = 'order';
            $deliveryStatus = 'Order Placed';
            $deliveryInsert->bind_param(
                'iissssssss',
                $orderId,
                $customerId,
                $referenceType,
                $orderNumber,
                $shippingName,
                $shippingPhone,
                $shippingEmail,
                $shippingAddress,
                $deliveryDate,
                $deliveryStatus
            );
            if (!$deliveryInsert->execute()) {
                throw new Exception('Could not create delivery order.');
            }
            $deliveryId = (int) $conn->insert_id;
            $deliveryInsert->close();

            $trackingStmt = $conn->prepare('INSERT INTO delivery_tracking (delivery_id, old_status, new_status, remarks) VALUES (?, ?, ?, ?)');
            if ($trackingStmt) {
                $oldStatus = '';
                $remarks = 'Order created from customer checkout.';
                $trackingStmt->bind_param('isss', $deliveryId, $oldStatus, $deliveryStatus, $remarks);
                $trackingStmt->execute();
                $trackingStmt->close();
            }

            $clearItems = $conn->prepare('DELETE ci FROM cart_items ci INNER JOIN carts c ON c.Cart_ID = ci.Cart_ID WHERE c.Cart_ID = ? AND c.User_ID = ? AND c.Cart_Status = "active"');
            if ($clearItems) {
                $clearItems->bind_param('ii', $cartId, $customerId);
                $clearItems->execute();
                $clearItems->close();
            }

            $cartStatusStmt = $conn->prepare('UPDATE carts SET Cart_Status = ? WHERE Cart_ID = ? AND User_ID = ?');
            if ($cartStatusStmt) {
                $converted = 'converted';
                $cartStatusStmt->bind_param('sii', $converted, $cartId, $customerId);
                $cartStatusStmt->execute();
                $cartStatusStmt->close();
            }

            $conn->commit();

            $orderItemsForEmail = [];
            foreach ($freshItems as $item) {
                $orderItemsForEmail[] = [
                    'Medicine_Name' => $item['Medicine_Name'],
                    'Quantity_Sold' => (int) $item['Quantity'],
                    'Price' => (float) $item['Unit_Price'],
                    'Sub_Total' => (float) $item['Unit_Price'] * (int) $item['Quantity'],
                ];
            }

            $invoiceData = [
                'bill_id' => $orderNumber,
                'date_time' => date('d M Y, h:i A'),
                'customer_name' => $shippingName,
                'customer_phone' => $shippingPhone,
                'employee_name' => 'Online Checkout',
                'total_amount' => $totalAmount,
                'items' => $orderItemsForEmail,
                'delivery_details' => [
                    'status' => 'Order Placed',
                    'reference_number' => $orderNumber,
                    'address' => $shippingAddress,
                    'estimated_date' => $deliveryDate,
                    'agent_name' => 'Unassigned',
                ],
            ];

            $attachmentPath = __DIR__ . '/tmp/order-' . $orderId . '.html';
            if (!is_dir(__DIR__ . '/tmp')) {
                mkdir(__DIR__ . '/tmp', 0777, true);
            }
            file_put_contents($attachmentPath, buildInvoiceEmailHtml($invoiceData));
            $mailResult = sendInvoiceEmail($invoiceData, $shippingEmail, $shippingName, !empty($mailConfig['attach_html_invoice']) ? $attachmentPath : null);
            if (is_file($attachmentPath)) {
                @unlink($attachmentPath);
            }

            $_SESSION['last_order_id'] = $orderId;
            $_SESSION['last_delivery_id'] = $deliveryId;

            if ($mailResult[0]) {
                setFlash('success', 'Order placed successfully. Invoice email sent.');
            } else {
                setFlash('warning', 'Order placed successfully. Email not sent: ' . $mailResult[1]);
            }

            header('Location: my_orders.php?view=' . $orderId);
            exit;
        } catch (Throwable $exception) {
            $conn->rollback();
            $error = 'Checkout failed: ' . $exception->getMessage();
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
        <?php echo renderCustomerNavbar('checkout', $cartCount); ?>

        <section class="customer-hero card compact" style="padding:14px;margin-bottom:10px;">
            <div class="customer-hero-copy">
                <span class="hero-kicker">Secure checkout</span>
                <h2 style="margin:6px 0 0;font-size:20px;">Confirm delivery details and place your medicine order safely.</h2>
                <p style="margin-top:6px;">We save the order, reduce stock, create delivery tracking, and email the invoice automatically after payment submission.</p>
                <div class="hero-action-row compact" style="margin-top:8px;">
                    <a class="btn btn-secondary" href="cart.php">Back to Cart</a>
                    <a class="btn btn-primary" href="my_orders.php">My Orders</a>
                </div>
            </div>
            <div class="customer-hero-card">
                <div class="hero-stat">
                    <span>Items</span>
                    <strong><?php echo count($cartItems); ?></strong>
                </div>
                <div class="hero-stat">
                    <span>Grand total</span>
                    <strong><?php echo money($grandTotal); ?></strong>
                </div>
                <div class="hero-stat">
                    <span>Delivery</span>
                    <strong>Estimated in 3 days</strong>
                </div>
            </div>
        </section>

        <div class="toast-stack">
            <?php echo renderFlash(); ?>
        </div>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo h($error); ?></div>
        <?php endif; ?>

        <section class="customer-section customer-section-grid checkout-grid">
            <div class="form-card checkout-form-card compact" style="padding:12px;border-radius:12px;">
                <div class="section-heading compact">
                    <div>
                        <span class="section-kicker">Checkout</span>
                        <h2>Delivery details</h2>
                    </div>
                </div>
                <form method="post" class="needs-validation">
                    <div class="form-grid">
                        <div class="form-group full">
                            <label for="shipping_name">Name</label>
                            <input type="text" id="shipping_name" name="shipping_name" required value="<?php echo h($_POST['shipping_name'] ?? ($customer['Full_Name'] ?? '')); ?>">
                        </div>
                        <div class="form-group full">
                            <label for="shipping_phone">Phone</label>
                            <input type="text" id="shipping_phone" name="shipping_phone" required value="<?php echo h($_POST['shipping_phone'] ?? ($customer['Phone'] ?? '')); ?>">
                        </div>
                        <div class="form-group full">
                            <label for="shipping_email">Email</label>
                            <input type="email" id="shipping_email" name="shipping_email" required value="<?php echo h($_POST['shipping_email'] ?? ($customer['Email'] ?? '')); ?>">
                        </div>
                        <div class="form-group full">
                            <label for="shipping_address">Delivery Address</label>
                            <textarea id="shipping_address" name="shipping_address" required rows="3" style="min-height:72px;max-height:140px;"><?php echo h($_POST['shipping_address'] ?? ($customer['Address'] ?? '')); ?></textarea>
                        </div>
                    </div>
                    <div class="form-actions" style="margin-top: 16px;">
                        <button class="btn btn-primary" type="submit" name="place_order">Place Order</button>
                    </div>
                </form>
            </div>

            <aside class="cart-total-box checkout-summary-card compact" style="padding:12px;border-radius:12px;">
                <h3 class="section-title" style="font-size:16px;margin-bottom:8px;">Order summary</h3>
                <div class="checkout-summary-list" style="gap:8px;">
                    <?php if (!empty($cartItems)): ?>
                        <?php foreach ($cartItems as $item): ?>
                            <div class="checkout-summary-item" style="padding:8px 0;display:flex;justify-content:space-between;align-items:center;">
                                <div style="min-width:0;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <strong style="font-size:14px;display:block;"><?php echo h($item['Medicine_Name']); ?></strong>
                                    <span class="muted" style="font-size:12px;">Qty: <?php echo (int) $item['Quantity']; ?></span>
                                </div>
                                <strong style="font-size:14px;"><?php echo money($item['Line_Total']); ?></strong>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-summary">Your cart is empty.</div>
                    <?php endif; ?>
                </div>
                <div class="order-summary-row" style="padding-top:8px;">
                    <span style="font-size:13px;">Grand Total</span>
                    <strong style="font-size:16px;"><?php echo money($grandTotal); ?></strong>
                </div>
                <div class="delivery-note" style="margin-top:8px;padding:10px;border-radius:10px;">
                    Estimated delivery date is automatically set to 3 days from today.
                </div>
            </aside>
        </section>
    </div>
</div>
</body>
</html>