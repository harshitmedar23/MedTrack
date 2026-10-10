<?php
require_once 'includes/functions.php';
requireAdmin();

$pageTitle = 'Employees';
$error = '';
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

function normalizeEmployeeStatusValue($status)
{
    $status = strtolower(trim((string) $status));
    return $status === 'inactive' ? 'Inactive' : 'Active';
}

function fetchEmployeeRecord(mysqli $conn, int $loginId)
{
    $stmt = $conn->prepare(
        'SELECT l.Employee_Login_ID, l.Employee_ID, l.Username, l.Password, l.Status, l.Is_Active, e.Employee_Name, e.Role, e.Phone, e.Salary
         FROM employee_login l
         INNER JOIN employee e ON e.Employee_ID = l.Employee_ID
         WHERE l.Employee_Login_ID = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $loginId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'save_employee') {
        $loginId = (int) ($_POST['employee_login_id'] ?? 0);
        $fullName = trim($_POST['employee_name'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $salary = (float) ($_POST['salary'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $status = normalizeEmployeeStatusValue($_POST['status'] ?? 'Active');

        if ($fullName === '' || $role === '' || $phone === '' || $username === '') {
            $error = 'Please fill in the employee details and username.';
        } elseif ($salary <= 0) {
            $error = 'Please enter a valid salary.';
        } elseif ($loginId === 0 && $password === '') {
            $error = 'Please set a password for the new employee account.';
        } else {
            $conn->begin_transaction();

            try {
                if ($loginId > 0) {
                    $existing = fetchEmployeeRecord($conn, $loginId);
                    if (!$existing) {
                        throw new RuntimeException('Employee account not found.');
                    }

                    $employeeId = (int) $existing['Employee_ID'];

                    $updateEmployee = $conn->prepare('UPDATE employee SET Employee_Name = ?, Role = ?, Phone = ?, Salary = ? WHERE Employee_ID = ?');
                    if (!$updateEmployee) {
                        throw new RuntimeException('Could not prepare employee update.');
                    }
                    $updateEmployee->bind_param('sssdi', $fullName, $role, $phone, $salary, $employeeId);
                    if (!$updateEmployee->execute()) {
                        throw new RuntimeException('Could not update employee profile.');
                    }
                    $updateEmployee->close();

                    if ($password !== '') {
                        $updateLogin = $conn->prepare('UPDATE employee_login SET Username = ?, Password = ?, Status = ?, Is_Active = ?, Updated_At = CURRENT_TIMESTAMP WHERE Employee_Login_ID = ?');
                        if (!$updateLogin) {
                            throw new RuntimeException('Could not prepare login update.');
                        }
                        $isActive = $status === 'Active' ? 1 : 0;
                        $updateLogin->bind_param('sssii', $username, $password, $status, $isActive, $loginId);
                    } else {
                        $updateLogin = $conn->prepare('UPDATE employee_login SET Username = ?, Status = ?, Is_Active = ?, Updated_At = CURRENT_TIMESTAMP WHERE Employee_Login_ID = ?');
                        if (!$updateLogin) {
                            throw new RuntimeException('Could not prepare login update.');
                        }
                        $isActive = $status === 'Active' ? 1 : 0;
                        $updateLogin->bind_param('ssii', $username, $status, $isActive, $loginId);
                    }

                    if (!$updateLogin->execute()) {
                        throw new RuntimeException('Could not update employee login.');
                    }
                    $updateLogin->close();

                    $conn->commit();
                    setFlash('success', 'Employee account updated successfully.');
                    header('Location: employees.php');
                    exit;
                }

                $checkStmt = $conn->prepare('SELECT Employee_Login_ID FROM employee_login WHERE Username = ? LIMIT 1');
                if (!$checkStmt) {
                    throw new RuntimeException('Could not prepare username check.');
                }
                $checkStmt->bind_param('s', $username);
                $checkStmt->execute();
                $existingLogin = $checkStmt->get_result();
                $existingRow = $existingLogin ? $existingLogin->fetch_assoc() : null;
                $checkStmt->close();

                if ($existingRow) {
                    throw new RuntimeException('That username is already in use.');
                }

                $insertEmployee = $conn->prepare('INSERT INTO employee (Employee_Name, Role, Phone, Salary) VALUES (?, ?, ?, ?)');
                if (!$insertEmployee) {
                    throw new RuntimeException('Could not prepare employee insert.');
                }
                $insertEmployee->bind_param('sssd', $fullName, $role, $phone, $salary);
                if (!$insertEmployee->execute()) {
                    throw new RuntimeException('Could not create employee record.');
                }
                $employeeId = (int) $conn->insert_id;
                $insertEmployee->close();

                $isActive = $status === 'Active' ? 1 : 0;
                $insertLogin = $conn->prepare('INSERT INTO employee_login (Employee_ID, Username, Password, Status, Is_Active) VALUES (?, ?, ?, ?, ?)');
                if (!$insertLogin) {
                    throw new RuntimeException('Could not prepare employee login insert.');
                }
                $insertLogin->bind_param('isssi', $employeeId, $username, $password, $status, $isActive);
                if (!$insertLogin->execute()) {
                    throw new RuntimeException('Could not create employee login.');
                }
                $insertLogin->close();

                $conn->commit();
                setFlash('success', 'Employee account created successfully.');
                header('Location: employees.php');
                exit;
            } catch (Throwable $exception) {
                $conn->rollback();
                $error = $exception->getMessage();
            }
        }
    } elseif ($action === 'toggle_status') {
        $loginId = (int) ($_POST['employee_login_id'] ?? 0);
        $status = normalizeEmployeeStatusValue($_POST['status'] ?? 'Inactive');
        $isActive = $status === 'Active' ? 1 : 0;

        $stmt = $conn->prepare('UPDATE employee_login SET Status = ?, Is_Active = ?, Updated_At = CURRENT_TIMESTAMP WHERE Employee_Login_ID = ?');
        if ($stmt) {
            $stmt->bind_param('sii', $status, $isActive, $loginId);
            if ($stmt->execute()) {
                $stmt->close();
                setFlash('success', 'Employee status updated successfully.');
                header('Location: employees.php');
                exit;
            }
            $stmt->close();
            $error = 'Could not update the employee status.';
        } else {
            $error = 'Could not prepare the status update.';
        }
    } elseif ($action === 'reset_password') {
        $loginId = (int) ($_POST['employee_login_id'] ?? 0);
        $newPassword = trim((string) ($_POST['new_password'] ?? 'employee123'));

        if ($newPassword === '') {
            $error = 'Please enter a password to reset.';
        } else {
            $stmt = $conn->prepare('UPDATE employee_login SET Password = ?, Updated_At = CURRENT_TIMESTAMP WHERE Employee_Login_ID = ?');
            if ($stmt) {
                $stmt->bind_param('si', $newPassword, $loginId);
                if ($stmt->execute()) {
                    $stmt->close();
                    setFlash('success', 'Employee password reset successfully.');
                    header('Location: employees.php');
                    exit;
                }
                $stmt->close();
                $error = 'Could not reset the employee password.';
            } else {
                $error = 'Could not prepare the password reset.';
            }
        }
    }
}

$editingEmployee = $editId > 0 ? fetchEmployeeRecord($conn, $editId) : null;
$employeeList = $conn->query(
    "SELECT l.Employee_Login_ID, l.Employee_ID, l.Username, l.Password, l.Status, l.Is_Active, l.Last_Login_At, l.Created_At, e.Employee_Name, e.Role, e.Phone, e.Salary
     FROM employee_login l
     INNER JOIN employee e ON e.Employee_ID = l.Employee_ID
     ORDER BY l.Created_At DESC, e.Employee_Name ASC"
);

$employeeCount = getCount($conn, 'employee');
$activeEmployeeCount = getCount($conn, 'employee_login', "WHERE Status = 'Active'");

include 'includes/header.php';
?>
<div class="hero-strip">
    <div>
        <h2>Employee Management</h2>
        <p>Create, update, deactivate, and reset employee accounts from one place.</p>
    </div>
    <div class="dashboard-hero-metrics">
        <div>
            <span class="muted">Employees</span>
            <strong><?php echo (int) $employeeCount; ?></strong>
        </div>
        <div>
            <span class="muted">Active accounts</span>
            <strong><?php echo (int) $activeEmployeeCount; ?></strong>
        </div>
    </div>
</div>

<?php echo renderFlash(); ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?php echo h($error); ?></div>
<?php endif; ?>

<div class="panel-grid">
    <div class="card">
        <h3 class="section-title"><?php echo $editingEmployee ? 'Edit Employee' : 'Add Employee'; ?></h3>
        <form method="post" class="needs-validation">
            <input type="hidden" name="action" value="save_employee">
            <input type="hidden" name="employee_login_id" value="<?php echo $editingEmployee ? (int) $editingEmployee['Employee_Login_ID'] : 0; ?>">
            <div class="form-grid">
                <div class="form-group full">
                    <label for="employee_name">Employee Name</label>
                    <input type="text" id="employee_name" name="employee_name" required value="<?php echo h($_POST['employee_name'] ?? ($editingEmployee['Employee_Name'] ?? '')); ?>">
                </div>
                <div class="form-group full">
                    <label for="role">Role</label>
                    <input type="text" id="role" name="role" required value="<?php echo h($_POST['role'] ?? ($editingEmployee['Role'] ?? '')); ?>">
                </div>
                <div class="form-group full">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" required value="<?php echo h($_POST['phone'] ?? ($editingEmployee['Phone'] ?? '')); ?>">
                </div>
                <div class="form-group full">
                    <label for="salary">Salary</label>
                    <input type="number" step="0.01" min="0" id="salary" name="salary" required value="<?php echo h($_POST['salary'] ?? ($editingEmployee['Salary'] ?? '')); ?>">
                </div>
                <div class="form-group full">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required value="<?php echo h($_POST['username'] ?? ($editingEmployee['Username'] ?? '')); ?>">
                </div>
                <div class="form-group full">
                    <label for="password">Password <?php echo $editingEmployee ? '(leave blank to keep current)' : ''; ?></label>
                    <input type="text" id="password" name="password" <?php echo $editingEmployee ? '' : 'required'; ?> value="<?php echo h($_POST['password'] ?? ''); ?>">
                </div>
                <div class="form-group full">
                    <label for="status">Status</label>
                    <?php $statusValue = $_POST['status'] ?? ($editingEmployee['Status'] ?? 'Active'); ?>
                    <select id="status" name="status" required>
                        <option value="Active" <?php echo normalizeEmployeeStatusValue($statusValue) === 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo normalizeEmployeeStatusValue($statusValue) === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="form-actions" style="margin-top: 18px;">
                <button type="submit" class="btn btn-primary"><?php echo $editingEmployee ? 'Update Employee' : 'Create Employee'; ?></button>
                <?php if ($editingEmployee): ?>
                    <a class="btn btn-secondary" href="employees.php">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="table-card">
        <h3 class="section-title">Employee Accounts</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Username</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($employeeList && $employeeList->num_rows > 0): ?>
                        <?php while ($row = $employeeList->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo h($row['Employee_Name']); ?></td>
                                <td><?php echo h($row['Role']); ?></td>
                                <td><?php echo h($row['Username']); ?></td>
                                <td><span class="order-status-badge"><?php echo h($row['Status']); ?></span></td>
                                <td>
                                    <div class="btn-row" style="flex-wrap: wrap; gap: 8px;">
                                        <a class="btn btn-secondary" href="employees.php?edit=<?php echo (int) $row['Employee_Login_ID']; ?>">Edit</a>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="employee_login_id" value="<?php echo (int) $row['Employee_Login_ID']; ?>">
                                            <input type="hidden" name="status" value="<?php echo h($row['Status'] === 'Active' ? 'Inactive' : 'Active'); ?>">
                                            <button type="submit" class="btn btn-secondary"><?php echo $row['Status'] === 'Active' ? 'Deactivate' : 'Activate'; ?></button>
                                        </form>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="employee_login_id" value="<?php echo (int) $row['Employee_Login_ID']; ?>">
                                            <input type="hidden" name="new_password" value="employee123">
                                            <button type="submit" class="btn btn-primary">Reset Password</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5">No employee accounts found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>