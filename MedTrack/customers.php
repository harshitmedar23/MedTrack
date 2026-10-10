<?php
require_once 'includes/functions.php';
requireAdmin();

$pageTitle = 'Customer Management';

$search = trim($_GET['search'] ?? '');
$editId = (int) ($_GET['edit'] ?? 0);
$customer = [
    'Customer_Name' => '',
    'Phone' => '',
    'Gender' => 'Male',
    'Age' => '',
];

if (isset($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];
    $stmt = $conn->prepare('DELETE FROM customer WHERE Customer_ID = ?');
    if ($stmt) {
        $stmt->bind_param('i', $deleteId);
        if ($stmt->execute()) {
            setFlash('success', 'Customer deleted successfully.');
        } else {
            setFlash('error', 'Cannot delete customer because bills are linked to it.');
        }
        $stmt->close();
    }
    header('Location: customers.php');
    exit;
}

if ($editId > 0) {
    $stmt = $conn->prepare('SELECT * FROM customer WHERE Customer_ID = ?');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $customer = $result->fetch_assoc() ?: $customer;
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int) ($_POST['customer_id'] ?? 0);
    $customerName = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $gender = trim($_POST['gender'] ?? 'Male');
    $age = (int) ($_POST['age'] ?? 0);

    if ($customerName === '' || $phone === '' || $age <= 0) {
        setFlash('error', 'Please fill in all customer fields correctly.');
    } else {
        if ($customerId > 0) {
            $stmt = $conn->prepare('UPDATE customer SET Customer_Name = ?, Phone = ?, Gender = ?, Age = ? WHERE Customer_ID = ?');
            $stmt->bind_param('sssii', $customerName, $phone, $gender, $age, $customerId);
            $message = 'Customer updated successfully.';
        } else {
            $stmt = $conn->prepare('INSERT INTO customer (Customer_Name, Phone, Gender, Age) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('sssi', $customerName, $phone, $gender, $age);
            $message = 'Customer added successfully.';
        }

        if ($stmt && $stmt->execute()) {
            setFlash('success', $message);
            $stmt->close();
            header('Location: customers.php');
            exit;
        }

        setFlash('error', 'Could not save customer details.');
        if ($stmt) {
            $stmt->close();
        }
    }
}

$where = '';
$params = [];
$types = '';
if ($search !== '') {
    $where = 'WHERE Customer_Name LIKE ? OR Phone LIKE ? OR Gender LIKE ?';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like];
    $types = 'sss';
}

$sql = 'SELECT * FROM customer ' . $where . ' ORDER BY Customer_ID DESC';
$stmt = $conn->prepare($sql);
if ($search !== '') {
    $like1 = $params[0];
    $like2 = $params[1];
    $like3 = $params[2];
    $stmt->bind_param($types, $like1, $like2, $like3);
}
$stmt->execute();
$customers = $stmt->get_result();
$stmt->close();

include 'includes/header.php';
?>
<div class="card">
    <form method="get" class="page-toolbar">
        <input type="search" name="search" value="<?php echo h($search); ?>" placeholder="Search customers...">
        <button class="btn btn-secondary" type="submit">Search</button>
        <a class="btn btn-primary" href="customers.php">Reset</a>
    </form>
</div>

<div class="panel-grid">
    <div class="form-card">
        <h3 class="section-title"><?php echo $editId > 0 ? 'Edit Customer' : 'Add Customer'; ?></h3>
        <form method="post" class="needs-validation">
            <input type="hidden" name="customer_id" value="<?php echo (int) $editId; ?>">
            <div class="form-grid">
                <div class="form-group full">
                    <label for="customer_name">Customer Name</label>
                    <input type="text" id="customer_name" name="customer_name" required value="<?php echo h($customer['Customer_Name']); ?>">
                </div>
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" required value="<?php echo h($customer['Phone']); ?>">
                </div>
                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender" required>
                        <option value="Male" <?php echo $customer['Gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo $customer['Gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo $customer['Gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="age">Age</label>
                    <input type="number" id="age" name="age" min="1" required value="<?php echo h($customer['Age']); ?>">
                </div>
            </div>
            <div class="form-actions" style="margin-top: 16px;">
                <button type="submit" class="btn btn-primary"><?php echo $editId > 0 ? 'Update Customer' : 'Save Customer'; ?></button>
                <?php if ($editId > 0): ?>
                    <a class="btn btn-secondary" href="customers.php">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="table-card">
        <h3 class="section-title">Customer List</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Gender</th>
                        <th>Age</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($customers && $customers->num_rows > 0): ?>
                        <?php while ($row = $customers->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo h($row['Customer_ID']); ?></td>
                                <td><?php echo h($row['Customer_Name']); ?></td>
                                <td><?php echo h($row['Phone']); ?></td>
                                <td><?php echo h($row['Gender']); ?></td>
                                <td><?php echo h($row['Age']); ?></td>
                                <td>
                                    <div class="table-actions">
                                        <a class="btn btn-secondary" href="customers.php?edit=<?php echo (int) $row['Customer_ID']; ?>">Edit</a>
                                        <a class="btn btn-danger" href="customers.php?delete=<?php echo (int) $row['Customer_ID']; ?>" onclick="return confirm('Delete this customer?');">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6">No customers found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>