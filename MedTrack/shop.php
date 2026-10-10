<?php
require_once 'includes/functions.php';
requireCustomer();

$customerId = getCustomerIdFromSession();
$cartCount = getCustomerCartItemCount($conn, $customerId);
$pageTitle = 'Medicine Shop';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $medicineId = (int) ($_POST['medicine_id'] ?? 0);
    $quantity = max(1, (int) ($_POST['quantity'] ?? 1));

    if ($medicineId <= 0) {
        $error = 'Please select a medicine.';
    } else {
        $medicineStmt = $conn->prepare('SELECT Medicine_ID, Medicine_Name, Price, Quantity, Is_Active FROM medicine WHERE Medicine_ID = ? LIMIT 1');
        if ($medicineStmt) {
            $medicineStmt->bind_param('i', $medicineId);
            $medicineStmt->execute();
            $medicineResult = $medicineStmt->get_result();
            $medicine = $medicineResult ? $medicineResult->fetch_assoc() : null;
            $medicineStmt->close();

            if (!$medicine || (int) $medicine['Is_Active'] !== 1) {
                $error = 'Selected medicine is not available.';
            } elseif ((int) $medicine['Quantity'] <= 0) {
                $error = 'Selected medicine is out of stock.';
            } elseif ($quantity > (int) $medicine['Quantity']) {
                $error = 'Requested quantity is greater than available stock.';
            } else {
                $cart = getOrCreateActiveCart($conn, $customerId);
                if (!$cart) {
                    $error = 'Could not create your cart.';
                } else {
                    $cartId = (int) $cart['Cart_ID'];
                    $itemStmt = $conn->prepare('SELECT Cart_Item_ID, Quantity FROM cart_items WHERE Cart_ID = ? AND Medicine_ID = ? LIMIT 1');
                    if ($itemStmt) {
                        $itemStmt->bind_param('ii', $cartId, $medicineId);
                        $itemStmt->execute();
                        $itemResult = $itemStmt->get_result();
                        $item = $itemResult ? $itemResult->fetch_assoc() : null;
                        $itemStmt->close();

                        $targetQuantity = $quantity + (int) ($item['Quantity'] ?? 0);
                        if ($targetQuantity > (int) $medicine['Quantity']) {
                            $error = 'Requested quantity exceeds available stock.';
                        } else {
                            if ($item) {
                                $updateStmt = $conn->prepare('UPDATE cart_items SET Quantity = ?, Unit_Price = ? WHERE Cart_Item_ID = ?');
                                if ($updateStmt) {
                                    $unitPrice = (float) $medicine['Price'];
                                    $cartItemId = (int) $item['Cart_Item_ID'];
                                    $updateStmt->bind_param('idi', $targetQuantity, $unitPrice, $cartItemId);
                                    $updateStmt->execute();
                                    $updateStmt->close();
                                }
                            } else {
                                $insertStmt = $conn->prepare('INSERT INTO cart_items (Cart_ID, Medicine_ID, Quantity, Unit_Price) VALUES (?, ?, ?, ?)');
                                if ($insertStmt) {
                                    $unitPrice = (float) $medicine['Price'];
                                    $insertStmt->bind_param('iiid', $cartId, $medicineId, $quantity, $unitPrice);
                                    $insertStmt->execute();
                                    $insertStmt->close();
                                }
                            }

                            setFlash('success', $medicine['Medicine_Name'] . ' added to cart.');
                            header('Location: shop.php');
                            exit;
                        }
                    }
                }
            }
        }
    }
}

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');

$categoriesResult = $conn->query("SELECT DISTINCT Category FROM medicine WHERE Is_Active = 1 ORDER BY Category ASC");

$featuredResult = $conn->query(
    "SELECT Medicine_ID, Medicine_Name, Category, Price, Quantity, Expiry_Date, Medicine_Image
     FROM medicine
     WHERE Is_Active = 1
     ORDER BY CASE WHEN Quantity <= 5 THEN 0 ELSE 1 END, Medicine_ID DESC
     LIMIT 6"
);

$recentResult = $conn->query(
    "SELECT Medicine_ID, Medicine_Name, Category, Price, Quantity, Expiry_Date, Medicine_Image
     FROM medicine
     WHERE Is_Active = 1
     ORDER BY Medicine_ID DESC
     LIMIT 6"
);

$popularResult = $conn->query(
    "SELECT m.Medicine_ID, m.Medicine_Name, m.Category, m.Price, m.Quantity, m.Expiry_Date, m.Medicine_Image, COALESCE(SUM(s.Quantity_Sold), 0) AS sold_qty
     FROM medicine m
     LEFT JOIN sales s ON s.Medicine_ID = m.Medicine_ID
     WHERE m.Is_Active = 1
     GROUP BY m.Medicine_ID, m.Medicine_Name, m.Category, m.Price, m.Quantity, m.Expiry_Date, m.Medicine_Image
     ORDER BY sold_qty DESC, m.Medicine_Name ASC
     LIMIT 6"
);

$medicineRows = [];
if ($search !== '' || $category !== '') {
    $searchPool = searchMedicineSuggestions($conn, $search, $category, 60);
    foreach ($searchPool as $row) {
        if ($category !== '' && $row['Category'] !== $category) {
            continue;
        }
        $medicineRows[] = $row;
    }
} else {
    $stmt = $conn->prepare('SELECT Medicine_ID, Medicine_Name, Category, Price, Quantity, Expiry_Date, Medicine_Image FROM medicine WHERE Is_Active = 1 ORDER BY Medicine_Name ASC');
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($result && ($row = $result->fetch_assoc())) {
            $medicineRows[] = $row;
        }
        $stmt->close();
    }
}

$categoryCounts = [];
$categoryCountResult = $conn->query('SELECT Category, COUNT(*) AS total FROM medicine WHERE Is_Active = 1 GROUP BY Category ORDER BY Category ASC');
if ($categoryCountResult) {
    while ($row = $categoryCountResult->fetch_assoc()) {
        $categoryCounts[] = $row;
    }
}

function shopMedicineCard(array $medicine): string
{
    $imageUrl = medicineImageUrl($medicine['Medicine_Image']);
    if ($imageUrl === '') {
        $imageUrl = medicinePlaceholderImageUrl();
    }

    $quantity = (int) $medicine['Quantity'];
    $expiryDate = !empty($medicine['Expiry_Date']) ? strtotime($medicine['Expiry_Date']) : false;
    $expiryLabel = '';
    if ($expiryDate !== false) {
        $daysLeft = (int) floor(($expiryDate - time()) / 86400);
        if ($daysLeft <= 30 && $daysLeft >= 0) {
            $expiryLabel = '<span class="badge badge-warning">Expiring soon</span>';
        } elseif ($daysLeft < 0) {
            $expiryLabel = '<span class="badge badge-danger">Expired</span>';
        }
    }

    $stockClass = medicineStockBadgeClass($quantity);
    $stockText = $quantity <= 0 ? 'Out of stock' : ($quantity <= 5 ? 'Low stock' : 'In stock');

    ob_start(); ?>
    <article class="medicine-card-modern">
        <div class="medicine-card-image">
            <img src="<?php echo h($imageUrl); ?>" alt="<?php echo h($medicine['Medicine_Name']); ?>">
            <div class="medicine-card-topbar">
                <span class="badge badge-light"><?php echo h($medicine['Category']); ?></span>
                <?php echo $expiryLabel; ?>
            </div>
        </div>
        <div class="medicine-card-content">
            <div>
                <h3><?php echo h($medicine['Medicine_Name']); ?></h3>
                <p class="medicine-subtitle">Trusted pharmacy supply with responsive support.</p>
            </div>
            <div class="medicine-meta-row">
                <span class="medicine-price"><?php echo money($medicine['Price']); ?></span>
                <span class="badge <?php echo h($stockClass); ?>"><?php echo h($stockText); ?>: <?php echo $quantity; ?></span>
            </div>
            <div class="medicine-action-row">
                <form method="post" class="medicine-cart-form needs-validation">
                    <input type="hidden" name="medicine_id" value="<?php echo (int) $medicine['Medicine_ID']; ?>">
                    <input type="number" name="quantity" min="1" max="<?php echo $quantity > 0 ? (int) $quantity : 1; ?>" value="1" <?php echo $quantity <= 0 ? 'disabled' : ''; ?>>
                    <button class="btn btn-primary" type="submit" name="add_to_cart" <?php echo $quantity <= 0 ? 'disabled' : ''; ?>><?php echo $quantity <= 0 ? 'Out of Stock' : 'Add to Cart'; ?></button>
                </form>
            </div>
        </div>
    </article>
    <?php
    return trim((string) ob_get_clean());
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
        <?php echo renderCustomerNavbar('shop', $cartCount); ?>

        <section class="customer-hero card">
            <div class="customer-hero-copy">
                <span class="hero-kicker">Modern medicine shopping</span>
                <h1>Find medicines faster, compare stock instantly, and checkout with confidence.</h1>
                <p>Search with live suggestions, filter by category, and add items to cart with a cleaner pharmacy storefront experience.</p>
                <div class="hero-action-row">
                    <a class="btn btn-primary" href="cart.php">Open Cart</a>
                    <a class="btn btn-secondary" href="my_orders.php">My Orders</a>
                </div>
            </div>
            <div class="customer-hero-card">
                <div class="hero-stat">
                    <span>Active items</span>
                    <strong><?php echo count($medicineRows); ?></strong>
                </div>
                <div class="hero-stat">
                    <span>Cart items</span>
                    <strong><?php echo (int) $cartCount; ?></strong>
                </div>
                <div class="hero-stat">
                    <span>Fast search</span>
                    <strong>Live suggestions</strong>
                </div>
            </div>
        </section>
        </section>

        <div class="toast-stack">
            <?php echo renderFlash(); ?>
        </div>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo h($error); ?></div>
        <?php endif; ?>

        <div class="category-chip-row" style="margin-top:10px;">
            <a class="category-chip <?php echo $category === '' ? 'active' : ''; ?>" href="shop.php">All</a>
            <?php foreach ($categoryCounts as $categoryRow): ?>
                <?php echo renderCategoryChip($categoryRow['Category'], $categoryRow['Category'], $category); ?>
            <?php endforeach; ?>
        </div>

        <section class="customer-section">
            <div class="section-heading">
                <div>
                    <span class="section-kicker">Featured</span>
                    <h2>Recommended medicines</h2>
                </div>
                <p>Popular picks and high-priority stock that customers usually reach for first.</p>
            </div>
            <div class="medicine-grid">
                <?php if ($featuredResult && $featuredResult->num_rows > 0): ?>
                    <?php while ($featuredMedicine = $featuredResult->fetch_assoc()): ?>
                        <?php echo shopMedicineCard($featuredMedicine); ?>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="customer-section">
            <div class="section-heading">
                <div>
                    <span class="section-kicker">Shop</span>
                    <h2>Browse medicines</h2>
                </div>
                <p><?php echo $search !== '' ? 'Ranked results for your search.' : 'All active medicines in a card-based layout.'; ?></p>
            </div>

            <?php if (!empty($medicineRows)): ?>
                <div class="medicine-grid medicine-grid-browse">
                    <?php foreach ($medicineRows as $medicine): ?>
                        <?php echo shopMedicineCard($medicine); ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state card">
                    <img src="<?php echo h(medicinePlaceholderImageUrl()); ?>" alt="No medicines found">
                    <h3>No medicines found</h3>
                    <p>Try another keyword or change the category filter.</p>
                </div>
            <?php endif; ?>
        </section>

        <section class="customer-section customer-section-grid">
            <div class="table-card">
                <div class="section-heading compact">
                    <div>
                        <span class="section-kicker">Popular</span>
                        <h2>Popular medicines</h2>
                    </div>
                </div>
                <div class="mini-list">
                    <?php if ($popularResult && $popularResult->num_rows > 0): ?>
                        <?php while ($popularMedicine = $popularResult->fetch_assoc()): ?>
                            <div class="mini-list-item">
                                <div>
                                    <strong><?php echo h($popularMedicine['Medicine_Name']); ?></strong><br>
                                    <span class="muted"><?php echo h($popularMedicine['Category']); ?></span>
                                </div>
                                <div class="mini-list-metrics">
                                    <span><?php echo money($popularMedicine['Price']); ?></span>
                                    <strong><?php echo (int) $popularMedicine['sold_qty']; ?> sold</strong>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-card">
                <div class="section-heading compact">
                    <div>
                        <span class="section-kicker">Recent</span>
                        <h2>Recently added</h2>
                    </div>
                </div>
                <div class="mini-list">
                    <?php if ($recentResult && $recentResult->num_rows > 0): ?>
                        <?php while ($recentMedicine = $recentResult->fetch_assoc()): ?>
                            <div class="mini-list-item">
                                <div>
                                    <strong><?php echo h($recentMedicine['Medicine_Name']); ?></strong><br>
                                    <span class="muted"><?php echo h($recentMedicine['Category']); ?></span>
                                </div>
                                <div class="mini-list-metrics">
                                    <span><?php echo money($recentMedicine['Price']); ?></span>
                                    <strong><?php echo (int) $recentMedicine['Quantity']; ?> stock</strong>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
</div>
</body>
</html>