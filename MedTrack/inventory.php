<?php
require_once 'includes/functions.php';
requireEmployee();

$pageTitle = 'Inventory Management';
$search = trim($_GET['search'] ?? '');

$where = '';
$params = [];
$types = '';
if ($search !== '') {
    $where = 'WHERE (m.Medicine_Name LIKE ? OR s.Supplier_Name LIKE ? OR m.Category LIKE ?) AND m.Is_Active = 1';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like];
    $types = 'sss';
} else {
    $where = 'WHERE m.Is_Active = 1';
}

$sql = "SELECT m.*, s.Supplier_Name
        FROM medicine m
        INNER JOIN supplier s ON s.Supplier_ID = m.Supplier_ID
        {$where}
        ORDER BY m.Quantity ASC, m.Expiry_Date ASC";
$stmt = $conn->prepare($sql);
if ($search !== '') {
    $like1 = $params[0];
    $like2 = $params[1];
    $like3 = $params[2];
    $stmt->bind_param($types, $like1, $like2, $like3);
}
$stmt->execute();
$inventory = $stmt->get_result();
$stmt->close();

$lowStockCount = getCount($conn, 'medicine', 'WHERE Quantity <= 10 AND Is_Active = 1');
$expiredCount = getCount($conn, 'medicine', 'WHERE Expiry_Date < CURDATE() AND Is_Active = 1');

$lowStock = $conn->query("SELECT Medicine_Name, Quantity, Expiry_Date FROM medicine WHERE Quantity <= 10 AND Is_Active = 1 ORDER BY Quantity ASC");
$expired = $conn->query("SELECT Medicine_Name, Quantity, Expiry_Date FROM medicine WHERE Expiry_Date < CURDATE() AND Is_Active = 1 ORDER BY Expiry_Date ASC");

include 'includes/header.php';
?>
<div class="summary-grid">
    <div class="stats-card">
        <div class="label">Low Stock Medicines</div>
        <div class="value"><?php echo (int) $lowStockCount; ?></div>
        <div class="hint">Quantity 10 or below</div>
    </div>
    <div class="stats-card">
        <div class="label">Expired Medicines</div>
        <div class="value"><?php echo (int) $expiredCount; ?></div>
        <div class="hint">Needs immediate action</div>
    </div>
</div>

<div class="card">
    <form method="get" class="page-toolbar">
        <input type="search" name="search" value="<?php echo h($search); ?>" placeholder="Search inventory...">
        <button class="btn btn-secondary" type="submit">Search</button>
        <a class="btn btn-primary" href="inventory.php">Reset</a>
    </form>
</div>

<div class="panel-grid">
    <div class="table-card">
        <h3 class="section-title">Complete Inventory</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Expiry Date</th>
                        <th>Supplier</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($inventory && $inventory->num_rows > 0): ?>
                        <?php while ($row = $inventory->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo h($row['Medicine_Name']); ?></td>
                                <td><?php echo h($row['Category']); ?></td>
                                <td>
                                    <?php if ((int) $row['Quantity'] <= 10): ?>
                                        <span class="badge badge-warning"><?php echo h($row['Quantity']); ?></span>
                                    <?php else: ?>
                                        <?php echo h($row['Quantity']); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (strtotime($row['Expiry_Date']) < strtotime(date('Y-m-d'))): ?>
                                        <span class="badge badge-danger"><?php echo h(date('d M Y', strtotime($row['Expiry_Date']))); ?></span>
                                    <?php else: ?>
                                        <?php echo h(date('d M Y', strtotime($row['Expiry_Date']))); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo h($row['Supplier_Name']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5">No inventory records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h3 class="section-title">Low Stock Medicines</h3>
        <div class="quick-list" style="margin-bottom: 18px;">
            <?php if ($lowStock && $lowStock->num_rows > 0): ?>
                <?php while ($row = $lowStock->fetch_assoc()): ?>
                    <div class="quick-item">
                        <span><?php echo h($row['Medicine_Name']); ?></span>
                        <strong><?php echo h($row['Quantity']); ?></strong>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="muted">No low stock medicines.</div>
            <?php endif; ?>
        </div>

        <h3 class="section-title">Expired Medicines</h3>
        <div class="quick-list">
            <?php if ($expired && $expired->num_rows > 0): ?>
                <?php while ($row = $expired->fetch_assoc()): ?>
                    <div class="quick-item">
                        <span><?php echo h($row['Medicine_Name']); ?></span>
                        <strong><?php echo h(date('d M Y', strtotime($row['Expiry_Date']))); ?></strong>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="muted">No expired medicines.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>