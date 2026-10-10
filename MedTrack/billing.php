<?php
require_once 'includes/functions.php';
require_once 'includes/email.php';
requireEmployee();

$pageTitle = 'Billing Module';

if (!isset($_SESSION['bill_cart'])) {
    $_SESSION['bill_cart'] = [];
}

if (empty($_SESSION['billing_form_token'])) {
    $_SESSION['billing_form_token'] = bin2hex(random_bytes(16));
}

if (isset($_GET['view'])) {
    $viewBillId = (int) $_GET['view'];
    if ($viewBillId > 0) {
        $viewStmt = $conn->prepare('SELECT Bill_ID FROM bill WHERE Bill_ID = ? LIMIT 1');
        if ($viewStmt) {
            $viewStmt->bind_param('i', $viewBillId);
            $viewStmt->execute();
            $viewResult = $viewStmt->get_result();
            $billExists = $viewResult && $viewResult->num_rows > 0;
            $viewStmt->close();

            if ($billExists) {
                $_SESSION['last_bill_id'] = $viewBillId;
                setFlash('success', 'Bill #' . $viewBillId . ' loaded in invoice preview.');
            } else {
                setFlash('error', 'Selected bill was not found.');
            }
        }
    }

    header('Location: billing.php');
    exit;
}

if (isset($_GET['remove'])) {
    $removeIndex = (int) $_GET['remove'];
    if (isset($_SESSION['bill_cart'][$removeIndex])) {
        unset($_SESSION['bill_cart'][$removeIndex]);
        $_SESSION['bill_cart'] = array_values($_SESSION['bill_cart']);
        setFlash('success', 'Item removed from the bill.');
    }
    header('Location: billing.php');
    exit;
}

$downloadBillId = isset($_GET['download']) ? (int) $_GET['download'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $medicineId = (int) ($_POST['medicine_id'] ?? 0);
    $quantity = (int) ($_POST['quantity_sold'] ?? 0);

    if ($medicineId <= 0 || $quantity <= 0) {
        setFlash('error', 'Please select a medicine and enter a valid quantity.');
    } else {
        $stmt = $conn->prepare('SELECT Medicine_ID, Medicine_Name, Price, Quantity FROM medicine WHERE Medicine_ID = ?');
        $stmt->bind_param('i', $medicineId);
        $stmt->execute();
        $medicineResult = $stmt->get_result();
        $medicine = $medicineResult ? $medicineResult->fetch_assoc() : null;
        $stmt->close();

        if (!$medicine) {
            setFlash('error', 'Selected medicine was not found.');
        } else {
            $existingIndex = null;
            foreach ($_SESSION['bill_cart'] as $index => $item) {
                if ((int) $item['Medicine_ID'] === $medicineId) {
                    $existingIndex = $index;
                    break;
                }
            }

            $currentQuantity = $quantity;
            if ($existingIndex !== null) {
                $currentQuantity += (int) $_SESSION['bill_cart'][$existingIndex]['Quantity_Sold'];
            }

            if ($currentQuantity > (int) $medicine['Quantity']) {
                setFlash('error', 'Not enough stock available for the selected medicine.');
            } else {
                $line = [
                    'Medicine_ID' => (int) $medicine['Medicine_ID'],
                    'Medicine_Name' => $medicine['Medicine_Name'],
                    'Price' => (float) $medicine['Price'],
                    'Quantity_Sold' => $currentQuantity,
                    'Sub_Total' => (float) $medicine['Price'] * $currentQuantity,
                ];

                if ($existingIndex !== null) {
                    $_SESSION['bill_cart'][$existingIndex] = $line;
                } else {
                    $_SESSION['bill_cart'][] = $line;
                }

                setFlash('success', 'Medicine added to the bill cart.');
            }
        }
    }

    header('Location: billing.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_bill'])) {
    $submittedToken = trim((string) ($_POST['billing_token'] ?? ''));
    $sessionToken = (string) ($_SESSION['billing_form_token'] ?? '');
    $customerName = trim($_POST['customer_name'] ?? '');
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    $customerEmail = trim($_POST['customer_email'] ?? '');
    $employeeId = (int) ($_POST['employee_id'] ?? 0);
    $deliveryRequired = isset($_POST['delivery_required']) ? 1 : 0;
    $deliveryAddress = trim($_POST['delivery_address'] ?? '');
    $estimatedDeliveryDate = trim($_POST['estimated_delivery_date'] ?? '');
    $deliveryAgentId = (int) ($_POST['delivery_agent_id'] ?? 0);
    $cart = $_SESSION['bill_cart'];
    $phoneDigits = preg_replace('/\D+/', '', $customerPhone);

    if ($submittedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        setFlash('error', 'This bill submission is no longer valid. Please review the draft and try again.');
    } elseif ($customerName === '' || $phoneDigits === '' || $customerEmail === '' || $employeeId <= 0) {
        setFlash('error', 'Please enter customer name, phone, email, and select an employee.');
    } elseif (!preg_match('/^[0-9]{10,15}$/', $phoneDigits)) {
        setFlash('error', 'Please enter a valid phone number (10 to 15 digits).');
    } elseif (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        setFlash('error', 'Please enter a valid customer email address.');
    } elseif ($deliveryRequired && ($deliveryAddress === '' || $estimatedDeliveryDate === '')) {
        setFlash('error', 'Please enter delivery address and estimated delivery date.');
    } elseif (empty($cart)) {
        setFlash('error', 'Add at least one medicine before generating the bill.');
    } else {
        $_SESSION['billing_form_token'] = bin2hex(random_bytes(16));
        $conn->begin_transaction();
        try {
            $totalAmount = 0;
            foreach ($cart as $item) {
                $totalAmount += (float) $item['Sub_Total'];
            }

            $customerId = 0;
            $customerLookup = $conn->prepare('SELECT Customer_ID FROM customer WHERE Customer_Name = ? AND Phone = ? LIMIT 1');
            if (!$customerLookup) {
                throw new Exception('Could not prepare customer lookup query.');
            }

            $customerLookup->bind_param('ss', $customerName, $phoneDigits);
            $customerLookup->execute();
            $customerResult = $customerLookup->get_result();
            $customerRow = $customerResult ? $customerResult->fetch_assoc() : null;
            $customerLookup->close();

            if ($customerRow) {
                $customerId = (int) $customerRow['Customer_ID'];
            } else {
                $customerInsert = $conn->prepare('INSERT INTO customer (Customer_Name, Phone, Gender, Age) VALUES (?, ?, ?, ?)');
                if (!$customerInsert) {
                    throw new Exception('Could not prepare customer insert query.');
                }

                $defaultGender = 'Other';
                $defaultAge = 18;
                $customerInsert->bind_param('sssi', $customerName, $phoneDigits, $defaultGender, $defaultAge);
                if (!$customerInsert->execute()) {
                    throw new Exception('Could not create customer record.');
                }

                $customerId = (int) $conn->insert_id;
                $customerInsert->close();
            }

            $stmt = $conn->prepare('INSERT INTO bill (Customer_ID, Employee_ID, Total_Amount) VALUES (?, ?, ?)');
            $stmt->bind_param('iid', $customerId, $employeeId, $totalAmount);
            $stmt->execute();
            $billId = $conn->insert_id;
            $stmt->close();

            $stockStmt = $conn->prepare('SELECT Quantity FROM medicine WHERE Medicine_ID = ? FOR UPDATE');
            $saleStmt = $conn->prepare('INSERT INTO sales (Bill_ID, Medicine_ID, Quantity_Sold, Sub_Total) VALUES (?, ?, ?, ?)');
            $updateStmt = $conn->prepare('UPDATE medicine SET Quantity = Quantity - ? WHERE Medicine_ID = ?');

            foreach ($cart as $item) {
                $medicineId = (int) $item['Medicine_ID'];
                $soldQty = (int) $item['Quantity_Sold'];
                $subTotal = (float) $item['Sub_Total'];

                $stockStmt->bind_param('i', $medicineId);
                $stockStmt->execute();
                $stockResult = $stockStmt->get_result();
                $stockRow = $stockResult ? $stockResult->fetch_assoc() : null;

                if (!$stockRow || (int) $stockRow['Quantity'] < $soldQty) {
                    throw new Exception('Stock became insufficient while generating the bill.');
                }

                $saleStmt->bind_param('iiid', $billId, $medicineId, $soldQty, $subTotal);
                $saleStmt->execute();

                $updateStmt->bind_param('ii', $soldQty, $medicineId);
                $updateStmt->execute();
            }

            if ($deliveryRequired) {
                $deliveryStatus = 'Order Placed';
                if ($deliveryAgentId > 0) {
                    $deliveryInsert = $conn->prepare(
                        'INSERT INTO delivery_orders (bill_id, customer_name, customer_phone, customer_email, delivery_address, estimated_delivery_date, delivery_status, agent_id)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                    );

                    if (!$deliveryInsert) {
                        throw new Exception('Could not prepare delivery order insert query.');
                    }

                    $deliveryInsert->bind_param(
                        'issssssi',
                        $billId,
                        $customerName,
                        $phoneDigits,
                        $customerEmail,
                        $deliveryAddress,
                        $estimatedDeliveryDate,
                        $deliveryStatus,
                        $deliveryAgentId
                    );
                } else {
                    $deliveryInsert = $conn->prepare(
                        'INSERT INTO delivery_orders (bill_id, customer_name, customer_phone, customer_email, delivery_address, estimated_delivery_date, delivery_status)
                         VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );

                    if (!$deliveryInsert) {
                        throw new Exception('Could not prepare delivery order insert query.');
                    }

                    $deliveryInsert->bind_param(
                        'issssss',
                        $billId,
                        $customerName,
                        $phoneDigits,
                        $customerEmail,
                        $deliveryAddress,
                        $estimatedDeliveryDate,
                        $deliveryStatus
                    );
                }

                if (!$deliveryInsert->execute()) {
                    throw new Exception('Could not create delivery order.');
                }

                $deliveryId = (int) $conn->insert_id;
                $deliveryInsert->close();

                $trackingStatus = 'Order Placed';
                $trackingInsert = $conn->prepare('INSERT INTO delivery_tracking (delivery_id, old_status, new_status, remarks) VALUES (?, ?, ?, ?)');
                if ($trackingInsert) {
                    $oldStatus = '';
                    $remarks = 'Delivery created from billing module.';
                    $trackingInsert->bind_param('isss', $deliveryId, $oldStatus, $trackingStatus, $remarks);
                    $trackingInsert->execute();
                    $trackingInsert->close();
                }
            }

            $stockStmt->close();
            $saleStmt->close();
            $updateStmt->close();

            $conn->commit();
            $employeeName = 'Staff';
            $employeeLookup = $conn->prepare('SELECT Employee_Name FROM employee WHERE Employee_ID = ? LIMIT 1');
            if ($employeeLookup) {
                $employeeLookup->bind_param('i', $employeeId);
                $employeeLookup->execute();
                $employeeResult = $employeeLookup->get_result();
                if ($employeeResult && ($employeeRow = $employeeResult->fetch_assoc())) {
                    $employeeName = $employeeRow['Employee_Name'];
                }
                $employeeLookup->close();
            }

            $invoiceItems = [];
            foreach ($cart as $item) {
                $invoiceItems[] = [
                    'Medicine_Name' => $item['Medicine_Name'],
                    'Quantity_Sold' => $item['Quantity_Sold'],
                    'Price' => $item['Price'],
                    'Sub_Total' => $item['Sub_Total'],
                ];
            }

            $invoiceData = [
                'bill_id' => $billId,
                'date_time' => date('d M Y, h:i A'),
                'customer_name' => $customerName,
                'customer_phone' => $phoneDigits,
                'employee_name' => $employeeName,
                'total_amount' => $totalAmount,
                'items' => $invoiceItems,
            ];

            if ($deliveryRequired) {
                $agentName = 'Unassigned';
                if ($deliveryAgentId > 0) {
                    $agentLookup = $conn->prepare('SELECT agent_name FROM delivery_agents WHERE delivery_agent_id = ? LIMIT 1');
                    if ($agentLookup) {
                        $agentLookup->bind_param('i', $deliveryAgentId);
                        $agentLookup->execute();
                        $agentResult = $agentLookup->get_result();
                        if ($agentResult && ($agentRow = $agentResult->fetch_assoc())) {
                            $agentName = $agentRow['agent_name'];
                        }
                        $agentLookup->close();
                    }
                }

                $invoiceData['delivery_details'] = [
                    'status' => 'Order Placed',
                    'reference_number' => (string) $billId,
                    'address' => $deliveryAddress,
                    'estimated_date' => $estimatedDeliveryDate,
                    'agent_name' => $agentName,
                ];
            }

            $attachmentPath = __DIR__ . '/tmp/invoice-' . $billId . '.html';
            if (!is_dir(__DIR__ . '/tmp')) {
                mkdir(__DIR__ . '/tmp', 0777, true);
            }
            file_put_contents($attachmentPath, buildInvoiceEmailHtml($invoiceData));

            $mailResult = sendInvoiceEmail($invoiceData, $customerEmail, $customerName, !empty($mailConfig['attach_html_invoice']) ? $attachmentPath : null);
            if (is_file($attachmentPath)) {
                @unlink($attachmentPath);
            }

            $_SESSION['bill_cart'] = [];
            $_SESSION['last_bill_id'] = $billId;
            if ($mailResult[0]) {
                $deliveryNote = $deliveryRequired ? ' Delivery order created.' : '';
                setFlash('success', 'Purchase completed successfully. Invoice email sent successfully.' . $deliveryNote . ' Bill ID: #' . $billId . '.');
            } else {
                $deliveryNote = $deliveryRequired ? ' Delivery order created.' : '';
                setFlash('warning', 'Purchase completed successfully. ' . $deliveryNote . ' Invoice email not sent: ' . $mailResult[1] . '. Bill ID: #' . $billId . '.');
            }
        } catch (Throwable $exception) {
            $conn->rollback();
            setFlash('error', 'Bill generation failed: ' . $exception->getMessage());
        }
    }

    header('Location: billing.php');
    exit;
}

if ($downloadBillId > 0) {
    $downloadStmt = $conn->prepare(
        'SELECT b.Bill_ID, b.Bill_Date, b.Total_Amount, c.Customer_Name, c.Phone, e.Employee_Name
         FROM bill b
         INNER JOIN customer c ON c.Customer_ID = b.Customer_ID
         INNER JOIN employee e ON e.Employee_ID = b.Employee_ID
         WHERE b.Bill_ID = ?
         LIMIT 1'
    );

    if ($downloadStmt) {
        $downloadStmt->bind_param('i', $downloadBillId);
        $downloadStmt->execute();
        $downloadResult = $downloadStmt->get_result();
        $downloadBill = $downloadResult ? $downloadResult->fetch_assoc() : null;
        $downloadStmt->close();

        if ($downloadBill) {
            $downloadItemsStmt = $conn->prepare(
                'SELECT s.Quantity_Sold, s.Sub_Total, m.Medicine_Name, m.Price
                 FROM sales s
                 INNER JOIN medicine m ON m.Medicine_ID = s.Medicine_ID
                 WHERE s.Bill_ID = ?
                 ORDER BY s.Sale_ID ASC'
            );

            if ($downloadItemsStmt) {
                $downloadItemsStmt->bind_param('i', $downloadBillId);
                $downloadItemsStmt->execute();
                $downloadItemsResult = $downloadItemsStmt->get_result();
                $downloadItems = [];
                if ($downloadItemsResult) {
                    while ($downloadItemRow = $downloadItemsResult->fetch_assoc()) {
                        $downloadItems[] = $downloadItemRow;
                    }
                }
                $downloadItemsStmt->close();

                $downloadInvoice = [
                    'bill_id' => $downloadBill['Bill_ID'],
                    'date_time' => date('d M Y, h:i A', strtotime($downloadBill['Bill_Date'])),
                    'customer_name' => $downloadBill['Customer_Name'],
                    'customer_phone' => $downloadBill['Phone'],
                    'employee_name' => $downloadBill['Employee_Name'],
                    'total_amount' => $downloadBill['Total_Amount'],
                    'items' => $downloadItems,
                ];

                header('Content-Type: text/html; charset=UTF-8');
                header('Content-Disposition: attachment; filename="invoice-' . $downloadBillId . '.html"');
                echo buildInvoiceEmailHtml($downloadInvoice);
                exit;
            }
        }
    }

    setFlash('error', 'Selected invoice could not be downloaded.');
    header('Location: billing.php');
    exit;
}

$employees = $conn->query('SELECT Employee_ID, Employee_Name FROM employee ORDER BY Employee_Name ASC');
$deliveryAgents = $conn->query('SELECT delivery_agent_id, agent_name FROM delivery_agents WHERE is_active = 1 ORDER BY agent_name ASC');
$medicines = $conn->query('SELECT Medicine_ID, Medicine_Name, Price, Quantity FROM medicine ORDER BY Medicine_Name ASC');
$cart = $_SESSION['bill_cart'];
$grandTotal = 0;
foreach ($cart as $item) {
    $grandTotal += (float) $item['Sub_Total'];
}

$selectedEmployee = $_POST['employee_id'] ?? '';
$customerNameInput = trim($_POST['customer_name'] ?? '');
$customerPhoneInput = trim($_POST['customer_phone'] ?? '');
$customerEmailInput = trim($_POST['customer_email'] ?? '');
$deliveryRequiredInput = isset($_POST['delivery_required']) ? '1' : '';
$deliveryAddressInput = trim($_POST['delivery_address'] ?? '');
$estimatedDeliveryDateInput = trim($_POST['estimated_delivery_date'] ?? '');
$deliveryAgentInput = (int) ($_POST['delivery_agent_id'] ?? 0);

$billingHistory = $conn->query(
    "SELECT
        b.Bill_ID,
        b.Total_Amount,
        b.Bill_Date,
        c.Customer_Name,
        c.Phone,
        COALESCE(
            GROUP_CONCAT(CONCAT(m.Medicine_Name, ' (', s.Quantity_Sold, ')') ORDER BY m.Medicine_Name SEPARATOR ', '),
            ''
        ) AS Medicines
    FROM bill b
    INNER JOIN customer c ON c.Customer_ID = b.Customer_ID
    LEFT JOIN sales s ON s.Bill_ID = b.Bill_ID
    LEFT JOIN medicine m ON m.Medicine_ID = s.Medicine_ID
    GROUP BY b.Bill_ID, b.Total_Amount, b.Bill_Date, c.Customer_Name, c.Phone
    ORDER BY b.Bill_ID DESC
    LIMIT 30"
);

$printBill = null;
$lastBillId = (int) ($_SESSION['last_bill_id'] ?? 0);

if ($lastBillId > 0) {
    $billStmt = $conn->prepare(
        'SELECT b.Bill_ID, b.Bill_Date, b.Total_Amount, c.Customer_Name, c.Phone, e.Employee_Name
         FROM bill b
         INNER JOIN customer c ON c.Customer_ID = b.Customer_ID
         INNER JOIN employee e ON e.Employee_ID = b.Employee_ID
         WHERE b.Bill_ID = ?
         LIMIT 1'
    );

    if ($billStmt) {
        $billStmt->bind_param('i', $lastBillId);
        $billStmt->execute();
        $billResult = $billStmt->get_result();
        $billRow = $billResult ? $billResult->fetch_assoc() : null;
        $billStmt->close();

        if ($billRow) {
            $itemsStmt = $conn->prepare(
                'SELECT s.Quantity_Sold, s.Sub_Total, m.Medicine_Name, m.Price
                 FROM sales s
                 INNER JOIN medicine m ON m.Medicine_ID = s.Medicine_ID
                 WHERE s.Bill_ID = ?
                 ORDER BY s.Sale_ID ASC'
            );

            if ($itemsStmt) {
                $itemsStmt->bind_param('i', $lastBillId);
                $itemsStmt->execute();
                $itemsResult = $itemsStmt->get_result();
                $items = [];

                if ($itemsResult) {
                    while ($itemRow = $itemsResult->fetch_assoc()) {
                        $items[] = $itemRow;
                    }
                }

                $itemsStmt->close();

                $printBill = [
                    'Bill_ID' => $billRow['Bill_ID'],
                    'Bill_Date' => $billRow['Bill_Date'],
                    'Total_Amount' => $billRow['Total_Amount'],
                    'Customer_Name' => $billRow['Customer_Name'],
                    'Customer_Phone' => $billRow['Phone'],
                    'Employee_Name' => $billRow['Employee_Name'],
                    'Items' => $items,
                ];
            }
        }
    }
}

$invoiceReady = $printBill !== null && empty($cart);
$invoiceStatusLabel = $invoiceReady ? 'Purchase completed successfully.' : 'Draft Invoice';
$displayBill = $invoiceReady ? $printBill : null;
$currentBillingActor = currentUserName();

include 'includes/header.php';
?>
<section class="billing-topbar glass-card">
    <div>
        <div class="section-kicker">Billing module</div>
        <h2 class="billing-title">Create compact invoices with a pharmacy POS flow</h2>
        <p class="muted billing-subtitle"><?php echo h($invoiceStatusLabel); ?> Add medicine items, then complete the purchase to save and print the invoice.</p>
    </div>
    <div class="billing-actions">
        <div class="billing-actor">
            <span class="muted">Billed by</span>
            <strong><?php echo h($currentBillingActor); ?></strong>
        </div>
        <div class="btn-row">
            <?php if ($invoiceReady): ?>
                <button type="button" class="btn btn-secondary" onclick="printBillSection()">Print Bill</button>
                <a class="btn btn-primary" href="billing.php?download=<?php echo (int) $printBill['Bill_ID']; ?>">Download Invoice</a>
            <?php else: ?>
                <span class="badge badge-warning">Draft Invoice</span>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="billing-layout">
    <div class="billing-column billing-column-main">
        <div id="printableBill" class="invoice-print-sheet glass-card">
            <div class="invoice-brand-row">
                <div class="invoice-logo" aria-hidden="true">SP</div>
                <div>
                    <h2 class="invoice-pharmacy-title">Smart Pharmacy</h2>
                    <p class="invoice-subtitle">Inventory and Billing System</p>
                </div>
            </div>

            <div class="invoice-meta-grid">
                <div><strong>Bill ID:</strong> <?php echo $displayBill ? ('#' . (int) $displayBill['Bill_ID']) : 'Draft'; ?></div>
                <div><strong>Date:</strong> <?php echo $displayBill ? h(date('d M Y, h:i A', strtotime($displayBill['Bill_Date']))) : h(date('d M Y, h:i A')); ?></div>
                <div><strong>Customer:</strong> <?php echo $displayBill ? h($displayBill['Customer_Name']) : 'Draft Invoice'; ?></div>
                <div><strong>Customer Phone:</strong> <?php echo $displayBill ? h($displayBill['Customer_Phone']) : '-'; ?></div>
                <div><strong>Billed By:</strong> <?php echo $displayBill ? h($displayBill['Employee_Name']) : h($currentBillingActor); ?></div>
            </div>

            <div class="invoice-table-wrap">
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($displayBill && !empty($displayBill['Items'])): ?>
                            <?php foreach ($displayBill['Items'] as $invoiceItem): ?>
                                <tr>
                                    <td><?php echo h($invoiceItem['Medicine_Name']); ?></td>
                                    <td><?php echo (int) $invoiceItem['Quantity_Sold']; ?></td>
                                    <td><?php echo money($invoiceItem['Price']); ?></td>
                                    <td><?php echo money($invoiceItem['Sub_Total']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php elseif (!empty($cart)): ?>
                            <?php foreach ($cart as $invoiceItem): ?>
                                <tr>
                                    <td><?php echo h($invoiceItem['Medicine_Name']); ?></td>
                                    <td><?php echo (int) $invoiceItem['Quantity_Sold']; ?></td>
                                    <td><?php echo money($invoiceItem['Price']); ?></td>
                                    <td><?php echo money($invoiceItem['Sub_Total']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">No bill generated yet. Add items and click Generate Bill.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="invoice-total-row">
                <span>Grand Total</span>
                <strong>
                    <?php
                    if ($displayBill) {
                        echo money($displayBill['Total_Amount']);
                    } else {
                        echo money($grandTotal);
                    }
                    ?>
                </strong>
            </div>

            <p class="invoice-footer-note">Thank you for visiting Smart Pharmacy</p>
        </div>

        <div class="table-card billing-cart-card">
            <h3 class="section-title">Current Bill Cart</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($cart)): ?>
                            <?php foreach ($cart as $index => $item): ?>
                                <tr>
                                    <td><?php echo h($item['Medicine_Name']); ?></td>
                                    <td><?php echo money($item['Price']); ?></td>
                                    <td><?php echo h($item['Quantity_Sold']); ?></td>
                                    <td><?php echo money($item['Sub_Total']); ?></td>
                                    <td><a class="btn btn-danger" href="billing.php?remove=<?php echo (int) $index; ?>" onclick="return confirm('Remove this item?');">Remove</a></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td colspan="3"><strong>Grand Total</strong></td>
                                <td colspan="2"><strong><?php echo money($grandTotal); ?></strong></td>
                            </tr>
                        <?php else: ?>
                            <tr><td colspan="5">No items added to the bill yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <section class="table-card billing-history-section">
            <h3 class="section-title">Billing History</h3>
            <div class="table-wrap billing-history-wrap">
                <table class="billing-history-table">
                    <thead>
                        <tr>
                            <th>Bill ID</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Medicines</th>
                            <th>Total Amount</th>
                            <th>Bill Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($billingHistory && $billingHistory->num_rows > 0): ?>
                            <?php while ($historyRow = $billingHistory->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo (int) $historyRow['Bill_ID']; ?></td>
                                    <td><?php echo h($historyRow['Customer_Name']); ?></td>
                                    <td><?php echo h($historyRow['Phone']); ?></td>
                                    <td><?php echo h($historyRow['Medicines'] !== '' ? $historyRow['Medicines'] : 'No items'); ?></td>
                                    <td><?php echo money($historyRow['Total_Amount']); ?></td>
                                    <td><?php echo h(date('d M Y, h:i A', strtotime($historyRow['Bill_Date']))); ?></td>
                                    <td><a class="btn btn-secondary" href="billing.php?view=<?php echo (int) $historyRow['Bill_ID']; ?>">View Bill</a></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7">No billing history found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <aside class="billing-column billing-column-side">
        <div class="form-card sticky-panel">
            <h3 class="section-title">Add Medicine to Bill</h3>
            <form method="post" class="needs-validation" id="billingForm">
                <input type="hidden" name="add_item" value="1">
                <div class="form-grid">
                    <div class="form-group full">
                        <label for="medicine_id">Medicine</label>
                        <select id="medicine_id" name="medicine_id" required>
                            <option value="">Select Medicine</option>
                            <?php if ($medicines && $medicines->num_rows > 0): ?>
                                <?php while ($row = $medicines->fetch_assoc()): ?>
                                    <option value="<?php echo (int) $row['Medicine_ID']; ?>" data-price="<?php echo h($row['Price']); ?>" data-stock="<?php echo (int) $row['Quantity']; ?>">
                                        <?php echo h($row['Medicine_Name']); ?> - Stock: <?php echo (int) $row['Quantity']; ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="quantity_sold">Quantity</label>
                        <input type="number" id="quantity_sold" name="quantity_sold" min="1" required value="1">
                    </div>
                    <div class="form-group full muted" id="stock_preview">Available stock: 0</div>
                    <div class="form-group full muted" id="price_preview">Unit Price: Rs. 0.00</div>
                    <div class="form-group full muted" id="line_subtotal">Line Subtotal: Rs. 0.00</div>
                </div>
                <div class="form-actions" style="margin-top: 16px;">
                    <button type="submit" class="btn btn-primary">Add Item</button>
                </div>
            </form>
        </div>

        <div class="form-card sticky-panel billing-summary-card">
            <h3 class="section-title">Bill Details</h3>
            <form method="post" class="needs-validation">
                <input type="hidden" name="generate_bill" value="1">
                <input type="hidden" name="billing_token" value="<?php echo h($_SESSION['billing_form_token'] ?? ''); ?>">
                <div class="form-grid">
                    <div class="form-group full">
                        <label for="customer_name">Customer Name</label>
                        <input type="text" id="customer_name" name="customer_name" required maxlength="100" placeholder="Enter customer name" value="<?php echo h($customerNameInput); ?>">
                    </div>
                    <div class="form-group">
                        <label for="customer_phone">Phone Number</label>
                        <input type="text" id="customer_phone" name="customer_phone" required maxlength="15" pattern="[0-9]{10,15}" placeholder="10 to 15 digit phone" value="<?php echo h($customerPhoneInput); ?>">
                    </div>
                    <div class="form-group">
                        <label for="customer_email">Email Address</label>
                        <input type="email" id="customer_email" name="customer_email" required placeholder="customer@example.com" value="<?php echo h($customerEmailInput); ?>">
                    </div>
                    <div class="form-group full checkbox-group">
                        <label class="checkbox-inline">
                            <input class="delivery-checkbox" type="checkbox" name="delivery_required" value="1" <?php echo $deliveryRequiredInput === '1' ? 'checked' : ''; ?>>
                            <span>Create delivery order</span>
                        </label>
                    </div>
                    <div class="form-group full">
                        <label for="delivery_address">Delivery Address</label>
                        <textarea id="delivery_address" name="delivery_address" placeholder="Customer delivery address"><?php echo h($deliveryAddressInput); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="estimated_delivery_date">Estimated Delivery Date</label>
                        <input type="date" id="estimated_delivery_date" name="estimated_delivery_date" value="<?php echo h($estimatedDeliveryDateInput); ?>">
                    </div>
                    <div class="form-group">
                        <label for="delivery_agent_id">Delivery Agent</label>
                        <select id="delivery_agent_id" name="delivery_agent_id">
                            <option value="">Assign later</option>
                            <?php if ($deliveryAgents && $deliveryAgents->num_rows > 0): ?>
                                <?php while ($agentRow = $deliveryAgents->fetch_assoc()): ?>
                                    <option value="<?php echo (int) $agentRow['delivery_agent_id']; ?>" <?php echo $deliveryAgentInput === (int) $agentRow['delivery_agent_id'] ? 'selected' : ''; ?>>
                                        <?php echo h($agentRow['agent_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="employee_id">Employee</label>
                        <select id="employee_id" name="employee_id" required>
                            <option value="">Select Employee</option>
                            <?php if ($employees && $employees->num_rows > 0): ?>
                                <?php while ($row = $employees->fetch_assoc()): ?>
                                    <option value="<?php echo (int) $row['Employee_ID']; ?>" <?php echo ((string) $selectedEmployee === (string) $row['Employee_ID']) ? 'selected' : ''; ?>><?php echo h($row['Employee_Name']); ?></option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <div class="form-actions" style="margin-top: 16px;">
                    <button type="submit" class="btn btn-success">Complete Purchase</button>
                </div>
            </form>
            <div class="alert alert-info" style="margin-top: 16px;">
                Current Bill Total: <?php echo money($grandTotal); ?>
            </div>
        </div>
    </aside>
</section>
<?php include 'includes/footer.php'; ?>