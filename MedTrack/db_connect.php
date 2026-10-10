<?php
// Database connection file for Smart Pharmacy Inventory Management System.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$username = 'root';
$password = 'harshit';
$database = 'meditrack_db';
$port = 3306;

mysqli_report(MYSQLI_REPORT_OFF);

function importSchemaIfNeeded(mysqli $conn)
{
    $check = $conn->query("SHOW TABLES LIKE 'admin_users'");
    if ($check && $check->num_rows > 0) {
        $check->close();
        return;
    }

    $sqlFile = __DIR__ . '/sql/meditrack_db.sql';
    if (!is_file($sqlFile)) {
        throw new RuntimeException('SQL file not found: ' . $sqlFile);
    }

    $sql = file_get_contents($sqlFile);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('SQL file is empty or could not be read.');
    }

    if (!$conn->multi_query($sql)) {
        throw new RuntimeException('Could not import SQL schema: ' . $conn->error);
    }

    do {
        $result = $conn->store_result();
        if ($result instanceof mysqli_result) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());

    if ($conn->errno) {
        throw new RuntimeException('SQL import finished with error: ' . $conn->error);
    }
}

function importDeliverySchemaIfNeeded(mysqli $conn)
{
    $check = $conn->query("SHOW TABLES LIKE 'delivery_agents'");
    if ($check && $check->num_rows > 0) {
        $check->close();
        return;
    }

    $sqlFile = __DIR__ . '/sql/meditrack_delivery.sql';
    if (!is_file($sqlFile)) {
        return;
    }

    $sql = file_get_contents($sqlFile);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('Delivery SQL file is empty or could not be read.');
    }

    if (!$conn->multi_query($sql)) {
        throw new RuntimeException('Could not import delivery schema: ' . $conn->error);
    }

    do {
        $result = $conn->store_result();
        if ($result instanceof mysqli_result) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());

    if ($conn->errno) {
        throw new RuntimeException('Delivery schema import finished with error: ' . $conn->error);
    }
}

function ensureMedicineStatusColumn(mysqli $conn)
{
    $result = $conn->query("SHOW COLUMNS FROM medicine LIKE 'Is_Active'");
    if ($result && $result->num_rows > 0) {
        $result->close();
        return;
    }

    if (!$conn->query("ALTER TABLE medicine ADD COLUMN Is_Active TINYINT(1) NOT NULL DEFAULT 1 AFTER Medicine_Image")) {
        throw new RuntimeException('Could not add medicine status column: ' . $conn->error);
    }
}

function ensureMultiRoleAuthSchema(mysqli $conn)
{
    $columnExists = function (string $table, string $column) use ($conn): bool {
        $result = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '" . $conn->real_escape_string($column) . "'");
        $exists = $result && $result->num_rows > 0;
        if ($result instanceof mysqli_result) {
            $result->free();
        }
        return $exists;
    };

    $indexExists = function (string $table, string $indexName) use ($conn): bool {
        $result = $conn->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '" . $conn->real_escape_string($indexName) . "'");
        $exists = $result && $result->num_rows > 0;
        if ($result instanceof mysqli_result) {
            $result->free();
        }
        return $exists;
    };

    $foreignKeyExists = function (string $table, string $constraintName) use ($conn): bool {
        $sql = "SELECT COUNT(*) AS total FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = '" . $conn->real_escape_string($table) . "' AND CONSTRAINT_NAME = '" . $conn->real_escape_string($constraintName) . "' AND CONSTRAINT_TYPE = 'FOREIGN KEY'";
        $result = $conn->query($sql);
        $row = $result ? $result->fetch_assoc() : ['total' => 0];
        if ($result instanceof mysqli_result) {
            $result->free();
        }
        return (int) ($row['total'] ?? 0) > 0;
    };

    $run = function (string $sql) use ($conn): void {
        if (!$conn->query($sql)) {
            throw new RuntimeException('Could not create role schema: ' . $conn->error . ' | SQL: ' . $sql);
        }
    };

    $run('ALTER TABLE admin_users MODIFY password VARCHAR(255) NOT NULL');

    if (!$columnExists('user_orders', 'Order_Status')) {
        $run("ALTER TABLE user_orders ADD COLUMN Order_Status ENUM('Pending', 'Confirmed', 'Processing', 'Delivered', 'Cancelled') NOT NULL DEFAULT 'Pending' AFTER Order_Number");
    } else {
        $run("ALTER TABLE user_orders MODIFY Order_Status ENUM('Pending', 'Confirmed', 'Processing', 'Delivered', 'Cancelled') NOT NULL DEFAULT 'Pending'");
    }

    if (!$columnExists('user_orders', 'Payment_Status')) {
        $run("ALTER TABLE user_orders ADD COLUMN Payment_Status ENUM('Unpaid', 'Paid', 'Refunded') NOT NULL DEFAULT 'Unpaid' AFTER Order_Status");
    } else {
        $run("ALTER TABLE user_orders MODIFY Payment_Status ENUM('Unpaid', 'Paid', 'Refunded') NOT NULL DEFAULT 'Unpaid'");
    }

    if (!$columnExists('carts', 'Cart_Status')) {
        $run("ALTER TABLE carts ADD COLUMN Cart_Status ENUM('active', 'converted', 'abandoned') NOT NULL DEFAULT 'active' AFTER User_ID");
    }

    if (!$indexExists('carts', 'uq_carts_user_active')) {
        $run('ALTER TABLE carts ADD UNIQUE KEY uq_carts_user_active (User_ID, Cart_Status)');
    }

    if ($foreignKeyExists('delivery_orders', 'fk_delivery_bill')) {
        $run('ALTER TABLE delivery_orders DROP FOREIGN KEY fk_delivery_bill');
    }

    if ($foreignKeyExists('delivery_orders', 'fk_delivery_agent')) {
        $run('ALTER TABLE delivery_orders DROP FOREIGN KEY fk_delivery_agent');
    }

    if (!$columnExists('delivery_orders', 'bill_id')) {
        $run('ALTER TABLE delivery_orders ADD COLUMN bill_id INT DEFAULT NULL FIRST');
    } else {
        $run('ALTER TABLE delivery_orders MODIFY bill_id INT DEFAULT NULL');
    }

    if (!$columnExists('delivery_orders', 'order_id')) {
        $run('ALTER TABLE delivery_orders ADD COLUMN order_id INT DEFAULT NULL AFTER bill_id');
    }

    if (!$columnExists('delivery_orders', 'user_id')) {
        $run('ALTER TABLE delivery_orders ADD COLUMN user_id INT DEFAULT NULL AFTER order_id');
    }

    if (!$columnExists('delivery_orders', 'reference_type')) {
        $run("ALTER TABLE delivery_orders ADD COLUMN reference_type VARCHAR(20) NOT NULL DEFAULT 'bill' AFTER user_id");
    }

    if (!$columnExists('delivery_orders', 'reference_number')) {
        $run('ALTER TABLE delivery_orders ADD COLUMN reference_number VARCHAR(40) DEFAULT NULL AFTER reference_type');
    }

    if (!$columnExists('delivery_orders', 'customer_email')) {
        $run('ALTER TABLE delivery_orders ADD COLUMN customer_email VARCHAR(120) DEFAULT NULL AFTER customer_phone');
    }

    if (!$foreignKeyExists('delivery_orders', 'fk_delivery_bill')) {
        $run('ALTER TABLE delivery_orders ADD CONSTRAINT fk_delivery_bill FOREIGN KEY (bill_id) REFERENCES bill(Bill_ID) ON DELETE CASCADE ON UPDATE CASCADE');
    }

    if (!$foreignKeyExists('delivery_orders', 'fk_delivery_order')) {
        $run('ALTER TABLE delivery_orders ADD CONSTRAINT fk_delivery_order FOREIGN KEY (order_id) REFERENCES user_orders(Order_ID) ON DELETE CASCADE ON UPDATE CASCADE');
    }

    if (!$foreignKeyExists('delivery_orders', 'fk_delivery_user')) {
        $run('ALTER TABLE delivery_orders ADD CONSTRAINT fk_delivery_user FOREIGN KEY (user_id) REFERENCES users(User_ID) ON DELETE CASCADE ON UPDATE CASCADE');
    }

    if (!$foreignKeyExists('delivery_orders', 'fk_delivery_agent')) {
        $run('ALTER TABLE delivery_orders ADD CONSTRAINT fk_delivery_agent FOREIGN KEY (agent_id) REFERENCES delivery_agents(delivery_agent_id) ON DELETE SET NULL ON UPDATE CASCADE');
    }

    if (!$columnExists('delivery_orders', 'delivery_status')) {
        $run("ALTER TABLE delivery_orders ADD COLUMN delivery_status ENUM('Order Placed', 'Processing', 'Packed', 'Out for Delivery', 'Delivered') NOT NULL DEFAULT 'Order Placed' AFTER estimated_delivery_date");
    }

    if (!$columnExists('delivery_orders', 'created_at')) {
        $run('ALTER TABLE delivery_orders ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }

    if (!$columnExists('delivery_orders', 'updated_at')) {
        $run('ALTER TABLE delivery_orders ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    }

    if ($columnExists('employee_login', 'Password_Hash') && !$columnExists('employee_login', 'Password')) {
        $run('ALTER TABLE employee_login CHANGE COLUMN Password_Hash Password VARCHAR(255) NOT NULL');
    }

    if ($columnExists('users', 'Password_Hash') && !$columnExists('users', 'Password')) {
        $run('ALTER TABLE users CHANGE COLUMN Password_Hash Password VARCHAR(255) NOT NULL');
    }

    if (!$columnExists('employee_login', 'Status')) {
        $run("ALTER TABLE employee_login ADD COLUMN Status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active' AFTER Password");
    }

    if ($columnExists('employee_login', 'Is_Active')) {
        $run("UPDATE employee_login SET Status = CASE WHEN Is_Active = 1 THEN 'Active' ELSE 'Inactive' END");
    }

    $employeeCountResult = $conn->query('SELECT COUNT(*) AS total FROM employee_login');
    $employeeCount = $employeeCountResult ? (int) ($employeeCountResult->fetch_assoc()['total'] ?? 0) : 0;
    if ($employeeCount === 0) {
        $employeeRowResult = $conn->query('SELECT Employee_ID, Employee_Name FROM employee ORDER BY Employee_ID ASC LIMIT 1');
        $employeeRow = $employeeRowResult ? $employeeRowResult->fetch_assoc() : null;
        if ($employeeRow) {
            $username = 'employee';
            $password = 'employee123';
            $status = 'Active';
            $stmt = $conn->prepare('INSERT INTO employee_login (Employee_ID, Username, Password, Status) VALUES (?, ?, ?, ?)');
            if ($stmt) {
                $employeeId = (int) $employeeRow['Employee_ID'];
                $stmt->bind_param('isss', $employeeId, $username, $password, $status);
                if (!$stmt->execute()) {
                    $stmt->close();
                    throw new RuntimeException('Could not seed employee login: ' . $stmt->error);
                }
                $stmt->close();
            }
        }
    }

    $userCountResult = $conn->query('SELECT COUNT(*) AS total FROM users');
    $userCount = $userCountResult ? (int) ($userCountResult->fetch_assoc()['total'] ?? 0) : 0;
    if ($userCount === 0) {
        $fullName = 'Demo Customer';
        $email = 'customer@medtrack.local';
        $phone = '9999999999';
        $password = 'customer123';
        $stmt = $conn->prepare('INSERT INTO users (Full_Name, Email, Phone, Password) VALUES (?, ?, ?, ?)');
        if ($stmt) {
            $stmt->bind_param('ssss', $fullName, $email, $phone, $password);
            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();
                throw new RuntimeException('Could not seed customer account: ' . $error);
            }
            $stmt->close();
        }
    }

    $adminStmt = $conn->prepare("UPDATE admin_users SET password = 'admin123' WHERE username = 'admin'");
    if ($adminStmt) {
        $adminStmt->execute();
        $adminStmt->close();
    }

    $employeeDemoStmt = $conn->prepare("UPDATE employee_login SET Password = 'employee123', Status = 'Active', Is_Active = 1 WHERE Username = 'employee'");
    if ($employeeDemoStmt) {
        $employeeDemoStmt->execute();
        $employeeDemoStmt->close();
    }

    $customerDemoStmt = $conn->prepare("UPDATE users SET Password = 'customer123', Is_Active = 1 WHERE Email = 'customer@medtrack.local'");
    if ($customerDemoStmt) {
        $customerDemoStmt->execute();
        $customerDemoStmt->close();
    }

    $run("UPDATE user_orders SET Order_Status = 'Pending' WHERE LOWER(Order_Status) = 'pending'");
    $run("UPDATE user_orders SET Order_Status = 'Confirmed' WHERE LOWER(Order_Status) = 'confirmed'");
    $run("UPDATE user_orders SET Order_Status = 'Processing' WHERE LOWER(Order_Status) IN ('processing', 'packed', 'shipped')");
    $run("UPDATE user_orders SET Order_Status = 'Delivered' WHERE LOWER(Order_Status) = 'delivered'");
    $run("UPDATE user_orders SET Order_Status = 'Cancelled' WHERE LOWER(Order_Status) = 'cancelled'");

    $run("UPDATE user_orders SET Payment_Status = 'Unpaid' WHERE LOWER(Payment_Status) = 'unpaid'");
    $run("UPDATE user_orders SET Payment_Status = 'Paid' WHERE LOWER(Payment_Status) = 'paid'");
    $run("UPDATE user_orders SET Payment_Status = 'Refunded' WHERE LOWER(Payment_Status) = 'refunded'");
}

$conn = new mysqli($host, $username, $password, '', $port);

if ($conn->connect_error) {
    die('Database server connection failed: ' . $conn->connect_error);
}

if (!$conn->query("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
    die('Database creation failed: ' . $conn->error);
}

if (!$conn->select_db($database)) {
    die('Database selection failed: ' . $conn->error);
}

$conn->set_charset('utf8mb4');

try {
    importSchemaIfNeeded($conn);
    importDeliverySchemaIfNeeded($conn);
    ensureMultiRoleAuthSchema($conn);
    ensureMedicineStatusColumn($conn);
} catch (Throwable $exception) {
    die('Database setup failed: ' . $exception->getMessage());
}
?>