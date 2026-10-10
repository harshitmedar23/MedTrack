<?php
require_once 'includes/functions.php';
requireAdmin();

$pageTitle = 'Supplier Management';

$search = trim($_GET['search'] ?? '');
$editId = (int) ($_GET['edit'] ?? 0);
$supplier = [
    'Supplier_Name' => '',
    'Phone' => '',
    'Address' => '',
];

if (isset($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];
    $stmt = $conn->prepare('DELETE FROM supplier WHERE Supplier_ID = ?');
    if ($stmt) {
        $stmt->bind_param('i', $deleteId);
        if ($stmt->execute()) {
            setFlash('success', 'Supplier deleted successfully.');
        } else {
            setFlash('error', 'Cannot delete supplier because medicines are linked to it.');
        }
        $stmt->close();
    }
    header('Location: suppliers.php');
    exit;
}

if ($editId > 0) {
    $stmt = $conn->prepare('SELECT * FROM supplier WHERE Supplier_ID = ?');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $supplier = $result->fetch_assoc() ?: $supplier;
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplierId = (int) ($_POST['supplier_id'] ?? 0);
    $supplierName = trim($_POST['supplier_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($supplierName === '' || $phone === '' || $address === '') {
        setFlash('error', 'Please fill in all supplier fields.');
    } else {
        if ($supplierId > 0) {
            $stmt = $conn->prepare('UPDATE supplier SET Supplier_Name = ?, Phone = ?, Address = ? WHERE Supplier_ID = ?');
            $stmt->bind_param('sssi', $supplierName, $phone, $address, $supplierId);
            $message = 'Supplier updated successfully.';
        } else {
            $stmt = $conn->prepare('INSERT INTO supplier (Supplier_Name, Phone, Address) VALUES (?, ?, ?)');
            $stmt->bind_param('sss', $supplierName, $phone, $address);
            $message = 'Supplier added successfully.';
        }

        if ($stmt && $stmt->execute()) {
            setFlash('success', $message);
            $stmt->close();
            header('Location: suppliers.php');
            exit;
        }

        setFlash('error', 'Could not save supplier details.');
        if ($stmt) {
            $stmt->close();
        }
    }
}

$where = '';
$params = [];
$types = '';
if ($search !== '') {
    $where = 'WHERE Supplier_Name LIKE ? OR Phone LIKE ? OR Address LIKE ?';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like];
    $types = 'sss';
}

$sql = 'SELECT * FROM supplier ' . $where . ' ORDER BY Supplier_ID DESC';
$stmt = $conn->prepare($sql);
if ($search !== '') {
    $like1 = $params[0];
    $like2 = $params[1];
    $like3 = $params[2];
    $stmt->bind_param($types, $like1, $like2, $like3);
}
$stmt->execute();
$suppliers = $stmt->get_result();
$stmt->close();

include 'includes/header.php';
?>
<div class="card">
    <form method="get" class="page-toolbar">
        <input type="search" name="search" value="<?php echo h($search); ?>" placeholder="Search suppliers...">
        <button class="btn btn-secondary" type="submit">Search</button>
        <a class="btn btn-primary" href="suppliers.php">Reset</a>
    </form>
</div>

<div class="panel-grid">
    <div class="form-card">
        <h3 class="section-title"><?php echo $editId > 0 ? 'Edit Supplier' : 'Add Supplier'; ?></h3>
        <form method="post" class="needs-validation">
            <input type="hidden" name="supplier_id" value="<?php echo (int) $editId; ?>">
            <div class="form-grid">
                <div class="form-group full">
                    <label for="supplier_name">Supplier Name</label>
                    <input type="text" id="supplier_name" name="supplier_name" required value="<?php echo h($supplier['Supplier_Name']); ?>">
                </div>
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" required value="<?php echo h($supplier['Phone']); ?>">
                </div>
                <div class="form-group full">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" required><?php echo h($supplier['Address']); ?></textarea>
                </div>
            </div>
            <div class="form-actions" style="margin-top: 16px;">
                <button type="submit" class="btn btn-primary"><?php echo $editId > 0 ? 'Update Supplier' : 'Save Supplier'; ?></button>
                <?php if ($editId > 0): ?>
                    <a class="btn btn-secondary" href="suppliers.php">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="table-card">
        <h3 class="section-title">Supplier List</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($suppliers && $suppliers->num_rows > 0): ?>
                        <?php while ($row = $suppliers->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo h($row['Supplier_ID']); ?></td>
                                <td><?php echo h($row['Supplier_Name']); ?></td>
                                <td><?php echo h($row['Phone']); ?></td>
                                <td><?php echo h($row['Address']); ?></td>
                                <td>
                                    <div class="table-actions">
                                        <a class="btn btn-secondary" href="suppliers.php?edit=<?php echo (int) $row['Supplier_ID']; ?>">Edit</a>
                                        <a class="btn btn-danger" href="suppliers.php?delete=<?php echo (int) $row['Supplier_ID']; ?>" onclick="return confirm('Delete this supplier?');">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5">No suppliers found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>