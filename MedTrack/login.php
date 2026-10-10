<?php
require_once 'includes/functions.php';

redirectAuthenticatedUser();

$error = '';

function findLoginMatch(mysqli $conn, string $login, string $password): array
{
    $login = trim($login);
    $password = (string) $password;

    if ($login === '' || $password === '') {
        return [false, null, null, 'Please enter your username/email and password.'];
    }

    $adminStmt = $conn->prepare('SELECT id, full_name, username, password FROM admin_users WHERE username = ? LIMIT 1');
    if ($adminStmt) {
        $adminStmt->bind_param('s', $login);
        $adminStmt->execute();
        $adminResult = $adminStmt->get_result();
        $admin = $adminResult ? $adminResult->fetch_assoc() : null;
        $adminStmt->close();

        if ($admin && verifyStoredPassword($password, $admin['password'])) {
            return [true, 'admin', $admin, ''];
        }
    }

    $employeeStmt = $conn->prepare(
        'SELECT l.Employee_Login_ID, l.Employee_ID, l.Username, l.Password, l.Status, l.Is_Active, e.Employee_Name
         FROM employee_login l
         INNER JOIN employee e ON e.Employee_ID = l.Employee_ID
         WHERE l.Username = ?
         LIMIT 1'
    );
    if ($employeeStmt) {
        $employeeStmt->bind_param('s', $login);
        $employeeStmt->execute();
        $employeeResult = $employeeStmt->get_result();
        $employee = $employeeResult ? $employeeResult->fetch_assoc() : null;
        $employeeStmt->close();

        $employeeStatus = strtolower((string) ($employee['Status'] ?? ''));
        $employeeActive = $employeeStatus === 'active' || (int) ($employee['Is_Active'] ?? 0) === 1;

        if ($employee && $employeeActive && verifyStoredPassword($password, $employee['Password'])) {
            return [true, 'employee', $employee, ''];
        }
    }

    $customerStmt = $conn->prepare(
        'SELECT User_ID, Full_Name, Email, Phone, Password, Is_Active
         FROM users
         WHERE Email = ? OR Phone = ?
         LIMIT 1'
    );
    if ($customerStmt) {
        $customerStmt->bind_param('ss', $login, $login);
        $customerStmt->execute();
        $customerResult = $customerStmt->get_result();
        $customer = $customerResult ? $customerResult->fetch_assoc() : null;
        $customerStmt->close();

        if ($customer && (int) $customer['Is_Active'] === 1 && verifyStoredPassword($password, $customer['Password'])) {
            return [true, 'customer', $customer, ''];
        }
    }

    return [false, null, null, 'Invalid login details. Please try again.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    [$ok, $role, $user, $message] = findLoginMatch($conn, $login, $password);

    if (!$ok) {
        $error = $message;
    } elseif ($role === 'admin') {
        startAuthSession('admin', [
            'id' => (int) $user['id'],
            'name' => $user['full_name'],
            'username' => $user['username'],
        ]);
        setFlash('success', 'Welcome back, ' . $user['full_name'] . '.');
        header('Location: dashboard.php');
        exit;
    } elseif ($role === 'employee') {
        $updateStmt = $conn->prepare('UPDATE employee_login SET Last_Login_At = NOW() WHERE Employee_Login_ID = ?');
        if ($updateStmt) {
            $loginId = (int) $user['Employee_Login_ID'];
            $updateStmt->bind_param('i', $loginId);
            $updateStmt->execute();
            $updateStmt->close();
        }

        startAuthSession('employee', [
            'login_id' => (int) $user['Employee_Login_ID'],
            'employee_id' => (int) $user['Employee_ID'],
            'name' => $user['Employee_Name'],
            'username' => $user['Username'],
        ]);
        setFlash('success', 'Welcome back, ' . $user['Employee_Name'] . '.');
        header('Location: employee_dashboard.php');
        exit;
    } elseif ($role === 'customer') {
        $updateStmt = $conn->prepare('UPDATE users SET Last_Login_At = NOW() WHERE User_ID = ?');
        if ($updateStmt) {
            $userId = (int) $user['User_ID'];
            $updateStmt->bind_param('i', $userId);
            $updateStmt->execute();
            $updateStmt->close();
        }

        startAuthSession('customer', [
            'id' => (int) $user['User_ID'],
            'name' => $user['Full_Name'],
            'email' => $user['Email'],
        ]);
        setFlash('success', 'Welcome back, ' . $user['Full_Name'] . '.');
        header('Location: user_dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Pharmacy Login</title>
    <link rel="stylesheet" href="assets/css/style.css?v=20260531a">
    <script defer src="assets/js/app.js?v=20260531a"></script>
</head>
<body>
<div class="auth-page">
    <div class="auth-shell glass-card">
        <section class="auth-hero-panel">
            <div class="brand-pill">Smart Pharmacy</div>
            <h1>Modern medicine ordering, billing, and delivery in one workspace.</h1>
            <p>Sign in as admin, employee, or customer using your username, email, or phone.</p>
            <div class="auth-feature-list">
                <div><strong>Fast access</strong><span>Customer, staff, and admin roles</span></div>
                <div><strong>Secure flow</strong><span>Session-based access control</span></div>
                <div><strong>Compact UI</strong><span>Built for phones and desktops</span></div>
            </div>
        </section>

        <div class="auth-card">
            <h1>Sign in</h1>
            <p>Use your login ID to continue.</p>

            <?php echo renderFlash(); ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo h($error); ?></div>
            <?php endif; ?>

            <form method="post" class="needs-validation">
                <div class="form-grid">
                    <div class="form-group full">
                        <label for="login">Username, Email, or Phone</label>
                        <input type="text" id="login" name="login" required placeholder="Enter your login ID" value="<?php echo h($_POST['login'] ?? ''); ?>">
                    </div>
                    <div class="form-group full">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required placeholder="Enter password">
                    </div>
                </div>
                <div class="form-actions" style="margin-top: 18px;">
                    <button type="submit" class="btn btn-primary">Login</button>
                    <a class="btn btn-secondary" href="user_register.php">Create Account</a>
                </div>
            </form>

            <div class="alert alert-info" style="margin-top: 18px;">
                Demo credentials: admin / admin123, employee / employee123, customer@medtrack.local / customer123
            </div>
        </div>
    </div>
</div>
</body>
</html>