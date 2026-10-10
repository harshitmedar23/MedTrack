<?php
require_once 'includes/functions.php';
requireAdmin();

$pageTitle = 'Medicine Management';

$search = trim($_GET['search'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');
$expiredFilter = trim($_GET['expired'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

if (isset($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];
        $stmt = $conn->prepare('UPDATE medicine SET Is_Active = 0 WHERE Medicine_ID = ?');
    if ($stmt) {
        $stmt->bind_param('i', $deleteId);
        if ($stmt->execute()) {
                setFlash('success', 'Medicine moved to inactive status successfully.');
        } else {
                setFlash('error', 'Could not deactivate medicine.');
        }
        $stmt->close();
    }
    header('Location: medicines.php');
    exit;
}

$whereParts = [];
$params = [];
$types = '';
if ($search !== '') {
    $whereParts[] = 'm.Medicine_Name LIKE ?';
    $params[] = '%' . $search . '%';
    $types .= 's';
}
if ($categoryFilter !== '') {
    $whereParts[] = 'm.Category = ?';
    $params[] = $categoryFilter;
    $types .= 's';
}
if ($expiredFilter === '1') {
    $whereParts[] = 'm.Expiry_Date < CURDATE()';
}

$whereParts[] = 'm.Is_Active = 1';

$where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

$countSql = "SELECT COUNT(*) AS total
             FROM medicine m
             INNER JOIN supplier s ON s.Supplier_ID = m.Supplier_ID
             {$where}";
$countStmt = $conn->prepare($countSql);
if ($types === 's') {
    $countStmt->bind_param('s', $params[0]);
} elseif ($types === 'ss') {
    $countStmt->bind_param('ss', $params[0], $params[1]);
}
$countStmt->execute();
$totalRow = $countStmt->get_result()->fetch_assoc();
$totalRecords = (int) ($totalRow['total'] ?? 0);
$countStmt->close();

$totalPages = max(1, (int) ceil($totalRecords / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$categoryResult = $conn->query('SELECT DISTINCT Category FROM medicine ORDER BY Category ASC');

function medicineQueryString(array $overrides = [])
{
    $base = [
        'search' => $_GET['search'] ?? '',
        'category' => $_GET['category'] ?? '',
        'expired' => $_GET['expired'] ?? '',
    ];
    $query = array_merge($base, $overrides);
    return http_build_query(array_filter($query, static function ($value) {
        return $value !== '' && $value !== null;
    }));
}

$sql = "SELECT m.*, s.Supplier_Name
        FROM medicine m
        INNER JOIN supplier s ON s.Supplier_ID = m.Supplier_ID
        {$where}
        ORDER BY m.Medicine_ID DESC
        LIMIT {$perPage} OFFSET {$offset}";
$stmt = $conn->prepare($sql);
if ($types === 's') {
    $stmt->bind_param('s', $params[0]);
} elseif ($types === 'ss') {
    $stmt->bind_param('ss', $params[0], $params[1]);
}
$stmt->execute();
$medicines = $stmt->get_result();
$stmt->close();

include 'includes/header.php';
?>
<div class="card">
    <form method="get" class="page-toolbar medicine-filters">
        <input type="search" name="search" value="<?php echo h($search); ?>" placeholder="Search by medicine name...">
        <select name="category">
            <option value="">All Categories</option>
            <?php if ($categoryResult && $categoryResult->num_rows > 0): ?>
                <?php while ($categoryRow = $categoryResult->fetch_assoc()): ?>
                    <option value="<?php echo h($categoryRow['Category']); ?>" <?php echo $categoryFilter === $categoryRow['Category'] ? 'selected' : ''; ?>>
                        <?php echo h($categoryRow['Category']); ?>
                    </option>
                <?php endwhile; ?>
            <?php endif; ?>
        </select>
        <select name="expired">
            <option value="">All Medicines</option>
            <option value="1" <?php echo $expiredFilter === '1' ? 'selected' : ''; ?>>Expired Only</option>
        </select>
        <button class="btn btn-secondary" type="submit">Apply Filters</button>
        <button class="btn btn-secondary" type="submit">Apply Filters</button>
        <a class="btn btn-primary" href="medicines.php">Reset</a>
        <a class="btn btn-primary" href="add_medicine.php">Add Medicine</a>
    </form>
</div>

<div class="summary-grid medicine-summary-grid">
    <div class="stats-card compact-card stats-blue">
        <div class="label">Low Stock Alert</div>
        <div class="value"><?php echo getCount($conn, 'medicine', 'WHERE Quantity <= 10'); ?></div>
        <div class="value"><?php echo getCount($conn, 'medicine', 'WHERE Quantity <= 10 AND Is_Active = 1'); ?></div>
        <div class="hint">Quantity 10 or below</div>
    </div>
    <div class="stats-card compact-card stats-warning">
        <div class="label">Expired Medicines</div>
        <div class="value"><?php echo getCount($conn, 'medicine', 'WHERE Expiry_Date < CURDATE() AND Is_Active = 1'); ?></div>
        <div class="hint">Expired items in stock</div>
    </div>
    <div class="stats-card compact-card stats-blue">
        <div class="label">Filtered Records</div>
        <div class="value"><?php echo (int) $totalRecords; ?></div>
        <div class="hint">10 records per page</div>
    </div>
</div>

<div class="table-card">
    <h3 class="section-title">Medicine List</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Image</th>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Expiry Date</th>
                    <th>Supplier</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($medicines && $medicines->num_rows > 0): ?>
                    <?php while ($row = $medicines->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?php $thumbImage = medicineImageUrl($row['Medicine_Image'] ?? ''); ?>
                                <?php if ($thumbImage !== ''): ?>
                                    <img class="medicine-thumb" src="<?php echo h($thumbImage); ?>" alt="<?php echo h($row['Medicine_Name']); ?>">
                                <?php else: ?>
                                    <span class="badge badge-light">No Image</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo h($row['Medicine_ID']); ?></td>
                            <td><?php echo h($row['Medicine_Name']); ?></td>
                            <td><?php echo h($row['Category']); ?></td>
                            <td><?php echo money($row['Price']); ?></td>
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
                            <td>
                                <div class="table-actions">
                                    <a class="btn btn-secondary" href="add_medicine.php?id=<?php echo (int) $row['Medicine_ID']; ?>">Edit</a>
                                    <a class="btn btn-danger" href="medicines.php?delete=<?php echo (int) $row['Medicine_ID']; ?>" onclick="return confirm('Delete this medicine?');">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="9">No medicines found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination-bar">
        <span class="muted">Page <?php echo (int) $page; ?> of <?php echo (int) $totalPages; ?></span>
        <div class="btn-row">
            <?php if ($page > 1): ?>
                <a class="btn btn-secondary" href="medicines.php?<?php echo h(medicineQueryString(['page' => $page - 1])); ?>">Previous</a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
                <a class="btn btn-primary" href="medicines.php?<?php echo h(medicineQueryString(['page' => $page + 1])); ?>">Next</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>