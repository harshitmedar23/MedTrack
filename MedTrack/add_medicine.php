<?php
require_once 'includes/functions.php';
requireAdmin();

$pageTitle = 'Add / Edit Medicine';

$editId = (int) ($_GET['id'] ?? 0);
$medicine = [
    'Medicine_Name' => '',
    'Category' => '',
    'Price' => '',
    'Quantity' => '',
    'Expiry_Date' => '',
    'Medicine_Image' => '',
    'Supplier_ID' => '',
];

if ($editId > 0) {
    $stmt = $conn->prepare('SELECT * FROM medicine WHERE Medicine_ID = ?');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $medicine = $result->fetch_assoc() ?: $medicine;
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $medicineId = (int) ($_POST['medicine_id'] ?? 0);
    $medicineName = trim($_POST['medicine_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $quantity = (int) ($_POST['quantity'] ?? 0);
    $expiryDate = trim($_POST['expiry_date'] ?? '');
    $supplierId = (int) ($_POST['supplier_id'] ?? 0);
    $imagePath = $medicine['Medicine_Image'] ?? '';
    $uploadFailed = false;

    if (!empty($_FILES['medicine_image']['name']) && ($_FILES['medicine_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/medicine/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = $_FILES['medicine_image']['type'] ?? '';
        $fileName = basename($_FILES['medicine_image']['name']);

        if (in_array($fileType, $allowedTypes, true)) {
            $safeName = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $fileName);
            $targetPath = $uploadDir . $safeName;

            if (move_uploaded_file($_FILES['medicine_image']['tmp_name'], $targetPath)) {
                $imagePath = 'uploads/medicine/' . $safeName;
            } else {
                setFlash('error', 'Medicine image upload failed.');
                $uploadFailed = true;
            }
        } else {
            setFlash('error', 'Please upload a valid image file.');
            $uploadFailed = true;
        }
    }

    if (!$uploadFailed && ($medicineName === '' || $category === '' || $price <= 0 || $quantity < 0 || $expiryDate === '' || $supplierId <= 0)) {
        setFlash('error', 'Please complete all medicine fields correctly.');
    } elseif (!$uploadFailed) {
        if ($medicineId > 0) {
            $stmt = $conn->prepare('UPDATE medicine SET Medicine_Name = ?, Category = ?, Price = ?, Quantity = ?, Expiry_Date = ?, Medicine_Image = ?, Supplier_ID = ? WHERE Medicine_ID = ?');
            $stmt->bind_param('ssdissii', $medicineName, $category, $price, $quantity, $expiryDate, $imagePath, $supplierId, $medicineId);
            $message = 'Medicine updated successfully.';
        } else {
            $stmt = $conn->prepare('INSERT INTO medicine (Medicine_Name, Category, Price, Quantity, Expiry_Date, Medicine_Image, Supplier_ID) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssdissi', $medicineName, $category, $price, $quantity, $expiryDate, $imagePath, $supplierId);
            $message = 'Medicine added successfully.';
        }

        if ($stmt && $stmt->execute()) {
            setFlash('success', $message);
            $stmt->close();
            header('Location: medicines.php');
            exit;
        }

        setFlash('error', 'Could not save medicine details.');
        if ($stmt) {
            $stmt->close();
        }
    }
}

$suppliers = $conn->query('SELECT Supplier_ID, Supplier_Name FROM supplier ORDER BY Supplier_Name ASC');

include 'includes/header.php';
?>
<div class="form-card">
    <form method="post" class="needs-validation" enctype="multipart/form-data">
        <input type="hidden" name="medicine_id" value="<?php echo (int) $editId; ?>">
        <div class="form-grid">
            <div class="form-group full">
                <label for="medicine_name">Medicine Name</label>
                <input type="text" id="medicine_name" name="medicine_name" required value="<?php echo h($medicine['Medicine_Name']); ?>">
            </div>
            <div class="form-group">
                <label for="category">Category</label>
                <input type="text" id="category" name="category" required value="<?php echo h($medicine['Category']); ?>">
            </div>
            <div class="form-group">
                <label for="price">Price</label>
                <input type="number" step="0.01" min="0.01" id="price" name="price" required value="<?php echo h($medicine['Price']); ?>">
            </div>
            <div class="form-group">
                <label for="quantity">Quantity</label>
                <input type="number" min="0" id="quantity" name="quantity" required value="<?php echo h($medicine['Quantity']); ?>">
            </div>
            <div class="form-group">
                <label for="expiry_date">Expiry Date</label>
                <input type="date" id="expiry_date" name="expiry_date" required value="<?php echo h($medicine['Expiry_Date']); ?>">
            </div>
            <div class="form-group full">
                <label for="supplier_id">Supplier</label>
                <select id="supplier_id" name="supplier_id" required>
                    <option value="">Select Supplier</option>
                    <?php if ($suppliers && $suppliers->num_rows > 0): ?>
                        <?php while ($row = $suppliers->fetch_assoc()): ?>
                            <option value="<?php echo (int) $row['Supplier_ID']; ?>" <?php echo ((int) $medicine['Supplier_ID'] === (int) $row['Supplier_ID']) ? 'selected' : ''; ?>>
                                <?php echo h($row['Supplier_Name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group full">
                <label for="medicine_image">Medicine Image</label>
                <input type="file" id="medicine_image" name="medicine_image" accept="image/*">
                <?php $previewImage = medicineImageUrl($medicine['Medicine_Image'] ?? ''); ?>
                <?php if ($previewImage !== ''): ?>
                    <div style="margin-top: 10px;">
                        <img class="medicine-preview" src="<?php echo h($previewImage); ?>" alt="Medicine preview">
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-actions" style="margin-top: 16px;">
            <button type="submit" class="btn btn-primary"><?php echo $editId > 0 ? 'Update Medicine' : 'Save Medicine'; ?></button>
            <a class="btn btn-secondary" href="medicines.php">Back to Medicines</a>
        </div>
    </form>
</div>
<?php include 'includes/footer.php'; ?>