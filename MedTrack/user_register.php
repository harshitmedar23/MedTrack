<?php
require_once 'includes/functions.php';

redirectAuthenticatedUser();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($fullName === '' || $email === '' || $phone === '' || $password === '' || $confirmPassword === '') {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
        $error = 'Please enter a valid phone number.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $checkStmt = $conn->prepare('SELECT User_ID FROM users WHERE Email = ? OR Phone = ? LIMIT 1');
        if ($checkStmt) {
            $checkStmt->bind_param('ss', $email, $phone);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $existingUser = $checkResult ? $checkResult->fetch_assoc() : null;
            $checkStmt->close();

            if ($existingUser) {
                $error = 'An account with this email or phone already exists.';
            } else {
                $stmt = $conn->prepare('INSERT INTO users (Full_Name, Email, Phone, Password) VALUES (?, ?, ?, ?)');
                if ($stmt) {
                    $stmt->bind_param('ssss', $fullName, $email, $phone, $password);
                    if ($stmt->execute()) {
                        $stmt->close();
                        setFlash('success', 'Account created successfully. You can log in now.');
                        header('Location: login.php');
                        exit;
                    }

                    $error = 'Could not create the account.';
                    $stmt->close();
                } else {
                    $error = 'Registration query could not be prepared.';
                }
            }
        } else {
            $error = 'Could not check for existing accounts.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Registration - Smart Pharmacy</title>
    <link rel="stylesheet" href="assets/css/style.css?v=20260531a">
    <script defer src="assets/js/app.js?v=20260531a"></script>
</head>
<body>
<div class="auth-page">
    <div class="auth-shell glass-card">
        <section class="auth-hero-panel">
            <div class="brand-pill">Smart Pharmacy</div>
            <h1>Create your customer account and start ordering medicines online.</h1>
            <p>Fast registration for customers who want a compact, responsive pharmacy shopping experience.</p>
            <div class="auth-feature-list">
                <div><strong>Quick signup</strong><span>Email and phone based login</span></div>
                <div><strong>Order ready</strong><span>Shop, cart, checkout, track</span></div>
                <div><strong>Lightweight</strong><span>No extra steps or OTP flow</span></div>
            </div>
        </section>

        <div class="auth-card">
            <h1>Customer registration</h1>
            <p>Create your portal account.</p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo h($error); ?></div>
            <?php endif; ?>

            <form method="post" class="needs-validation">
                <div class="form-grid">
                    <div class="form-group full">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" required value="<?php echo h($_POST['full_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group full">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required value="<?php echo h($_POST['email'] ?? ''); ?>">
                    </div>
                    <div class="form-group full">
                        <label for="phone">Phone</label>
                        <input type="text" id="phone" name="phone" required value="<?php echo h($_POST['phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group full">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-group full">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                <div class="form-actions" style="margin-top: 18px;">
                    <button type="submit" class="btn btn-primary">Create Account</button>
                    <a class="btn btn-secondary" href="login.php">Back to Login</a>
                </div>
            </form>

            <div class="alert alert-info" style="margin-top: 18px;">
                Existing users can log in with email or phone.
            </div>
        </div>
    </div>
</div>
</body>
</html>